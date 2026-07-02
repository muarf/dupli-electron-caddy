<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
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
    
    // Vérification de l'activation IA
    require_once __DIR__ . '/../models/SettingsManager.php';
    $db = pdo_connect();
    $settingsManager = new SettingsManager($db);
    $aiEnabled = (int)$settingsManager->get('ai_enabled', 0);
    
    $jobId = null;
    if ($aiEnabled && isset($result['id'])) {
        $scriptPath = realpath(__DIR__ . '/../maintenance/background_indexer.php');
        $logFile = __DIR__ . '/../../logs/background_indexer.log';
        if ($scriptPath) {
            $cmd = sprintf(
                'nohup php %s %s >> %s 2>&1 &',
                escapeshellarg($scriptPath),
                escapeshellarg($result['id']),
                escapeshellarg($logFile)
            );
            exec($cmd);
            $jobId = 'idx_' . $result['id'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'file' => $result,
        'ai_enabled' => (bool)$aiEnabled,
        'job_id' => $jobId
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





