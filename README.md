# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 📥 Téléchargements & Versions

Choisissez le canal correspondant à vos besoins :

### 🟢 Version Stable (v1.5.62)
Dernière version stable testée et approuvée.
[![Stable Release](https://img.shields.io/github/v/release/muarf/dupli-electron-caddy?label=Stable&style=for-the-badge&color=green)](https://github.com/muarf/dupli-electron-caddy/releases/tag/v1.5.62)
- **Windows** : [Duplicator-Setup-1.5.62.exe](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-Setup-1.5.62.exe)
- **Linux (AppImage)** : [Duplicator-1.5.62-x86_64.AppImage](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-1.5.62-x86_64.AppImage)
- **Linux (Debian/Ubuntu)** : [Duplicator-1.5.62-amd64.deb](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-1.5.62-amd64.deb)

---

### 🧪 Version Beta (v2.0)
Fonctionnalités récentes stabilisées (moteur Tauri v2, auto-tirage, cross-platform unifié).
[![Beta Release](https://img.shields.io/github/v/release/muarf/dupli-electron-caddy?include_prereleases&label=Beta&style=for-the-badge&color=orange)](https://github.com/muarf/dupli-electron-caddy/releases)
- **Windows / Linux** : [Voir les dernières releases Beta](https://github.com/muarf/dupli-electron-caddy/releases?q=beta)

---

### ⚡ Version Alpha (v3.0)
Canal expérimental de développement actif (tapis de tri interactif, synchronisation avancée du spooler d'impression).
- **Windows / Linux** : [Voir les dernières releases Alpha](https://github.com/muarf/dupli-electron-caddy/releases?q=alpha)

---

### ✨ Nouveautés majeures (Beta & Alpha)
- **🧺 Tapis de Tri (Auto-Tirage)** : Organisation visuelle et tri intuitif des travaux du spooler avec synchronisation temps réel et calcul précis par copie.
- **🚀 Moteur d'Automatisation** : Analyse intelligente des formats, imposition automatique et persistance des sessions.
- **🖥️ Unification Cross-Platform** : Support complet Linux & Windows sous moteur Tauri et traitement graphique ImageMagick unifié.
- **🛡️ Fiabilité & Santé** : Isolation des canaux de release et système de diagnostic (Health Check) intégré.

---

👉 **[Voir toutes les releases](https://github.com/muarf/dupli-electron-caddy/releases)**

## 🚀 Fonctionnalités

### 🖨️ Production & Impression (Beta)
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
- **Images vers Pdf** et reciproque
- **Separateur de couleurs**
   - RGB, CMYK, 2 couleurs, pipette
- **Effets images**
  - Posterisation ( pochoirs multi couches)
  - Tramage 

### 🔧 Technique
- Serveur Caddy intégré pour la portabilité
- Support PHP avec serveur intégré pour windows
- Application Electron cross-platform (Windows, Linux, Macos à venir)
- Interface utilisateur intuitive ( on essaie ;))




## 📦 Installation




### Prérequis

- Node.js 18+ 
- npm ou yarn
- **Composer** (pour les dépendances PHP)

### Installation de Composer

**Windows (avec Chocolatey) :**
```powershell
# Ouvrir PowerShell en tant qu'administrateur
choco install composer -y
```

**Windows (manuel) :**
Téléchargez et installez Composer depuis https://getcomposer.org/download/

**Linux/macOS :**
```bash
# Installation globale
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Installation des dépendances

**1. Dépendances Node.js :**
```bash
npm install
```

**2. Dépendances PHP (dans le dossier app/) :**
```bash
cd app
composer install
cd ..
```

### Téléchargement des binaires

```bash
# Télécharger Caddy et PHP pour toutes les plateformes
npm run download-all
```

## 🔧 Développement

### Démarrer en mode développement

```bash
npm start
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


## 🏗️ Build

### Build pour toutes les plateformes

```bash
npm run build:caddy
```

### Build spécifique

```bash
# Windows
npm run build:caddy -- --win

# Linux
npm run build:caddy -- --linux

# macOS
npm run build:caddy -- --mac
```




### Releases

Les releases sont automatiquement créées avec :
- Windows: `Duplicator-Caddy-Setup-{version}.exe`
- Linux: `Duplicator-{version}-x86_64.AppImage`
- macOS: `Duplicator-{version}.dmg`

Les liens ci-dessus pointent automatiquement vers la dernière version disponible.

## ✅ À vérifier


## 📋 TODO

- MacOS RELEASES
- Contraste, luminosité, bitmap. 

## 🐛 Dépannage



### Problèmes courants

Dites moi !


# Test auto-release
