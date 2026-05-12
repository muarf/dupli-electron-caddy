<?php
// Désactiver l'affichage des erreurs pour éviter la pollution JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Nettoyer tout buffer de sortie
while (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isElectron()) {
    http_response_code(403);
    echo json_encode(['error' => 'L\'exploration de dossiers locaux est interdite en mode serveur.']);
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

if (!is_dir($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'Directory not found']);
    exit;
}

try {
    $files = scanDirectoryForLibrary($path, $recursive);
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'count' => count($files)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur preview_directory: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    error_log("Erreur fatale preview_directory: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur fatale: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}





