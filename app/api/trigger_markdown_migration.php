<?php
/**
 * trigger_markdown_migration.php — Phase 5 : API de déclenchement de la migration Markdown
 *
 * Lance process_markdown_chunks.php en arrière-plan.
 * Retourne {success, message} en JSON.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$mode = $_POST['mode'] ?? 'all'; // 'all', 'retry', 'force'

$scriptDir = __DIR__ . '/../maintenance';
$worker    = $scriptDir . '/process_markdown_chunks.php';
$logsDir   = __DIR__ . '/../logs';
$statusFile = $logsDir . '/markdown_status.json';

if (!file_exists($worker)) {
    echo json_encode(['success' => false, 'error' => 'Worker non trouvé : ' . $worker]);
    exit;
}

// Vérifier qu'une migration n'est pas déjà en cours
if (file_exists($statusFile)) {
    $status = json_decode(file_get_contents($statusFile), true);
    if (!empty($status['running'])) {
        echo json_encode([
            'success' => false,
            'error'   => 'Une migration est déjà en cours (' . ($status['processed'] ?? 0) . '/' . ($status['total'] ?? '?') . ' fichiers).'
        ]);
        exit;
    }
}

// Construire les arguments selon le mode
$args = match ($mode) {
    'retry' => '--retry-errors',
    'force' => '--all --force',
    default => '--all',
};

// Lancer en arrière-plan (même pattern que trigger_vectorization.php)
$phpBin = (PHP_OS_FAMILY === 'Windows') ? (PHP_BINARY ?: 'php') : 'php';
$logFile = escapeshellarg($logsDir . '/markdown_migration.log');
$cmd     = escapeshellarg($phpBin) . ' ' . escapeshellarg($worker) . ' ' . $args;

if (PHP_OS_FAMILY === 'Windows') {
    $bgCmd = 'start /B "" ' . $cmd . ' >> ' . $logFile . ' 2>&1';
    pclose(popen($bgCmd, 'r'));
} else {
    $bgCmd = 'nohup ' . $cmd . ' >> ' . $logsDir . '/markdown_migration.log 2>&1 &';
    exec($bgCmd);
}

// Initialiser le statut immédiatement
if (!is_dir($logsDir)) @mkdir($logsDir, 0777, true);
file_put_contents($statusFile, json_encode([
    'running'    => true,
    'progress'   => 0,
    'total'      => null,
    'processed'  => 0,
    'errors'     => 0,
    'started_at' => date('Y-m-d H:i:s'),
]));

echo json_encode([
    'success' => true,
    'message' => 'Migration Markdown démarrée en arrière-plan (mode : ' . $mode . ')'
]);
