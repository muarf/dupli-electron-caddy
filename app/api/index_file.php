<?php
// Désactiver l'affichage des erreurs pour éviter la pollution JSON
// ini_set('display_errors', 0);
error_reporting(E_ALL);

// Nettoyer tout buffer de sortie
while (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$jsonInput = file_get_contents('php://input');
if (empty($jsonInput) && PHP_SAPI === 'cli') {
    $jsonInput = file_get_contents('php://stdin');
}
$data = json_decode($jsonInput, true);
$path = $data['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Path is required']);
    exit;
}

// Augmenter la limite de mémoire pour l'indexation (1 GB pour gérer les gros PDFs)
$originalMemoryLimit = ini_get('memory_limit');
ini_set('memory_limit', '512M');

try {
    $manager = new BibliothequeManager();
    $result = $manager->addExternalFile($path);
    
    // Libérer la mémoire après traitement
    unset($manager);
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    
    echo json_encode([
        'success' => true,
        'result' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur index_file: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    error_log("Erreur fatale index_file: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur fatale (mémoire insuffisante ou fichier corrompu): ' . basename($path ?? '')
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // Restaurer la limite de mémoire originale
    ini_set('memory_limit', $originalMemoryLimit);
}





