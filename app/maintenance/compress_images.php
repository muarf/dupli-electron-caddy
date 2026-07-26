<?php
/**
 * Script de compression des images base64 dans la base de données
 * 
 * Ce script :
 * 1. Trouve toutes les images base64 dans les tables contenant du contenu Quill.js
 * 2. Les compresse et les redimensionne
 * 3. Met à jour la base de données avec les images optimisées
 */

// Configuration
$maxWidth = 800;
$quality = 0.8;
$backup = true;

// Connexion à la base de données
try {
    $db = new PDO('sqlite:duplinew.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion à la base de données réussie\n";
} catch (Exception $e) {
    die("❌ Erreur de connexion à la base : " . $e->getMessage() . "\n");
}

/**
 * Compresse une image base64
 */
function compressBase64Image($base64, $maxWidth = 800, $quality = 0.8) {
    // Vérifier si c'est bien une image base64
    if (!preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $base64)) {
        return $base64;
    }
    
    // Extraire le type d'image et les données
    preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,(.+)$/', $base64, $matches);
    $imageType = $matches[1];
    $imageData = base64_decode($matches[2]);
    
    // Créer l'image depuis les données string
    $image = imagecreatefromstring($imageData);
    
    if (!$image) {
        return $base64;
    }
    
    // Obtenir les dimensions originales
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculer les nouvelles dimensions si nécessaire
    if ($width > $maxWidth) {
        $newHeight = ($height * $maxWidth) / $width;
        
        // Créer une nouvelle image redimensionnée
        $resized = imagecreatetruecolor($maxWidth, $newHeight);
        
        // Préserver la transparence pour les PNG
        if ($imageType === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $maxWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Compresser l'image
    ob_start();
    
    switch ($imageType) {
        case 'jpeg':
        case 'jpg':
            imagejpeg($image, null, $quality * 100);
            break;
        case 'png':
            // Pour PNG, on peut ajuster la compression (0-9, 9 = max compression)
            imagepng($image, null, 9 - ($quality * 9));
            break;
        case 'gif':
            imagegif($image);
            break;
    }
    
    $compressedData = ob_get_contents();
    ob_end_clean();
    
    imagedestroy($image);
    
    return 'data:image/' . $imageType . ';base64,' . base64_encode($compressedData);
}

/**
 * Trouve et compresse les images dans une table
 */
function processTable($db, $tableName, $columnName, $backup = true) {
    global $maxWidth, $quality;
    
    echo "\n🔍 Traitement de la table : $tableName, colonne : $columnName\n";
    
    try {
        // Créer une sauvegarde si demandé
        if ($backup) {
            $backupTable = $tableName . '_backup_' . date('Y-m-d_H-i-s');
            $db->exec("CREATE TABLE `$backupTable` AS SELECT * FROM `$tableName`");
            echo "📦 Sauvegarde créée : $backupTable\n";
        }
        
        // Récupérer toutes les lignes contenant des images
        $stmt = $db->prepare("SELECT * FROM `$tableName` WHERE `$columnName` LIKE '%data:image/%'");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📊 " . count($rows) . " lignes trouvées avec des images\n";
        
        $processed = 0;
        $compressed = 0;
        $errors = 0;
        
        foreach ($rows as $row) {
            $processed++;
            $originalContent = $row[$columnName];
            $compressedContent = $originalContent;
            
            // Trouver toutes les images base64 dans le contenu
            preg_match_all('/data:image\/[^;]+;base64,[A-Za-z0-9+\/]+=*/', $originalContent, $matches);
            
            if (!empty($matches[0])) {
                foreach ($matches[0] as $imageData) {
                    try {
                        $compressedImage = compressBase64Image($imageData, $maxWidth, $quality);
                        $compressedContent = str_replace($imageData, $compressedImage, $compressedContent);
                        $compressed++;
                    } catch (Exception $e) {
                        echo "⚠️ Erreur lors de la compression d'une image : " . $e->getMessage() . "\n";
                        $errors++;
                    }
                }
                
                // Mettre à jour la base de données si le contenu a changé
                if ($compressedContent !== $originalContent) {
                    $updateStmt = $db->prepare("UPDATE `$tableName` SET `$columnName` = ? WHERE id = ?");
                    $updateStmt->execute([$compressedContent, $row['id']]);
                }
            }
            
            // Afficher le progrès
            if ($processed % 10 == 0) {
                echo "⏳ Progression : $processed/" . count($rows) . " lignes traitées\n";
            }
        }
        
        echo "✅ Table $tableName terminée :\n";
        echo "   - Lignes traitées : $processed\n";
        echo "   - Images compressées : $compressed\n";
        echo "   - Erreurs : $errors\n";
        
    } catch (Exception $e) {
        echo "❌ Erreur lors du traitement de $tableName : " . $e->getMessage() . "\n";
    }
}

/**
 * Fonction principale
 */
function main($db, $backup = true) {
    
    echo "🚀 Début de la compression des images base64\n";
    echo "============================================\n";
    
    // Tables et colonnes à traiter (contenu Quill.js)
    $tablesToProcess = [
        ['table' => 'aide_machines_qa', 'column' => 'reponse'],
        ['table' => 'news', 'column' => 'news'],
        // Ajoutez d'autres tables si nécessaire
    ];
    
    $startTime = microtime(true);
    $totalImages = 0;
    
    foreach ($tablesToProcess as $tableInfo) {
        // Vérifier que la table existe
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM `" . $tableInfo['table'] . "`");
            processTable($db, $tableInfo['table'], $tableInfo['column'], $backup);
        } catch (Exception $e) {
            echo "⚠️ Table " . $tableInfo['table'] . " ignorée : " . $e->getMessage() . "\n";
        }
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "\n🎉 Compression terminée !\n";
    echo "========================\n";
    echo "⏱️ Durée totale : {$duration}s\n";
    echo "📦 Sauvegardes créées : " . ($backup ? "Oui" : "Non") . "\n";
    echo "\n💡 Conseils :\n";
    echo "   - Testez votre application pour vérifier que tout fonctionne\n";
    echo "   - Les sauvegardes sont dans les tables *_backup_*\n";
    echo "   - Vous pouvez supprimer les sauvegardes une fois satisfait\n";
}

// Exécuter le script
main($db, $backup);
?>
