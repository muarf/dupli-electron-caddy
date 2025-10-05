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
function insert_photocop($type, $marque, $contact, $nb_f, $rv, $prix, $paye, $cb, $mot, $date, $db = null)
{
    // CORRECTION DEADLOCK : Utiliser la connexion passée en paramètre si disponible (pour les transactions)
    if ($db === null) {
        $con   = pdo_connect();
        $db    = pdo_connect();
    }
    
    $query = $db->prepare('INSERT into photocop (type, marque, contact, nb_f, rv, prix, paye, cb, mot, date) VALUES (:type,:marque,:contact,:nb_f,:rv,:prix,:paye,:cb,:mot,:date)');
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
    $query->execute() or die('<div class="alert alert-danger"> <strong>Danger!</strong> Une erreur s\'est produite.<a href="javascript:" onclick="history.go(-1); return false"></div>');
}

/**
 * Récupérer les derniers tirages avec pagination
 */
function last($machine, $sql, $page = 1, $per_page = 20)
{
    $con = pdo_connect();
    $db = pdo_connect();
    
    // Calculer l'offset pour la pagination
    $offset = ($page - 1) * $per_page;
    
    // Vérifier si c'est un duplicopieur (nom complet comme "Ricoh dx4545" ou juste "riso_double")
    $query_check = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
    $query_check->execute([$machine, $machine, $machine]);
    $is_duplicopieur = $query_check->fetchColumn() > 0;
    
    if ($is_duplicopieur) {
        // C'est un duplicopieur, utiliser la table dupli avec filtre par duplicopieur_id
        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
        $query_dup->execute([$machine, $machine, $machine]);
        $duplicopieur_id = $query_dup->fetchColumn();
        
        if ($duplicopieur_id) {
            if (strpos($sql, 'WHERE') !== false) {
                $sql_modified = str_replace('WHERE', 'AND', $sql);
                $query = $db->prepare('SELECT * FROM dupli WHERE duplicopieur_id = ? ' . $sql_modified . ' LIMIT ' . $per_page . ' OFFSET ' . $offset);
            } else {
                $query = $db->prepare('SELECT * FROM dupli WHERE duplicopieur_id = ? ' . $sql . ' LIMIT ' . $per_page . ' OFFSET ' . $offset);
            }
            $query->execute([$duplicopieur_id]);
        } else {
            // Fallback si pas trouvé
            $query = $db->query('SELECT * FROM dupli '.$sql.' LIMIT ' . $per_page . ' OFFSET ' . $offset);
        }
    } else if ($machine === 'A3' || $machine === 'A4' || $machine === 'dupli') {
        // Pour A3, A4, et dupli (ancien système), utiliser la table dupli sans filtre
        $query = $db->query('SELECT * FROM dupli '.$sql.' LIMIT ' . $per_page . ' OFFSET ' . $offset);
    } else {
        // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
        if (strpos($sql, 'WHERE') !== false) {
            $sql_modified = str_replace('WHERE', 'AND', $sql);
            $query = $db->prepare('SELECT * FROM photocop WHERE marque = ? ' . $sql_modified . ' LIMIT ' . $per_page . ' OFFSET ' . $offset);
        } else {
            $query = $db->prepare('SELECT * FROM photocop WHERE marque = ? ' . $sql . ' LIMIT ' . $per_page . ' OFFSET ' . $offset);
        }
        $query->execute(array($machine));
    }
    
    $i = 0;
    $last = array(); // Initialiser le tableau
    while($result = $query->fetch(PDO::FETCH_OBJ))
    {
      $last[$i]['date'] = date('d.m.y',$result->date);
      $last[$i]['contact'] = $result->contact ;
      
      $last[$i]['prix'] = round(floatval($result->prix ?? 0), 2);
      $last[$i]['id'] = $result->id;
      $last[$i]['mot'] = $result->mot;
      $i++;
    }
    
    // Compter le nombre total d'entrées pour la pagination
    if ($machine !== 'A3' && $machine !== 'A4' && $machine !== 'dupli') {
        if (strpos($sql, 'WHERE') !== false) {
            $sql_modified = str_replace('WHERE', 'AND', $sql);
            $count_query = $db->prepare('SELECT COUNT(*) as total FROM photocop WHERE marque = ? ' . $sql_modified);
        } else {
            $count_query = $db->prepare('SELECT COUNT(*) as total FROM photocop WHERE marque = ? ' . $sql);
        }
        $count_query->execute(array($machine));
    } else {
        $count_query = $db->query('SELECT COUNT(*) as total FROM dupli '.$sql);
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
    
    return $last ;
}

/**
 * Récupérer un tirage par ID et machine
 */
function get_tirage($id,$machine)
{
    $db = pdo_connect();
    $id = ceil(floatval($id));
    
    // Debug: logger les paramètres
    error_log("DEBUG get_tirage: id=$id, machine='$machine'");
    
    // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
    if($machine == "A3" || $machine == "A4" || $machine == "dupli") {
        // Pour les duplicopieurs, vérifier que c'est une machine valide
        $machines = array("A3","A4","dupli");
        in_array($machine,$machines) or die('donttrytohackme');
        
        if($machine == "dupli") {
            // Pour 'dupli', utiliser la table 'dupli'
            $query = $db->query('SELECT * FROM dupli WHERE id = '.$id.' ');
        } else {
            // Pour A3/A4, utiliser les tables minuscules
            $table_name = strtolower($machine);
            $query = $db->query('SELECT * FROM '.$table_name.' WHERE id = '.$id.' ');
        }
    } else {
        // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
        // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
        $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR marque = ? OR modele = ?)');
        $query->execute([$machine, $machine, $machine]);
        $is_duplicopieur = $query->fetchColumn() > 0;
        
        error_log("DEBUG get_tirage: is_duplicopieur=" . ($is_duplicopieur ? 'true' : 'false'));
        
        if($is_duplicopieur) {
            // C'est un duplicopieur, chercher dans la table dupli avec le nom_machine
            $query = $db->prepare('SELECT * FROM dupli WHERE id = ? AND nom_machine = ?');
            $query->execute(array($id, $machine));
            error_log("DEBUG get_tirage: Requête duplicopieur exécutée pour id=$id, nom_machine='$machine'");
        } else {
            // Pour les photocopieurs, vérifier que c'est une marque valide
            $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
            $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
            in_array($machine,$valid_marques) or die('donttrytohackme');
            $query = $db->prepare('SELECT * FROM photocop WHERE id = ? AND marque = ?');
            $query->execute(array($id, $machine));
            error_log("DEBUG get_tirage: Requête photocopieur exécutée pour id=$id, marque='$machine'");
        }
    }
    
    $res = $query->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG get_tirage: Résultat=" . ($res ? 'trouvé' : 'non trouvé'));
    
    if($res === false) {
        return false;
    }
    $res['machine'] = $machine;
    return $res;
}

/**
 * Marquer un tirage comme payé
 */
function marquer_comme_paye($id, $machine) {
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
function update_tirage($id,$form,$machine){
    $db = pdo_connect();
    $old = get_tirage($id,$machine);
    
    $old['save']= "";
    $update = array_diff_assoc($form, $old);
   
    if(!empty($update)){
         $id = ceil(floatval($id));
         
         // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
         if($machine == "A3" || $machine == "A4" || $machine == "dupli") {
             // Pour les duplicopieurs, vérifier que c'est une machine valide
             $machines = array("A3","A4","dupli");
             in_array($machine,$machines) or die('donttrytohackme');
             
             if($machine == "dupli") {
                 $table_name = "dupli";
             } else {
                 $table_name = strtolower($machine);
             }
             
             $sql = 'UPDATE '.$table_name.' SET' ;
             foreach ($update as $key => $column) {
                 if($key != 'save' && $key != 'nb_f'){
                      $sql = $sql.' '.$key.' = :'.$key.' , ';
                 }
             }
             $sql = substr($sql, 0, -2).' WHERE id = '.$id;
             
             $query = $db->prepare($sql);
             foreach ($update as $key => $column) {
                 if($key != 'save' && $key != 'nb_f'){
                      $query->bindValue(':'.$key, $column);
                 }
             }
             $query->execute() or die(print_r($query->errorInfo()));
             
         } else {
            // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
            // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR marque = ? OR modele = ?)');
            $query->execute([$machine, $machine, $machine]);
            $is_duplicopieur = $query->fetchColumn() > 0;
             
             if($is_duplicopieur) {
                 // C'est un duplicopieur, utiliser la table dupli avec le nom_machine
                     $sql = 'UPDATE dupli SET' ;
                     foreach ($update as $key => $column) {
                         if($key != 'save' && $key != 'nb_f'){
                              $sql = $sql.' '.$key.' = :'.$key.' , ';
                         }
                     }
                 $sql = substr($sql, 0, -2).' WHERE id = '.$id.' AND nom_machine = "'.$machine.'"';
                 
                 $query = $db->prepare($sql);
                 foreach ($update as $key => $column) {
                     if($key != 'save' && $key != 'nb_f'){
                          $query->bindValue(':'.$key, $column);
                     }
                 }
                 $query->execute() or die(print_r($query->errorInfo()));
                 
            } else {
                // Pour les photocopieurs, vérifier que c'est une marque valide
                $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
                $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
                in_array($machine,$valid_marques) or die('donttrytohackme');
                
                // Récupérer les colonnes existantes de la table photocop
                $query = $db->query('DESCRIBE photocop');
                $columns = $query->fetchAll(PDO::FETCH_COLUMN);
                
                    // Filtrer les données pour ne garder que les colonnes existantes
                    $filtered_update = array();
                    foreach ($update as $key => $column) {
                        if($key != 'save' && $key != 'nb_f' && in_array($key, $columns)){
                            $filtered_update[$key] = $column;
                        }
                    }
                
                if(!empty($filtered_update)){
                    $sql = 'UPDATE photocop SET' ;
                    foreach ($filtered_update as $key => $column) {
                        $sql = $sql.' '.$key.' = :'.$key.' , ';
                    }
                    $sql = substr($sql, 0, -2).' WHERE id = '.$id.' AND marque = :marque';
                    
                    $query = $db->prepare($sql);
                    foreach ($filtered_update as $key => $column) {
                        $query->bindValue(':'.$key, $column);
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
function del_tirage($id,$machine){
    $db = pdo_connect();
    $id = ceil(floatval($id));
    
    // Vérifier si c'est une machine valide (A3, A4, dupli) ou une marque de photocopieuse
    if($machine == "A3" || $machine == "A4" || $machine == "dupli") {
        // Pour les duplicopieurs, vérifier que c'est une machine valide
        $machines = array("A3","A4","dupli");
        in_array($machine,$machines) or die('donttrytohackme');
        
        if($machine == "dupli") {
            $table_name = "dupli";
        } else {
            $table_name = strtolower($machine);
        }
        $db->query('DELETE from '.$table_name.' WHERE id= '.$id.'');
    } else {
            // Vérifier si c'est un duplicopieur (nom complet comme "riso rz 370")
            // Gérer le cas où marque = modele (nom complet) et le cas où marque != modele
            $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR marque = ? OR modele = ?)');
            $query->execute([$machine, $machine, $machine]);
            $is_duplicopieur = $query->fetchColumn() > 0;
        
        if($is_duplicopieur) {
            // C'est un duplicopieur, supprimer dans la table dupli avec le nom_machine
            $db->query('DELETE from dupli WHERE id= '.$id.' AND nom_machine = "'.$machine.'"');
        } else {
            // Pour les photocopieurs, vérifier que c'est une marque valide
            $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ""');
            $valid_marques = $query->fetchAll(PDO::FETCH_COLUMN);
            in_array($machine,$valid_marques) or die('donttrytohackme');
            $db->query('DELETE from photocop WHERE id= '.$id.' AND marque = "'.$machine.'"');
        }
    }
}
?>
