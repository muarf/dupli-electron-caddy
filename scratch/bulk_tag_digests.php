<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/func.php';

try {
    $db = pdo_connect();
    $stmt = $db->query("SELECT id, filename, tags FROM bibliotheque_files WHERE filename LIKE 'digest_%'");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Analyse de " . count($files) . " fichiers digest...\n";
    $updatedCount = 0;

    foreach ($files as $file) {
        // Format attendu: digest_YYYY_week_...
        if (preg_match('/digest_(\d{4})_week_/i', $file['filename'], $matches)) {
            $year = $matches[1];
            
            // On récupère les tags actuels
            $currentTags = !empty($file['tags']) ? array_map('trim', explode(',', $file['tags'])) : [];
            $currentTags = array_filter($currentTags);
            
            $newTags = $currentTags;
            
            // On ajoute 'week' s'il n'y est pas
            if (!in_array('week', $newTags) && !in_array('Week', $newTags)) {
                $newTags[] = 'week';
            }
            
            // On ajoute l'année s'il n'y est pas
            if (!in_array($year, $newTags)) {
                $newTags[] = $year;
            }
            
            // Si on a ajouté des tags, on met à jour la BDD
            if (count($newTags) > count($currentTags)) {
                $tagsStr = implode(', ', $newTags);
                $upd = $db->prepare("UPDATE bibliotheque_files SET tags = ? WHERE id = ?");
                $upd->execute([$tagsStr, $file['id']]);
                $updatedCount++;
            }
        }
    }

    echo "Mise à jour terminée : $updatedCount fichiers ont été enrichis avec les tags correspondants.\n";

} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}
