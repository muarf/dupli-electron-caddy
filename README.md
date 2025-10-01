# Duplicator - Gestion de Comptabilité pour Collectif de Reproduction

Application de gestion de comptabilité pour collectifs de reproduction (duplicopieurs/photocopieurs) avec calcul des prix de revient, packagée en application Electron cross-platform avec serveur Caddy intégré.

## 🚀 Fonctionnalités

### 📊 Gestion Comptable
- Calcul des prix de revient pour les photocopies
- Gestion des coûts d'impression (papier, encre, maintenance)
- Suivi des volumes d'impression
- Rapports de rentabilité

### 📄 Traitement de Documents
- Imposition de PDF (mise en page optimisée)
- Unimposition de PDF (séparation des pages)
- Interface web moderne pour la gestion des documents
- Support de multiples formats

### 🔧 Technique
- Serveur Caddy intégré pour la portabilité
- Support PHP avec serveur intégré
- Application Electron cross-platform (Windows, Linux, macOS)
- Interface utilisateur intuitive

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

## 🚀 Déploiement

### GitHub Actions

Le projet utilise GitHub Actions pour :
- Tests automatiques
- Build cross-platform
- Publication automatique des releases

### Releases

Les releases sont automatiquement créées avec :
- Windows: `Duplicator-Caddy-Setup-{version}.exe`
- Linux: `Duplicator-{version}.AppImage`
- macOS: `Duplicator-{version}.dmg`

## 🐛 Dépannage

### Problèmes courants

1. **Caddy ne démarre pas** : Vérifiez que les binaires sont téléchargés
2. **PHP ne répond pas** : Vérifiez les logs dans la console
3. **Port déjà utilisé** : Changez le port dans le Caddyfile

### Logs

Les logs sont affichés dans la console de l'application Electron.

## 🤝 Contribution

1. Fork le projet
2. Créez une branche feature (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📞 Support

Pour toute question ou problème, ouvrez une issue sur GitHub.
