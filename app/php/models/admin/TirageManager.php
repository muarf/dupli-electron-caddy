<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/pricing.php';
require_once __DIR__ . '/../../controler/functions/tirage.php';

/**
 * Gestionnaire de tirages pour l'administration
 * Gère l'affichage et la modification des tirages
 */

class TirageManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir la liste des machines
     */
    public function getMachines() {
        $machines = get_machines();
        $organized_machines = array();
        
        // Ajouter toutes les machines (duplicopieurs et photocopieurs)
        foreach ($machines as $machine) {
            $organized_machines[] = $machine;
        }
        
        return $organized_machines;
    }
    
    /**
     * Obtenir les derniers tirages pour une machine
     */
    public function getLastTirages($machine, $sql, $page = 1, $limit = 20) {
        // Déterminer si c'est un duplicopieur ou un photocopieur
        if ($this->isDuplicopieur($machine)) {
            // Pour les duplicopieurs, utiliser la table dupli avec le nom spécifique de la machine
            return last($machine, $sql, $page, $limit);
        } else {
            // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
            return last($machine, $sql, $page, $limit);
        }
    }
    
    /**
     * Obtenir le prix total en attente pour une machine
     */
    public function getPrixEnAttente($machine) {
        // Déterminer si c'est un duplicopieur ou un photocopieur
        if ($this->isDuplicopieur($machine)) {
            // Pour les duplicopieurs, utiliser la table dupli avec le nom spécifique de la machine
            return prix_du($machine);
        } else {
            // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
            return prix_du($machine);
        }
    }
    
    /**
     * Déterminer si une machine est un duplicopieur
     */
    private function isDuplicopieur($machine) {
        $db = pdo_connect();
        $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
        $query->execute([$machine, $machine, $machine]);
        return $query->fetchColumn() > 0;
    }
    
    /**
     * Marquer un tirage comme payé
     */
    public function marquerCommePaye($id, $table) {
        return $this->con->marquer_comme_paye($id, $table);
    }
    
    /**
     * Supprimer plusieurs tirages sélectionnés
     */
    public function deleteSelectedTirages($delete_ids, $delete_machines) {
        $db = pdo_connect();
        $deleted_count = 0;
        $errors = array();
        
        for ($i = 0; $i < count($delete_ids); $i++) {
            $id = intval($delete_ids[$i]);
            $machine = $delete_machines[$i];
            
            try {
                // Déterminer si c'est un duplicopieur ou un photocopieur
                if ($this->isDuplicopieur($machine)) {
                    // Pour les duplicopieurs, supprimer dans la table dupli avec duplicopieur_id
                    $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
                    $query_dup->execute([$machine, $machine, $machine]);
                    $duplicopieur_id = $query_dup->fetchColumn();
                    
                    if ($duplicopieur_id) {
                        $query = $db->prepare('DELETE FROM dupli WHERE id = ? AND duplicopieur_id = ?');
                        $query->execute([$id, $duplicopieur_id]);
                        if ($query->rowCount() > 0) {
                            $deleted_count++;
                        }
                    }
                } else if ($machine === 'A3' || $machine === 'A4' || $machine === 'dupli') {
                    // Pour les anciens duplicopieurs
                    $table_name = ($machine === 'dupli') ? 'dupli' : strtolower($machine);
                    $query = $db->prepare('DELETE FROM ' . $table_name . ' WHERE id = ?');
                    $query->execute([$id]);
                    if ($query->rowCount() > 0) {
                        $deleted_count++;
                    }
                } else {
                    // Pour les photocopieurs
                    $query = $db->prepare('DELETE FROM photocop WHERE id = ? AND marque = ?');
                    $query->execute([$id, $machine]);
                    if ($query->rowCount() > 0) {
                        $deleted_count++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Erreur lors de la suppression du tirage $id ($machine): " . $e->getMessage();
            }
        }
        
        return array(
            'deleted_count' => $deleted_count,
            'errors' => $errors
        );
    }
    
    /**
     * Marquer plusieurs tirages sélectionnés comme payés
     */
    public function markSelectedAsPaid($pay_ids, $pay_machines) {
        $db = pdo_connect();
        $paid_count = 0;
        $errors = array();
        
        for ($i = 0; $i < count($pay_ids); $i++) {
            $id = intval($pay_ids[$i]);
            $machine = $pay_machines[$i];
            
            try {
                // Déterminer si c'est un duplicopieur ou un photocopieur
                if ($this->isDuplicopieur($machine)) {
                    // Pour les duplicopieurs, marquer comme payé dans la table dupli
                    $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
                    $query_dup->execute([$machine, $machine, $machine]);
                    $duplicopieur_id = $query_dup->fetchColumn();
                    
                    if ($duplicopieur_id) {
                        // Utiliser duplicopieur_id si disponible
                        $query = $db->prepare('UPDATE dupli SET paye = "oui" WHERE id = ? AND duplicopieur_id = ?');
                        $query->execute([$id, $duplicopieur_id]);
                        if ($query->rowCount() > 0) {
                            $paid_count++;
                        } else {
                            // Fallback avec nom_machine
                            $query_fallback = $db->prepare('UPDATE dupli SET paye = "oui" WHERE id = ? AND nom_machine = ?');
                            $query_fallback->execute([$id, $machine]);
                            if ($query_fallback->rowCount() > 0) {
                                $paid_count++;
                            }
                        }
                    }
                } else if ($machine === 'A3' || $machine === 'A4' || $machine === 'dupli') {
                    // Pour les anciens duplicopieurs
                    $table_name = ($machine === 'dupli') ? 'dupli' : strtolower($machine);
                    $query = $db->prepare('UPDATE ' . $table_name . ' SET paye = "oui" WHERE id = ?');
                    $query->execute([$id]);
                    if ($query->rowCount() > 0) {
                        $paid_count++;
                    }
                } else {
                    // Pour les photocopieurs
                    $query = $db->prepare('UPDATE photocop SET paye = "oui" WHERE id = ? AND marque = ?');
                    $query->execute([$id, $machine]);
                    if ($query->rowCount() > 0) {
                        $paid_count++;
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Erreur lors du paiement du tirage $id ($machine): " . $e->getMessage();
            }
        }
        
        return array(
            'paid_count' => $paid_count,
            'errors' => $errors
        );
    }
    
    /**
     * Construire la clause SQL selon les paramètres
     */
    public function buildSqlClause() {
        if(!isset($_GET['order']) && !isset($_GET['paye'])){ 
            $phrase = 'Voir seulement les <a href="?admin&tirages&paye">nons-payés</a> ou les classer par <a href="?admin&tirages&order">ordre de prix</a>'; 
            $sql = "ORDER By id DESC";
        }
        if(!isset($_GET['order']) && isset($_GET['paye'])) {  
            $phrase = 'Voir tous les <a href="?admin&tirages">derniers tirages</a> ou classer les nons payés par <a href="?admin&tirages&paye&order">ordre de prix</a>'; 
            $sql = ' WHERE paye = "non" ORDER By id DESC';
        }
        if(isset($_GET['order']) && !isset($_GET['paye'])){ 
            $phrase = 'Voir seulement les <a href="?admin&tirages&paye">nons-payés</a>'; 
            $sql = ' ORDER by prix * 1 DESC'; 
        }
        if(isset($_GET['order']) && isset($_GET['paye'])){ 
            $phrase = 'Voir tous les <a href="?admin&tirages">derniers tirages</a>';  
            $sql = ' WHERE paye = "non" ORDER by prix * 1 DESC';
        }
        
        return array('sql' => $sql, 'phrase' => $phrase);
    }
    
    /**
     * Obtenir toutes les données de tirages pour l'affichage
     */
    public function getAllTirageData() {
        $data = array();
        
        // Construire la clause SQL
        $sqlData = $this->buildSqlClause();
        $data['phrase'] = $sqlData['phrase'];
        
        // Obtenir les machines organisées
        $machines = $this->getMachines();
        $data['machines'] = $machines;
        
        // Obtenir les tirages pour chaque machine
        foreach ($machines as $machine) {
            // Déterminer la page pour cette machine
            $page_param = 'page_' . strtolower(str_replace(' ', '_', $machine));
            $current_page = isset($_GET[$page_param]) ? intval($_GET[$page_param]) : 1;
            
            $data['last'][$machine] = $this->getLastTirages($machine, $sqlData['sql'], $current_page, 20);
            $data['prix_du'][$machine] = $this->getPrixEnAttente($machine);
        }
        
        return $data;
    }
}
?>
