# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 📥 Téléchargements & Versions

| Canal | Version | Moteur | Statut | Accès |
| :--- | :--- | :--- | :--- | :--- |
| **🟢 Stable** | `v1.5.62` | Electron | Recommandé en production | [Télécharger (v1.5.62)](#-version-stable-v1562) |
| **🧪 Beta** | `v2.0.x` | Tauri | Nouvelles fonctionnalités stabilisées | [Releases Beta](https://github.com/muarf/dupli-electron-caddy/releases?q=beta) |
| **⚡ Alpha** | `v3.0.x` | Tauri | Développement actif (spooler, tapis de tri) | [Releases Alpha](https://github.com/muarf/dupli-electron-caddy/releases?q=alpha) |

---

### 🟢 Version Stable (`v1.5.62`)
Version éprouvée basée sur **Electron + Caddy**, recommandée pour une utilisation quotidienne.

- **Windows** : [Duplicator-Setup-1.5.62.exe](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-Setup-1.5.62.exe) *(Installeur)* | [Version portable .zip](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-1.5.62-win.zip)
- **Linux** : [Duplicator-1.5.62-x86_64.AppImage](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-1.5.62-x86_64.AppImage) | [Paquet Debian/Ubuntu .deb](https://github.com/muarf/dupli-electron-caddy/releases/download/v1.5.62/Duplicator-1.5.62-amd64.deb)

---

### 🧪 Version Beta (`v2.0`)
Version moderne basée sur **Tauri** avec performances accrues et empreinte mémoire réduite.
- Accéder aux téléchargements : **[Dernières releases Beta (Windows & Linux)](https://github.com/muarf/dupli-electron-caddy/releases?q=beta)**

---

### ⚡ Version Alpha (`v3.0`)
Canal de test à la pointe du développement :
- Intègre le **Tapis de tri interactif** pour l'auto-tirage.
- Synchronisation avancée du spooler d'impression et détection automatique multi-copies.
- Accéder aux téléchargements : **[Dernières releases Alpha (Windows & Linux)](https://github.com/muarf/dupli-electron-caddy/releases?q=alpha)**

---

👉 **[Voir l'ensemble des releases sur GitHub](https://github.com/muarf/dupli-electron-caddy/releases)**

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
