<?php
/**
 * get_markdown_migration_status.php — Phase 5 : Statut de la migration Markdown
 *
 * Retourne le contenu de logs/markdown_status.json pour polling UI.
 * Enrichit avec les comptages BDD (raw/processing/done/error).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

$statusFile = __DIR__ . '/../logs/markdown_status.json';
$status = [];

if (file_exists($statusFile)) {
    $status = json_decode(file_get_contents($statusFile), true) ?: [];
}

// Enrichir avec les comptages réels depuis la BDD
try {
    $db = pdo_connect();
    $counts = $db->query("
        SELECT markdown_status, COUNT(*) as cnt
        FROM bibliotheque_files
        WHERE file_type = 'pdf'
        GROUP BY markdown_status
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $status['counts'] = [
        'raw'        => (int)($counts['raw']        ?? 0),
        'processing' => (int)($counts['processing'] ?? 0),
        'done'       => (int)($counts['done']       ?? 0),
        'error'      => (int)($counts['error']      ?? 0),
        'null'       => (int)($counts[null] ?? $counts[''] ?? 0),
    ];
    $status['counts']['total'] = array_sum($status['counts']);
} catch (Exception $e) {
    $status['db_error'] = $e->getMessage();
}

echo json_encode($status);
