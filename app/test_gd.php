<?php
/**
 * Script de test pour vérifier la disponibilité de l'extension GD
 * Usage: php test_gd.php
 */

echo "=== Test de l'extension PHP GD ===\n\n";

// 1. Vérifier si GD est chargé
if (extension_loaded('gd')) {
    echo "✅ Extension GD est chargée !\n\n";
    
    // 2. Informations sur GD
    $gd_info = gd_info();
    echo "--- Informations GD ---\n";
    foreach ($gd_info as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'OUI' : 'NON';
        }
        echo sprintf("%-30s: %s\n", $key, $value);
    }
    echo "\n";
    
    // 3. Vérifier les fonctions nécessaires
    echo "--- Fonctions nécessaires pour Taux de Remplissage ---\n";
    $required_functions = [
        'getimagesize',
        'imagecreatefromjpeg',
        'imagecreatefrompng',
        'imagecreatefromgif',
        'imagecolorat',
        'imagecolorsforindex',
        'imagesx',
        'imagesy',
        'imagedestroy'
    ];
    
    $all_ok = true;
    foreach ($required_functions as $func) {
        $exists = function_exists($func);
        echo sprintf("%-30s: %s\n", $func, $exists ? '✅ OUI' : '❌ NON');
        if (!$exists) {
            $all_ok = false;
        }
    }
    
    echo "\n";
    
    if ($all_ok) {
        echo "🎉 Toutes les fonctions sont disponibles !\n";
        echo "✅ La page 'Taux de Remplissage' devrait fonctionner correctement.\n\n";
        
        // 4. Test pratique avec une image de test
        echo "--- Test de création d'image ---\n";
        try {
            $img = imagecreatetruecolor(100, 100);
            if ($img) {
                echo "✅ Création d'image : OK\n";
                
                // Remplir avec une couleur
                $white = imagecolorallocate($img, 255, 255, 255);
                imagefill($img, 0, 0, $white);
                echo "✅ Remplissage de couleur : OK\n";
                
                // Lire un pixel
                $color = imagecolorat($img, 50, 50);
                $rgb = imagecolorsforindex($img, $color);
                echo sprintf("✅ Lecture de pixel : OK (RGB: %d, %d, %d)\n", 
                    $rgb['red'], $rgb['green'], $rgb['blue']);
                
                imagedestroy($img);
                echo "✅ Libération mémoire : OK\n\n";
                
                echo "🎯 GD fonctionne parfaitement !\n";
            } else {
                echo "❌ Échec de création d'image\n";
            }
        } catch (Exception $e) {
            echo "❌ Erreur lors du test : " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "⚠️  Certaines fonctions sont manquantes.\n";
        echo "❌ La page 'Taux de Remplissage' ne fonctionnera PAS.\n";
    }
    
} else {
    echo "❌ Extension GD n'est PAS chargée !\n\n";
    echo "Pour activer GD :\n";
    echo "1. Sur Linux : apt-get install php-gd\n";
    echo "2. Sur Windows : Ajouter 'extension=gd2.dll' dans php.ini\n";
    echo "3. Redémarrer le serveur web\n\n";
    echo "Consultez README_GD_WINDOWS.md pour plus de détails.\n";
}

echo "\n=== Fin du test ===\n";
?>






