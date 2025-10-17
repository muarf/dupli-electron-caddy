<?php
require_once __DIR__ . '/../../controler/functions/utilities.php';
require_once __DIR__ . '/../../controler/functions/i18n.php';

/**
 * Gestionnaire de news pour l'administration
 * Gère les actualités et informations du site
 */

class NewsManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir toutes les news
     */
    public function getAllNews() {
        return get_news("");
    }
    
    /**
     * Obtenir une news spécifique par ID
     */
    public function getNews($id) {
        return get_news($id);
    }
    
    /**
     * Insérer une nouvelle news
     */
    public function insertNews($titre, $texte) {
        return insert_news($titre, $texte);
    }
    
    /**
     * Mettre à jour une news existante
     */
    public function updateNews($titre, $texte, $id) {
        return update_news($titre, $texte, $id);
    }
    
    /**
     * Supprimer une news
     */
    public function deleteNews($id) {
        return delete_news($id);
    }
    
    /**
     * Obtenir toutes les données de news pour l'affichage
     */
    public function getAllNewsData() {
        $data = array();
        
        // Obtenir toutes les news
        $data['news'] = $this->getAllNews();
        
        return $data;
    }
}
?>
