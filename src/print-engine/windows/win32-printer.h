#pragma once
#include <napi.h>
#include <atomic>
#include <string>
#include <vector>
#include <windows.h>
#include <winspool.h>

// ---------------------------------------------------------------------------
// JobEvent — données brutes émises vers Node.js pour chaque changement de job
// Pas d'analyse ici : Node.js s'occupe de tout le reste.
// ---------------------------------------------------------------------------
struct JobEvent {
  uint32_t    jobId;
  std::string printerName;
  std::string documentName;
  std::string status;       // "Spooling" | "Printing" | "Printed" | "Paused" | "Error" | "Deleting" | "Processing"
  std::string splPath;      // Chemin absolu vers le fichier .SPL (vide si inconnu)
  std::string format;       // "EMF" | "XPS" | "PCL" | "RAW" | "unknown"
  std::string timeSubmitted;// ISO 8601
  uint32_t    totalPages;
  uint32_t    copies;
  int         paperSize;    // dmPaperSize (DMPAPER_*)
  int         duplex;       // DMDUP_*
  int         color;        // 1=Mono 2=Color (driver metadata, non analysé)
  int         icmMethod;
};

// ---------------------------------------------------------------------------
// MonitorWorker — AsyncProgressWorker N-API
// Tourne dans un thread background, émet des JobEvent vers Node.js.
// ---------------------------------------------------------------------------
class MonitorWorker : public Napi::AsyncProgressWorker<JobEvent> {
public:
  MonitorWorker(Napi::Function callback, Napi::Env env)
    : Napi::AsyncProgressWorker<JobEvent>(callback), env_(env) {}

  void Execute(const ExecutionProgress& progress) override;
  void OnProgress(const JobEvent* data, size_t count) override;

  void Stop() { stopRequested_ = true; }

  // Helpers réutilisables (non dépendants du thread worker)
  std::string DetectSplFormat(const std::string& splPath);
  std::string FindSplPath(uint32_t jobId);

private:
  Napi::Env env_;
  // Lit les métadonnées brutes d'un job via GetJobW
  JobEvent    GetJobEvent(void* hPrinter, uint32_t jobId);

  std::atomic<bool> stopRequested_{false};
};

// ---------------------------------------------------------------------------
// API exportée vers Node.js
// ---------------------------------------------------------------------------
Napi::Value StartMonitoring(const Napi::CallbackInfo& info);
Napi::Value StopMonitoring(const Napi::CallbackInfo& info);
Napi::Value GetPrinters(const Napi::CallbackInfo& info);
Napi::Value GetPrinterCapabilities(const Napi::CallbackInfo& info);
Napi::Value PrintJob(const Napi::CallbackInfo& info);
Napi::Value ReanalyzeJob(const Napi::CallbackInfo& info);
