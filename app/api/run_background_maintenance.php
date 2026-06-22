<?php
// API pour lancer les tâches de maintenance en arrière-plan (extraction texte, etc.)
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

// Empêcher les lancements multiples trop fréquents (cache de 5 minutes)
$lockFile = __DIR__ . '/../logs/maintenance_lock.txt';
if (file_exists($lockFile) && (time() - filemtime($lockFile) < 300)) {
    echo json_encode(['status' => 'skipped', 'message' => 'Maintenance already running or recently finished']);
    exit;
}

// Créer le lock
if (!is_dir(dirname($lockFile))) mkdir(dirname($lockFile), 0777, true);
file_put_contents($lockFile, time());

// Job ID pour le suivi (optionnel)
$jobId = 'maint_' . uniqid();

$phpPath = 'php';
if (defined('PHP_BINARY') && !empty(PHP_BINARY) && PHP_BINARY !== 'php' && file_exists(PHP_BINARY)) {
    $phpPath = PHP_BINARY;
} else if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Mode Tauri Dev & Prod fallbacks
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
}

$scriptPath = realpath(__DIR__ . '/../maintenance/background_indexer.php');

if (!$scriptPath) {
    echo json_encode(['error' => 'Worker script not found']);
    exit;
}

error_log("[DEBUG] PHP_BINARY: " . (defined('PHP_BINARY') ? PHP_BINARY : 'undefined'));
error_log("[DEBUG] phpPath: " . $phpPath);

// Commande pour le mode index_text
$cmdArgs = [
    $phpPath,
    $scriptPath,
    'maintenance', // param (ignored in index_text)
    '0',           // recursive (ignored)
    $jobId,
    'index_text'   // mode
];

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cmd = 'start /B "" ' . implode(' ', array_map('escapeshellarg', $cmdArgs));
    pclose(popen($cmd, 'r'));
} else {
    $cmd = implode(' ', array_map('escapeshellarg', $cmdArgs)) . ' > /dev/null 2>&1 &';
    exec($cmd);
}

echo json_encode([
    'success' => true,
    'message' => 'Maintenance de fond démarrée',
    'job_id' => $jobId
]);
