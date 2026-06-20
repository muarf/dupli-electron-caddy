// =============================================================================
// app_commands.rs - Commandes générales de l'application
//
// Responsabilité : Toutes les commandes qui ne relèvent ni de l'impression,
//                  ni de l'administration, ni de la gestion des serveurs.
//                  (Fichiers, dialogue, version, mise à jour)
// =============================================================================

use tauri::command;
use tauri::Manager;
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

/// Nettoie les fichiers temporaires de l'application
#[command]
pub async fn cleanup_tmp_files() -> Result<u32, String> {
    log::info!("[app_commands] cleanup_tmp_files()");

    let tmp_dir = std::env::temp_dir();
    let mut count = 0u32;

    if let Ok(entries) = std::fs::read_dir(&tmp_dir) {
        for entry in entries.flatten() {
            let name = entry.file_name().to_string_lossy().to_lowercase();
            // Nettoie uniquement les fichiers temporaires créés par l'application
            if name.starts_with("duplicator_") || name.starts_with("dupli_tmp_") {
                if std::fs::remove_file(entry.path()).is_ok() {
                    count += 1;
                }
            }
        }
    }

    log::info!("[app_commands] cleanup_tmp_files() : {} fichier(s) supprimé(s)", count);
    Ok(count)
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

/// Retourne le chemin de la base de données SQLite principale (partagée avec Electron)
#[command]
pub fn get_database_path(app: tauri::AppHandle) -> Result<String, String> {
    log::info!("[app_commands] get_database_path()");

    #[cfg(target_os = "windows")]
    let db_path = std::env::var("APPDATA")
        .map(|p| std::path::PathBuf::from(p).join("Duplicator").join("duplinew.sqlite").to_string_lossy().to_string())
        .map_err(|e| format!("Impossible de localiser le répertoire APPDATA : {e}"));

    #[cfg(not(target_os = "windows"))]
    let db_path = app.path()
        .app_data_dir()
        .map(|p| p.join("duplinew.sqlite").to_string_lossy().to_string())
        .map_err(|e| format!("Impossible de localiser le répertoire de données : {e}"));

    let db_path_ok = db_path?;

    log::info!("[app_commands] get_database_path() : {}", db_path_ok);
    Ok(db_path_ok)
}

/// Retourne la version de l'application (depuis Cargo.toml)
#[command]
pub fn get_app_version() -> String {
    env!("CARGO_PKG_VERSION").to_string()
}

/// Redémarre l'application
#[command]
pub fn restart_app() {
    log::info!("[app_commands] restart_app() : redémarrage de l'application");
    tauri::process::restart(&tauri::Env::default());
}

// =============================================================================
// Mises à jour (Stubs — À implémenter avec tauri-plugin-updater)
// =============================================================================

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct UpdateCheckResult {
    pub update_available: bool,
    pub version: Option<String>,
    pub notes: Option<String>,
}

/// Vérifie si une mise à jour est disponible
#[command]
pub async fn check_for_updates() -> Result<UpdateCheckResult, String> {
    log::info!("[app_commands] check_for_updates()");
    // TODO : Intégrer tauri-plugin-updater quand l'endpoint de mise à jour sera configuré
    Ok(UpdateCheckResult {
        update_available: false,
        version: None,
        notes: None,
    })
}

/// Lance le téléchargement de la mise à jour
#[command]
pub async fn download_update() -> Result<(), String> {
    log::warn!("[app_commands] download_update() : non encore implémenté (tauri-plugin-updater requis)");
    Err("La mise à jour automatique n'est pas encore configurée dans cette version Tauri.".to_string())
}

/// Installe la mise à jour téléchargée
#[command]
pub async fn install_update() -> Result<(), String> {
    log::warn!("[app_commands] install_update() : non encore implémenté (tauri-plugin-updater requis)");
    Err("L'installation de la mise à jour n'est pas encore configurée.".to_string())
}
