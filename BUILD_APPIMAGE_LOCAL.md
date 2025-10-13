# Builder l'AppImage Linux en local

## Pourquoi builder en local ?

GitHub Actions a des limites d'espace disque qui peuvent empêcher le build automatique de l'AppImage. Builder en local permet de contourner ce problème.

## Prérequis

- Ubuntu/Debian Linux (ou autre distribution compatible)
- Node.js 18+
- Au moins 5 GB d'espace disque libre
- PHP installé (pour les tests)

## Étapes

### 1. Préparer l'environnement

```bash
cd /root/dupli-electron-caddy

# S'assurer d'être sur la bonne branche
git checkout fix-linux-appimage
git pull origin fix-linux-appimage

# Installer les dépendances
npm install
```

### 2. Nettoyer et préparer

```bash
# Nettoyer les anciens builds
rm -rf dist/

# Vider le dossier php/ pour économiser de l'espace
rm -rf php/*
mkdir -p php/

# Nettoyer les caches
npm cache clean --force
rm -rf node_modules/.cache
rm -rf ~/.cache/electron-builder
```

### 3. Builder l'AppImage

```bash
# Builder l'AppImage Linux
npm run build:caddy -- --linux

# L'AppImage sera dans dist/
ls -lh dist/*.AppImage
```

### 4. Tester l'AppImage

```bash
# Rendre exécutable
chmod +x dist/Duplicator-*.AppImage

# Tester
./dist/Duplicator-*.AppImage
```

## Publier sur GitHub

### Manuellement via l'interface web

1. Aller sur https://github.com/muarf/dupli-electron-caddy/releases
2. Créer une nouvelle release ou éditer une existante
3. Uploader l'AppImage depuis `dist/`

### Via GitHub CLI

```bash
# Créer une release
gh release create v1.x.x dist/Duplicator-*.AppImage \
  --title "Version 1.x.x" \
  --notes "Description des changements"

# Ou ajouter à une release existante
gh release upload v1.x.x dist/Duplicator-*.AppImage
```

## Dépannage

### Erreur "No space left on device"

```bash
# Vérifier l'espace disque
df -h

# Nettoyer Docker si installé
sudo docker system prune -af

# Supprimer les vieux noyaux
sudo apt autoremove
```

### Erreur de dépendances

```bash
# Installer les dépendances système
sudo apt-get update
sudo apt-get install -y libgtk-3-0 libnotify4 libnss3 libxss1 libxtst6
```

### L'AppImage ne démarre pas

```bash
# Extraire et inspecter
./Duplicator-*.AppImage --appimage-extract

# Vérifier le contenu
ls -la squashfs-root/
```

## Automatisation locale

Vous pouvez créer un script local pour automatiser le processus :

```bash
#!/bin/bash
# build-and-publish.sh

set -e

echo "=== Build et publication AppImage ==="

# Nettoyer
rm -rf dist/
rm -rf php/*
mkdir -p php/
npm cache clean --force

# Builder
npm run build:caddy -- --linux

# Trouver l'AppImage
APPIMAGE=$(ls dist/*.AppImage)
echo "AppImage créée: $APPIMAGE"

# Tester
chmod +x "$APPIMAGE"
echo "Test de l'AppImage..."
# "$APPIMAGE" --version

# Publier
read -p "Publier sur GitHub ? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Tag de version (ex: v1.3.15): " VERSION
    gh release create "$VERSION" "$APPIMAGE" \
      --title "Release $VERSION" \
      --notes "Build Linux avec PHP système"
    echo "✅ Publié !"
fi
```

## Notes importantes

- L'AppImage Linux utilise **PHP système**, pas PHP embarqué
- La taille devrait être d'environ 200-250 MB (vs 300+ MB avec PHP)
- Les utilisateurs devront installer PHP sur leur système
- La page d'aide s'affichera automatiquement si PHP est absent

## Configuration testée

- Ubuntu 20.04 LTS / 22.04 LTS
- Node.js 18.x
- npm 9.x
- Electron Builder 26.x
- Espace disque nécessaire : ~5 GB

