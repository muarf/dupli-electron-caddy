// =============================================================================
// windows_native.rs - Couche bas niveau : API Win32 Spouleur d'impression
//
// Responsabilité UNIQUE : Appels FFI bruts à winspool.drv (Win32).
// Ce module ne sait rien de Tauri, de serde, ni du réseau.
// Il expose une API Rust propre, typée, et safe en surface.
//
// IMPORTANT : Ce module n'est compilé que sur Windows.
// =============================================================================

#![cfg(target_os = "windows")]

use std::ffi::OsString;
use std::os::windows::ffi::OsStringExt;
use windows_sys::Win32::Foundation::{FALSE, GetLastError, HANDLE};
use windows_sys::Win32::Graphics::Printing::{
    ClosePrinter, DeletePrinter, EnumJobsW, EnumPrintersW, OpenPrinterW, SetJobW,
    JOB_INFO_2W, PRINTER_ENUM_CONNECTIONS, PRINTER_ENUM_LOCAL, PRINTER_INFO_2W,
};

// =============================================================================
// Types Rust propres retournés par ce module
// Ces types ne dépendent pas de windows-sys et peuvent être utilisés librement.
// =============================================================================

/// Représentation propre d'une imprimante (sans pointeur FFI)
#[derive(Debug, Clone)]
pub struct PrinterInfo {
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

/// Représentation propre d'un job d'impression (sans pointeur FFI)
#[derive(Debug, Clone)]
pub struct PrintJob {
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
    /// Issu de DEVMODE.dmDuplex > 1 (2=recto-verso grand côté, 3=petit côté)
    pub is_duplex: bool,
    /// Issu de DEVMODE.dmPaperSize (9=A4, 8=A3, 11=A5, 1=Letter, 5=Legal)
    pub paper_size: String,
    /// Issu de DEVMODE.dmColor (2=Color, 1=Monochrome)
    pub is_color: bool,
    /// Nombre d'exemplaires (issu de DEVMODE.dmCopies)
    pub copies: u32,
    /// Heure de soumission du job (issu de JOB_INFO_2W.Submitted, format ISO 8601)
    /// Clé de déduplication : permet de distinguer deux jobs qui auraient le même job_id
    /// (Windows recycle les IDs de 1 à ~999 avant de reboucler).
    pub time_submitted: String,
}

/// Erreur Win32 avec code et message lisible
#[derive(Debug)]
pub struct Win32Error {
    pub code: u32,
    pub message: String,
}

impl std::fmt::Display for Win32Error {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "Erreur Win32 {}: {}", self.code, self.message)
    }
}

pub type Win32Result<T> = Result<T, Win32Error>;

// =============================================================================
// Fonctions publiques — API Rust safe
// =============================================================================

/// Énumère toutes les imprimantes locales et les connexions réseau.
/// Retourne une liste de `PrinterInfo` avec leurs métadonnées complètes.
pub fn enum_printers() -> Win32Result<Vec<PrinterInfo>> {
    log::trace!("[windows_native] enum_printers() : début de l'énumération");

    let flags = PRINTER_ENUM_LOCAL | PRINTER_ENUM_CONNECTIONS;

    // --- Étape 1 : Premier appel à vide pour obtenir la taille du buffer ---
    let mut bytes_needed: u32 = 0;
    let mut printer_count: u32 = 0;

    // Ce premier appel est attendu d'échouer (ERROR_INSUFFICIENT_BUFFER = 122)
    // Son seul but est de remplir `bytes_needed`.
    unsafe {
        EnumPrintersW(
            flags,
            std::ptr::null_mut(), // serveur local (null = machine courante)
            2,                    // niveau 2 = structure PRINTER_INFO_2W
            std::ptr::null_mut(), // pas encore de buffer
            0,
            &mut bytes_needed,
            &mut printer_count,
        );
    }

    if bytes_needed == 0 {
        log::warn!("[windows_native] enum_printers() : aucune imprimante détectée (bytes_needed=0)");
        return Ok(Vec::new());
    }

    log::trace!(
        "[windows_native] enum_printers() : buffer requis = {} octets pour {} imprimantes potentielles",
        bytes_needed, printer_count
    );

    // --- Étape 2 : Allocation du buffer et vrai appel ---
    let mut buffer: Vec<u8> = vec![0u8; bytes_needed as usize];

    let success = unsafe {
        EnumPrintersW(
            flags,
            std::ptr::null_mut(),
            2,
            buffer.as_mut_ptr(),
            bytes_needed,
            &mut bytes_needed,
            &mut printer_count,
        )
    };

    if success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!("[windows_native] enum_printers() : EnumPrintersW a échoué avec le code Win32 {}", code);
        return Err(Win32Error {
            code,
            message: format!("EnumPrintersW a échoué (code: {})", win32_error_message(code)),
        });
    }

    log::debug!("[windows_native] enum_printers() : {} imprimantes trouvées", printer_count);

    // --- Étape 3 : Parsing des structures PRINTER_INFO_2W depuis le buffer ---
    // SÉCURITÉ : les pointeurs dans les structs PRINTER_INFO_2W pointent DANS le buffer.
    // On copie toutes les strings AVANT que le buffer ne soit droppé.
    let printers = unsafe {
        let info_slice = std::slice::from_raw_parts(
            buffer.as_ptr() as *const PRINTER_INFO_2W,
            printer_count as usize,
        );

        info_slice
            .iter()
            .enumerate()
            .map(|(i, info)| {
                let name = wide_ptr_to_string(info.pPrinterName);
                let driver = wide_ptr_to_string(info.pDriverName);
                let port = wide_ptr_to_string(info.pPortName);
                let comment = wide_ptr_to_string(info.pComment);
                let location = wide_ptr_to_string(info.pLocation);
                let status = info.Status;
                let jobs = info.cJobs;

                log::trace!(
                    "[windows_native] enum_printers() [{}] '{}' | status=0x{:08X} | jobs={} | port={}",
                    i, name, status, jobs, port
                );

                PrinterInfo {
                    status_label: printer_status_to_label(status),
                    name,
                    driver_name: driver,
                    port_name: port,
                    comment,
                    location,
                    status,
                    jobs_count: jobs,
                    is_default: (info.Attributes & 0x0004) != 0, // PRINTER_ATTRIBUTE_DEFAULT
                    is_shared: (info.Attributes & 0x0008) != 0,  // PRINTER_ATTRIBUTE_SHARED
                }
            })
            .collect()
    };
    // `buffer` est droppé ici — la mémoire est libérée automatiquement par Rust

    Ok(printers)
}

/// Récupère tous les jobs d'impression d'une imprimante donnée.
/// Ouvre et ferme le handle proprement (pas de fuite de ressource).
pub fn get_print_jobs(printer_name: &str) -> Win32Result<Vec<PrintJob>> {
    log::trace!("[windows_native] get_print_jobs('{}') : début", printer_name);

    let printer_name_wide: Vec<u16> = to_wide_null(printer_name);

    // --- Ouverture du handle de l'imprimante ---
    let mut handle: HANDLE = std::ptr::null_mut();
    let success = unsafe {
        OpenPrinterW(
            printer_name_wide.as_ptr() as *mut u16,
            &mut handle,
            std::ptr::null_mut(), // pas de PRINTER_DEFAULTS spécifique
        )
    };

    if success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!(
            "[windows_native] get_print_jobs('{}') : OpenPrinterW échoué, code={}",
            printer_name, code
        );
        return Err(Win32Error {
            code,
            message: format!(
                "Impossible d'ouvrir l'imprimante '{}' ({})",
                printer_name, win32_error_message(code)
            ),
        });
    }

    // INVARIANT : à partir d'ici, `handle` DOIT être fermé via ClosePrinter.
    // On utilise un scope + fermeture explicite pour garantir cela.
    let result = unsafe { get_jobs_from_handle(handle, printer_name) };

    // Fermeture du handle — toujours exécutée même si get_jobs_from_handle échoue
    unsafe { ClosePrinter(handle); }
    log::trace!("[windows_native] get_print_jobs('{}') : handle fermé proprement", printer_name);

    result
}

/// Supprime un job d'impression par son ID.
/// Retourne Ok(()) si le job a été supprimé avec succès.
pub fn delete_print_job(printer_name: &str, job_id: u32) -> Win32Result<()> {
    log::debug!(
        "[windows_native] delete_print_job('{}', job_id={}) : tentative de suppression",
        printer_name, job_id
    );

    let printer_name_wide: Vec<u16> = to_wide_null(printer_name);

    let mut handle: HANDLE = std::ptr::null_mut();
    let success = unsafe {
        OpenPrinterW(
            printer_name_wide.as_ptr() as *mut u16,
            &mut handle,
            std::ptr::null_mut(),
        )
    };

    if success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!(
            "[windows_native] delete_print_job : OpenPrinterW échoué pour '{}', code={}",
            printer_name, code
        );
        return Err(Win32Error {
            code,
            message: format!(
                "Impossible d'ouvrir l'imprimante '{}' pour suppression ({})",
                printer_name, win32_error_message(code)
            ),
        });
    }

    // JOB_CONTROL_DELETE = 5 selon la doc MSDN
    const JOB_CONTROL_DELETE_CMD: u32 = 5;

    let delete_success = unsafe {
        SetJobW(
            handle,
            job_id,
            0,                    // Level 0 = pas de structure de données
            std::ptr::null_mut(), // pJob = null car Level = 0
            JOB_CONTROL_DELETE_CMD,
        )
    };

    // Fermeture du handle dans tous les cas
    unsafe { ClosePrinter(handle); }

    if delete_success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!(
            "[windows_native] delete_print_job : SetJobW échoué pour job_id={}, code={}",
            job_id, code
        );
        return Err(Win32Error {
            code,
            message: format!(
                "Impossible de supprimer le job {} ({})",
                job_id, win32_error_message(code)
            ),
        });
    }

    log::info!(
        "[windows_native] delete_print_job : job {} supprimé de '{}'",
        job_id, printer_name
    );
    Ok(())
}

/// Supprime une imprimante du système Windows.
/// Nécessite les droits Manage Printers (administrateur en pratique).
/// Retourne Ok(()) si la suppression a réussi.
pub fn delete_printer(printer_name: &str) -> Win32Result<()> {
    log::debug!(
        "[windows_native] delete_printer('{}') : tentative de suppression",
        printer_name
    );

    let printer_name_wide: Vec<u16> = to_wide_null(printer_name);

    // Ouverture du handle avec les droits de gestion (PRINTER_ALL_ACCESS = 0x000F000C)
    let mut handle: HANDLE = std::ptr::null_mut();
    let success = unsafe {
        OpenPrinterW(
            printer_name_wide.as_ptr() as *mut u16,
            &mut handle,
            std::ptr::null_mut(),
        )
    };

    if success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!(
            "[windows_native] delete_printer('{}') : OpenPrinterW échoué, code={}",
            printer_name, code
        );
        return Err(Win32Error {
            code,
            message: format!(
                "Impossible d'ouvrir l'imprimante '{}' pour suppression ({})",
                printer_name, win32_error_message(code)
            ),
        });
    }

    // DeletePrinter prend le handle ouvert et supprime l'imprimante
    let delete_success = unsafe { DeletePrinter(handle) };

    // ClosePrinter même si la suppression échoue
    unsafe { ClosePrinter(handle); }

    if delete_success == FALSE {
        let code = unsafe { GetLastError() };
        log::error!(
            "[windows_native] delete_printer('{}') : DeletePrinterW échoué, code={}",
            printer_name, code
        );
        return Err(Win32Error {
            code,
            message: format!(
                "Impossible de supprimer l'imprimante '{}' ({})",
                printer_name, win32_error_message(code)
            ),
        });
    }

    log::info!("[windows_native] delete_printer('{}') : imprimante supprimée", printer_name);
    Ok(())
}

// =============================================================================
// Fonctions privées — Utilitaires FFI (UNSAFE contenu ici)
// =============================================================================

/// Récupère les jobs d'impression depuis un handle déjà ouvert.
/// Factoriser cette logique permet de garantir que le ClosePrinter n'est pas oublié.
unsafe fn get_jobs_from_handle(handle: HANDLE, printer_name: &str) -> Win32Result<Vec<PrintJob>> {
    let mut bytes_needed: u32 = 0;
    let mut job_count: u32 = 0;

    // Premier appel à vide pour calculer la taille du buffer
    EnumJobsW(
        handle,
        0,                    // premier job (index 0)
        u32::MAX,             // nombre max de jobs à récupérer
        2,                    // niveau 2 = JOB_INFO_2W (le plus complet)
        std::ptr::null_mut(),
        0,
        &mut bytes_needed,
        &mut job_count,
    );

    if bytes_needed == 0 {
        log::trace!("[windows_native] get_jobs_from_handle('{}') : aucun job en attente", printer_name);
        return Ok(Vec::new());
    }

    log::trace!(
        "[windows_native] get_jobs_from_handle('{}') : {} octets pour {} jobs",
        printer_name, bytes_needed, job_count
    );

    let mut buffer: Vec<u8> = vec![0u8; bytes_needed as usize];

    let success = EnumJobsW(
        handle,
        0,
        u32::MAX,
        2,
        buffer.as_mut_ptr(),
        bytes_needed,
        &mut bytes_needed,
        &mut job_count,
    );

    if success == FALSE {
        let code = GetLastError();
        log::error!(
            "[windows_native] get_jobs_from_handle('{}') : EnumJobsW échoué, code={}",
            printer_name, code
        );
        return Err(Win32Error {
            code,
            message: format!("EnumJobsW échoué pour '{}' ({})", printer_name, win32_error_message(code)),
        });
    }

    let job_slice = std::slice::from_raw_parts(
        buffer.as_ptr() as *const JOB_INFO_2W,
        job_count as usize,
    );

    let jobs = job_slice
        .iter()
        .map(|job| {
            let document = wide_ptr_to_string(job.pDocument);
            let user = wide_ptr_to_string(job.pUserName);
            let machine = wide_ptr_to_string(job.pMachineName);
            let datatype = wide_ptr_to_string(job.pDatatype);
            let status = job.Status;

            // Lire le timestamp de soumission (SYSTEMTIME) pour construire la clé de dédup
            let st = &job.Submitted;
            let time_submitted = if st.wYear > 0 {
                format!(
                    "{:04}-{:02}-{:02}T{:02}:{:02}:{:02}.{:03}Z",
                    st.wYear, st.wMonth, st.wDay,
                    st.wHour, st.wMinute, st.wSecond, st.wMilliseconds
                )
            } else {
                String::new()
            };

            log::trace!(
                "[windows_native] job_id={} doc='{}' user='{}' status=0x{:08X} pages={}/{} submitted='{}'",
                job.JobId, document, user, status, job.PagesPrinted, job.TotalPages, time_submitted
            );

            // Lire les champs DEVMODE (duplex, taille papier, couleur, copies)
            // DEVMODEW offsets (Win32 ABI stable, WCHAR[32] + 4 WORDs + 1 DWORD + union) :
            //   offset 68 : dmSize      (u16)  — taille de la struct (garde de sécurité)
            //   offset 70 : dmFields    (u32)  — bitmask des champs valides
            //   offset 78 : dmPaperSize (i16)  — code papier Windows
            //   offset 86 : dmCopies    (i16)  — nombre de copies
            //   offset 92 : dmColor     (i16)  — 1=Mono, 2=Color
            //   offset 94 : dmDuplex    (i16)  — 1=Simplex, 2=DuplexLong, 3=DuplexShort
            //
            // On vérifie dmFields avant chaque lecture, exactement comme le fait le C++
            // de la branche beta : `if (dm->dmFields & DM_COPIES) ev.copies = dm->dmCopies;`
            // Sans cette vérification, on risque de lire un résidu mémoire à la place d'une
            // valeur valide (ex : le pilote RISO ne positionne pas toujours DM_COPIES via
            // EnumJobsW, ce qui renvoyait 0 → fallback 1 au lieu du vrai nombre d'exemplaires).
            const DM_PAPERSIZE: u32 = 0x0000_0002;
            const DM_COPIES:    u32 = 0x0000_0100;
            const DM_COLOR:     u32 = 0x0000_0800;
            const DM_DUPLEX:    u32 = 0x0000_1000;

            let (is_duplex, paper_size, is_color, copies) = unsafe {
                if !job.pDevMode.is_null() {
                    let ptr = job.pDevMode as *const u8;
                    let dm_size = u16::from_le_bytes([*ptr.add(68), *ptr.add(69)]);
                    if dm_size >= 96 {
                        // Lire le bitmask dmFields (offset 70, u32 LE)
                        let dm_fields = u32::from_le_bytes([
                            *ptr.add(70), *ptr.add(71), *ptr.add(72), *ptr.add(73),
                        ]);

                        let dm_paper_size = if dm_fields & DM_PAPERSIZE != 0 {
                            i16::from_le_bytes([*ptr.add(78), *ptr.add(79)])
                        } else { 9 }; // défaut A4

                        let dm_copies = if dm_fields & DM_COPIES != 0 {
                            i16::from_le_bytes([*ptr.add(86), *ptr.add(87)])
                        } else { 1 };

                        let dm_color = if dm_fields & DM_COLOR != 0 {
                            i16::from_le_bytes([*ptr.add(92), *ptr.add(93)])
                        } else { 1 }; // défaut Mono

                        let dm_duplex = if dm_fields & DM_DUPLEX != 0 {
                            i16::from_le_bytes([*ptr.add(94), *ptr.add(95)])
                        } else { 1 }; // défaut Simplex

                        let paper = match dm_paper_size {
                            9  => "A4",
                            8  => "A3",
                            11 => "A5",
                            1  => "Letter",
                            5  => "Legal",
                            _  => "A4",
                        };
                        let copies_val = if dm_copies > 0 { dm_copies as u32 } else { 1 };

                        log::trace!(
                            "[windows_native] job_id={} dmFields=0x{:08X} copies={} duplex={} color={} paper={}",
                            job.JobId, dm_fields, copies_val, dm_duplex, dm_color, paper
                        );

                        (dm_duplex > 1, paper.to_string(), dm_color == 2, copies_val)
                    } else {
                        (false, "A4".to_string(), false, 1)
                    }
                } else {
                    (false, "A4".to_string(), false, 1)
                }
            };

            PrintJob {
                job_id: job.JobId,
                document,
                machine_name: machine,
                user_name: user,
                datatype,
                status,
                status_label: job_status_to_label(status),
                pages_printed: job.PagesPrinted,
                total_pages: job.TotalPages,
                size_bytes: job.Size,
                priority: job.Priority,
                is_duplex,
                paper_size,
                is_color,
                copies,
                time_submitted,
            }
        })
        .collect();

    Ok(jobs)
    // `buffer` droppé ici — mémoire libérée
}

/// Convertit un pointeur vers une chaîne wide (UTF-16) en `String` Rust.
/// Retourne une chaîne vide si le pointeur est null.
///
/// SÉCURITÉ : Le pointeur doit rester valide pendant l'exécution de cette fonction.
///            Ne jamais stocker le pointeur au-delà de la durée de vie du buffer.
unsafe fn wide_ptr_to_string(ptr: *const u16) -> String {
    if ptr.is_null() {
        return String::new();
    }
    // Calcule la longueur de la chaîne (jusqu'au null terminator)
    let len = (0usize..).take_while(|&i| *ptr.add(i) != 0).count();
    let slice = std::slice::from_raw_parts(ptr, len);
    // Conversion UTF-16 → String Rust (les caractères invalides sont remplacés)
    OsString::from_wide(slice)
        .to_string_lossy()
        .into_owned()
}

/// Convertit une `&str` Rust en Vec<u16> terminé par null (format Win32)
fn to_wide_null(s: &str) -> Vec<u16> {
    use std::os::windows::ffi::OsStrExt;
    std::ffi::OsStr::new(s)
        .encode_wide()
        .chain(std::iter::once(0u16)) // null terminator obligatoire
        .collect()
}

/// Traduit un code d'erreur Win32 en message lisible
fn win32_error_message(code: u32) -> &'static str {
    match code {
        2   => "Fichier introuvable",
        5   => "Accès refusé (droits insuffisants ?)",
        122 => "Buffer trop petit",
        1801 => "Nom d'imprimante invalide",
        1802 => "L'imprimante n'existe pas",
        1804 => "Nom de datatype invalide",
        1722 => "Le serveur RPC est indisponible (spouleur arrêté ?)",
        _   => "Erreur système inconnue",
    }
}

/// Transforme le champ `Status` (bitmask) d'une PRINTER_INFO_2W en libellé lisible
fn printer_status_to_label(status: u32) -> String {
    if status == 0 {
        return "Prête".to_string();
    }
    let mut labels = Vec::new();
    if status & 0x00000001 != 0 { labels.push("En pause"); }
    if status & 0x00000002 != 0 { labels.push("Erreur"); }
    if status & 0x00000008 != 0 { labels.push("Hors ligne"); }
    if status & 0x00000010 != 0 { labels.push("Bourrage papier"); }
    if status & 0x00000080 != 0 { labels.push("Impression en cours"); }
    if status & 0x00000400 != 0 { labels.push("Porte ouverte"); }
    if status & 0x00000800 != 0 { labels.push("Papier manquant"); }
    if labels.is_empty() {
        format!("Statut inconnu (0x{:08X})", status)
    } else {
        labels.join(", ")
    }
}

/// Transforme le champ `Status` (bitmask) d'une JOB_INFO_2W en libellé lisible
fn job_status_to_label(status: u32) -> String {
    if status == 0 {
        return "En attente".to_string();
    }
    let mut labels = Vec::new();
    if status & 0x00000001 != 0 { labels.push("En pause"); }
    if status & 0x00000002 != 0 { labels.push("Erreur"); }
    if status & 0x00000004 != 0 { labels.push("Suppression en cours"); }
    if status & 0x00000008 != 0 { labels.push("Spoule en cours"); }
    if status & 0x00000010 != 0 { labels.push("Impression en cours"); }
    if status & 0x00000020 != 0 { labels.push("Hors ligne"); }
    if status & 0x00000040 != 0 { labels.push("Papier manquant"); }
    if status & 0x00000080 != 0 { labels.push("Supprimé"); }
    if status & 0x00000100 != 0 { labels.push("Bloqué"); }
    if labels.is_empty() {
        format!("Statut inconnu (0x{:08X})", status)
    } else {
        labels.join(", ")
    }
}
