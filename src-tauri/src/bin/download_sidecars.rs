use std::env;
use std::fs::{self, File};
use std::io::{self, Read};
use std::path::Path;
use std::process::Command;

// =============================================================================
// Détection du Target Triple cible de Rust
// =============================================================================
fn get_target_triple() -> &'static str {
    let os = env::consts::OS;
    let arch = env::consts::ARCH;

    match (os, arch) {
        ("windows", "x86_64") => "x86_64-pc-windows-msvc",
        ("windows", "x86") => "i686-pc-windows-msvc",
        ("windows", "aarch64") => "aarch64-pc-windows-msvc",
        ("linux", "x86_64") => "x86_64-unknown-linux-gnu",
        ("linux", "aarch64") => "aarch64-unknown-linux-gnu",
        ("macos", "x86_64") => "x86_64-apple-darwin",
        ("macos", "aarch64") => "aarch64-apple-darwin",
        _ => panic!("Architecture ou OS non supporté : {}-{}", os, arch),
    }
}

// =============================================================================
// URLs des binaires Caddy & PHP
// =============================================================================
struct DownloadConfig {
    url: &'static str,
    is_zip: bool,
    bin_in_archive: &'static str,
}

fn get_caddy_config(triple: &str) -> Option<DownloadConfig> {
    match triple {
        "x86_64-pc-windows-msvc" => Some(DownloadConfig {
            url: "https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_windows_amd64.zip",
            is_zip: true,
            bin_in_archive: "caddy.exe",
        }),
        "x86_64-unknown-linux-gnu" => Some(DownloadConfig {
            url: "https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_linux_amd64.tar.gz",
            is_zip: false,
            bin_in_archive: "caddy",
        }),
        "aarch64-unknown-linux-gnu" => Some(DownloadConfig {
            url: "https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_linux_arm64.tar.gz",
            is_zip: false,
            bin_in_archive: "caddy",
        }),
        "x86_64-apple-darwin" => Some(DownloadConfig {
            url: "https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_macOS_amd64.tar.gz",
            is_zip: false,
            bin_in_archive: "caddy",
        }),
        "aarch64-apple-darwin" => Some(DownloadConfig {
            url: "https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_macOS_arm64.tar.gz",
            is_zip: false,
            bin_in_archive: "caddy",
        }),
        _ => None,
    }
}

fn get_php_windows_config() -> DownloadConfig {
    DownloadConfig {
        url: "https://windows.php.net/downloads/releases/php-8.4.13-nts-Win32-vs17-x64.zip",
        is_zip: true,
        bin_in_archive: "php.exe",
    }
}

// =============================================================================
// Logique principale
// =============================================================================
fn main() -> Result<(), Box<dyn std::error::Error>> {
    let triple = get_target_triple();
    println!("### Configuration Rust des Sidecars pour la cible : {} ###", triple);

    // Détermination du répertoire de sortie binaries/ à la racine de src-tauri
    // Le binaire s'exécute depuis le dossier du projet ou src-tauri
    let current_dir = env::current_dir()?;
    let binaries_dir = if current_dir.ends_with("src-tauri") {
        current_dir.join("binaries")
    } else {
        current_dir.join("src-tauri").join("binaries")
    };

    if !binaries_dir.exists() {
        fs::create_dir_all(&binaries_dir)?;
    }

    setup_caddy(triple, &binaries_dir)?;
    setup_php(triple, &binaries_dir)?;

    println!("### Configuration terminée avec succès dans : {} ###", binaries_dir.display());
    Ok(())
}

fn setup_caddy(triple: &str, binaries_dir: &Path) -> Result<(), Box<dyn std::error::Error>> {
    let config = match get_caddy_config(triple) {
        Some(c) => c,
        None => {
            println!("Aucune configuration Caddy trouvée pour la cible {}.", triple);
            return Ok(());
        }
    };

    let ext = if triple.contains("windows") { ".exe" } else { "" };
    let output_name = format!("caddy-{}{}", triple, ext);
    let output_path = binaries_dir.join(&output_name);

    if output_path.exists() && fs::metadata(&output_path)?.len() > 1024 {
        println!("[Caddy] Le binaire existe déjà : {}", output_name);
        return Ok(());
    }

    println!("[Caddy] Téléchargement depuis : {}...", config.url);
    let response = ureq::get(config.url).call()?;
    let mut body_bytes = Vec::new();
    response.into_reader().read_to_end(&mut body_bytes)?;

    let tmp_archive = binaries_dir.join(format!("tmp_caddy_archive.{}", if config.is_zip { "zip" } else { "tar.gz" }));
    fs::write(&tmp_archive, &body_bytes)?;

    println!("[Caddy] Extraction de l'archive...");
    let tmp_extract_dir = binaries_dir.join("tmp_caddy_extracted");
    if tmp_extract_dir.exists() {
        fs::remove_dir_all(&tmp_extract_dir)?;
    }
    fs::create_dir_all(&tmp_extract_dir)?;

    extract_archive(&tmp_archive, &tmp_extract_dir, config.is_zip)?;

    let extracted_bin = tmp_extract_dir.join(config.bin_in_archive);
    fs::copy(&extracted_bin, &output_path)?;

    #[cfg(unix)]
    {
        use std::os::unix::fs::PermissionsExt;
        fs::set_permissions(&output_path, fs::Permissions::from_mode(0o755))?;
    }

    // Nettoyage des fichiers temporaires
    let _ = fs::remove_file(&tmp_archive);
    let _ = fs::remove_dir_all(&tmp_extract_dir);

    println!("[Caddy] Installé sous : {}", output_name);
    Ok(())
}

fn setup_php(triple: &str, binaries_dir: &Path) -> Result<(), Box<dyn std::error::Error>> {
    let ext = if triple.contains("windows") { ".exe" } else { "" };
    let output_name = format!("php-{}{}", triple, ext);
    let output_path = binaries_dir.join(&output_name);

    if output_path.exists() && fs::metadata(&output_path)?.len() > 1024 {
        println!("[PHP] Le binaire existe déjà : {}", output_name);
        return Ok(());
    }

    if triple.contains("windows") {
        let config = get_php_windows_config();
        println!("[PHP] Téléchargement de PHP Windows depuis : {}...", config.url);
        let response = ureq::get(config.url).call()?;
        let mut body_bytes = Vec::new();
        response.into_reader().read_to_end(&mut body_bytes)?;

        let tmp_archive = binaries_dir.join("tmp_php_archive.zip");
        fs::write(&tmp_archive, &body_bytes)?;

        println!("[PHP] Extraction...");
        let tmp_extract_dir = binaries_dir.join("tmp_php_extracted");
        if tmp_extract_dir.exists() {
            fs::remove_dir_all(&tmp_extract_dir)?;
        }
        fs::create_dir_all(&tmp_extract_dir)?;

        extract_archive(&tmp_archive, &tmp_extract_dir, true)?;

        let extracted_bin = tmp_extract_dir.join(config.bin_in_archive);
        fs::copy(&extracted_bin, &output_path)?;

        let _ = fs::remove_file(&tmp_archive);
        let _ = fs::remove_dir_all(&tmp_extract_dir);

        println!("[PHP] Installé sous : {}", output_name);
    } else {
        // Unix (Linux/macOS) : Copie du PHP système local
        println!("[PHP] Utilisation du PHP système...");
        
        let which_php = Command::new("which")
            .arg("php")
            .output()?;
        
        if !which_php.status.success() {
            return Err("PHP n'est pas détecté sur le système Unix local.".into());
        }

        let php_path_str = String::from_utf8_lossy(&which_php.stdout).trim().to_string();
        let php_path = Path::new(&php_path_str);

        fs::copy(php_path, &output_path)?;
        #[cfg(unix)]
        {
            use std::os::unix::fs::PermissionsExt;
            fs::set_permissions(&output_path, fs::Permissions::from_mode(0o755))?;
        }
        println!("[PHP] Copié depuis le système vers : {}", output_name);

        // Tentative de copie de php-fpm
        let fpm_name = format!("php-fpm-{}", triple);
        let fpm_path = binaries_dir.join(&fpm_name);

        if !fpm_path.exists() {
            let mut fpm_found = false;
            // Essayer différentes variantes de noms courants
            for fpm_cmd in &["php-fpm", "php-fpm8.2", "php-fpm8.1", "php-fpm8.3"] {
                let which_fpm = Command::new("which").arg(fpm_cmd).output();
                if let Ok(out) = which_fpm {
                    if out.status.success() {
                        let path_str = String::from_utf8_lossy(&out.stdout).trim().to_string();
                        fs::copy(Path::new(&path_str), &fpm_path)?;
                        #[cfg(unix)]
                        {
                            use std::os::unix::fs::PermissionsExt;
                            fs::set_permissions(&fpm_path, fs::Permissions::from_mode(0o755))?;
                        }
                        println!("[PHP-FPM] Copié depuis le système vers : {}", fpm_name);
                        fpm_found = true;
                        break;
                    }
                }
            }
            if !fpm_found {
                println!("[PHP-FPM] php-fpm non détecté en local. Seul le serveur PHP intégré sera utilisé.");
            }
        }
    }

    Ok(())
}

fn extract_archive(archive: &Path, dest: &Path, is_zip: bool) -> Result<(), Box<dyn std::error::Error>> {
    let file = File::open(archive)?;
    if is_zip {
        let mut archive = zip::ZipArchive::new(file)?;
        for i in 0..archive.len() {
            let mut file = archive.by_index(i)?;
            let outpath = match file.enclosed_name() {
                Some(path) => dest.join(path),
                None => continue,
            };

            if file.name().ends_with('/') {
                fs::create_dir_all(&outpath)?;
            } else {
                if let Some(p) = outpath.parent() {
                    if !p.exists() {
                        fs::create_dir_all(p)?;
                    }
                }
                let mut outfile = File::create(&outpath)?;
                io::copy(&mut file, &mut outfile)?;
            }
        }
    } else {
        let tar = flate2::read::GzDecoder::new(file);
        let mut archive = tar::Archive::new(tar);
        archive.unpack(dest)?;
    }
    Ok(())
}
