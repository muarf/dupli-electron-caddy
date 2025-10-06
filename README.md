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

- **Multitirages** : Fonctionne uniquement avec la même machine (pas de mélange de machines)
- **Désimposer** : Ne fonctionne qu'avec le script Python (pas d'interface web)

## ✅ Bugs corrigés (v1.1.0)

- ✅ **Page Admin** : Correction de l'affichage répété et des variables non définies
- ✅ **Ajout de machines** : Résolution de l'erreur "Unexpected end of JSON input" sur la page tirage_multimachines
- ✅ **Newsletter** : Possibilité d'activer/désactiver la newsletter depuis l'admin
- ✅ **Changements admin** : Types de machines correctement détectés dynamiquement
- ✅ **Type photocopieuse** : Distinction correcte entre photocopieurs à encre et à toner
- ✅ **Headers pages** : Uniformisation des headers entre impose/unimpose
- ✅ **Erreurs PHP** : Correction des erreurs de variables non initialisées et de syntaxe PDO
- ✅ **Téléchargement BDD** : Correction du téléchargement des sauvegardes (HTML → fichier .sqlite)
- ✅ **Statistiques globales** : Inclusion des photocopieurs dans les statistiques globales (blablastats)
- ✅ **Fonctions stats** : Correction des structures manquantes dans stats_by_machine_photocop

## 🆕 Nouvelles fonctionnalités (v1.1.0)

### Imposition Tracts
Nouvelle fonctionnalité pour optimiser l'impression de tracts et documents :

- **Interface intuitive** : Drag & drop pour sélectionner vos PDF
- **Détection automatique** : Reconnaissance automatique des formats A4, A5, A6
- **Duplication intelligente** : 
  - A4 → 2 copies sur A3 (paysage)
  - A5 → 4 copies sur A3 (portrait) 
  - A6 → 8 copies sur A3 (paysage)
- **Gestion recto/verso** : Traitement automatique des documents recto/verso
- **Prévisualisation** : Aperçu du résultat avant téléchargement
- **Fallback robuste** : Utilisation de Ghostscript pour les PDF incompatibles

### Améliorations techniques
- **Corrections PHP** : Résolution des erreurs de variables non définies
- **Interface admin** : Correction des problèmes d'affichage répété
- **AJAX robuste** : Correction des erreurs de communication client/serveur

## 📋 TODO

- **Statistiques de remplissage** : Statistique de remplissage de la page
- **Outils Riso** : Intégrer outils Riso open source pour séparer les couleurs/coloriser noir et blanc

## 🐛 Dépannage

### Problèmes courants

Dites moi !

### Logs

Les logs sont affichés dans la console de l'application Electron.
# Trigger Windows build
