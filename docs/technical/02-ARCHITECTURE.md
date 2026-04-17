# Architecture du Projet Dupli Electron Caddy

> **Branche analysée :** `feature/cross-platform-unification`  
> **Date :** 8 avril 2026

---

## Vue d'ensemble architecturale

Dupli Electron Caddy suit une **architecture hybride multi-processus** combinant :

1. **Electron** (processus principal + renderer)
2. **Serveur web Caddy** (reverse proxy)
3. **Serveur PHP intégré** (PHP-Builtin Server)
4. **Modules natifs C++** (impression Windows/Linux)

```
┌─────────────────────────────────────────────────────────────────┐
│                         ELECTRON MAIN                           │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  main-caddy.js                                            │  │
│  │  ├── Gestion cycle de vie application                    │  │
│  │  ├── Démarrage serveur Caddy (port 8000)                 │  │
│  │  ├── Démarrage serveur PHP (port 8001)                   │  │
│  │  ├── IPC avec renderer (preload.js)                      │  │
│  │  ├── Gestion mise à jour (electron-updater)              │  │
│  │  ├── Nettoyage fichiers temporaires                      │  │
│  │  └── Impression fichiers (print-file IPC)                │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                        │
│  ┌───────────────────────▼───────────────────────┐             │
│  │  CADDY SERVER (127.0.0.1:8000)                │             │
│  │  • Reverse proxy → 127.0.0.1:8001 (PHP)      │             │
│  │  • Serveur fichiers statiques                 │             │
│  │  • Gestion CORS (dev mode)                    │             │
│  └───────────────────────────────────────────────┘             │
│                          │                                        │
│                          ▼                                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  PHP BUILTIN SERVER (127.0.0.1:8001)                     │  │
│  │  • Point d'entrée : app/public/index.php                  │  │
│  │  • Routage vers app/api/*.php                             │  │
│  │  • Sessions PHP (fichiers tmp)                             │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼ (HTTP/API)
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND PHP (MVC-like)                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  API Layer   │──│ Controllers │──│  Models      │          │
│  │  (app/api/)  │  │/functions/  │  │ (app/models/│          │
│  │ • tirage.php │  │ • pricing   │  │  • Imposi   │          │
│  │ • imposi.php │  │ • consomma  │  │  • SpoolMgr │          │
│  │ • image_*.php│  │ • utilities │  │  • Biblioth │          │
│  └──────────────┘  └──────────────┘  └──────────────┘          │
│                                                                  │
│  Base de données SQLite (dupli.db) + support MySQL             │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼ (template rendering)
┌─────────────────────────────────────────────────────────────────┐
│              FRONTEND (Renderer Process)                        │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  preload.js (contextBridge)                               │  │
│  │  • electronAPI exposé au renderer                         │  │
│  │  • Validation paramètres                                  │  │
│  └───────────────────────────────────────────────────────────┘  │
│                          │                                        │
│  ┌───────────────────────▼───────────────────────┐             │
│  │  app/public/ (web root)                        │             │
│  │  • index.php (bootstrap)                       │             │
│  │  • css/ styles.css                             │             │
│  │  • js/ app.js (frontend logic)                 │             │
│  │  • images/ icones                              │             │
│  └────────────────────────────────────────────────┘             │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼ (IPC & natifs)
┌─────────────────────────────────────────────────────────────────┐
│         MODULES NATIFS (Node.js addons C++)                      │
│  ┌──────────────────────┐         ┌─────────────────────────┐  │
│  │ print-engine/        │         │ print-processor/        │  │
│  │ • win32-printer.js   │◄───────►│ • DupliPrintProcessor.cpp│ │
│  │ • cups-printer.js    │         │   (traitement spool)    │  │
│  │                     │         └─────────────────────────┘  │
│  │ • platform abstraction                                      │
│  └──────────────────────┘                                      │
│  • getPrinters()                                               │
│  • getPrinterCapabilities()                                    │
│  • printJob(pdfPath, options)                                  │
│  • startPrinterMonitor(callback)                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## Dossier structure détaillée

### 1. Racine du projet

```
dupli-electron-caddy/
├── main-caddy.js          # Processus principal (Caddy + PHP dual)
├── main.js                # Processus alternatif (PHP seul)
├── preload.js             # API sécurisée (contextBridge)
├── package.json           # Config Node + scripts
├── Caddyfile              # Configuration Caddy
├── electron-builder-caddy.yml  # Config build electron-builder
├── electron-builder-beta.yml   # Config build beta
├── deploy-script.sh       # Script déploiement automatique
├── generate_blank_pdfs.js # Génération PDF vierges (A3, A4)
├── check-db.js            # Vérification intégrité DB
├── blank_*.pdf            # PDF templates vierges
└── api_response.json      # Exemple réponses API (debug)
```

### 2. Backend PHP (`app/`)

```
app/
├── composer.json          # Dépendances PHP (FPDI, FPDF, pdfparser)
├── php.ini                # Configuration PHP (upload_max_filesize=50M)
├── conf.php               # Configuration DB + chemins
├── controler/             # Contrôleurs + fonctions utilitaires
│   ├── functions/
│   │   ├── pricing.php           # Calcul prix machines/papier
│   │   ├── consommation.php      # Suivi consommables (masters/encre)
│   │   ├── utilities.php         # Fonctions génériques (template, path)
│   │   ├── i18n.php              # Internationalisation
│   │   ├── database.php          # Gestion DB (PDO, pdo_connect)
│   │   ├── machines.php          # Logique machines duplicopieurs
│   │   ├── stats.php             # Calculs statistiques
│   │   ├── news.php              # Gestion news internes
│   │   └── health_check.php      # Vérification santé système
│   └── BibliothequeManager.php    # Gestionnaire bibliothèque fichiers
├── models/                # Classes métier (OOP)
│   ├── Imposition.php           # Algorithme imposition N-up
│   ├── ImpositionProcessor.php   # Helper imposition (A5/A6 traits)
│   ├── CropMarks.php             # Dessin traits de coupe
│   ├── SpoolManager.php          # Gestion spool Windows (SPL/SHD)
│   ├── pdf_to_png.php            # Conversion PDF → PNG (Ghostscript)
│   ├── png_to_pdf.php            # Conversion PNG → PDF (TCPDF)
│   ├── image_processor.php       # Traitement images complet
│   ├── riso_separator.php        # Séparateur couleurs Riso
│   ├── unimpose.php              # Désimposition PDF (livret → pages)
│   └── unimpose_logic.php        # Algorithme désimposition
├── api/                   # Endpoints REST (frontend → backend)
│   └── [大量 fichiers PHP]        # ~39 endpoints (tirage, imposi, etc.)
├── view/                  # Templates PHP (HTML + JS inline)
│   ├── *.html.php                  # Pages principales
│   ├── admin.*.html.php            # Interface administration
│   ├── components/                 # Composants réutilisables
│   └── partials/                   # Partials (header, footer)
├── public/                # Racine web (symlink vers app/)
│   ├── index.php                  # Front controller
│   ├── tmp/                       # Fichiers temporaires (dev)
│   ├── css/
│   ├── js/
│   └── images/
└── vendor/                # Composer dependencies (FPDI, etc.)
```

### 3. Modules natifs (`src/`)

```
src/
├── print-engine/          # Moteur d'impression (Node.js + C++)
│   ├── index.js               # Façade unifiée (détection plateforme)
│   ├── windows/
│   │   ├── win32-printer.js   # Wrapper JS → addon natif .node
│   │   ├── printer-monitor.js # Surveillance spooler Windows
│   │   └── build/Release/     # win32-printer.node (compilé)
│   └── linux/
│       └── cups-printer.js    # Implémentation CUPS/IPP
│
└── print-processor/       # Processeur d'impression C++ natif
    └── DupliPrintProcessor.cpp # Traitement spool PCL/RAW
```

**Note :** Le module natif Windows (`win32-printer.node`) est un addon C++ compilé avec node-gyp, qui accède aux API Windows :
- `GetPrinter()` / `EnumPrinters()` — Énumération imprimantes
- `OpenPrinter()` / `ClosePrinter()` — Gestion handles
- `GetPrinterData()` — Récupération capacités (DEVMODE)
- `StartDocPrinter()` / `WritePrinter()` — Envoi jobs RAW
- Surveillance spooler via `FindFirstPrinterChangeNotification()`

### 4. Utilitaires (`utils/`)

```
utils/
├── printer-monitor.js     # Moniteur jobs Windows (POLL spooler)
├── spool-analyzer.js      # Analyse fichiers SPL (PCL/RAW)
└── printer-monitor.ps1    # Script PowerShell config
```

### 5. Binaires embarqués

```
caddy/                   # Serveur Caddy (binaire)
php/                     # PHP portable (Windows builds)
ghostscript/             # Binaires GS (Linux, Windows, macOS)
imagemagick/             # Binaires ImageMagick (convert, mogrify)
```

---

## Flux d'exécution détaillé

### Démarrage application (main-caddy.js)

1. **Initialisation Electron**
   ```js
   app.whenReady().then(createWindow)
   ```

2. **Nettoyage temporaires** (`cleanupTmpFiles()`)
   - Supprime vieux fichiers dans `app/public/tmp/`
   - Détermine chemin selon AppImage/Windows/dev

3. **Création fenêtre**
   - `BrowserWindow` avec webPreferences isolées
   - `preload.js` injecté via `contextBridge`
   - Icône résolue dynamiquement (multiple fallbacks)

4. **Démarrage serveur PHP intégré**
   ```js
   spawn('php', [
     '-c', phpIniPath,
     '-S', '127.0.0.1:8001',
     '-t', appPath
   ])
   ```
   - Variables env : `PHP_CLI_SERVER_WORKERS=1`, `PHP_CLI_SERVER_LOG_LEVEL=0`
   - Windows : lancé via `cmd.exe` pour redirection NUL (silence)

5. **Chargement interface**
   - `setTimeout(2000)` → attendre PHP ready
   - `mainWindow.loadURL('http://127.0.0.1:8000/')`
   - `mainWindow.show()`

6. **Menu & mise à jour**
   - Menu personnalisé (Application, Affichage)
   - `autoUpdater.checkForUpdatesAndNotify()`

7. **Gestion erreurs**
   - `did-fail-load` → page erreur avec bouton reload
   - `uncaughtException` / `unhandledRejection` → log console

### Cycle de vie

```
APP START
   ├─ cleanupTmpFiles()
   ├─ startPhpServer()
   ├─ createWindow() → loadURL http://127.0.0.1:8000/
   ├─ createMenu()
   ├─ autoUpdater.checkForUpdates()
   └─ READY

USER INTERACTION
   ├─ IPC via electronAPI (preload → main)
   │   ├─ print-file
   │   ├─ open-file
   │   ├─ restart-php
   │   ├─ check-admin / restart-as-admin
   │   └─ Printer monitor controls
   │
   ├─ Frontend (JS) → API PHP (HTTP)
   │   ├─ GET  /api/tirage.php
   │   ├─ POST /api/imposition.php
   │   ├─ POST /api/image_processor.php
   │   └─ ...
   │
   └─ Backend PHP
       ├─ Lecture DB (pdo_connect)
       ├─ Appels Ghostscript/exec()
       ├─ Génération PDF (TCPDF/FPDI)
       └─ Rendu template HTML

APP CLOSE
   ├─ cleanupTmpFiles()
   ├─ stopPhpServer()
   └─ app.quit()
```

---

## Communication entre modules

### 1. Electron IPC (Main ↔ Renderer)

**Preload (exposed API) :**
```js
contextBridge.exposeInMainWorld('electronAPI', {
  // Fichiers
  openFile: (path) => ipcRenderer.invoke('open-file', path),
  openExternalFile: (url) => ipcRenderer.invoke('open-external-file', url),

  // Impression
  printFile: (url, options) => ipcRenderer.invoke('print-file', url, options),

  // Mise à jour
  checkForUpdates: () => ipcRenderer.invoke('check-for-updates'),
  downloadUpdate: () => ipcRenderer.invoke('download-update'),
  installUpdate: () => ipcRenderer.invoke('install-update'),

  // Supervision PHP
  onPhpLog: (cb) => ipcRenderer.on('php-log', (e, p) => cb(p)),
  onPhpFatal: (cb) => ipcRenderer.on('php-fatal', (e, p) => cb(p)),
  restartPhp: () => ipcRenderer.invoke('restart-php'),

  // Moniteur imprimantes
  getPrinters: () => ipcRenderer.invoke('get-printers'),
  getPrinterCapabilities: (name) => ipcRenderer.invoke('get-printer-capabilities', name),
  startPrinterMonitor: (start) => ipcRenderer.invoke('toggle-printer-monitor', start),
  onPrintJobDetected: (cb) => ipcRenderer.on('print-job-detected', (e, p) => cb(p)),

  // Admin
  checkAdminStatus: () => ipcRenderer.invoke('check-admin-status'),
  restartAsAdmin: () => ipcRenderer.invoke('restart-as-admin')
})
```

**Main (handlers) :**
```js
// main-caddy.js
ipcMain.handle('print-file', async (event, fileUrl) => {
  // Créer BrowserWindow caché → charger PDF → webContents.print()
})

ipcMain.handle('open-file', async (event, filePath) => {
  // shell.openPath(fullPath)
})

ipcMain.handle('restart-php', async () => {
  stopPhpServer()
  startPhpServer()
})
```

### 2. Frontend → Backend PHP (HTTP)

Le frontend (dans `app/public/`) utilise des requêtes AJAX classiques vers les endpoints PHP :

```js
// Exemples dans app.js (si existe)
fetch('?imposition', { method: 'POST', body: formData })
fetch('?image_processor', { method: 'POST', body: formData })
fetch('?pdf_to_png', { method: 'POST' })
```

**Routage :** Le point d'entrée `app/public/index.php` route vers les vues selon paramètres GET/POST.

### 3. Impression natif (Windows)

```
Renderer (JS)
   │
   ▼ electronAPI.printJob(pdfPath, options)
   │
   ▼ IPC → main-caddy.js (ipcMain.handle)
   │
   ▼ print-engine/windows/win32-printer.js
   │   ├── loadNativeAddon() → require('./build/Release/win32-printer.node')
   │   └── printJob(pdfPath, options)
   │        └── addon.printJob(printerName, pdfPath, copies, duplex, …)
   │
   ▼ Addon C++ (win32-printer.node)
   │   ├── OpenPrinter()
   │   ├── StartDocPrinter()
   │   ├── WritePrinter() (envoi RAW PDF)
   │   └── ClosePrinter()
   │
   ▼ Pilote imprimante Windows → Spooler
        └── Fichier temporaire SPL créé
```

### 4. Impression SumatraPDF (Windows fallback)

Si l'addon natif n'est pas disponible, l'app utilise **SumatraPDF.exe** en ligne de commande :
```
SumatraPDF.exe -print-to "HP_LaserJet" -print-settings "4x,paper=A4,portrait,simplex,color" -silent "file.pdf"
```

Avantage : gestion native des copies multiples sans boucle Electron.

### 5. Impression Linux (CUPS)

```
print-engine/linux/cups-printer.js
   ├── checkCupsAvailable() → which lpstat, lp
   ├── getPrinters() → lpstat -p / lpstat -d
   ├── getPrinterCapabilities() → lpoptions -l
   └── printJob() → lp -d printer -n copies -o media=A4 …
```

---

## Sécurité & isolation

### Sandbox Electron

- **contextIsolation : true** (preload.js)
- **nodeIntegration : false** dans BrowserWindow
- **sandbox : false** (désactivé pour compatibilité PHP, mais isolation maintenue via IPC)

### Validation des entrées

- **preload.js** : Tous les appels `ipcRenderer.invoke()` passent par le main process
- **main-caddy.js** : Validation des chemins (`path.join`, `shell.openPath` avec vérification)
- **PHP** : `htmlentities()` sur POST, vérification MIME upload, taille max 50MB

### Uploads sécurisés

```
app/controler/functions/*.php
  ├── Vérification $_FILES["error"] === UPLOAD_ERR_OK
  ├── finfo_file() → MIME type (application/pdf, image/*)
  ├── Taille max : 50 MB
  ├── Dossier temporaire : sys_get_temp_dir() + "duplicator_*"
  ├── Nettoyage nom : preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)
  └── .htaccess protection (dossier uploads)
```

---

## Configuration & environnement

### Variables d'environnement reconnues

| Variable | Usage | Défaut |
|----------|-------|--------|
| `APPIMAGE` | Détection mode AppImage | (auto) |
| `DUPLICATOR_DB_PATH` | Chemin DB SQLite | `~/.config/Duplicator/dupli.db` |
| `DUPLICATOR_TEMP_DIR` | Dossier temporaire | `/tmp/duplicator/` |
| `NODE_ENV` | Mode dev/prod | `production` |
| `ELECTRON_DISABLE_SECURITY_WARNINGS` | Désactive warnings (dev) | false |

### Fichiers de configuration

**`app/conf.php`** — Centralise :
```php
$conf = [
  'db_type' => 'sqlite',           // sqlite | mysql
  'db_path' => '/path/to/dupli.db', // SQLite
  'dsn' => 'sqlite:/path/to/dupli.db',
  'login' => '',                    // MySQL user
  'pass' => '',                     // MySQL pass
  'site_name' => 'Duplicator',
  // …
];
```

**`Caddyfile`** — Configuration reverse proxy :
```
:8000 {
  root * /home/.../dupli-electron-caddy/app/public
  php_fastcgi 127.0.0.1:8001
  file_server
}
```

---

## Extensibilité & plugins

L'application prévoit une architecture modulaire :

- **Nouvelles machines** : Ajout en base (`duplicopieurs` table) + config prix
- **Nouveaux formats imposition** : Extension `Imposition.php` (méthodes `processA5Side`, `processA6Side`)
- **Nouveaux traitements image** : Ajout fonction dans `image_processor.php`
- **API personnalisées** : Créer fichier dans `app/api/` + routage frontend

---

## Points d'attention (Production)

⚠️ **AppImage read-only** :  
Le filesystem AppImage est en lecture seule. Toutes les écritures (DB, tmp, uploads) doivent absolument utiliser :
- `/tmp` (via `sys_get_temp_dir()`)
- `~/.config/Duplicator/` (dossier user)

⚠️ **Timeouts PHP** :  
Les conversions PDF longues risquent le timeout (30s par défaut).  
Solutions :
- `set_time_limit(600)` dans les endpoints concernés
- Traitement asynchrone avec `fastcgi_finish_request()` + polling
- Augmenter `max_execution_time` dans `php.ini`

⚠️ **Mémoire ImageMagick** :  
`image_processor.php` charge des images 300 DPI entières en mémoire.  
Limiter dimensions ou utiliser `Imagick::scaleImage()` pour réduire empreinte.

⚠️ **Mises à jour auto** :  
`electron-updater` nécessite un serveur de releases (GitHub Releases ou auto-hébergé).  
Configuration dans `main-caddy.js` (fournisseur `generic` ou `github`).

---

*Fin du documentarchitecture — Suite : 03-LOGIQUE_METIER.md*
