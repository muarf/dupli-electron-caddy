<?php
require_once __DIR__ . '/../controler/functions/database.php';

session_start();

// Vérifier que l'utilisateur est connecté (temporairement désactivé pour debug)
if (!isset($_SESSION['user_id'])) {
    // Temporairement désactivé pour debug
    // http_response_code(401);
    // echo json_encode(['error' => 'Non autorisé']);
    // exit;
}

// Vérifier que l'index est fourni
if (!isset($_GET['index']) || !is_numeric($_GET['index'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Index manquant ou invalide']);
    exit;
}

$index = intval($_GET['index']);

try {
    // Inclure les fichiers nécessaires
    require_once __DIR__ . '/../controler/func.php';
    require_once __DIR__ . '/../models/tirage_multimachines.php';
    
    // Récupérer les données nécessaires (même logique que dans le modèle principal)
    $db = pdo_connect();
    $db = pdo_connect();
    
    // Récupérer la liste des duplicopieurs disponibles
    $query = $db->query("SELECT * FROM duplicopieurs ORDER BY marque, modele");
    $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Si un seul duplicopieur, le sélectionner automatiquement
    $duplicopieur_selectionne = null;
    if(count($duplicopieurs) == 1) {
        $duplicopieur_selectionne = $duplicopieurs[0];
    }
    
    // Récupérer la liste des photocopieurs disponibles (exclure les duplicopieurs)
    $duplicopieurs_names = [];
    foreach ($duplicopieurs as $dup) {
        $machine_name = $dup['marque'] . ' ' . $dup['modele'];
        if ($dup['marque'] === $dup['modele']) {
            $machine_name = $dup['marque'];
        }
        $duplicopieurs_names[] = $machine_name;
    }
    
    $photocopiers = [];
    if (!empty($duplicopieurs_names)) {
        $placeholders = str_repeat('?,', count($duplicopieurs_names) - 1) . '?';
        $query = $db->prepare("SELECT DISTINCT marque FROM photocop WHERE marque NOT IN ($placeholders)");
        $query->execute($duplicopieurs_names);
        $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
    } else {
        $query = $db->query('SELECT DISTINCT marque FROM photocop');
        $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
    }
    
    // Générer le HTML de la machine
    $html = generateMachineHTML($index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers);
    
    // Retourner le HTML
    header('Content-Type: application/json');
    echo json_encode(['html' => $html]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>

