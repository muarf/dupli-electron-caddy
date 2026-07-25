// =============================================================================
// printer_commands.rs - Pont Tauri ↔ Module natif Windows
//
// Responsabilité UNIQUE : Exposer les fonctions de `windows_native.rs` au
//                         frontend via des `#[tauri::command]` Tauri.
//
// Ce module :
//   - Définit des structs sérialisables (serde::Serialize) pour le JSON frontend
//   - Transforme les erreurs Win32 en messages d'erreur explicites pour l'utilisateur
//   - Ne contient AUCUN appel Win32 direct (délégué à windows_native.rs)
//   - Fournit des stubs vides sur les OS non-Windows pour que le code compile
// =============================================================================

use serde::Serialize;
use serde_json::Value;
use std::sync::{Arc, Mutex};
use tauri::{command, AppHandle, Emitter};

// =============================================================================
// Types sérialisables (JSON pour le frontend)
// Nommés différemment des types internes windows_native pour maintenir
// le découplage entre la couche OS et la couche présentation.
// =============================================================================

/// Imprimante sérialisée pour le frontend
#[derive(Debug, Clone, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct PrinterDto {
    pub name: String,
    pub driver_name: String,
    pub port_name: String,
    pub comment: String,
    pub location: String,
    pub status: u32,
    pub status_label: String,
    pub jobs_count: u32,
    pub is_default: bool,
    pub is_shared: bool,
}

/// Job d'impression sérialisé pour le frontend
#[derive(Debug, Clone, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct PrintJobDto {
    pub job_id: u32,
    pub document: String,
    pub machine_name: String,
    pub user_name: String,
    pub datatype: String,
    pub status: u32,
    pub status_label: String,
    pub pages_printed: u32,
    pub total_pages: u32,
    pub size_bytes: u32,
    pub priority: u32,
    pub is_duplex: bool,
    pub paper_size: String,
    pub is_color: bool,
}

// =============================================================================
// Commandes Tauri
// =============================================================================

/// Récupère la liste de toutes les imprimantes locales et réseau.
///
/// Côté frontend : `await invoke('get_printers')`
/// Retourne : `PrinterDto[]` (ou une erreur string)
#[command]
pub fn get_printers() -> Result<Vec<PrinterDto>, String> {
    log::info!("[printer_commands] get_printers() : demande de liste des imprimantes");

    #[cfg(target_os = "windows")]
    {
        crate::windows_native::enum_printers()
            .map(|printers| {
                log::info!("[printer_commands] get_printers() : {} imprimante(s) retournée(s)", printers.len());
                printers.into_iter().map(|p| PrinterDto {
                    name: p.name,
                    driver_name: p.driver_name,
                    port_name: p.port_name,
                    comment: p.comment,
                    location: p.location,
                    status: p.status,
                    status_label: p.status_label,
                    jobs_count: p.jobs_count,
                    is_default: p.is_default,
                    is_shared: p.is_shared,
                }).collect()
            })
            .map_err(|e| {
                log::error!("[printer_commands] get_printers() : erreur Win32 — {e}");
                format!("Impossible de lister les imprimantes : {e}")
            })
    }

    // Récupération des imprimantes CUPS sous Linux/macOS
    #[cfg(not(target_os = "windows"))]
    {
        log::info!("[printer_commands] get_printers() : exécution sous Linux/macOS");
        let mut printers = Vec::new();

        // 1. Déterminer l'imprimante par défaut via `lpstat -d`
        let default_printer = std::process::Command::new("lpstat")
            .arg("-d")
            .output()
            .ok()
            .and_then(|out| {
                if out.status.success() {
                    let stdout = String::from_utf8_lossy(&out.stdout);
                    // Format: "system default destination: Printer_Name"
                    stdout.split(':').nth(1).map(|s| s.trim().to_string())
                } else {
                    None
                }
            });

        // 2. Récupérer la liste des imprimantes via `lpstat -e`
        if let Ok(out) = std::process::Command::new("lpstat").arg("-e").output() {
            if out.status.success() {
                let stdout = String::from_utf8_lossy(&out.stdout);
                for line in stdout.lines() {
                    let name = line.trim().to_string();
                    if name.is_empty() { continue; }

                    let is_default = Some(&name) == default_printer.as_ref();
                    printers.push(PrinterDto {
                        name: name.clone(),
                        driver_name: "CUPS".to_string(),
                        port_name: "CUPS_Port".to_string(),
                        comment: "Imprimante CUPS système".to_string(),
                        location: "".to_string(),
                        status: 4, // 4 = Idle / Prête
                        status_label: "Prête".to_string(),
                        jobs_count: 0,
                        is_default,
                        is_shared: false,
                    });
                }
            } else {
                log::warn!("[printer_commands] get_printers() : lpstat -e a renvoyé une erreur");
            }
        } else {
            log::warn!("[printer_commands] get_printers() : impossible de lancer lpstat");
        }

        log::info!("[printer_commands] get_printers() : {} imprimante(s) trouvée(s) sous Linux/macOS", printers.len());
        Ok(printers)
    }
}

/// Récupère les jobs d'impression pour une imprimante donnée.
///
/// Côté frontend : `await invoke('get_print_jobs', { printerName: 'HP LaserJet' })`
/// Retourne : `PrintJobDto[]` (ou une erreur string)
#[command]
pub fn get_print_jobs(printer_name: String) -> Result<Vec<PrintJobDto>, String> {
    log::info!("[printer_commands] get_print_jobs('{}') : demande de jobs", printer_name);

    if printer_name.trim().is_empty() {
        return Err("Le nom de l'imprimante ne peut pas être vide.".to_string());
    }

    #[cfg(target_os = "windows")]
    {
        crate::windows_native::get_print_jobs(&printer_name)
            .map(|jobs| {
                log::info!(
                    "[printer_commands] get_print_jobs('{}') : {} job(s) retourné(s)",
                    printer_name, jobs.len()
                );
                jobs.into_iter().map(|j| PrintJobDto {
                    job_id: j.job_id,
                    document: j.document,
                    machine_name: j.machine_name,
                    user_name: j.user_name,
                    datatype: j.datatype,
                    status: j.status,
                    status_label: j.status_label,
                    pages_printed: j.pages_printed,
                    total_pages: j.total_pages,
                    size_bytes: j.size_bytes,
                    priority: j.priority,
                    is_duplex: j.is_duplex,
                    paper_size: j.paper_size.clone(),
                    is_color: j.is_color,
                }).collect()
            })
            .map_err(|e| {
                log::error!("[printer_commands] get_print_jobs('{}') : erreur Win32 — {e}", printer_name);
                format!("Impossible de récupérer les jobs de '{}' : {}", printer_name, e)
            })
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::warn!("[printer_commands] get_print_jobs() : non supporté sur cette plateforme");
        Ok(Vec::new())
    }
}

/// Supprime un job d'impression par son ID.
///
/// Côté frontend : `await invoke('delete_print_job', { printerName: 'HP LaserJet', jobId: 42 })`
/// Retourne : `null` (Ok) ou une erreur string
#[command]
pub fn delete_print_job(printer_name: String, job_id: u32) -> Result<(), String> {
    log::info!(
        "[printer_commands] delete_print_job('{}', job_id={}) : demande de suppression",
        printer_name, job_id
    );

    #[cfg(target_os = "windows")]
    {
        let mut resolved_printer_name = printer_name.trim().to_string();

        if resolved_printer_name.is_empty() {
            log::info!("[printer_commands] delete_print_job : printer_name vide, recherche du job {} dans toutes les imprimantes...", job_id);
            if let Ok(printers) = crate::windows_native::enum_printers() {
                for printer in &printers {
                    if printer.jobs_count == 0 { continue; }
                    // Ignorer les imprimantes réseau hors ligne / inaccessibles pour éviter le timeout Win32 (30-60s)
                    if (printer.status & (0x00000080 | 0x00000400 | 0x00001000)) != 0
                        || printer.status_label.to_lowercase().contains("offline")
                        || printer.status_label.to_lowercase().contains("hors ligne")
                    {
                        log::info!("[printer_commands] delete_print_job : saut de l'imprimante hors ligne '{}'", printer.name);
                        continue;
                    }
                    if let Ok(jobs) = crate::windows_native::get_print_jobs(&printer.name) {
                        if jobs.into_iter().any(|j| j.job_id == job_id) {
                            resolved_printer_name = printer.name.clone();
                            log::info!(
                                "[printer_commands] delete_print_job : job {} trouvé sur l'imprimante '{}'",
                                job_id, resolved_printer_name
                            );
                            break;
                        }
                    }
                }
            }
        }

        if resolved_printer_name.is_empty() {
            log::warn!("[printer_commands] delete_print_job : job {} non trouvé sur aucune imprimante, il a probablement déjà été supprimé ou imprimé", job_id);
            return Ok(());
        }

        crate::windows_native::delete_print_job(&resolved_printer_name, job_id)
            .map_err(|e| {
                log::error!(
                    "[printer_commands] delete_print_job('{}', {}) : erreur Win32 — {e}",
                    resolved_printer_name, job_id
                );
                // Message d'erreur orienté utilisateur final (pas de jargon technique Win32)
                if e.code == 5 {
                    "Accès refusé. Relancez l'application en tant qu'administrateur pour supprimer ce job.".to_string()
                } else {
                    format!("Impossible de supprimer le job {} : {}", job_id, e)
                }
            })
    }

    #[cfg(not(target_os = "windows"))]
    {
        // Formater l'identifiant pour CUPS : printer_name-job_id
        let cancel_arg = if printer_name.trim().is_empty() {
            format!("{}", job_id)
        } else {
            let clean_printer = printer_name.replace(' ', "_");
            format!("{}-{}", clean_printer, job_id)
        };

        log::info!(
            "[printer_commands] delete_print_job(job_id={}) : tentative via cancel avec '{}'",
            job_id, cancel_arg
        );
        
        let mut status = std::process::Command::new("cancel")
            .arg(&cancel_arg)
            .status();

        // Fallback si échec (essayer uniquement avec l'ID numérique)
        if (status.is_err() || !status.as_ref().unwrap().success()) && !printer_name.trim().is_empty() {
            log::info!(
                "[printer_commands] delete_print_job : échec avec '{}', tentative fallback avec '{}'",
                cancel_arg, job_id
            );
            status = std::process::Command::new("cancel")
                .arg(format!("{}", job_id))
                .status();
        }

        match status {
            Ok(s) if s.success() => {
                log::info!("[printer_commands] delete_print_job : job {} annulé avec succès", job_id);
                Ok(())
            }
            Ok(s) => {
                let code = s.code().unwrap_or(-1);
                Err(format!("La commande cancel (CUPS) a échoué avec le code de sortie : {}", code))
            }
            Err(e) => {
                Err(format!("Impossible de lancer la commande cancel : {}", e))
            }
        }
    }
}

/// Lance l'impression d'un fichier PDF depuis une URL ou un chemin de fichier.
///
/// Côté frontend : `await invoke('print_file', { filePath: '...', printerName: '...' })`
/// Délègue à `print_job` avec les mêmes paramètres.
#[command]
pub fn print_file(file_path: String, printer_name: String) -> Result<(), String> {
    log::info!(
        "[printer_commands] print_file('{}' → '{}') : délégation vers print_job",
        file_path, printer_name
    );

    // Validation préliminaire
    if file_path.trim().is_empty() {
        return Err("Le chemin du fichier est vide.".to_string());
    }
    if printer_name.trim().is_empty() {
        return Err("Le nom de l'imprimante est vide.".to_string());
    }

    // Délègue à print_job avec les options appropriées
    let options = serde_json::json!({
        "printerName": printer_name,
        "copies": 1
    });
    print_job(file_path, options)
}

/// Récupère les capacités d'une imprimante (formats, couleur, duplex, etc.)
///
/// Côté frontend : `await invoke('get_printer_capabilities', { printerName: '...' })`
#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct PrinterCapabilities {
    pub supports_color: bool,
    pub supports_duplex: bool,
    pub supports_staple: bool,
    pub max_copies: u32,
    pub supported_paper_sizes: Vec<String>,
}

#[command]
pub fn get_printer_capabilities(printer_name: String) -> Result<PrinterCapabilities, String> {
    log::info!(
        "[printer_commands] get_printer_capabilities('{}') : demande",
        printer_name
    );

    if printer_name.trim().is_empty() {
        return Err("Le nom de l'imprimante ne peut pas être vide.".to_string());
    }

    #[cfg(target_os = "windows")]
    {
        // TODO : Implémenter via DeviceCapabilitiesW (Win32) dans windows_native.rs
        // Retourne des valeurs par défaut conservatrices pour l'instant
        log::warn!(
            "[printer_commands] get_printer_capabilities('{}') : implémentation partielle, retour des valeurs par défaut",
            printer_name
        );

        Ok(PrinterCapabilities {
            supports_color: false,
            supports_duplex: false,
            supports_staple: false,
            max_copies: 999,
            supported_paper_sizes: vec!["A4".to_string(), "Letter".to_string()],
        })
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::info!("[printer_commands] get_printer_capabilities('{}') : exécution sous Linux/macOS", printer_name);
        
        let mut supports_color = false;
        let mut supports_duplex = false;
        let mut supports_staple = false;
        let mut supported_paper_sizes = Vec::new();

        if let Ok(out) = std::process::Command::new("lpoptions")
            .args(["-p", &printer_name, "-l"])
            .output()
        {
            if out.status.success() {
                let stdout = String::from_utf8_lossy(&out.stdout);
                for line in stdout.lines() {
                    let trimmed = line.trim();
                    if trimmed.is_empty() { continue; }

                    if trimmed.starts_with("Duplex/") || trimmed.starts_with("sides/") {
                        supports_duplex = true;
                    }
                    if trimmed.starts_with("StapleLocation/") || trimmed.starts_with("Staple/") {
                        supports_staple = true;
                    }
                    if trimmed.starts_with("ColorModel/") || trimmed.starts_with("print-color-mode/") {
                        if let Some(colon_idx) = trimmed.find(':') {
                            let values = trimmed[colon_idx + 1..].to_lowercase();
                            if values.contains("color") || values.contains("rgb") {
                                supports_color = true;
                            }
                        }
                    }
                    if trimmed.starts_with("PageSize/") || trimmed.starts_with("media/") {
                        if let Some(colon_idx) = trimmed.find(':') {
                            let values = &trimmed[colon_idx + 1..];
                            for val in values.split_whitespace() {
                                let clean_val = val.trim_start_matches('*');
                                if !supported_paper_sizes.contains(&clean_val.to_string()) {
                                    supported_paper_sizes.push(clean_val.to_string());
                                }
                            }
                        }
                    }
                }
            }
        }

        if supported_paper_sizes.is_empty() {
            supported_paper_sizes.push("A4".to_string());
        }

        Ok(PrinterCapabilities {
            supports_color,
            supports_duplex,
            supports_staple,
            max_copies: 9999,
            supported_paper_sizes,
        })
    }
}

// =============================================================================
// Moniteur d'impression — état partagé entre les commandes
// =============================================================================

/// État global du moniteur d'impression.
/// Enregistré dans Tauri via `app.manage(PrintMonitorState::new())`.
/// Thread-safe : `Arc<Mutex<...>>` pour accès depuis plusieurs commandes async.
pub struct PrintMonitorState {
    /// Handle de la tâche de surveillance (Some = actif, None = arrêté)
    handle: Arc<Mutex<Option<tauri::async_runtime::JoinHandle<()>>>>,
}

impl PrintMonitorState {
    pub fn new() -> Self {
        PrintMonitorState {
            handle: Arc::new(Mutex::new(None)),
        }
    }

    /// Retourne `true` si le moniteur est actuellement actif.
    pub fn is_running(&self) -> bool {
        self.handle
            .lock()
            .map(|g| g.is_some())
            .unwrap_or(false)
    }

    /// Démarre le moniteur si pas déjà actif.
    /// Spawne une tâche Tokio qui poll le spouleur toutes les 2 secondes.
    pub fn start(&self, app_handle: AppHandle) {
        let mut guard = match self.handle.lock() {
            Ok(g) => g,
            Err(_) => { log::error!("[PrintMonitorState] Mutex empoisonné"); return; }
        };

        if guard.is_some() {
            log::warn!("[PrintMonitorState] start() appelé mais le moniteur est déjà actif");
            return;
        }

        let handle = tauri::async_runtime::spawn(async move {
            log::info!("[PrintMonitorState] Moniteur démarré — polling toutes les 2 secondes");
            let _ = app_handle.emit("print-monitor-started", ());

            // Cache des états vus : clé composite → état courant.
            // Clé   = "{printerName}_{jobId}_{timeSubmitted}" (comme la branche beta C++)
            // Valeur = "{status}|{totalPages}"
            //
            // Avantage vs un simple HashSet<u32> :
            //  - Windows recycle les job_id (1..~999). Avec un HashSet<u32>, un ID recyclé
            //    n'est jamais ré-émis. Avec la clé composite, deux jobs qui auraient le même
            //    job_id mais des timeSubmitted différents sont correctement distincts.
            //  - Les changements de statut (Spooling → Printing → Printed) sont détectés
            //    et ré-émis au frontend pour mise à jour de l'interface.
            let mut seen_states: std::collections::HashMap<String, String> = std::collections::HashMap::new();
            let mut initial_scan_done = false;

            loop {
                tokio::time::sleep(tokio::time::Duration::from_secs(2)).await;

                // Sur Windows : récupérer tous les jobs de toutes les imprimantes
                #[cfg(target_os = "windows")]
                {
                    match crate::windows_native::enum_printers() {
                        Ok(printers) => {
                            for printer in &printers {
                                if printer.jobs_count == 0 { continue; }
                                match crate::windows_native::get_print_jobs(&printer.name) {
                                    Ok(jobs) => {
                                        for job in jobs {
                                            // Clé composite identifiant ce job de façon unique
                                            let job_key = format!(
                                                "{}_{}_{}", printer.name, job.job_id, job.time_submitted
                                            );
                                            // État courant : statut + pages pour détecter les mises à jour
                                            let job_state = format!("{}|{}", job.status, job.total_pages);

                                            let is_new = !seen_states.contains_key(&job_key);
                                            let has_changed = !is_new && seen_states.get(&job_key) != Some(&job_state);

                                            if is_new || has_changed {
                                                if is_new {
                                                    log::info!(
                                                        "[PrintMonitorState] Nouveau job détecté : id={} doc='{}' imprimante='{}' submitted='{}'",
                                                        job.job_id, job.document, printer.name, job.time_submitted
                                                    );
                                                } else {
                                                    log::info!(
                                                        "[PrintMonitorState] Job mis à jour : id={} doc='{}' état='{}'",
                                                        job.job_id, job.document, job_state
                                                    );
                                                }

                                                seen_states.insert(job_key, job_state);

                                                let payload = serde_json::json!({
                                                    "jobId": job.job_id,
                                                    "document": job.document,
                                                    "printerName": printer.name,
                                                    "status": job.status,
                                                    "statusLabel": job.status_label,
                                                    "totalPages": job.total_pages,
                                                    "sizeBytes": job.size_bytes,
                                                    "isDuplex": job.is_duplex,
                                                    "paperSize": job.paper_size,
                                                    "isGrayscale": !job.is_color,
                                                    "copies": job.copies,
                                                    "timeSubmitted": job.time_submitted,
                                                });
                                                let _ = app_handle.emit("print-job-detected", payload);
                                            }
                                        }
                                    }
                                    Err(e) => log::warn!(
                                        "[PrintMonitorState] Impossible de récupérer les jobs de '{}' : {}",
                                        printer.name, e
                                    ),
                                }
                            }
                        }
                        Err(e) => log::error!("[PrintMonitorState] enum_printers() échoué : {}", e),
                    }
                }

                // Sur non-Windows : polling CUPS via lpstat
                #[cfg(not(target_os = "windows"))]
                {
                    let is_initial = !initial_scan_done;
                    if is_initial {
                        initial_scan_done = true;
                    }

                    if let Ok(out) = std::process::Command::new("lpstat")
                        .args(["-W", "all", "-o"])
                        .output()
                    {
                        if out.status.success() {
                            let stdout = String::from_utf8_lossy(&out.stdout);
                            for line in stdout.lines() {
                                let parts: Vec<&str> = line.split_whitespace().collect();
                                if parts.len() >= 3 {
                                    let job_part = parts[0];
                                    let size_bytes: u32 = parts[2].parse().unwrap_or(0);
                                    
                                    if let Some(dash_idx) = job_part.rfind('-') {
                                        let printer_name = job_part[..dash_idx].to_string();
                                        let job_id_str = &job_part[dash_idx + 1..];
                                        if let Ok(job_id) = job_id_str.parse::<u32>() {
                                            // Pour CUPS, pas de time_submitted — on utilise
                                            // une clé composite avec suffixe fixe "cups".
                                            let job_key = format!("{}_{}_{}", printer_name, job_id, "cups");
                                            let is_new = !seen_states.contains_key(&job_key);
                                            if is_new {
                                                seen_states.insert(job_key, "cups".to_string());
                                                if !is_initial {
                                                    log::info!(
                                                        "[PrintMonitorState] (CUPS) Nouveau job détecté : id={} doc='Job {}' imprimante='{}'",
                                                        job_id, job_id, printer_name
                                                    );
                                                    let payload = serde_json::json!({
                                                        "jobId": job_id,
                                                        "document": format!("Job {}", job_id),
                                                        "printerName": printer_name,
                                                        "status": 3, // Spooled/Printing
                                                        "statusLabel": "Prêt".to_string(),
                                                        "totalPages": 1,
                                                        "sizeBytes": size_bytes,
                                                        "isDuplex": false,
                                                        "paperSize": "A4",
                                                        "isGrayscale": false,
                                                    });
                                                    let _ = app_handle.emit("print-job-detected", payload);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });

        *guard = Some(handle);
        log::info!("[PrintMonitorState] Tâche de surveillance enregistrée");
    }

    /// Arrête le moniteur si actif.
    pub fn stop(&self) {
        let mut guard = match self.handle.lock() {
            Ok(g) => g,
            Err(_) => { log::error!("[PrintMonitorState] Mutex empoisonné dans stop()"); return; }
        };

        if let Some(task_handle) = guard.take() {
            task_handle.abort();
            log::info!("[PrintMonitorState] Tâche de surveillance annulée");
        } else {
            log::warn!("[PrintMonitorState] stop() appelé mais le moniteur n'était pas actif");
        }
    }
}

// =============================================================================
// Nouvelles commandes Tauri
// =============================================================================

/// Démarre ou arrête la surveillance continue du spouleur d'impression.
///
/// Côté frontend : `await invoke('toggle_printer_monitor', { start: true })`
#[command]
pub fn toggle_printer_monitor(
    start: bool,
    app_handle: AppHandle,
    state: tauri::State<PrintMonitorState>,
) -> Result<(), String> {
    if start {
        log::info!("[printer_commands] toggle_printer_monitor(start=true) : activation du moniteur");
        state.start(app_handle);
        Ok(())
    } else {
        log::info!("[printer_commands] toggle_printer_monitor(start=false) : arrêt du moniteur");
        state.stop();
        Ok(())
    }
}

/// Retourne le statut actuel du moniteur d'impression.
///
/// Côté frontend : `await invoke('get_printer_monitor_status')`
/// Retourne : `{ isRunning: bool }`
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct MonitorStatus {
    pub is_running: bool,
}

#[command]
pub fn get_printer_monitor_status(
    state: tauri::State<PrintMonitorState>,
) -> Result<MonitorStatus, String> {
    let is_running = state.is_running();
    log::debug!("[printer_commands] get_printer_monitor_status() : is_running={}", is_running);
    Ok(MonitorStatus { is_running })
}

/// Supprime une imprimante du système.
///
/// Côté frontend : `await invoke('delete_printer', { printerName: 'HP LaserJet' })`
#[command]
pub fn delete_printer(printer_name: String) -> Result<(), String> {
    log::info!("[printer_commands] delete_printer('{}') : demande de suppression", printer_name);

    if printer_name.trim().is_empty() {
        return Err("Le nom de l'imprimante ne peut pas être vide.".to_string());
    }

    #[cfg(target_os = "windows")]
    {
        crate::windows_native::delete_printer(&printer_name)
            .map_err(|e| {
                log::error!("[printer_commands] delete_printer('{}') : erreur Win32 — {e}", printer_name);
                if e.code == 5 {
                    "Accès refusé. Relancez l'application en tant qu'administrateur pour supprimer cette imprimante.".to_string()
                } else {
                    format!("Impossible de supprimer l'imprimante '{}' : {}", printer_name, e)
                }
            })
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::warn!("[printer_commands] delete_printer() : non supporté sur cette plateforme");
        Err("La suppression d'imprimante n'est supportée que sous Windows.".to_string())
    }
}

/// Réanalyse un job d'impression en récupérant ses informations actualisées depuis le spouleur.
///
/// Côté frontend :
/// `await invoke('reanalyze_print_job', { jobId, documentName, format, splPath, driverColor })`
/// Résultat de la réanalyse d'un job
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ReanalyzeResult {
    pub job_id: u32,
    pub found: bool,
    pub document: String,
    pub status: u32,
    pub status_label: String,
    pub total_pages: u32,
    pub size_bytes: u32,
    pub is_grayscale: bool,
    pub is_duplex: bool,
    pub paper_size: String,
    pub fill_rate: f64,
    pub thumbnail_url: String,
}

#[command]
pub fn reanalyze_print_job(
    job_id: u32,
    document_name: String,
    format: String,
    spl_path: String,
    driver_color: bool,
) -> Result<ReanalyzeResult, String> {
    log::info!(
        "[printer_commands] reanalyze_print_job(job_id={}, doc='{}', format='{}') : début",
        job_id, document_name, format
    );
    log::debug!(
        "[printer_commands] reanalyze_print_job : spl_path='{}' driver_color={}",
        spl_path, driver_color
    );

    #[cfg(target_os = "windows")]
    {
        // Chercher le job dans TOUTES les imprimantes
        let printers = crate::windows_native::enum_printers()
            .map_err(|e| format!("Impossible de lister les imprimantes : {}", e))?;

        for printer in &printers {
            if printer.jobs_count == 0 { continue; }
            // Ignorer les imprimantes réseau hors ligne / inaccessibles pour éviter le timeout Win32 (30-60s)
            if (printer.status & (0x00000080 | 0x00000400 | 0x00001000)) != 0
                || printer.status_label.to_lowercase().contains("offline")
                || printer.status_label.to_lowercase().contains("hors ligne")
            {
                log::info!("[printer_commands] reanalyze_print_job : saut de l'imprimante hors ligne '{}'", printer.name);
                continue;
            }
            if let Ok(jobs) = crate::windows_native::get_print_jobs(&printer.name) {
                if let Some(job) = jobs.into_iter().find(|j| j.job_id == job_id) {
                    log::info!(
                        "[printer_commands] reanalyze_print_job : job {} trouvé sur '{}'",
                        job_id, printer.name
                    );
                    return Ok(ReanalyzeResult {
                        job_id: job.job_id,
                        found: true,
                        document: job.document,
                        status: job.status,
                        status_label: job.status_label,
                        total_pages: job.total_pages,
                        size_bytes: job.size_bytes,
                        is_grayscale: !job.is_color,
                        is_duplex: job.is_duplex,
                        paper_size: job.paper_size.clone(),
                        fill_rate: 0.0,
                        thumbnail_url: "".to_string(),
                    });
                }
            }
        }

        // Job non trouvé dans le spouleur (peut-être déjà terminé)
        log::warn!("[printer_commands] reanalyze_print_job : job {} non trouvé dans le spouleur", job_id);
        Ok(ReanalyzeResult {
            job_id,
            found: false,
            document: document_name,
            status: 0,
            status_label: "Job introuvable dans le spouleur".to_string(),
            total_pages: 0,
            size_bytes: 0,
            is_grayscale: false,
            is_duplex: false,
            paper_size: "A4".to_string(),
            fill_rate: 0.0,
            thumbnail_url: "".to_string(),
        })
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::info!("[printer_commands] reanalyze_print_job() : exécution sous Linux/macOS");

        // 1. Déterminer le chemin du spool file
        let mut source_path = std::path::PathBuf::from(&spl_path);
        if spl_path.is_empty() || !source_path.exists() {
            let padded_id = format!("{:05}", job_id);
            let p1 = std::path::PathBuf::from(format!("/var/spool/cups/d{}-001", padded_id));
            let p2 = std::path::PathBuf::from(format!("/var/spool/cups/d{}", padded_id));
            if p1.exists() {
                source_path = p1;
            } else if p2.exists() {
                source_path = p2;
            }
        }

        if !source_path.exists() {
            log::warn!("[printer_commands] reanalyze_print_job : spool file introuvable pour le job {}", job_id);
            return Ok(ReanalyzeResult {
                job_id,
                found: false,
                document: document_name,
                status: 0,
                status_label: "Fichier spool introuvable".to_string(),
                total_pages: 0,
                size_bytes: 0,
                is_grayscale: false,
                is_duplex: false,
                paper_size: "A4".to_string(),
                fill_rate: 0.0,
                thumbnail_url: "".to_string(),
            });
        }

        // 2. Copier vers /tmp pour l'analyse Ghostscript
        let temp_copy_str = format!("/tmp/convert_job_{}.pdf", job_id);
        if let Err(e) = std::fs::copy(&source_path, &temp_copy_str) {
            log::error!("[printer_commands] reanalyze_print_job : impossible de copier le spool {} vers /tmp : {}", job_id, e);
            return Err(format!("Impossible de lire le fichier spool : {}", e));
        }

        // 3. Créer le dossier des miniatures
        let thumb_dir = std::path::Path::new("/tmp/dupli_thumbnails");
        if !thumb_dir.exists() {
            let _ = std::fs::create_dir_all(thumb_dir);
        }

        // 4. Générer les miniatures via gs
        let output_pattern = format!("/tmp/dupli_thumbnails/job_{}_page_%d.png", job_id);
        let _ = std::process::Command::new("gs")
            .args([
                "-dNOPAUSE",
                "-dBATCH",
                "-dSAFER",
                "-dQUIET",
                "-sDEVICE=png16m",
                "-r72",
                &format!("-sOutputFile={}", output_pattern),
                &temp_copy_str,
            ])
            .status();

        // 5. Analyser la couverture d'encre (ink_cov) via gs
        let mut total_c = 0.0;
        let mut total_m = 0.0;
        let mut total_y = 0.0;
        let mut total_k = 0.0;
        let mut pages = 0;

        if let Ok(out) = std::process::Command::new("gs")
            .args([
                "-dNOPAUSE",
                "-dBATCH",
                "-dSAFER",
                "-dQUIET",
                "-o", "-",
                "-sDEVICE=ink_cov",
                &temp_copy_str,
            ])
            .output()
        {
            if out.status.success() {
                let stdout = String::from_utf8_lossy(&out.stdout);
                for line in stdout.lines() {
                    let parts: Vec<&str> = line.split_whitespace().collect();
                    if parts.len() >= 4 {
                        if let (Ok(c), Ok(m), Ok(y), Ok(k)) = (
                            parts[0].parse::<f64>(),
                            parts[1].parse::<f64>(),
                            parts[2].parse::<f64>(),
                            parts[3].parse::<f64>(),
                        ) {
                            total_c += c;
                            total_m += m;
                            total_y += y;
                            total_k += k;
                            pages += 1;
                        }
                    }
                }
            }
        }

        // Nettoyage de la copie temporaire
        let _ = std::fs::remove_file(&temp_copy_str);

        // 6. Calcul des statistiques
        let mut is_grayscale = true;
        let mut fill_rate = 0.0;
        let total_pages = if pages > 0 { pages } else { 1 };

        if pages > 0 {
            let avg_c = total_c / (pages as f64);
            let avg_m = total_m / (pages as f64);
            let avg_y = total_y / (pages as f64);
            let diff_cm = (avg_c - avg_m).abs();
            let diff_my = (avg_m - avg_y).abs();
            let diff_cy = (avg_c - avg_y).abs();
            let max_diff = diff_cm.max(diff_my).max(diff_cy);

            let is_color = (avg_c + avg_m + avg_y > 2.0) && (max_diff > 1.0);
            is_grayscale = !is_color || !driver_color;
            fill_rate = (total_c + total_m + total_y + total_k) / (pages as f64);
        }

        let size_bytes = std::fs::metadata(&source_path).map(|m| m.len() as u32).unwrap_or(0);
        let thumbnail_url = format!("/?get_linux_thumb=job_{}_page_1.png", job_id);

        Ok(ReanalyzeResult {
            job_id,
            found: true,
            document: document_name,
            status: 3, // Spooled/Printing
            status_label: "Prêt".to_string(),
            total_pages,
            size_bytes,
            is_grayscale,
            is_duplex: false,
            paper_size: "A4".to_string(),
            fill_rate,
            thumbnail_url,
        })
    }
}

/// Lance l'impression d'un fichier PDF sur une imprimante donnée via SumatraPDF.
///
/// Côté frontend : `await invoke('print_job', { pdfPath: '...', options: { printerName: '...', copies: 1 } })`
#[command]
pub fn print_job(pdf_path: String, options: Value) -> Result<(), String> {
    log::info!(
        "[printer_commands] print_job('{}') : début",
        pdf_path
    );

    if pdf_path.trim().is_empty() {
        return Err("Le chemin du fichier PDF est vide.".to_string());
    }
    if !std::path::Path::new(&pdf_path).exists() {
        return Err(format!("Fichier PDF introuvable : {}", pdf_path));
    }

    let printer_name = options
        .get("printerName")
        .and_then(|v| v.as_str())
        .unwrap_or("")
        .to_string();

    let copies = options
        .get("copies")
        .and_then(|v| v.as_u64())
        .unwrap_or(1);

    log::info!(
        "[printer_commands] print_job : imprimante='{}' copies={}",
        printer_name, copies
    );

    #[cfg(target_os = "windows")]
    {
        // Cherche SumatraPDF dans les emplacements standards
        let sumatra_paths = [
            // Sidecar Tauri (embarqué dans l'app, chemin relatif au binaire)
            "SumatraPDF.exe",
            // Installations standard de SumatraPDF
            r"C:\Program Files\SumatraPDF\SumatraPDF.exe",
            r"C:\Program Files (x86)\SumatraPDF\SumatraPDF.exe",
        ];

        let sumatra_path = sumatra_paths
            .iter()
            .find(|p| std::path::Path::new(p).exists())
            .ok_or_else(|| {
                "SumatraPDF est introuvable. Installez-le ou placez SumatraPDF.exe dans le dossier de l'application.".to_string()
            })?;

        log::info!("[printer_commands] print_job : SumatraPDF trouvé en '{}'", sumatra_path);

        // Argument -print-to pour spécifier l'imprimante, ou -print-to-default
        let printer_arg = if !printer_name.is_empty() {
            format!("-print-to \"{}\" ", printer_name)
        } else {
            "-print-to-default ".to_string()
        };

        let mut cmd = std::process::Command::new(sumatra_path);
        cmd.arg("-silent");  // sans fenêtre
        if !printer_name.is_empty() {
            cmd.args(["-print-to", &printer_name]);
        } else {
            cmd.arg("-print-to-default");
        }
        if copies > 1 {
            cmd.args(["-print-settings", &format!("{}", copies)]);
        }
        cmd.arg(&pdf_path);

        log::debug!("[printer_commands] print_job : commande SumatraPDF = {:?} {}", cmd, printer_arg);

        let status = cmd
            .status()
            .map_err(|e| format!("Impossible de lancer SumatraPDF : {e}"))?;

        if status.success() {
            log::info!("[printer_commands] print_job : impression lancée avec succès");
            Ok(())
        } else {
            let code = status.code().unwrap_or(-1);
            log::error!("[printer_commands] print_job : SumatraPDF a quitté avec le code {code}");
            Err(format!("SumatraPDF a échoué (code de sortie : {})", code))
        }
    }

    #[cfg(not(target_os = "windows"))]
    {
        // Sur Linux/macOS : utiliser lp (CUPS) comme fallback
        log::info!("[printer_commands] print_job (Linux) : tentative via lp (CUPS)");
        let mut cmd = std::process::Command::new("lp");
        if !printer_name.is_empty() {
            cmd.args(["-d", &printer_name]);
        }
        if copies > 1 {
            cmd.args(["-n", &copies.to_string()]);
        }
        cmd.arg(&pdf_path);

        let status = cmd
            .status()
            .map_err(|e| format!("Impossible de lancer lp (CUPS) : {e}"))?;

        if status.success() {
            log::info!("[printer_commands] print_job (Linux) : impression lancée via lp");
            Ok(())
        } else {
            let code = status.code().unwrap_or(-1);
            Err(format!("lp (CUPS) a échoué (code de sortie : {})", code))
        }
    }
}
