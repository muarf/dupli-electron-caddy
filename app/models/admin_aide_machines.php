<?php
/**
 * Page d'administration des aides machines
 * Gère les opérations CRUD pour les aides et tutoriels
 */

require_once __DIR__ . '/admin/AideManager.php';

function Action($conf) {
    // Créer l'instance du gestionnaire d'aides
    $aideManager = new AideManager($conf);
    
    // Variables pour les messages
    $aide_error = null;
    $aide_success = null;
    
    // Gestion des actions POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_qa':
                    if (!empty($_POST['machine']) && !empty($_POST['question']) && !empty($_POST['reponse'])) {
                        $result = $aideManager->addQA(
                            htmlspecialchars($_POST['machine']),
                            htmlspecialchars($_POST['question']),
                            $_POST['reponse'], // Ne pas échapper le HTML pour permettre le formatage
                            intval($_POST['ordre'] ?? 0),
                            htmlspecialchars($_POST['categorie'] ?? 'general')
                        );
                        
                        if (isset($result['success'])) {
                            $aide_success = $result['success'];
                        } else {
                            $aide_error = $result['error'];
                        }
                    } else {
                        $aide_error = 'Tous les champs sont obligatoires.';
                    }
                    break;
                    
                case 'edit_qa':
                    if (!empty($_POST['id']) && !empty($_POST['machine']) && !empty($_POST['question']) && !empty($_POST['reponse'])) {
                        $result = $aideManager->updateQA(
                            $_POST['id'],
                            htmlspecialchars($_POST['machine']),
                            htmlspecialchars($_POST['question']),
                            $_POST['reponse'],
                            intval($_POST['ordre'] ?? 0),
                            htmlspecialchars($_POST['categorie'] ?? 'general')
                        );
                        
                        if (isset($result['success'])) {
                            $aide_success = $result['success'];
                        } else {
                            $aide_error = $result['error'];
                        }
                    } else {
                        $aide_error = 'Tous les champs sont obligatoires.';
                    }
                    break;
                    
                case 'delete_qa':
                    if (!empty($_POST['id'])) {
                        $result = $aideManager->deleteQA($_POST['id']);
                        
                        if (isset($result['success'])) {
                            $aide_success = $result['success'];
                        } else {
                            $aide_error = $result['error'];
                        }
                    } else {
                        $aide_error = 'ID manquant pour la suppression.';
                    }
                    break;
            }
        }
    }
    
    // Gestion des requêtes AJAX
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        
        switch ($_GET['ajax']) {
            case 'get_qa':
                if (isset($_GET['id'])) {
                    $qa = $aideManager->getQA($_GET['id']);
                    if ($qa) {
                        echo json_encode(['success' => true, 'qa' => $qa]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Q&A non trouvée']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID manquant']);
                }
                exit;
                
            case 'get_machines':
                $machines = $aideManager->getAllMachines();
                echo json_encode(['success' => true, 'machines' => $machines]);
                exit;
        }
    }
    
    // Obtenir toutes les données pour l'affichage
    $data = $aideManager->getAllAidesData();
    $data['all_machines'] = $aideManager->getAllMachines();
    
    // Ajouter les messages
    $data['aide_error'] = $aide_error;
    $data['aide_success'] = $aide_success;
    
    return $data;
}
?>
