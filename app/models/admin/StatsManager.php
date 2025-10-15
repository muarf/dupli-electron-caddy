<?php
require_once __DIR__ . '/../../controler/functions/utilities.php';
require_once __DIR__ . '/../../controler/functions/simple_i18n.php';

/**
 * Gestionnaire de statistiques pour l'administration
 * Gère les textes et messages des statistiques
 */

class StatsManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir le texte d'introduction des statistiques
     */
    public function getStatsIntroText() {
        return get_site_setting('stats_intro_text', '');
    }
    
    /**
     * Mettre à jour le texte d'introduction des statistiques
     */
    public function updateStatsIntroText($text) {
        return update_site_setting('stats_intro_text', $text);
    }
    
    /**
     * Obtenir toutes les données de statistiques pour l'affichage
     */
    public function getAllStatsData() {
        $data = array();
        
        // Obtenir le texte d'introduction des statistiques
        $data['stats_intro_text'] = $this->getStatsIntroText();
        
        return $data;
    }
}
?>
