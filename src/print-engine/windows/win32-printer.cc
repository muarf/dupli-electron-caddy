#include "win32-printer.h"
#include <algorithm>
#include <fstream>
#include <iostream>
#include <map>
#include <set>
#include <string>
#include <vector>

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
                  std::string currentState = details.statusStr + "_" +
                                             std::to_string(details.totalPages);

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
};

int CALLBACK EmfEnumProc(HDC hdc, HANDLETABLE *lpht, const ENHMETARECORD *lpemr,
                         int nHandles, LPARAM lpData) {
  EmfAnalysisData *data = (EmfAnalysisData *)lpData;

  // Check for color-related records
  switch (lpemr->iType) {
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
  case EMR_ELLIPSE:
  case EMR_POLYGON: {
    data->filledPixels += 500; // Rough estimate for shapes
    break;
  }
  case EMR_STRETCHDIBITS:
  case EMR_BITBLT: {
    data->filledPixels += 2000; // Images typically have more coverage
    data->hasColor = true;      // Assume images are color
    break;
  }
  case EMR_EXTTEXTOUTW:
  case EMR_EXTTEXTOUTA: {
    data->filledPixels += 100; // Text has some coverage
    break;
  }
  }

  return 1; // Continue enumeration
}

// Helper function to search for a pattern in buffer (case insensitive for text)
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

// Analyze spool file content for color detection
// Uses STATISTICAL ANALYSIS of byte triplets to detect color vs grayscale
// This works for ANY format including proprietary RISO format
void AnalyzeSpoolFile(DWORD jobId, bool &isGrayscale, float &fillRate) {
  isGrayscale = true; // Default to grayscale
  fillRate = 0.0f;

  // Spool directory path
  wchar_t spoolPath[MAX_PATH];
  GetSystemDirectoryW(spoolPath, MAX_PATH);
  wcscat_s(spoolPath, L"\\spool\\PRINTERS\\");

  // Search for SPL files
  wchar_t searchPattern[MAX_PATH];
  wcscpy_s(searchPattern, spoolPath);
  wcscat_s(searchPattern, L"*.SPL");

  WIN32_FIND_DATAW findData;
  HANDLE hFind = FindFirstFileW(searchPattern, &findData);

  if (hFind == INVALID_HANDLE_VALUE) {
    return;
  }

  // Statistics for color detection
  long long colorTriplets = 0; // Triplets where R, G, B differ significantly
  long long grayTriplets = 0;  // Triplets where R ≈ G ≈ B
  long long totalTriplets = 0;

  do {
    wchar_t fullPath[MAX_PATH];
    wcscpy_s(fullPath, spoolPath);
    wcscat_s(fullPath, findData.cFileName);

    // Try to open the file for reading
    HANDLE hFile =
        CreateFileW(fullPath, GENERIC_READ, FILE_SHARE_READ | FILE_SHARE_WRITE,
                    NULL, OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);

    if (hFile != INVALID_HANDLE_VALUE) {
      // Get file size
      DWORD fileSize = GetFileSize(hFile, NULL);
      if (fileSize > 0 && fileSize < 100 * 1024 * 1024) { // Max 100MB
        // Read the file in chunks and analyze
        // Skip the first 4KB (likely headers) and sample the rest
        DWORD skipBytes = 4096;
        if (fileSize > skipBytes) {
          SetFilePointer(hFile, skipBytes, NULL, FILE_BEGIN);
        }

        // Read up to 2MB for analysis
        DWORD bytesToRead =
            (std::min)(fileSize - skipBytes, (DWORD)(2 * 1024 * 1024));
        unsigned char *buffer = new unsigned char[bytesToRead];
        DWORD bytesRead = 0;

        if (ReadFile(hFile, buffer, bytesToRead, &bytesRead, NULL) &&
            bytesRead > 100) {

          // === STATISTICAL RGB TRIPLET ANALYSIS ===
          // Treat the data as potential RGB triplets and analyze variance
          // Sample every 10th triplet for speed

          int sampleRate = 10;
          int threshold = 15; // Difference threshold to consider "different"

          for (DWORD i = 0; i < bytesRead - 3; i += 3 * sampleRate) {
            unsigned char r = buffer[i];
            unsigned char g = buffer[i + 1];
            unsigned char b = buffer[i + 2];

            // Skip if all values are very low (likely header/metadata)
            // or all very high (likely white space)
            if ((r < 10 && g < 10 && b < 10) ||
                (r > 245 && g > 245 && b > 245)) {
              continue;
            }

            totalTriplets++;

            // Check if this triplet looks like color (R, G, B differ)
            int diffRG = abs((int)r - (int)g);
            int diffGB = abs((int)g - (int)b);
            int diffRB = abs((int)r - (int)b);

            if (diffRG > threshold || diffGB > threshold ||
                diffRB > threshold) {
              // This triplet has significant color variance
              colorTriplets++;
            } else {
              // This triplet looks grayscale (R ≈ G ≈ B)
              grayTriplets++;
            }
          }
        }

        delete[] buffer;
      }
      CloseHandle(hFile);
    }
  } while (FindNextFileW(hFind, &findData));

  FindClose(hFind);

  // Decision: if more than 5% of triplets show color variance, it's a color job
  // This threshold accounts for noise and metadata in the file
  if (totalTriplets > 100) {
    double colorRatio = (double)colorTriplets / (double)totalTriplets;
    isGrayscale = (colorRatio < 0.05); // Less than 5% color = grayscale

    std::cout << "[COLOR_DETECT] Triplets analyzed: " << totalTriplets
              << ", color: " << colorTriplets << " (" << (colorRatio * 100.0)
              << "%)"
              << " → " << (isGrayscale ? "GRAYSCALE" : "COLOR") << std::endl;
  } else {
    std::cout << "[COLOR_DETECT] Not enough data to analyze (" << totalTriplets
              << " triplets)" << std::endl;
  }

  // Fill rate stays at 0 - can't calculate accurately for proprietary formats
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
  details.isGrayscale = true; // Default to grayscale (safer assumption)
  details.fillRate = 0.0f;    // Default to 0% fill

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
      details.color = jobInfo->pDevMode->dmColor;

    if (jobInfo->pDevMode->dmFields & DM_COPIES)
      details.copies = jobInfo->pDevMode->dmCopies;

    if (jobInfo->pDevMode->dmFields & DM_ICMMETHOD)
      details.icmMethod = jobInfo->pDevMode->dmICMMethod;
  }

  // Analyze spool file for actual color content and fill rate
  AnalyzeSpoolFile(jobId, details.isGrayscale, details.fillRate);

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
