<?php
/**
 * Séparateur de couleur Riso
 * Tout le traitement est fait côté client en JavaScript
 */
require_once __DIR__ . '/../controler/functions/i18n.php';

function Action($conf) {
    // Récupérer la liste des tambours depuis la base de données
    $tambours = array();
    
    try {
        $db = pdo_connect();
        
        // Récupérer tous les tambours actifs
        $query = $db->query('SELECT DISTINCT tambours FROM duplicopieurs WHERE actif = 1');
        $results = $query->fetchAll(PDO::FETCH_ASSOC);
        
        // Parser tous les tambours JSON et créer une liste unique
        $tambours_set = array();
        foreach ($results as $row) {
            if (!empty($row['tambours'])) {
                try {
                    $tamb = json_decode($row['tambours'], true);
                    if (is_array($tamb)) {
                        foreach ($tamb as $t) {
                            $tambours_set[$t] = true;
                        }
                    }
                } catch (Exception $e) {
                    // Ignorer les erreurs de parsing
                }
            }
        }
        
        // Convertir en tableau et ajouter les tambours standards
        $tambours = array_keys($tambours_set);
        
        // Ajouter les tambours Riso standards si pas déjà présents
        $standard_tambours = array('tambour_noir', 'tambour_rouge', 'tambour_bleu', 'tambour_jaune', 'tambour_vert', 'tambour_violet');
        foreach ($standard_tambours as $t) {
            if (!in_array($t, $tambours)) {
                $tambours[] = $t;
            }
        }
        
    } catch (Exception $e) {
        // Fallback sur les tambours standards
        $tambours = array('tambour_noir', 'tambour_rouge', 'tambour_bleu', 'tambour_jaune', 'tambour_vert', 'tambour_violet');
    }
    
    return template("../view/riso_separator.html.php", array(
        'tambours' => $tambours
    ));
}

?>
