#!/bin/bash

# Script de déploiement pour dupli-electron-caddy
# Ce script commit les changements de dupli-php-dev et crée une release

set -e  # Arrêter en cas d'erreur

echo "🚀 Début du processus de déploiement..."

# 1. Commit et push des changements dans dupli-php-dev
echo "📝 1. Commit et push des changements dans dupli-php-dev..."
cd /root/dupli-php-dev

# Ajouter tous les fichiers modifiés
git add .

# Commit avec un message descriptif (si il y a des changements)
if ! git diff --staged --quiet; then
    git commit -m "feat: Ajout fonctionnalité upload PDF pour aide_machines

- Upload de PDFs jusqu'à 20MB avec stockage permanent
- Interface d'upload dans admin_aide_machines.html.php
- Aperçu PDF intégré dans l'éditeur Summernote
- Gestion des sessions admin corrigée
- Limites PHP augmentées (upload_max_filesize, post_max_size)
- Sécurité: .htaccess pour protéger les uploads
- Traductions ajoutées pour l'interface PDF"
fi

# Push vers le repository distant
git push origin main

echo "✅ Changements commités et pushés dans dupli-php-dev"

# 2. Pull des changements dans dupli-electron-caddy/app
echo "📥 2. Pull des changements dans dupli-electron-caddy/app..."
cd /root/dupli-electron-caddy/app

# Stasher les changements locaux s'il y en a
if ! git diff --quiet || ! git diff --staged --quiet; then
    echo "Stash des changements locaux..."
    git stash push -m "Stash avant pull des changements PDF"
fi

# Basculer sur main et pull
git checkout main
git pull origin main

echo "✅ Changements récupérés dans dupli-electron-caddy/app"

# 3. Créer une nouvelle release
echo "🏷️ 3. Création d'une nouvelle release..."

# Aller dans le répertoire principal de dupli-electron-caddy
cd /root/dupli-electron-caddy/

# Obtenir la dernière release depuis GitHub
echo "🔍 Récupération de la dernière release depuis GitHub..."
LATEST_TAG=$(git ls-remote --tags origin | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1)

if [ -z "$LATEST_TAG" ]; then
    echo "❌ Aucune release trouvée, utilisation de v1.0.0"
    LATEST_TAG="v1.0.0"
else
    echo "Dernière release trouvée: $LATEST_TAG"
fi

# Extraire la version et l'incrémenter
CURRENT_VERSION=${LATEST_TAG#v}  # Enlever le 'v' du début
echo "Version actuelle: $CURRENT_VERSION"

# Incrémenter la version patch (ex: 1.0.0 -> 1.0.1)
NEW_VERSION=$(node -p "
const version = '$CURRENT_VERSION';
const [major, minor, patch] = version.split('.').map(Number);
\`\${major}.\${minor}.\${patch + 1}\`
")

echo "Nouvelle version: $NEW_VERSION"

# Mettre à jour package.json
npm version $NEW_VERSION --no-git-tag-version

# Commit la nouvelle version
git add package.json
git commit -m "chore: bump version to $NEW_VERSION - Ajout fonctionnalité PDF upload"

# Créer un tag
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION: Ajout fonctionnalité upload PDF pour aide_machines"

# Push le tag
git push origin main
git push origin "v$NEW_VERSION"

echo "✅ Release v$NEW_VERSION créée et publiée"

# 4. Résumé
echo ""
echo "🎉 Déploiement terminé avec succès !"
echo "📋 Résumé:"
echo "   - Commit pushé dans dupli-php-dev"
echo "   - Changements récupérés dans dupli-electron-caddy/app"  
echo "   - Release v$NEW_VERSION créée"
echo ""
echo "🔗 Vous pouvez maintenant distribuer la nouvelle version de l'application Electron"
