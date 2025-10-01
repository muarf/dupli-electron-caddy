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

## 📁 Structure du projet

```
dupli-electron-caddy/
├── app/                    # Application PHP
│   ├── public/            # Fichiers publics
│   └── ...
├── caddy/                 # Binaires Caddy
├── php/                   # Binaires PHP
├── scripts/               # Scripts utilitaires
├── tests/                 # Tests
├── main-caddy.js          # Processus principal Electron
├── Caddyfile              # Configuration Caddy
└── package.json
```

## 🔧 Configuration

### Caddy

Le serveur Caddy est configuré via le fichier `Caddyfile` :

```
:8000 {
    reverse_proxy 127.0.0.1:8001
    
    header {
        X-Content-Type-Options nosniff
        X-Frame-Options SAMEORIGIN
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
    }
}
```

### PHP

PHP fonctionne en mode serveur intégré sur le port 8001, configuré avec :
- `upload_max_filesize=50M`
- `post_max_size=50M`
- `max_execution_time=300`
- `memory_limit=256M`


### Releases

Les releases sont automatiquement créées avec :
- Windows: `Duplicator-Caddy-Setup-{version}.exe`
- Linux: `Duplicator-{version}.AppImage`
- macOS: `Duplicator-{version}.dmg`

## 🐛 Dépannage

### Problèmes courants

Dites moi !

### Logs

Les logs sont affichés dans la console de l'application Electron.
