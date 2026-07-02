<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
// Désactiver l'affichage des erreurs pour éviter la pollution JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fonction pour renvoyer une réponse JSON d'erreur
function sendJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    // Nettoyer tout buffer avant d'envoyer
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fonction pour renvoyer une réponse JSON de succès
function sendJsonSuccess($data) {
    header('Content-Type: application/json; charset=utf-8');
    // Nettoyer tout buffer avant d'envoyer
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Nettoyer tout buffer de sortie au début
while (ob_get_level()) {
    ob_end_clean();
}

// Démarrer un nouveau buffer pour capturer les erreurs
ob_start();

try {
    require_once __DIR__ . '/../controler/conf.php';
    require_once __DIR__ . '/../controler/func.php';
    require_once __DIR__ . '/../models/BibliothequeManager.php';
} catch (Exception $e) {
    error_log("Erreur chargement fichiers search_bibliotheque: " . $e->getMessage());
    sendJsonError("Erreur de chargement: " . $e->getMessage());
} catch (Error $e) {
    error_log("Erreur fatale chargement fichiers search_bibliotheque: " . $e->getMessage());
    sendJsonError("Erreur fatale de chargement: " . $e->getMessage());
}

try {
    // Vérifier que la table existe
    $db = pdo_connect();
    if (!$db) {
        sendJsonError("Impossible de se connecter à la base de données");
    }
    
    $search = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? '';
    
    // Filtres techniques
    $filters = [
        'format' => $_GET['format'] ?? null,
        'color' => $_GET['color'] ?? null,
        'imposition' => $_GET['imposition'] ?? null,
        'tag' => $_GET['tag'] ?? null,
        'sort_by' => $_GET['sort_by'] ?? 'created_at',
        'sort_order' => $_GET['sort_order'] ?? 'DESC',
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 24,
        'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];
    
    $manager = new BibliothequeManager();
    $files = $manager->getAllFiles($search, $type, $filters);
    $total = $manager->countAllFiles($search, $type, $filters);
    
    // Nettoyer les données pour éviter les problèmes d'encodage UTF-8
    $cleanedFiles = array_map(function($file) {
        foreach ($file as $key => $value) {
            if (is_string($value)) {
                $file[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $file[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $file[$key]);
                if (!mb_check_encoding($file[$key], 'UTF-8')) {
                    $file[$key] = mb_convert_encoding($file[$key], 'UTF-8', 'UTF-8');
                    if (!mb_check_encoding($file[$key], 'UTF-8')) $file[$key] = '';
                }
            }
        }
        return $file;
    }, $files);
    
    sendJsonSuccess([
        'success' => true,
        'files' => $cleanedFiles,
        'total' => $total,
        'limit' => $filters['limit'],
        'offset' => $filters['offset']
    ]);
    
} catch (Exception $e) {
    error_log("Erreur search_bibliotheque: " . $e->getMessage());
    sendJsonError($e->getMessage());
} catch (Error $e) {
    error_log("Erreur fatale search_bibliotheque: " . $e->getMessage());
    sendJsonError('Erreur fatale: ' . $e->getMessage());
}
