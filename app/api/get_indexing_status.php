<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
// API pour lire le statut de l'indexation
require_once __DIR__ . '/../controler/conf.php';

header('Content-Type: application/json; charset=utf-8');

$jobId = $_GET['job_id'] ?? '';

if (empty($jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Job ID is required']);
    exit;
}

// Sécuriser l'ID (alphanumérique seulement)
if (!preg_match('/^[a-zA-Z0-9_.]+$/', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Job ID']);
    exit;
}

$logDir = __DIR__ . '/../logs/indexing_status';

if ($jobId === 'latest') {
    // Trouver le fichier le plus récent
    $files = glob($logDir . '/*.json');
    if (empty($files)) {
        echo json_encode(['status' => 'none']);
        exit;
    }
    
    // Trier par date de modification (décroissant)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $statusFile = $files[0];
    
    // Vérifier si le job est encore actif (modifié il y a moins de 2 minutes)
    // ou si son statut est 'indexing'/'scanning'
    $content = @file_get_contents($statusFile);
    $data = json_decode($content, true);
    
    if (!$data || !in_array($data['status'], ['indexing', 'scanning'])) {
         echo json_encode(['status' => 'none']);
         exit;
    }
} else {
    $statusFile = $logDir . '/' . $jobId . '.json';
    
    if (!file_exists($statusFile)) {
        echo json_encode(['status' => 'unknown', 'percent' => 0]);
        exit;
    }
}

// Lire le fichier (avec essai si verrouillé)
$content = false;
for ($i = 0; $i < 3; $i++) {
    $content = @file_get_contents($statusFile);
    if ($content !== false && !empty($content)) break;
    usleep(50000); // 50ms
}

if ($content === false || empty($content)) {
    // Peut arriver au tout début
    echo json_encode(['status' => 'starting', 'percent' => 0]);
    exit;
}

// Renvoyer le contenu brut ou décodé/re-encodé
// On renvoie direct le JSON stocké
echo $content;
