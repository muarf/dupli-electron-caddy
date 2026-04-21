#include "win32-printer.h"
#include <algorithm>
#include <fstream>
// Force Unicode API calls to avoid GetJobA/GetJobW mismatch
#ifndef UNICODE
#define UNICODE
#endif
#ifndef _UNICODE
#define _UNICODE
#endif
#include <gdiplus.h>
#include <iomanip>
#include <iostream>
#include <map>
#include <set>
#include <shlobj.h>
#include <shlwapi.h>
#include <string>
#include <vector>
#include <windows.h>
#include <wininet.h>

// Link necessary libraries
#pragma comment(lib, "gdiplus.lib")
#pragma comment(lib, "shlwapi.lib")
#pragma comment(lib, "wininet.lib")

// Forward declarations
std::string LPWSTRToUTF8(LPWSTR lpwstr);
std::wstring ToWString(const std::string &str);
void LogDebug(const std::string &msg);

// Helper: Enable "Keep Printed Jobs" on a printer
void EnsureKeepPrintedJobs(HANDLE hPrinter) {
  DWORD needed;
  GetPrinterW(hPrinter, 2, NULL, 0, &needed);
  if (needed > 0) {
    std::vector<BYTE> buffer(needed);
    if (GetPrinterW(hPrinter, 2, buffer.data(), needed, &needed)) {
      PRINTER_INFO_2W *pInfo = (PRINTER_INFO_2W *)buffer.data();
      if (!(pInfo->Attributes & PRINTER_ATTRIBUTE_KEEPPRINTEDJOBS)) {
        pInfo->Attributes |= PRINTER_ATTRIBUTE_KEEPPRINTEDJOBS;
        if (!SetPrinterW(hPrinter, 2, (LPBYTE)pInfo, 0)) {
          LogDebug("Failed to set PRINTER_ATTRIBUTE_KEEPPRINTEDJOBS. Error: " +
                   std::to_string(GetLastError()));
        } else {
          LogDebug("Successfully enabled KEEPPRINTEDJOBS for printer: " +
                   LPWSTRToUTF8(pInfo->pPrinterName));
        }
      }
    }
  }
}

// Global debug file path
const std::string DEBUG_LOG_PATH =
    "C:\\Users\\Dupli\\AppData\\Local\\Programs\\dupli-electron-"
    "caddy\\logs\\native_debug.log";

void LogDebug(const std::string &message) {
  std::ofstream logFile(DEBUG_LOG_PATH, std::ios::app);
  if (logFile.is_open()) {
    logFile << message << std::endl;
    logFile.close();
  }
}

// Struct JobDetails is defined in win32-printer.h

// Cache structure for SPL analysis results
struct SplAnalysisCache {
  bool isGrayscale;
  float fillRate;
  std::string timestamp;
  std::string thumbnailUrl;
  std::string documentName; // New: to verify jobId recycling
  DWORD lastFileSize;       // New: to detect if document is still growing
  DWORD totalPages;         // New: to cache detected page count
};

struct EmfConversionResult {
  std::vector<std::wstring> pngPaths;
  std::string thumbnailUrl;
  DWORD totalPages; // New: to store detected page count from PHP API
};

// Global cache for SPL analysis
std::map<std::string, SplAnalysisCache> splAnalysisCache;

std::string LPWSTRToUTF8(LPWSTR lpwstr) {
  if (lpwstr == nullptr) return "";
  int size_needed = WideCharToMultiByte(CP_UTF8, 0, lpwstr, -1, NULL, 0, NULL, NULL);
  std::string result(size_needed - 1, 0);
  WideCharToMultiByte(CP_UTF8, 0, lpwstr, -1, &result[0], size_needed, NULL, NULL);
  return result;
}

std::wstring ToWString(const std::string &str) {
  if (str.empty()) return std::wstring();
  int size_needed = MultiByteToWideChar(CP_UTF8, 0, &str[0], (int)str.size(), NULL, 0);
  std::wstring wstrTo(size_needed, 0);
  MultiByteToWideChar(CP_UTF8, 0, &str[0], (int)str.size(), &wstrTo[0], size_needed);
  return wstrTo;
}

Napi::String StringToNapiString(Napi::Env env, const std::string &str) {
  return Napi::String::New(env, str.c_str());
}

// Forward declaration for logging
void LogDebug(const std::string &msg);

// --- MonitorWorker Implementation ---

void MonitorWorker::Execute(const ExecutionProgress &progress) {
  // Store previously seen jobs with their status and page count
  // Key: jobUuid, Value: "state" string
  std::map<std::string, std::string> seenJobStates;
  // Track jobs that reached stable state (SPOOLING=0) and were reported final
  std::set<std::string> finalJobs;

  while (!stopRequested_) {
    // Enumerate all printers
    DWORD needed, returned;
    EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL,
                 0, &needed, &returned);

    if (needed > 0) {
      std::vector<BYTE> buffer(needed);
      if (EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2,
                        buffer.data(), needed, &needed, &returned)) {
        PRINTER_INFO_2W *printers = (PRINTER_INFO_2W *)buffer.data();

        for (DWORD i = 0; i < returned; i++) {
          std::string printerName = LPWSTRToUTF8(printers[i].pPrinterName);
          HANDLE hPrinter;

          if (OpenPrinterW(printers[i].pPrinterName, &hPrinter, NULL)) {
            // Ensure printer keeps jobs so we can analyze them even if they are fast
            EnsureKeepPrintedJobs(hPrinter);

            DWORD jobNeeded, jobReturned;
            EnumJobsW(hPrinter, 0, 100, 2, NULL, 0, &jobNeeded, &jobReturned);

            if (jobNeeded > 0) {
              std::vector<BYTE> jobBuffer(jobNeeded);
              if (EnumJobsW(hPrinter, 0, 100, 2, jobBuffer.data(), jobNeeded,
                           &jobReturned, &jobReturned)) { // System API wants both but we use jobReturned
                JOB_INFO_2W *jobs = (JOB_INFO_2W *)jobBuffer.data();

                for (DWORD j = 0; j < jobReturned; j++) {
                  DWORD jobId = jobs[j].JobId;

                  // Get full job details to determine current state
                  JobDetails details = GetJobInfo(hPrinter, jobId);

                  // Create a truly unique key for this job (Printer_ID_StartTime)
                  std::string jobKey = details.jobUuid;
                  
                  // Construct a state string to detect changes
                  std::string currentState =
                      details.statusStr + "_" +
                      std::to_string(details.totalPages) + "_" +
                      std::to_string(details.fillRate) + "_" +
                      std::to_string(details.isGrayscale) + "_" +
                      details.thumbnailUrl;

                  // Report if this is a new job OR if state changed
                  bool isNew = (seenJobStates.find(jobKey) == seenJobStates.end());
                  bool hasChanged = !isNew && (seenJobStates[jobKey] != currentState);

                  if (isNew || hasChanged) {
                    seenJobStates[jobKey] = currentState;

                    std::vector<JobDetails> data;
                    data.push_back(details);
                    progress.Send(data.data(), data.size());
                  }

                  // If job is STABLE (not spooling) and NOT yet finalized and NOT printing
                  // We treat it as finished and delete it from spooler to cleanup
                  if (details.statusStr != "Spooling" && details.statusStr != "Printing" &&
                      finalJobs.find(jobKey) == finalJobs.end() && details.totalPages > 0) {
                    
                    LogDebug("Job " + jobKey + " is stable. Requesting SPOOLER DELETE.");
                    if (SetJob(hPrinter, jobId, 0, NULL, JOB_CONTROL_DELETE)) {
                        finalJobs.insert(jobKey);
                        LogDebug("Successfully deleted job " + jobKey + " from Windows spooler.");
                    } else {
                        LogDebug("Failed to delete job " + jobKey + ". Error: " + std::to_string(GetLastError()));
                    }
                  }
                }
              }
            }
            ClosePrinter(hPrinter);
          }
        }
      }
    }

    // Poll every 100ms
    Sleep(100);
  }
}

// Custom EMF enumeration callback to analyze colors
struct EmfAnalysisData {
  bool hasColor;
  double totalPixels;
  double filledPixels;
  RECTL rclBounds; // Store bounds
};

// Helper to check if a bitmap contains color
struct BitmapAnalysis {
  bool hasColor;
  double density; // 0.0 to 1.0 (portion of non-white pixels)
};

// Helper to analyze bitmap content for color and density
BitmapAnalysis AnalyzeBitmap(const char *recordBase, DWORD offBmi,
                             DWORD offBits, DWORD sizeBmi, DWORD sizeBits) {
  BitmapAnalysis result = {false, 1.0}; // Default conservative

  if (offBmi == 0 || offBits == 0)
    return result;

  const BITMAPINFOHEADER *bmi = (const BITMAPINFOHEADER *)(recordBase + offBmi);
  const BYTE *bits = (const BYTE *)(recordBase + offBits);

  if (bmi->biBitCount == 1) {
    // Monochrome: 0 is Black (Ink), 1 is White (Paper) usually.
    // Need to verify standard DIB, usually 0=Black, 1=White.
    // Scan bits.
    // Doing full scan might be slow, use sampling.
    result.density = 0.5; // Placeholder/Heuristic if we don't scan
    // Let's implement sampling for 1bpp if possible, but bit-math is tricky
    // with stride. For now, assume 1bpp is likely text/lineart, so 10-20%
    // density? Or if it's a mask?
    result.density = 0.2;
    return result;
  }

  if (bmi->biBitCount <= 8) {
    // Paletted. Check palette.
    DWORD numColors = bmi->biClrUsed;
    if (numColors == 0)
      numColors = (1 << bmi->biBitCount);
    if (numColors > 256)
      numColors = 256;

    const RGBQUAD *palette = (const RGBQUAD *)((const char *)bmi + bmi->biSize);
    bool paletteHasColor = false;
    int whiteIndices = 0;

    // Check palette colors
    for (DWORD i = 0; i < numColors; i++) {
      if (palette[i].rgbRed != palette[i].rgbGreen ||
          palette[i].rgbGreen != palette[i].rgbBlue) {
        paletteHasColor = true;
      }
      if (palette[i].rgbRed > 250 && palette[i].rgbGreen > 250 &&
          palette[i].rgbBlue > 250) {
        whiteIndices++; // This index is White
      }
    }
    result.hasColor = paletteHasColor;

    // If all palette is black/white, density depends on usage.
    // If palette has many white entries, we should scan.
    // For safety/speed, return 0.5?
    return result;
  }

  if (bmi->biBitCount == 24 || bmi->biBitCount == 32) {
    DWORD pixelCount = bmi->biWidth * abs(bmi->biHeight);
    DWORD step = (pixelCount > 10000) ? 10 : 1;
    if (pixelCount > 100000)
      step = 100;

    int channels = (bmi->biBitCount == 32) ? 4 : 3;
    DWORD samples = 0;
    DWORD nonWhite = 0;

    for (DWORD i = 0; i < sizeBits; i += (channels * step)) {
      if (i + 2 >= sizeBits)
        break;
      BYTE b = bits[i];
      BYTE g = bits[i + 1];
      BYTE r = bits[i + 2];

      samples++;

      // Check Color
      if (r != g || g != b) {
        result.hasColor = true;
      }

      // Check White (Tolerance)
      bool isWhite = (r > 245 && g > 245 && b > 245);
      if (!isWhite) {
        nonWhite++;
      }
    }

    if (samples > 0) {
      result.density = (double)nonWhite / (double)samples;
    }
    return result;
  }

  return result;
}

// Helper to determine if a rect covers the whole page (Background)
bool IsBackground(double w, double h, const RECTL &bounds) {
  double totalW = std::abs(bounds.right - bounds.left);
  double totalH = std::abs(bounds.bottom - bounds.top);
  if (totalW == 0 || totalH == 0)
    return false;
  double elemArea = w * h;
  double pageArea = totalW * totalH;
  // If element covers > 90% of page, assume it's a background clear/fill
  return (elemArea / pageArea) > 0.90;
}

int CALLBACK EmfEnumProc(HDC hdc, HANDLETABLE *lpht, const ENHMETARECORD *lpemr,
                         int nHandles, LPARAM lpData) {
  EmfAnalysisData *data = (EmfAnalysisData *)lpData;

  // Check for color-related records
  switch (lpemr->iType) {
  case EMR_HEADER: {
    const ENHMETAHEADER *header = (const ENHMETAHEADER *)lpemr;
    data->rclBounds = header->rclBounds;
    LogDebug("EmfEnumProc: Header Bounds: " +
             std::to_string(header->rclBounds.left) + "," +
             std::to_string(header->rclBounds.top) + "-" +
             std::to_string(header->rclBounds.right) + "," +
             std::to_string(header->rclBounds.bottom));
    break;
  }
  case EMR_SETTEXTCOLOR:
  case EMR_SETBKCOLOR: {
    const EMRSETTEXTCOLOR *rec = (const EMRSETTEXTCOLOR *)lpemr;
    BYTE r = GetRValue(rec->crColor);
    BYTE g = GetGValue(rec->crColor);
    BYTE b = GetBValue(rec->crColor);
    if (r != g || g != b) {
      data->hasColor = true;
    }
    break;
  }
  case EMR_CREATEBRUSHINDIRECT: {
    const EMRCREATEBRUSHINDIRECT *rec = (const EMRCREATEBRUSHINDIRECT *)lpemr;
    BYTE r = GetRValue(rec->lb.lbColor);
    BYTE g = GetGValue(rec->lb.lbColor);
    BYTE b = GetBValue(rec->lb.lbColor);
    if (r != g || g != b) {
      data->hasColor = true;
    }
    // Estimate fill based on brush style
    if (rec->lb.lbStyle == BS_SOLID && (r != 255 || g != 255 || b != 255)) {
      data->filledPixels += 1000; // Rough estimate
    }
    break;
  }
  case EMR_CREATEPEN: {
    const EMRCREATEPEN *rec = (const EMRCREATEPEN *)lpemr;
    BYTE r = GetRValue(rec->lopn.lopnColor);
    BYTE g = GetGValue(rec->lopn.lopnColor);
    BYTE b = GetBValue(rec->lopn.lopnColor);
    if (r != g || g != b) {
      data->hasColor = true;
    }
    break;
  }

  case EMR_RECTANGLE:
  case EMR_ELLIPSE: {
    const EMRRECTANGLE *rec = (const EMRRECTANGLE *)lpemr;
    double w = std::abs(rec->rclBox.right - rec->rclBox.left);
    double h = std::abs(rec->rclBox.bottom - rec->rclBox.top);

    if (!IsBackground(w, h, data->rclBounds)) {
      data->filledPixels += (w * h); // Solid shape 100% coverage
    }
    break;
  }
  case EMR_POLYGON: {
    const EMRPOLYGON *rec = (const EMRPOLYGON *)lpemr;
    double w = std::abs(rec->rclBounds.right - rec->rclBounds.left);
    double h = std::abs(rec->rclBounds.bottom - rec->rclBounds.top);

    if (!IsBackground(w, h, data->rclBounds)) {
      data->filledPixels += (w * h); // Solid shape 100% coverage
    }
    break;
  }
  case EMR_STRETCHDIBITS: {
    const EMRSTRETCHDIBITS *rec = (const EMRSTRETCHDIBITS *)lpemr;
    double w = std::abs(rec->rclBounds.right - rec->rclBounds.left);
    double h = std::abs(rec->rclBounds.bottom - rec->rclBounds.top);

    // Analyze bitmap content for density and color
    BitmapAnalysis analysis =
        AnalyzeBitmap((const char *)rec, rec->offBmiSrc, rec->offBitsSrc,
                      rec->cbBmiSrc, rec->cbBitsSrc);

    if (analysis.hasColor)
      data->hasColor = true;

    // Use calculated density (e.g. 0.0 for white background, 0.9 for photo)
    data->filledPixels += (w * h) * analysis.density;
    break;
  }
  case EMR_BITBLT: {
    const EMRBITBLT *rec = (const EMRBITBLT *)lpemr;
    double w = std::abs(rec->rclBounds.right - rec->rclBounds.left);
    double h = std::abs(rec->rclBounds.bottom - rec->rclBounds.top);

    // Ignore WHITENESS operations (Clear Screen)
    if (rec->dwRop != WHITENESS) {
      // Analyze bitmap content for density and color
      BitmapAnalysis analysis =
          AnalyzeBitmap((const char *)rec, rec->offBmiSrc, rec->offBitsSrc,
                        rec->cbBmiSrc, rec->cbBitsSrc);

      if (analysis.hasColor)
        data->hasColor = true;

      data->filledPixels += (w * h) * analysis.density;
    }
    break;
  }
  case EMR_EXTTEXTOUTW:
  case EMR_EXTTEXTOUTA: {
    const EMREXTTEXTOUTA *rec =
        (const EMREXTTEXTOUTA *)lpemr; // Common layout for Bounds
    double w = std::abs(rec->rclBounds.right - rec->rclBounds.left);
    double h = std::abs(rec->rclBounds.bottom - rec->rclBounds.top);

    if (w > 0 && h > 0) {
      // Text is sparse, roughly 20-30% of its bounding box depends on font
      // weight/spacing. Using 0.25 (25%) as a realistic estimator for "ink"
      // vs "box".
      data->filledPixels += (w * h) * 0.25;
    } else {
      // Fallback if no bounds (rare): add a substantial constant based on
      // previous scale Previous scale: ~15M total area. A generic text line
      // ~3000 width x 50 height = 150000. 25% of that = 37500.
      data->filledPixels += 20000;
    }
    break;
  }
  }

  return 1; // Continue enumeration
}

// Helper function to search for a pattern in buffer (case insensitive for
// text)
bool ContainsPattern(const char *buffer, size_t bufferSize, const char *pattern,
                     size_t patternLen) {
  if (bufferSize < patternLen)
    return false;
  for (size_t i = 0; i <= bufferSize - patternLen; i++) {
    bool match = true;
    for (size_t j = 0; j < patternLen; j++) {
      if (tolower(buffer[i + j]) != tolower(pattern[j])) {
        match = false;
        break;
      }
    }
    if (match)
      return true;
  }
  return false;
}

// Helper to find EMF start offset in a buffer
// Returns -1 if not found, or offset
long FindEmfOffset(const unsigned char *buffer, size_t size) {
  if (size < 128)
    return -1;

  // Check if it already looks like EMF (Type=1 at 0, Signature at 40)
  if (buffer[0] == 1 && buffer[1] == 0 && buffer[2] == 0 && buffer[3] == 0) {
    if (buffer[40] == ' ' && buffer[41] == 'E' && buffer[42] == 'M' &&
        buffer[43] == 'F') {
      return 0;
    }
  }

  // Scan for " EMF" signature (0x20464D45) to handle SPL headers
  // Increase limit to ensuring we don't miss it in large headers
  size_t scanLimit = (std::min)(size, (size_t)(5 * 1024 * 1024));

  for (size_t i = 40; i < scanLimit; i++) {
    if (buffer[i] == ' ' && buffer[i + 1] == 'E' && buffer[i + 2] == 'M' &&
        buffer[i + 3] == 'F') {
      // Possible signature, check record type at i-40
      size_t start = i - 40;
      DWORD type = *((DWORD *)&buffer[start]);
      if (type == 1) { // EMR_HEADER
        LogDebug("FindEmfOffset: Found EMF signature at offset " +
                 std::to_string(start));
        return (long)start;
      }
    }
  }

  // Backup scan for " EMF" at ANY offset if type check fails
  for (size_t i = 0; i < scanLimit - 4; i++) {
    if (buffer[i] == ' ' && buffer[i + 1] == 'E' && buffer[i + 2] == 'M' &&
        buffer[i + 3] == 'F') {
      LogDebug("FindEmfOffset: Backup found EMF signature at offset " +
               std::to_string(i - 40));
      return (long)(i - 40);
    }
  }

  return -1;
}

// Helper: Check if buffer contains PCL signature (\x1B%-12345X or ESC)
bool IsPclFile(const unsigned char *buffer, size_t size) {
  if (size < 4)
    return false;

  // Check for Kyocera PRESCRIBE (!R!)
  if (buffer[0] == '!' && buffer[1] == 'R' && buffer[2] == '!')
    return true;

  // Check for PJL header
  const char *pjlHeader = "\x1B%-12345X";
  if (size >= 9 && memcmp(buffer, pjlHeader, 9) == 0)
    return true;

  // Check for common ESC commands in first 1KB
  size_t scanLimit = (std::min)(size, (size_t)1024);
  for (size_t i = 0; i < scanLimit - 1; i++) {
    if (buffer[i] == 0x1B) { // ESC
      // Check for some common PCL sequences: ESC E (Reset), ESC & (Config),
      // ESC
      // * (Raster)
      if (buffer[i + 1] == 'E' || buffer[i + 1] == '&' ||
          buffer[i + 1] == '*') {
        return true;
      }
    }
  }

  return false;
}

// Helper: Check if buffer contains XPS signature (PK\x03\x04)
bool IsXpsFile(const unsigned char *buffer, size_t size) {
  if (size < 4)
    return false;

  // Check for ZIP signature (XPS files are ZIP archives)
  // PK\x03\x04 is the standard ZIP file header
  if (buffer[0] == 0x50 && buffer[1] == 0x4B && buffer[2] == 0x03 &&
      buffer[3] == 0x04) {
    return true;
  }

  return false;
}

// (Deprecated GDI rendering turned out to be unreliable for these memory
// handles) We now calculate it directly in AnalyzeSpoolFile using
// EmfAnalysisData stats.

// Helper: Get Ghostscript executable path
std::wstring GetGhostscriptPath() {
  // Try relative to exe first (development/packaged app)
  wchar_t exePath[MAX_PATH];
  GetModuleFileNameW(NULL, exePath, MAX_PATH);
  std::wstring exeDir = std::wstring(exePath);
  size_t lastSlash = exeDir.find_last_of(L"\\");
  if (lastSlash != std::wstring::npos) {
    exeDir = exeDir.substr(0, lastSlash);
  }

  // Check multiple possible locations
  std::vector<std::wstring> relativePaths = {
      L"\\ghostscript\\gswin64c.exe",
      L"\\..\\ghostscript\\gswin64c.exe",
      L"\\app\\ghostscript\\gswin64c.exe",
      L"\\..\\app\\ghostscript\\gswin64c.exe",
  };

  for (const auto &relPath : relativePaths) {
    std::wstring testPath = exeDir + relPath;

    // Resolve relative path to absolute
    wchar_t fullPath[MAX_PATH];
    if (GetFullPathNameW(testPath.c_str(), MAX_PATH, fullPath, NULL)) {
      if (PathFileExistsW(fullPath)) {
        LogDebug("Found Ghostscript at: " +
                 std::string(fullPath, fullPath + wcslen(fullPath)));
        return std::wstring(fullPath);
      }
    }
  }

  // Fallback: system PATH
  LogDebug("Ghostscript not found in app directories, using system PATH");
  return L"gswin64c.exe";
}

// Helper: Convert EMF to PNG using PHP API
// Returns list of PNG files created (one per page)
// Helper: Convert EMF to PNG using PHP API
// Returns list of PNG files created (one per page) and thumbnail URL
EmfConversionResult ConvertEmfToPngViaPhpApi(DWORD jobId) {
  EmfConversionResult result;

  try {
    // Build GET URL
    std::string url = "http://127.0.0.1:8001/?convert_emf_to_png&job_id=" +
                      std::to_string(jobId);

    // Make HTTP GET to PHP API (Port 8001 is PHP built-in server, 8000 is
    // Caddy)
    HINTERNET hInternet = InternetOpenA(
        "Fill Rate Analyzer", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) {
      LogDebug("Failed to initialize WinINet");
      return result;
    }

    HINTERNET hConnect = InternetOpenUrlA(
        hInternet, url.c_str(), NULL, 0,
        INTERNET_FLAG_NO_CACHE_WRITE | INTERNET_FLAG_RELOAD, 0);

    if (!hConnect) {
      DWORD error = GetLastError();
      InternetCloseHandle(hInternet);
      LogDebug("Failed to connect to PHP API at " + url +
               ". Error: " + std::to_string(error));
      return result;
    }

    // Read response
    std::string response;
    char buffer[4096];
    DWORD bytesRead;

    while (InternetReadFile(hConnect, buffer, sizeof(buffer), &bytesRead) &&
           bytesRead > 0) {
      response.append(buffer, bytesRead);
    }

    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);

    LogDebug("PHP API Response: " + response);

    // Parse JSON response (simple manual parsing for "path" fields)
    size_t pathPos = 0;
    while ((pathPos = response.find("\"path\":\"", pathPos)) !=
           std::string::npos) {
      pathPos += 8; // Skip past "path":"
      size_t endPos = response.find("\"", pathPos);
      if (endPos != std::string::npos) {
        std::string pathStr = response.substr(pathPos, endPos - pathPos);

        // Convert to wstring
        std::wstring wPath(pathStr.begin(), pathStr.end());
        result.pngPaths.push_back(wPath);

        pathPos = endPos;
      }
    }

    // Parse base_url for thumbnail
    size_t urlPos = response.find("\"base_url\":\"");
    if (urlPos != std::string::npos) {
      urlPos += 12;
      size_t endUrl = response.find("\"", urlPos);
      if (endUrl != std::string::npos) {
        result.thumbnailUrl =
            response.substr(urlPos, endUrl - urlPos) + "page_0.png";
      }
    }

    LogDebug("Found " + std::to_string(result.pngPaths.size()) +
             " PNG files from PHP API");

    // Parse total_pages OR page_count (EMF/XPS)
    size_t tpPos = response.find("\"total_pages\":");
    if (tpPos == std::string::npos) tpPos = response.find("\"page_count\":");
    if (tpPos != std::string::npos) {
      // Skip the key + colon, find the number start
      size_t colonPos = response.find(":", tpPos);
      if (colonPos != std::string::npos) {
        tpPos = colonPos + 1;
        // Skip whitespace
        while (tpPos < response.size() && (response[tpPos] == ' ' || response[tpPos] == '\t')) tpPos++;
        size_t tpEnd = response.find(",", tpPos);
        if (tpEnd == std::string::npos) tpEnd = response.find("}", tpPos);
        if (tpEnd != std::string::npos) {
          std::string tpStr = response.substr(tpPos, tpEnd - tpPos);
          result.totalPages = (DWORD)atoi(tpStr.c_str());
          LogDebug("Parsed total_pages/page_count: " + std::to_string(result.totalPages));
        }
      }
    } else {
      // Fallback: count PNGs generated
      result.totalPages = (DWORD)result.pngPaths.size();
    }

  } catch (...) {
    LogDebug("Exception in ConvertEmfToPngViaPhpApi");
  }

  return result;
}

// Helper: Convert PCL/RAW to PNG using PHP API
// Returns list of PNG files created (one per page) and thumbnail URL
EmfConversionResult ConvertPclToPngViaPhpApi(DWORD jobId) {
  EmfConversionResult result;

  try {
    // Build GET URL - Pointing to NEW PCL script
    std::string url = "http://127.0.0.1:8001/?convert_pcl_to_png&job_id=" +
                      std::to_string(jobId);

    // Make HTTP GET to PHP API
    HINTERNET hInternet = InternetOpenA(
        "Fill Rate Analyzer PCL", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
    if (!hInternet) {
      LogDebug("Failed to initialize WinINet for PCL");
      return result;
    }

    HINTERNET hConnect = InternetOpenUrlA(
        hInternet, url.c_str(), NULL, 0,
        INTERNET_FLAG_NO_CACHE_WRITE | INTERNET_FLAG_RELOAD, 0);

    if (!hConnect) {
      DWORD error = GetLastError();
      InternetCloseHandle(hInternet);
      LogDebug("Failed to connect to PHP PCL API at " + url +
               ". Error: " + std::to_string(error));
      return result;
    }

    // Read response
    std::string response;
    char buffer[4096];
    DWORD bytesRead;

    while (InternetReadFile(hConnect, buffer, sizeof(buffer), &bytesRead) &&
           bytesRead > 0) {
      response.append(buffer, bytesRead);
    }

    InternetCloseHandle(hConnect);
    InternetCloseHandle(hInternet);

    LogDebug("PHP PCL API Response: " + response);

    // Parse JSON response (simple manual parsing for "path" fields)
    size_t pathPos = 0;
    while ((pathPos = response.find("\"path\":\"", pathPos)) !=
           std::string::npos) {
      pathPos += 8; // Skip past "path":"
      size_t endPos = response.find("\"", pathPos);
      if (endPos != std::string::npos) {
        std::string pathStr = response.substr(pathPos, endPos - pathPos);

        // Convert to wstring
        std::wstring wPath(pathStr.begin(), pathStr.end());
        result.pngPaths.push_back(wPath);

        pathPos = endPos;
      }
    }

    // Parse base_url for thumbnail
    size_t urlPos = response.find("\"base_url\":\"");
    if (urlPos != std::string::npos) {
      urlPos += 12;
      size_t endUrl = response.find("\"", urlPos);
      if (endUrl != std::string::npos) {
        // PCL script generates page_1.png usually, but we ensure page_0
        // exists or use 1 We'll trust the script generates page_0.png if we
        // added that logic, or we just point to page_1 if that's what we
        // have. For consistency with frontend, let's assume page_0 if
        // accessible.
        result.thumbnailUrl =
            response.substr(urlPos, endUrl - urlPos) + "page_0.png";
      }
    }

    LogDebug("Found " + std::to_string(result.pngPaths.size()) +
             " PNG files from PHP PCL API");

    // Parse total_pages OR page_count (PCL returns page_count)
    size_t tpPos = response.find("\"total_pages\":");
    if (tpPos == std::string::npos) tpPos = response.find("\"page_count\":");
    if (tpPos != std::string::npos) {
      // Skip the key + colon, find the number start
      size_t colonPos = response.find(":", tpPos);
      if (colonPos != std::string::npos) {
        tpPos = colonPos + 1;
        // Skip whitespace
        while (tpPos < response.size() && (response[tpPos] == ' ' || response[tpPos] == '\t')) tpPos++;
        size_t tpEnd = response.find(",", tpPos);
        if (tpEnd == std::string::npos) tpEnd = response.find("}", tpPos);
        if (tpEnd != std::string::npos) {
          std::string tpStr = response.substr(tpPos, tpEnd - tpPos);
          result.totalPages = (DWORD)atoi(tpStr.c_str());
          LogDebug("Parsed PCL total_pages/page_count: " + std::to_string(result.totalPages));
        }
      }
    } else {
      // Fallback: count PNGs generated
      result.totalPages = (DWORD)result.pngPaths.size();
    }

  } catch (...) {
    LogDebug("Exception in ConvertPclToPngViaPhpApi");
  }

  return result;
}

// Helper: Convert XPS to PNG using PHP API
// Returns list of PNG files created (one per page) and thumbnail URL
EmfConversionResult ConvertXpsToPngViaPhpApi(DWORD jobId) {
  EmfConversionResult result;
  int retryCount = 0;
  const int maxRetries = 20; // 2 seconds total wait (20 * 100ms)

  while (retryCount < maxRetries) {
    try {
      // Build GET URL - Pointing to XPS conversion script
      std::string url = "http://127.0.0.1:8001/?convert_xps_to_png&job_id=" +
                        std::to_string(jobId);

      if (retryCount > 0) {
        LogDebug("Calling XPS conversion API (Retry " +
                 std::to_string(retryCount) + "): " + url);
      } else {
        LogDebug("Calling XPS conversion API: " + url);
      }

      // Open Internet connection
      HINTERNET hInternet = InternetOpenA(
          "Win32Printer/1.0", INTERNET_OPEN_TYPE_DIRECT, NULL, NULL, 0);
      if (!hInternet) {
        LogDebug("InternetOpen failed");
        return result;
      }

      // Open URL
      HINTERNET hUrl = InternetOpenUrlA(hInternet, url.c_str(), NULL, 0,
                                        INTERNET_FLAG_RELOAD, 0);
      if (!hUrl) {
        LogDebug("InternetOpenUrl failed");
        InternetCloseHandle(hInternet);
        return result;
      }

      // Read response
      std::string response;
      char buffer[4096];
      DWORD bytesRead;
      while (InternetReadFile(hUrl, buffer, sizeof(buffer), &bytesRead) &&
             bytesRead > 0) {
        response.append(buffer, bytesRead);
      }

      InternetCloseHandle(hUrl);
      InternetCloseHandle(hInternet);

      LogDebug("XPS API Response: " + response);

      // Check for specific "Incomplete" error
      // The PHP script returns: {"error":"Incomplete XPS file (No EOCD)"}
      if (response.find("Incomplete XPS file") != std::string::npos) {
        LogDebug(
            "XPS file incomplete. Waiting for spooler to finish writing...");
        Sleep(250); // Wait 250ms
        retryCount++;
        continue; // Retry loop
      }

      // Parse JSON response (simple parsing for our known format)
      // Expected:
      // {"success":true,"pages":[{"page":1,"path":"thumbnails/123/page_1.png"}]}
      if (response.find("\"success\":true") != std::string::npos) {
        // Extract thumbnail URL (first page)
        size_t pathStart = response.find("\"path\":\"");
        if (pathStart != std::string::npos) {
          pathStart += 8; // Skip '"path":"'
          size_t pathEnd = response.find("\"", pathStart);
          if (pathEnd != std::string::npos) {
            result.thumbnailUrl =
                response.substr(pathStart, pathEnd - pathStart);
            LogDebug("XPS Thumbnail URL: " + result.thumbnailUrl);
          }
        }

        // Extract all page paths
        size_t pos = 0;
        while ((pos = response.find("\"path\":\"", pos)) != std::string::npos) {
          pos += 8;
          size_t endPos = response.find("\"", pos);
          if (endPos != std::string::npos) {
            std::string relativePath = response.substr(pos, endPos - pos);

            // Convert to absolute Windows path
            wchar_t exePath[MAX_PATH];
            GetModuleFileNameW(NULL, exePath, MAX_PATH);
            std::wstring exeDir = std::wstring(exePath);
            size_t lastSlash = exeDir.find_last_of(L"\\");
            if (lastSlash != std::wstring::npos) {
              exeDir = exeDir.substr(0, lastSlash);
            }

            std::wstring fullPath =
                exeDir + L"\\app\\public\\" +
                std::wstring(relativePath.begin(), relativePath.end());
            result.pngPaths.push_back(fullPath);

            pos = endPos;
          }
        }

        LogDebug("XPS conversion successful, " +
                 std::to_string(result.pngPaths.size()) + " pages");

        // Parse total_pages if present
        size_t tpPos = response.find("\"total_pages\":");
        if (tpPos != std::string::npos) {
          tpPos += 14;
          size_t tpEnd = response.find(",", tpPos);
          if (tpEnd == std::string::npos) tpEnd = response.find("}", tpPos);
          if (tpEnd != std::string::npos) {
            std::string tpStr = response.substr(tpPos, tpEnd - tpPos);
            result.totalPages = (DWORD)atoi(tpStr.c_str());
            LogDebug("Parsed XPS total_pages: " + std::to_string(result.totalPages));
          }
        }

        // Success! Break loop
        break;
      } else {
        LogDebug("XPS conversion failed with unknown error (not incomplete). "
                 "Stopping retries.");
        // If it's another error (e.g. "Failed to execute GhostXPS"), don't
        // retry blindly
        break;
      }
    } catch (...) {
      LogDebug("Exception in ConvertXpsToPngViaPhpApi");
      break;
    }
  }

  return result;
}

// Helper: Analyze PNG pixels to calculate fill rate
// Returns fill rate percentage and updates isGrayscale
float AnalyzePngPixels(const std::wstring &pngPath, bool &isGrayscale) {
  using namespace Gdiplus;

  // Initialize GDI+
  GdiplusStartupInput gdiplusStartupInput;
  ULONG_PTR gdiplusToken;
  GdiplusStartup(&gdiplusToken, &gdiplusStartupInput, NULL);

  float fillRate = 0.0f;
  // bool hasColor = false; // logic moved to pixel counting

  try {
    // Load PNG
    Bitmap *bitmap = new Bitmap(pngPath.c_str());

    if (bitmap && bitmap->GetLastStatus() == Ok) {
      UINT width = bitmap->GetWidth();
      UINT height = bitmap->GetHeight();
      UINT totalPixels = width * height;
      UINT filledPixels = 0;
      UINT coloredPixels = 0; // Count colored pixels

      LogDebug("Analyzing PNG: " + std::to_string(width) + "x" +
               std::to_string(height));

      // Sample pixels (analyze 1 out of every 4 for speed)
      int step = 2; // Sample every 2nd pixel = analyze 25% of pixels
      UINT sampledPixels = 0;

      for (UINT y = 0; y < height; y += step) {
        for (UINT x = 0; x < width; x += step) {
          Color color;
          bitmap->GetPixel(x, y, &color);

          BYTE r = color.GetR();
          BYTE g = color.GetG();
          BYTE b = color.GetB();

          // Check for color with tolerance (avoid noise)
          int diff1 = abs((int)r - (int)g);
          int diff2 = abs((int)g - (int)b);
          int diff3 = abs((int)r - (int)b);
          // Increased tolerance to 15 and require more than just one pixel
          if (diff1 > 15 || diff2 > 15 || diff3 > 15) {
            coloredPixels++;
          }

          // Calculate luminosity
          int luminosity = (r + g + b) / 3;

          // If pixel is not white (luminosity < 200), count as filled
          // Adjusted to 200 to exclude light artifacts/gray and match PHP
          // calculation
          if (luminosity < 200) {
            filledPixels++;
          }

          sampledPixels++;
        }
      }

      // Extrapolate to full image (since we sampled)
      float samplingRatio = (float)sampledPixels / (float)totalPixels;
      float estimatedFilledPixels = filledPixels / samplingRatio;

      fillRate = (estimatedFilledPixels / totalPixels) * 100.0f;

      // Update grayscale status based on percentage of colored pixels
      // Threshold: 0.1% of pixels must be colored to be considered Color mode (lowered from 0.5%)
      // This catches documents with small color elements (logos, etc.)
      // FIX: Once color is detected, it remains color for the entire job
      double colorPercentage =
          ((double)coloredPixels / (double)sampledPixels) * 100.0;
      if (colorPercentage > 0.1) {
        isGrayscale = false;
      }

      LogDebug("Color detection: " + std::to_string(colorPercentage) + "% colored pixels");

      LogDebug("Fill rate: " + std::to_string(fillRate) + "% (sampled " +
               std::to_string(sampledPixels) + "/" +
               std::to_string(totalPixels) +
               " pixels). Color%: " + std::to_string(colorPercentage));
    }

    delete bitmap;

  } catch (...) {
    LogDebug("Exception in AnalyzePngPixels");
  }

  GdiplusShutdown(gdiplusToken);

  return fillRate;
}

// Helper: Read Job ID from SHD (Shadow) file header
// Windows 10+: offset 12, Windows XP/Vista: offset 8
DWORD ReadJobIdFromShd(const std::wstring &shdPath) {
  HANDLE hFile = CreateFileW(shdPath.c_str(), GENERIC_READ,
                             FILE_SHARE_READ | FILE_SHARE_WRITE, NULL,
                             OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);

  if (hFile == INVALID_HANDLE_VALUE) {
    // File might be locked, try with retry
    for (int retry = 0; retry < 3; retry++) {
      Sleep(100);
      hFile = CreateFileW(shdPath.c_str(), GENERIC_READ,
                          FILE_SHARE_READ | FILE_SHARE_WRITE, NULL,
                          OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);
      if (hFile != INVALID_HANDLE_VALUE)
        break;
    }
    if (hFile == INVALID_HANDLE_VALUE) {
      return 0;
    }
  }

  BYTE buffer[16];
  DWORD bytesRead = 0;
  if (!ReadFile(hFile, buffer, 16, &bytesRead, NULL) || bytesRead < 16) {
    CloseHandle(hFile);
    return 0;
  }
  CloseHandle(hFile);

  // Try Windows 10+ format (offset 12)
  DWORD jobId = *((DWORD *)(buffer + 12));
  if (jobId > 0 && jobId < 100000) {
    return jobId;
  }

  // Fallback: Windows XP/Vista format (offset 8)
  jobId = *((DWORD *)(buffer + 8));
  if (jobId > 0 && jobId < 100000) {
    return jobId;
  }

  return 0;
}

// Helper: Find SPL file by Job ID
// 1. First tries standard naming (00{jobId}.SPL)
// 2. If not found, scans SHD files to find the correct one
// 3. If SHD empty/not found, falls back to most recent FP*.SPL file
std::wstring FindSplFileByJobId(DWORD jobId, const std::wstring &spoolPath) {
  // Step 1: Try standard naming format
  wchar_t standardName[MAX_PATH];
  swprintf_s(standardName, MAX_PATH, L"%s%05lu.SPL", spoolPath.c_str(), jobId);

  if (PathFileExistsW(standardName)) {
    // std::wcout << L"[FILLRATE] Found SPL via standard naming: " <<
    // standardName
    //            << std::endl;
    return std::wstring(standardName);
  }

  // Step 2: Scan SHD files to find matching Job ID
  // std::wcout << L"[FILLRATE] Standard SPL not found, scanning SHD files..."
  //            << std::endl;

  wchar_t searchPattern[MAX_PATH];
  swprintf_s(searchPattern, MAX_PATH, L"%s*.SHD", spoolPath.c_str());

  WIN32_FIND_DATAW findData;
  HANDLE hFind = FindFirstFileW(searchPattern, &findData);
  std::wstring mostRecentFpSpl = L"";
  FILETIME mostRecentTime = {0, 0};

  if (hFind != INVALID_HANDLE_VALUE) {
    do {
      std::wstring shdFileName = findData.cFileName;
      std::wstring shdFullPath = spoolPath + shdFileName;

      // Debug: log each SHD file found
      std::wcout << L"[FILLRATE] Found SHD: " << shdFileName << L" (size="
                 << findData.nFileSizeLow << L")" << std::endl;

      // Track FP files for fallback (regardless of SHD content)
      if (shdFileName.find(L"FP") == 0) {
        std::wstring splFileName = shdFileName;
        size_t dotPos = splFileName.rfind(L'.');
        if (dotPos != std::wstring::npos) {
          splFileName = splFileName.substr(0, dotPos) + L".SPL";
        }
        std::wstring splFullPath = spoolPath + splFileName;
        if (PathFileExistsW(splFullPath.c_str())) {
          // Track most recent FP file
          if (CompareFileTime(&findData.ftLastWriteTime, &mostRecentTime) > 0) {
            mostRecentTime = findData.ftLastWriteTime;
            mostRecentFpSpl = splFullPath;
          }
        }
      }

      // Check if SHD has content (not empty)
      if (findData.nFileSizeLow > 0 || findData.nFileSizeHigh > 0) {
        DWORD shdJobId = ReadJobIdFromShd(shdFullPath);

        // Debug: log job ID read from SHD
        std::wcout << L"[FILLRATE]   -> SHD Job ID: " << shdJobId
                   << L" (looking for: " << jobId << L")" << std::endl;

        if (shdJobId == jobId) {
          // Found matching SHD, now get corresponding SPL
          std::wstring splFileName = shdFileName;
          size_t dotPos = splFileName.rfind(L'.');
          if (dotPos != std::wstring::npos) {
            splFileName = splFileName.substr(0, dotPos) + L".SPL";
          }

          std::wstring splFullPath = spoolPath + splFileName;

          if (PathFileExistsW(splFullPath.c_str())) {
            std::wcout << L"[FILLRATE] Found SPL via SHD mapping: "
                       << splFileName << L" (SHD Job ID: " << shdJobId << L")"
                       << std::endl;
            FindClose(hFind);
            return splFullPath;
          }
        }
      }
    } while (FindNextFileW(hFind, &findData));
    FindClose(hFind);
  }

  // Step 3: If no match found but we have a recent FP file, use it
  if (!mostRecentFpSpl.empty()) {
    std::wcout << L"[FILLRATE] Using most recent FP SPL as fallback: "
               << mostRecentFpSpl << std::endl;
    return mostRecentFpSpl;
  }

  return L"";
}

// Analyze spool file content for color detection and fill rate
// Now uses Ghostscript + pixel analysis for accurate results
void AnalyzeSpoolFile(DWORD jobId, const std::string &documentName,
                      bool &isGrayscale, float &fillRate,
                      std::string &thumbnailUrl, DWORD &totalPages) {
  LogDebug("AnalyzeSpoolFile: Starting PIXEL ANALYSIS for Job " +
           std::to_string(jobId) + " (" + documentName + ")");

  fillRate = 0.0f;

  // FIX: Use jobId + documentName as cache key to avoid cross-contamination
  // between different jobs with same document name
  std::string cacheKey = std::to_string(jobId) + "|" + documentName;

  // Check cache first
  if (splAnalysisCache.find(cacheKey) != splAnalysisCache.end()) {
    SplAnalysisCache cached = splAnalysisCache[cacheKey];

    // Verify if it's the SAME document. If not, ignore cache (jobId recycled)
    if (cached.documentName == documentName) {
      // Get current file size to see if it grew
      wchar_t spoolPathTmp[MAX_PATH];
      GetSystemDirectoryW(spoolPathTmp, MAX_PATH);
      wcscat_s(spoolPathTmp, L"\\spool\\PRINTERS\\");
      std::wstring foundPath =
          FindSplFileByJobId(jobId, std::wstring(spoolPathTmp));

      DWORD currentSize = 0;
      if (!foundPath.empty()) {
        HANDLE hFile = CreateFileW(foundPath.c_str(), GENERIC_READ,
                                   FILE_SHARE_READ | FILE_SHARE_WRITE, NULL,
                                   OPEN_EXISTING, 0, NULL);
        if (hFile != INVALID_HANDLE_VALUE) {
          currentSize = GetFileSize(hFile, NULL);
          CloseHandle(hFile);
        }
      }

      if (currentSize <= cached.lastFileSize && cached.lastFileSize > 0) {
        isGrayscale = cached.isGrayscale;
        fillRate = cached.fillRate;
        thumbnailUrl = cached.thumbnailUrl;
        
        // Re-apply correction even on cache hits: 
        // If Windows reports N pages (totalPages > 0) and cache says N+1, keep Windows value.
        if (totalPages > 0 && cached.totalPages == totalPages + 1) {
            LogDebug("Correcting cached page count: trust Windows (" + std::to_string(totalPages) + 
                     ") over cached analysis (" + std::to_string(cached.totalPages) + ")");
            // totalPages remains the Windows count (input)
        } else {
            totalPages = cached.totalPages;
        }

        LogDebug("AnalyzeSpoolFile: Using cached result (Size unchanged: " +
                 std::to_string(currentSize) +
                 ") - Grayscale=" + std::to_string(isGrayscale) +
                 ", FillRate=" + std::to_string(fillRate) +
                 ", final totalPages=" + std::to_string(totalPages));
        return;
      } else {
        LogDebug("AnalyzeSpoolFile: File Grew (" +
                 std::to_string(cached.lastFileSize) + " -> " +
                 std::to_string(currentSize) + "). Re-analyzing...");
      }
    } else {
      LogDebug("AnalyzeSpoolFile: Cache Document Mismatch (" +
               cached.documentName + " vs " + documentName + "). Invaliding.");
    }
  }

  wchar_t spoolPath[MAX_PATH];
  GetSystemDirectoryW(spoolPath, MAX_PATH);
  wcscat_s(spoolPath, L"\\spool\\PRINTERS\\");

  std::wstring wsSpoolPath(spoolPath);
  LogDebug("[FILLRATE] Scanning SPL files in: " +
           std::string(wsSpoolPath.begin(), wsSpoolPath.end()));

  // Use new universal SPL finder (supports standard + File Pooling)
  std::wstring foundFullPath =
      FindSplFileByJobId(jobId, std::wstring(spoolPath));

  if (foundFullPath.empty()) {
    LogDebug("[FILLRATE] No SPL file found for Job " + std::to_string(jobId));
    return;
  }

  // Open SPL file
  HANDLE hFile = CreateFileW(foundFullPath.c_str(), GENERIC_READ,
                             FILE_SHARE_READ | FILE_SHARE_WRITE, NULL,
                             OPEN_EXISTING, 0, NULL);
  if (hFile == INVALID_HANDLE_VALUE) {
    LogDebug("[FILLRATE] Could not open SPL file. Error: " +
             std::to_string(GetLastError()));
    return;
  }

  DWORD fileSize = GetFileSize(hFile, NULL);
  if (fileSize == INVALID_FILE_SIZE) {
    CloseHandle(hFile);
    return;
  }

  // Read SPL file
  std::vector<BYTE> buffer(fileSize);
  DWORD bytesRead;
  if (!ReadFile(hFile, buffer.data(), fileSize, &bytesRead, NULL)) {
    CloseHandle(hFile);
    return;
  }
  CloseHandle(hFile);

  // Find EMF offset
  long emfOffset = FindEmfOffset(buffer.data(), bytesRead);
  std::vector<std::wstring> pngFiles;
  EmfConversionResult conversion;

  if (emfOffset >= 0) {
    LogDebug("[FILLRATE] EMF found at offset " + std::to_string(emfOffset));
    // Convert EMF to PNG(s) using PHP API
    conversion = ConvertEmfToPngViaPhpApi(jobId);
    pngFiles = conversion.pngPaths;
  } else if (IsXpsFile(buffer.data(), bytesRead)) {
    LogDebug("[FILLRATE] No EMF signature, but XPS signature DETECTED. "
             "Proceeding to XPS conversion.");
    // Convert XPS to PNG(s) using PHP API
    conversion = ConvertXpsToPngViaPhpApi(jobId);
    pngFiles = conversion.pngPaths;
  } else if (IsPclFile(buffer.data(), bytesRead)) {
    LogDebug("[FILLRATE] No EMF/XPS signature, but PCL signature DETECTED. "
             "Proceeding to PCL conversion.");
    // Convert PCL to PNG(s) using PHP API
    conversion = ConvertPclToPngViaPhpApi(jobId);
    pngFiles = conversion.pngPaths;
  } else {
    LogDebug("[FILLRATE] NO KNOWN signature found (EMF/XPS/PCL). Aborting "
             "conversion to avoid infinite loop.");
    // Do nothing, pngFiles remains empty
  }

  if (pngFiles.empty()) {
    LogDebug("[FILLRATE] Failed to convert SPL to PNG (EMF or PCL)");
    return;
  }

  LogDebug("[FILLRATE] Analyzing " + std::to_string(pngFiles.size()) +
           " page(s)");

  // Analyze each page and calculate average
  float totalFillRate = 0.0f;
  int pagesAnalyzed = 0;

  // Files are already in public/thumbnails managed by PHP
  // Check if they exist, analyze them, but do NOT delete them
  for (const auto &pngFile : pngFiles) {
    if (PathFileExistsW(pngFile.c_str())) {
      float pageFillRate = AnalyzePngPixels(pngFile, isGrayscale);
      totalFillRate += pageFillRate;
      pagesAnalyzed++;

      LogDebug("[FILLRATE] Page " + std::to_string(pagesAnalyzed) + ": " +
               std::to_string(pageFillRate) + "%");
    }
  }

  // Calculate average fill rate
  if (pagesAnalyzed > 0) {
    fillRate = totalFillRate / pagesAnalyzed;
  }

  // Update Cache
  splAnalysisCache[cacheKey] = {isGrayscale, fillRate, "now",
                             conversion.thumbnailUrl, documentName};

  // IMPORTANT: Assign back to output parameter so caller sees it immediately
  thumbnailUrl = conversion.thumbnailUrl;
  
  // Use detected page count from PHP API if available, otherwise use analyzed count
  if (conversion.totalPages > 0) {
    // If we have a valid Windows count (passed in totalPages) and PHP analysis
    // finds more pages, we trust the Windows count if it's the 31 vs 30 case
    if (totalPages > 0 && conversion.totalPages == totalPages + 1) {
      LogDebug("Correcting page count: trust Windows (" + std::to_string(totalPages) + 
               ") over analysis (" + std::to_string(conversion.totalPages) + ")");
      // Keep totalPages as is (the Windows count)
    } else {
      totalPages = conversion.totalPages;
      LogDebug("Using detected totalPages from PHP API: " + std::to_string(totalPages));
    }
  } else if (pagesAnalyzed > 0) {
    totalPages = (DWORD)pagesAnalyzed;
    LogDebug("Using analyzed page count: " + std::to_string(totalPages));
  }

  LogDebug("[FILLRATE] Average Fill Rate: " + std::to_string(fillRate) +
           "% across " + std::to_string(pagesAnalyzed) + " page(s)");

  // Cleanup temp directory
  if (!pngFiles.empty()) {
    std::wstring tempDir = pngFiles[0];
    size_t lastSlash = tempDir.find_last_of(L"\\");
    if (lastSlash != std::wstring::npos) {
      tempDir = tempDir.substr(0, lastSlash);
      RemoveDirectoryW(tempDir.c_str());
    }
  }

  // Cache the result - BUT NEVER cache 0-page results to avoid the race condition
  // where the job is detected during spooling (empty file) and cached as 0
  if (pagesAnalyzed > 0 || conversion.totalPages > 0) {
    SplAnalysisCache cacheEntry;
    cacheEntry.isGrayscale = isGrayscale;
    cacheEntry.fillRate = fillRate;
    cacheEntry.timestamp = std::to_string(time(NULL));
    cacheEntry.thumbnailUrl = conversion.thumbnailUrl;
    cacheEntry.documentName = documentName;
    cacheEntry.lastFileSize = fileSize;
    cacheEntry.totalPages = totalPages; // Store detected page count
    splAnalysisCache[cacheKey] = cacheEntry;
    LogDebug("AnalyzeSpoolFile: Cached result for Job " + std::to_string(jobId) + " with key " + cacheKey);
  } else {
    LogDebug("AnalyzeSpoolFile: NOT caching 0-page result for Job " + std::to_string(jobId));
  }

  LogDebug(
      "AnalyzeSpoolFile: FINAL - Grayscale=" + std::to_string(isGrayscale) +
      ", FillRate=" + std::to_string(fillRate) + "%");
}

JobDetails MonitorWorker::GetJobInfo(HANDLE hPrinter, DWORD jobId) {
  JobDetails details;
  details.jobId = jobId;
  details.paperSize = 0;
  details.duplex = 0;
  details.color = 0;
  details.copies = 1;    // Default to 1 copy
  details.icmMethod = 0; // Default ICM method
  details.totalPages = 0;
  details.isGrayscale =
      true;                // Will be refined by AnalyzeSpoolFile or Driver Mode
  details.fillRate = 0.0f; // Default to 0% fill

  DWORD needed = 0;
  GetJobW(hPrinter, jobId, 2, NULL, 0, &needed);

  if (needed == 0)
    return details;

  std::vector<BYTE> buffer(needed);
  if (!GetJobW(hPrinter, jobId, 2, buffer.data(), needed, &needed)) {
    return details;
  }

  JOB_INFO_2W *jobInfo = (JOB_INFO_2W *)buffer.data();

  details.printerName = LPWSTRToUTF8(jobInfo->pPrinterName);
  details.documentName = LPWSTRToUTF8(jobInfo->pDocument);

  // Use TotalPages if available, otherwise use PagesPrinted as fallback
  // Some applications don't set TotalPages, but PagesPrinted is updated
  // during printing
  if (jobInfo->TotalPages > 0) {
    details.totalPages = jobInfo->TotalPages;
  } else if (jobInfo->PagesPrinted > 0) {
    details.totalPages = jobInfo->PagesPrinted;
  }

  // Extract Time Submitted
  SYSTEMTIME st = jobInfo->Submitted;
  if (st.wYear > 0) {
    char timeBuf[64];
    sprintf_s(timeBuf, sizeof(timeBuf), "%04d-%02d-%02dT%02d:%02d:%02d.000Z",
              st.wYear, st.wMonth, st.wDay, st.wHour, st.wMinute, st.wSecond);
    details.timeSubmitted = std::string(timeBuf);
  } else {
    details.timeSubmitted = ""; // Empty if invalid
  }

  if (jobInfo->Status & JOB_STATUS_PRINTING)
    details.statusStr = "Printing";
  else if (jobInfo->Status & JOB_STATUS_SPOOLING)
    details.statusStr = "Spooling";
  else if (jobInfo->Status & JOB_STATUS_PAUSED)
    details.statusStr = "Paused";
  else if (jobInfo->Status & JOB_STATUS_ERROR)
    details.statusStr = "Error";
  else if (jobInfo->Status & JOB_STATUS_DELETING)
    details.statusStr = "Deleting";
  else if (jobInfo->Status & JOB_STATUS_PRINTED)
    details.statusStr = "Printed";
  else
    details.statusStr = "Processing";

  if (jobInfo->pDevMode != NULL) {
    if (jobInfo->pDevMode->dmFields & DM_PAPERSIZE)
      details.paperSize = jobInfo->pDevMode->dmPaperSize;

    if (jobInfo->pDevMode->dmFields & DM_DUPLEX)
      details.duplex = jobInfo->pDevMode->dmDuplex;

    if (jobInfo->pDevMode->dmFields & DM_COLOR)
      details.color = jobInfo->pDevMode->dmColor; // 1=Mono, 2=Color

    if (jobInfo->pDevMode->dmFields & DM_COPIES)
      details.copies = jobInfo->pDevMode->dmCopies;

    if (jobInfo->pDevMode->dmFields & DM_ICMMETHOD)
      details.icmMethod = jobInfo->pDevMode->dmICMMethod;
  }

  // Set default isGrayscale based on Driver Metadata
  // If Driver says Monochrome (1), it IS Grayscale.
  // If Driver says Color (2), assume Color UNTIL PROVEN GRAYSCALE by
  // analysis. This prevents "Color job detected as Grayscale" when SPL
  // analysis fails.
  if (details.color == 1) {
    details.isGrayscale = true;
  } else {
    details.isGrayscale = false; // Assume color if driver says so
  }

  // Generate unique UUID
  details.jobUuid = details.printerName + "_" + std::to_string(jobId) + "_" + details.timeSubmitted;
  // Sanitize UUID (replace spaces with underscores)
  for (size_t i = 0; i < details.jobUuid.length(); ++i) {
    if (details.jobUuid[i] == ' ') details.jobUuid[i] = '_';
    if (details.jobUuid[i] == ':') details.jobUuid[i] = '-';
  }

  // Analyze spool file for ACTUAL color content and fill rate
  // Analyze spool file for ACTUAL color content and fill rate
  // This will overwrite isGrayscale ONLY if analysis succeeds.
  // CRITICAL: We now allow analysis during spooling because size-tracking
  // will re-trigger analysis when the file grows.
  AnalyzeSpoolFile(jobId, details.documentName, details.isGrayscale,
                   details.fillRate, details.thumbnailUrl, details.totalPages);

  return details;
}

void MonitorWorker::OnProgress(const JobDetails *data, size_t count) {
  Napi::Env env = this->Env();
  Napi::HandleScope scope(env);

  for (size_t i = 0; i < count; i++) {
    Napi::Object obj = Napi::Object::New(env);
    obj.Set("jobId", Napi::Number::New(env, data[i].jobId));
    obj.Set("jobUuid", StringToNapiString(env, data[i].jobUuid));
    obj.Set("printerName", StringToNapiString(env, data[i].printerName));
    obj.Set("documentName", StringToNapiString(env, data[i].documentName));
    obj.Set("status", StringToNapiString(env, data[i].statusStr));
    obj.Set("paperSize", Napi::Number::New(env, data[i].paperSize));
    obj.Set("duplex", Napi::Number::New(env, data[i].duplex));
    obj.Set("color", Napi::Number::New(env, data[i].color));
    obj.Set("totalPages", Napi::Number::New(env, data[i].totalPages));
    obj.Set("copies", Napi::Number::New(env, data[i].copies));
    obj.Set("icmMethod", Napi::Number::New(env, data[i].icmMethod));
    obj.Set("isGrayscale", Napi::Boolean::New(env, data[i].isGrayscale));
    obj.Set("fillRate", Napi::Number::New(env, data[i].fillRate));
    obj.Set("thumbnailUrl", StringToNapiString(env, data[i].thumbnailUrl));
    obj.Set("timeSubmitted", StringToNapiString(env, data[i].timeSubmitted));

    Callback().Call({Napi::String::New(env, "job"), obj});
  }
}

// --- Implementation of Printer Functions ---

// Get default printer name
std::string GetDefaultPrinterName() {
  wchar_t buffer[260];
  DWORD size = sizeof(buffer) / sizeof(wchar_t);
  if (GetDefaultPrinterW(buffer, &size)) {
    return LPWSTRToUTF8(buffer);
  }
  return "";
}

Napi::Value GetPrinters(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();
  DWORD needed, returned;

  // First call to get size
  EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL, 0,
               &needed, &returned);

  if (needed == 0) {
    return Napi::Array::New(env);
  }

  std::vector<BYTE> buffer(needed);
  if (!EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2,
                    buffer.data(), needed, &needed, &returned)) {
    return Napi::Array::New(env);
  }

  PRINTER_INFO_2W *printers = (PRINTER_INFO_2W *)buffer.data();
  Napi::Array result = Napi::Array::New(env, returned);
  std::string defaultPrinter = GetDefaultPrinterName();

  for (DWORD i = 0; i < returned; i++) {
    Napi::Object printerObj = Napi::Object::New(env);
    std::string name = LPWSTRToUTF8(printers[i].pPrinterName);

    printerObj.Set("name", name);
    printerObj.Set("displayName", name); // Using same name for display mostly
    printerObj.Set("status", Napi::Number::New(env, printers[i].Status));
    printerObj.Set("isDefault",
                   Napi::Boolean::New(env, name == defaultPrinter));

    result.Set(i, printerObj);
  }

  return result;
}

Napi::Value GetPrinterCapabilities(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();
  if (info.Length() < 1 || !info[0].IsString()) {
    Napi::TypeError::New(env, "Printer name expected")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  std::wstring printerName = ToWString(info[0].As<Napi::String>().Utf8Value());
  HANDLE hPrinter;

  if (!OpenPrinterW((LPWSTR)printerName.c_str(), &hPrinter, NULL)) {
    Napi::Error::New(env, "Could not open printer")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  Napi::Object result = Napi::Object::New(env);

  // Duplex capability
  DWORD duplex =
      DeviceCapabilitiesW(printerName.c_str(), NULL, DC_DUPLEX, NULL, NULL);
  result.Set("duplex",
             Napi::Boolean::New(env, duplex == 1)); // 1 means supported

  // Color capability
  DWORD color =
      DeviceCapabilitiesW(printerName.c_str(), NULL, DC_COLORDEVICE, NULL, NULL);
  result.Set("color", Napi::Boolean::New(env, color == 1));

  ClosePrinter(hPrinter);
  return result;
}

Napi::Value PrintJob(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();

  if (info.Length() < 2 || !info[0].IsString() || !info[1].IsObject()) {
    Napi::TypeError::New(env, "Arguments: (pdfPath, optionsObject)")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  std::string pdfPath = info[0].As<Napi::String>();
  Napi::Object options = info[1].As<Napi::Object>();

  std::string printerName;
  if (options.Has("printer")) {
    printerName = options.Get("printer").As<Napi::String>();
  } else {
    printerName = GetDefaultPrinterName();
  }

  // --- Printing Logic (RAW) ---
  HANDLE hPrinter;
  DOC_INFO_1W docInfo;
  DWORD dwJob;
  DWORD dwBytesWritten;

  // Convert to Wide strings
  std::wstring wPrinterName = ToWString(printerName);
  std::wstring wPdfPath = ToWString(pdfPath);

  // Open Printer
  if (!OpenPrinterW((LPWSTR)wPrinterName.c_str(), &hPrinter, NULL)) {
    Napi::Object res = Napi::Object::New(env);
    res.Set("success", false);
    res.Set("message", "OpenPrinter failed");
    return res;
  }

  docInfo.pDocName = (LPWSTR)wPdfPath.c_str(); // Use filename as doc name
  docInfo.pOutputFile = NULL;
  docInfo.pDatatype = (LPWSTR)L"RAW";

  dwJob = StartDocPrinterW(hPrinter, 1, (LPBYTE)&docInfo);
  if (dwJob == 0) {
    ClosePrinter(hPrinter);
    Napi::Object res = Napi::Object::New(env);
    res.Set("success", false);
    res.Set("message", "StartDocPrinter failed");
    return res;
  }

  if (!StartPagePrinter(hPrinter)) {
    EndDocPrinter(hPrinter);
    ClosePrinter(hPrinter);
    Napi::Object res = Napi::Object::New(env);
    res.Set("success", false);
    res.Set("message", "StartPagePrinter failed");
    return res;
  }

  // Read file and write to printer
  std::ifstream file(pdfPath, std::ios::binary);
  if (!file) {
    EndPagePrinter(hPrinter);
    EndDocPrinter(hPrinter);
    ClosePrinter(hPrinter);
    Napi::Object res = Napi::Object::New(env);
    res.Set("success", false);
    res.Set("message", "Could not open source PDF file");
    return res;
  }

  char buffer[8192];
  while (file.read(buffer, sizeof(buffer)) || file.gcount() > 0) {
    if (!WritePrinter(hPrinter, buffer, file.gcount(), &dwBytesWritten)) {
      break; // Error writing
    }
  }

  EndPagePrinter(hPrinter);
  EndDocPrinter(hPrinter);
  ClosePrinter(hPrinter);

  Napi::Object res = Napi::Object::New(env);
  res.Set("success", true);
  res.Set("jobId", Napi::Number::New(env, dwJob));
  res.Set("printer", printerName);
  res.Set("message", "Job sent to spooler");

  return res;
}

// --- ReanalyzeJob ---
// Force re-analysis of a job, bypassing cache
// Returns: { success, isGrayscale, fillRate, thumbnailUrl }
Napi::Value ReanalyzeJob(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();

  if (info.Length() < 1 || !info[0].IsNumber()) {
    Napi::TypeError::New(env, "Job ID (number) required")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  DWORD jobId = info[0].As<Napi::Number>().Uint32Value();

  LogDebug("[ReanalyzeJob] Forcing re-analysis of Job " +
           std::to_string(jobId));

  // Clear cache for this job to force re-analysis
  // Build cache key prefix (jobId|) and remove all matching entries
  std::string keyPrefix = std::to_string(jobId) + "|";
  for (auto it = splAnalysisCache.begin(); it != splAnalysisCache.end();) {
    if (it->first.substr(0, keyPrefix.length()) == keyPrefix) {
      LogDebug("[ReanalyzeJob] Cleared cache for key " + it->first);
      it = splAnalysisCache.erase(it);
    } else {
      ++it;
    }
  }

  // Perform analysis
  bool isGrayscale = true;
  float fillRate = 0.0f;
  std::string thumbnailUrl = "";
  DWORD totalPages = 0;
  std::string documentName =
      "ReanalyzedJob"; // Placeholder, we don't have doc name here

  AnalyzeSpoolFile(jobId, documentName, isGrayscale, fillRate, thumbnailUrl, totalPages);

  // Build result object
  Napi::Object result = Napi::Object::New(env);
  result.Set("success", Napi::Boolean::New(env, !thumbnailUrl.empty()));
  result.Set("isGrayscale", Napi::Boolean::New(env, isGrayscale));
  result.Set("fillRate", Napi::Number::New(env, fillRate));
  result.Set("thumbnailUrl", Napi::String::New(env, thumbnailUrl));
  result.Set("totalPages", Napi::Number::New(env, totalPages));

  LogDebug("[ReanalyzeJob] Result: success=" +
           std::to_string(!thumbnailUrl.empty()) +
           ", isGrayscale=" + std::to_string(isGrayscale) + ", fillRate=" +
           std::to_string(fillRate) + ", thumbnailUrl=" + thumbnailUrl);

  return result;
}

// --- Init ---

MonitorWorker *globalWorker = nullptr;

Napi::Value StartMonitoring(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();

  if (info.Length() < 1 || !info[0].IsFunction()) {
    Napi::TypeError::New(env, "Callback function required")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  if (globalWorker) {
    return Napi::Boolean::New(env, false);
  }

  Napi::Function callback = info[0].As<Napi::Function>();
  globalWorker = new MonitorWorker(callback, env);
  globalWorker->Queue();

  return Napi::Boolean::New(env, true);
}

Napi::Value StopMonitoring(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();
  if (globalWorker) {
    globalWorker->Stop();
    globalWorker = nullptr;
  }
  return Napi::Boolean::New(env, true);
}

// Init Module
Napi::Object Init(Napi::Env env, Napi::Object exports) {
  exports.Set(Napi::String::New(env, "startPrinterMonitor"),
              Napi::Function::New(env, StartMonitoring));
  exports.Set(Napi::String::New(env, "stopPrinterMonitor"),
              Napi::Function::New(env, StopMonitoring));
  exports.Set(Napi::String::New(env, "getPrinters"),
              Napi::Function::New(env, GetPrinters));
  exports.Set(Napi::String::New(env, "getPrinterCapabilities"),
              Napi::Function::New(env, GetPrinterCapabilities));
  exports.Set(Napi::String::New(env, "printJob"),
              Napi::Function::New(env, PrintJob));
  exports.Set(Napi::String::New(env, "reanalyzeJob"),
              Napi::Function::New(env, ReanalyzeJob));

  return exports;
}

NODE_API_MODULE(win32_printer, Init)
