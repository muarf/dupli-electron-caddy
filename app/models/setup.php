<?php
require_once __DIR__ . '/../controler/functions/i18n.php';

if (!function_exists('Action')) {
function Action($conf = null){
    try {
        $db = pdo_connect();
        
        // Vérifier si des machines ont déjà été enregistrées
        $has_machines = check_machines_exist();
        
        if ($has_machines) {
            // Des machines existent déjà, rediriger vers l'accueil
            header('Location: ?accueil');
            exit;
        }
    } catch (PDOException $e) {
        // Base de données non trouvée, continuer avec l'installation
    }
    
    // Récupérer le mode (choice, create, upload)
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'choice';
    
    // Récupérer les erreurs de session s'il y en a
    $errors = isset($_SESSION['setup_errors']) ? $_SESSION['setup_errors'] : array();
    unset($_SESSION['setup_errors']);
    
    // Message de succès après upload
    $success = isset($_GET['upload_success']) ? "Base de données restaurée avec succès !" : null;
    
    // Détecter si mode standalone (pas Electron)
    $is_standalone = !isset($_SERVER['ELECTRON_RUNNING']) && php_sapi_name() === 'cli-server';
    $base_path = $is_standalone ? '' : 'public/';
    
    $result = array(
        'step' => 'setup',
        'mode' => $mode,
        'errors' => $errors,
        'success' => $success,
        'base_path' => $base_path
    );
    
    // Rendu direct sans template() pour éviter double encapsulation HTML
    extract($result);
    include(__DIR__ . '/../view/setup.html.php');
    return '';
}
}
?>
