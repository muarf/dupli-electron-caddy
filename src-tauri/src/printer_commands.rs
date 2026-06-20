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

    // Stub pour Linux/macOS — retourne une liste vide sans erreur
    #[cfg(not(target_os = "windows"))]
    {
        log::warn!("[printer_commands] get_printers() : non supporté sur cette plateforme (non-Windows)");
        Ok(Vec::new())
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
        log::warn!("[printer_commands] delete_print_job() : non supporté sur cette plateforme");
        Err("La suppression de jobs n'est supportée que sous Windows.".to_string())
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

            // Cache des job_id déjà vus pour n'émettre que les nouveaux jobs.
            // Déclaré ici (hors du loop) pour persister entre chaque itération.
            // Conditionné #[cfg(windows)] pour éviter le warning unused_variables sur Linux.
            #[cfg(target_os = "windows")]
            let mut seen_job_ids: std::collections::HashSet<u32> = std::collections::HashSet::new();

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
                                            if seen_job_ids.insert(job.job_id) {
                                                // Nouveau job : émettre l'événement vers le frontend
                                                log::info!(
                                                    "[PrintMonitorState] Nouveau job détecté : id={} doc='{}' imprimante='{}'",
                                                    job.job_id, job.document, printer.name
                                                );
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

                // Sur non-Windows : le moniteur ne fait rien mais reste actif pour ne pas crasher
                #[cfg(not(target_os = "windows"))]
                {
                    log::trace!("[PrintMonitorState] Moniteur actif (non-Windows : aucune action)");
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
        })
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::warn!("[printer_commands] reanalyze_print_job() : non supporté sur cette plateforme");
        Err("La réanalyse de job n'est supportée que sous Windows.".to_string())
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
