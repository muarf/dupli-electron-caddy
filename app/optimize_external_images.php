<?php
/**
 * Script pour optimiser et convertir les images externes en base64
 */

// Configuration
$maxWidth = 800;
$quality = 0.8;

/**
 * Compresse et convertit une image en base64 optimisé
 */
function optimizeImageToBase64($imagePath, $maxWidth = 800, $quality = 0.8) {
    if (!file_exists($imagePath)) {
        throw new Exception("Image non trouvée : $imagePath");
    }
    
    // Détecter le type d'image
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        throw new Exception("Impossible de lire les informations de l'image : $imagePath");
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Créer l'image selon son type
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($imagePath);
            break;
        default:
            throw new Exception("Type d'image non supporté : $mimeType");
    }
    
    if (!$image) {
        throw new Exception("Impossible de créer l'image depuis $imagePath");
    }
    
    // Obtenir les dimensions originales
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    
    echo "📏 Dimensions originales : {$originalWidth}x{$originalHeight}\n";
    
    // Calculer les nouvelles dimensions si nécessaire
    $newWidth = $originalWidth;
    $newHeight = $originalHeight;
    
    if ($originalWidth > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = ($originalHeight * $maxWidth) / $originalWidth;
        
        echo "🔄 Redimensionnement vers : {$newWidth}x{$newHeight}\n";
        
        // Créer une nouvelle image redimensionnée
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Compresser l'image
    ob_start();
    imagejpeg($image, null, $quality * 100);
    $compressedData = ob_get_contents();
    ob_end_clean();
    
    imagedestroy($image);
    
    // Convertir en base64
    $base64 = base64_encode($compressedData);
    $base64DataUrl = 'data:image/jpeg;base64,' . $base64;
    
    // Calculer les tailles
    $originalSize = filesize($imagePath);
    $compressedSize = strlen($compressedData);
    $base64Size = strlen($base64);
    
    echo "📊 Taille originale : " . formatBytes($originalSize) . "\n";
    echo "📊 Taille compressée : " . formatBytes($compressedSize) . "\n";
    echo "📊 Taille base64 : " . formatBytes($base64Size) . "\n";
    echo "📈 Réduction : " . round((1 - $compressedSize / $originalSize) * 100, 1) . "%\n";
    
    return $base64DataUrl;
}

/**
 * Formate les octets en unités lisibles
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Fonction principale
 */
function main() {
    global $maxWidth, $quality;
    
    echo "🚀 OPTIMISATION DES IMAGES EXTERNES\n";
    echo "====================================\n\n";
    
    $images = [
        'image1.png' => 'https://www.kreyoly.com/uploads/allimg/20230306/1-23030611253G51.jpg',
        'image2.png' => 'https://www.kreyoly.com/uploads/allimg/20230306/1-23030611253T59.jpg'
    ];
    
    $optimizedImages = [];
    
    foreach ($images as $filename => $url) {
        echo "🖼️ Traitement de $filename\n";
        echo "URL : $url\n";
        echo str_repeat("-", 50) . "\n";
        
        try {
            $base64DataUrl = optimizeImageToBase64($filename, $maxWidth, $quality);
            $optimizedImages[$filename] = $base64DataUrl;
            
            echo "✅ Image optimisée avec succès !\n\n";
            
        } catch (Exception $e) {
            echo "❌ Erreur : " . $e->getMessage() . "\n\n";
        }
    }
    
    // Afficher les résultats
    if (!empty($optimizedImages)) {
        echo "🎉 RÉSULTATS D'OPTIMISATION\n";
        echo "===========================\n";
        
        foreach ($optimizedImages as $filename => $base64DataUrl) {
            echo "\n📋 $filename :\n";
            echo "Base64 (premiers 100 caractères) : " . substr($base64DataUrl, 0, 100) . "...\n";
            echo "Taille complète : " . formatBytes(strlen($base64DataUrl)) . "\n";
        }
        
        echo "\n💾 CODE POUR METTRE À JOUR LA BASE DE DONNÉES :\n";
        echo "===============================================\n";
        
        // Générer le code SQL/PHP pour mettre à jour la base
        foreach ($optimizedImages as $filename => $base64DataUrl) {
            $escapedBase64 = addslashes($base64DataUrl);
            echo "\n-- Pour $filename :\n";
            $originalUrl = $images[$filename];
            $originalFilename = basename($originalUrl);
            echo "UPDATE aide_machines_qa SET reponse = REPLACE(reponse, '$originalUrl', '$escapedBase64') WHERE reponse LIKE '%$originalFilename%';\n";
        }
        
        echo "\n📝 INSTRUCTIONS :\n";
        echo "=================\n";
        echo "1. Copiez les commandes SQL ci-dessus\n";
        echo "2. Exécutez-les dans votre base de données\n";
        echo "3. Les images externes seront remplacées par les versions optimisées\n";
        echo "4. Supprimez les fichiers temporaires : image1.jpg, image2.jpg\n";
    }
    
    // Nettoyer les fichiers temporaires
    echo "\n🧹 NETTOYAGE\n";
    echo "============\n";
    
    foreach (array_keys($images) as $filename) {
        if (file_exists($filename)) {
            unlink($filename);
            echo "🗑️ Supprimé : $filename\n";
        }
    }
    
    echo "\n✅ Optimisation terminée !\n";
}

// Exécuter le script
main();
?>
