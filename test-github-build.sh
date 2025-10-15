#!/bin/bash
set -e

echo "=== Simulation du build GitHub Actions ==="
echo ""

# Nettoyage comme dans le workflow
echo "=== Nettoyage agressif ==="
npm cache clean --force

if [ -d "php/" ]; then
  echo "Taille php/: $(du -sh php/ | cut -f1)"
  rm -rf php/*
fi
mkdir -p php/

echo "Nettoyage des fichiers temporaires de l'application..."
rm -rf app/public/tmp/* || true
rm -rf app/public/sauvegarde/* || true
rm -rf app/tmp/* || true
echo "Fichiers temporaires supprimés"

rm -rf ~/.cache/electron-builder || true
rm -rf node_modules/.cache || true

echo ""
echo "=== Vérifications avant build ==="
df -h /
echo ""

# Nettoyer /tmp
echo "Nettoyage du répertoire temporaire..."
sudo find /tmp -name "t-*" -type d -exec rm -rf {} + 2>/dev/null || true
echo "✅ Répertoire temporaire nettoyé"

# Build avec publish=always comme dans GitHub Actions
echo ""
echo "=== Build avec --publish=always ==="
npm run build:caddy -- --publish=always

echo ""
echo "=== Vérification de l'AppImage ==="
VERSION=$(node -p "require('./package.json').version")
APPIMAGE_FILE="dist/Duplicator-${VERSION}.AppImage"
echo "Version: $VERSION"
echo "Fichier attendu: $APPIMAGE_FILE"

if [ -f "$APPIMAGE_FILE" ]; then
  echo "✅ AppImage créé"
  ls -lh "$APPIMAGE_FILE"
else
  echo "❌ AppImage manquant"
  echo "Fichiers disponibles dans dist/:"
  ls -la dist/
  exit 1
fi

echo ""
echo "✅ TEST RÉUSSI"

