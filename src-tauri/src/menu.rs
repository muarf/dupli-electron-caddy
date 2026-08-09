use tauri::menu::{Menu, MenuItem, PredefinedMenuItem, Submenu};
use tauri::{AppHandle, Manager, Wry};
use tauri_plugin_dialog::{DialogExt, MessageDialogButtons};
use tauri_plugin_shell::ShellExt;

pub fn build_menu(app_handle: &AppHandle) -> tauri::Result<Menu<Wry>> {
    let about_i = MenuItem::with_id(app_handle, "about", "About", true, None::<&str>)?;
    let check_updates_i = MenuItem::with_id(app_handle, "check_updates", "Check for Updates", true, Some("F3"))?;
    let restart_i = MenuItem::with_id(app_handle, "restart", "Restart", true, None::<&str>)?;
    let quit_i = MenuItem::with_id(app_handle, "quit", "Quit", true, None::<&str>)?;
    let reload_i = MenuItem::with_id(app_handle, "reload", "Reload", true, Some("F5"))?;
    let open_data_folder_i = MenuItem::with_id(app_handle, "open_data_folder", "Open Data Folder", true, None::<&str>)?;
    let open_devtools_i = MenuItem::with_id(app_handle, "open_devtools", "Open DevTools", true, None::<&str>)?;
    let debug_submenu = Submenu::with_id(app_handle, "debug", "Debug", true)?;
    debug_submenu.append(&open_devtools_i)?;
    let app_menu = Submenu::with_id(app_handle, "app_menu", "Application", true)?;
    app_menu.append(&about_i)?;
    app_menu.append(&check_updates_i)?;
    app_menu.append(&restart_i)?;
    app_menu.append(&PredefinedMenuItem::separator(app_handle)?)?;
    app_menu.append(&quit_i)?;
    let view_menu = Submenu::with_id(app_handle, "view_menu", "View", true)?;
    view_menu.append(&reload_i)?;
    view_menu.append(&open_data_folder_i)?;
    view_menu.append(&debug_submenu)?;
    let menu = Menu::with_items(app_handle, &[&app_menu, &view_menu])?;
    Ok(menu)
}

pub fn handle_menu_event(app: &AppHandle, event: tauri::menu::MenuEvent) {
    match event.id().as_ref() {
        "about" => {
            let version = app.package_info().version.to_string();
            let app_clone = app.clone();
            app.dialog()
                .message(format!(
                    "Duplicator\n\nVersion {}\n\nApplication de duplication de documents\n\nGitHub: https://github.com/muarf/dupli-electron-caddy",
                    version
                ))
                .title("About Duplicator")
                .buttons(MessageDialogButtons::OkCustom("Open GitHub".into()))
                .show(move |_| {
                    #[allow(deprecated)]
                    let _ = app_clone.shell().open("https://github.com/muarf/dupli-electron-caddy", None);
                });
        }
        "check_updates" => {
            let app_clone = app.clone();
            tauri::async_runtime::spawn(async move {
                let state = app_clone.state::<crate::app_commands::UpdateState>();
                let _ = crate::app_commands::check_for_updates(app_clone.clone(), state).await;
            });
        }
        "restart" => {
            crate::app_commands::restart_app();
        }
        "quit" => {
            app.exit(0);
        }
        "reload" => {
            if let Some(window) = app.get_webview_window("main") {
                let _ = window.eval("window.location.reload();");
            }
        }
        "open_data_folder" => {
            if let Ok(dir) = app.path().app_data_dir() {
                let _ = app.shell().open(dir.to_string_lossy().to_string(), None);
            }
        }
        "open_devtools" => {
            if let Some(window) = app.get_webview_window("main") {
                if window.is_devtools_open() {
                    window.close_devtools();
                } else {
                    window.open_devtools();
                }
            }
        }
        _ => {}
    }
}
