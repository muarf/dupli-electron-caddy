use std::env;
use std::fs;

fn main() {
    // Résout le problème de l'œuf et de la poule :
    // Avant de compiler le binaire de téléchargement (ou l'app principale),
    // le script de build de Tauri (`tauri_build::build()`) va valider que les
    // sidecars déclarés dans tauri.conf.json existent physiquement sous `binaries/`.
    // Si ils sont absents, la compilation échoue.
    // Solution : On crée des fichiers vides (mocks temporaires) si ils n'existent pas encore.
    
    let target_os = env::var("CARGO_CFG_TARGET_OS").unwrap_or_default();
    let target_arch = env::var("CARGO_CFG_TARGET_ARCH").unwrap_or_default();
    
    // Détermination de la cible (Target Triple) courante
    let triple = match (target_os.as_str(), target_arch.as_str()) {
        ("windows", "x86_64") => "x86_64-pc-windows-msvc",
        ("windows", "x86") => "i686-pc-windows-msvc",
        ("windows", "aarch64") => "aarch64-pc-windows-msvc",
        ("linux", "x86_64") => "x86_64-unknown-linux-gnu",
        ("linux", "aarch64") => "aarch64-unknown-linux-gnu",
        ("macos", "x86_64") => "x86_64-apple-darwin",
        ("macos", "aarch64") => "aarch64-apple-darwin",
        _ => "",
    };

    if !triple.is_empty() {
        let current_dir = env::current_dir().unwrap();
        let binaries_dir = current_dir.join("binaries");
        if !binaries_dir.exists() {
            let _ = fs::create_dir_all(&binaries_dir);
        }

        let ext = if target_os == "windows" { ".exe" } else { "" };
        
        // Liste des sidecars à mocker s'ils sont absents
        let sidecars = vec![
            format!("caddy-{}{}", triple, ext),
            format!("php-{}{}", triple, ext),
        ];

        for sidecar in sidecars {
            let path = binaries_dir.join(sidecar);
            if !path.exists() {
                // Crée un fichier de 1 octet pour valider la présence
                let _ = fs::write(path, b"\0");
            }
        }
    }

    tauri_build::build();
}
