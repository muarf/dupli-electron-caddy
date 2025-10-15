# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 🚀 Fonctionnalités

### 📊 Gestion Comptable
- Calcul des prix de revient pour les différentes machines
- Gestion des coûts d'impression (papier, encre, masters, tambours, devellopeurs)
- Suivi des volumes d'impression 
- Statistiques d'utilisation, prévision des temps de changement de consommables
- Rapports de rentabilité

### 📄 Traitement de Documents
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

### Installation des dépendances

```bash
npm install
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
- Linux: `Duplicator-{version}.AppImage`
- macOS: `Duplicator-{version}.dmg`

## ✅ À vérifier


## 📋 TODO

- MacOS RELEASES
- Contraste, luminosité, bitmap. 

## 🐛 Dépannage



### Problèmes courants

Dites moi !


