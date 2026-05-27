<?php
/**
 * API : Déclenchement de la vectorisation en arrière-plan
 * POST - Admin seulement
 */
ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$scriptPath = realpath(__DIR__ . '/../maintenance/vectorize_chunks.php');

if (!$scriptPath || !file_exists($scriptPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Script de vectorisation introuvable.']);
    exit;
}

$logFile = __DIR__ . '/../../logs/vectorization.log';
$jobId = uniqid('vec_', true);

// Lancement en arrière-plan (compatible Linux)
$cmd = sprintf(
    'nohup php %s >> %s 2>&1 &',
    escapeshellarg($scriptPath),
    escapeshellarg($logFile)
);

exec($cmd);

echo json_encode([
    'success' => true,
    'job_id'  => $jobId,
    'message' => 'Vectorisation lancée en arrière-plan. Consultez les logs pour le suivi.',
    'log'     => $logFile,
]);
