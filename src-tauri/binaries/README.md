# Convention de nommage des Sidecars Tauri (obligatoire)
#
# Tauri exige que les binaires embarqués soient nommés avec le "target triple"
# du système d'exploitation cible. Le chemin déclaré dans tauri.conf.json est
# "binaries/caddy", mais les fichiers physiques doivent respecter ces noms.
#
# Ce dossier doit contenir les binaires pour CHAQUE plateforme cible.
#
# ┌──────────────────────────────────────────────────────────────────────────┐
# │  OS        │ Architecture │ Fichiers requis                               │
# ├──────────────────────────────────────────────────────────────────────────┤
# │  Windows   │ x86_64       │ caddy-x86_64-pc-windows-msvc.exe              │
# │            │              │ php-x86_64-pc-windows-msvc.exe                │
# ├──────────────────────────────────────────────────────────────────────────┤
# │  macOS     │ Intel        │ caddy-x86_64-apple-darwin                     │
# │            │              │ php-x86_64-apple-darwin                       │
# │            │ Apple Silicon│ caddy-aarch64-apple-darwin                    │
# │            │              │ php-aarch64-apple-darwin                      │
# ├──────────────────────────────────────────────────────────────────────────┤
# │  Linux     │ x86_64       │ caddy-x86_64-unknown-linux-gnu                │
# │            │              │ php-x86_64-unknown-linux-gnu                  │
# └──────────────────────────────────────────────────────────────────────────┘
#
# IMPORTANT : Ce dossier est dans .gitignore car les binaires peuvent
#             être lourds et doivent être récupérés via un script de download
#             (scripts/download-binaries.js ou un équivalent Rust/shell).

Ce fichier README sert de documentation. Les binaires ne sont pas versionnés.
