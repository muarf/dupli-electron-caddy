<?php
/**
 * API pour récupérer la liste unique des tags de la bibliothèque
 */

header('Content-Type: application/json; charset=utf-8');

try {
    require_once(__DIR__ . '/../controler/conf.php');
    require_once(__DIR__ . '/../controler/functions/database.php');

    $db = pdo_connect();
    
    // Récupérer toutes les chaînes de tags
    $stmt = $db->query("SELECT tags FROM bibliotheque_files WHERE tags IS NOT NULL AND tags != ''");
    $all_tags_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $unique_tags = [];
    foreach ($all_tags_raw as $row) {
        // Séparer par virgule et nettoyer
        $tags = explode(',', $row);
        foreach ($tags as $tag) {
            $trimmed = trim($tag);
            if (!empty($trimmed) && !in_array($trimmed, $unique_tags)) {
                $unique_tags[] = $trimmed;
            }
        }
    }
    
    sort($unique_tags);
    echo json_encode($unique_tags);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
