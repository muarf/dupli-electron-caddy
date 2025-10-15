<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/pricing.php';
require_once __DIR__ . '/../../controler/functions/consommation.php';
require_once __DIR__ . '/../../controler/functions/simple_i18n.php';

/**
 * Gestionnaire de prix pour l'administration
 * Gère les prix des consommables (encre, master) et du papier
 */

class PriceManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir tous les prix
     */
    public function getPrices() {
        return get_price();
    }
    
    /**
     * Insérer ou mettre à jour un prix
     */
    public function insertPrice($machine, $type, $prix_pack, $prix_unite) {
        $machine_id = null;
        
        if (strpos($machine, 'dupli_') === 0) {
            // Cas : "dupli_18" -> machine_id = "18", machine_type = "dupli"
            $machine_id = str_replace('dupli_', '', $machine);
            $machine = 'dupli';
        } elseif ($machine === 'photocop') {
            // Cas : photocopieur -> machine_id = "1"
            $machine_id = "1";
        } else {
            // Cas : nom d'affichage du duplicopieur (ex: "ricoh dx4545")
            // Chercher l'ID dans la base de données
            $db = pdo_connect();
            // SQLite n'a pas CONCAT, on utilise l'opérateur ||
            if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque || " " || modele = ? OR marque = ?) AND actif = 1 LIMIT 1');
            } else {
                $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ?) AND actif = 1 LIMIT 1');
            }
            $query->execute([$machine, $machine]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $machine_id = $result['id'];
                // Garder le format "dupli" pour machine_type
                $machine = 'dupli';
            } else {
                $machine_id = "1"; // Fallback
                $machine = 'dupli';
            }
        }
        
        return insert_prix($machine, $machine_id, $type, $prix_pack, $prix_unite);
    }
    
    /**
     * Insérer ou mettre à jour le prix du papier
     */
    public function insertPapier($prix_A4) {
        return insert_papier($prix_A4);
    }
    
    /**
     * Obtenir les consommables pour une machine
     */
    public function getConsommables($machine) {
        return get_cons($machine);
    }
    
    /**
     * Convertir le nom de machine en clé de prix
     */
    private function getMachinePriceKey($machine_name, $machine_id = null) {
        // Pour les photocopieurs, chercher l'ID réel dans la base de données
        $db = pdo_connect();
        $query = $db->prepare('SELECT id FROM photocopieurs WHERE marque = ? AND actif = 1 LIMIT 1');
        $query->execute([$machine_name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return 'photocop_' . $result['id'];
        }
        
        // Pour les duplicopieurs, utiliser l'ID fourni ou chercher dans la base
        if ($machine_id) {
            return 'dupli_' . $machine_id;
        }
        
        // Si pas d'ID fourni, chercher dans la base de données
        // SQLite n'a pas CONCAT, on utilise l'opérateur ||
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque || " " || modele = ? OR marque = ?) AND actif = 1 LIMIT 1');
        } else {
            $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ?) AND actif = 1 LIMIT 1');
        }
        $query->execute([$machine_name, $machine_name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return 'dupli_' . $result['id'];
        }
        
        // Fallback sur dupli_1 si pas trouvé
        return 'dupli_1';
    }
    
    /**
     * Obtenir les photocopieurs installés avec leurs noms réels
     */
    public function getPhotocopieurs() {
        $db = pdo_connect();
        $query = $db->query('SELECT id, marque, modele FROM photocopieurs WHERE actif = 1');
        $photocopieurs = array();
        
        while ($result = $query->fetch(PDO::FETCH_OBJ)) {
            $photocopieurs[] = array(
                'id' => $result->id,
                'marque' => $result->marque,
                'modele' => $result->modele,
                'display_name' => $result->modele ?: $result->marque
            );
        }
        
        return $photocopieurs;
    }
    
    /**
     * Obtenir les duplicopieurs installés avec leurs noms réels
     */
    public function getDuplicopieurs() {
        $db = pdo_connect();
        $query = $db->query('SELECT id, marque, modele, tambours FROM duplicopieurs WHERE actif = 1');
        $duplicopieurs = array();
        
        while ($result = $query->fetch(PDO::FETCH_OBJ)) {
            $machine_name = $result->marque . ' ' . $result->modele;
            if ($result->marque === $result->modele) {
                $machine_name = $result->marque;
            }
            
            // Parser les tambours
            $tambours = ['tambour_noir']; // Fallback par défaut
            if (!empty($result->tambours)) {
                try {
                    $tambours = json_decode($result->tambours, true);
                    if (!is_array($tambours)) {
                        $tambours = ['tambour_noir'];
                    }
                } catch (Exception $e) {
                    $tambours = ['tambour_noir'];
                }
            }
            
            $duplicopieurs[] = array(
                'id' => $result->id,
                'marque' => $result->marque,
                'modele' => $result->modele,
                'display_name' => $machine_name,
                'machine_name' => $machine_name,
                'tambours' => $tambours
            );
        }
        
        return $duplicopieurs;
    }
    
    /**
     * Obtenir les prix d'encre pour une photocopieuse spécifique
     */
    public function getPrixEncrePhotocop($photocop_name, $couleur) {
        // Fonction locale pour éviter les conflits d'inclusion
        return $this->prix_encre_photocop_by_name_local($photocop_name, $couleur);
    }
    
    /**
     * Obtenir les prix pour un duplicopieur spécifique
     */
    public function getPrixEncreDuplicop($machine_name, $type) {
        $db = pdo_connect();
        $prix = get_price();
        
        // Récupérer l'ID du duplicopieur
        // SQLite n'a pas CONCAT, on utilise l'opérateur ||
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque || " " || modele = ? OR marque = ?) AND actif = 1 LIMIT 1');
        } else {
            $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ?) AND actif = 1 LIMIT 1');
        }
        $query->execute([$machine_name, $machine_name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return array(
                'moyenne_totale' => array('temps' => 0, 'nb_p' => 0, 'nb_m' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => 0, 'class' => 'info', 'color' => 'green'
            );
        }
        
        $machine_key = 'dupli_' . $result['id'];
        $prix_unite = isset($prix[$machine_key][$type]['unite']) ? $prix[$machine_key][$type]['unite'] : 0;
        $prix_pack = isset($prix[$machine_key][$type]['pack']) ? $prix[$machine_key][$type]['pack'] : 0;
        
        // Récupérer les données de consommables
        $query_cons = $db->prepare('SELECT * FROM cons WHERE machine = ? AND type = ? ORDER BY date ASC');
        $query_cons->execute([strtolower($machine_name), $type]);
        
        $res = array();
        $i = 0;
        while ($result_cons = $query_cons->fetch(PDO::FETCH_OBJ)) {
            $res[$i]['date'] = intval($result_cons->date);
            $res[$i]['type'] = $result_cons->type;
            $res[$i]['nb_p'] = $result_cons->nb_p;
            $res[$i]['nb_m'] = $result_cons->nb_m;
            $i++;
        }
        
        $max = count($res);
        
        // Si pas de données, retourner les prix de base
        if ($max == 0) {
            return array(
                'moyenne_totale' => array('temps' => 0, 'nb_p' => 0, 'nb_m' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => $prix_unite, 'class' => 'info', 'color' => 'green',
                'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
            );
        }
        
        // Calculer les moyennes selon le type (passages ou masters)
        for($i = 0; $i < $max; $i++) {
            if($i > 0) {
                $ii = $i - 1; 
                $res['temps_diff'][$i] = $res[$i]['date'] - $res[$ii]['date'];
                
                if ($type === 'master') {
                    // Pour les masters, calculer la différence des compteurs masters
                    $res['nb_m'][$i] = $res[$i]['nb_m'] - $res[$ii]['nb_m'];
                } else {
                    // Pour les tambours, calculer les passages entre changements
                    $query_tirages = $db->prepare('
                        SELECT SUM(passage_ap - passage_av) as total_passages 
                        FROM dupli 
                        WHERE duplicopieur_id = ? 
                        AND (tambour = ? OR tambour IS NULL OR tambour = "")
                        AND date BETWEEN ? AND ?
                    ');
                    $query_tirages->execute([$result['id'], $type, $res[$ii]['date'], $res[$i]['date']]);
                    $tirages_result = $query_tirages->fetch(PDO::FETCH_ASSOC);
                    $res['nb_p'][$i] = $tirages_result['total_passages'] ?? 0;
                }
                $ii++;  
            }
        }
        
        $temps_moy = isset($res['temps_diff']) && is_array($res['temps_diff']) ? array_sum($res['temps_diff'])/count($res['temps_diff']) : 0;
        
        if ($type === 'master') {
            $nb_m_moy = isset($res['nb_m']) && is_array($res['nb_m']) ? array_sum($res['nb_m'])/count($res['nb_m']) : 0;
            $nb_p_moy = 0; // Pas de passages pour les masters
        } else {
            $nb_p_moy = isset($res['nb_p']) && is_array($res['nb_p']) ? array_sum($res['nb_p'])/count($res['nb_p']) : 0;
            $nb_m_moy = 0; // Pas de masters pour les tambours
        }
        
        $temps_depuis = time() - ($res[$max-1]['date'] ?? time());
        if($temps_depuis == 0) { $temps_depuis = 1; }
        
        // Calculer le prix selon le type
        if ($type === 'master') {
            if($nb_m_moy == 0) { $nb_m_moy = 1; }
            $prix_calcule = $prix_pack / $nb_m_moy;
        } else {
            if($nb_p_moy == 0) { $nb_p_moy = 1; }
            $prix_calcule = $prix_pack / $nb_p_moy;
        }
        
        $temps_jusqua = $temps_moy - $temps_depuis;
        
        // Déterminer la classe CSS
        if($temps_jusqua < -30) { $class = "danger"; }
        elseif($temps_jusqua < 0) { $class = "warning"; }
        elseif($temps_jusqua < 30) { $class = "info"; }
        else { $class = "success"; }
        
        // Déterminer la couleur
        $color = ($prix_calcule > $prix_unite) ? "red" : "green";
        
        // Calculer le nombre actuel depuis la dernière entrée dans la table dupli
        $nb_actuel = 0;
        if ($max > 0) {
            if ($type === 'master') {
                // Pour les masters, récupérer le dernier compteur master
                $query_actuel = $db->prepare('SELECT master_ap FROM dupli WHERE duplicopieur_id = ? ORDER BY date DESC LIMIT 1');
                $query_actuel->execute([$result['id']]);
                $result_actuel = $query_actuel->fetch(PDO::FETCH_ASSOC);
                $nb_actuel = $result_actuel['master_ap'] ?? 0;
            } else {
                // Pour les tambours, récupérer le dernier compteur de passages
                $query_actuel = $db->prepare('SELECT passage_ap FROM dupli WHERE duplicopieur_id = ? ORDER BY date DESC LIMIT 1');
                $query_actuel->execute([$result['id']]);
                $result_actuel = $query_actuel->fetch(PDO::FETCH_ASSOC);
                $nb_actuel = $result_actuel['passage_ap'] ?? 0;
            }
        }
        
        return array(
            'moyenne_totale' => array('temps' => $temps_moy, 'nb_p' => $nb_p_moy, 'nb_m' => $nb_m_moy),
            'nb_actuel' => $nb_actuel, 'temps_depuis' => $temps_depuis, 'temps_jusqua' => $temps_jusqua,
            'prix_calcule' => $prix_calcule, 'class' => $class, 'color' => $color,
            'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
        );
    }
    
    /**
     * Obtenir les prix pour un tambour spécifique d'un duplicopieur
     */
    public function getPrixTambourDuplicop($machine_name, $tambour, $duplicopieur_id) {
        $db = pdo_connect();
        $prix = get_price();
        
        $machine_key = 'dupli_' . $duplicopieur_id;
        $prix_unite = isset($prix[$machine_key][$tambour]['unite']) ? $prix[$machine_key][$tambour]['unite'] : 0;
        $prix_pack = isset($prix[$machine_key][$tambour]['pack']) ? $prix[$machine_key][$tambour]['pack'] : 0;
        
        // Récupérer les changements de tambour dans cons
        if ($tambour === 'tambour_noir') {
            // Pour tambour_noir, chercher les changements de type "tambour" avec tambour = "tambour_noir" ou les anciens changements "encre"
            $query_cons = $db->prepare('SELECT * FROM cons WHERE machine = ? AND ((type = "tambour" AND tambour = ?) OR type = "encre") ORDER BY date ASC');
            $query_cons->execute([strtolower($machine_name), $tambour]);
        } else {
            // Pour les autres tambours, chercher les changements de type "tambour" avec le tambour spécifique
            $query_cons = $db->prepare('SELECT * FROM cons WHERE machine = ? AND type = "tambour" AND tambour = ? ORDER BY date ASC');
            $query_cons->execute([strtolower($machine_name), $tambour]);
        }
        
        $res = array();
        $i = 0;
        while ($result_cons = $query_cons->fetch(PDO::FETCH_OBJ)) {
            $res[$i]['date'] = intval($result_cons->date);
            $res[$i]['type'] = $result_cons->type;
            $res[$i]['nb_p'] = $result_cons->nb_p;
            $res[$i]['nb_m'] = $result_cons->nb_m;
            $i++;
        }
        
        $max = count($res);
        
        // Si pas de données, retourner les prix de base
        if ($max == 0) {
            return array(
                'moyenne_totale' => array('temps' => 0, 'nb_p' => 0, 'nb_m' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => $prix_unite, 'class' => 'info', 'color' => 'green',
                'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
            );
        }
        
        // Calculer les moyennes basées sur les tirages réels
        for($i = 0; $i < $max; $i++) {
            if($i > 0) {
                $ii = $i - 1; 
                
                // Pour les tambours, utiliser directement la différence des compteurs de passage
                $res['temps_diff'][$i] = $res[$i]['date'] - $res[$ii]['date']; 
                $res['nb_f'][$i] = $res[$i]['nb_p'] - $res[$ii]['nb_p']; // Différence des compteurs de passage
                $ii++;  
            }
        }
        
        $temps_moy = isset($res['temps_diff']) && is_array($res['temps_diff']) ? array_sum($res['temps_diff'])/count($res['temps_diff']) : 0;
        $nb_p_moy = isset($res['nb_f']) && is_array($res['nb_f']) ? array_sum($res['nb_f'])/count($res['nb_f']) : 0;
        
        $temps_depuis = time() - ($res[$max-1]['date'] ?? time());
        if($temps_depuis == 0) { $temps_depuis = 1; }
        if($nb_p_moy == 0) { $nb_p_moy = 1; }
        
        $temps_jusqua = $temps_moy - $temps_depuis;
        $prix_calcule = $prix_pack / $nb_p_moy;
        
        // Déterminer la classe CSS
        if($temps_jusqua < -30) { $class = "danger"; }
        elseif($temps_jusqua < 0) { $class = "warning"; }
        elseif($temps_jusqua < 30) { $class = "info"; }
        else { $class = "success"; }
        
        // Déterminer la couleur
        $color = ($prix_calcule > $prix_unite) ? "red" : "green";
        
        // Calculer le nombre actuel de passages depuis le dernier changement
        $nb_actuel = 0;
        if ($max > 0) {
            $dernier_changement = $res[$max-1]['date'];
            $dernier_compteur = $res[$max-1]['nb_p'];
            
            // Récupérer le compteur actuel de la machine
            $query_actuel = $db->prepare('
                SELECT MAX(passage_ap) as compteur_actuel 
                FROM dupli 
                WHERE duplicopieur_id = ? 
                AND date >= ?
            ');
            $query_actuel->execute([$duplicopieur_id, $dernier_changement]);
            $result_actuel = $query_actuel->fetch(PDO::FETCH_ASSOC);
            $compteur_actuel = $result_actuel['compteur_actuel'] ?? $dernier_compteur;
            
            $nb_actuel = $compteur_actuel - $dernier_compteur;
        }
        
        return array(
            'moyenne_totale' => array('temps' => $temps_moy, 'nb_p' => $nb_p_moy, 'nb_m' => 0),
            'nb_actuel' => $nb_actuel, 'temps_depuis' => $temps_depuis, 'temps_jusqua' => $temps_jusqua,
            'prix_calcule' => $prix_calcule, 'class' => $class, 'color' => $color,
            'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
        );
    }
    
    /**
     * Fonction locale pour calculer les prix d'encre
     */
    private function prix_encre_photocop_by_name_local($photocop_name, $couleur) {
        $db = pdo_connect();
        $prix = get_price();
        
        // Si c'est "couleur", calculer la somme des 4 couleurs individuelles
        if ($couleur === "couleur") {
            $couleurs = array("noire", "bleue", "jaune", "rouge");
            $prix_total_unite = 0;
            $prix_total_pack = 0;
            
            foreach ($couleurs as $couleur_individuelle) {
                $machine_key = $this->getMachinePriceKey($photocop_name);
                $prix_unite = isset($prix[$machine_key][$couleur_individuelle]['unite']) ? $prix[$machine_key][$couleur_individuelle]['unite'] : 0;
                $prix_pack = isset($prix[$machine_key][$couleur_individuelle]['pack']) ? $prix[$machine_key][$couleur_individuelle]['pack'] : 0;
                
                $prix_total_unite += $prix_unite;
                $prix_total_pack += $prix_pack;
            }
            
            return array(
                'moyenne_total' => array('temps' => 0, 'nb_p' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => $prix_total_unite, 'class' => 'info', 'color' => 'green'
            );
        }
        
        // Pour les consommables de toner (tambour, dev), retourner les prix de base
        if (in_array($couleur, ['tambour', 'dev'])) {
            $machine_key = $this->getMachinePriceKey($photocop_name);
            $prix_unite = isset($prix[$machine_key][$couleur]['unite']) ? $prix[$machine_key][$couleur]['unite'] : 0;
            $prix_pack = isset($prix[$machine_key][$couleur]['pack']) ? $prix[$machine_key][$couleur]['pack'] : 0;
            
            return array(
                'moyenne_total' => array('temps' => 0, 'nb_p' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => $prix_unite, 'class' => 'info', 'color' => 'green',
                'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
            );
        }
        
        // Pour les couleurs individuelles, faire les vrais calculs
        // Récupérer les données de consommables depuis la table cons
        $query = $db->prepare('SELECT * FROM cons WHERE machine = ? AND type = ? ORDER BY date ASC');
        $query->execute(array($photocop_name, $couleur));
        
        $res = array();
        $i = 0;
        while ($result = $query->fetch(PDO::FETCH_OBJ)) {
            $res[$i]['date'] = intval($result->date);
            $res[$i]['nb_p'] = $result->nb_p;
            $i++;
        }
        
        $max = count($res);
        
        // Si pas de données, retourner des valeurs par défaut
        if ($max == 0) {
            $machine_key = $this->getMachinePriceKey($photocop_name);
            $prix_unite = isset($prix[$machine_key][$couleur]['unite']) ? $prix[$machine_key][$couleur]['unite'] : 0;
            
            return array(
                'moyenne_total' => array('temps' => 0, 'nb_p' => 0),
                'nb_actuel' => 0, 'temps_depuis' => 0, 'temps_jusqua' => 0,
                'prix_calcule' => 0, 'class' => 'info', 'color' => 'green',
                'prix_unite' => $prix_unite
            );
        }
        
        // Calculer les moyennes
        for($i = 0; $i < $max; $i++) {
            if($i > 0) {
                $ii = $i - 1; 
                $res['temps_diff'][$i] = $res[$i]['date'] - $res[$ii]['date']; 
                $res['nb_f'][$i] = $res[$i]['nb_p'] - $res[$ii]['nb_p'];
                $ii++;  
            }
        }
        
        // Vérifier si les tableaux existent avant d'utiliser array_sum
        $temps_moy = isset($res['temps_diff']) && is_array($res['temps_diff']) ? array_sum($res['temps_diff'])/count($res['temps_diff']) : 0;
        $nb_p_moy = isset($res['nb_f']) && is_array($res['nb_f']) ? array_sum($res['nb_f'])/count($res['nb_f']) : 0;
        
        $machine_key = $this->getMachinePriceKey($photocop_name);
        $prix_unite = isset($prix[$machine_key][$couleur]['unite']) ? $prix[$machine_key][$couleur]['unite'] : 0;
        $prix_pack = isset($prix[$machine_key][$couleur]['pack']) ? $prix[$machine_key][$couleur]['pack'] : 0;
        
        $temps_depuis = time() - ($res[$max-1]['date'] ?? time());
        if($temps_depuis == 0) { $temps_depuis = 1; }
        if($nb_p_moy == 0) { $nb_p_moy = 1; }
        
        $temps_jusqua = $temps_moy - $temps_depuis;
        $prix_calcule = $prix_pack / $nb_p_moy;
        
        // Déterminer la classe CSS
        if($temps_jusqua < -30) { $class = "danger"; }
        elseif($temps_jusqua < 0) { $class = "warning"; }
        elseif($temps_jusqua < 30) { $class = "info"; }
        else { $class = "success"; }
        
        // Déterminer la couleur
        $color = ($prix_calcule > $prix_unite) ? "red" : "green";
        
        // Calculer le nombre actuel de passages depuis le dernier changement
        $nb_actuel = 0;
        if ($max > 0) {
            $dernier_changement = $res[$max-1]['date'];
            $dernier_compteur = $res[$max-1]['nb_p'];
            
            // Récupérer le compteur actuel depuis la table photocop
            $query_actuel = $db->prepare('SELECT SUM(nb_f) as total_passages FROM photocop WHERE marque = ? AND date >= ?');
            $query_actuel->execute([$photocop_name, $dernier_changement]);
            $result_actuel = $query_actuel->fetch(PDO::FETCH_ASSOC);
            
            // Le nb_actuel est le compteur du dernier changement + les passages depuis
            $nb_actuel = $dernier_compteur + ($result_actuel['total_passages'] ?? 0);
        }
        
        return array(
            'moyenne_total' => array('temps' => $temps_moy, 'nb_p' => $nb_p_moy),
            'nb_actuel' => $nb_actuel, 'temps_depuis' => $temps_depuis, 'temps_jusqua' => $temps_jusqua,
            'prix_calcule' => $prix_calcule, 'class' => $class, 'color' => $color,
            'prix_unite' => $prix_unite, 'prix_pack' => $prix_pack
        );
    }
    
    /**
     * Obtenir toutes les données de prix pour l'affichage
     */
    public function getAllPriceData() {
        $data = array();
        
        // Prix généraux
        $data['prix'] = $this->getPrices();
        
        // Duplicopieurs installés - adapter pour le template existant
        $duplicopieurs = $this->getDuplicopieurs();
        $data['duplicopieurs_installes'] = $duplicopieurs;
        
        if (!empty($duplicopieurs)) {
            // Chaque duplicopieur devient une clé dans $machines
            foreach ($duplicopieurs as $dup_data) {
                $machine_name = $dup_data['machine_name'];
                $display_name = $dup_data['display_name'];
                
                // Structure attendue par le template : $machines[nom_machine] = [tambours + master]
                $types = array_merge($dup_data['tambours'], ['master']);
                $data['machines'][$display_name] = $types;
                
                // Récupérer les consommables pour ce duplicopieur spécifique
                foreach ($dup_data['tambours'] as $tambour) {
                    $data['cons'][$display_name][$tambour] = $this->getPrixTambourDuplicop($machine_name, $tambour, $dup_data['id']);
                }
                $data['cons'][$display_name]['master'] = $this->getPrixEncreDuplicop($machine_name, 'master');
                
                // Ajouter les prix dans la structure attendue
                $machine_key = 'dupli_' . $dup_data['id'];
                if (isset($data['prix'][$machine_key])) {
                    $data['prix'][$display_name] = $data['prix'][$machine_key];
                }
            }
        }
        
        // Photocopieurs installés
        $photocopiers = $this->getPhotocopieurs();
        $data['photocopiers_installes'] = $photocopiers;
        
        if (!empty($photocopiers)) {
            $data['cons']['photocopieurs'] = array();
            $data['photocop'] = array();
            
            foreach ($photocopiers as $photocop_data) {
                $photocop_name = $photocop_data['marque']; // Utiliser la marque pour les calculs
                $display_name = $photocop_data['display_name']; // Nom d'affichage
                
                // Déterminer le type d'encre pour savoir quels consommables afficher
                $db = pdo_connect();
                $query_type = $db->prepare('SELECT type_encre FROM photocopieurs WHERE marque = ? AND actif = 1 LIMIT 1');
                $query_type->execute([$photocop_name]);
                $type_result = $query_type->fetch(PDO::FETCH_ASSOC);
                $type_encre = $type_result ? $type_result['type_encre'] : 'encre';
                
                if ($type_encre === 'toner') {
                    // Photocopieur à toner : afficher les toners + tambour + dev
                    $consommables = array("noir", "cyan", "magenta", "jaune", "tambour", "dev");
                    foreach ($consommables as $consommable) {
                        $data['cons']['photocopieurs'][$display_name][$consommable] = $this->getPrixEncrePhotocop($photocop_name, $consommable);
                    }
                } else {
                    // Photocopieur à encre : afficher les couleurs d'encre
                    $couleurs = array("noire", "bleue", "jaune", "rouge");
                    foreach ($couleurs as $couleur) {
                        $data['cons']['photocopieurs'][$display_name][$couleur] = $this->getPrixEncrePhotocop($photocop_name, $couleur);
                    }
                }
                
                // Ajouter les prix dans la structure attendue
                $machine_key = $this->getMachinePriceKey($photocop_name);
                if (isset($data['prix'][$machine_key])) {
                    $data['prix'][$display_name] = $data['prix'][$machine_key];
                }
            }
        }
        
        return $data;
    }
}