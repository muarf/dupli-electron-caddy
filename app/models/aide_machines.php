<?php
/**
 * Page publique d'aide et tutoriels des machines
 * Affiche les questions/réponses organisées par machine
 */

require_once __DIR__ . '/admin/AideManager.php';
require_once __DIR__ . '/../controler/functions/simple_i18n.php';

function Action($conf) {
    // Créer l'instance du gestionnaire d'aides
    $aideManager = new AideManager($conf);
    
    // Obtenir toutes les données Q&A
    $data = $aideManager->getAllAidesData();
    
    // Obtenir la liste de toutes les machines pour le sélecteur
    $data['all_machines'] = $aideManager->getAllMachines();
    
    // Si une machine spécifique est demandée via GET
    if (isset($_GET['machine']) && !empty($_GET['machine'])) {
        $machine_demandee = htmlspecialchars($_GET['machine']);
        $data['qa_selectionnees'] = $aideManager->getQAByMachine($machine_demandee);
        $data['machine_selectionnee'] = $machine_demandee;
    }
    
    // Retourner le template avec les données
    return template("../view/aide_machines.html.php", $data);
}
?>
