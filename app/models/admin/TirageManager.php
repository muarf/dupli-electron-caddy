<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/pricing.php';
require_once __DIR__ . '/../../controler/functions/tirage.php';
require_once __DIR__ . '/../../controler/functions/i18n.php';

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
    public function getLastTirages($machine, $sql, $page = 1, $limit = 20, $params = []) {
        // Debug: log l'appel
        if ($machine == 'comcolor' && $page == 4) {
            error_log("DEBUG getLastTirages: machine=$machine, page=$page, limit=$limit");
        }
        
        // Déterminer si c'est un duplicopieur ou un photocopieur
        if ($this->isDuplicopieur($machine)) {
            // Pour les duplicopieurs, utiliser la table dupli avec le nom spécifique de la machine
            $result = last($machine, $sql, $page, $limit, $params);
            if ($machine == 'comcolor' && $page == 4) {
                error_log("DEBUG getLastTirages: après last(), count=" . count($result));
            }
            return $result;
        } else {
            // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
            $result = last($machine, $sql, $page, $limit, $params);
            if ($machine == 'comcolor' && $page == 4) {
                error_log("DEBUG getLastTirages: après last(), count=" . count($result));
            }
            return $result;
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
        // SQLite n'a pas CONCAT, on utilise l'opérateur ||
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
        } else {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
        }
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
                    // SQLite n'a pas CONCAT, on utilise l'opérateur ||
                    if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
                    } else {
                        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
                    }
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
                    // SQLite n'a pas CONCAT, on utilise l'opérateur ||
                    if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
                    } else {
                        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
                    }
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
        $where = [];
        $params = [];
        $phrase_parts = [];
        
        // Filtrage par statut de paiement
        $paye_status = $_GET['paye'] ?? 'non';
        if ($paye_status === 'deja_paye') {
            $where[] = 'paye = "oui"';
            $phrase_parts[] = __('admin_tirage.status_paid');
        } elseif ($paye_status === 'tous') {
            // Tous les tirages
            $phrase_parts[] = __('admin_tirage.status_all');
        } else {
            // Par défaut ou explicitement "non"
            $where[] = 'paye = "non"';
            $phrase_parts[] = __('admin_tirage.status_unpaid');
        }
        
        // Recherche par contact (Fuzzy simple avec LIKE pour l'instant)
        if (!empty($_GET['search'])) {
            $search = $_GET['search'];
            $where[] = 'contact LIKE ?';
            $params[] = '%' . $search . '%';
            $phrase_parts[] = __('admin_tirage.search_prefix') . '"' . htmlspecialchars($search) . '"';
        }
        
        $sql_where = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        
        // Ordre
        if (isset($_GET['order'])) {
            $sql_order = ' ORDER BY prix * 1 DESC, date DESC';
            $phrase_parts[] = __('admin_tirage.sorted_by_price');
        } else {
            $sql_order = ' ORDER BY date DESC, id DESC';
            $phrase_parts[] = __('admin_tirage.most_recent');
        }
        
        $sql = $sql_where . $sql_order;
        $phrase = __('admin_tirage.display_prefix') . implode(', ', $phrase_parts);
        
        return array('sql' => $sql, 'params' => $params, 'phrase' => $phrase);
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
            
            $tirages = $this->getLastTirages($machine, $sqlData['sql'], $current_page, 20, $sqlData['params']);
            
            // Debug: log avant regroupement
            if ($machine == 'comcolor' && $current_page == 4) {
                error_log("DEBUG getAllTirageData: machine=$machine, page=$current_page, count(tirages)=" . count($tirages));
                if (isset($tirages['pagination'])) {
                    error_log("DEBUG getAllTirageData: pagination=" . json_encode($tirages['pagination']));
                }
            }
            
            // Regrouper les tirages par tirage_global_id
            $data['last'][$machine] = $this->groupTiragesByGlobalId($tirages);
            
            // Debug: log après regroupement
            if ($machine == 'comcolor' && $current_page == 4) {
                $grouped_count = isset($data['last'][$machine]['pagination']) ? count($data['last'][$machine]) - 1 : count($data['last'][$machine]);
                error_log("DEBUG getAllTirageData: après regroupement, count(grouped)=" . $grouped_count);
            }
            $data['prix_du'][$machine] = $this->getPrixEnAttente($machine);
        }
        
        return $data;
    }
    
    /**
     * Regrouper les tirages par tirage_global_id
     */
    private function groupTiragesByGlobalId($tirages) {
        // Debug: log l'entrée de la fonction
        error_log(date('Y-m-d H:i:s') . ' - DEBUG groupTiragesByGlobalId: ENTRY, count(tirages)=' . count($tirages) . "\n");
        
        // Extraire la pagination si elle existe
        $pagination = null;
        if (isset($tirages['pagination'])) {
            $pagination = $tirages['pagination'];
            error_log(date('Y-m-d H:i:s') . ' - DEBUG: pagination found: ' . json_encode($pagination) . "\n");
            unset($tirages['pagination']);
        } else {
            error_log(date('Y-m-d H:i:s') . ' - DEBUG: no pagination in tirages' . "\n");
        }
        
        // Réindexer le tableau pour s'assurer que les indices sont numériques et séquentiels
        $tirages = array_values($tirages);
        error_log(date('Y-m-d H:i:s') . ' - DEBUG: after array_values, count(tirages)=' . count($tirages) . "\n");
        
        $grouped = array();
        $groups = array();
        
        // Grouper les tirages par tirage_global_id
        foreach ($tirages as $tirage) {
            $global_id = isset($tirage['tirage_global_id']) && !empty($tirage['tirage_global_id']) 
                ? $tirage['tirage_global_id'] 
                : 'single_' . $tirage['id']; // Tirages sans groupe
            
            if (!isset($groups[$global_id])) {
                $groups[$global_id] = array(
                    'tirage_global_id' => $global_id,
                    'tirages' => array(),
                    'prix_total' => 0,
                    'count' => 0,
                    'all_paid' => true
                );
            }
            
            $groups[$global_id]['tirages'][] = $tirage;
            $groups[$global_id]['prix_total'] += floatval($tirage['prix'] ?? 0);
            $groups[$global_id]['count']++;
            
            // Vérifier si le tirage est payé
            $is_paid = isset($tirage['paye']) && ($tirage['paye'] === 'oui' || $tirage['paye'] === 'Oui');
            if (!isset($groups[$global_id]['all_paid'])) {
                $groups[$global_id]['all_paid'] = true;
            }
            if (!$is_paid) {
                $groups[$global_id]['all_paid'] = false;
            }
        }
        
        // Convertir en tableau indexé pour l'affichage et trier par date (plus récent en premier)
        $i = 0;
        foreach ($groups as $group) {
            // Calculer la date max du groupe pour le tri
            $max_date = 0;
            foreach ($group['tirages'] as $tirage) {
                // Utiliser le timestamp si disponible, sinon convertir la date formatée
                if (isset($tirage['date_timestamp'])) {
                    $timestamp = intval($tirage['date_timestamp']);
                } else if (isset($tirage['date'])) {
                    $date_str = $tirage['date'];
                    // Convertir 'd.m.y' en timestamp
                    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $date_str, $matches)) {
                        $day = intval($matches[1]);
                        $month = intval($matches[2]);
                        $year = 2000 + intval($matches[3]); // Année sur 2 chiffres
                        $timestamp = mktime(0, 0, 0, $month, $day, $year);
                    } else {
                        $timestamp = 0;
                    }
                } else {
                    $timestamp = 0;
                }
                if ($timestamp > $max_date) {
                    $max_date = $timestamp;
                }
            }
            $grouped[$i] = $group;
            $grouped[$i]['_sort_date'] = $max_date; // Stocker la date pour le tri
            $i++;
        }
        
        // Trier les groupes par date décroissante (plus récent en premier)
        usort($grouped, function($a, $b) {
            $date_a = isset($a['_sort_date']) ? $a['_sort_date'] : 0;
            $date_b = isset($b['_sort_date']) ? $b['_sort_date'] : 0;
            return $date_b - $date_a; // Décroissant
        });
        
        // Retirer le champ temporaire de tri et réindexer
        $grouped = array_values($grouped);
        foreach ($grouped as &$group) {
            unset($group['_sort_date']);
        }
        unset($group);
        
        // Calculer le nombre total de groupes (après regroupement)
        // IMPORTANT: Utiliser le nombre réel après regroupement, pas celui de la BDD
        $total_groups = count($grouped);
        
        // Extraire per_page de la pagination si disponible, sinon utiliser 20
        $per_page = isset($pagination['per_page']) ? $pagination['per_page'] : 20;
        
        // Extraire la page demandée depuis la pagination
        $requested_page = isset($pagination['current_page']) ? intval($pagination['current_page']) : 1;
        
        // Calculer le nombre total de pages basé sur le nombre réel de groupes
        $total_pages = $total_groups > 0 ? ceil($total_groups / $per_page) : 0;
        
        // S'assurer que la page demandée est valide
        if ($requested_page < 1) {
            $current_page = 1;
        } else if ($requested_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages; // Aller à la dernière page valide
        } else {
            $current_page = $requested_page;
        }
        
        // Calculer l'offset
        $offset = ($current_page - 1) * $per_page;
        
        // Debug: log pour comprendre le problème
        $pagination_total = isset($pagination['total_entries']) ? $pagination['total_entries'] : 'N/A';
        $debug_msg = "DEBUG groupTiragesByGlobalId: total_groups=$total_groups (real), pagination_total_entries=$pagination_total (from BDD), requested_page=$requested_page, current_page=$current_page, per_page=$per_page, offset=$offset, total_pages=$total_pages\n";
        error_log(date('Y-m-d H:i:s') . ' - ' . $debug_msg);
        
        // Si pas de groupes, retourner un tableau vide
        if ($total_groups == 0) {
            $grouped_limited = array();
            if ($pagination !== null) {
                $pagination['total_entries'] = 0;
                $pagination['total_pages'] = 0;
                $pagination['current_page'] = 1;
                $grouped_limited['pagination'] = $pagination;
            }
            return $grouped_limited;
        }
        
        // Limiter les groupes selon la pagination
        // S'assurer que $grouped est bien un tableau indexé numériquement avant array_slice
        $grouped = array_values($grouped);
        
        // Debug: vérifier la structure avant array_slice
        $debug_msg = "DEBUG before array_slice: grouped count=" . count($grouped) . ", offset=$offset, per_page=$per_page, current_page=$current_page\n";
        error_log(date('Y-m-d H:i:s') . ' - ' . $debug_msg);
        
        // Limiter les groupes selon la pagination
        $grouped_limited = array_slice($grouped, $offset, $per_page);
        
        // Debug: vérifier le résultat
        $debug_msg = "DEBUG after array_slice: grouped_limited count=" . count($grouped_limited) . " for page $current_page\n";
        error_log(date('Y-m-d H:i:s') . ' - ' . $debug_msg);
        
        // Réindexer pour s'assurer que les indices commencent à 0
        $grouped_limited = array_values($grouped_limited);
        
        // Mettre à jour la pagination avec le nombre réel de groupes
        // IMPORTANT: Utiliser le nombre réel de groupes après regroupement, pas celui de la BDD
        if ($pagination !== null) {
            $pagination['total_entries'] = $total_groups; // Nombre réel après regroupement
            $pagination['total_pages'] = $total_pages; // Nombre réel de pages
            $pagination['current_page'] = $current_page; // Page ajustée si nécessaire
            $grouped_limited['pagination'] = $pagination;
        }
        
        // Debug final
        error_log(date('Y-m-d H:i:s') . " - DEBUG FINAL: returning " . count($grouped_limited) . " groups for page $current_page\n");
        
        return $grouped_limited;
    }
}
?>
