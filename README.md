# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 📥 Télécharger la dernière version

[![Latest Release](https://img.shields.io/github/v/release/muarf/dupli-electron-caddy?label=Dernière%20version&style=for-the-badge)](https://github.com/muarf/dupli-electron-caddy/releases/latest)
[![Downloads](https://img.shields.io/github/downloads/muarf/dupli-electron-caddy/total?label=Téléchargements&style=for-the-badge)](https://github.com/muarf/dupli-electron-caddy/releases)

Téléchargez la dernière release directement depuis GitHub :

- **Windows** : [![Windows Download](https://img.shields.io/badge/Windows-Download-blue?style=flat-square&logo=windows)](https://github.com/muarf/dupli-electron-caddy/releases/latest) - Fichier `Duplicator-Caddy-Setup-*.exe`
- **Linux** : [![Linux Download](https://img.shields.io/badge/Linux-Download-orange?style=flat-square&logo=linux)](https://github.com/muarf/dupli-electron-caddy/releases/latest) - Fichier `Duplicator-*-x86_64.AppImage`
- **macOS** : [![macOS Download](https://img.shields.io/badge/macOS-Download-gray?style=flat-square&logo=apple)](https://github.com/muarf/dupli-electron-caddy/releases/latest) - Fichier `Duplicator-*.dmg`

👉 **[Voir toutes les releases et télécharger](https://github.com/muarf/dupli-electron-caddy/releases)**

## 🚀 Fonctionnalités

### 📊 Gestion Comptable
- Calcul des prix de revient pour les différentes machines
- Gestion des coûts d'impression (papier, encre, masters, tambours, devellopeurs)
- Suivi des volumes d'impression 
- Statistiques d'utilisation, prévision des temps de changement de consommables
- Rapports de rentabilité

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
