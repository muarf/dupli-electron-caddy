#!/bin/bash

# 🚀 Script rapide pour pousser vers GitHub
# Usage: ./quick-github-push.sh VOTRE_USERNAME

if [ -z "$1" ]; then
    echo "❌ Usage: ./quick-github-push.sh VOTRE_USERNAME_GITHUB"
    echo "   Exemple: ./quick-github-push.sh monusername"
    exit 1
fi

USERNAME="$1"
REPO_URL="https://github.com/${USERNAME}/dupli-php-dev.git"

echo "📋 Configuration du repository..."
echo "   URL: $REPO_URL"
echo ""

# Vérifier si le remote existe déjà
if git remote get-url origin &> /dev/null; then
    echo "⚠️  Le remote 'origin' existe déjà"
    git remote -v
    read -p "   Voulez-vous le remplacer ? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git remote remove origin
        git remote add origin "$REPO_URL"
        echo "✅ Remote 'origin' mis à jour"
    else
        echo "❌ Opération annulée"
        exit 1
    fi
else
    git remote add origin "$REPO_URL"
    echo "✅ Remote 'origin' ajouté"
fi

# Renommer la branche en main
echo ""
echo "🔄 Renommage de la branche en 'main'..."
git branch -M main
echo "✅ Branche renommée"

# Afficher le statut
echo ""
echo "📊 Statut actuel:"
git log --oneline -1
git remote -v

echo ""
echo "🎯 Prêt à pousser vers GitHub !"
echo ""
echo "⚠️  AVANT DE CONTINUER :"
echo "   1. Assurez-vous d'avoir créé le repository sur GitHub : https://github.com/new"
echo "   2. NE PAS initialiser avec README, .gitignore ou licence"
echo ""
read -p "Voulez-vous pousser maintenant ? (y/N) " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🚀 Push vers GitHub..."
    git push -u origin main
    
    if [ $? -eq 0 ]; then
        echo ""
        echo "✅✅✅ SUCCÈS ! ✅✅✅"
        echo ""
        echo "🎉 Votre repository est maintenant sur GitHub !"
        echo "🔗 Visitez : https://github.com/${USERNAME}/dupli-php-dev"
        echo ""
        echo "📚 Prochaines étapes :"
        echo "   - Consultez GITHUB_SETUP.md pour merger avec dupli-electron-caddy"
        echo "   - Mettez à jour les URLs dans README.md"
    else
        echo ""
        echo "❌ Erreur lors du push"
        echo "   Vérifiez que :"
        echo "   - Le repository existe sur GitHub"
        echo "   - Vous avez les droits d'accès"
        echo "   - Votre configuration Git est correcte"
    fi
else
    echo "❌ Push annulé"
    echo "   Vous pouvez pousser manuellement plus tard avec :"
    echo "   git push -u origin main"
fi
