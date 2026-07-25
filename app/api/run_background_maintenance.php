<?php
// API pour lancer les tâches de maintenance en arrière-plan (extraction texte, etc.)
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

// Verrou de fichier atomique pour empêcher les exécutions parallèles
$lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_maintenance.lock';
$lockFp = @fopen($lockFile, 'c+');
if ($lockFp && !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo json_encode(['status' => 'skipped', 'message' => 'Maintenance déjà en cours d\'exécution']);
    exit;
}

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
