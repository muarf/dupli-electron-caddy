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
- Imposition de PDF (8/16 pages A5/A6 sur un A3 rectoverso)
- Unimposition de PDF (séparation des pages pour un pdf déjà imposé en livret)
- Interface web moderne ormats

### 🔧 Technique
- Serveur Caddy intégré pour la portabilité
- Support PHP avec serveur intégré
- Application Electron cross-platform (Windows, Linux, macOS)
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

- **Statistiques** : Vérifier que les statistiques prennent en charge toutes les machines
- **Machine à toner** : Vérifier que ça marche avec une machine à toner

## 🐛 Bugs connus

- **Affichage des prix** : Pas d'affichage du prix en JS sur la page `tirage_multimachines` des passages et des masters
- **Newsletter** : Pas possible d'enlever la newsletter
- **Changements admin** : Sur la page admin les changements n'ont pas de type
- **Type photocopieuse** : Photocopieuse à encre a "master" et "drum" dans le type, alors que c'est juste pour les photocopieurs à toner

## 📋 TODO

- **PDF A4/A5 → A3** : PDF d'une page A4/A5 multiple sur du A3
- **PDF recto/verso → A3** : PDF de deux pages recto/verso multiple sur du A3
- **Statistiques de remplissage** : Statistique de remplissage de la page
- **Outils Riso** : Intégrer outils Riso open source pour séparer les couleurs/coloriser noir et blanc

## 🐛 Dépannage

### Problèmes courants

Dites moi !

### Logs

Les logs sont affichés dans la console de l'application Electron.
