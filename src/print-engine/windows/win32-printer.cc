#include <napi.h>
#include <windows.h>
#include <winspool.h>
#include <string>
#include <vector>
#include <map>
#include <fstream>
#include <iostream>
#include <shlwapi.h>
#include <ctime>

#pragma comment(lib, "winspool.lib")
#pragma comment(lib, "shlwapi.lib")

// --- Helper Functions for String Conversion ---

std::string ToUTF8(const std::wstring &wstr) {
  if (wstr.empty()) return std::string();
  int size_needed = WideCharToMultiByte(CP_UTF8, 0, &wstr[0], (int)wstr.size(), NULL, 0, NULL, NULL);
  std::string strTo(size_needed, 0);
  WideCharToMultiByte(CP_UTF8, 0, &wstr[0], (int)wstr.size(), &strTo[0], size_needed, NULL, NULL);
  return strTo;
}

std::string LPWSTRToUTF8(LPWSTR lpwstr) {
  if (lpwstr == nullptr) return "";
  return ToUTF8(std::wstring(lpwstr));
}

std::wstring ToWString(const std::string &str) {
  if (str.empty()) return std::wstring();
  int size_needed = MultiByteToWideChar(CP_UTF8, 0, &str[0], (int)str.size(), NULL, 0);
  std::wstring wstrTo(size_needed, 0);
  MultiByteToWideChar(CP_UTF8, 0, &str[0], (int)str.size(), &wstrTo[0], size_needed);
  return wstrTo;
}

std::string GetDebugLogPath() {
  wchar_t tempPath[MAX_PATH];
  GetTempPathW(MAX_PATH, tempPath);
  return ToUTF8(std::wstring(tempPath)) + "duplicator_native_debug.log";
}

void LogDebug(const std::string &message) {
  std::ofstream logFile(GetDebugLogPath(), std::ios::app);
  if (logFile.is_open()) {
    logFile << message << std::endl;
    logFile.close();
  }
}

struct SplAnalysisCache {
  bool isGrayscale;
  float fillRate;
  DWORD totalPages;
  std::string timestamp;
  std::string thumbnailUrl;
  std::string documentName;
  DWORD lastFileSize;
};

std::map<DWORD, SplAnalysisCache> splAnalysisCache;

struct JobDetails {
  DWORD jobId;
  std::string printerName;
  std::string documentName;
  std::string statusStr;
  DWORD status;
  DWORD paperSize;
  DWORD duplex;
  DWORD color;
  DWORD copies;
  DWORD icmMethod;
  DWORD totalPages;
  bool isGrayscale;
  float fillRate;
  std::string thumbnailUrl;
  std::string timeSubmitted;
};

JobDetails GetJobInfo(HANDLE hPrinter, DWORD jobId);

class MonitorWorker : public Napi::AsyncProgressWorker<JobDetails> {
public:
  MonitorWorker(Napi::Function &callback) : Napi::AsyncProgressWorker<JobDetails>(callback), stop_(false) {}
  void Execute(const ExecutionProgress &progress) override {
    std::map<std::string, std::string> seenJobStates;
    while (!stop_) {
      DWORD needed, returned;
      EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, NULL, 0, &needed, &returned);
      if (needed > 0) {
        std::vector<BYTE> buffer(needed);
        if (EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, NULL, 2, buffer.data(), needed, &needed, &returned)) {
          PRINTER_INFO_2W *printers = (PRINTER_INFO_2W *)buffer.data();
          for (DWORD i = 0; i < returned; i++) {
            HANDLE hPrinter;
            if (OpenPrinterW(printers[i].pPrinterName, &hPrinter, NULL)) {
              DWORD jNeeded, jReturned;
              EnumJobsW(hPrinter, 0, 100, 2, NULL, 0, &jNeeded, &jReturned);
              if (jNeeded > 0) {
                std::vector<BYTE> jBuffer(jNeeded);
                if (EnumJobsW(hPrinter, 0, 100, 2, jBuffer.data(), jNeeded, &jNeeded, &jReturned)) {
                  JOB_INFO_2W *jobs = (JOB_INFO_2W *)jBuffer.data();
                  for (DWORD j = 0; j < jReturned; j++) {
                    JobDetails d = GetJobInfo(hPrinter, jobs[j].JobId);
                    std::string key = d.printerName + "_" + std::to_string(d.jobId);
                    std::string state = d.statusStr + "_" + std::to_string(d.totalPages) + "_" + std::to_string(d.fillRate);
                    if (seenJobStates.find(key) == seenJobStates.end() || seenJobStates[key] != state) {
                      seenJobStates[key] = state;
                      progress.Send(&d, 1);
                    }
                  }
                }
              }
              ClosePrinter(hPrinter);
            }
          }
        }
      }
      Sleep(100);
    }
  }
  void OnProgress(const JobDetails *data, size_t count) override {
    Napi::HandleScope scope(Env());
    for (size_t i = 0; i < count; i++) {
      Napi::Object obj = Napi::Object::New(Env());
      obj.Set("jobId", Napi::Number::New(Env(), data[i].jobId));
      obj.Set("printerName", Napi::String::New(Env(), data[i].printerName.c_str()));
      obj.Set("documentName", Napi::String::New(Env(), data[i].documentName.c_str()));
      obj.Set("statusStr", Napi::String::New(Env(), data[i].statusStr.c_str()));
      obj.Set("status", Napi::Number::New(Env(), data[i].status));
      obj.Set("totalPages", Napi::Number::New(Env(), data[i].totalPages));
      obj.Set("isGrayscale", Napi::Boolean::New(Env(), data[i].isGrayscale));
      obj.Set("fillRate", Napi::Number::New(Env(), data[i].fillRate));
      obj.Set("thumbnailUrl", Napi::String::New(Env(), data[i].thumbnailUrl.c_str()));
      obj.Set("timeSubmitted", Napi::String::New(Env(), data[i].timeSubmitted.c_str()));
      Callback().Call({Napi::String::New(Env(), "job"), obj});
    }
  }
  void Stop() { stop_ = true; }
private:
  bool stop_;
};

void AnalyzeSpoolFile(DWORD jobId, const std::string &doc, bool &gray, float &fill, std::string &thumb, DWORD &pages) {
  LogDebug("AnalyzeSpoolFile: Job " + std::to_string(jobId));
  // Keep it simple for now to ensure compilation
}

JobDetails GetJobInfo(HANDLE hPrinter, DWORD jobId) {
  JobDetails d;
  d.jobId = jobId;
  d.totalPages = 0;
  d.isGrayscale = true;
  DWORD needed;
  GetJobW(hPrinter, jobId, 2, NULL, 0, &needed);
  if (needed > 0) {
    std::vector<BYTE> buf(needed);
    if (GetJobW(hPrinter, jobId, 2, buf.data(), needed, &needed)) {
      JOB_INFO_2W *ji = (JOB_INFO_2W *)buf.data();
      d.printerName = LPWSTRToUTF8(ji->pPrinterName);
      d.documentName = LPWSTRToUTF8(ji->pDocument);
      d.totalPages = ji->TotalPages;
      d.statusStr = (ji->Status & JOB_STATUS_PRINTED) ? "Printed" : (ji->Status & JOB_STATUS_SPOOLING) ? "Spooling" : "Processing";
      AnalyzeSpoolFile(jobId, d.documentName, d.isGrayscale, d.fillRate, d.thumbnailUrl, d.totalPages);
    }
  }
  return d;
}

Napi::Value GetPrinterCapabilities(const Napi::CallbackInfo &info) {
  std::wstring name = ToWString(info[0].As<Napi::String>().Utf8Value());
  int bins = DeviceCapabilitiesW(name.c_str(), NULL, DC_BINS, NULL, NULL);
  Napi::Object res = Napi::Object::New(info.Env());
  res.Set("bins", Napi::Number::New(info.Env(), bins));
  return res;
}

Napi::Value PrintJob(const Napi::CallbackInfo &info) {
  std::wstring name = ToWString(info[0].As<Napi::String>().Utf8Value());
  std::string data = info[1].As<Napi::String>().Utf8Value();
  HANDLE h;
  if (OpenPrinterW((LPWSTR)name.c_str(), &h, NULL)) {
    DOC_INFO_1W di = {(LPWSTR)L"Print Job", NULL, (LPWSTR)L"RAW"};
    if (StartDocPrinterW(h, 1, (LPBYTE)&di)) {
      StartPagePrinter(h);
      DWORD w; WritePrinter(h, (LPVOID)data.c_str(), (DWORD)data.length(), &w);
      EndPagePrinter(h); EndDocPrinter(h);
    }
    ClosePrinter(h);
  }
  return Napi::Boolean::New(info.Env(), true);
}

Napi::Value StartMonitor(const Napi::CallbackInfo &info) {
  Napi::Function cb = info[0].As<Napi::Function>();
  (new MonitorWorker(cb))->Queue();
  return info.Env().Undefined();
}

Napi::Object Init(Napi::Env env, Napi::Object exports) {
  exports.Set("startMonitor", Napi::Function::New(env, StartMonitor));
  exports.Set("getPrinterCapabilities", Napi::Function::New(env, GetPrinterCapabilities));
  exports.Set("printJob", Napi::Function::New(env, PrintJob));
  return exports;
}

NODE_API_MODULE(win32_printer, Init)
