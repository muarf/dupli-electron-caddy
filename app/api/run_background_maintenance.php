<?php
// API pour lancer les tâches de maintenance en arrière-plan (extraction texte, etc.)
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

// PID file pour empêcher les exécutions parallèles
// Note: flock() est inefficace ici car le lock est libéré quand la requête HTTP se termite,
//       bien que le processus background continue. On utilise un PID file à la place.
$pidFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_maintenance.pid';
$isRunning = false;

if (file_exists($pidFile)) {
    $oldPid = (int)trim(file_get_contents($pidFile));
    if ($oldPid > 0) {
        // Vérifier si le processus tourne encore (signal 0)
        if ((function_exists('posix_kill') && posix_kill($oldPid, 0)) || (PHP_OS_FAMILY === 'Windows' && `tasklist /FI "PID eq $oldPid" 2>NUL | find /I "$oldPid"`)) {
            $isRunning = true;
        } else {
            // PID fantôme — nettoyer
            @unlink($pidFile);
        }
    } else {
        @unlink($pidFile);
    }
}

if ($isRunning) {
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
    // Windows: écrire un marqueur (pas de PID fiable depuis start /B)
    file_put_contents($pidFile, getmypid());
} else {
    // Linux: capturer le PID du processus background
    $cmd = implode(' ', array_map('escapeshellarg', $cmdArgs)) . ' > /dev/null 2>&1 & echo $!';
    exec($cmd, $output);
    $bgPid = trim($output[0] ?? '');
    if ($bgPid && ctype_digit($bgPid)) {
        file_put_contents($pidFile, $bgPid);
    } else {
        // Fallback: écrire notre propre PID (la maintenance est lancée, le fichier sera nettoyé au prochain check)
        file_put_contents($pidFile, getmypid());
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Maintenance de fond démarrée',
    'job_id' => $jobId
]);
