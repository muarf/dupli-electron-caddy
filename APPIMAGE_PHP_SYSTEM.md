# AppImage avec PHP Système

## Vue d'ensemble

L'AppImage de Duplicator pour Linux a été modifiée pour **utiliser le PHP du système** au lieu d'embarquer PHP directement dans l'AppImage.

### Avantages de cette approche

1. **Taille réduite** : L'AppImage est beaucoup plus légère sans PHP embarqué (~100+ MB de moins)
2. **Sécurité** : Les mises à jour de sécurité PHP sont gérées par le système d'exploitation
3. **Compatibilité** : Utilise les extensions PHP déjà configurées sur le système
4. **Maintenance** : Pas besoin de maintenir des binaires PHP pour chaque distribution

### Fonctionnement

#### Au démarrage de l'AppImage

1. L'application vérifie si PHP est installé sur le système (Linux uniquement)
2. Si PHP est présent :
   - L'application démarre normalement avec Caddy et PHP système
   - Utilise les extensions PHP du système (gd, sqlite3, mbstring, xml)
3. Si PHP est absent :
   - Une page d'aide s'affiche automatiquement
   - La page contient des instructions d'installation selon la distribution Linux
   - L'utilisateur peut copier-coller les commandes d'installation

#### Page d'aide PHP (`php-install-guide.html`)

Une page HTML statique moderne et interactive qui affiche :
- Instructions d'installation pour Debian/Ubuntu/Mint
- Instructions d'installation pour Fedora/RHEL/CentOS
- Instructions d'installation pour Arch/Manjaro
- Liste des extensions PHP requises
- Détection automatique de l'architecture système
- Boutons de copie pour faciliter l'installation

### Prérequis PHP

L'application nécessite PHP avec les extensions suivantes :

```bash
# Debian/Ubuntu/Linux Mint
sudo apt update
sudo apt install php php-cli php-gd php-sqlite3 php-mbstring php-xml

# Fedora/RHEL/CentOS
sudo dnf install php php-cli php-gd php-pdo php-mbstring php-xml

# Arch Linux/Manjaro
sudo pacman -S php php-gd php-sqlite
```

### Architecture technique

#### Fichiers modifiés

1. **`main-caddy.js`**
   - Nouvelle fonction `checkPhpInstalled()` : Vérifie la présence de PHP
   - Modification de `createWindow()` : Affiche la page d'aide si PHP absent (Linux uniquement)
   - Modification de `startPhpFpm()` : Configuration adaptative selon la plateforme
     - Linux AppImage : PHP système sans php.ini personnalisé
     - Windows : PHP embarqué avec extensions et php.ini
     - macOS : PHP système

2. **`electron-builder-caddy.yml`**
   - Ajout de `php-install-guide.html` dans les fichiers
   - Configuration Linux : Exclusion de `php/**/*` du build
   - Configuration Windows/Mac : Conservation de PHP embarqué

3. **`php-install-guide.html`**
   - Page d'aide HTML statique
   - Design moderne et responsive
   - Instructions par distribution
   - Boutons de copie interactifs

#### Détection de PHP

```javascript
function checkPhpInstalled() {
    return new Promise((resolve) => {
        const { exec } = require('child_process');
        exec('php --version', (error, stdout, stderr) => {
            if (error) {
                console.error('PHP n\'est pas installé ou non accessible:', error.message);
                resolve(false);
            } else {
                console.log('PHP détecté:', stdout.split('\n')[0]);
                resolve(true);
            }
        });
    });
}
```

#### Configuration PHP par plateforme

- **Linux AppImage** : Utilise `php` système avec arguments `-d` pour la configuration
- **Windows** : Utilise PHP embarqué avec `-c php.ini` et `extension_dir`
- **macOS** : PHP système (à adapter selon les besoins)

### Builds par plateforme

| Plateforme | PHP | Taille AppImage/Package | Notes |
|------------|-----|-------------------------|-------|
| Linux (AppImage) | Système | ~200 MB (sans PHP) | Nécessite PHP pré-installé |
| Windows (NSIS/Portable) | Embarqué | ~300 MB | PHP inclus |
| macOS (DMG) | Système | ~250 MB | PHP système recommandé |

### Tests

#### Test avec PHP installé

```bash
# Vérifier PHP
php --version

# Lancer l'AppImage
./Duplicator-*.AppImage
# → L'application devrait démarrer normalement
```

#### Test sans PHP installé

```bash
# Désinstaller temporairement PHP (ou tester dans un conteneur)
# Lancer l'AppImage
./Duplicator-*.AppImage
# → La page d'aide devrait s'afficher
```

#### Test dans Docker

```bash
# Créer un conteneur Ubuntu sans PHP
docker run -it --rm \
  -e DISPLAY=$DISPLAY \
  -v /tmp/.X11-unix:/tmp/.X11-unix \
  -v $(pwd):/app \
  ubuntu:22.04 bash

# Dans le conteneur
cd /app
./Duplicator-*.AppImage
# → Devrait afficher la page d'aide

# Installer PHP
apt update && apt install -y php php-cli php-gd php-sqlite3 php-mbstring php-xml

# Relancer l'AppImage
./Duplicator-*.AppImage
# → Devrait démarrer normalement
```

### Dépannage

#### PHP installé mais non détecté

```bash
# Vérifier que PHP est dans le PATH
which php

# Vérifier la version
php --version

# Vérifier les extensions
php -m | grep -E "(gd|sqlite|mbstring|xml)"
```

#### Extensions manquantes

```bash
# Installer les extensions manquantes
sudo apt install php-gd php-sqlite3 php-mbstring php-xml

# Vérifier l'installation
php -m
```

#### Problèmes de permissions

```bash
# Rendre l'AppImage exécutable
chmod +x Duplicator-*.AppImage

# Lancer avec des logs
./Duplicator-*.AppImage --verbose
```

### Migration depuis l'ancienne version

Les utilisateurs de l'ancienne AppImage (avec PHP embarqué) doivent :

1. Installer PHP sur leur système avant de mettre à jour
2. Ou laisser l'application afficher la page d'aide au premier lancement
3. Suivre les instructions d'installation
4. Redémarrer l'application

### Développement

#### Mode développement

En mode développement, le code détecte automatiquement l'environnement :

```javascript
const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
```

#### Build

```bash
# Build pour Linux (sans PHP)
npm run build:caddy -- --linux

# Build pour Windows (avec PHP)
npm run build:caddy -- --win

# Build pour toutes les plateformes
npm run build:caddy
```

### Future améliorations possibles

1. **Détection périodique** : Vérifier toutes les X secondes si PHP a été installé
2. **IPC Communication** : Permettre à la page d'aide de communiquer avec le processus principal
3. **Installation guidée** : Script d'installation automatique avec sudo prompt
4. **Support Flatpak/Snap** : Adapter pour d'autres formats de packaging
5. **Extensions optionnelles** : Vérifier et suggérer des extensions additionnelles

### Références

- [Electron Builder](https://www.electron.build/)
- [AppImage Best Practices](https://docs.appimage.org/)
- [PHP CLI Server](https://www.php.net/manual/en/features.commandline.webserver.php)

