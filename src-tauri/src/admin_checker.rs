// =============================================================================
// admin_checker.rs - Gestion des droits d'administration et UAC sous Windows
//
// Responsabilité UNIQUE : Vérifier le statut administrateur de l'application
//                         et permettre sa relance en mode élevé.
//
// Ce module utilise `windows-sys` sous Windows pour manipuler directement
// les API de sécurité système (ShellExecuteW, IsUserAnAdmin).
// =============================================================================

use tauri::command;

// =============================================================================
// Commandes Tauri (Interface Frontend)
// =============================================================================

/// Vérifie si l'application est en cours d'exécution avec les privilèges admin.
///
/// Côté frontend : `await invoke('check_admin_status')`
/// Retourne : `true` si admin, `false` sinon.
#[command]
pub fn check_admin_status() -> bool {
    log::debug!("[admin_checker] Vérification du statut administrateur");
    
    #[cfg(target_os = "windows")]
    {
        // Appel natif à la fonction Windows `IsUserAnAdmin`
        let is_admin = unsafe { windows_sys::Win32::UI::Shell::IsUserAnAdmin() };
        let status = is_admin != 0;
        log::info!("[admin_checker] Statut administrateur sous Windows : {}", status);
        status
    }

    #[cfg(not(target_os = "windows"))]
    {
        // Unix (Linux/macOS) : On vérifie si l'utilisateur est root (UID 0)
        let uid = unsafe { libc::getuid() };
        let status = uid == 0;
        log::info!("[admin_checker] Statut root sous Unix : {}", status);
        status
    }
}

/// Tente de redémarrer l'application avec les privilèges administrateur (UAC prompt).
///
/// Côté frontend : `await invoke('restart_as_admin')`
/// Retourne : `Ok` si la requête de relance a été acceptée, `Err` sinon.
#[command]
pub fn restart_as_admin() -> Result<(), String> {
    log::info!("[admin_checker] Tentative de redémarrage de l'application en tant qu'administrateur");

    if check_admin_status() {
        log::warn!("[admin_checker] L'application possède déjà les droits administrateur");
        return Ok(());
    }

    #[cfg(target_os = "windows")]
    {
        // 1. Obtenir le chemin de l'exécutable courant de manière sécurisée
        let current_exe = std::env::current_exe()
            .map_err(|e| format!("Impossible de localiser l'exécutable : {}", e))?;
        
        // 2. Encoder en UTF-16 terminé par un caractère nul (requis par l'API Windows)
        let file_wide = to_wide_null(&current_exe.to_string_lossy());
        let operation_wide = to_wide_null("runas"); // "runas" déclenche l'invite UAC Windows
        
        // Sécurité : On récupère les arguments passés à l'application originale
        // pour les retransmettre à la nouvelle instance.
        let args: Vec<String> = std::env::args().skip(1).collect();
        let args_str = args.join(" ");
        let parameters_wide = to_wide_null(&args_str);

        log::debug!(
            "[admin_checker] Relance via ShellExecuteW : {} {}", 
            current_exe.display(), 
            args_str
        );

        unsafe {
            use windows_sys::Win32::UI::Shell::ShellExecuteW;
            use windows_sys::Win32::UI::WindowsAndMessaging::SW_SHOW;
            use windows_sys::Win32::Foundation::HWND;

            // Appel de l'API Windows ShellExecuteW de manière sécurisée
            let result = ShellExecuteW(
                0 as HWND,                     // Aucun handle de fenêtre parent requis
                operation_wide.as_ptr(),       // Verbe d'action "runas"
                file_wide.as_ptr(),            // Chemin de l'exécutable courant
                parameters_wide.as_ptr(),      // Arguments transmis à la relance
                std::ptr::null(),              // Répertoire de travail (hérité par défaut)
                SW_SHOW,                       // Affiche la fenêtre lancée
            );

            // ShellExecuteW retourne une valeur > 32 en cas de succès.
            // Si la valeur est <= 32, cela indique une erreur système (ex: 1223 = SE_ERR_ACCESSDENIED si refus UAC).
            let hinstance_val = result as usize;
            if hinstance_val <= 32 {
                log::error!(
                    "[admin_checker] ShellExecuteW a échoué avec le code de retour {}", 
                    hinstance_val
                );
                return Err(format!(
                    "Échec de l'élévation UAC (code de retour Windows : {}). L'utilisateur a probablement refusé la demande.", 
                    hinstance_val
                ));
            }
        }

        log::info!("[admin_checker] Demande d'élévation transmise à Windows avec succès. Fermeture du processus actuel.");
        // Ferme immédiatement l'instance courante sans privilèges pour laisser place à la nouvelle.
        std::process::exit(0);
    }

    #[cfg(not(target_os = "windows"))]
    {
        log::warn!("[admin_checker] L'élévation automatique par commande n'est pas supportée sous Linux/macOS");
        Err("L'élévation de privilèges via l'application n'est supportée que sous Windows. Sur ce système, lancez l'application en utilisant 'sudo'.".to_string())
    }
}

// =============================================================================
// Utilitaires de conversion FFI (Uniquement sous Windows)
// =============================================================================

#[cfg(target_os = "windows")]
/// Convertit une chaîne de caractères en UTF-16 terminé par un caractère nul
fn to_wide_null(s: &str) -> Vec<u16> {
    use std::os::windows::ffi::OsStrExt;
    std::ffi::OsStr::new(s)
        .encode_wide()
        .chain(std::iter::once(0u16))
        .collect()
}
