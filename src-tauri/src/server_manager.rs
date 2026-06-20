// =============================================================================
// server_manager.rs - Gestion du cycle de vie des serveurs (Caddy, PHP)
//
// Responsabilité UNIQUE : Démarrer, superviser et arrêter les processus
//                         Caddy et PHP via l'API Sidecar de Tauri.
//
// Ce module ne contient AUCUNE logique d'impression ni de droits admin.
// Il communique avec le frontend via des événements Tauri (emit).
// =============================================================================

use std::sync::{Arc, Mutex};
use tauri::{AppHandle, Emitter, Manager};
use tauri_plugin_shell::process::{CommandChild, CommandEvent};
use tauri_plugin_shell::ShellExt;
use serde::Serialize;

// =============================================================================
// Types et structures
// =============================================================================

/// Statut d'un serveur, sérialisable pour envoi au frontend
#[derive(Debug, Clone, Serialize)]
pub struct ServerStatus {
    pub caddy_running: bool,
    pub php_running: bool,
}

/// Payload d'un message de log, sérialisable pour envoi au frontend
#[derive(Debug, Clone, Serialize)]
pub struct LogMessage {
    pub source: String, // "caddy" | "php"
    pub level: String,  // "stdout" | "stderr"
    pub message: String,
}

/// État global des processus enfants, partagé entre les commandes Tauri.
/// Encapsulé dans Arc<Mutex<>> pour être thread-safe et muable.
pub struct AppState {
    pub caddy_child: Arc<Mutex<Option<CommandChild>>>,
    pub php_child: Arc<Mutex<Option<CommandChild>>>,
}

impl AppState {
    pub fn new() -> Self {
        AppState {
            caddy_child: Arc::new(Mutex::new(None)),
            php_child: Arc::new(Mutex::new(None)),
        }
    }
}

// =============================================================================
// Fonctions publiques : API interne du module
// =============================================================================

/// Lance tous les sidecars (Caddy + PHP) de manière asynchrone.
/// Appelée depuis main.rs dans le hook `.setup()`.
pub async fn launch_all_sidecars(app: &AppHandle) -> Result<(), String> {
    // Récupère l'état partagé, géré par Tauri via `app.manage()`
    let state = app.state::<AppState>();

    // --- Lancement de Caddy ---
    let caddy_handle = app.clone();
    let caddy_child_ref = Arc::clone(&state.caddy_child);

    let caddyfile = get_caddyfile_path(app);
    log::info!("[server_manager] Caddyfile : {}", caddyfile);

    let caddy_child = spawn_sidecar(
        &app,
        "caddy",
        &["run", "--config", &caddyfile, "--adapter", "caddyfile"],
    )
    .await
    .map_err(|e| format!("Impossible de lancer Caddy: {e}"))?;

    // Stocke le handle du processus Caddy dans l'état global
    *caddy_child_ref.lock().unwrap() = Some(caddy_child.0);

    // Écoute les logs de Caddy dans une tâche dédiée
    let mut caddy_rx = caddy_child.1;
    tauri::async_runtime::spawn(async move {
        pipe_logs_to_frontend(&caddy_handle, "caddy", &mut caddy_rx).await;
    });

    // --- Lancement de PHP (serveur built-in, identique à Electron) ---
    // La commande équivaut à : php -S 127.0.0.1:8001 -t app/public [options]
    let php_handle = app.clone();
    let php_child_ref = Arc::clone(&state.php_child);

    let php_args = build_php_args(app);
    let php_args_refs: Vec<&str> = php_args.iter().map(|s| s.as_str()).collect();

    log::info!("[server_manager] PHP args : {:?}", php_args);

    let php_child = spawn_sidecar(
        &app,
        "php",
        &php_args_refs,
    )
    .await
    .map_err(|e| format!("Impossible de lancer PHP: {e}"))?;

    // Stocke le handle du processus PHP dans l'état global
    *php_child_ref.lock().unwrap() = Some(php_child.0);

    // Écoute les logs de PHP dans une tâche dédiée
    let mut php_rx = php_child.1;
    tauri::async_runtime::spawn(async move {
        pipe_logs_to_frontend(&php_handle, "php", &mut php_rx).await;
    });

    Ok(())
}

/// Arrête tous les processus sidecar proprement.
/// Appelée depuis le hook `on_window_event` lors de la fermeture.
pub async fn stop_all_sidecars(app: &AppHandle) {
    let state = app.state::<AppState>();
    kill_child(&state.caddy_child, "caddy");
    kill_child(&state.php_child, "php");
}

// =============================================================================
// Commandes Tauri exposées au frontend
// =============================================================================

/// Retourne le statut actuel des serveurs
#[tauri::command]
pub fn get_server_status(state: tauri::State<AppState>) -> ServerStatus {
    let caddy_running = state
        .caddy_child
        .lock()
        .unwrap()
        .is_some();

    let php_running = state
        .php_child
        .lock()
        .unwrap()
        .is_some();

    ServerStatus { caddy_running, php_running }
}

/// Redémarre uniquement PHP-FPM (sans toucher à Caddy)
#[tauri::command]
pub async fn restart_php(app: AppHandle) -> Result<String, String> {
    let state = app.state::<AppState>();

    // Arrête PHP proprement
    kill_child(&state.php_child, "php");
    log::info!("PHP arrêté, redémarrage en cours...");

    // Attend un court délai pour s'assurer que le port est libéré
    tokio::time::sleep(std::time::Duration::from_millis(500)).await;

    // Relance PHP
    let php_args = build_php_args(&app);
    let php_args_refs: Vec<&str> = php_args.iter().map(|s| s.as_str()).collect();

    let php_child = spawn_sidecar(
        &app,
        "php",
        &php_args_refs,
    )
    .await
    .map_err(|e| format!("Redémarrage PHP échoué: {e}"))?;

    let php_handle = app.clone();
    let php_child_ref = Arc::clone(&state.php_child);
    *php_child_ref.lock().unwrap() = Some(php_child.0);

    let mut php_rx = php_child.1;
    tauri::async_runtime::spawn(async move {
        pipe_logs_to_frontend(&php_handle, "php", &mut php_rx).await;
    });

    log::info!("PHP redémarré avec succès.");
    Ok("PHP redémarré".to_string())
}

/// Arrête tous les serveurs (commande frontend)
#[tauri::command]
pub async fn stop_servers(app: AppHandle) -> Result<(), String> {
    stop_all_sidecars(&app).await;
    Ok(())
}

/// Démarre tous les serveurs (commande frontend, utile si l'arrêt a été forcé)
#[tauri::command]
pub async fn start_servers(app: AppHandle) -> Result<(), String> {
    launch_all_sidecars(&app).await
}

// =============================================================================
// Fonctions privées (internes au module)
// =============================================================================

/// Lance un sidecar et retourne son handle + son receiver de logs.
/// Retourne `(CommandChild, Receiver<CommandEvent>)`.
async fn spawn_sidecar(
    app: &AppHandle,
    sidecar_name: &str,
    args: &[&str],
) -> Result<(CommandChild, tauri::async_runtime::Receiver<CommandEvent>), String> {
    let mut command = app
        .shell()
        .sidecar(sidecar_name)
        .map_err(|e| format!("Sidecar '{sidecar_name}' introuvable dans la config Tauri: {e}"))?;

    for arg in args {
        command = command.arg(*arg);
    }

    if sidecar_name == "php" {
        // Obtenir le chemin de la base de données SQLite (partagée avec Electron dans Roaming/Duplicator)
        #[cfg(target_os = "windows")]
        let db_path = std::env::var("APPDATA")
            .map(|p| std::path::PathBuf::from(p).join("Duplicator").join("duplinew.sqlite").to_string_lossy().to_string())
            .unwrap_or_else(|_| {
                app.path()
                    .app_data_dir()
                    .map(|p| p.join("duplinew.sqlite").to_string_lossy().to_string())
                    .unwrap_or_default()
            });

        #[cfg(not(target_os = "windows"))]
        let db_path = app.path()
            .app_data_dir()
            .map(|p| p.join("duplinew.sqlite").to_string_lossy().to_string())
            .unwrap_or_default();

        if !db_path.is_empty() {
            command = command.env("DUPLICATOR_DB_PATH", &db_path);
            log::info!("[server_manager] PHP sidecar env DUPLICATOR_DB_PATH={}", db_path);
        }
        command = command.env("ELECTRON_RUNNING", "1");

        // Note: DLLs (php8.dll etc.) are bundled via tauri.conf.json resources at {resource_dir}/binaries/,
        // same directory where the sidecar binary resolves, so they are found automatically.
    }

    let (rx, child) = command
        .spawn()
        .map_err(|e| format!("Échec du spawn du sidecar '{sidecar_name}': {e}"))?;

    log::info!("Sidecar '{sidecar_name}' lancé avec succès.");
    Ok((child, rx))
}

/// Lit en continu les événements stdout/stderr d'un processus et les émet
/// vers le frontend sous forme d'événements "log-message".
async fn pipe_logs_to_frontend(
    app: &AppHandle,
    source: &str,
    rx: &mut tauri::async_runtime::Receiver<CommandEvent>,
) {
    use tauri_plugin_shell::process::CommandEvent;

    while let Some(event) = rx.recv().await {
        match event {
            CommandEvent::Stdout(line) => {
                let message = String::from_utf8_lossy(&line).to_string();
                log::debug!("[{source}][stdout] {message}");
                let _ = app.emit("log-message", LogMessage {
                    source: source.to_string(),
                    level: "stdout".to_string(),
                    message,
                });
            }
            CommandEvent::Stderr(line) => {
                let message = String::from_utf8_lossy(&line).to_string();
                log::warn!("[{source}][stderr] {message}");
                let _ = app.emit("log-message", LogMessage {
                    source: source.to_string(),
                    level: "stderr".to_string(),
                    message,
                });
            }
            CommandEvent::Terminated(status) => {
                log::warn!("Sidecar '{source}' terminé avec le code: {:?}", status.code);
                let _ = app.emit("server-terminated", source);
                break; // Arrête l'écoute si le processus s'est terminé
            }
            CommandEvent::Error(e) => {
                log::error!("Erreur IO sur le sidecar '{source}': {e}");
            }
            _ => {} // Ignorer les autres événements (ex: Killed)
        }
    }
}

/// Tue un processus enfant de manière sécurisée (avec gestion du Mutex).
fn kill_child(child_ref: &Arc<Mutex<Option<CommandChild>>>, name: &str) {
    let mut guard = child_ref.lock().unwrap();
    if let Some(child) = guard.take() {
        match child.kill() {
            Ok(_) => log::info!("Processus '{name}' arrêté proprement."),
            Err(e) => log::warn!("Impossible d'arrêter '{name}': {e}"),
        }
    }
}

/// Retourne le chemin absolu vers le répertoire racine de l'application PHP (app/public).
/// Cherche dans l'ordre : bundle Tauri resources, puis chemin de développement.
fn get_php_docroot(app: &AppHandle) -> std::path::PathBuf {
    // En mode bundle : les ressources sont dans resource_dir()
    if let Ok(res) = app.path().resource_dir() {
        let candidate = res.join("app").join("public");
        if candidate.exists() {
            return candidate;
        }
    }
    // En mode développement : relatif au Cargo.toml (src-tauri/../app/public)
    // On utilise l'exécutable courant comme point de référence
    if let Ok(exe) = std::env::current_exe() {
        // dev: .../src-tauri/target/debug/duplicator -> remonter 3 niveaux
        let dev_path = exe
            .ancestors()
            .nth(4)
            .map(|p| p.join("app").join("public"));
        if let Some(p) = dev_path {
            if p.exists() { return p; }
        }
    }
    // Fallback absolu
    std::path::PathBuf::from("app/public")
}

/// Retourne le chemin absolu vers le répertoire racine de l'application PHP (app/).
fn get_php_app_base(app: &AppHandle) -> std::path::PathBuf {
    // En mode bundle
    if let Ok(res) = app.path().resource_dir() {
        let candidate = res.join("app");
        if candidate.exists() { return candidate; }
    }
    // En mode développement
    if let Ok(exe) = std::env::current_exe() {
        let dev_path = exe
            .ancestors()
            .nth(4)
            .map(|p| p.join("app"));
        if let Some(p) = dev_path {
            if p.exists() { return p; }
        }
    }
    std::path::PathBuf::from("app")
}

/// Retourne le chemin absolu du Caddyfile.
fn get_caddyfile_path(app: &AppHandle) -> String {
    // En mode bundle : ressource bundlée
    if let Ok(res) = app.path().resource_dir() {
        let candidate = res.join("Caddyfile");
        if candidate.exists() {
            return candidate.to_string_lossy().to_string();
        }
    }
    // En mode développement : à la racine du projet
    if let Ok(exe) = std::env::current_exe() {
        let dev_path = exe
            .ancestors()
            .nth(4)
            .map(|p| p.join("Caddyfile"));
        if let Some(p) = dev_path {
            if p.exists() { return p.to_string_lossy().to_string(); }
        }
    }
    "Caddyfile".to_string()
}

/// Construit les arguments pour lancer le serveur PHP built-in.
/// Identique à ce que fait Electron : `php -S 127.0.0.1:8001 -t app/public`
fn build_php_args(app: &AppHandle) -> Vec<String> {
    let docroot = get_php_docroot(app);
    let app_base = get_php_app_base(app);
    let session_dir = std::env::temp_dir().join("duplicator_sessions_tauri");

    // Créer le répertoire de sessions s'il n'existe pas
    let _ = std::fs::create_dir_all(&session_dir);

    let docroot_str = docroot.to_string_lossy();
    let app_base_str = app_base.to_string_lossy();
    let vendor_str = app_base.join("vendor").to_string_lossy().to_string();
    let session_str = session_dir.to_string_lossy();
    let php_ini_path = app_base.join("php.ini");
    let php_ini_str = php_ini_path.to_string_lossy();

    #[cfg(target_os = "windows")]
    let include_sep = ";";
    #[cfg(not(target_os = "windows"))]
    let include_sep = ":";

    let mut args = Vec::new();

    // 1. Charger php.ini si disponible
    if php_ini_path.exists() {
        args.push("-c".to_string());
        args.push(php_ini_str.to_string());
    }

    // 2. Arguments standards
    args.push("-S".to_string());
    args.push("127.0.0.1:8001".to_string());
    args.push("-t".to_string());
    args.push(docroot_str.to_string());

    // 3. Arguments d'extensions spécifiques à Windows
    #[cfg(target_os = "windows")]
    {
        let ext_dir = get_php_ext_dir(app);
        let ext_dir_str = ext_dir.to_string_lossy().replace('\\', "/");
        args.push("-d".to_string());
        args.push(format!("extension_dir={}", ext_dir_str));
        args.push("-d".to_string());
        args.push("extension=php_sqlite3.dll".to_string());
        args.push("-d".to_string());
        args.push("extension=php_pdo_sqlite.dll".to_string());
    }

    // 4. Autres arguments INI
    args.push("-d".to_string());
    args.push(format!("include_path={}{}{}{}{}", app_base_str, include_sep, vendor_str, include_sep, "."));
    args.push("-d".to_string());
    args.push("display_errors=1".to_string());
    args.push("-d".to_string());
    args.push("log_errors=1".to_string());
    args.push("-d".to_string());
    args.push("upload_max_filesize=50M".to_string());
    args.push("-d".to_string());
    args.push("post_max_size=50M".to_string());
    args.push("-d".to_string());
    args.push("max_input_vars=10000".to_string());
    args.push("-d".to_string());
    args.push("max_input_nesting_level=256".to_string());
    args.push("-d".to_string());
    args.push(format!("session.save_path={}", session_str));

    args
}

/// Retourne le chemin absolu vers le dossier d'extensions PHP (ext).
/// Gère la différence entre le mode développement (src-tauri/binaries/ext) et bundle de production.
fn get_php_ext_dir(app: &AppHandle) -> std::path::PathBuf {
    // 1. En mode bundle : les extensions sont copiées dans le répertoire de ressources de l'application
    if let Ok(res) = app.path().resource_dir() {
        let candidate = res.join("binaries").join("ext");
        if candidate.exists() {
            return candidate;
        }
        let candidate2 = res.join("ext");
        if candidate2.exists() {
            return candidate2;
        }
        let candidate3 = res.join("src-tauri").join("binaries").join("ext");
        if candidate3.exists() {
            return candidate3;
        }
    }
    // 2. En mode développement : relatif au projet (src-tauri/binaries/ext)
    if let Ok(exe) = std::env::current_exe() {
        let dev_path = exe
            .ancestors()
            .nth(4)
            .map(|p| p.join("src-tauri").join("binaries").join("ext"));
        if let Some(p) = dev_path {
            if p.exists() {
                return p;
            }
        }
    }
    // 3. Fallback : dossier ext à côté de l'exécutable
    if let Ok(exe) = std::env::current_exe() {
        if let Some(parent) = exe.parent() {
            return parent.join("ext");
        }
    }
    std::path::PathBuf::from("ext")
}
