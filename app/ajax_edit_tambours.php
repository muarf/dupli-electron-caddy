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
    if (!isset($_POST['machine_id']) || !isset($_POST['tambours']) || !isset($_POST['prix_tambour_unite'])) {
        throw new Exception('Données manquantes');
    }
    
    $machine_id = (int)$_POST['machine_id'];
    $tambours = $_POST['tambours'];
    $prix_unite = $_POST['prix_tambour_unite'];
    $prix_pack = $_POST['prix_tambour_pack'] ?? [];
    
    if ($machine_id <= 0 || empty($tambours) || !is_array($tambours)) {
        throw new Exception('Données invalides');
    }
    
    // Charger les classes nécessaires
    require_once __DIR__ . '/../controler/func.php';
    require_once __DIR__ . '/../models/admin/MachineManager.php';
    
    $db = pdo_connect();
    $db = pdo_connect();
    
    // Vérifier que le duplicopieur existe
    $query = $db->prepare('SELECT id, marque, modele FROM duplicopieurs WHERE id = ? AND actif = 1');
    $query->execute([$machine_id]);
    $duplicopieur = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$duplicopieur) {
        throw new Exception('Duplicopieur introuvable');
    }
    
    // Commencer une transaction
    $db->beginTransaction();
    
    try {
        // Mettre à jour les tambours dans la table duplicopieurs
        $tambours_json = json_encode($tambours);
        $query = $db->prepare('UPDATE duplicopieurs SET tambours = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $query->execute([$tambours_json, $machine_id]);
        
        // Supprimer les anciens prix des tambours pour ce duplicopieur
        $query = $db->prepare('DELETE FROM prix WHERE machine_type = "dupli" AND machine_id = ? AND type != "master"');
        $query->execute([$machine_id]);
        
        // Insérer les nouveaux prix pour chaque tambour
        $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
        
        for ($i = 0; $i < count($tambours); $i++) {
            $tambour = $tambours[$i];
            $prix_unite_val = isset($prix_unite[$i]) ? (float)$prix_unite[$i] : 0.002;
            $prix_pack_val = isset($prix_pack[$i]) ? (float)$prix_pack[$i] : 0;
            
            $query->execute(['dupli', $machine_id, $tambour, $prix_unite_val, $prix_pack_val]);
        }
        
        // Valider la transaction
        $db->commit();
        
        echo json_encode(['success' => 'Tambours mis à jour avec succès pour ' . $duplicopieur['marque']]);
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>


