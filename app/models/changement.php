<?php
require_once __DIR__ . '/../controler/functions/database.php';

function Action()
{
    $array = array();
    $db = pdo_connect();
    
    // Gestion AJAX pour récupérer les derniers compteurs
    if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_last_counters' && isset($_GET['machine'])) {
        try {
            $machine = $_GET['machine'];
            
            // Vérifier si c'est un duplicopieur en cherchant dans la base
            $db = pdo_connect();
            // Construire la requête pour gérer les noms avec ou sans duplication
            $query = $db->prepare('SELECT * FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?)) AND actif = 1');
            $query->execute([$machine, $machine, $machine]);
            $duplicopieur = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($duplicopieur) {
                // C'est un duplicopieur, récupérer les compteurs pour cette machine spécifique
                $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                $query_counters->execute([$machine]);
                $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
                
                if ($last_counters) {
                    $counters = [
                        'master_av' => ceil($last_counters['master_ap']),
                        'passage_av' => ceil($last_counters['passage_ap'])
                    ];
                } else {
                    $counters = ['master_av' => 0, 'passage_av' => 0];
                }
            } else {
                // Pour les photocopieurs, utiliser get_last_counters_photocop
                $counters = get_last_counters_photocop($machine);
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'counters' => $counters]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Traitement du formulaire de changement
    if (isset($_POST['machine']) && isset($_POST['type']) && isset($_POST['nb_p'])) {
        try {
            $db = pdo_connect();
            
            // Validation des données
            $machine = trim($_POST['machine']);
            $type = trim($_POST['type']);
            $nb_p = intval($_POST['nb_p']);
            $nb_m = intval($_POST['nb_m'] ?? 0);
            $tambour = trim($_POST['tambour'] ?? '');
            
            if (empty($machine) || empty($type) || $nb_p < 0) {
                $array['error'] = 'Veuillez remplir tous les champs obligatoires correctement.';
            } else {
                // Insertion dans la table cons avec le champ tambour
                $date = time();
                $sql = "INSERT INTO cons (machine, type, date, nb_p, nb_m, tambour) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$machine, $type, $date, $nb_p, $nb_m, $tambour]);
                
                if ($result) {
                    $array['success'] = 'Changement enregistré avec succès !';
                    // Réinitialiser les champs après succès
                    $_POST = array();
                } else {
                    $array['error'] = 'Erreur lors de l\'enregistrement.';
                }
            }
        } catch (Exception $e) {
            $array['error'] = 'Erreur : ' . $e->getMessage();
        }
    }
    
    // Récupérer les machines disponibles
    $db = pdo_connect();
    
    // Récupérer les duplicopieurs actifs depuis la base
    try {
        $query = $db->query('SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele');
        $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
        $array['duplicopieurs'] = [];
        
        foreach ($duplicopieurs as $dup) {
            // Utiliser le nom réel de la machine au lieu de A3/A4
            $machine_name = $dup['marque'] . ' ' . $dup['modele'];
            // Éviter la duplication si marque et modèle sont identiques
            if ($dup['marque'] === $dup['modele']) {
                $machine_name = $dup['marque'];
            }
            
            // Parser les tambours depuis JSON
            $tambours = [];
            if (!empty($dup['tambours'])) {
                try {
                    $tambours = json_decode($dup['tambours'], true);
                } catch (Exception $e) {
                    $tambours = ['tambour_noir'];
                }
            } else {
                $tambours = ['tambour_noir']; // Fallback pour les anciens duplicopieurs
            }
            
            $array['duplicopieurs'][] = [
                'name' => $machine_name,
                'supporte_a3' => $dup['supporte_a3'],
                'supporte_a4' => $dup['supporte_a4'],
                'tambours' => $tambours
            ];
        }
    } catch (Exception $e) {
        $array['duplicopieurs'] = [];
    }
    
    // Récupérer les photocopieurs depuis la table photocopieurs
    try {
        $query = $db->query('SELECT marque, modele FROM photocopieurs WHERE actif = 1 ORDER BY marque, modele');
        $photocopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
        $photocopiers = [];
        
        foreach ($photocopieurs as $photocop) {
            // Utiliser le nom réel de la machine
            $machine_name = $photocop['marque'];
            if (!empty($photocop['modele']) && $photocop['marque'] !== $photocop['modele']) {
                $machine_name = $photocop['marque'] . ' ' . $photocop['modele'];
            }
            $photocopiers[] = $machine_name;
        }
    } catch (Exception $e) {
        $photocopiers = [];
    }
    $array['photocopiers'] = $photocopiers;
    
    
    // Récupérer l'aide dynamique
    $array['aide_dynamique'] = getAideMachine($db);
    
    return template("../view/changement.html.php", $array);
}

/**
 * Récupérer l'aide pour une machine spécifique
 */
function getAideMachine($db) {
    try {
        $query = $db->query('SELECT machine, contenu_aide FROM aide_machines ORDER BY machine');
        $aides = $query->fetchAll(PDO::FETCH_ASSOC);
        
        // Retourner les aides sous forme de JSON pour JavaScript
        return json_encode($aides);
        
    } catch (Exception $e) {
        return json_encode([]);
    }
}
?>
