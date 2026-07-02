<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
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

// Lancement en arrière-plan
$phpPath = 'php';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Mode Tauri Prod fallback
    $candidates = [
        __DIR__ . '/../../src-tauri/binaries/php-x86_64-pc-windows-msvc.exe',
        dirname(__DIR__, 2) . '/binaries/php-x86_64-pc-windows-msvc.exe'
    ];
    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real && file_exists($real)) {
            $phpPath = $real;
            break;
        }
    }
    $cmd = 'start /B "" ' . escapeshellarg($phpPath) . ' ' . escapeshellarg($scriptPath) . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
    pclose(popen($cmd, 'r'));
} else {
    $cmd = sprintf(
        'nohup php %s >> %s 2>&1 &',
        escapeshellarg($scriptPath),
        escapeshellarg($logFile)
    );
    exec($cmd);
}

echo json_encode([
    'success' => true,
    'job_id'  => $jobId,
    'message' => 'Vectorisation lancée en arrière-plan. Consultez les logs pour le suivi.',
    'log'     => $logFile,
]);
