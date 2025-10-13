<?php
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
    
    $result = array(
        'step' => 'setup',
        'mode' => $mode,
        'errors' => $errors,
        'success' => $success
    );
    
    return template("../view/setup.html.php", $result);
}
?>
