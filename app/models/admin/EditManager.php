<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/tirage.php';

/**
 * Gestionnaire d'édition des tirages pour l'administration
 * Gère l'édition et la suppression des tirages individuels
 */

class EditManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir un tirage spécifique par ID et machine
     */
    public function getTirage($id, $machine) {
        return get_tirage($id, $machine);
    }
    
    /**
     * Mettre à jour un tirage
     */
    public function updateTirage($id, $data, $machine) {
        // Filtrer les champs qui ne doivent pas être mis à jour
        $fieldsToExclude = array('password', 'save', 'delete');
        $filteredData = array();
        
        foreach ($data as $key => $value) {
            if (!in_array($key, $fieldsToExclude)) {
                $filteredData[$key] = $value;
            }
        }
        
        return update_tirage($id, $filteredData, $machine);
    }
    
    /**
     * Supprimer un tirage
     */
    public function deleteTirage($id, $machine) {
        $db = pdo_connect();
        $id = ceil($id);
        
        // Vérifier si c'est un duplicopieur (nom complet comme "Ricoh dx4545")
        $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque = ? OR modele = ?)');
        $query->execute([$machine, $machine]);
        $is_duplicopieur = $query->fetchColumn() > 0;
        
        if($is_duplicopieur) {
            // C'est un duplicopieur, supprimer dans la table dupli avec le nom_machine
            $db->query('DELETE from dupli WHERE id= '.$id.' AND nom_machine = "'.$machine.'"');
        } else {
            // Pour les photocopieurs, vérifier que c'est une marque valide
            $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
            $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array($machine, $valid_marques)) {
                throw new Exception("Machine '$machine' non autorisée");
            }
            $db->query('DELETE from photocop WHERE id= '.$id.' AND marque = "'.$machine.'"');
        }
        
        return true;
    }
    
    /**
     * Déterminer la table à utiliser selon la machine
     */
    public function getTableName($machine) {
        $table = $machine;
        if($machine == "A3" || $machine == "A4") {
            // Pour les duplicopieurs, utiliser la table en minuscules
            $table = strtolower($machine);
        } else {
            // Pour les photocopieurs, utiliser la table 'photocop'
            $table = 'photocop';
        }
        return $table;
    }
    
    /**
     * Obtenir toutes les données d'édition pour l'affichage
     */
    public function getEditData($id, $machine) {
        $data = array();
        
        // Obtenir le tirage
        $data['tirage'] = $this->getTirage($id, $machine);
        
        // Déterminer la table
        $data['table'] = $this->getTableName($machine);
        
        // Vérifier si le tirage a été trouvé
        if($data['tirage'] === false) {
            $data['error'] = 'Tirage non trouvé';
        }
        
        return $data;
    }
}
?>
