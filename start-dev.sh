#!/bin/bash
# Script pour lancer l'application en mode développement
# Les modifications dans app/ seront directement visibles sans rebuild

cd "$(dirname "$0")"

echo "🚀 Démarrage en mode développement..."
echo "📝 Les modifications dans app/ seront directement visibles"
echo ""

# Lancer Electron avec le serveur PHP système
npm run start:caddy
