# Duplicator - Gestion de Comptabilité & Atelier d'Impression

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, atelier d'imposition et d'auto-tirage, packagée en application native cross-platform via **Tauri v2/v3** avec serveur Caddy et PHP intégrés.

## 📥 Téléchargements & Versions

| Canal | Version | Moteur | Statut | Accès |
| :--- | :--- | :--- | :--- | :--- |
| **🟢 Production (Main)** | `v3.0.x` | Tauri | Version principale recommandée | [Dernières Releases](https://github.com/muarf/dupli-electron-caddy/releases) |
| **🧪 Beta** | `v2.0.x` | Tauri | Canal de consolidation | [Releases Beta](https://github.com/muarf/dupli-electron-caddy/releases?q=beta) |
| **⚡ Alpha** | `v3.0.x` | Tauri | Développement actif (spooler, tapis de tri) | [Releases Alpha](https://github.com/muarf/dupli-electron-caddy/releases?q=alpha) |
| **📦 Archive Legacy** | `v1.5.62` | Electron | Ancienne version historique (archivée) | [Release v1.5.62](https://github.com/muarf/dupli-electron-caddy/releases/tag/v1.5.62) |

---

### 🟢 Version Actuelle (Tauri)
- **Windows** : Télécharger l'installeur `.exe` ou `.msi` sur la page des releases.
- **Linux** : Paquets `.AppImage` et `.deb` disponibles.
- 👉 **[Accéder aux dernières releases](https://github.com/muarf/dupli-electron-caddy/releases)**

---

### ✨ Fonctionnalités majeures
- **🧺 Tapis de Tri (Auto-Tirage)** : Organisation visuelle et tri intuitif des travaux du spooler avec synchronisation temps réel et calcul précis par copie.
- **🚀 Moteur d'Automatisation** : Analyse intelligente des formats, imposition automatique et persistance des sessions.
- **🖥️ Moteur Moderne Tauri** : Performances accrues, empreinte mémoire ultra-faible, binaire natif et traitement d'image unifié (ImageMagick / Ghostscript).
- **🛡️ Fiabilité & Santé** : Système de diagnostic (Health Check) intégré pour les services Caddy/PHP.

---

## 🚀 Fonctionnalités Détaillées

### 🖨️ Production & Impression
- **Imposition automatique** : Transformation de PDF A4 en planches A3 prêtes à l'impression.
- **Traitement d'images** : Optimisation des densités pour la reproduction.
- **Multimachines** : Gestion centralisée pour les collectifs équipés de plusieurs parcs.

### 📊 Gestion Comptable
- Calcul des prix de revient temps réel.
- Suivi précis des consommables (encre, masters, tambours, développeurs).
- Statistiques d'utilisation et rapports de rentabilité par machine.
- Historique des volumes d'impression.

### 📄 Traitement de Documents
- **Bibliothèque PDF/PNG**
  - Gestion centralisée de fichiers PDF et PNG
  - Recherche full-text dans les documents
  - Génération automatique de miniatures
  - Indexation de dossiers externes
- **Imposition de PDF** (8/16 pages A5/A6 sur un A3 rectoverso)
- **Unimposition de PDF** (séparation des pages pour un pdf déjà imposé en livret)
- **Imposition Tracts** (duplication intelligente A4/A5/A6 vers A3 avec orientation optimisée)
  - Détection automatique du format PDF (A4, A5, A6)
  - Duplication automatique (2x A4, 4x A5, 8x A6 sur A3)
  - Gestion recto/verso avec pages séparées
  - Prévisualisation intégrée et téléchargement
  - Fallback Ghostscript pour PDF incompatibles
- Interface web moderne avec drag & drop
- **Images vers Pdf** et réciproque
- **Séparateur de couleurs**
   - RGB, CMYK, 2 couleurs, pipette
- **Effets images**
  - Postérisation (pochoirs multi-couches)
  - Tramage 

### 🔧 Technique
- Moteur applicatif **Tauri** (Rust + WebView native)
- Serveur Caddy intégré pour la portabilité
- Moteur PHP embarqué pour le backend applicatif

---

## 📦 Installation & Développement

### Prérequis

- Node.js 18+ 
- npm ou yarn
- Rust & Cargo (`rustup`)
- **Composer** (pour les dépendances PHP)

### Installation de Composer

**Windows (avec Chocolatey) :**
```powershell
choco install composer -y
```

**Windows (manuel) :**
Téléchargez et installez Composer depuis https://getcomposer.org/download/

**Linux/macOS :**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Installation des dépendances

```bash
# 1. Dépendances Node.js
npm install

# 2. Dépendances PHP (dans app/)
cd app
composer install
cd ..

# 3. Téléchargement des sidecars et binaires requis
npm run download-all
node scripts/download-tauri-sidecars.js
```

---

## 🔧 Lancement en développement

```bash
npm run dev
# ou directement :
npx @tauri-apps/cli dev
```

### Tests

```bash
# Tests unitaires
npm run test:unit

# Tests d'intégration
npm run test:integration

# Tests E2E
npm run test:e2e

# Tous les tests
npm test
```

---

## 🏗️ Build de production

```bash
# Build de l'application Tauri pour votre système courant :
npx @tauri-apps/cli build
```

Les livrables générés se trouvent dans :
- Windows: `src-tauri/target/release/bundle/nsis/` et `.../msi/`
- Linux: `src-tauri/target/release/bundle/appimage/` et `.../deb/`

## ✅ À vérifier


## 📋 TODO

- MacOS RELEASES
- Contraste, luminosité, bitmap. 

## 🐛 Dépannage



### Problèmes courants

Dites moi !


# Test auto-release
