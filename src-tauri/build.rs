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
            format!("dupli-php-{}{}", triple, ext),
        ];

        for sidecar in sidecars {
            let path = binaries_dir.join(sidecar);
            if !path.exists() {
                // Crée un fichier de 1 octet pour valider la présence
                let _ = fs::write(path, b"\0");
            }
        }

        // Copier les DLLs de binaries/ vers le répertoire cible (target/debug ou target/release)
        if target_os == "windows" {
            if let Ok(out_dir) = env::var("OUT_DIR") {
                let out_path = std::path::Path::new(&out_dir);
                if let Some(target_dir) = out_path.parent().and_then(|p| p.parent()).and_then(|p| p.parent()) {
                    let current_dir = env::current_dir().unwrap();
                    let binaries_dir = current_dir.join("binaries");
                    if binaries_dir.exists() {
                        if let Ok(entries) = fs::read_dir(&binaries_dir) {
                            for entry in entries.flatten() {
                                let path = entry.path();
                                if path.is_file() {
                                    if let Some(ext) = path.extension() {
                                        if ext.to_string_lossy().to_lowercase() == "dll" {
                                            let dest = target_dir.join(path.file_name().unwrap());
                                            let _ = fs::copy(&path, &dest);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Copier également le dossier des extensions 'ext' entier vers target/
                    let ext_src = binaries_dir.join("ext");
                    if ext_src.exists() {
                        let ext_dst = target_dir.join("ext");
                        let _ = copy_dir_all(&ext_src, &ext_dst);
                    }
                }
            }
        }
    }

    #[cfg(target_os = "windows")]
    {
        let mut windows = tauri_build::WindowsAttributes::new();
        windows = windows.app_manifest(
            r#"
<assembly xmlns="urn:schemas-microsoft-com:asm.v1" manifestVersion="1.0">
  <dependency>
    <dependentAssembly>
      <assemblyIdentity
        type="win32"
        name="Microsoft.Windows.Common-Controls"
        version="6.0.0.0"
        processorArchitecture="*"
        publicKeyToken="6595b64144ccf1df"
        language="*"
      />
    </dependentAssembly>
  </dependency>
  <trustInfo xmlns="urn:schemas-microsoft-com:asm.v3">
    <security>
        <requestedPrivileges>
            <requestedExecutionLevel level="requireAdministrator" uiAccess="false" />
        </requestedPrivileges>
    </security>
  </trustInfo>
</assembly>
"#
        );
        tauri_build::try_build(tauri_build::Attributes::new().windows_attributes(windows))
            .expect("failed to run build script");
    }

    #[cfg(not(target_os = "windows"))]
    {
        tauri_build::build();
    }
}

fn copy_dir_all(src: impl AsRef<std::path::Path>, dst: impl AsRef<std::path::Path>) -> std::io::Result<()> {
    fs::create_dir_all(&dst)?;
    for entry in fs::read_dir(src)? {
        let entry = entry?;
        let ty = entry.file_type()?;
        if ty.is_dir() {
            copy_dir_all(entry.path(), dst.as_ref().join(entry.file_name()))?;
        } else {
            fs::copy(entry.path(), dst.as_ref().join(entry.file_name()))?;
        }
    }
    Ok(())
}
