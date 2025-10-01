<?php
require_once __DIR__ . '/../controler/functions/database.php';

/**
 * Route AJAX pour la gestion des changements
 */
session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Inclure les dépendances
require_once __DIR__ . '/controler/func.php';
require_once __DIR__ . '/controler/conf.php';

try {
    $db = pdo_connect();
    $db = pdo_connect();
    
    if (isset($_GET['action']) && $_GET['action'] === 'get_change' && isset($_GET['id'])) {
        $change_id = intval($_GET['id']);
        $query = $db->prepare('SELECT * FROM cons WHERE id = ?');
        $query->execute([$change_id]);
        $change = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($change) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'change' => $change]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Changement non trouvé']);
        }
        exit;
    }
    
    // Si aucune action AJAX valide
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Action non valide']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
