<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/i18n.php';

/**
 * Gestionnaire des aides machines
 * Gère les opérations CRUD pour les aides et tutoriels des machines
 */

class AideManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Initialiser la connexion
        $this->con = new stdClass();
    }
    
    /**
     * Ajouter une Q&A pour une machine
     */
    public function addQA($machine, $question, $reponse, $ordre = 0, $categorie = 'general') {
        try {
            $db = pdo_connect();
            
            $sql = "INSERT INTO aide_machines_qa (machine, question, reponse, ordre, categorie) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$machine, $question, $reponse, $ordre, $categorie]);
            
            if ($result) {
                return ['success' => 'Q&A ajoutée avec succès'];
            } else {
                return ['error' => 'Erreur lors de l\'ajout'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter une Q&A avec catégorie (alias pour compatibilité)
     */
    public function addQAWithCategory($machine, $question, $reponse, $ordre = 0, $categorie = 'general') {
        return $this->addQA($machine, $question, $reponse, $ordre, $categorie);
    }
    
    /**
     * Ajouter une aide pour une machine (ancienne méthode pour compatibilité)
     */
    public function addAide($machine, $contenu_aide) {
        try {
            $db = pdo_connect();
            
            // Vérifier si une aide existe déjà pour cette machine
            $checkStmt = $db->prepare("SELECT id FROM aide_machines WHERE machine = ?");
            $checkStmt->execute([$machine]);
            
            if ($checkStmt->fetch()) {
                return ['error' => 'Une aide existe déjà pour cette machine. Utilisez la modification pour la mettre à jour.'];
            }
            
            $sql = "INSERT INTO aide_machines (machine, contenu_aide) VALUES (?, ?)";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$machine, $contenu_aide]);
            
            if ($result) {
                return ['success' => 'Aide ajoutée avec succès'];
            } else {
                return ['error' => 'Erreur lors de l\'ajout'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour une Q&A existante
     */
    public function updateQA($id, $machine, $question, $reponse, $ordre = 0, $categorie = 'general') {
        try {
            $db = pdo_connect();
            
            $sql = "UPDATE aide_machines_qa SET machine = ?, question = ?, reponse = ?, ordre = ?, categorie = ?, date_modification = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$machine, $question, $reponse, $ordre, $categorie, $id]);
            
            if ($result) {
                return ['success' => 'Q&A mise à jour avec succès'];
            } else {
                return ['error' => 'Erreur lors de la mise à jour'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour une aide existante (ancienne méthode pour compatibilité)
     */
    public function updateAide($id, $machine, $contenu_aide) {
        try {
            $db = pdo_connect();
            
            $sql = "UPDATE aide_machines SET machine = ?, contenu_aide = ?, date_modification = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$machine, $contenu_aide, $id]);
            
            if ($result) {
                return ['success' => 'Aide mise à jour avec succès'];
            } else {
                return ['error' => 'Erreur lors de la mise à jour'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer une Q&A
     */
    public function deleteQA($id) {
        try {
            $db = pdo_connect();
            
            $sql = "DELETE FROM aide_machines_qa WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => 'Q&A supprimée avec succès'];
            } else {
                return ['error' => 'Erreur lors de la suppression'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer une aide (ancienne méthode pour compatibilité)
     */
    public function deleteAide($id) {
        try {
            $db = pdo_connect();
            
            $sql = "DELETE FROM aide_machines WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => 'Aide supprimée avec succès'];
            } else {
                return ['error' => 'Erreur lors de la suppression'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Erreur : ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtenir toutes les aides
     */
    public function getAllAides() {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM aide_machines ORDER BY machine ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir une Q&A spécifique par ID
     */
    public function getQA($id) {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM aide_machines_qa WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Obtenir une aide spécifique par ID (ancienne méthode pour compatibilité)
     */
    public function getAide($id) {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM aide_machines WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Obtenir une aide par nom de machine
     */
    public function getAideByMachine($machine) {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM aide_machines WHERE machine = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$machine]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Obtenir toutes les machines ayant une aide
     */
    public function getMachinesWithAide() {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT machine FROM aide_machines ORDER BY machine ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir toutes les données d'aide pour l'affichage
     */
    public function getAllAidesData() {
        $data = array();
        
        // Obtenir toutes les Q&A
        $data['qa_list'] = $this->getAllQA();
        
        // Organiser par machine
        $data['qa_by_machine'] = array();
        foreach ($data['qa_list'] as $qa) {
            if (!isset($data['qa_by_machine'][$qa['machine']])) {
                $data['qa_by_machine'][$qa['machine']] = array();
            }
            $data['qa_by_machine'][$qa['machine']][] = $qa;
        }
        
        return $data;
    }
    
    /**
     * Obtenir toutes les Q&A
     */
    public function getAllQA($categorie = null) {
        try {
            $db = pdo_connect();
            
            if ($categorie) {
                $sql = "SELECT * FROM aide_machines_qa WHERE categorie = ? ORDER BY machine ASC, ordre ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute([$categorie]);
            } else {
                $sql = "SELECT * FROM aide_machines_qa ORDER BY machine ASC, ordre ASC";
                $stmt = $db->prepare($sql);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir les Q&A pour une machine spécifique
     */
    public function getQAByMachine($machine, $categorie = 'general') {
        try {
            $db = pdo_connect();
            
            $sql = "SELECT * FROM aide_machines_qa WHERE machine = ? AND categorie = ? ORDER BY ordre ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$machine, $categorie]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir les Q&A pour une machine et une catégorie spécifique
     */
    public function getQAByMachineAndCategory($machine, $categorie = 'general') {
        return $this->getQAByMachine($machine, $categorie);
    }
    
    /**
     * Obtenir la liste de toutes les machines disponibles (duplicopieurs + photocopieurs)
     */
    public function getAllMachines() {
        try {
            $db = pdo_connect();
            $machines = array();
            
            // Récupérer les duplicopieurs
            $stmt_dupli = $db->prepare("SELECT DISTINCT marque, modele FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele");
            $stmt_dupli->execute();
            $duplicopieurs_raw = $stmt_dupli->fetchAll(PDO::FETCH_ASSOC);
            
            // Construire les noms des duplicopieurs (éviter la duplication si marque = modèle)
            $duplicopieurs = array();
            foreach ($duplicopieurs_raw as $dup) {
                $marque = trim($dup['marque']);
                $modele = trim($dup['modele']);
                
                if ($marque === $modele) {
                    $duplicopieurs[] = $marque;
                } else {
                    $duplicopieurs[] = $marque . ' ' . $modele;
                }
            }
            
            // Récupérer les photocopieurs
            $stmt_photo = $db->prepare("SELECT DISTINCT marque, modele FROM photocopieurs WHERE actif = 1 ORDER BY marque, modele");
            $stmt_photo->execute();
            $photocopieurs_raw = $stmt_photo->fetchAll(PDO::FETCH_ASSOC);
            
            // Construire les noms des photocopieurs (éviter la duplication si marque = modèle)
            $photocopieurs = array();
            foreach ($photocopieurs_raw as $photo) {
                $marque = trim($photo['marque']);
                $modele = trim($photo['modele']);
                
                if ($marque === $modele) {
                    $photocopieurs[] = $marque;
                } else {
                    $photocopieurs[] = $marque . ' ' . $modele;
                }
            }
            
            // Fusionner et trier
            $machines = array_merge($duplicopieurs, $photocopieurs);
            sort($machines);
            
            return $machines;
            
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
