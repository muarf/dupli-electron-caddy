// =============================================================================
// main.rs - Point d'entrée principal de l'application Tauri
//
// Responsabilité : Initialisation de Tauri, enregistrement des plugins,
//                  des commandes, injection du bridge JS, et démarrage
//                  des Sidecars.
//
// Ce fichier ne contient AUCUNE logique métier directe.
// =============================================================================

#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

// Modules internes
mod admin_checker;
mod app_commands;
mod printer_commands;
mod server_manager;
mod windows_native;

// Imports Tauri
use tauri::{Emitter, Manager, WebviewWindowBuilder};

/// Port Caddy par défaut (doit correspondre au Caddyfile)
const CADDY_PORT: u16 = 8000;

/// URL principale de l'application (chargée après le démarrage des serveurs)
const APP_URL: &str = "http://127.0.0.1:8000/?accueil";

/// URL de secours : PHP sans Caddy (si Caddy ne répond pas à temps)
const FALLBACK_URL: &str = "http://127.0.0.1:8001/?accueil";

/// Timeout en secondes pour attendre que Caddy soit prêt
const CADDY_READY_TIMEOUT_SECS: u64 = 30;

// Le bridge JS est intégré directement dans le binaire Rust via include_str!
const TAURI_BRIDGE_JS: &str = include_str!("../tauri-bridge.js");

// =============================================================================
// Point d'entrée
// =============================================================================
fn main() {
    tauri::Builder::default()
        // --- Plugins officiels ---
        .plugin(tauri_plugin_log::Builder::default().build())
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .plugin(tauri_plugin_process::init())
        // --- Commandes exposées au frontend ---
        .invoke_handler(tauri::generate_handler![
            // Gestion de l'application et des fichiers
            app_commands::open_file,
            app_commands::open_external_file,
            app_commands::cleanup_tmp_files,
            app_commands::show_open_dialog,
            app_commands::get_database_path,
            app_commands::get_app_version,
            app_commands::restart_app,
            // Mises à jour
            app_commands::check_for_updates,
            app_commands::download_update,
            app_commands::install_update,
            // Gestion des serveurs (Caddy, PHP)
            server_manager::start_servers,
            server_manager::stop_servers,
            server_manager::get_server_status,
            server_manager::restart_php,
            // Imprimantes
            printer_commands::get_printers,
            printer_commands::get_print_jobs,
            printer_commands::delete_print_job,
            printer_commands::print_file,
            printer_commands::get_printer_capabilities,
            // Moniteur d'impression
            printer_commands::toggle_printer_monitor,
            printer_commands::get_printer_monitor_status,
            // Commandes imprimante avancées
            printer_commands::delete_printer,
            printer_commands::reanalyze_print_job,
            printer_commands::print_job,
            // Administration
            admin_checker::check_admin_status,
            admin_checker::restart_as_admin,
        ])
        // --- Hook de démarrage ---
        .setup(|app| {
            // 1. Enregistrement de l'état global des processus enfants
            app.manage(server_manager::AppState::new());

            // 2. Enregistrement de l'état du moniteur d'impression
            app.manage(printer_commands::PrintMonitorState::new());

            // 3. Création de la fenêtre principale avec une page d'attente.
            //    L'URL Caddy sera chargée une fois les serveurs prêts.
            //    On utilise une URL de chargement locale (data URI) pour éviter
            //    un écran blanc pendant le démarrage des serveurs.
            let loading_page = tauri::WebviewUrl::External(
                "data:text/html,<!DOCTYPE html><html><head><meta charset='utf-8'>\
                <style>body{margin:0;display:flex;align-items:center;justify-content:center;\
                height:100vh;background:#1a1a2e;font-family:Arial,sans-serif;color:#eee;}\
                .loader{text-align:center;} h2{font-size:1.4em;margin-bottom:16px;}\
                .spinner{width:40px;height:40px;border:4px solid #333;border-top-color:#6c63ff;\
                border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px;}\
                @keyframes spin{to{transform:rotate(360deg);}}\
                </style></head><body><div class='loader'>\
                <div class='spinner'></div><h2>Démarrage de Duplicator…</h2>\
                <p style='color:#aaa;font-size:.9em'>Initialisation des serveurs</p>\
                </div></body></html>"
                    .parse().expect("URL de chargement invalide")
            );

            let _window = WebviewWindowBuilder::new(app, "main", loading_page)
                .title("Duplicator")
                .inner_size(1280.0, 800.0)
                .min_inner_size(900.0, 600.0)
                .resizable(true)
                .center()
                // Bridge JS injecté AVANT tout chargement de page
                .initialization_script(TAURI_BRIDGE_JS)
                .build()
                .expect("Impossible de créer la fenêtre principale");

            // 4. Démarrage des serveurs + navigation vers l'app PHP
            let app_handle = app.handle().clone();
            tauri::async_runtime::spawn(async move {
                log::info!("Démarrage des serveurs (Caddy, PHP)...");

                match server_manager::launch_all_sidecars(&app_handle).await {
                    Err(e) => {
                        log::error!("Erreur critique au démarrage des serveurs : {e}");
                        let _ = app_handle.emit("server-error", e.to_string());
                        return;
                    }
                    Ok(()) => log::info!("Sidecars lancés, attente que Caddy réponde..."),
                }

                // Attendre que Caddy réponde (polling HTTP)
                let caddy_ready = wait_for_caddy(CADDY_PORT, CADDY_READY_TIMEOUT_SECS).await;

                let target_url = if caddy_ready {
                    log::info!("Caddy prêt ! Navigation vers {APP_URL}");
                    APP_URL
                } else {
                    log::warn!("Caddy timeout — fallback vers PHP direct : {FALLBACK_URL}");
                    FALLBACK_URL
                };

                // Navigate la fenêtre vers l'URL PHP
                if let Some(window) = app_handle.get_webview_window("main") {
                    let parsed_url = target_url.parse().expect("URL invalide");
                    if let Err(e) = window.navigate(parsed_url) {
                        log::error!("Impossible de naviguer vers {target_url} : {e}");
                    }
                    let _ = app_handle.emit("servers-ready", ());
                } else {
                    log::error!("Fenêtre 'main' introuvable après le démarrage des serveurs");
                }
            });

            Ok(())
        })
        // --- Arrêt propre des sidecars à la fermeture ---
        .on_window_event(|window, event| {
            if let tauri::WindowEvent::Destroyed = event {
                log::info!("Fermeture de la fenêtre — arrêt des serveurs...");
                let app_handle = window.app_handle().clone();
                tauri::async_runtime::spawn(async move {
                    server_manager::stop_all_sidecars(&app_handle).await;
                });
            }
        })
        .run(tauri::generate_context!())
        .expect("Erreur fatale lors du démarrage de l'application Tauri");
}

// =============================================================================
// Fonction utilitaire : attendre que Caddy réponde sur son port HTTP
// =============================================================================

/// Tente de se connecter en TCP sur le port de Caddy toutes les 500ms.
/// Retourne `true` si le serveur répond dans le délai, `false` sinon.
/// On utilise une connexion TCP (pas HTTP) pour être léger et rapide.
async fn wait_for_caddy(port: u16, timeout_secs: u64) -> bool {
    use tokio::net::TcpStream;
    use tokio::time::{sleep, timeout, Duration};

    let deadline = Duration::from_secs(timeout_secs);
    let poll_interval = Duration::from_millis(500);
    let addr = format!("127.0.0.1:{port}");

    log::debug!("[wait_for_caddy] Polling {addr} (timeout: {timeout_secs}s)");

    let result = timeout(deadline, async {
        loop {
            match TcpStream::connect(&addr).await {
                Ok(_) => {
                    log::info!("[wait_for_caddy] Caddy répond sur {addr}");
                    return true;
                }
                Err(_) => {
                    sleep(poll_interval).await;
                }
            }
        }
    })
    .await;

    result.unwrap_or(false)
}
