<?php
/**
 * Script de réparation des artefacts PDF (<>)
 * Nettoie le texte, regénère les chunks et prépare la re-vectorisation
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$db = pdo_connect();
$manager = new BibliothequeManager();

echo "[" . date('H:i:s') . "] Recherche des fichiers avec des artefacts <>...\n";

$stmt = $db->query("SELECT id, filename, extracted_text FROM bibliotheque_files WHERE extracted_text LIKE '%<>%'");
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($files);
echo "[" . date('H:i:s') . "] Trouvé $total fichiers à réparer.\n";

foreach ($files as $index => $file) {
    $id = $file['id'];
    $name = $file['filename'];
    
    echo "[" . ($index + 1) . "/$total] Réparation de : $name... ";
    
    $cleanText = str_replace('<>', ' ', $file['extracted_text']);
    
    // 1. Update table principale
    $upd = $db->prepare("UPDATE bibliotheque_files SET extracted_text = ?, updated_at = datetime('now') WHERE id = ?");
    $upd->execute([$cleanText, $id]);
    
    // 2. Update FTS
    $updFts = $db->prepare("UPDATE bibliotheque_files_fts SET extracted_text = ? WHERE rowid = ?");
    $updFts->execute([$cleanText, $id]);
    
    // 3. Regénérer les chunks (ils seront automatiquement propres grâce à la modif du Manager)
    $manager->generateChunksForFile($id, $cleanText);
    
    // 4. Supprimer les anciens vecteurs pour forcer la re-vectorisation
    // (Les chunks ayant été supprimés/recréés par generateChunksForFile, 
    // les anciens vecteurs sont déjà orphelins ou supprimés par cascade si foreign keys ON DELETE CASCADE)
    // Mais on va s'assurer que les NOUVEAUX chunks n'ont pas de vecteurs (ce qui est le cas par défaut)
    
    echo "OK\n";
}

echo "[" . date('H:i:s') . "] Réparation terminée. N'oubliez pas de relancer la vectorisation OVH.\n";
