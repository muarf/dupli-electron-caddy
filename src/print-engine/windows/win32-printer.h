#pragma once

#include <napi.h>
#include <string>
#include <vector>
#include <windows.h>
#include <winspool.h>

// Structure simple pour passer les données du thread C++ vers JS
struct JobDetails {
  DWORD jobId;
  std::string jobUuid;
  std::string printerName;
  std::string documentName;
  std::string statusStr; // "Spooling", "Printing", etc.
  short paperSize;
  short duplex;
  short color;
  short copies;    // Number of copies requested
  DWORD icmMethod; // ICM method - might help detect grayscale
  DWORD totalPages;
  bool isGrayscale;         // true if EMF analysis shows grayscale content
  float fillRate;           // 0.0 to 100.0 - ink coverage percentage
  std::string thumbnailUrl; // URL to the generated thumbnail
  std::string
      timeSubmitted; // Creation time (ISO8601) to identify unique job events
};

// Worker asynchrone pour la surveillance
// Utilise AsyncProgressWorker pour envoyer des événements (progress) sans
// arrêter le thread
class MonitorWorker : public Napi::AsyncProgressWorker<JobDetails> {
public:
  MonitorWorker(Napi::Function &callback, Napi::Env env)
      : Napi::AsyncProgressWorker<JobDetails>(callback), env_(env) {
    stopRequested_ = false;
  }

  ~MonitorWorker() {}

  // Méthode pour demander l'arrêt propre du thread
  void Stop() { stopRequested_ = true; }

protected:
  // Le code qui tourne dans un thread séparé (BLOQUANT acceptés ici)
  void Execute(const ExecutionProgress &progress);

  // Appelé quand le thread envoie des données (via progress.Send)
  // S'exécute sur le thread principal JS
  void OnProgress(const JobDetails *data, size_t count);

  // Appelé quand le worker a fini (ou erreur)
  void OnOK() {}
  void OnError(const Napi::Error &e) {}

private:
  Napi::Env env_;
  bool stopRequested_;

  // Helpers privés
  JobDetails GetJobInfo(HANDLE hPrinter, DWORD jobId);
};
