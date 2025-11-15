#!/bin/bash

# Script de test pour la détection PHP dans Duplicator AppImage
# Ce script permet de tester le comportement avec et sans PHP

echo "======================================"
echo "Test de détection PHP - Duplicator"
echo "======================================"
echo ""

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les résultats
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
    else
        echo -e "${RED}✗${NC} $2"
    fi
}

# Test 1: Vérifier si PHP est installé
echo "Test 1: Vérification de l'installation PHP"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php --version | head -n 1)
    print_status 0 "PHP est installé: $PHP_VERSION"
    PHP_INSTALLED=1
else
    print_status 1 "PHP n'est pas installé"
    PHP_INSTALLED=0
fi
echo ""

# Test 2: Vérifier les extensions PHP requises
if [ $PHP_INSTALLED -eq 1 ]; then
    echo "Test 2: Vérification des extensions PHP"
    
    REQUIRED_EXTENSIONS=("gd" "sqlite3" "mbstring" "xml")
    ALL_EXTENSIONS_OK=1
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -q "^$ext$"; then
            print_status 0 "Extension $ext présente"
        else
            print_status 1 "Extension $ext manquante"
            ALL_EXTENSIONS_OK=0
        fi
    done
    echo ""
    
    if [ $ALL_EXTENSIONS_OK -eq 1 ]; then
        echo -e "${GREEN}Toutes les extensions requises sont présentes !${NC}"
    else
        echo -e "${YELLOW}Certaines extensions sont manquantes.${NC}"
        echo "Pour installer les extensions manquantes:"
        echo ""
        echo "Debian/Ubuntu:"
        echo "  sudo apt install php-gd php-sqlite3 php-mbstring php-xml"
        echo ""
        echo "Fedora/RHEL:"
        echo "  sudo dnf install php-gd php-pdo php-mbstring php-xml"
        echo ""
        echo "Arch Linux:"
        echo "  sudo pacman -S php-gd php-sqlite"
    fi
else
    echo "Test 2: Ignoré (PHP non installé)"
    echo ""
    echo -e "${YELLOW}PHP n'est pas installé sur votre système.${NC}"
    echo "Pour installer PHP avec les extensions requises:"
    echo ""
    echo "Debian/Ubuntu/Linux Mint:"
    echo "  sudo apt update"
    echo "  sudo apt install php php-cli php-gd php-sqlite3 php-mbstring php-xml"
    echo ""
    echo "Fedora/RHEL/CentOS:"
    echo "  sudo dnf install php php-cli php-gd php-pdo php-mbstring php-xml"
    echo ""
    echo "Arch Linux/Manjaro:"
    echo "  sudo pacman -S php php-gd php-sqlite"
fi
echo ""

# Test 3: Vérifier si l'AppImage existe
echo "Test 3: Recherche de l'AppImage"
APPIMAGE_FOUND=0
for file in dist/Duplicator-*.AppImage; do
    if [ -f "$file" ]; then
        print_status 0 "AppImage trouvé: $file"
        APPIMAGE_FILE="$file"
        APPIMAGE_FOUND=1
        break
    fi
done

if [ $APPIMAGE_FOUND -eq 0 ]; then
    print_status 1 "Aucune AppImage trouvée dans dist/"
    echo ""
    echo "Pour créer l'AppImage, exécutez:"
    echo "  npm run build:caddy -- --linux"
fi
echo ""

# Test 4: Test de démarrage du serveur PHP (si PHP est installé)
if [ $PHP_INSTALLED -eq 1 ]; then
    echo "Test 4: Test du serveur PHP intégré"
    
    # Créer un fichier PHP de test temporaire
    TEST_DIR=$(mktemp -d)
    echo "<?php phpinfo(); ?>" > "$TEST_DIR/test.php"
    
    # Démarrer le serveur PHP en arrière-plan
    php -S 127.0.0.1:9999 -t "$TEST_DIR" &> /dev/null &
    PHP_PID=$!
    
    # Attendre que le serveur démarre
    sleep 2
    
    # Tester la connexion
    if curl -s http://127.0.0.1:9999/test.php | grep -q "PHP Version"; then
        print_status 0 "Le serveur PHP intégré fonctionne correctement"
    else
        print_status 1 "Problème avec le serveur PHP intégré"
    fi
    
    # Arrêter le serveur de test
    kill $PHP_PID 2>/dev/null
    rm -rf "$TEST_DIR"
else
    echo "Test 4: Ignoré (PHP non installé)"
fi
echo ""

# Test 5: Vérifier les fichiers nécessaires
echo "Test 5: Vérification des fichiers de configuration"

FILES_TO_CHECK=(
    "main-caddy.js"
    "php-install-guide.html"
    "electron-builder-caddy.yml"
    "Caddyfile"
)

for file in "${FILES_TO_CHECK[@]}"; do
    if [ -f "$file" ]; then
        print_status 0 "$file présent"
    else
        print_status 1 "$file manquant"
    fi
done
echo ""

# Résumé final
echo "======================================"
echo "Résumé"
echo "======================================"
echo ""

if [ $PHP_INSTALLED -eq 1 ] && [ $ALL_EXTENSIONS_OK -eq 1 ]; then
    echo -e "${GREEN}✓ Votre système est prêt pour exécuter Duplicator AppImage${NC}"
    if [ $APPIMAGE_FOUND -eq 1 ]; then
        echo ""
        echo "Vous pouvez lancer l'application avec:"
        echo "  ./$APPIMAGE_FILE"
    fi
elif [ $PHP_INSTALLED -eq 1 ]; then
    echo -e "${YELLOW}⚠ PHP est installé mais certaines extensions sont manquantes${NC}"
    echo "Installez les extensions manquantes puis relancez ce test."
else
    echo -e "${YELLOW}⚠ PHP n'est pas installé${NC}"
    echo "L'AppImage affichera une page d'aide au démarrage."
    echo "Installez PHP avec les commandes ci-dessus puis relancez l'application."
fi
echo ""

# Test optionnel: Simuler l'absence de PHP
echo "======================================"
echo "Test optionnel"
echo "======================================"
echo ""
echo "Pour tester le comportement sans PHP, vous pouvez:"
echo "1. Utiliser Docker:"
echo "   docker run -it --rm -v \$(pwd):/app ubuntu:22.04 /app/$APPIMAGE_FILE"
echo ""
echo "2. Ou renommer temporairement PHP:"
echo "   sudo mv /usr/bin/php /usr/bin/php.backup"
echo "   # Tester l'AppImage"
echo "   sudo mv /usr/bin/php.backup /usr/bin/php"
echo ""

