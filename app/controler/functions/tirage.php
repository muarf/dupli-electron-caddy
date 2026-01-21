<?php
/**
 * Fonctions de gestion des tirages pour l'application Duplicator
 * 
 * Ce fichier contient toutes les fonctions liées à la gestion des tirages,
 * des photocopies et des duplicopieurs.
 */

/**
 * Insérer un tirage photocopieur
 */
function insert_photocop($type, $marque, $contact, $nb_f, $rv, $prix, $paye, $cb, $mot, $date, $db = null, $tirage_global_id = null, $session_id = null)
{
    // CORRECTION DEADLOCK : Utiliser la connexion passée en paramètre si disponible (pour les transactions)
    if ($db === null) {
        $db = pdo_connect();
    }

    // Vérifier si la colonne tirage_global_id existe
    $hasTirageGlobalId = false;
    try {
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query_check = $db->prepare("PRAGMA table_info(photocop)");
            $query_check->execute();
            $columns = $query_check->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === 'tirage_global_id') {
                    $hasTirageGlobalId = true;
                    break;
                }
            }
        } else {
            // MySQL - vérifier si la colonne existe
            $query_check = $db->prepare("SHOW COLUMNS FROM `photocop` LIKE 'tirage_global_id'");
            $query_check->execute();
            $hasTirageGlobalId = $query_check->rowCount() > 0;
        }
    } catch (Exception $e) {
        // Si erreur, on continue sans tirage_global_id
        error_log("Erreur vérification colonne tirage_global_id: " . $e->getMessage());
    }

    if ($hasTirageGlobalId) {
        $query = $db->prepare('INSERT into photocop (type, marque, contact, nb_f, rv, prix, paye, cb, mot, date, tirage_global_id, session_id) VALUES (:type,:marque,:contact,:nb_f,:rv,:prix,:paye,:cb,:mot,:date,:tirage_global_id,:session_id)');
    } else {
        $query = $db->prepare('INSERT into photocop (type, marque, contact, nb_f, rv, prix, paye, cb, mot, date, session_id) VALUES (:type,:marque,:contact,:nb_f,:rv,:prix,:paye,:cb,:mot,:date,:session_id)');
    }

    $query->bindParam(':type', $type);
    $query->bindParam(':marque', $marque);
    $query->bindParam(':contact', $contact);
    $query->bindParam(':nb_f', $nb_f);
    $query->bindParam(':rv', $rv);
    $query->bindParam(':prix', $prix);
    $query->bindParam(':paye', $paye);
    $query->bindParam(':cb', $cb);
    $query->bindParam(':mot', $mot);
    $query->bindParam(':date', $date);

    if ($hasTirageGlobalId) {
        $query->bindParam(':tirage_global_id', $tirage_global_id);
    }
    $query->bindParam(':session_id', $session_id);

    if (!$query->execute()) {
        $errorInfo = $query->errorInfo();
        throw new Exception("Erreur lors de l'insertion photocop : " . $errorInfo[2]);
    }

    return $db->lastInsertId();
}

/**
 * Récupérer les derniers tirages avec pagination
 */
function last($machine, $sql, $page = 1, $per_page = 20)
{
    $con = pdo_connect();
    $db = pdo_connect();

    // Récupérer TOUS les tirages (sans limite) pour pouvoir paginer correctement après regroupement
    // La pagination se fera après regroupement dans TirageManager
    $offset = 0;
    // Pas de limite - récupérer tous les tirages
    // La pagination par groupes se fera après regroupement

    // Vérifier si c'est un duplicopieur (nom complet comme "Ricoh dx4545" ou juste "riso_double")
    // SQLite n'a pas CONCAT, on utilise l'opérateur ||
    if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
        $query_check = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
    } else {
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query_check = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
        } else {
            $query_check = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
        }
    }
    $query_check->execute([$machine, $machine, $machine]);
    $is_duplicopieur = $query_check->fetchColumn() > 0;

    if ($is_duplicopieur) {
        // C'est un duplicopieur, utiliser la table dupli avec filtre par duplicopieur_id
        // SQLite n'a pas CONCAT, on utilise l'opérateur ||
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
        } else {
            if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
            } else {
                $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR (marque = ? AND modele = ?))');
            }
        }
        $query_dup->execute([$machine, $machine, $machine]);
        $duplicopieur_id = $query_dup->fetchColumn();

        if ($duplicopieur_id) {
            if (strpos($sql, 'WHERE') !== false) {
                $sql_modified = str_replace('WHERE', 'AND', $sql);
                $query = $db->prepare('SELECT * FROM dupli WHERE duplicopieur_id = ? ' . $sql_modified);
            } else {
                $query = $db->prepare('SELECT * FROM dupli WHERE duplicopieur_id = ? ' . $sql);
            }
            $query->execute([$duplicopieur_id]);
        } else {
            // Fallback si pas trouvé
            $query = $db->query('SELECT * FROM dupli ' . $sql);
        }
    } else if ($machine === 'A3' || $machine === 'A4' || $machine === 'dupli') {
        // Pour A3, A4, et dupli (ancien système), utiliser la table dupli sans filtre
        $query = $db->query('SELECT * FROM dupli ' . $sql);
    } else {
        // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
        if (strpos($sql, 'WHERE') !== false) {
            $sql_modified = str_replace('WHERE', 'AND', $sql);
            $query = $db->prepare('SELECT * FROM photocop WHERE marque = ? ' . $sql_modified);
        } else {
            $query = $db->prepare('SELECT * FROM photocop WHERE marque = ? ' . $sql);
        }
        $query->execute(array($machine));
    }

    $i = 0;
    $last = array(); // Initialiser le tableau
    while ($result = $query->fetch(PDO::FETCH_OBJ)) {
        $last[$i]['date'] = date('d.m.y', $result->date);
        $last[$i]['date_timestamp'] = intval($result->date); // Conserver le timestamp pour le tri
        $last[$i]['contact'] = $result->contact;

        // Debug: log pour comprendre combien de tirages sont récupérés
        if ($i == 0 || $i == 999 || $i == 1999) {
            error_log("DEBUG last($machine): tirage $i - date=" . $last[$i]['date'] . ", id=" . $result->id);
        }

        $last[$i]['prix'] = round(floatval($result->prix ?? 0), 2);
        $last[$i]['id'] = $result->id;
        $last[$i]['mot'] = $result->mot;
        // Ajouter tirage_global_id si disponible
        $last[$i]['tirage_global_id'] = isset($result->tirage_global_id) ? $result->tirage_global_id : null;
        // Ajouter le statut de paiement
        $last[$i]['paye'] = isset($result->paye) ? $result->paye : 'non';
        $i++;
    }

    // Debug: log le nombre total de tirages récupérés
    $debug_msg = "DEBUG last($machine): total tirages récupérés=" . count($last) . "\n";
    if ($machine == 'comcolor') {
        file_put_contents('/tmp/pagination_debug.log', date('Y-m-d H:i:s') . ' - ' . $debug_msg, FILE_APPEND);
    }

    // Compter le nombre total de groupes (multi-tirages) pour la pagination
    // On compte les tirage_global_id distincts + les tirages sans tirage_global_id
    if ($machine !== 'A3' && $machine !== 'A4' && $machine !== 'dupli') {
        // Pour les photocopieurs
        if (strpos($sql, 'WHERE') !== false) {
            $sql_modified = str_replace('WHERE', 'AND', $sql);
        } else {
            $sql_modified = $sql;
        }

        // SQLite compatible
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            // Compter les groupes distincts : tirage_global_id distincts + tirages sans tirage_global_id
            $count_query = $db->prepare('SELECT COUNT(DISTINCT CASE WHEN tirage_global_id IS NOT NULL AND tirage_global_id != "" THEN tirage_global_id ELSE "single_" || id END) as total FROM photocop WHERE marque = ? ' . $sql_modified);
        } else {
            $count_query = $db->prepare('SELECT COUNT(DISTINCT CASE WHEN tirage_global_id IS NOT NULL AND tirage_global_id != "" THEN tirage_global_id ELSE CONCAT("single_", id) END) as total FROM photocop WHERE marque = ? ' . $sql_modified);
        }
        $count_query->execute(array($machine));
    } else {
        // Pour les duplicopieurs - vérifier si la colonne tirage_global_id existe
        try {
            // SQLite compatible
            if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                $count_query = $db->query('SELECT COUNT(DISTINCT CASE WHEN tirage_global_id IS NOT NULL AND tirage_global_id != "" THEN tirage_global_id ELSE "single_" || id END) as total FROM dupli ' . $sql);
            } else {
                $count_query = $db->query('SELECT COUNT(DISTINCT CASE WHEN tirage_global_id IS NOT NULL AND tirage_global_id != "" THEN tirage_global_id ELSE CONCAT("single_", id) END) as total FROM dupli ' . $sql);
            }
        } catch (Exception $e) {
            // Si la colonne n'existe pas, compter normalement
            $count_query = $db->query('SELECT COUNT(*) as total FROM dupli ' . $sql);
        }
    }
    $count_result = $count_query->fetch(PDO::FETCH_OBJ);
    $total_entries = $count_result->total;
    $total_pages = ceil($total_entries / $per_page);

    $last['pagination'] = array(
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_entries' => $total_entries,
        'per_page' => $per_page
    );

    return $last;
}

/**
 * Récupérer un tirage par ID et machine
 */
function get_tirage($id, $machine)
{
    $db = pdo_connect();
    $id = ceil(floatval($id));

    // Debug: logger les paramètres
    error_log("DEBUG get_tirage: id=$id, machine='$machine'");

    // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
    if ($machine == "A3" || $machine == "A4" || $machine == "dupli") {
        // Pour les duplicopieurs, vérifier que c'est une machine valide
        $machines = array("A3", "A4", "dupli");
        in_array($machine, $machines) or die('donttrytohackme');

        if ($machine == "dupli") {
            // Pour 'dupli', utiliser la table 'dupli'
            $query = $db->query('SELECT * FROM dupli WHERE id = ' . $id . ' ');
        } else {
            // Pour A3/A4, utiliser les tables minuscules
            $table_name = strtolower($machine);
            $query = $db->query('SELECT * FROM ' . $table_name . ' WHERE id = ' . $id . ' ');
        }
    } else {
        // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
        // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
        } else {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
        }
        $query->execute([$machine, $machine, $machine]);
        $is_duplicopieur = $query->fetchColumn() > 0;

        error_log("DEBUG get_tirage: is_duplicopieur=" . ($is_duplicopieur ? 'true' : 'false'));

        if ($is_duplicopieur) {
            // C'est un duplicopieur, chercher dans la table dupli avec le nom_machine
            $query = $db->prepare('SELECT * FROM dupli WHERE id = ? AND nom_machine = ?');
            $query->execute(array($id, $machine));
            error_log("DEBUG get_tirage: Requête duplicopieur exécutée pour id=$id, nom_machine='$machine'");
        } else {
            // Pour les photocopieurs, vérifier que c'est une marque valide
            $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
            $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
            in_array($machine, $valid_marques) or die('donttrytohackme');
            $query = $db->prepare('SELECT * FROM photocop WHERE id = ? AND marque = ?');
            $query->execute(array($id, $machine));
            error_log("DEBUG get_tirage: Requête photocopieur exécutée pour id=$id, marque='$machine'");
        }
    }

    $res = $query->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG get_tirage: Résultat=" . ($res ? 'trouvé' : 'non trouvé'));

    if ($res === false) {
        return false;
    }
    $res['machine'] = $machine;
    return $res;
}

/**
 * Marquer un tirage comme payé
 */
function marquer_comme_paye($id, $machine)
{
    $db = pdo_connect();
    try {
        // Vérifier si c'est une photocopieuse (nom de machine) ou une table de duplicopieur
        if (in_array(strtolower($machine), array('dupli', 'a4'))) {
            // Pour les duplicopieurs A3 et A4
            $table_name = strtolower($machine);
            $query = "UPDATE $table_name SET paye='oui' WHERE id=:id";
        } else {
            // Pour les photocopieurs, utiliser la table 'photocop' et filtrer par marque
            $table_name = 'photocop';
            $query = "UPDATE $table_name SET paye='oui' WHERE id=:id AND marque=:marque";
        }

        $stmt = $db->prepare($query);
        $stmt->bindValue(':id', $id);
        if ($table_name == 'photocop') {
            $stmt->bindValue(':marque', $machine);
        }
        $stmt->execute();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

/**
 * Mettre à jour un tirage
 */
function update_tirage($id, $form, $machine)
{
    $db = pdo_connect();
    $old = get_tirage($id, $machine);

    $old['save'] = "";
    $update = array_diff_assoc($form, $old);

    if (!empty($update)) {
        $id = ceil(floatval($id));

        // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
        if ($machine == "A3" || $machine == "A4" || $machine == "dupli") {
            // Pour les duplicopieurs, vérifier que c'est une machine valide
            $machines = array("A3", "A4", "dupli");
            in_array($machine, $machines) or die('donttrytohackme');

            if ($machine == "dupli") {
                $table_name = "dupli";
            } else {
                $table_name = strtolower($machine);
            }

            $sql = 'UPDATE ' . $table_name . ' SET';
            foreach ($update as $key => $column) {
                if ($key != 'save' && $key != 'nb_f') {
                    $sql = $sql . ' ' . $key . ' = :' . $key . ' , ';
                }
            }
            $sql = substr($sql, 0, -2) . ' WHERE id = ' . $id;

            $query = $db->prepare($sql);
            foreach ($update as $key => $column) {
                if ($key != 'save' && $key != 'nb_f') {
                    $query->bindValue(':' . $key, $column);
                }
            }
            $query->execute() or die(print_r($query->errorInfo()));

        } else {
            // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
            // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
            if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
                $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
            } else {
                $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
            }
            $query->execute([$machine, $machine, $machine]);
            $is_duplicopieur = $query->fetchColumn() > 0;

            if ($is_duplicopieur) {
                // C'est un duplicopieur, utiliser la table dupli avec le nom_machine
                $sql = 'UPDATE dupli SET';
                foreach ($update as $key => $column) {
                    if ($key != 'save' && $key != 'nb_f') {
                        $sql = $sql . ' ' . $key . ' = :' . $key . ' , ';
                    }
                }
                $sql = substr($sql, 0, -2) . ' WHERE id = ' . $id . ' AND nom_machine = "' . $machine . '"';

                $query = $db->prepare($sql);
                foreach ($update as $key => $column) {
                    if ($key != 'save' && $key != 'nb_f') {
                        $query->bindValue(':' . $key, $column);
                    }
                }
                $query->execute();
                if ($query->errorCode() != '00000') {
                    throw new Exception("Erreur SQL duplicopieur: " . implode(', ', $query->errorInfo()));
                }

            } else {
                // Pour les photocopieurs, vérifier que c'est une marque valide
                $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
                $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
                in_array($machine, $valid_marques) or die('donttrytohackme');

                // Récupérer les colonnes existantes de la table photocop (SQLite compatible)
                $query = $db->query('PRAGMA table_info(photocop)');
                $columns_info = $query->fetchAll(PDO::FETCH_ASSOC);
                $columns = array_column($columns_info, 'name');

                // Filtrer les données pour ne garder que les colonnes existantes
                $filtered_update = array();
                foreach ($update as $key => $column) {
                    if ($key != 'save' && $key != 'nb_f' && in_array($key, $columns)) {
                        $filtered_update[$key] = $column;
                    }
                }

                if (!empty($filtered_update)) {
                    $sql = 'UPDATE photocop SET';
                    foreach ($filtered_update as $key => $column) {
                        $sql = $sql . ' ' . $key . ' = :' . $key . ' , ';
                    }
                    $sql = substr($sql, 0, -2) . ' WHERE id = ' . $id . ' AND marque = :marque';

                    $query = $db->prepare($sql);
                    foreach ($filtered_update as $key => $column) {
                        $query->bindValue(':' . $key, $column);
                    }
                    $query->bindValue(':marque', $machine);
                    $query->execute() or die(print_r($query->errorInfo()));
                }
            }
        }
    }
}

/**
 * Supprimer un tirage
 */
function del_tirage($id, $machine)
{
    $db = pdo_connect();
    $id = ceil(floatval($id));

    // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
    if ($machine == "A3" || $machine == "A4" || $machine == "dupli") {
        // Pour les duplicopieurs, vérifier que c'est une machine valide
        $machines = array("A3", "A4", "dupli");
        in_array($machine, $machines) or die('donttrytohackme');

        if ($machine == "dupli") {
            $table_name = "dupli";
        } else {
            $table_name = strtolower($machine);
        }
        $db->query('DELETE from ' . $table_name . ' WHERE id= ' . $id . '');
    } else {
        // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
        // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
        } else {
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (marque || " " || modele = ? OR marque = ? OR modele = ?)');
        }
        $query->execute([$machine, $machine, $machine]);
        $is_duplicopieur = $query->fetchColumn() > 0;

        if ($is_duplicopieur) {
            // C'est un duplicopieur, supprimer dans la table dupli avec le nom_machine
            $db->query('DELETE from dupli WHERE id= ' . $id . ' AND nom_machine = "' . $machine . '"');
        } else {
            // Pour les photocopieurs, vérifier que c'est une marque valide
            $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
            $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
            in_array($machine, $valid_marques) or die('donttrytohackme');
            $db->query('DELETE from photocop WHERE id= ' . $id . ' AND marque = "' . $machine . '"');
        }
    }
}
?>