<?php
/**
 * API de recherche de morceaux de texte (chunks)
 * Utilise FTS5 pour trouver les passages les plus pertinents.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$db = pdo_connect();
$q = $_GET['q'] ?? '';
$limit = intval($_GET['limit'] ?? 5);

if (empty($q)) {
    echo json_encode(['success' => false, 'error' => 'Requête vide']);
    exit;
}

try {
    // Recherche FTS5 sur les chunks
    // On récupère aussi le nom du fichier d'origine
    $sql = "
        SELECT 
            c.id, 
            c.file_id, 
            c.content, 
            c.section_title,
            c.heading_level,
            f.filename,
            f.filepath,
            bm25(bibliotheque_chunks_fts) as rank
        FROM bibliotheque_chunks_fts fts
        JOIN bibliotheque_chunks c ON c.id = fts.rowid
        JOIN bibliotheque_files f ON f.id = c.file_id
        WHERE bibliotheque_chunks_fts MATCH ?
        ORDER BY rank
        LIMIT ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$q . '*', $limit]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'query' => $q,
        'count' => count($results),
        'results' => $results
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
