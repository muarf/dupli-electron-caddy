<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
// API pour lancer l'indexation en arrière-plan
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isElectron()) {
    http_response_code(403);
    echo json_encode(['error' => 'L\'indexation de dossiers arbitraires est interdite en mode serveur.']);
    exit;
}

// PID file pour empêcher les exécutions parallèles
$pidFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_indexing.pid';
$isRunning = false;

if (file_exists($pidFile)) {
    $oldPid = (int)trim(file_get_contents($pidFile));
    if ($oldPid > 0) {
        if ((function_exists('posix_kill') && posix_kill($oldPid, 0)) || (PHP_OS_FAMILY === 'Windows' && `tasklist /FI "PID eq $oldPid" 2>NUL | find /I "$oldPid"`)) {
            $isRunning = true;
        } else {
            @unlink($pidFile);
        }
    } else {
        @unlink($pidFile);
    }
}

if ($isRunning) {
    http_response_code(409);
    echo json_encode(['error' => 'Une tâche d\'indexation est déjà en cours d\'exécution.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$path = $data['path'] ?? '';
$recursive = $data['recursive'] ?? false;

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Path is required']);
    exit;
}

if (!file_exists($path) || !is_dir($path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Directory not found']);
    exit;
}

// Générer un ID de job unique
$jobId = uniqid('idx_', true);

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

// Chemin du script worker
$scriptPath = __DIR__ . '/../maintenance/background_indexer.php';
// On s'assure que le fichier existe avant de récupérer un realpath vide
if (!file_exists($scriptPath)) {
    http_response_code(500);
    echo json_encode(['error' => "Worker script not found at : $scriptPath"]);
    exit;
}
$scriptPath = realpath($scriptPath);

// Construire la commande
$cmdArgs = [
    $phpPath,
    $scriptPath,
    $path,
    $recursive ? '1' : '0',
    $jobId
];

// Exécuter en arrière-plan selon l'OS
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Windows: start /B permet de lancer en arrière-plan sans bloquer
    $cmd = 'start /B "" ' . implode(' ', array_map('escapeshellarg', $cmdArgs));
    pclose(popen($cmd, 'r'));
    file_put_contents($pidFile, getmypid());
} else {
    // Linux/Mac: capturer le PID du processus background
    $cmd = implode(' ', array_map('escapeshellarg', $cmdArgs)) . ' > /dev/null 2>&1 & echo $!';
    exec($cmd, $output);
    $bgPid = trim($output[0] ?? '');
    if ($bgPid && ctype_digit($bgPid)) {
        file_put_contents($pidFile, $bgPid);
    } else {
        file_put_contents($pidFile, getmypid());
    }
}
    
    // Créer un fichier de statut initial pour éviter les race conditions
    $logDir = __DIR__ . '/../logs/indexing_status';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    
    $initialStatus = [
        'job_id' => $jobId,
        'status' => 'starting',
        'percent' => 0
    ];
    file_put_contents($logDir . '/' . $jobId . '.json', json_encode($initialStatus));

    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'message' => 'Indexation démarrée en arrière-plan'
    ]);

