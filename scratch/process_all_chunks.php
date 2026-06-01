<?php
/**
 * Script pour générer les chunks de texte pour tous les fichiers existants
 * dans la bibliothèque.
 */

require_once __DIR__ . '/../app/controler/func.php';
require_once __DIR__ . '/../app/models/BibliothequeManager.php';

$db = pdo_connect();
$libManager = new BibliothequeManager();

try {
    echo "Récupération des fichiers avec texte extrait...\n";
    $stmt = $db->query("SELECT id, filename, extracted_text FROM bibliotheque_files WHERE extracted_text IS NOT NULL AND extracted_text != ''");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalFiles = count($files);
    echo "$totalFiles fichiers à traiter.\n\n";

    foreach ($files as $index => $file) {
        $count = ($index + 1);
        echo "[$count/$totalFiles] Traitement de : {$file['filename']} ... ";
        
        $chunkCount = $libManager->generateChunksForFile($file['id'], $file['extracted_text']);
        
        echo "$chunkCount morceaux générés.\n";
    }

    echo "\nTerminé ! Tous les fichiers ont été découpés en morceaux de 300 mots.\n";

} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
