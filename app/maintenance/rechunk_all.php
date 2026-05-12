<?php
/**
 * Script pour régénérer tous les chunks de la bibliothèque
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$db = pdo_connect();
$libManager = new BibliothequeManager();

echo "[" . date('H:i:s') . "] Début de la régénération des chunks...\n";

// 1. Récupérer tous les fichiers qui ont du texte extrait
$stmt = $db->query("SELECT id, filename, extracted_text FROM bibliotheque_files WHERE extracted_text IS NOT NULL AND extracted_text != ''");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($files);
echo "[" . date('H:i:s') . "] Trouvé $total fichiers à traiter.\n";

// Désactiver temporairement les contraintes de clés étrangères pour aller plus vite si besoin
// Mais ici on veut que ON DELETE CASCADE fonctionne pour nettoyer les vecteurs
$db->exec("PRAGMA foreign_keys = ON;");

$count = 0;
foreach ($files as $file) {
    $count++;
    if ($count % 50 === 0) {
        echo "[" . date('H:i:s') . "] Progression : $count / $total (" . round(($count/$total)*100) . "%)\n";
    }

    try {
        // generateChunksForFile supprime les anciens chunks (et donc les vecteurs via CASCADE)
        // et crée les nouveaux avec la nouvelle logique corrigée
        $numChunks = $libManager->generateChunksForFile($file['id'], $file['extracted_text']);
    } catch (Exception $e) {
        echo "[" . date('H:i:s') . "] Erreur sur fichier " . $file['id'] . " (" . $file['filename'] . ") : " . $e->getMessage() . "\n";
    }
}

echo "[" . date('H:i:s') . "] Terminé ! $total fichiers traités.\n";
echo "[" . date('H:i:s') . "] IMPORTANT : Relancer maintenant app/maintenance/vectorize_chunks.php\n";
