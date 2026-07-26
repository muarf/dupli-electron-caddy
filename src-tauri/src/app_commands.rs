// =============================================================================
// app_commands.rs - Commandes générales de l'application
//
// Responsabilité : Toutes les commandes qui ne relèvent ni de l'impression,
//                  ni de l'administration, ni de la gestion des serveurs.
//                  (Fichiers, dialogue, version, mise à jour)
// =============================================================================

use tauri::command;
use serde::Serialize;



// =============================================================================
// Commandes Fichiers & Dialogue
// =============================================================================

/// Ouvre un fichier avec l'application système par défaut
#[command]
pub async fn open_file(file_path: String) -> Result<(), String> {
    log::info!("[app_commands] open_file('{}')", file_path);

    if !std::path::Path::new(&file_path).exists() {
        return Err(format!("Fichier introuvable : {}", file_path));
    }

    #[cfg(target_os = "windows")]
    {
        std::process::Command::new("cmd")
            .args(["/c", "start", "", &file_path])
            .spawn()
            .map_err(|e| format!("Impossible d'ouvrir le fichier : {e}"))?;
    }
    #[cfg(target_os = "linux")]
    {
        std::process::Command::new("xdg-open")
            .arg(&file_path)
            .spawn()
            .map_err(|e| format!("Impossible d'ouvrir le fichier : {e}"))?;
    }
    #[cfg(target_os = "macos")]
    {
        std::process::Command::new("open")
            .arg(&file_path)
            .spawn()
            .map_err(|e| format!("Impossible d'ouvrir le fichier : {e}"))?;
    }
    Ok(())
}

/// Ouvre un fichier externe à partir d'une URL encodée
#[command]
pub async fn open_external_file(file_url: String) -> Result<(), String> {
    log::info!("[app_commands] open_external_file('{}')", file_url);

    // Décode l'URL pour extraire le chemin local
    let path = file_url
        .replace("file:///", "/")
        .replace("file://", "/")
        .replace("%20", " ");

    open_file(path).await
}

/// Nettoie les fichiers temporaires de l'application âgés de plus d'1 jour
pub fn cleanup_tmp_files() -> u32 {
    log::info!("[app_commands] cleanup_tmp_files()");

    let tmp_dir = std::env::temp_dir();
    let mut count = 0u32;
    let one_day_ago = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap_or_default()
        .as_secs()
        .saturating_sub(86400); // 24h en secondes

    if let Ok(entries) = std::fs::read_dir(&tmp_dir) {
        for entry in entries.flatten() {
            let name = entry.file_name().to_string_lossy().to_lowercase();
            if name.starts_with("duplicator_") || name.starts_with("dupli_tmp_") {
                // Vérifier l'âge du fichier via metadata
                let is_old = entry.metadata()
                    .and_then(|m| m.modified())
                    .map(|t| t.duration_since(std::time::UNIX_EPOCH).unwrap_or_default().as_secs() < one_day_ago)
                    .unwrap_or(false);

                if is_old && std::fs::remove_file(entry.path()).is_ok() {
                    count += 1;
                }
            }
        }
    }

    log::info!("[app_commands] cleanup_tmp_files() : {} fichier(s) supprimé(s)", count);
    count
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct DialogResult {
    pub cancelled: bool,
    pub file_path: Option<String>,
    pub file_paths: Vec<String>,
}

/// Affiche une boîte de dialogue de sélection de fichier native
#[command]
pub async fn show_open_dialog(options: serde_json::Value) -> Result<DialogResult, String> {
    log::info!("[app_commands] show_open_dialog()");

    let _multiple = options.get("multiple")
        .and_then(|v| v.as_bool())
        .unwrap_or(false);

    // Note : la sélection graphique du fichier est déléguée au plugin tauri-plugin-dialog
    // depuis le frontend. Cette commande est un fallback de bas niveau.
    log::warn!("[app_commands] show_open_dialog() : utiliser @tauri-apps/api/dialog depuis le frontend pour une meilleure UX");

    Ok(DialogResult {
        cancelled: true,
        file_path: None,
        file_paths: Vec::new(),
    })
}

// =============================================================================
// Informations de l'application
// =============================================================================

/// Redémarre l'application
#[command]
pub fn restart_app() {
    log::info!("[app_commands] restart_app() : redémarrage de l'application");
    tauri::process::restart(&tauri::Env::default());
}

// =============================================================================
// Mises à jour (Mise en œuvre réelle avec tauri-plugin-updater)
// =============================================================================

use tauri_plugin_updater::UpdaterExt;
use tauri::Emitter;

pub struct UpdateState {
    pub pending_update: std::sync::Mutex<Option<tauri_plugin_updater::Update>>,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct UpdateCheckResult {
    pub update_available: bool,
    pub version: Option<String>,
    pub notes: Option<String>,
}

/// Vérifie si une mise à jour est disponible
#[command]
pub async fn check_for_updates(
    app: tauri::AppHandle,
    state: tauri::State<'_, UpdateState>,
) -> Result<UpdateCheckResult, String> {
    log::info!("[app_commands] check_for_updates()");
    let updater = app.updater_builder()
        .pubkey("dW50cnVzdGVkIGNvbW1lbnQ6IG1pbmlzaWduIHB1YmxpYyBrZXk6IDUwRDQxOEYwNDFDQTMzNEEKUldSS004cEI4QmpVVUJJV3lWeDlBWVF0VWp3b05CTFBNUXFETnRFa0FPcTlwZ3dYTjdQakxQZmUK")
        .endpoints(vec![url::Url::parse("https://raw.githubusercontent.com/muarf/dupli-electron-caddy/alpha/updater/latest.json").unwrap()])
        .map_err(|e| e.to_string())?
        .build()
        .map_err(|e| e.to_string())?;

    match updater.check().await {
        Ok(Some(update)) => {
            let version = update.version.clone();
            let body = update.body.clone();
            log::info!("[app_commands] Update available: {}", version);

            // Stocker la mise à jour disponible dans l'état global
            *state.pending_update.lock().unwrap_or_else(|e| e.into_inner()) = Some(update);

            // Émettre l'événement vers le frontend
            let _ = app.emit("update-available", serde_json::json!({
                "version": version,
                "notes": body
            }));

            Ok(UpdateCheckResult {
                update_available: true,
                version: Some(version),
                notes: body,
            })
        }
        Ok(None) => {
            log::info!("[app_commands] No update available");
            *state.pending_update.lock().unwrap_or_else(|e| e.into_inner()) = None;
            let _ = app.emit("update-not-available", ());
            Ok(UpdateCheckResult {
                update_available: false,
                version: None,
                notes: None,
            })
        }
        Err(e) => {
            let err_msg = format!("Failed to check for updates: {e}");
            log::error!("[app_commands] {}", err_msg);
            let _ = app.emit("update-error", serde_json::json!({ "message": err_msg }));
            Err(err_msg)
        }
    }
}

/// Lance le téléchargement de la mise à jour
#[command]
pub async fn download_update(
    app: tauri::AppHandle,
    state: tauri::State<'_, UpdateState>,
) -> Result<(), String> {
    log::info!("[app_commands] download_update()");
    let mut pending_guard = state.pending_update.lock().unwrap_or_else(|e| e.into_inner());
    let update = pending_guard.take().ok_or_else(|| "Aucune mise à jour disponible en attente de téléchargement.".to_string())?;

    let app_clone = app.clone();
    let version = update.version.clone();

    // Variable partagée pour suivre le total transféré
    let transferred = std::sync::Arc::new(std::sync::Mutex::new(0));

    tauri::async_runtime::spawn(async move {
        let transferred_clone = transferred.clone();
        let app_emit_progress = app_clone.clone();

        log::info!("[app_commands] Arrêt des serveurs sidecar avant installation de la mise à jour...");
        crate::server_manager::stop_all_sidecars(&app_clone);

        let res = update.download_and_install(
            move |chunk_length, content_length| {
                let mut t = transferred_clone.lock().unwrap_or_else(|e| e.into_inner());
                *t += chunk_length;
                let total = content_length.unwrap_or(*t as u64);
                let percent = if total > 0 {
                    (*t as f64 / total as f64) * 100.0
                } else {
                    0.0
                };
                let _ = app_emit_progress.emit("download-progress", serde_json::json!({
                    "percent": percent,
                    "transferred": *t,
                    "total": total
                }));
            },
            move || {
                log::info!("[app_commands] Download finished.");
            }
        ).await;

        match res {
            Ok(_) => {
                log::info!("[app_commands] Update downloaded and installed successfully.");
                let _ = app_clone.emit("update-downloaded", serde_json::json!({ "version": version }));
            }
            Err(e) => {
                let err_msg = format!("Failed to download and install update: {e}");
                log::error!("[app_commands] {}", err_msg);
                let _ = app_clone.emit("update-error", serde_json::json!({ "message": err_msg }));
            }
        }
    });

    Ok(())
}

/// Installe la mise à jour téléchargée (redémarre l'app)
#[command]
pub fn install_update() {
    log::info!("[app_commands] install_update() : redémarrage pour appliquer la mise à jour");
    tauri::process::restart(&tauri::Env::default());
}
