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
- **PDF vers PNG** (conversion de PDF en images PNG pour traitement)
- **PNG vers PDF** (assemblage d'images PNG en documents PDF)
- **Séparateur de couleurs Riso** (séparation RGB/CMYK pour impression multi-tambours)
  - Modes RGB, CMYK, et 2 couleurs
  - Outil pipette pour isolation de couleurs
  - Effets de postérisation et halftone (trames)
  - Export individuel ou ZIP de toutes les couches
- Interface web moderne avec drag & drop

### 🔧 Technique
- Serveur Caddy intégré pour la portabilité
- Support PHP avec serveur intégré
- Application Electron cross-platform (Windows, Linux, macOS)
- Interface utilisateur intuitive ( on essaie ;))

## 📦 Installation

### Prérequis (Développement)

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

### Installation de l'application (Utilisateurs)

Au premier lancement de l'application, vous avez **2 options** :

#### Option 1 : Créer vos machines
- Configurez manuellement vos duplicopieurs et photocopieurs
- Définissez les prix et compteurs initiaux
- Créez votre mot de passe administrateur

#### Option 2 : Importer une base de données
- Restaurez une base SQLite existante (depuis une autre instance ou sauvegarde)
- Le fichier doit contenir au moins une machine configurée
- Un backup automatique est créé si une base existe déjà

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

## 🐛 Bugs connus

- **Multitirages** : Fonctionne uniquement avec la même machine (pas de mélange de machines)

## ⚠️ À tester/vérifier pour v1.3.0

- **Statistiques** : Vérifier que les statistiques prennent en charge toutes les machines
- **Machine à toner** : Vérifier le fonctionnement complet avec machines à toner
- **Import BDD** : Tester l'import de BDD sur toutes les plateformes
- **Séparateur Riso** : Tests approfondis sur Windows packagé
- **Persistence BDD** : Valider la persistence après plusieurs mises à jour successives

## ✅ Bugs corrigés (v1.2.0 / v1.3.0-dev)

- ✅ **Erreur 502 Windows** : Résolution de l'erreur 502 sur les pages riso_separator, pdf_to_png, png_to_pdf
  - Fichiers JavaScript manquants (`riso-tools.js`, `jszip.min.js`) maintenant inclus
  - Pages manquantes ajoutées dans `$page_secure`
  - Dépendance CDN externe (JSZip) remplacée par version locale
- ✅ **Persistence BDD** : La base de données persiste maintenant correctement lors des mises à jour
  - BDD stockée dans userData (`AppData/Roaming` sur Windows, `~/.config` sur Linux)
  - Communication Electron → PHP via variable d'environnement `DUPLICATOR_DB_PATH`
  - Aucune perte de données lors des mises à jour
- ✅ **Sessions cross-platform** : Chemins de sessions compatibles Windows/Linux/macOS
  - Utilisation de `os.tmpdir()` au lieu de `/tmp/` hardcodé
  - Fonctionne sur toutes les plateformes
- ✅ **Erreurs 403 explicites** : Messages d'erreur détaillés pour pages non autorisées
  - Affiche la page demandée et comment corriger
  - Liste toutes les pages autorisées
  - Facilite le débogage
- ✅ **Timeouts Caddy** : Augmentation des timeouts (120s) pour traitement d'images lourdes
  - Résout les timeouts sur les opérations PDF/image
  - Logs détaillés pour débogage

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

## 🆕 Nouvelles fonctionnalités (v1.2.0 / v1.3.0-dev)

### Installation améliorée
- **Double option d'installation** : Créer des machines OU importer une BDD existante
  - Interface de choix claire au premier lancement
  - Upload de fichier SQLite avec validation
  - Backup automatique avant restauration
  - Migration facilitée entre instances

### Outils de traitement d'images
- **PDF vers PNG** : Conversion de documents PDF en images
  - Extraction de pages individuelles
  - Export en PNG haute qualité
  - Gestion multi-pages
  
- **PNG vers PDF** : Assemblage d'images en document PDF
  - Support drag & drop
  - Ordre personnalisable
  - Prévisualisation
  
- **Séparateur de couleurs Riso** : Séparation de couleurs pour impression RISO
  - Mode RGB (3 canaux) / CMYK (4 canaux) / 2 couleurs (N&B)
  - Outil pipette avec tolérance réglable
  - Effets de postérisation (réduction de niveaux de gris)
  - Effets halftone (trames de points authentiques)
  - Export par couche ou ZIP complet
  - Interface complète côté client (JavaScript)

### Améliorations techniques
- **Persistence BDD garantie** : Base de données dans userData, jamais écrasée
- **Cross-platform robuste** : Sessions et chemins compatibles Windows/Linux/macOS
- **Timeouts augmentés** : 120s pour opérations lourdes (images, PDF)
- **Erreurs explicites** : Messages 403 détaillés avec instructions de correction
- **Logs détaillés** : Caddy logs dans `/tmp/caddy_duplicator.log`

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

## 📋 TODO (v1.4.0+)

- **Séparateur Riso** : 
  - Corriger les trames (halftone)
  - Améliorer la navigation de la page
- **FrankenPHP pour Linux** : Intégrer FrankenPHP pour simplifier le déploiement Linux AppImage (binaire statique)
- **Support macOS** : Vérifier et tester le fonctionnement complet sous macOS
- **Statistiques de remplissage** : Statistique de remplissage de la page

## 🐛 Dépannage

### Problèmes courants

#### Erreur 502 Bad Gateway
- **Cause** : PHP ne répond pas ou crash
- **Solutions** :
  1. Vérifier que les pages sont dans `$page_secure` (app/public/index.php et app/index.php)
  2. Vérifier les logs Caddy : `/tmp/caddy_duplicator.log` (Linux) ou `%TEMP%\caddy_duplicator.log` (Windows)
  3. Vérifier que les extensions PHP sont chargées (sqlite3, gd, fileinfo)

#### Page blanche ou 403
- **Cause** : Page manquante dans `$page_secure`
- **Solution** : L'erreur affiche maintenant comment corriger (liste des pages à ajouter)

#### La BDD disparaît après mise à jour
- **Résolu depuis v1.2.0** : La BDD persiste dans userData
- **Emplacements** :
  - Windows: `C:\Users\USERNAME\AppData\Roaming\Duplicator\duplinew.sqlite`
  - Linux: `~/.config/Duplicator/duplinew.sqlite`
  - macOS: `~/Library/Application Support/Duplicator/duplinew.sqlite`

### Logs

#### Console Electron
Les logs de démarrage et erreurs sont affichés dans la console Electron (DevTools).

#### Logs Caddy
- Fichier : `/tmp/caddy_duplicator.log` (Linux/macOS) ou `%TEMP%\caddy_duplicator.log` (Windows)
- Contenu : Requêtes, erreurs, timeouts

#### Logs PHP
Affichés dans la console Electron avec préfixe `PHP Server:` ou `PHP Error:`

## 💾 Données et Persistence

### Emplacement de la base de données
La base de données SQLite est **automatiquement** stockée dans le dossier userData de l'application :
- **Windows** : `C:\Users\<USERNAME>\AppData\Roaming\Duplicator\duplinew.sqlite`
- **Linux** : `~/.config/Duplicator/duplinew.sqlite`
- **macOS** : `~/Library/Application Support/Duplicator/duplinew.sqlite`

### Sauvegardes
Les sauvegardes manuelles sont stockées dans `app/public/sauvegarde/` et peuvent être :
- Créées depuis l'admin
- Téléchargées
- Restaurées
- Utilisées lors de l'installation (import de BDD)
