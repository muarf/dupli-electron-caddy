#include "win32-printer.h"
#include <algorithm>
#include <fstream>
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

// Global debug file path
const std::string DEBUG_LOG_PATH =
    "C:\\Users\\Dupli\\AppData\\Local\\Programs\\dupli-electron-caddy\\debug_"
    "emf.log";

void LogDebug(const std::string &message) {
  std::ofstream logFile(DEBUG_LOG_PATH, std::ios::app);
  if (logFile.is_open()) {
    logFile << message << std::endl;
    logFile.close();
  }
}

// Cache structure for SPL analysis results
struct SplAnalysisCache {
  bool isGrayscale;
  float fillRate;
  std::string timestamp;
  std::string thumbnailUrl;
};

struct EmfConversionResult {
  std::vector<std::wstring> pngPaths;
  std::string thumbnailUrl;
};

// Global cache for SPL analysis
std::map<DWORD, SplAnalysisCache> splAnalysisCache;

// --- Helpers ---

Napi::String StringToNapiString(Napi::Env env, const std::string &str) {
  return Napi::String::New(env, str.c_str());
}

std::string LPSTRToString(LPSTR lpstr) {
  if (lpstr == nullptr)
    return "";
  return std::string(lpstr);
}

// --- MonitorWorker Implementation ---

void MonitorWorker::Execute(const ExecutionProgress &progress) {
  // Store previously seen jobs with their status and page count
  // Key: printerName_jobId, Value: "status_totalPages" string
  std::map<std::string, std::string> seenJobStates;

  while (!stopRequested_) {
    // Enumerate all printers
    DWORD needed, returned;
    EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL,
                 0, &needed, &returned);

    if (needed > 0) {
      std::vector<BYTE> buffer(needed);
      if (EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2,
                       buffer.data(), needed, &needed, &returned)) {
        PRINTER_INFO_2 *printers = (PRINTER_INFO_2 *)buffer.data();

        for (DWORD i = 0; i < returned; i++) {
          std::string printerName = LPSTRToString(printers[i].pPrinterName);
          HANDLE hPrinter;

          if (OpenPrinter(printers[i].pPrinterName, &hPrinter, NULL)) {
            DWORD jobNeeded, jobReturned;
            EnumJobs(hPrinter, 0, 100, 2, NULL, 0, &jobNeeded, &jobReturned);

            if (jobNeeded > 0) {
              std::vector<BYTE> jobBuffer(jobNeeded);
              if (EnumJobs(hPrinter, 0, 100, 2, jobBuffer.data(), jobNeeded,
                           &jobNeeded, &jobReturned)) {
                JOB_INFO_2 *jobs = (JOB_INFO_2 *)jobBuffer.data();

                for (DWORD j = 0; j < jobReturned; j++) {
                  DWORD jobId = jobs[j].JobId;

                  // Get full job details to determine current state
                  JobDetails details = GetJobInfo(hPrinter, jobId);

                  // Create a key and state string for this job
                  std::string jobKey =
                      printerName + "_" + std::to_string(jobId);
                  std::string currentState =
                      details.statusStr + "_" +
                      std::to_string(details.totalPages) + "_" +
                      std::to_string(details.fillRate) + "_" +
                      details.statusStr + "_" +
                      std::to_string(details.totalPages) + "_" +
                      std::to_string(details.fillRate) + "_" +
                      std::to_string(details.isGrayscale) + "_" +
                      details.thumbnailUrl;

                  // Report if this is a new job OR if state changed (status or
                  // page count)
                  if (seenJobStates.find(jobKey) == seenJobStates.end() ||
                      seenJobStates[jobKey] != currentState) {
                    seenJobStates[jobKey] = currentState;
                    progress.Send(&details, 1);
                  }
                }
              }
            }
            ClosePrinter(hPrinter);
          }
        }
      }
    }

    // Poll every 100ms (faster to catch page count updates before job
    // disappears)
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
        return (long)start;
      }
    }
  }
  return -1;
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

  } catch (...) {
    LogDebug("Exception in ConvertEmfToPngViaPhpApi");
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
      // Threshold: 0.5% of pixels must be colored to be considered Color mode
      // This ignores small anti-aliasing artifacts or compression noise
      double colorPercentage =
          ((double)coloredPixels / (double)sampledPixels) * 100.0;
      if (colorPercentage > 0.5) {
        isGrayscale = false;
      } else {
        isGrayscale = true;
      }

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
    std::wcout << L"[FILLRATE] Found SPL via standard naming: " << standardName
               << std::endl;
    return std::wstring(standardName);
  }

  // Step 2: Scan SHD files to find matching Job ID
  std::wcout << L"[FILLRATE] Standard SPL not found, scanning SHD files..."
             << std::endl;

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
// Analyze spool file content for color detection and fill rate
// Now uses Ghostscript + pixel analysis for accurate results
void AnalyzeSpoolFile(DWORD jobId, bool &isGrayscale, float &fillRate,
                      std::string &thumbnailUrl) {
  LogDebug("AnalyzeSpoolFile: Starting PIXEL ANALYSIS for Job " +
           std::to_string(jobId));

  fillRate = 0.0f;

  // Check cache first
  if (splAnalysisCache.find(jobId) != splAnalysisCache.end()) {
    SplAnalysisCache cached = splAnalysisCache[jobId];
    isGrayscale = cached.isGrayscale;
    fillRate = cached.fillRate;
    thumbnailUrl = cached.thumbnailUrl;
    // thumbnailUrl is unused in this function's logic but available in cache
    LogDebug("AnalyzeSpoolFile: Using cached result - Grayscale=" +
             std::to_string(isGrayscale) +
             ", FillRate=" + std::to_string(fillRate));
    return;
  }

  wchar_t spoolPath[MAX_PATH];
  GetSystemDirectoryW(spoolPath, MAX_PATH);
  wcscat_s(spoolPath, L"\\spool\\PRINTERS\\");

  std::wcout << L"[FILLRATE] Scanning SPL files in: " << spoolPath << std::endl;

  // Use new universal SPL finder (supports standard + File Pooling)
  std::wstring foundFullPath =
      FindSplFileByJobId(jobId, std::wstring(spoolPath));

  if (foundFullPath.empty()) {
    std::cout << "[FILLRATE] No SPL file found for Job " << jobId << std::endl;
    return;
  }

  // Open SPL file
  HANDLE hFile = CreateFileW(foundFullPath.c_str(), GENERIC_READ,
                             FILE_SHARE_READ | FILE_SHARE_WRITE, NULL,
                             OPEN_EXISTING, 0, NULL);
  if (hFile == INVALID_HANDLE_VALUE) {
    std::cout << "[FILLRATE] Could not open SPL file. Error: " << GetLastError()
              << std::endl;
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
  if (emfOffset < 0) {
    std::cout << "[FILLRATE] EMF Signature NOT found in SPL file." << std::endl;
    return;
  }

  std::cout << "[FILLRATE] EMF found at offset " << emfOffset << std::endl;

  // Convert EMF to PNG(s) using PHP API (no need to extract EMF, API reads SPL
  // directly)
  // Convert EMF to PNG(s) using PHP API
  EmfConversionResult conversion = ConvertEmfToPngViaPhpApi(jobId);
  std::vector<std::wstring> pngFiles = conversion.pngPaths;

  if (pngFiles.empty()) {
    std::cout << "[FILLRATE] Failed to convert EMF to PNG" << std::endl;
    return;
  }

  std::cout << "[FILLRATE] Analyzing " << pngFiles.size() << " page(s)"
            << std::endl;

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

      std::wcout << L"[FILLRATE] Page " << pagesAnalyzed << L": "
                 << pageFillRate << L"%" << std::endl;
    }
  }

  // Calculate average fill rate
  if (pagesAnalyzed > 0) {
    fillRate = totalFillRate / pagesAnalyzed;
  }

  // Update Cache
  splAnalysisCache[jobId] = {isGrayscale, fillRate, "now",
                             conversion.thumbnailUrl};

  // IMPORTANT: Assign back to output parameter so caller sees it immediately
  thumbnailUrl = conversion.thumbnailUrl;

  std::cout << "[FILLRATE] Average Fill Rate: " << fillRate << "% across "
            << pagesAnalyzed << " page(s)" << std::endl;

  // Cleanup temp directory
  if (!pngFiles.empty()) {
    std::wstring tempDir = pngFiles[0];
    size_t lastSlash = tempDir.find_last_of(L"\\");
    if (lastSlash != std::wstring::npos) {
      tempDir = tempDir.substr(0, lastSlash);
      RemoveDirectoryW(tempDir.c_str());
    }
  }

  // Cache the result
  SplAnalysisCache cacheEntry;
  cacheEntry.isGrayscale = isGrayscale;
  cacheEntry.fillRate = fillRate;
  cacheEntry.timestamp = std::to_string(time(NULL));
  cacheEntry.thumbnailUrl = conversion.thumbnailUrl;
  splAnalysisCache[jobId] = cacheEntry;

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
  GetJob(hPrinter, jobId, 2, NULL, 0, &needed);

  if (needed == 0)
    return details;

  std::vector<BYTE> buffer(needed);
  if (!GetJob(hPrinter, jobId, 2, buffer.data(), needed, &needed)) {
    return details;
  }

  JOB_INFO_2 *jobInfo = (JOB_INFO_2 *)buffer.data();

  details.printerName = LPSTRToString(jobInfo->pPrinterName);
  details.documentName = LPSTRToString(jobInfo->pDocument);

  // Use TotalPages if available, otherwise use PagesPrinted as fallback
  // Some applications don't set TotalPages, but PagesPrinted is updated
  // during printing
  if (jobInfo->TotalPages > 0) {
    details.totalPages = jobInfo->TotalPages;
  } else if (jobInfo->PagesPrinted > 0) {
    details.totalPages = jobInfo->PagesPrinted;
  } else {
    details.totalPages = 0;
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

  // Analyze spool file for ACTUAL color content and fill rate
  // This will overwrite isGrayscale ONLY if analysis succeeds
  AnalyzeSpoolFile(jobId, details.isGrayscale, details.fillRate,
                   details.thumbnailUrl);

  return details;
}

void MonitorWorker::OnProgress(const JobDetails *data, size_t count) {
  Napi::HandleScope scope(env_);

  for (size_t i = 0; i < count; i++) {
    Napi::Object obj = Napi::Object::New(env_);
    obj.Set("jobId", Napi::Number::New(env_, data[i].jobId));
    obj.Set("printerName", StringToNapiString(env_, data[i].printerName));
    obj.Set("documentName", StringToNapiString(env_, data[i].documentName));
    obj.Set("status", StringToNapiString(env_, data[i].statusStr));
    obj.Set("paperSize", Napi::Number::New(env_, data[i].paperSize));
    obj.Set("duplex", Napi::Number::New(env_, data[i].duplex));
    obj.Set("color", Napi::Number::New(env_, data[i].color));
    obj.Set("totalPages", Napi::Number::New(env_, data[i].totalPages));
    obj.Set("copies", Napi::Number::New(env_, data[i].copies));
    obj.Set("icmMethod", Napi::Number::New(env_, data[i].icmMethod));
    obj.Set("isGrayscale", Napi::Boolean::New(env_, data[i].isGrayscale));
    obj.Set("fillRate", Napi::Number::New(env_, data[i].fillRate));
    obj.Set("thumbnailUrl", StringToNapiString(env_, data[i].thumbnailUrl));
    obj.Set("timeSubmitted", StringToNapiString(env_, data[i].timeSubmitted));

    Callback().Call({Napi::String::New(env_, "job"), obj});
  }
}

// --- Implementation of Printer Functions ---

// Get default printer name
std::string GetDefaultPrinterName() {
  char buffer[260];
  DWORD size = sizeof(buffer);
  if (GetDefaultPrinter(buffer, &size)) {
    return std::string(buffer);
  }
  return "";
}

Napi::Value GetPrinters(const Napi::CallbackInfo &info) {
  Napi::Env env = info.Env();
  DWORD needed, returned;

  // First call to get size
  EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL, 0,
               &needed, &returned);

  if (needed == 0) {
    return Napi::Array::New(env);
  }

  std::vector<BYTE> buffer(needed);
  if (!EnumPrinters(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2,
                    buffer.data(), needed, &needed, &returned)) {
    return Napi::Array::New(env);
  }

  PRINTER_INFO_2 *printers = (PRINTER_INFO_2 *)buffer.data();
  Napi::Array result = Napi::Array::New(env, returned);
  std::string defaultPrinter = GetDefaultPrinterName();

  for (DWORD i = 0; i < returned; i++) {
    Napi::Object printerObj = Napi::Object::New(env);
    std::string name = LPSTRToString(printers[i].pPrinterName);

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

  std::string printerName = info[0].As<Napi::String>();
  HANDLE hPrinter;

  if (!OpenPrinter(const_cast<char *>(printerName.c_str()), &hPrinter, NULL)) {
    Napi::Error::New(env, "Could not open printer")
        .ThrowAsJavaScriptException();
    return env.Null();
  }

  Napi::Object result = Napi::Object::New(env);

  // Duplex capability
  DWORD duplex =
      DeviceCapabilities(printerName.c_str(), NULL, DC_DUPLEX, NULL, NULL);
  result.Set("duplex",
             Napi::Boolean::New(env, duplex == 1)); // 1 means supported

  // Color capability
  DWORD color =
      DeviceCapabilities(printerName.c_str(), NULL, DC_COLORDEVICE, NULL, NULL);
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
  DOC_INFO_1 docInfo;
  DWORD dwJob;
  DWORD dwBytesWritten;

  // Open Printer
  if (!OpenPrinter(const_cast<char *>(printerName.c_str()), &hPrinter, NULL)) {
    Napi::Object res = Napi::Object::New(env);
    res.Set("success", false);
    res.Set("message", "OpenPrinter failed");
    return res;
  }

  docInfo.pDocName =
      const_cast<char *>(pdfPath.c_str()); // Use filename as doc name
  docInfo.pOutputFile = NULL;
  docInfo.pDatatype = (LPSTR) "RAW";

  dwJob = StartDocPrinter(hPrinter, 1, (LPBYTE)&docInfo);
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

  return exports;
}

NODE_API_MODULE(win32_printer, Init)
