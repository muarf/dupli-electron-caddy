<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/simple_i18n.php';

/**
 * Gestionnaire des changements de consommables
 * Gère les opérations CRUD pour les changements
 */

require_once __DIR__ . '/../../controler/func.php';

class ChangesManager {
    private $con;
    
    public function __construct() {
        // Initialiser la connexion
        $this->con = new stdClass();
    }
    
    /**
     * Ajouter un changement
     */
    public function addChange($data) {
        try {
            $db = pdo_connect();
            
            // Normaliser la date au format timestamp
            $date = isset($data['date']) ? $data['date'] : time();
            if (!is_numeric($date)) {
                $date = strtotime($date);
            }
            
            $sql = "INSERT INTO cons (machine, type, date, nb_p, nb_m, tambour) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([
                $data['machine'],
                $data['type'], 
                $date,
                $data['nb_p'],
                $data['nb_m'],
                $data['tambour'] ?? ''
            ]);
            
            if ($result) {
                return ['success' => 'Changement ajouté avec succès'];
            } else {
                return ['error' => 'Erreur lors de l\'ajout'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un changement
     */
    public function deleteChange($id) {
        try {
            $db = pdo_connect();
            
            // S'assurer que $id est un entier
            $id = intval($id);
            
            $sql = "DELETE FROM cons WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => 'Changement supprimé avec succès'];
            } else {
                return ['error' => 'Erreur lors de la suppression'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour un changement
     */
    public function updateChange($id, $data) {
        try {
            $db = pdo_connect();
            
            // Normaliser la date au format timestamp
            $date = isset($data['date']) ? $data['date'] : time();
            if (!is_numeric($date)) {
                $date = strtotime($date);
            }
            
            $sql = "UPDATE cons SET machine = ?, type = ?, date = ?, nb_p = ?, nb_m = ?, tambour = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([
                $data['machine'],
                $data['type'],
                $date, 
                $data['nb_p'],
                $data['nb_m'],
                $data['tambour'] ?? '',
                $id
            ]);
            
            if ($result) {
                return ['success' => 'Changement modifié avec succès'];
            } else {
                return ['error' => 'Erreur lors de la modification'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer un changement par ID
     */
    public function getChange($id) {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM cons WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupérer tous les changements avec pagination
     */
    public function getAllChanges($limit = 50, $offset = 0) {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM cons ORDER BY date DESC LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$limit, $offset]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
}
?>












