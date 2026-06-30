#!/bin/bash
echo ""
echo "========================================================"
echo "  Duplicator Studio - Installation IA Locale (Mac/Linux)"
echo "========================================================"
echo ""
echo "Ce processus va telecharger et installer les modeles IA locaux (environ 1.5 Go)."
echo "Veuillez patienter, cela peut prendre quelques minutes selon votre connexion..."
echo ""

TARGET_DIR="$1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"

if [ -z "$TARGET_DIR" ]; then
    TARGET_DIR="$SCRIPT_DIR/../bin/ai_models_cache"
fi

# Ensure Python 3 is available
if ! command -v python3 &> /dev/null; then
    echo "[Erreur] python3 n'est pas installe sur ce systeme."
    exit 1
fi

# Create venv if it doesn't exist
VENV_DIR="$TARGET_DIR/venv"
if [ ! -d "$VENV_DIR" ]; then
    echo "Creation de l'environnement virtuel dans $VENV_DIR..."
    python3 -m venv "$VENV_DIR"
fi

# Activate venv
source "$VENV_DIR/bin/activate"

# Upgrade pip
python3 -m pip install --upgrade pip

# Run the installation script
INSTALL_SCRIPT="$SCRIPT_DIR/../app/api/scripts/install.py"
python3 "$INSTALL_SCRIPT" "$TARGET_DIR"

echo ""
echo "Installation terminee ! L'environnement virtuel se trouve dans : $VENV_DIR"
echo "Vous pouvez fermer ce terminal."
read -p "Appuyez sur Entree pour quitter..."
