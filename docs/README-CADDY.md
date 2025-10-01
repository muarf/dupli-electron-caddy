# Duplicator - Version Caddy

Cette version de l'application Duplicator utilise Caddy comme serveur web au lieu du serveur PHP intégré, offrant une meilleure compatibilité cross-platform.

## 🚀 Installation

### Prérequis
- Node.js 16+
- npm ou yarn
- Caddy (téléchargé automatiquement)
- PHP-FPM

### Installation des dépendances
```bash
npm install
```

### Téléchargement de Caddy
```bash
npm run download-caddy
```

## 🧪 Tests

### Exécuter tous les tests
```bash
npm test
```

### Tests unitaires
```bash
npm run test:unit
```

### Tests d'intégration
```bash
npm run test:integration
```

### Tests end-to-end
```bash
npm run test:e2e
```

### Tests avec couverture
```bash
npm run test:coverage
```

## 🏗️ Build

### Build avec Caddy
```bash
npm run build:caddy
```

### Build standard (PHP intégré)
```bash
npm run build
```

## 🎯 Utilisation

### Démarrage avec Caddy
```bash
npm run start:caddy
```

### Démarrage avec PHP intégré
```bash
npm run start:php
```

## 📁 Structure

```
dupli-electron-caddy/
├── main-caddy.js          # Point d'entrée avec Caddy
├── main.js                # Point d'entrée avec PHP intégré
├── Caddyfile              # Configuration Caddy
├── php-fpm.conf           # Configuration PHP-FPM
├── scripts/
│   └── download-caddy.js  # Script de téléchargement Caddy
├── tests/
│   ├── unit/              # Tests unitaires
│   ├── integration/       # Tests d'intégration
│   ├── e2e/               # Tests end-to-end
│   └── helpers/           # Utilitaires de test
└── app/                   # Application PHP
```

## 🔧 Configuration

### Caddy
Le fichier `Caddyfile` configure :
- Port 8000
- Gestion des fichiers statiques
- Intégration PHP-FPM
- Headers de sécurité
- Logs d'erreur

### PHP-FPM
Le fichier `php-fpm.conf` configure :
- Socket Unix
- Gestion des processus
- Configuration PHP
- Sessions

## 🐛 Dépannage

### Caddy ne démarre pas
1. Vérifier que Caddy est téléchargé : `ls caddy/`
2. Vérifier les permissions : `chmod +x caddy/caddy`
3. Vérifier la configuration : `caddy validate --config Caddyfile`

### PHP-FPM ne démarre pas
1. Vérifier que PHP-FPM est installé : `php-fpm --version`
2. Vérifier la configuration : `php-fpm -t --fpm-config php-fpm.conf`
3. Vérifier les permissions du socket

### Tests qui échouent
1. Vérifier que les dépendances de test sont installées
2. Vérifier que les ports de test sont libres
3. Exécuter les tests individuellement pour isoler le problème

## 📊 Performance

### Comparaison des versions
- **PHP intégré** : Plus simple, moins de dépendances
- **Caddy** : Meilleures performances, plus robuste, cross-platform

### Métriques
- Temps de démarrage : ~3-5 secondes
- Utilisation mémoire : ~100-200MB
- Taille de distribution : ~150-200MB

## 🔒 Sécurité

### Headers de sécurité
- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- X-XSS-Protection: 1; mode=block
- Referrer-Policy: strict-origin-when-cross-origin

### CORS
- Access-Control-Allow-Origin: *
- Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
- Access-Control-Allow-Headers: Content-Type, Authorization

## 🌐 Cross-platform

### Plateformes supportées
- Windows (x64)
- Linux (x64)
- macOS (x64)

### Binaires Caddy
- Windows: `caddy.exe`
- Linux: `caddy`
- macOS: `caddy`

## 📝 Logs

### Fichiers de log
- Caddy: `/tmp/duplicator_caddy_errors.log`
- PHP-FPM: `/tmp/duplicator_php_errors.log`
- Sessions: `/tmp/duplicator_sessions/`

### Niveaux de log
- ERROR: Erreurs critiques
- WARN: Avertissements
- INFO: Informations générales
- DEBUG: Détails de débogage

## 🤝 Contribution

### Ajout de tests
1. Créer le fichier de test dans le bon répertoire
2. Suivre la convention de nommage
3. Ajouter les mocks nécessaires
4. Documenter les cas de test

### Modification de la configuration
1. Modifier le fichier de configuration
2. Tester la configuration
3. Mettre à jour la documentation
4. Ajouter des tests si nécessaire

## 📚 Ressources

- [Documentation Caddy](https://caddyserver.com/docs/)
- [Documentation PHP-FPM](https://www.php.net/manual/en/install.fpm.php)
- [Documentation Electron](https://www.electronjs.org/docs)
- [Documentation Jest](https://jestjs.io/docs/getting-started)

