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
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

try {
    $manager = new BibliothequeManager();
    $result = $manager->addUploadedFile($_FILES['file']);
    
    echo json_encode([
        'success' => true,
        'file' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erreur upload_bibliotheque: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    http_response_code(500);
    error_log("Erreur fatale upload_bibliotheque: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erreur fatale: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}





