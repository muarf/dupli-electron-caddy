<?php
require_once __DIR__ . '/../../controler/conf.php';
require_once __DIR__ . '/../../controler/func.php';
require_once __DIR__ . '/MachineManager.php';

// Vérifier que c'est bien une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Vérifier que les paramètres sont présents
if (!isset($_POST['machine_id']) || !isset($_POST['machine_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

$machine_id = $_POST['machine_id'];
$machine_type = $_POST['machine_type'];

// Valider le type de machine
if (!in_array($machine_type, ['duplicopieur', 'photocopieur'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Type de machine invalide']);
    exit;
}

// Valider l'ID de la machine
if (!is_numeric($machine_id) || $machine_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de machine invalide']);
    exit;
}

try {
    // Configuration de la base de données
    $conf = [
        'dsn' => 'mysql:dbname=duplinew;host=127.0.0.1',
        'login' => 'dupli_user',
        'pass' => 'mot_de_passe_solide'
    ];
    
    // Créer l'instance du gestionnaire de machines
    $machineManager = new AdminMachineManager($conf);
    
    // Supprimer la machine
    $result = $machineManager->deleteMachine($machine_id, $machine_type);
    
    // Retourner la réponse
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>


