# Flux & Communications - Duplicator

> **Scope :** Canaux de communication, protocoles, formats de données  
> **Fichiers clés :** `main-caddy.js`, `preload.js`, `src/print-engine/`, `utils/printer-monitor.js`

---

## Table des matières

1. [Vue d'ensemble des flux](#1-vue-densemble-des-flux)
2. [Electron IPC (Main ↔ Renderer)](#2-electron-ipc-main--renderer)
3. [Frontend → Backend PHP (HTTP REST)](#3-frontend--backend-php-http-rest)
4. [Communication impression native](#4-communication-impression-native)
5. [Surveillance spooler Windows](#5-surveillance-spooler-windows)
6. [Formats de données échangés](#6-formats-de-données-échangés)
7. [Timing & timeouts](#7-timing--timeouts)
8. [Gestion erreurs & reprise](#8-gestion-erreurs--reprise)

---

## 1. Vue d'ensemble des flux

```
┌────────────┐
│  Renderer  │  (Chromium - Frontend)
│  (JS/HTML) │
└─────┬──────┘
      │ electronAPI (contextBridge)
      ├─────────────────────────────────────────────┐
      │  IPC MAIN (ipcRenderer.invoke / on)        │
      ▼                                             ▼
┌─────────────────┐                       ┌──────────────────────┐
│ Electron Main   │                       │  Backend PHP         │
│ (main-caddy.js) │                       │  (127.0.0.1:8001)    │
├─────────────────┤                       ├──────────────────────┤
│ • print-file    │                       │ • api/*.php          │
│ • open-file     │                       │ • controllers/       │
│ • restart-php   │                       │ • models/            │
│ • update-*      │                       │ • DB (SQLite)        │
│ • printer-*     │                       └──────────┬───────────┘
└────────┬────────┘                                  │ HTTP
         │                                           │ GET/POST
         │ exec/spawn                                ▼
         │                                   ┌──────────────────┐
         ├───────────────────────────────────│   Caddy (8000)   │
         │                                   │  reverse proxy   │
         ▼                                   └──────────────────┘
┌─────────────────┐
│  Node.js        │
│  print-engine   │◄──── Native addon C++ (Windows .node)
│  (win32/linux)  │
└─────────────────┘
```

---

## 2. Electron IPC (Main ↔ Renderer)

### 2.1 Preload : API exposée

**Fichier :** `preload.js`

```js
contextBridge.exposeInMainWorld('electronAPI', {
  // ── Fichiers ─────────────────────────────────────
  openFile: (filePath) => ipcRenderer.invoke('open-file', filePath),
  cleanupTmpFiles: () => ipcRenderer.invoke('cleanup-tmp-files'),
  showOpenDialog: (options) => ipcRenderer.invoke('show-open-dialog', options),

  openExternalFile: (fileUrl) => ipcRenderer.invoke('open-external-file', fileUrl),

  // ── Mise à jour ──────────────────────────────────
  checkForUpdates: () => ipcRenderer.invoke('check-for-updates'),
  downloadUpdate: () => ipcRenderer.invoke('download-update'),
  installUpdate: () => ipcRenderer.invoke('install-update'),
  getAppVersion: () => ipcRenderer.invoke('get-app-version'),

  onUpdateAvailable: (cb) => ipcRenderer.on('update-available', (e, info) => cb(info)),
  onDownloadProgress: (cb) => ipcRenderer.on('download-progress', (e, p) => cb(p)),
  onUpdateDownloaded: (cb) => ipcRenderer.on('update-downloaded', (e, i) => cb(i)),

  // ── Supervision PHP ─────────────────────────────
  onPhpLog: (cb) => ipcRenderer.on('php-log', (e, p) => cb(p)),
  onPhpFatal: (cb) => ipcRenderer.on('php-fatal', (e, p) => cb(p)),
  onPhpStatus: (cb) => ipcRenderer.on('php-process-status', (e, p) => cb(p)),
  restartPhp: () => ipcRenderer.invoke('restart-php'),
  restartApp: () => ipcRenderer.invoke('restart-app'),

  // ── Moniteur imprimantes (Windows) ──────────────
  getPrinters: () => ipcRenderer.invoke('get-printers'),
  togglePrinterMonitor: (start) => ipcRenderer.invoke('toggle-printer-monitor', start),
  getPrinterMonitorStatus: () => ipcRenderer.invoke('get-printer-monitor-status'),
  deletePrinter: (name) => ipcRenderer.invoke('delete-printer', name),
  deletePrintJob: (printer, jobId) => ipcRenderer.invoke('delete-print-job', printer, jobId),
  reanalyzePrintJob: (jobId) => ipcRenderer.invoke('reanalyze-print-job', jobId),
  onPrintJobDetected: (cb) => ipcRenderer.on('print-job-detected', (e, p) => cb(p)),
  onPrintMonitorError: (cb) => ipcRenderer.on('print-monitor-error', (e, p) => cb(p)),
  onConsoleLog: (cb) => ipcRenderer.on('console-log', (e, p) => cb(p)),

  // ── Impression module ───────────────────────────
  getPrinterCapabilities: (name) => ipcRenderer.invoke('get-printer-capabilities', name),
  printJob: (pdfPath, options) => ipcRenderer.invoke('print-job', pdfPath, options),

  // ── Impression fichiers directe ──────────────────
  printFile: (fileUrl, options) => ipcRenderer.invoke('print-file', fileUrl, options),

  // ── Droits admin ────────────────────────────────
  checkAdminStatus: () => ipcRenderer.invoke('check-admin-status'),
  restartAsAdmin: () => ipcRenderer.invoke('restart-as-admin')
});
```

### 2.2 Main : Handlers IPC

**Fichier :** `main-caddy.js`

```js
// ── Impression fichier (popup dialogue système) ─────
ipcMain.handle('print-file', async (event, fileUrl) => {
  const printWindow = new BrowserWindow({ show: false, webPreferences: { sandbox: true } });
  printWindow.loadURL(fileUrl);
  printWindow.webContents.on('did-finish-load', () => {
    printWindow.webContents.print({ silent: false, printBackground: true }, (success) => {
      printWindow.close();
      return { success };
    });
  });
});

// ── Ouverture fichier externe ───────────────────────
ipcMain.handle('open-file', async (event, filePath) => {
  const fullPath = resolveAppPath(filePath); // gestion AppImage/Windows
  await shell.openPath(fullPath);
  return { success: true };
});

// ── restart-php ─────────────────────────────────────
ipcMain.handle('restart-php', async () => {
  stopPhpServer();
  await new Promise(r => setTimeout(r, 500)); // petite attente
  startPhpServer();
  return { success: true };
});

// ── get-printers (via module print-engine) ──────────
ipcMain.handle('get-printers', async () => {
  const printModule = require('./src/print-engine');
  return await printModule.getPrinters();
});

// ── print-job (impression native) ───────────────────
ipcMain.handle('print-job', async (event, pdfPath, options) => {
  const printModule = require('./src/print-engine');
  return await printModule.printJob(pdfPath, options);
});

// ── Moniteur imprimantes ───────────────────────────
ipcMain.handle('toggle-printer-monitor', async (event, start) => {
  const monitor = require('./utils/printer-monitor');
  return start ? monitor.start() : monitor.stop();
});
```

**Événements asynchrones (emit) :**
```js
// Émis depuis modules natifs (callbacks C++ → JS)
mainWindow.webContents.send('print-job-detected', jobData);
mainWindow.webContents.send('php-log', { message: '...' });
```

---

## 3. Frontend → Backend PHP (HTTP REST)

### 3.1 Point d'entrée

```
http://127.0.0.1:8000/  →  app/public/index.php
```

`index.php` route selon paramètres GET :

| Param | Page | Fichier inclus |
|-------|------|----------------|
| `imposition` | Imposition PDF | `app/view/imposition.html.php` |
| `pdf_to_png` | PDF → PNG | `app/models/pdf_to_png.php` |
| `png_to_pdf` | PNG → PDF | `app/models/png_to_pdf.php` |
| `image_processor` | Traitement image | `app/models/image_processor.php` |
| `unimpose` | Désimposition | `app/models/unimpose.php` |
| `tirage` | Gestion tirages | `app/view/tirage.html.php` |
| `admin*` | Admin | `app/view/admin.*.html.php` |
| (null) | Accueil | `app/view/accueil.html.php` |

### 3.2 Protocole POST (form-data)

Les formulaires utilisent `enctype="multipart/form-data"` :

```html
<form action="?imposition" method="POST" enctype="multipart/form-data">
  <input type="file" name="pdf">
  <input type="hidden" name="lib_file_id" value="123"> <!-- optionnel -->
  <select name="format">A3/A4</select>
  <select name="imposition_mode">brochure/livre</select>
  <input type="number" name="bleed_size" value="3">
  <button type="submit">Générer</button>
</form>
```

**Flux serveur :**

```php
function Action($conf) {
  $errors = []; $success = false;

  // 1. Détection source (bibliothèque vs upload direct)
  if (isset($_POST['lib_file_id'])) {
    $file = BibliothequeManager::getFile($_POST['lib_file_id']);
    $source = $file['filepath']; // pas d'upload
  } elseif (isset($_FILES['pdf'])) {
    move_uploaded_file($_FILES['pdf']['tmp_name'], $uploadFile);
    $source = $uploadFile;
  }

  // 2. Traitement long (ex: imposition)
  set_time_limit(600);
  $outputFile = '/tmp/duplicator_' . uniqid() . '.pdf';
  $imposer = new Imposition($source, $settings);
  $imposer->process($outputFile, $previewOutput);

  // 3. Nettoyage temporaires
  unlink($source); // si upload

  // 4. Retour template
  return template('../view/imposition.html.php', [
    'success' => true,
    'download_url' => '?download&file=' . basename($outputFile)
  ]);
}
```

### 3.3 Téléchargement fichiers temporaires

Les fichiers générés sont servis via route dédiée :

```
GET /?download_pdf&file=output.pdf&dir=tmp_subdir
→ app/public/index.php case 'download_pdf':
   readfile(sys_get_temp_dir() . '/duplicator_' . $dir . '/' . $file)
   header('Content-Type: application/pdf')
```

Protection : le fichier doit exister dans le sous-dossier temporaire attendu (pas de path traversal).

---

## 4. Communication impression native

### 4.1 Architecture print-engine

```
renderer JS (frontend)
   │
   ├─→ electronAPI.printJob(pdfPath, options)
   │        │
   ▼        ▼
preload (invoke) → main-caddy (ipcMain.handle)
                          │
                          ▼
                require('./src/print-engine')
                          │
                  Détection plateforme
                  ├─ Windows → require('./windows/win32-printer')
                  └─ Linux   → require('./linux/cups-printer')
                          │
                          ▼
                  Platform specific implementation
```

### 4.2 Windows (win32-printer.js)

**Chargement addon natif :**
```js
const addonPath = path.join(__dirname, 'build', 'Release', 'win32-printer.node');
nativeAddon = require(addonPath); // C++ addon
// Fallback compilation: node-gyp rebuild
```

**API exposée par l'addon C++ :**
```cpp
// win32-printer.node (C++)
Napi::Value getPrinters(const Napi::CallbackInfo& info) {
  // EnumPrinters() Windows API → retourne tableau d'objets { name, displayName, status, isDefault }
}

Napi::Value getPrinterCapabilities(const Napi::CallbackInfo& info) {
  // OpenPrinter() + GetPrinter() + DEVMODE
  // Retourne : { inputSlots[], pageSizes[], duplex, color, colorModes[], resolutions[] }
}

Napi::Value printJob(const Napi::CallbackInfo& info) {
  // OpenPrinter() + StartDocPrinter() + WritePrinter() (RAW PDF bytes)
  // Retourne : { jobId, success }
}

Napi::Value startPrinterMonitor(const Napi::CallbackInfo& info) {
  // FindFirstPrinterChangeNotification() + callback JS
  // Détecte jobs ajoutés/supprimés
}
```

**Impression directe (RAW) :**
- Le PDF est lu en binaire (`fs.readFileSync(pdfPath)`)
- `WritePrinter(hPrinter, pdfBuffer, bufferSize)` envoie flux brut au driver
- Le driver Windows le convertit en PCL/PS selon capacités imprimante

### 4.3 Linux (CUPS)

**Détection :** `which lpstat`, `which lp`

**Liste imprimantes :**
```bash
lpstat -p 2>/dev/null    # printer HP_LaserJet is idle...
lpstat -d 2>/dev/null    # system default destination: HP_LaserJet
```

**Capacités :**
```bash
lpoptions -p "HP_LaserJet" -l
# Output:
# InputSlot/Auto: *Auto SheetBypass Tray1 Tray2
# PageSize/Media: *A4 A3 Letter Legal
# Duplex/Duplex: *True False
# ColorModel/Color: *RGB CMYK Gray
```

**Impression :**
```bash
lp -d "HP_LaserJet" -n 2 -o media=A4 -o sides=two-sided-long-edge input.pdf
# Retour: "request id is HP_LaserJet-123 (1 file(s))"
```

`printJob()` parse `stdout` pour extraire job ID (`/request id is (\S+)/`).

### 4.4 Fallback SumatraPDF (Windows)

Si addon natif indisponible, l'app utilise **SumatraPDF.exe** :

```js
const args = [
  '-print-to', printerName,
  '-print-settings', '2x,paper=A4,portrait,duplexlong,color',
  '-silent',
  pdfPath
];
spawn(sumatraPath, args);
```

Avantages :
- Gère copies natives sans boucle
- Supporte tous formats papier
- Pas de popup UI (silent)
- Code retour 0 = succès

### 4.5 Format options impression

Les objets `options` passés aux moteurs d'impression :

```typescript
interface PrintOptions {
  printer: string;           // Nom imprimante (obligatoire)
  copies?: number;           // Copies (défaut 1)
  paperSize?: string;        // A2, A3, A4, A5, Letter, Legal…
  orientation?: 'portrait' | 'landscape';
  duplex?: 'simplex' | 'duplexno tumble' | 'duplextumble';
  colorMode?: 'color' | 'monochrome';
  scaling?: 'fit' | 'shrink' | 'noscale';
  paperTray?: string;        // Bac papier (ex: "Tray2")
  pageSubset?: 'all' | 'odd' | 'even';
  pageRange?: string;        // "1-5,8,10-12"
  resolution?: string;       // "300dpi", "600dpi"
}
```

**Conversion vers Sumatra :**
```
copies > 1        → "4x" (dans print-settings)
paperSize A4      → "paper=A4"
duplex duplexlong → "duplexlong"
colorMode color   → "color"
```

**Conversion vers CUPS :**
```
-n 2              → copies
-o media=A4       → format
-o sides=two-sided-long-edge → duplex
-o print-color-mode=monochrome → noir&blanc
```

---

## 5. Surveillance spooler Windows

### 5.1 Architecture printer-monitor

```
utils/printer-monitor.js (Node.js)
   │
   ▼ require('./windows/printer-monitor.js')
        │
        ▼ Addon C++ (win32-printer.node)
             └─ FindFirstPrinterChangeNotification()
                 ├─ WaitForSingleObject(event)
                 ├─ EnumerateJobs() → liste jobs
                 └─ Callback JS avec job data
```

### 5.2 Chargement (depuis main-caddy)

```js
const monitor = require('./utils/printer-monitor');

monitor.start((job) => {
  // job = { printerName, jobId, document, userName, status, totalPages, ... }
  mainWindow.webContents.send('print-job-detected', job);
});
```

**Callback reçoit** un objet job pour chaque changement :
```json
{
  "printerName": "HP_LaserJet_4200",
  "jobId": 105,
  "document": "document.pdf",
  "user": "Babar",
  "status": "Printing",
  "totalPages": 12,
  "pagesPrinted": 3,
  "bytes": 245760,
  "pSubmitted": "2026-04-08T09:30:00Z"
}
```

### 5.3 Analyse SPL (spool-analyzer.js)

Lorsqu'un job est détecté :
1. `SpoolManager.findSpoolFile(jobId)` localise le fichier `.SPL`
2. Lecture binaire → extraction en-tête PCL/RAW
3. Calcul `fillRate` (pourcentage couverture noir) :
   - Si PCL : parse données raster
   - Si RAW : suppose 100% (PDF direct)
4. Génère miniature (via Ghostscript si PDF dans spool)
5. Envoie événement `print-job-detected` avec métadonnées enrichies

---

## 6. Formats de données échangés

### 6.1 IPC (JSON)

**Request → Main :**
```json
{
  "channel": "print-file",
  "args": ["file:///tmp/doc.pdf", { "printer": "HP", "copies": 2 }]
}
```

**Response ← Main :**
```json
{
  "success": true,
  "jobId": 123456,
  "message": "Impression envoyée (2 copies)",
  "engine": "SumatraPDF"
}
```

**Events ← Main (webContents.send) :**
```json
{
  "type": "print-job-detected",
  "payload": { "jobId": 105, "printer": "HP", "document": "file.pdf" }
}
```

### 6.2 PHP → Frontend (template variables)

Les fonctions `template($file, $variables)` injectent variables dans vue :

```php
return template("../view/imposition.html.php", [
  'errors' => $errors,           // array<string>
  'success' => $success,         // bool
  'result' => $result,           // string (filename) ou array
  'download_url' => $url,        // string
  'progress_key' => $key,        // string (async polling)
  'from_lib_file' => $file       // array|null (metadata bibliothèque)
]);
```

### 6.3 Progress polling (async)

Pour traitements longs :

1. POST déclenche traitement → créé `progress_key` (uniqid)
2. Écrit immédiat `{"status":"processing","current":0,"total":100,"message":"…"}` dans :
   ```
   sys_get_temp_dir() + '/duplicator_image_processor_progress_' + progress_key + '.json'
   ```
3. Réponse HTTP immédiate (avec `fastcgi_finish_request()`) → frontend reçoit `{ progress_key }`
4. Frontend poll chaque 500–1000ms : `GET ?progress_key=abc123`
5. Fichier JSON mis à jour par le processus PHP en arrière-plan
6. Quand `status = "completed"` → frontend arrête poll et affiche download_url

**Exemple progression :**
```json
{ "status":"processing", "current":25, "total":100, "message":"Conversion PDF→images..." }
{ "status":"processing", "current":90, "total":100, "message":"Traitement page 5/6..." }
{ "status":"completed", "current":100, "total":100, "message":"Terminé", "download_url":"?download..." }
```

---

## 7. Timing & timeouts

### 7.1 Timeouts PHP

- **Par défaut** : 30s (`max_execution_time` php.ini)
- **Traitements longs** (imposition, image_processor) : `set_time_limit(600)` (10 min)
- **Progress polling** : limité à `set_time_limit(5)` (lecture fichier JSON)

### 7.2 Délais démarrage PHP

`main-caddy.js` attend 2 secondes avant de charger `http://127.0.0.1:8000/` :
```js
setTimeout(() => {
  mainWindow.loadURL('http://127.0.0.1:8000/');
  mainWindow.show();
}, 2000);
```

Permet au serveur PHP de s'initialiser (surtout Windows où PHP startup peut être lent).

### 7.3 Polling moniteur imprimantes

`printer-monitor.js` (Node) interroge spooler Windows toutes les **2 secondes** :
```js
setInterval(() => {
  enumerateJobs();
}, 2000);
```

---

## 8. Gestion erreurs & reprise

### 8.1 Erreurs PHP (backend)

- **Try/catch** généralisé dans tous les `Action()` des modèles
- **error_log()** systématique avec `error_log("Erreur … : " . $e->getMessage())`
- **Retour utilisateur** : messages `$errors[]` affichés dans template
- **Trace complète** en log développement (`$e->getTraceAsString()`)

### 8.2 Erreurs IPC (Electron Main)

```js
ipcMain.handle('print-file', async (event, fileUrl) => {
  try {
    // …
    return { success: true };
  } catch (error) {
    console.error('Erreur impression:', error);
    return { success: false, error: error.message };
  }
});
```

Renderer reçoit objet `{ success: false, error: "…"}`, affiche message.

### 8.3恢复 after crash

- **PHP** : crashes → redémarrage automatique via `restartPhp()` (bouton ou watchdog frontend)
- **Electron** : redémarrage manuel appli
- **Spool jobs** : jobs orphelins → `SpoolManager::deleteSpoolFiles()` (admin)

### 8.4 Nettoyage temporaires

**Au démarrage :** `cleanupTmpFiles()` supprime `app/public/tmp/*`

**À la fermeture :** App quod → `cleanupTmpFiles()` + `stopPhpServer()`

**After each upload :** `unlink($uploadFile)` immédiatement après traitement

**Temp système (Linux/AppImage) :** `/tmp/duplicator_*` → purgé au reboot

---

## 9. Sécurité communications

### 9.1 Isolation contextBridge

- `preload.js` est le **seul** pont entre renderer et main
- `contextBridge.exposeInMainWorld('electronAPI', {…})` expose uniquement API whitelistée
- Aucun `require()` ou `process` accessible depuis renderer

### 9.2 Validation chemins

Tout appel `openFile($path)` / `printFile($url)` passe par résolution :

```js
function resolveAppPath(filePath) {
  const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
  if (isAppImage) {
    return path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public', filePath);
  }
  return path.join(__dirname, 'app', 'public', filePath);
}
```

Évite path traversal (pas de `../` sortant du dossier app).

### 9.3 Authentification sessions PHP

Sessions classiques PHP (`session_start()`) stockées en fichiers tmp :

- Cookie PHPSESSID ←→ fichier `sess_<id>`
- Pas de chiffrement (mode hors-ligne assume environnement de confiance)
- Production : recommandé HTTPS + cookies Secure/HttpOnly

### 9.4 Upload restrictions

- MIME check via `finfo_file()`
- Extension `.htaccess` dans dossiers uploads pour bloquer exécution
- Taille max 50 MB (`upload_max_filesize` dans php.ini)

---

## 10. Schémas séquentiels

### 10.1 Séquence imposition complète

```
Frontend (renderer)
   │
   ├─ formulaire POST multipart (PDF + options)
   │
   ▼ HTTP POST ?imposition
Backend PHP (imposition.php Action)
   │
   ├─ reception FILES / lib_file_id
   ├─ move_uploaded_file → /tmp/duplicator/pdf_upload_XXX.pdf
   │
   ├─ new Imposition($pdfPath, $settings)
   ├─ $imposer->process($outputFile)
   │    ├─ setSourceFile() → pageCount
   │    ├─ boucle stackDepth
   │    │    ├─ AddPage() recto
   │    │    ├─ placePage() × n_up
   │    │    └─ AddPage() verso (si duplex)
   │    └─ Output($outputFile)
   │
   ├─ unlink($uploadFile)
   ├─ $success = true
   └─ return template(view, ['download_url' => '?download&file=…'])
   │
   ▼ HTML response (page téléchargement)
Frontend affiche bouton "Télécharger le PDF imposé"
```

### 10.2 Séquence impression Windows

```
Frontend clic "Imprimer"
   │
   ▼ electronAPI.printJob(pdfPath, { printer: "HP", copies: 2, duplex: "duplex" })
   │
   ▼ IPC 'print-job' (main-caddy)
   │
   ├─ validatePath(pdfPath)
   ├─ const impl = require('./src/print-engine/windows/win32-printer')
   │
   ▼ impl.printJob(pdfPath, options)
        │
        ├─ loadNativeAddon() → win32-printer.node
        ├─ addon.printJob(printer, pdfBuffer, copies, duplex, …)
        │     │
        │     └─ C++: OpenPrinter() + StartDocPrinter() + WritePrinter()
        │
        └─ Résolution : { success: true, jobId: 123 }
   │
   ▼ electronAPI resolves → frontend "Impression lancée"
```

### 10.3 Séquence printer monitor (background)

```
main-caddy démarrage
   │
   ▼ require('./utils/printer-monitor').start(callback)
        │
        ▼ win32-printer.node startPrinterMonitor()
             └─ FindFirstPrinterChangeNotification(NULL, 0, PRINTER_CHANGE_ADD_JOB)
                  │
                  ▼ WaitForSingleObject(event, INFINITE)
                       │ (job ajouté)
                       ▼ GetJob() → infos job
                            │
                            ▼ Callback JS
                                 │
                                 ▼ mainWindow.webContents.send('print-job-detected', job)
                                      │
                                      ▼ renderer: electronAPI.onPrintJobDetected(cb)
                                           │
                                           ▼ UI update (nouveau job dans liste)
```

---

*Fin flux & communications — Suite : 05-DEPLOIEMENT_CI.md*
