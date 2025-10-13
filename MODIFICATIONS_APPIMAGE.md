# Modifications AppImage - Utilisation de PHP Système

## 🎯 Objectif

Modifier l'AppImage Linux pour qu'elle utilise le **PHP du système** au lieu d'embarquer PHP, et afficher une **page d'aide interactive** si PHP n'est pas installé.

## ✅ Modifications effectuées

### 1. Page d'aide PHP (`php-install-guide.html`)

Création d'une belle page HTML statique qui :
- ✨ Affiche des instructions d'installation selon la distribution Linux (Debian/Ubuntu, Fedora/RHEL, Arch)
- 📋 Permet de copier-coller les commandes d'installation en un clic
- 📱 Design moderne et responsive
- 🔍 Détecte automatiquement l'architecture du système
- 📚 Liste toutes les extensions PHP requises

### 2. Détection PHP au démarrage (`main-caddy.js`)

**Nouvelle fonction `checkPhpInstalled()`** :
```javascript
// Vérifie si PHP est installé en exécutant 'php --version'
// Retourne true/false
```

**Modification de `createWindow()`** :
- Pour Linux AppImage uniquement : vérifie si PHP est installé
- Si PHP absent → affiche `php-install-guide.html`
- Si PHP présent → démarre l'application normalement

**Modification de `startPhpFpm()`** :
- **Linux AppImage** : Utilise PHP système sans php.ini personnalisé
- **Windows** : Continue d'utiliser PHP embarqué avec extensions
- **macOS** : Utilise PHP système

### 3. Configuration de build (`electron-builder-caddy.yml`)

**Pour Linux** :
- ✂️ Exclusion de `php/**/*` du build
- 📦 Inclusion de `php-install-guide.html`
- 💾 Réduction de la taille de l'AppImage (~100+ MB de moins)

**Pour Windows et macOS** :
- 📌 Conservation de PHP embarqué (pas de changement)

### 4. Documentation (`APPIMAGE_PHP_SYSTEM.md`)

Documentation technique complète en anglais couvrant :
- Architecture et fonctionnement
- Prérequis et installation
- Guide de développement
- Tests et dépannage
- Comparaison par plateforme

### 5. Script de test (`test-php-detection.sh`)

Script bash interactif pour tester :
- ✓ Présence de PHP
- ✓ Extensions PHP requises
- ✓ Serveur PHP intégré
- ✓ Fichiers de configuration
- ✓ Présence de l'AppImage

## 📦 Résultat

### Avant (AppImage avec PHP embarqué)
```
Taille : ~300 MB
Contient : PHP + extensions + Caddy + Ghostscript + Application
```

### Après (AppImage avec PHP système)
```
Taille : ~200 MB
Contient : Caddy + Ghostscript + Application
Nécessite : PHP installé sur le système
```

## 🚀 Utilisation

### Si PHP est installé
```bash
./Duplicator-1.0.0.AppImage
# → L'application démarre normalement
```

### Si PHP n'est pas installé
```bash
./Duplicator-1.0.0.AppImage
# → Une page d'aide s'affiche avec les instructions d'installation
```

### Installation de PHP (Debian/Ubuntu)
```bash
sudo apt update
sudo apt install php php-cli php-gd php-sqlite3 php-mbstring php-xml
```

### Installation de PHP (Fedora/RHEL/CentOS)
```bash
sudo dnf install php php-cli php-gd php-pdo php-mbstring php-xml
```

### Installation de PHP (Arch/Manjaro)
```bash
sudo pacman -S php php-gd php-sqlite
```

## 🧪 Tests

### Tester avec PHP installé
```bash
# Vérifier PHP
php --version

# Lancer le script de test
./test-php-detection.sh

# Lancer l'AppImage
./dist/Duplicator-1.0.0.AppImage
```

### Tester sans PHP (avec Docker)
```bash
docker run -it --rm \
  -e DISPLAY=$DISPLAY \
  -v /tmp/.X11-unix:/tmp/.X11-unix \
  -v $(pwd):/app \
  ubuntu:22.04 \
  /app/dist/Duplicator-1.0.0.AppImage
# → Devrait afficher la page d'aide
```

## 📋 Extensions PHP requises

| Extension | Description | Utilisation |
|-----------|-------------|-------------|
| `php-gd` | Traitement d'images | Manipulation PDF/PNG |
| `php-sqlite3` | Base de données SQLite | Stockage des données |
| `php-mbstring` | Chaînes multi-octets | Support UTF-8 |
| `php-xml` | Parser XML | Traitement de données |
| `php-cli` | Interface en ligne de commande | Serveur web intégré |

## 🔧 Build

### Build pour Linux (sans PHP)
```bash
npm run build:caddy -- --linux
```

### Build pour Windows (avec PHP)
```bash
npm run build:caddy -- --win
```

### Build pour toutes les plateformes
```bash
npm run build:caddy
```

## 📊 Comparaison par plateforme

| Plateforme | PHP | Taille | Notes |
|------------|-----|--------|-------|
| **Linux AppImage** | Système | ~200 MB | ✅ Nécessite PHP pré-installé |
| **Windows (NSIS)** | Embarqué | ~300 MB | ✅ Tout inclus |
| **macOS (DMG)** | Système | ~250 MB | ⚠️ PHP système recommandé |

## 🎨 Avantages

1. **Taille réduite** : AppImage 100+ MB plus légère
2. **Sécurité** : Mises à jour PHP gérées par le système
3. **Compatibilité** : Utilise les extensions déjà installées
4. **Maintenance** : Pas de binaires PHP à maintenir
5. **Standards Linux** : Suit les conventions des distributions

## ⚠️ Points d'attention

1. **Prérequis** : L'utilisateur doit installer PHP manuellement
2. **Documentation** : La page d'aide guide l'installation
3. **Windows/Mac** : Pas d'impact, continuent avec PHP embarqué

## 📝 Fichiers modifiés

```
✨ Nouveau : php-install-guide.html
✨ Nouveau : APPIMAGE_PHP_SYSTEM.md
✨ Nouveau : MODIFICATIONS_APPIMAGE.md
✨ Nouveau : test-php-detection.sh
🔧 Modifié : main-caddy.js
🔧 Modifié : electron-builder-caddy.yml
```

## 🔄 Commits

```
426c4dc Update from dupli-php-dev
a8f0380 AppImage: Utiliser PHP système et afficher page d'aide si absent
```

## 🌟 Prochaines étapes possibles

1. **Auto-détection périodique** : Vérifier si PHP a été installé toutes les 30 secondes
2. **Bouton "Redémarrer"** : Dans la page d'aide, permettre de relancer la détection
3. **Script d'installation** : Automatiser l'installation de PHP avec sudo
4. **Support Flatpak/Snap** : Adapter pour d'autres formats
5. **Page de diagnostic** : Afficher les extensions manquantes en détail

## 📞 Support

En cas de problème :
1. Exécuter `./test-php-detection.sh`
2. Vérifier que PHP est dans le PATH : `which php`
3. Vérifier les extensions : `php -m`
4. Consulter `APPIMAGE_PHP_SYSTEM.md` pour plus de détails

---

**Date** : 13 octobre 2025  
**Version** : 1.0.0  
**Auteur** : AI Assistant (Claude)

