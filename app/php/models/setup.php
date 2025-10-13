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
    
    // Récupérer les erreurs de session s'il y en a
    $errors = isset($_SESSION['setup_errors']) ? $_SESSION['setup_errors'] : array();
    
    $result = array(
        'step' => 'setup',
        'errors' => $errors
    );
    
    return template("../view/setup.html.php", $result);
}
?>
