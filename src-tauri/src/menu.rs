use tauri::menu::{Menu, MenuItem, PredefinedMenuItem, Submenu};
use tauri::{AppHandle, Manager, Wry};
use tauri_plugin_dialog::{DialogExt, MessageDialogButtons};
use tauri_plugin_shell::ShellExt;

/// Construit le menu natif (Application et Affichage)
pub fn build_menu(app_handle: &AppHandle) -> tauri::Result<Menu<Wry>> {
    // Sous-menu Application
    let about_i = MenuItem::with_id(app_handle, "about", "À propos", true, None::<&str>)?;
    let check_updates_i = MenuItem::with_id(app_handle, "check_updates", "Vérifier les mises à jour", true, Some("F3"))?;
    let devtools_i = MenuItem::with_id(app_handle, "devtools", "Outils de développement", true, Some("CmdOrControl+Shift+I"))?;
    let quit_i = MenuItem::with_id(app_handle, "quit", "Quitter", true, Some("CmdOrControl+Q"))?;
    
    let app_submenu = Submenu::with_items(
        app_handle,
        "Application",
        true,
        &[
            &about_i,
            &check_updates_i,
            &devtools_i,
            &PredefinedMenuItem::separator(app_handle)?,
            &quit_i,
        ],
    )?;

    // Sous-menu Affichage
    let reload_i = MenuItem::with_id(app_handle, "reload", "Recharger", true, Some("F5"))?;
    let force_reload_i = MenuItem::with_id(app_handle, "force_reload", "Forcer le rechargement", true, Some("CmdOrControl+F5"))?;
    
    let view_submenu = Submenu::with_items(
        app_handle,
        "Affichage",
        true,
        &[
            &reload_i,
            &force_reload_i,
        ],
    )?;

    Menu::with_items(app_handle, &[&app_submenu, &view_submenu])
}

/// Gère les clics sur les éléments du menu
pub fn handle_menu_event(app_handle: &AppHandle, event: tauri::menu::MenuEvent) {
    match event.id().as_ref() {
        "about" => {
            let version = app_handle.package_info().version.to_string();
            let app_handle_clone = app_handle.clone();
            
            app_handle.dialog()
                .message(format!("Duplicator\n\nVersion {}\n\nApplication de duplication de documents\n\nGitHub: https://github.com/muarf/dupli-electron-caddy", version))
                .title("À propos de Duplicator")
                .buttons(MessageDialogButtons::OkCustom("Ouvrir GitHub".into()))
                .show(move |result| {
                    if !result {
                        // L'utilisateur a cliqué sur "Ouvrir GitHub" (le bouton custom)
                        let _ = app_handle_clone.shell().open("https://github.com/muarf/dupli-electron-caddy", None);
                    }
                });
        }
        "check_updates" => {
            let app_clone = app_handle.clone();
            tauri::async_runtime::spawn(async move {
                let state = app_clone.state::<crate::app_commands::UpdateState>();
                let _ = crate::app_commands::check_for_updates(app_clone.clone(), state).await;
            });
        }
        "devtools" => {
            #[cfg(debug_assertions)]
            if let Some(window) = app_handle.get_webview_window("main") {
                if window.is_devtools_open() {
                    window.close_devtools();
                } else {
                    window.open_devtools();
                }
            }
        }
        "quit" => {
            app_handle.exit(0);
        }
        "reload" => {
            if let Some(window) = app_handle.get_webview_window("main") {
                let _ = window.eval("window.location.reload();");
            }
        }
        "force_reload" => {
            if let Some(window) = app_handle.get_webview_window("main") {
                let _ = window.eval("window.location.reload(true);");
            }
        }
        _ => {}
    }
}
