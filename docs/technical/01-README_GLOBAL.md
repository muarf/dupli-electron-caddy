# Documentation Technique - Dupli Electron Caddy

> **Projet :** dupli-electron-caddy  
> **Branche :** `feature/cross-platform-unification`  
> **Version analysée :** 2.0.1-beta.local  
> **Date d'analyse :** 8 avril 2026  
> **Auteur :** oracle_ia (Agent Architecte Logiciel)

---

## 📋 Table des Matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Stack technique](#2-stack-technique)
3. [Installation et lancement](#3-installation-et-lancement)
4. [Structure du projet](#4-structure-du-projet)
5. [Fonctionnalités principales](#5-fonctionnalités-principales)
6. [Commandes utiles](#6-commandes-utiles)
7. [Notes de développement](#7-notes-de-développement)

---

## 1. Vue d'ensemble

**Duplicator** (dupli-electron-caddy) est une application de bureau **Electron** qui permet la gestion complète d'un atelier d'impression/duplicatique professionnelle. Elle combine :

- **Serveur web embarqué** (Caddy) pour héberger l'interface web
- **Backend PHP** (SQLite/MySQL) pour la logique métier
- **Frontend web** (HTML/CSS/JS) pour l'interface utilisateur
- **Modules natifs C++** pour le contrôle avancé des imprimantes
- **Intégration Ghostscript & ImageMagick** pour le traitement PDF et images

L'application est conçue pour fonctionner **hors-ligne totale** (mode portable) ou en mode client/serveur classique.

---

## 2. Stack technique

### 2.1 Core / Runtime

| Composant | Version / Détails | Rôle |
|-----------|------------------|------|
| **Electron** | 22.3.27 | Framework application desktop |
| **Node.js** | 22.11.0 (bundlé) | Runtime JavaScript serveur |
| **Caddy** | 2.8.4 | Reverse proxy + serveur web statique |
| **PHP** | 8.2+ (CLI intégré) | Backend logique métier |
| **SQLite** | 3.45+ | Base de données embarquée (par défaut) |

### 2.2 Bibliothèques & Outils

**Node.js / Electron**
- `electron-updater` (^6.6.2) — Mises à jour automatiques
- `sqlite3` (^5.1.7) — Accès direct à la base
- `bcryptjs` (^3.0.3) — Hash mots de passe
- `pdfjs-dist` (^3.11.174) — Prévisualisation PDF
- `pdfkit` (^0.17.2) — Génération PDF côté JS
- `node-addon-api` (^7.0.0) — Bindings C++ natifs

**PHP**
- `setasign/fpdi-tcpdf` (^2.3) — Import/Imposition PDF
- `setasign/fpdf` (^1.8) — Génération PDF
- `smalot/pdfparser` (^2.12) — Analyse PDF

**Traitement documents**
- **Ghostscript** (10.0+) — Conversion PDF → images, nettoyage, recomposition
- **ImageMagick** (7.1+) — Manipulation images (via PHP GD/Imagick)

**Build / Déploiement**
- `electron-builder` (^26.0.12) — Création installateurs (NSIS Windows, AppImage Linux, DMG macOS)
- `cross-env` — Variables d'environnement cross-platform
- `node-gyp` — Compilation modules natifs C++

### 2.3 Formats & Standards

- **Base de données** : SQLite (`dupli.db`) en portable ; MySQL/MariaDB en multi-postes
- **Configuration app** : `.env` + `app/conf.php`
- **Internationalisation** : Système i18n maison (fichiers de langue JSON/ PHP)
- **Sessions** : Fichiers temporaires (mode offline) ou Redis/DB selon config

---

## 3. Installation et lancement

### 3.1 Prérequis

**Mode développement :**
```bash
git clone https://github.com/muarf/dupli-electron-caddy.git
cd dupli-electron-caddy
npm install
```

**Mode production (AppImage) :**  
Aucun prérequis — tout est embarqué (PHP, Caddy, Ghostscript, ImageMagick).

### 3.2 Installation des dépendances PHP (si développement web séparé)

```bash
cd app
composer install
```

### 3.3 Lancement de l'application

```bash
# Mode complet avec Caddy + PHP-FPM intégré
npm start:caddy

# Mode PHP seul (plus léger)
npm start:php

# Mode développement avec DevTools
npm run dev
```

**Arguments CLI :**
- `--no-sandbox` : Désactive sandbox (requis Linux parfois)
- `--disable-gpu` : Désactive accélération GPU (évite bugs GLX)
- `--user-data-dir <path>` : Dossier de données personnalisé

### 3.4 Configuration initiale

À la première exécution, l'application crée automatiquement :
```
~/.config/Duplicator/
├── dupli.db          # Base SQLite
├── temp/             # Fichiers temporaires
└── logs/             # Logs d'application
```

---

## 4. Structure du projet

```
dupli-electron-caddy/
│
├── 📄 main-caddy.js           # Processus principal Electron (Caddy + PHP)
├── 📄 main.js                  # Processus simplifié (PHP seul)
├── 📄 preload.js               # API sécurisée exposée au renderer
├── 📄 package.json             # Dépendances Node + scripts
├── 📄 Caddyfile               # Configuration reverse proxy Caddy
│
├── 📁 app/                    # Backend PHP ( MVC-like )
│   ├── 📁 api/                # Endpoints REST (39 fichiers)
│   ├── 📁 controler/          # Contrôleurs & fonctions métier
│   │   └── 📁 functions/      # Bibliothèques métier (pricing, utilities…)
│   ├── 📁 models/             # Classes métier (Imposition, SpoolManager…)
│   ├── 📁 view/               # Templates PHP (HTML/CSS/JS)
│   │   ├── 📁 components/     # Composants réutilisables
│   │   └── 📁 partials/       # Partials (header, footer…)
│   ├── 📁 public/             # Racine web (répliquée depuis app/)
│   ├── 📄 composer.json       # Dépendances PHP
│   └── 📄 php.ini             # Configuration PHP (upload_max_filesize, etc.)
│
├── 📁 src/                    # Modules natifs Node.js/C++
│   ├── 📁 print-engine/       # Moteur d'impression natif
│   │   ├── index.js           # façade unifiée (Windows/Linux/macOS)
│   │   ├── 📁 windows/        # Implémentation Windows
│   │   │   ├── win32-printer.js
│   │   │   ├── printer-monitor.js
│   │   │   └── build/         # Addon C++ compilé (.node)
│   │   └── 📁 linux/          # Implémentation Linux
│   │       └── cups-printer.js
│   └── 📁 print-processor/    # Processeur d'impression C++ natif
│       └── DupliPrintProcessor.cpp
│
├── 📁 utils/                  # Scripts utilitaires
│   ├── printer-monitor.js      # Surveillance jobs Windows (spooler)
│   ├── spool-analyzer.js       # Analyse SPL/SHD
│   └── printer-monitor.ps1     # PowerShell config
│
├── 📁 caddy/                  # Binaire Caddy (inclus en release)
├── 📁 php/                    # PHP portable (Windows)
├── 📁 ghostscript/            # Binaires Ghostscript (multi-platform)
├── 📁 imagemagick/            # Binaires ImageMagick
│
├── 📁 build/                  # Artéfacts electron-builder
├── 📁 docs/                   # Documentation utilisateur
├── 📁 tests/                  # Tests Jest/PHP
└── 📁 docs-generated/         # ← Documentation générée (ce dossier)
```

---

## 5. Fonctionnalités principales

### 5.1 Gestion des tirages & consommables

- **Suivi en temps réel** des consommations (encre, masters, tambours)
- **Calcul automatique** du coût par impression (par machine)
- **Alertes pro-actives** (seuil de fin de consommable atteint)
- **Historique complet** par machine et par type de support

**Fichiers clés :** `pricing.php`, `consommation.php`

### 5.2 Imposition PDF (A5, A6, brochures, livres)

- **Modes supportés :**
  - `A5` — 4 pages par feuille A3 (2×2), recto-verso miroir
  - `A6` — 16 pages par feuille A3 (4×4), recto-verso
  - `Brochure` — Avec hirondelles et traits de coupe
  - `Livre` — Avec traits de coupe centraux

- **Options :**
  - Fond perdu configurable (bleed)
  - Numérotation dans les zones de coupe
  - Traits de coupe (normal / central / both)
  - Aperçu en temps réel

**Fichiers clés :** `ImpositionProcessor.php`, `Imposition.php`, `CropMarks.php`

### 5.3 Traitement images & PDF

- **PDF → PNG** (300 DPI, avec extraction dimensions exactes)
- **PNG → PDF** (préservation format A3/A4/portrait/paysage)
- **Traitement image** : contraste, luminosité, gamma, saturation
- **Conversion bitmap** : seuil (threshold) ou Floyd-Steinberg dithering
- **Intégration bibliothèque** : réutilisation de fichiers déjà uploadés

**Fichiers clés :** `pdf_to_png.php`, `png_to_pdf.php`, `image_processor.php`

### 5.4 Impression avancée

- **Détection automatique** imprimantes (Windows + Linux/CUPS)
- **Capacités d'impression** : bacs papier, formats, recto-verso, résolution
- **Surveillance spooler** (Windows) : Détection jobs, thumbnail génération, fill-rate
- **Suppression sécurisée** des fichiers spool (SPL/SHD)
- **Impression via SumatraPDF** (Windows) pour copies natives

**Fichiers clés :** `src/print-engine/`, `SpoolManager.php`, `printer-monitor.js`

### 5.5 Bibliothèque de documents

- **Stockage permanent** de fichiers PDF/PNG
- **Tagging & recherche** par catégorie
- **Prévisualisation** intégrée
- **Réutilisation** dans les workflows (upload depuis bibliothèque)

**Fichiers clé :** `BibliothequeManager.php`

### 5.6 Interface d'administration

- **Gestion machines** : ajout/modification/suppression duplicopieurs & photocopieurs
- **Gestion prix** : tarifs papier, encre, masters par machine
- **Gestion imprimantes** : sélection, configuration
- **Statistiques** : consommation, coûts, tendances
- **Système de news** : actualités internes

---

## 6. Commandes utiles

### 6.1 Développement

```bash
# Installer dépendances Node + PHP
npm install
cd app && composer install && cd ..

# Lancer en dev (avec DevTools)
npm run dev

# Compiler modules natifs Windows (print-engine)
npm run rebuild:print-engine
```

### 6.2 Build & Release

```bash
# Télécharger binaires externes (Caddy, PHP, Ghostscript…)
npm run download-all

# Build standard (multi-platform)
npm run build

# Build spécifique Caddy (avec Caddy embarqué)
npm run build:caddy

# Build Windows (NSIS)
# Produit : dist/Duplicator-Setup-<version>.exe
```

### 6.3 Tests

```bash
# Tous les tests
npm test

# Unitaires
npm run test:unit

# Intégration (Caddy + PHP simple)
npm run test:integration

# End-to-end (application Electron)
npm run test:e2e

# Watch mode
npm run test:watch

# Coverage
npm run test:coverage
```

### 6.4 Déploiement

Le script `app/deploy-script.sh` automatise :
1. Commit des changements dans `dupli-php-dev`
2. Pull dans `dupli-electron-caddy/app`
3. Bump de version + création tag Git
4. Push de la release

---

## 7. Notes de développement

### 7.1 Architecture multi-processus

```
┌─────────────────────────────────────────────┐
│   Electron Main Process (main-caddy.js)     │
│   ├── Caddy Server (127.0.0.1:8000)         │
│   ├── PHP Built-in Server (127.0.0.1:8001)  │
│   ├── IPC : preload.js ⇄ Renderer           │
│   └── Gestion modules natifs (print-engine) │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│   Renderer Process (Chromium)               │
│   ├── Frontend (HTML/CSS/JS)                │
│   └── electronAPI (contextBridge)           │
└─────────────────────────────────────────────┘
```

**Note :** Caddy fait du reverse proxy de `127.0.0.1:8000 → 127.0.0.1:8001` pour servir l'interface PHP comme un site web classique.

### 7.2 Gestion du stockage (AppImage / portable)

L'application détecte automatiquement l'environnement :
- **AppImage / portable** → dossiers `~/.config/Duplicator/`
- **Développement** → dossier `app/public/tmp/`
- **Windows installé** → `%APPDATA%\Duplicator\`

Fonction `resolveTempDir()` dans `utilities.php` centralise cette logique.

### 7.3 Base de données

- **SQLite par défaut** : fichier `dupli.db` dans dossier data
- **MySQL optionnel** : pour déploiements multi-postes (config dans `conf.php`)
- **Migrations** : gérées manuellement via scripts SQL dans `app/maintenance/`

### 7.4 Traitement PDF asynchrone

Les opérations longues (conversion PDF → images, traitement image, imposition) utilisent un système de **progression asynchrone** :
1. POST déclenche traitement → génère `progress_key`
2. Réponse immédiate avec `progress_key`
3. Frontend poll `?progress_key=…` pour suivre avancement
4. Fichier progress JSON dans `/tmp/duplicator_*`

Évite timeouts PHP et donne feedback utilisateur.

### 7.5 Impressions Windows ( SumatraPDF )

L'application utilise **SumatraPDF** en ligne de commande pour :
- Copies natives (pas de boucle dans Electron)
- Tous formats papier
- Recto-verso (simplex/duplex/tumble)
- Silence total (pas de popup)

Avantage : fiable, rapide, pas de dialogues utilisateur.

### 7.6 Points d'attention

⚠️ **Compatibilité AppImage :**
- Le système de fichiers est **read-only** → tout écriture doit passer par `/tmp` ou `~/.config`
- PHP CLI intégré utilise `php.ini` custom (chems en dur)
- Ghostscript/ImageMagick embarqués doivent être exécutables (`chmod +x`)

⚠️ **Sécurité :**
- Aucune authentification en mode développement
- Mode production : sessions PHP + .htaccess (à vérifier)
- Uploads : limite 50MB, vérification MIME, extension `.htaccess` de protection

⚠️ **Performances :**
- ImageProcessor : limite 2000px pour dithering PHP pur (fallback seuil simple)
- PDF imposés : cache de templates TCPDF pour éviter re-import
- SQLite : WAL mode + cache_size=10000 pour performances

---

## 8. Glossaire

| Terme | Définition |
|-------|------------|
| **Dupli** | Duplicopieur (machine Risographique) |
| **Photocop** | Photocopieur numérique |
| **Master** | Original pour duplicopieur (maître) |
| **Encre** | Consommable encre (pour photocopieurs) |
| **Tambour** | Cylindre d'impression Riso (couleur spécifique) |
| **Imposition** | Placement de plusieurs pages sur une feuille |
| **Spool** | File d'impression Windows (SPL/SHD) |
| **Fill-rate** | Taux de couverture d'une page (noir vs blanc) |
| **Recto/Verso** | Face avant / arrière d'une feuille |
| **Tête-bêche** | Rotation 180° sur le verso (livre) |

---

*Document généré automatiquement par oracle_ia — Basé sur l'analyse du code source de la branche `feature/cross-platform-unification`*
