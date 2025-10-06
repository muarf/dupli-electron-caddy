<?php
require_once __DIR__ . '/../controler/functions/database.php';

session_start();
header('Content-Type: application/json');

// Vérifier l'authentification admin
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

try {
    // Vérifier les données reçues
    if (!isset($_POST['machine_id'])) {
        throw new Exception('ID de machine manquant');
    }
    
    $machine_id = (int)$_POST['machine_id'];
    
    if ($machine_id <= 0) {
        throw new Exception('ID de machine invalide');
    }
    
    // Charger les classes nécessaires
    require_once __DIR__ . '/../controler/func.php';
    
    $db = pdo_connect();
    $db = pdo_connect();
    
    // Récupérer les prix des tambours pour ce duplicopieur
    $query = $db->prepare('SELECT type, unite, pack FROM prix WHERE machine_type = "dupli" AND machine_id = ? AND type != "master"');
    $query->execute([$machine_id]);
    $prices = $query->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($prices as $price) {
        $result[$price['type']] = [
            'unite' => (float)$price['unite'],
            'pack' => (float)$price['pack']
        ];
    }
    
    echo json_encode(['success' => true, 'prices' => $result]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
