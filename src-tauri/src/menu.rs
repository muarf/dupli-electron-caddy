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

pub fn handle_menu_event(app: &AppHandle, event: &str) {
    match event {
        "about" => {
            let _ = app.dialog().message("Dupli Electron Caddy").title("About").blocking_show();
        }
        "check_updates" => {
            let window = app.get_webview_window("main").unwrap();
            let _ = window.emit("check-updates-now", ());
        }
        "restart" => {
            let window = app.get_webview_window("main").unwrap();
            let _ = window.emit("request-restart", ());
        }
        "quit" => {
            std::process::exit(0);
        }
        "reload" => {
            if let Some(window) = app.get_webview_window("main") {
                let _ = window.reload();
            }
        }
        "open_data_folder" => {
            let window = app.get_webview_window("main").unwrap();
            let _ = window.emit("open-data-folder", ());
        }
        "open_devtools" => {
            if let Some(window) = app.get_webview_window("main") {
                window.open_devtools();
            }
        }
        _ => {}
    }
}
