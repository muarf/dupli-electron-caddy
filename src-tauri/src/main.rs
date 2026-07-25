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
mod menu;
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
    let app = tauri::Builder::default()
        // --- Plugins officiels ---
        .plugin(tauri_plugin_log::Builder::default().build())
        .plugin(tauri_plugin_shell::init())
        .plugin(tauri_plugin_dialog::init())
        .plugin(tauri_plugin_fs::init())
        .plugin(tauri_plugin_process::init())
        .plugin(tauri_plugin_updater::Builder::new().build())
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
            // Configuration du menu natif
            let app_menu = menu::build_menu(app.handle())?;
            app.set_menu(app_menu)?;
            app.on_menu_event(menu::handle_menu_event);

            // 1. Enregistrement de l'état global des processus enfants
            app.manage(server_manager::AppState::new());

            // 1b. Enregistrement de l'état global des mises à jour
            app.manage(app_commands::UpdateState {
                pending_update: std::sync::Mutex::new(None),
            });

            // 2. Enregistrement de l'état du moniteur d'impression
            let monitor_state = printer_commands::PrintMonitorState::new();
            monitor_state.start(app.handle().clone());
            app.manage(monitor_state);

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

                // Attendre que Caddy et PHP répondent tous les deux (polling HTTP/TCP)
                let caddy_ready = wait_for_port(CADDY_PORT, CADDY_READY_TIMEOUT_SECS).await;
                let php_ready = wait_for_port(8001, CADDY_READY_TIMEOUT_SECS).await;

                let parsed_url = if caddy_ready && php_ready {
                    log::info!("Serveurs prêts (Caddy & PHP) ! Navigation vers {APP_URL}");
                    APP_URL.parse().expect("URL invalide")
                } else if !php_ready {
                    log::warn!("PHP n'est pas prêt. Tentative de chargement de la page d'aide.");
                    let guide_path = app_handle.path().resource_dir().ok().and_then(|res| {
                        let candidate_up = res.join("_up_").join("php-install-guide.html");
                        if candidate_up.exists() {
                            return Some(candidate_up);
                        }
                        let candidate = res.join("php-install-guide.html");
                        if candidate.exists() {
                            return Some(candidate);
                        }
                        None
                    });

                    if let Some(path) = guide_path {
                        tauri::Url::from_file_path(path).unwrap_or_else(|_| {
                            FALLBACK_URL.parse().expect("URL de secours invalide")
                        })
                    } else {
                        log::error!("Fichier php-install-guide.html introuvable dans les ressources.");
                        FALLBACK_URL.parse().expect("URL de secours invalide")
                    }
                } else {
                    log::warn!("Caddy n'est pas prêt — fallback vers PHP direct : {FALLBACK_URL}");
                    FALLBACK_URL.parse().expect("URL de secours invalide")
                };

                // Navigate la fenêtre vers l'URL
                if let Some(window) = app_handle.get_webview_window("main") {
                    if let Err(e) = window.navigate(parsed_url.clone()) {
                        log::error!("Impossible de naviguer vers {parsed_url} : {e}");
                    }
                    let _ = app_handle.emit("servers-ready", ());
                } else {
                    log::error!("Fenêtre 'main' introuvable après le démarrage des serveurs");
                }

                // Démarrer la purge automatique planifiée (startup + toutes les heures)
                if php_ready {
                    tauri::async_runtime::spawn(async move {
                        // 1. Attendre 10 secondes après le lancement (comme Electron)
                        tokio::time::sleep(tokio::time::Duration::from_secs(10)).await;
                        loop {
                            log::info!("[SECURE PURGE] Lancement de la purge automatique...");
                            let res = tauri::async_runtime::spawn_blocking(|| {
                                ureq::get("http://127.0.0.1:8001/?secure_purge").call()
                            }).await;

                            match res {
                                Ok(Ok(response)) => {
                                    log::info!("[SECURE PURGE] Réponse reçue de secure_purge: {}", response.status());
                                }
                                Ok(Err(e)) => {
                                    log::error!("[SECURE PURGE] Échec de l'appel secure_purge: {e}");
                                }
                                Err(e) => {
                                    log::error!("[SECURE PURGE] Erreur de thread pour secure_purge: {e}");
                                }
                            }

                            // 2. Attendre 1 heure (3600 secondes)
                            tokio::time::sleep(tokio::time::Duration::from_secs(3600)).await;
                        }
                    });
                }
            });

            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("Erreur lors de la construction de l'application Tauri");

    app.run(|app_handle, event| {
        match event {
            tauri::RunEvent::ExitRequested { .. } | tauri::RunEvent::Exit => {
                log::info!("Fermeture de l'application — arrêt des serveurs sidecar...");
                server_manager::stop_all_sidecars(app_handle);
            }
            _ => {}
        }
    });
}

// =============================================================================
// Fonction utilitaire : attendre qu'un port réponde en TCP
// =============================================================================

/// Tente de se connecter en TCP sur le port spécifié toutes les 500ms.
/// Retourne `true` si le serveur répond dans le délai, `false` sinon.
async fn wait_for_port(port: u16, timeout_secs: u64) -> bool {
    use tokio::net::TcpStream;
    use tokio::time::{sleep, timeout, Duration};

    let deadline = Duration::from_secs(timeout_secs);
    let poll_interval = Duration::from_millis(500);
    let addr = format!("127.0.0.1:{port}");

    log::debug!("[wait_for_port] Polling {addr} (timeout: {timeout_secs}s)");

    let result = timeout(deadline, async {
        loop {
            match TcpStream::connect(&addr).await {
                Ok(_) => {
                    log::info!("[wait_for_port] Le port {port} répond");
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

// Trigger rebuild
