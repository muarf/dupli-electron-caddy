<?php
/**
 * Fonctions de gestion des consommables pour l'application Duplicator
 * 
 * Ce fichier contient toutes les fonctions liées à la gestion des consommables,
 * des changements de tambours, masters et encre.
 */

/**
 * Insérer un changement de consommable
 */
function insert_cons($date, $machine, $type, $nb_p, $nb_m)
{
    $con   = pdo_connect();
    $db    = pdo_connect();
    $query = $db->prepare('INSERT into cons VALUES ("",:date,:machine,:type,:nb_p,:nb_m)');
    $query->bindparam(':date', $date);
    $query->bindparam(':machine', $machine);
    $query->bindparam(':nb_m', $nb_m);
    $query->bindparam(':nb_p', $nb_p);
    $query->bindparam(':type', $type);
    $query->execute() or die(print_r($query->errorInfo()));
}

/**
 * Insérer un changement de consommable pour photocopieur
 */
function insert_cons_photocop($nb_total,$couleur)
{
    $db = pdo_connect();

    $date = time();	
    $query = $db->prepare('INSERT into cons (date, machine, type, nb_p, nb_m) VALUES (:date,"photocop",:couleur,:nb_p,"0")');
    $query->bindparam(':date',$date);
    $query->bindparam(':couleur',$couleur);
    $query->bindparam(':nb_p',$nb_total); 
    $query->execute() or die (print_r($query->errorInfo()));
}

/**
 * Insérer un changement de consommable pour photocopieur par nom
 */
function insert_cons_photocop_by_name($nb_total, $couleur, $photocop_name)
{
    $db = pdo_connect();
    
    $date = time();
    $machine_key = strtolower(str_replace(' ', '_', $photocop_name));
    
    $query = $db->prepare('INSERT into cons (date, machine, type, nb_p, nb_m) VALUES (:date,:machine,:couleur,:nb_p,"0")');
    $query->bindparam(':date', $date);
    $query->bindparam(':machine', $machine_key);
    $query->bindparam(':couleur', $couleur);
    $query->bindparam(':nb_p', $nb_total);
    $query->execute() or die (print_r($query->errorInfo()));
}

/**
 * Récupérer les consommables d'un photocopieur par couleur
 */
function get_cons_photocop($couleur)
{
    $con = pdo_connect();
    $db = pdo_connect();
    // CORRECTION : Recherche insensible à la casse
    $query =$db->query('SELECT * FROM cons where LOWER(machine) = "photocop" and type= "'.$couleur.'"');
      $i=0;
      while ($result = $query->fetch(PDO::FETCH_OBJ))
      {
        $res[$i]['date'] = intval($result->date);
        $res[$i]['couleur'] = $result->type;
        $res[$i]['nb_p'] = $result->nb_p;
      
        $i++;
      }
    return $res;
}

/**
 * Récupérer les consommables d'une machine
 */
function get_cons($machine)
{
    $con = pdo_connect();
    $db = pdo_connect();
    $prix = get_price();
    
    // Initialiser $res comme tableau vide
    $res = array();
    
    // Initialiser les variables de compteur
    $i_master = 0;
    $i_encre = 0;
    $ii_master = 0;
    $ii_encre = 0;
    $ii = 0; // Initialiser $ii pour éviter les erreurs

    if($machine != 'photocop')
    {
    	// Pour les duplicopieurs, utiliser 'dupli' au lieu du nom de la machine
    	$table_name = ($machine === 'dx4545' || $machine === 'A3' || $machine === 'dupli') ? 'dupli' : $machine;
    	$nb = get_last_number($table_name);
    }
    // CORRECTION : Cas spécial pour "Duplicopieur" et recherche insensible à la casse
    $machine_for_cons = $machine;
    if ($machine === 'Duplicopieur') {
        $machine_for_cons = 'dupli';
    }
    
    $query =$db->query('SELECT * FROM cons where LOWER(machine) = LOWER("'.$machine_for_cons.'")');
    $i=0;
    while ($result = $query->fetch(PDO::FETCH_OBJ))
    {
      $res[$i]['date'] = intval($result->date);
      $res[$i]['type'] = $result->type;
      $res[$i]['nb_p'] = $result->nb_p;
      $res[$i]['nb_m'] = $result->nb_m;
      $i++;
    }
    $max = count($res) ;
    
    // Si pas de données, retourner un tableau vide avec structure
    if ($max == 0) {
        if ($machine == 'photocop') {
            $res['photocop'] = array();
            $res['photocop']['moyenne_total'] = array('temps' => 0, 'nb_p' => 0);
            $res['photocop']['nb_actuel'] = 0;
            $res['photocop']['nb_debut'] = 0;
            $res['photocop']['temps_depuis'] = 0;
            $res['photocop']['temps_jusqua'] = 0;
            $res['photocop']['prix_calcule'] = 0;
            $res['photocop']['class'] = 'info';
            $res['photocop']['color'] = 'green';
        } else {
            $res['master'] = array();
            $res['encre'] = array();
            $res['master']['moyenne_totale'] = array('temps' => 0, 'nb_m' => 0);
            $res['encre']['moyenne_totale'] = array('temps' => 0, 'nb_p' => 0);
            $res['master']['nb_actuel'] = $nb['master_av'];
            $res['encre']['nb_actuel'] = $nb['passage_av'];
            $res['master']['temps_depuis'] = 0;
            $res['encre']['temps_depuis'] = 0;
            $res['master']['temps_jusqua'] = 0;
            $res['encre']['temps_jusqua'] = 0;
        }
        return $res;
    }
    
    for($i=0; $i < $max  ;$i++)
    {
      if($machine =='photocop')
      {
      	$res['photocop'][$i]['temps'] =  $res[$i]['date'];
          $res['photocop'][$i]['nb_p'] = $res[$i]['nb_p'];
      	if($i > 0 )
        	{
        		$ii = $i -1; 
        		$res['temps_moy'][$i] =  $res[$i]['date'] - $res[$ii]['date'] ; 
        		$res['nb_f'][$i] = $res[$i]['nb_p'] - $res[$ii]['nb_p'];
        		$ii++;	
    		}
      }
      else
      { 
        if($res[$i]['type'] == "master")
        {
          if(!isset($i_master)){ $i_master = 0;}
          $res['master'][$i_master]['temps'] =  $res[$i]['date'];
          $res['master'][$i_master]['nb_m'] = $res[$i]['nb_m'];
          if( $i_master >0 )
          { 
          	$ii_master = $i_master -1; 
          	$res['master']['temps_moy'][$i_master] =  $res['master'][$i_master]['temps'] - $res['master'][$ii_master]['temps']; 
          	$res['master']['nb_m_moy'][$i_master] = $res['master'][$i_master]['nb_m'] - $res['master'][$ii_master]['nb_m'];
          	$ii_master++;
          }
          $i_master++; 

        }
        if($res[$i]['type'] == "encre")
        {
          if(!isset($i_encre)){$i_encre = 0;}
          $res['encre'][$i_encre]['temps'] =  $res[$i]['date'];
          $res['encre'][$i_encre]['nb_p'] = $res[$i]['nb_p'];
          if( $i_encre >0 )
          { 
          	$ii_encre = $i_encre -1;
          	$res['encre']['temps_moy'][$i_encre] =  $res['encre'][$i_encre]['temps']- $res['encre'][$ii_encre]['temps']; 
          	$res['encre']['nb_p_moy'][$i_encre] =  $res['encre'][$i_encre]['nb_p']- $res['encre'][$ii_encre]['nb_p']; 
          	$ii_encre++;
          }
          $i_encre++; 
        }

      }
    }
    
    // Mettre à jour les indices pour pointer vers le dernier enregistrement
    if (isset($i_master) && $i_master > 0) {
        $ii_master = $i_master - 1;
    }
    if (isset($i_encre) && $i_encre > 0) {
        $ii_encre = $i_encre - 1;
    }
    if($machine =='photocop')
    { 
    	// Vérifier si les tableaux existent avant d'utiliser array_sum
    	$res['photocop']['moyenne_total']['temps'] = isset($res['temps_moy']) && is_array($res['temps_moy']) ? array_sum($res['temps_moy'])/count($res['temps_moy']) : 0;
    	$res['photocop']['moyenne_total']['nb_p'] = isset($res['nb_f']) && is_array($res['nb_f']) ? array_sum($res['nb_f'])/count($res['nb_f']) : 0;
    	
    	// Utiliser la dernière date disponible ou 0 si aucune donnée
    	$last_date = ($max > 0) ? $res[$max - 1]['date'] : 0;
    	$query = $db->query('SELECT sum(nb_f) as nbr from photocop WHERE date > '.$last_date.' ');
    	$result = $query->fetch(PDO::FETCH_OBJ);
    	$res['photocop']['nb_actuel'] = $result->nbr ?? 0;
    	$query = $db->query('SELECT sum(nb_f) as nbr from photocop  ');
    	$result = $query->fetch(PDO::FETCH_OBJ);
    	$res['photocop']['nb_debut'] = $result->nbr ?? 0;
       	$res['photocop']['temps_depuis'] = time() - $last_date;
        if($res['photocop']['temps_depuis'] == 0)   { $res['photocop']['temps_depuis'] =1;}
        if($res['photocop']['moyenne_total']['nb_p']== 0)   { $res['photocop']['moyenne_total']['nb_p'] =1;}
       	$res['photocop']['temps_jusqua'] = $res['photocop']['moyenne_total']['temps'] - $res['photocop']['temps_depuis'];
    	// Utiliser des valeurs par défaut si les prix ne sont pas définis
    	$prix_pack = $prix['photocop']['noire']['pack'] ?? 140;
    	$prix_unite = $prix['photocop']['noire']['unite'] ?? 0.005;
    	$res['photocop']['prix_calcule'] = $prix_pack / $res['photocop']['moyenne_total']['nb_p'];
    	if($res['photocop']['temps_jusqua']  < -30){ $res['photocop']['class'] = "danger" ;}
		if(($res['photocop']['temps_jusqua']  < 0) AND ($res['photocop']['temps_jusqua']  > -30)){$res['photocop']['class'] = "warning";}
		if(($res['photocop']['temps_jusqua']  > 0)&&($res['photocop']['temps_jusqua']  < 30)){$res['photocop']['class'] = "info" ;}
		if($res['photocop']['temps_jusqua']  > 30){$res['photocop']['class'] = "success";}
		($res['photocop']['prix_calcule']  > $prix_unite)? $res['photocop']['color'] = "green":$res['photocop']['color'] = "red";
  	  }
    else
    {
      // Vérifier si les tableaux existent avant d'utiliser array_sum
      $res['encre']['moyenne_totale']['temps'] = isset($res['encre']['temps_moy']) && is_array($res['encre']['temps_moy']) ? array_sum($res['encre']['temps_moy'])/count($res['encre']['temps_moy']) : 0;
      $res['master']['moyenne_totale']['temps'] = isset($res['master']['temps_moy']) && is_array($res['master']['temps_moy']) ? array_sum($res['master']['temps_moy'])/count($res['master']['temps_moy']) : 0;
      $res['master']['moyenne_totale']['nb_m'] = isset($res['master']['nb_m_moy']) && is_array($res['master']['nb_m_moy']) ? array_sum($res['master']['nb_m_moy'])/count($res['master']['nb_m_moy']) : 0;
      $res['encre']['moyenne_totale']['nb_p'] = isset($res['encre']['nb_p_moy']) && is_array($res['encre']['nb_p_moy']) ? array_sum($res['encre']['nb_p_moy'])/count($res['encre']['nb_p_moy']) : 0;
      
      // Vérifier si les variables existent avant de les utiliser
      $res['master']['nb_actuel'] = isset($res['master'][$ii_master ?? 0]['nb_m']) ? $nb['master_av'] - $res['master'][$ii_master]['nb_m'] : $nb['master_av'];
      $res['encre']['nb_actuel'] = isset($res['encre'][$ii_encre ?? 0]['nb_p']) ? $nb['passage_av'] - $res['encre'][$ii_encre]['nb_p'] : $nb['passage_av'];
      $res['master']['temps_depuis'] = isset($res['master'][$ii_master ?? 0]['temps']) ? time() - $res['master'][$ii_master]['temps'] : 0;
      $res['encre']['temps_depuis'] = isset($res['encre'][$ii_encre ?? 0]['temps']) ? time() - $res['encre'][$ii_encre]['temps'] : 0;
      $res['encre']['temps_jusqua'] = $res['encre']['moyenne_totale']['temps'] - $res['encre']['temps_depuis'];
      $res['master']['temps_jusqua'] = $res['master']['moyenne_totale']['temps'] - $res['master']['temps_depuis'];
      
      // CORRECTION : Chercher l'ID réel du duplicopieur dans la base de données au lieu d'utiliser dupli_1 en dur
      $machine_key = '';
      // Pour les anciennes machines, utiliser dupli_1 comme fallback
      $machine_lower = strtolower($machine);
      if ($machine_lower === 'dx4545' || $machine_lower === 'a3' || $machine_lower === 'dupli' || $machine_lower === 'duplicopieur') {
          $machine_key = 'dupli_1';
      } else {
          // Pour les nouvelles machines, chercher l'ID réel dans la table duplicopieurs
          // SQLite utilise || pour la concaténation, pas CONCAT
          if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
              $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque = ? OR (marque || " " || modele) = ? OR LOWER(marque) = ?) AND actif = 1 LIMIT 1');
          } else {
              $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque = ? OR CONCAT(marque, " ", modele) = ? OR LOWER(marque) = ?) AND actif = 1 LIMIT 1');
          }
          $query_dup->execute([$machine, $machine, strtolower($machine)]);
          $dup_result = $query_dup->fetch(PDO::FETCH_ASSOC);
          if ($dup_result) {
              $machine_key = 'dupli_' . $dup_result['id'];
          } else {
              // Fallback : utiliser le nom de la machine en uppercase
              $machine_key = strtoupper($machine);
          }
      }
      
      $res['master']['prix_calcule'] = ($res['master']['moyenne_totale']['nb_m'] > 0) ? ($prix[$machine_key]['master']['pack'] ?? 0) / $res['master']['moyenne_totale']['nb_m'] : 0;
      $res['encre']['prix_calcule'] = ($res['encre']['moyenne_totale']['nb_p'] > 0) ? ($prix[$machine_key]['encre']['pack'] ?? 0) / $res['encre']['moyenne_totale']['nb_p'] : 0;
      
      ($res['encre']['prix_calcule']< ($prix[$machine_key]['encre']['unite'] ?? 0)) ? $res['encre']['color'] = "green": $res['encre']['color'] = "red";
      ($res['master']['prix_calcule']< ($prix[$machine_key]['master']['unite'] ?? 0)) ? $res['master']['color'] = "green": $res['master']['color'] = "red";
      
      if(($res['encre']['temps_jusqua']/86400) < -30){ $res['encre']['class'] = "danger" ;}
		if((($res['encre']['temps_jusqua']/86400) < 0) AND ($res['encre']['temps_jusqua'] > -30)){ $res['encre']['class'] = "alert";}
		if((($res['encre']['temps_jusqua']/86400) > 0)&&($res['encre']['temps_jusqua'] < 30)){ $res['encre']['class'] = "info" ;}
		if(($res['encre']['temps_jusqua']/86400) > 30){ $res['encre']['class'] = "success";}
		if($res['master']['temps_jusqua'] < -30){ $res['master']['class'] = "danger" ;}
		if(($res['master']['temps_jusqua'] < 0) AND ($res['master']['temps_jusqua'] > -30)){$res['master']['class'] = "warning";}
		if(($res['master']['temps_jusqua'] > 0)&&($res['master']['temps_jusqua'] < 30)){$res['master']['class'] = "info" ;}
		if($res['master']['temps_jusqua'] > 30){$res['master']['class'] = "success";}

    }

    return $res;
}
?>
