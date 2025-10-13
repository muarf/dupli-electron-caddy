<?php
function Action(){
    try {
        $db = pdo_connect();
        
        // Vérifier s'il y a des machines dans la base
        $has_machines = check_machines_exist();
        
        if (!$has_machines) {
            // Pas de machines, rediriger vers le choix d'installation
            header('Location: ?setup&mode=choice');
            exit;
        }
        
        // Il y a des machines, rediriger vers l'accueil
        header('Location: ?accueil');
        exit;
    } catch (PDOException $e) {
        // Base de données non trouvée, rediriger vers le choix d'installation
        header('Location: ?setup&mode=choice');
        exit;
    }
}
?>
