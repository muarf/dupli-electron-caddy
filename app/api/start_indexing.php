<?php
// API pour lancer l'indexation en arrière-plan
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
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

// Déterminer le chemin vers PHP
$phpPath = __DIR__ . '/../../php/php.exe';
if (!file_exists($phpPath)) {
    // Fallback sur le PHP système si la version embarquée n'est pas trouvée
    $phpPath = 'php';
} else {
    $phpPath = realpath($phpPath);
}

// Chemin du script worker
$scriptPath = __DIR__ . '/../maintenance/background_indexer.php';
$scriptPath = realpath($scriptPath);

if (!$scriptPath) {
    http_response_code(500);
    echo json_encode(['error' => 'Worker script not found']);
    exit;
}

// Construire la commande
// Windows uniquement pour l'instant (comme demandé par le context)
// start /B permet de lancer en arrière-plan sans bloquer
$cmdArgs = [
    $phpPath,
    $scriptPath,
    $path,
    $recursive ? '1' : '0',
    $jobId
];

// Échapper les arguments
// Attention : escapeshellarg sous Windows entoure de "
// La commande finale sera : start /B "" "php.exe" "script.php" "path" "1" "jobId"
$cmd = 'start /B "" ' . implode(' ', array_map('escapeshellarg', $cmdArgs));

// Exécuter
try {
    pclose(popen($cmd, 'r'));
    
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
