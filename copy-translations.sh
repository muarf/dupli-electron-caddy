#!/bin/bash

# Script pour copier les fichiers de traduction vers dupli-php-dev
# À exécuter depuis le répertoire dupli-php-dev

echo "🔄 Copie des fichiers de traduction..."

# Créer les répertoires nécessaires
mkdir -p app/controler/functions
mkdir -p app/models/admin
mkdir -p app/translations
mkdir -p app/view

# Copier les nouveaux fichiers
echo "📁 Copie des nouveaux fichiers..."
cp /workspace/app/controler/functions/simple_i18n.php app/controler/functions/
cp /workspace/app/models/admin/TranslationManager.php app/models/admin/
cp /workspace/app/models/admin_translations.php app/models/
cp /workspace/app/view/admin_translations.html.php app/view/

# Copier les traductions
echo "🌍 Copie des fichiers de traduction..."
cp -r /workspace/app/translations/* app/translations/

# Copier les vues modifiées
echo "📄 Copie des vues modifiées..."
cp /workspace/app/view/header.html.php app/view/
cp /workspace/app/view/footer.html.php app/view/
cp /workspace/app/view/admin.html.php app/view/
cp /workspace/app/view/accueil.html.php app/view/
cp /workspace/app/view/admin.aide.html.php app/view/
cp /workspace/app/view/admin.bdd.html.php app/view/
cp /workspace/app/view/admin.edit.html.php app/view/
cp /workspace/app/view/admin.login.html.php app/view/
cp /workspace/app/view/admin.mot.html.php app/view/
cp /workspace/app/view/admin.mots.html.php app/view/
cp /workspace/app/view/admin.tirage.html.php app/view/
cp /workspace/app/view/admin_aide_machines.html.php app/view/
cp /workspace/app/view/changement.html.php app/view/
cp /workspace/app/view/error.html.php app/view/
cp /workspace/app/view/imposition.html.php app/view/
cp /workspace/app/view/imposition_tracts.html.php app/view/
cp /workspace/app/view/pdf_to_png.html.php app/view/
cp /workspace/app/view/png_to_pdf.html.php app/view/
cp /workspace/app/view/riso_separator.html.php app/view/
cp /workspace/app/view/setup.html.php app/view/
cp /workspace/app/view/stats.html.php app/view/
cp /workspace/app/view/taux_remplissage.html.php app/view/
cp /workspace/app/view/tirage_multimachines.html.php app/view/
cp /workspace/app/view/unimpose.html.php app/view/

# Copier les modèles modifiés
echo "🔧 Copie des modèles modifiés..."
cp /workspace/app/models/header.php app/models/
cp /workspace/app/models/footer.php app/models/
cp /workspace/app/models/accueil.php app/models/
cp /workspace/app/models/admin.php app/models/
cp /workspace/app/models/changement.php app/models/
cp /workspace/app/models/imposition.php app/models/
cp /workspace/app/models/imposition_tracts.php app/models/
cp /workspace/app/models/pdf_to_png.php app/models/
cp /workspace/app/models/png_to_pdf.php app/models/
cp /workspace/app/models/riso_separator.php app/models/
cp /workspace/app/models/setup.php app/models/
cp /workspace/app/models/stats.php app/models/
cp /workspace/app/models/taux_remplissage.php app/models/
cp /workspace/app/models/tirage_multimachines.php app/models/
cp /workspace/app/models/unimpose.php app/models/
cp /workspace/app/models/admin/AideManager.php app/models/admin/
cp /workspace/app/models/admin/ChangesManager.php app/models/admin/
cp /workspace/app/models/admin/MachineManager.php app/models/admin/
cp /workspace/app/models/admin/NewsManager.php app/models/admin/
cp /workspace/app/models/admin/PriceManager.php app/models/admin/
cp /workspace/app/models/admin/StatsManager.php app/models/admin/
cp /workspace/app/models/admin/TirageManager.php app/models/admin/

# Copier le fichier principal modifié
echo "⚙️ Copie du fichier principal..."
cp /workspace/app/index.php app/

echo "✅ Copie terminée !"
echo "📋 Résumé des modifications :"
echo "   - Système de traduction complet (4 langues)"
echo "   - Interface d'administration des traductions"
echo "   - Toutes les vues traduites"
echo "   - Tous les modèles mis à jour"
echo ""
echo "🚀 Vous pouvez maintenant tester les traductions !"