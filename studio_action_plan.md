# Plan d'Action Studio : Intégration, Administration, Side-boarding et Tests

> **Révision critique du 2026-06-29** — Chaque assertion a été vérifiée dans le code source réel.

Ce document résume de façon exhaustive le contexte actuel des développements du module **Studio** et détaille toutes les étapes requises pour finaliser l'intégration des outils de PDF/OCR, de traitement de polices et d'IA (Docling) dans un environnement **Tauri (Rust)**.

---

## 1. Contexte du Projet & Architecture

L'application est construite sous **Tauri (Rust)**. Elle fait tourner :
- Un serveur web local géré par **Caddy** (redirigeant vers un backend PHP).
- Un backend en **PHP** avec une base de données **SQLite** (située par défaut dans `C:\Users\Dupli\AppData\Roaming\Duplicator\duplinew.sqlite` sous Windows).
- Un front-end interactif (Studio) qui communique avec le script de traitement `app/api/studio_process.php`.

Il existe déjà un mécanisme de résolution de binaires dans [`binary_utilities.php`](app/controler/functions/binary_utilities.php) : `get_binary_path()` cherche d'abord dans `bin/<platform>/`, puis dans `$PATH` système. La plateforme Windows est déjà mappée sur le dossier `bin/win-x64/` — qui contient déjà `magick.exe`, `identify.exe`, `gs.exe`, `gpcl6.exe`, `gxps.exe`.

---

## 2. État des Lieux & Chantiers à Réaliser

---

### Étape 1 : Administration des Endpoints IA (VPS-Oracle)

**Contexte — chemins Python actuellement hardcodés dans `studio_process.php` :**

| Branche | Chemin Python | Script | Dépendance |
|---|---|---|---|
| `recognize_font` (L.1452) | `app/api/venv_fonts/bin/python` | `scripts/font_recognizer.py` | transformers, PIL, modèle HuggingFace |
| `to_docx_docling` (L.1091) | `/opt/docling-venv/bin/python3` | `scripts/docling_copyfit.py` | docling (lourd) |
| `to_docx_flow` (L.1111) | `app/api/venv_fonts/bin/python` | `scripts/text_to_docx.py` | python-docx uniquement |
| `to_docx` (L.1131) | `app/api/venv_fonts/bin/python` | `scripts/pdf_to_docx.py` | pdf2docx uniquement |

> ⚠️ Seuls `recognize_font` et `to_docx_docling` nécessitent un VPS (modèles lourds). Les deux autres (`text_to_docx` et `pdf2docx`) sont des bibliothèques légères — voir Étape 2 pour leur traitement Windows.

**À implémenter :**
1. **`SettingsManager.php`** : Ajouter dans `initAiSettings()` :
   - `studio_api_fonts_url` (URL de l'API de reconnaissance de polices sur le VPS).
   - `studio_api_docling_url` (URL de l'API Docling sur le VPS).

2. **`admin.bibliotheque_ia.html.php`** : Ajouter un nouveau panel "Studio — Outils IA" avec deux champs URL, sauvegardés via le mécanisme `save_ai_settings.php` existant.

3. **Refactoring dans `studio_process.php`** :
   - `recognize_font` : lire `studio_api_fonts_url` depuis la DB ; si configuré → cURL POST (image Base64) ; sinon → fallback venv local (ou erreur claire si venv absent).
   - `to_docx_docling` : lire `studio_api_docling_url` depuis la DB ; si configuré → envoyer le PDF par HTTP POST ; sinon → fallback `/opt/docling-venv/` (ou erreur).

---

### Étape 2 : Intégration Windows (Tauri) — Stratégie par Outil

> **Contexte clé** : L'infrastructure `binary_utilities.php` / `get_binary_path()` est déjà en place et résout les binaires depuis `bin/win-x64/`. Il suffit de l'étendre aux nouveaux outils et d'adopter la bonne stratégie pour chaque dépendance Python.

#### Binaires natifs (simples à intégrer)

Ces outils ont des binaires Windows autonomes et suivent le même modèle que `magick.exe` déjà en place :

| Outil | Statut actuel | Action |
|---|---|---|
| `magick.exe` / `identify.exe` | ✅ Déjà dans `bin/win-x64/` | Rien à faire |
| `gs.exe` / `gswin64c.exe` | ✅ Déjà dans `bin/win-x64/` | Rien à faire |
| `tesseract.exe` | ❌ Absent | Télécharger l'installeur UB-Mannheim, extraire le `.exe` + `tessdata/fra.traineddata` dans `bin/win-x64/tesseract/` |
| `pdftotext.exe` (poppler) | ❌ Absent | Télécharger poppler-windows (github.com/oschwartz10612/poppler-windows), placer dans `bin/win-x64/` |
| `exiftool.exe` | ❌ Absent | Télécharger l'exécutable standalone exiftool.exe, placer dans `bin/win-x64/` |

**Adaptation PHP :** Étendre `get_binary_path()` ou créer des fonctions dédiées `get_tesseract_path()`, `get_pdftotext_path()`, `get_exiftool_path()` sur le modèle de `get_ghostscript_path()` déjà existant. Les appels dans `studio_process.php` qui utilisent `shell_exec('exiftool ...')` doivent passer par ces fonctions.

> ⚠️ Tesseract nécessite que la variable d'environnement `TESSDATA_PREFIX` pointe vers le dossier `tessdata/`. À définir dans PHP avant l'appel : `putenv('TESSDATA_PREFIX=' . $tessdata_dir)`.

#### `ocrmypdf` — Stratégie retenue : appel via `python.exe` embarqué

`ocrmypdf` est un outil Python qui orchestre Tesseract + Ghostscript. Il n'existe pas de binaire Windows standalone officiel. **Deux options :**

**Option A — Appel système à `ocrmypdf` (si Python installé sur l'hôte)**
- Inconvénient : dépend d'une installation Python + pip sur la machine Windows de l'utilisateur final → inacceptable pour une app distribuée.

**Option B ✅ (recommandée) — `python.exe` embarqué + `venv` gelé**
- Intégrer une distribution Python **embeddable** Windows (disponible sur python.org, ~10 Mo décompressée) dans `bin/win-x64/python/`.
- Créer un venv ou installer `ocrmypdf`, `pdf2docx`, `python-docx` dans ce Python embarqué.
- PHP appelle `bin/win-x64/python/python.exe` au lieu d'un chemin hardcodé.
- **Avantage** : un seul Python embarqué sert à `ocrmypdf`, `pdf2docx` ET `text_to_docx`.
- **Inconvénient** : ~100-150 Mo supplémentaires dans le bundle. Acceptable.

**Option C — Remplacer `ocrmypdf` par appel direct Tesseract**
- Appeler directement `tesseract.exe` sur chaque page (converti en image via ImageMagick), puis reconstituer un PDF avec GhostScript.
- Plus fragile que `ocrmypdf`, qui gère mieux les cas limites (PDF déjà textuel, deskew, etc.).
- À retenir uniquement si le bundle Python est jugé trop lourd.

> **Décision à prendre** : Option B recommandée. Un `python-embed` de 10 Mo + les libs pip (~50-80 Mo pour pdf2docx+ocrmypdf) reste dans des proportions raisonnables.

#### `font_recognizer.py` — Stratégie : uniquement VPS

Le script charge un modèle HuggingFace (`dchen0/font-classifier-v4`). Ce modèle pèse plusieurs centaines de Mo. **Embarquer le modèle dans le bundle Windows est hors de question.**

→ **Cette fonctionnalité est uniquement disponible si `studio_api_fonts_url` est configuré (VPS).** Si l'URL est vide, le bouton "Reconnaître la police" renvoie une erreur explicite invitant à configurer le VPS dans les réglages.

#### `docling_copyfit.py` — Stratégie : uniquement VPS

Idem — Docling est très lourd (modèles ML pour la mise en page). Pas d'embarquement local.

→ **Cette fonctionnalité est uniquement disponible si `studio_api_docling_url` est configuré (VPS).**

#### Plan d'implémentation complet pour Windows

```
bin/win-x64/
├── magick.exe          ✅ déjà présent
├── identify.exe        ✅ déjà présent
├── gs.exe              ✅ déjà présent
├── tesseract.exe       ❌ à ajouter
├── tessdata/
│   └── fra.traineddata ❌ à ajouter
├── pdftotext.exe       ❌ à ajouter
├── exiftool.exe        ❌ à ajouter
└── python/             ❌ à créer (Python Embeddable 3.11)
    ├── python.exe
    ├── python311.dll
    └── Lib/site-packages/
        ├── pdf2docx/   ← pip install
        ├── docx/       ← pip install python-docx
        └── ocrmypdf/   ← pip install
```

**Adaptation PHP** : Créer une fonction `get_python_path()` dans `binary_utilities.php` qui renvoie `bin/win-x64/python/python.exe` sur Windows, ou le `python`/`python3` système sur Linux/macOS. Les appels hardcodés dans `studio_process.php` seront remplacés par cette fonction.

---

### Étape 3 : Guide d'Installation (Mac & Linux)

Le fichier `php-install-guide.html` existe et couvre l'installation PHP. Il ne couvre pas les dépendances Studio.

Sur Linux/macOS, pas d'embarquement : l'utilisateur doit installer lui-même.

**À ajouter dans `php-install-guide.html`** :

```bash
# Linux (Debian/Ubuntu)
sudo apt-get update
sudo apt-get install -y imagemagick tesseract-ocr tesseract-ocr-fra \
    poppler-utils libimage-exiftool-perl ocrmypdf python3 python3-pip
pip3 install pdf2docx python-docx
```

```bash
# macOS (Homebrew)
brew install imagemagick tesseract tesseract-lang poppler exiftool \
    ocrmypdf ghostscript python3
pip3 install pdf2docx python-docx
```

> ⚠️ Sur Debian/Ubuntu, le paquet `exiftool` s'appelle `libimage-exiftool-perl` (et non `exiftool`).

Un outil de diagnostic dans l'administration (vérifier si chaque binaire est accessible via `which`) serait utile à terme.

---

### Étape 4 : Tests Automatiques (PHPUnit/Pest)

**État actuel** : `StudioProcessTest.php` couvre `impose`, `resize`, `to_pdf`, `riso_pdf`, `analyze_ink`, `pdf_to_images`, `merge`, `unimpose`, `organize_pages`. Aucun test pour les 10 nouvelles actions.

**Stratégie de test par catégorie :**

| Action | Dépendance externe | Stratégie |
|---|---|---|
| `passthrough_pdf` | Aucune (PHP pur) | Test direct |
| `modification` (redact/numérotation) | TCPDF (PHP) | Test direct |
| `list_fonts` | Aucune (filesystem) | Test direct |
| `upload_font` | TCPDF (PHP) | Test direct |
| `crop_pdf` | ImageMagick | `->skip()` si `magick` absent |
| `organize_pages` (crop/flip) | ImageMagick | Compléter test existant avec `crop` + `flipH`/`flipV` |
| `read_metadata` | exiftool | `->skip()` si `exiftool` absent |
| `update_metadata` | exiftool | `->skip()` si `exiftool` absent |
| `ocr_cleanup` | ocrmypdf + Tesseract | `->skip()` si `ocrmypdf` absent |
| `recognize_font` | venv Python + modèle HF | Mocker le script Python ; tester la logique PHP |
| `download_google_font` | Réseau | `->skip()` en CI ; tester le comportement `offline` |

> **Point clé** : Le helper `run_studio_process.php` mappe correctement `$_POST = $payload['post']` (ligne 24). `recognize_font` passant son image via `$_POST['image']` (Base64) fonctionnera sans modification du helper.

> ⚠️ Les tests avec `->skip()` conditionnel doivent utiliser la syntaxe Pest : `->skip(fn() => !shell_exec('which ocrmypdf'))`.

---

## Résumé des Priorités

| Priorité | Tâche | Complexité |
|---|---|---|
| 🔴 P1 | Remplacer chemins Python hardcodés par `get_python_path()` dans `studio_process.php` | Moyenne |
| 🔴 P1 | Ajouter `tesseract.exe`, `pdftotext.exe`, `exiftool.exe` dans `bin/win-x64/` | Faible |
| 🔴 P1 | Créer `bin/win-x64/python/` (Python embeddable + pip libs) | Moyenne |
| 🟠 P2 | Ajouter les clés `studio_api_*` dans `SettingsManager.php` + admin UI | Faible |
| 🟠 P2 | Refactorer `recognize_font` et `to_docx_docling` pour VPS | Moyenne |
| 🟡 P3 | Mettre à jour `php-install-guide.html` | Faible |
| 🟡 P3 | Écrire les tests PHPUnit/Pest manquants | Moyenne |
