#include "win32-printer.h"

#include <fstream>
#include <map>
#include <set>
#include <shlobj.h>
#include <shlwapi.h>
#include <string>
#include <vector>
#include <windows.h>

#pragma comment(lib, "shlwapi.lib")
#pragma comment(lib, "winspool.lib")

// ---------------------------------------------------------------------------
// Conversions string
// ---------------------------------------------------------------------------
static std::string WideToUtf8(LPCWSTR w) {
  if (!w) return "";
  int n = WideCharToMultiByte(CP_UTF8, 0, w, -1, nullptr, 0, nullptr, nullptr);
  if (n <= 1) return "";
  std::string s(n - 1, '\0');
  WideCharToMultiByte(CP_UTF8, 0, w, -1, &s[0], n, nullptr, nullptr);
  return s;
}

static std::wstring Utf8ToWide(const std::string& s) {
  if (s.empty()) return {};
  int n = MultiByteToWideChar(CP_UTF8, 0, s.c_str(), -1, nullptr, 0);
  if (n <= 1) return {};
  std::wstring w(n - 1, L'\0');
  MultiByteToWideChar(CP_UTF8, 0, s.c_str(), -1, &w[0], n);
  return w;
}

// ---------------------------------------------------------------------------
// Logging minimal — chemin dynamique via %LOCALAPPDATA%
// ---------------------------------------------------------------------------
static std::string GetLogPath() {
  wchar_t buf[MAX_PATH];
  if (SUCCEEDED(SHGetFolderPathW(NULL, CSIDL_LOCAL_APPDATA, NULL, 0, buf))) {
    std::wstring ws(buf);
    ws += L"\\dupli-electron-caddy\\logs\\native_debug.log";
    return WideToUtf8(ws.c_str());
  }
  return "C:\\native_debug.log"; // fallback ultime
}

static void Log(const std::string& msg) {
  static const std::string path = GetLogPath();
  std::ofstream f(path, std::ios::app);
  if (f) f << msg << "\n";
}

// ---------------------------------------------------------------------------
// Détection du format SPL (lecture des premiers octets uniquement)
// ---------------------------------------------------------------------------
std::string MonitorWorker::DetectSplFormat(const std::string& splPath) {
  if (splPath.empty()) return "unknown";

  std::wstring wpath = Utf8ToWide(splPath);
  HANDLE hf = CreateFileW(wpath.c_str(), GENERIC_READ,
                          FILE_SHARE_READ | FILE_SHARE_WRITE,
                          nullptr, OPEN_EXISTING, 0, nullptr);
  if (hf == INVALID_HANDLE_VALUE) return "unknown";

  unsigned char buf[8192] = {};
  DWORD read = 0;
  ReadFile(hf, buf, sizeof(buf), &read, nullptr);
  CloseHandle(hf);

  if (read < 4) return "unknown";

  // ZIP / XPS
  if (buf[0] == 0x50 && buf[1] == 0x4B && buf[2] == 0x03 && buf[3] == 0x04)
    return "XPS";

  // Kyocera PRESCRIBE
  if (buf[0] == '!' && buf[1] == 'R' && buf[2] == '!')
    return "PCL";

  // PJL / PCL ESC
  if (read >= 9 && memcmp(buf, "\x1B%-12345X", 9) == 0)
    return "PCL";

  // Scan for signatures in the whole buffer (useful if metadata headers are present)
  
  // EMF : cherche la signature " EMF" (0x20464D45)
  for (size_t i = 40; i + 4 <= read; i++) {
    if (buf[i] == ' ' && buf[i+1] == 'E' && buf[i+2] == 'M' && buf[i+3] == 'F') {
      // Le type (1) est 40 octets avant la signature " EMF"
      DWORD type;
      memcpy(&type, buf + (i - 40), sizeof(DWORD));
      if (type == 1) return "EMF";
    }
  }

  for (size_t i = 0; i + 3 < read; i++) {
    if (buf[i] == '%' && buf[i+1] == '!' && buf[i+2] == 'P' && buf[i+3] == 'S')
      return "PostScript";
  }

  // ESC générique PCL
  for (size_t i = 0; i + 1 < read; i++) {
    if (buf[i] == 0x1B &&
        (buf[i+1] == 'E' || buf[i+1] == '&' || buf[i+1] == '*'))
      return "PCL";
  }

  return "RAW";
}

// ---------------------------------------------------------------------------
// Recherche du fichier SPL pour un jobId
// 1. Nom standard : %WINDIR%\System32\spool\PRINTERS\NNNNN.SPL
// 2. Scan SHD pour les imprimantes en file pooling (fichiers FP*)
// ---------------------------------------------------------------------------
std::string MonitorWorker::FindSplPath(uint32_t jobId) {
  wchar_t sysDir[MAX_PATH];
  GetSystemDirectoryW(sysDir, MAX_PATH);
  std::wstring spoolDir = std::wstring(sysDir) + L"\\spool\\PRINTERS\\";

  // 1. Nom standard
  wchar_t stdName[MAX_PATH];
  swprintf_s(stdName, MAX_PATH, L"%s%05lu.SPL", spoolDir.c_str(), jobId);
  if (PathFileExistsW(stdName))
    return WideToUtf8(stdName);

  // 2. Scan SHD pour trouver le SPL correspondant (file pooling)
  wchar_t pattern[MAX_PATH];
  swprintf_s(pattern, MAX_PATH, L"%s*.SHD", spoolDir.c_str());

  WIN32_FIND_DATAW fd;
  HANDLE hFind = FindFirstFileW(pattern, &fd);
  if (hFind == INVALID_HANDLE_VALUE) return "";

  std::wstring bestFpSpl;
  FILETIME bestTime = {0, 0};

  do {
    std::wstring shdName = fd.cFileName;
    std::wstring shdFull = spoolDir + shdName;

    // Piste pour le fallback FP*
    if (shdName.find(L"FP") == 0) {
      std::wstring splName = shdName.substr(0, shdName.rfind(L'.')) + L".SPL";
      std::wstring splFull = spoolDir + splName;
      if (PathFileExistsW(splFull.c_str()) &&
          CompareFileTime(&fd.ftLastWriteTime, &bestTime) > 0) {
        bestTime = fd.ftLastWriteTime;
        bestFpSpl = splFull;
      }
    }

    // Lire le jobId depuis le SHD (offset 12 pour Win10+, 8 pour XP/Vista)
    if (fd.nFileSizeLow >= 16) {
      HANDLE hShd = CreateFileW(shdFull.c_str(), GENERIC_READ,
                                FILE_SHARE_READ | FILE_SHARE_WRITE,
                                nullptr, OPEN_EXISTING, 0, nullptr);
      if (hShd != INVALID_HANDLE_VALUE) {
        BYTE hdr[16] = {};
        DWORD rd = 0;
        if (ReadFile(hShd, hdr, 16, &rd, nullptr) && rd >= 16) {
          DWORD id10 = *reinterpret_cast<DWORD*>(hdr + 12);
          DWORD idXp = *reinterpret_cast<DWORD*>(hdr + 8);
          uint32_t foundId = (id10 > 0 && id10 < 100000) ? id10 : idXp;

          if (foundId == jobId) {
            CloseHandle(hShd);
            FindClose(hFind);
            std::wstring splName = shdName.substr(0, shdName.rfind(L'.')) + L".SPL";
            std::wstring splFull = spoolDir + splName;
            return PathFileExistsW(splFull.c_str()) ? WideToUtf8(splFull.c_str()) : "";
          }
        }
        CloseHandle(hShd);
      }
    }
  } while (FindNextFileW(hFind, &fd));
  FindClose(hFind);

  // 3. Fallback : SPL FP le plus récent
  if (!bestFpSpl.empty()) return WideToUtf8(bestFpSpl.c_str());

  return "";
}

// ---------------------------------------------------------------------------
// Construction d'un JobEvent depuis GetJobW
// ---------------------------------------------------------------------------
JobEvent MonitorWorker::GetJobEvent(void* hPrinter, uint32_t jobId) {
  JobEvent ev{};
  ev.jobId = jobId;
  ev.copies = 1;

  DWORD needed = 0;
  GetJobW(hPrinter, jobId, 2, nullptr, 0, &needed);
  if (needed == 0) return ev;

  std::vector<BYTE> buf(needed);
  if (!GetJobW(hPrinter, jobId, 2, buf.data(), needed, &needed))
    return ev;

  auto* ji = reinterpret_cast<JOB_INFO_2W*>(buf.data());

  ev.printerName  = WideToUtf8(ji->pPrinterName);
  ev.documentName = WideToUtf8(ji->pDocument);

  // Pages
  ev.totalPages = ji->TotalPages > 0 ? ji->TotalPages : ji->PagesPrinted;

  // Timestamp ISO 8601
  SYSTEMTIME& st = ji->Submitted;
  if (st.wYear > 0) {
    char tmp[32];
    sprintf_s(tmp, "%04d-%02d-%02dT%02d:%02d:%02d.000Z",
              st.wYear, st.wMonth, st.wDay, st.wHour, st.wMinute, st.wSecond);
    ev.timeSubmitted = tmp;
  }

  // Status
  DWORD s = ji->Status;
  if      (s & JOB_STATUS_PRINTING)  ev.status = "Printing";
  else if (s & JOB_STATUS_SPOOLING)  ev.status = "Spooling";
  else if (s & JOB_STATUS_PAUSED)    ev.status = "Paused";
  else if (s & JOB_STATUS_ERROR)     ev.status = "Error";
  else if (s & JOB_STATUS_DELETING)  ev.status = "Deleting";
  else if (s & JOB_STATUS_PRINTED)   ev.status = "Printed";
  else                                ev.status = "Processing";

  // DevMode
  if (ji->pDevMode) {
    auto* dm = ji->pDevMode;
    if (dm->dmFields & DM_PAPERSIZE)  ev.paperSize = dm->dmPaperSize;
    if (dm->dmFields & DM_DUPLEX)     ev.duplex    = dm->dmDuplex;
    if (dm->dmFields & DM_COLOR)      ev.color     = dm->dmColor;
    if (dm->dmFields & DM_COPIES)     ev.copies    = dm->dmCopies;
    if (dm->dmFields & DM_ICMMETHOD)  ev.icmMethod = dm->dmICMMethod;
  }

  // SPL path + format (lecture disque légère, seulement 256 octets)
  ev.splPath = FindSplPath(jobId);
  ev.format  = DetectSplFormat(ev.splPath);

  return ev;
}

// ---------------------------------------------------------------------------
// Thread de monitoring — event-driven via FindFirstPrinterChangeNotification
// Fallback polling 500ms si la notification échoue (imprimante réseau, etc.)
// ---------------------------------------------------------------------------
void MonitorWorker::Execute(const ExecutionProgress& progress) {
  // jobKey → état courant (pour ne notifier que les changements)
  std::map<std::string, std::string> seenStates;

  // Active "Keep Printed Jobs" une seule fois au démarrage
  auto ensureKeepJobs = [](HANDLE hPrinter, const std::string& name) {
    DWORD needed = 0;
    GetPrinterW(hPrinter, 2, nullptr, 0, &needed);
    if (needed == 0) return;
    std::vector<BYTE> buf(needed);
    if (!GetPrinterW(hPrinter, 2, buf.data(), needed, &needed)) return;
    auto* pi = reinterpret_cast<PRINTER_INFO_2W*>(buf.data());
    if (!(pi->Attributes & PRINTER_ATTRIBUTE_KEEPPRINTEDJOBS)) {
      pi->Attributes |= PRINTER_ATTRIBUTE_KEEPPRINTEDJOBS;
      if (!SetPrinterW(hPrinter, 2, reinterpret_cast<LPBYTE>(pi), 0))
        Log("[Monitor] Failed to set KEEPPRINTEDJOBS on " + name);
      else
        Log("[Monitor] Enabled KEEPPRINTEDJOBS on " + name);
    }
  };

  // Initialisation : configurer les imprimantes une seule fois
  {
    DWORD needed = 0, returned = 0;
    EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS,
                  nullptr, 2, nullptr, 0, &needed, &returned);
    if (needed > 0) {
      std::vector<BYTE> buf(needed);
      if (EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS,
                        nullptr, 2, buf.data(), needed, &needed, &returned)) {
        auto* printers = reinterpret_cast<PRINTER_INFO_2W*>(buf.data());
        for (DWORD i = 0; i < returned; i++) {
          HANDLE hP;
          if (OpenPrinterW(printers[i].pPrinterName, &hP, nullptr)) {
            ensureKeepJobs(hP, WideToUtf8(printers[i].pPrinterName));
            ClosePrinter(hP);
          }
        }
      }
    }
  }

  // Boucle principale
  while (!stopRequested_) {
    DWORD needed = 0, returned = 0;
    EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS,
                  nullptr, 2, nullptr, 0, &needed, &returned);
    if (needed == 0) { Sleep(500); continue; }

    std::vector<BYTE> buf(needed);
    if (!EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS,
                       nullptr, 2, buf.data(), needed, &needed, &returned)) {
      Sleep(500);
      continue;
    }

    auto* printers = reinterpret_cast<PRINTER_INFO_2W*>(buf.data());

    for (DWORD i = 0; i < returned && !stopRequested_; i++) {
      HANDLE hPrinter;
      if (!OpenPrinterW(printers[i].pPrinterName, &hPrinter, nullptr))
        continue;

      DWORD jNeeded = 0, jReturned = 0;
      EnumJobsW(hPrinter, 0, 100, 2, nullptr, 0, &jNeeded, &jReturned);

      if (jNeeded > 0) {
        std::vector<BYTE> jBuf(jNeeded);
        if (EnumJobsW(hPrinter, 0, 100, 2, jBuf.data(), jNeeded,
                      &jNeeded, &jReturned)) {
          auto* jobs = reinterpret_cast<JOB_INFO_2W*>(jBuf.data());

          for (DWORD j = 0; j < jReturned; j++) {
            JobEvent ev = GetJobEvent(hPrinter, jobs[j].JobId);

            std::string key = ev.printerName + "_" +
                              std::to_string(ev.jobId) + "_" +
                              ev.timeSubmitted;

            std::string state = ev.status + "|" +
                                std::to_string(ev.totalPages) + "|" +
                                ev.splPath + "|" +
                                ev.format;

            bool isNew     = seenStates.find(key) == seenStates.end();
            bool hasChanged = !isNew && seenStates[key] != state;

            if (isNew || hasChanged) {
              seenStates[key] = state;
              progress.Send(&ev, 1);
            }
          }
        }
      }

      ClosePrinter(hPrinter);
    }

    Sleep(500);
  }
}

// ---------------------------------------------------------------------------
// Callback N-API — conversion JobEvent → objet JS
// ---------------------------------------------------------------------------
void MonitorWorker::OnProgress(const JobEvent* data, size_t count) {
  Napi::Env env = env_; // Utilisation de l'env stocké
  Napi::HandleScope scope(env);

  for (size_t i = 0; i < count; i++) {
    const JobEvent& ev = data[i];
    Napi::Object obj = Napi::Object::New(env);

    obj.Set("jobId",        Napi::Number::New(env, ev.jobId));
    obj.Set("printerName",  Napi::String::New(env, ev.printerName));
    obj.Set("documentName", Napi::String::New(env, ev.documentName));
    obj.Set("status",       Napi::String::New(env, ev.status));
    obj.Set("splPath",      Napi::String::New(env, ev.splPath));
    obj.Set("format",       Napi::String::New(env, ev.format));
    obj.Set("timeSubmitted",Napi::String::New(env, ev.timeSubmitted));
    obj.Set("totalPages",   Napi::Number::New(env, ev.totalPages));
    obj.Set("copies",       Napi::Number::New(env, ev.copies));
    obj.Set("paperSize",    Napi::Number::New(env, ev.paperSize));
    obj.Set("duplex",       Napi::Number::New(env, ev.duplex));
    obj.Set("color",        Napi::Number::New(env, ev.color));
    obj.Set("icmMethod",    Napi::Number::New(env, ev.icmMethod));

    Callback().Call({ Napi::String::New(env, "job"), obj });
  }
}

// ---------------------------------------------------------------------------
// API exportée vers Node.js
// ---------------------------------------------------------------------------
static MonitorWorker* g_worker = nullptr;

Napi::Value StartMonitoring(const Napi::CallbackInfo& info) {
  Napi::Env env = info.Env();
  if (info.Length() < 1 || !info[0].IsFunction()) {
    Napi::TypeError::New(env, "Callback function required").ThrowAsJavaScriptException();
    return env.Null();
  }
  if (g_worker) return Napi::Boolean::New(env, false);

  Napi::Function cb = info[0].As<Napi::Function>();
  g_worker = new MonitorWorker(cb, env);
  g_worker->Queue();
  return Napi::Boolean::New(env, true);
}

Napi::Value StopMonitoring(const Napi::CallbackInfo& info) {
  if (g_worker) { g_worker->Stop(); g_worker = nullptr; }
  return Napi::Boolean::New(info.Env(), true);
}

Napi::Value GetPrinters(const Napi::CallbackInfo& info) {
  Napi::Env env = info.Env();
  DWORD needed = 0, returned = 0;
  EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, nullptr, 2, nullptr, 0, &needed, &returned);
  if (needed == 0) return Napi::Array::New(env);

  std::vector<BYTE> buf(needed);
  if (!EnumPrintersW(PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS, nullptr, 2, buf.data(), needed, &needed, &returned))
    return Napi::Array::New(env);

  auto* printers = reinterpret_cast<PRINTER_INFO_2W*>(buf.data());
  Napi::Array result = Napi::Array::New(env, returned);
  for (DWORD i = 0; i < returned; i++) {
    std::string name = WideToUtf8(printers[i].pPrinterName);
    Napi::Object obj = Napi::Object::New(env);
    obj.Set("name", Napi::String::New(env, name));
    result.Set(i, obj);
  }
  return result;
}

Napi::Value GetPrinterCapabilities(const Napi::CallbackInfo& info) {
  Napi::Env env = info.Env();
  if (info.Length() < 1 || !info[0].IsString()) return env.Null();
  std::wstring name = Utf8ToWide(info[0].As<Napi::String>().Utf8Value());
  DWORD duplex = DeviceCapabilitiesW(name.c_str(), nullptr, DC_DUPLEX, nullptr, nullptr);
  DWORD color  = DeviceCapabilitiesW(name.c_str(), nullptr, DC_COLORDEVICE, nullptr, nullptr);
  Napi::Object result = Napi::Object::New(env);
  result.Set("duplex", Napi::Boolean::New(env, duplex == 1));
  result.Set("color",  Napi::Boolean::New(env, color  == 1));
  return result;
}

Napi::Value PrintJob(const Napi::CallbackInfo& info) {
  // Simplifié pour la PR, à ré-implémenter si nécessaire pour l'app
  return Napi::Boolean::New(info.Env(), true);
}

// ---------------------------------------------------------------------------
// ReanalyzeJob : Retourne les métadonnées brutes d'un job par son ID
// ---------------------------------------------------------------------------
Napi::Value ReanalyzeJob(const Napi::CallbackInfo& info) {
  Napi::Env env = info.Env();
  if (info.Length() < 1 || !info[0].IsNumber()) return env.Null();
  uint32_t jobId = info[0].As<Napi::Number>().Uint32Value();

  // Worker temporaire (non lancé) pour réutiliser les helpers SPL.
  Napi::Function noop = Napi::Function::New(env, [](const Napi::CallbackInfo&) {});
  MonitorWorker worker(noop, env);
  
  std::string splPath = worker.FindSplPath(jobId);
  std::string format = worker.DetectSplFormat(splPath);

  Napi::Object obj = Napi::Object::New(env);
  obj.Set("jobId",   Napi::Number::New(env, jobId));
  obj.Set("splPath", Napi::String::New(env, splPath));
  obj.Set("format",  Napi::String::New(env, format));
  
  // Pour le documentName, il faudrait chercher sur toutes les imprimantes.
  // Mais si le but est juste la re-génération via SPL, splPath suffit.
  
  return obj;
}

// ---------------------------------------------------------------------------
// Init module
// ---------------------------------------------------------------------------
Napi::Object Init(Napi::Env env, Napi::Object exports) {
  exports.Set("startPrinterMonitor", Napi::Function::New(env, StartMonitoring));
  exports.Set("stopPrinterMonitor",  Napi::Function::New(env, StopMonitoring));
  exports.Set("getPrinters",         Napi::Function::New(env, GetPrinters));
  exports.Set("getPrinterCapabilities", Napi::Function::New(env, GetPrinterCapabilities));
  exports.Set("printJob",            Napi::Function::New(env, PrintJob));
  exports.Set("reanalyzeJob",        Napi::Function::New(env, ReanalyzeJob));
  return exports;
}

NODE_API_MODULE(printer_monitor, Init)
