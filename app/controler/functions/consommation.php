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

    // CORRECTION : Chercher le nom réel de la machine dans la base de données au lieu d'utiliser des noms en dur
    $machine_for_cons = $machine;
    $duplicopieur_id = null;
    $table_name = 'dupli'; // Par défaut pour les duplicopieurs
    $nb = array('master_av' => 0, 'passage_av' => 0); // Initialiser par défaut
    
    if($machine != 'photocop')
    {
        // Chercher dans la table duplicopieurs pour obtenir le nom réel utilisé dans cons
        if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
            $query_dup = $db->prepare('SELECT id, marque, modele FROM duplicopieurs WHERE ((marque || " " || modele) = ? OR marque = ? OR LOWER(marque) = LOWER(?)) AND actif = 1 LIMIT 1');
        } else {
            $query_dup = $db->prepare('SELECT id, marque, modele FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ? OR LOWER(marque) = LOWER(?)) AND actif = 1 LIMIT 1');
        }
        $query_dup->execute([$machine, $machine, $machine]);
        $dup_result = $query_dup->fetch(PDO::FETCH_ASSOC);
        
        if ($dup_result) {
            // Trouvé dans duplicopieurs : utiliser le nom réel (marque ou marque + modele)
            $duplicopieur_id = $dup_result['id'];
            if ($dup_result['marque'] === $dup_result['modele']) {
                $machine_for_cons = $dup_result['marque'];
            } else {
                $machine_for_cons = $dup_result['marque'] . ' ' . $dup_result['modele'];
            }
            $table_name = 'dupli';
            $nb = get_last_number($table_name);
        } else {
            // Pas trouvé dans duplicopieurs : utiliser le nom tel quel (pour compatibilité avec anciens noms)
            $machine_for_cons = $machine;
            // Essayer de deviner la table : si ce n'est pas un nom connu, utiliser le nom directement
            $known_names = ['dx4545', 'A3', 'A4', 'dupli'];
            if (in_array(strtolower($machine), array_map('strtolower', $known_names))) {
                $table_name = 'dupli';
            } else {
                $table_name = $machine;
            }
            $nb = get_last_number($table_name);
        }
    }
    
    // Rechercher dans cons avec le nom réel de la machine (insensible à la casse)
    $query = $db->prepare('SELECT * FROM cons WHERE LOWER(machine) = LOWER(?)');
    $query->execute([$machine_for_cons]);
    $i=0;
    while ($result = $query->fetch(PDO::FETCH_OBJ))
    {
      $res[$i]['date'] = intval($result->date);
      $res[$i]['type'] = $result->type;
      $res[$i]['nb_p'] = $result->nb_p;
      $res[$i]['nb_m'] = $result->nb_m;
      $res[$i]['tambour'] = $result->tambour ?? null; // Récupérer le champ tambour pour identifier le type de tambour
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
          $res['encre'][$i_encre]['tambour'] = $res[$i]['tambour'] ?? null; // Stocker le tambour associé à chaque changement
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
    	// CORRECTION : Chercher le prix dans la nouvelle structure (photocop_{id} au lieu de photocop)
    	// Trouver le premier photocopieur actif pour récupérer son ID
    	$query_photocop = $db->query('SELECT id FROM photocopieurs WHERE actif = 1 ORDER BY id LIMIT 1');
    	$photocop_result = $query_photocop->fetch(PDO::FETCH_ASSOC);
    	
    	$prix_pack = 140; // Valeur par défaut
    	$prix_unite = 0.005; // Valeur par défaut
    	
    	if ($photocop_result) {
    	    $photocop_id = $photocop_result['id'];
    	    $machine_key = 'photocop_' . $photocop_id;
    	
    	    // Chercher dans la nouvelle structure retournée par get_price()
    	    if (isset($prix[$machine_key]['noire']['pack'])) {
    	        $prix_pack = $prix[$machine_key]['noire']['pack'];
    	    }
    	    if (isset($prix[$machine_key]['noire']['unite'])) {
    	        $prix_unite = $prix[$machine_key]['noire']['unite'];
    	    }
    	}
    	
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
      
      // CORRECTION : Utiliser l'ID du duplicopieur trouvé précédemment ou chercher dynamiquement
      $machine_key = '';
      if ($duplicopieur_id !== null) {
          // Utiliser l'ID trouvé au début de la fonction
          $machine_key = 'dupli_' . $duplicopieur_id;
      } else {
          // Si pas trouvé au début, chercher maintenant dans duplicopieurs
          if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
              $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE ((marque || " " || modele) = ? OR marque = ? OR LOWER(marque) = LOWER(?)) AND actif = 1 LIMIT 1');
          } else {
              $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ? OR LOWER(marque) = LOWER(?)) AND actif = 1 LIMIT 1');
          }
          $query_dup->execute([$machine, $machine, $machine]);
          $dup_result = $query_dup->fetch(PDO::FETCH_ASSOC);
          if ($dup_result) {
              $machine_key = 'dupli_' . $dup_result['id'];
          } else {
              // Fallback : si vraiment pas trouvé, essayer avec le nom d'origine
              // Mais ne pas utiliser dupli_1 en dur
              $machine_key = strtoupper($machine);
          }
      }
      
      // Calcul du prix master (inchangé)
      $res['master']['prix_calcule'] = ($res['master']['moyenne_totale']['nb_m'] > 0) ? ($prix[$machine_key]['master']['pack'] ?? 0) / $res['master']['moyenne_totale']['nb_m'] : 0;
      
      // CORRECTION : Calcul du prix encre avec détection du tambour
      // Déterminer quel tambour est utilisé (chercher dans les données encre récupérées)
      $tambour_utilise = null;
      if (isset($res['encre']) && is_array($res['encre'])) {
          // Prendre le premier tambour trouvé (ou le plus fréquent si plusieurs)
          foreach ($res['encre'] as $encre_data) {
              if (isset($encre_data['tambour']) && !empty($encre_data['tambour'])) {
                  $tambour_utilise = $encre_data['tambour'];
                  break; // Utiliser le premier trouvé
              }
          }
      }
      
      // Chercher le prix pack : d'abord avec le tambour spécifique, puis fallback sur 'encre'
      $prix_pack_encre = 0;
      if ($tambour_utilise && isset($prix[$machine_key][$tambour_utilise]['pack'])) {
          // Utiliser le prix du tambour spécifique trouvé dans cons
          $prix_pack_encre = $prix[$machine_key][$tambour_utilise]['pack'];
      } elseif (isset($prix[$machine_key]['tambour_noir']['pack'])) {
          // Fallback sur tambour_noir (le plus commun)
          $prix_pack_encre = $prix[$machine_key]['tambour_noir']['pack'];
      } elseif (isset($prix[$machine_key]['encre']['pack'])) {
          // Fallback sur 'encre' (ancienne structure de compatibilité)
          $prix_pack_encre = $prix[$machine_key]['encre']['pack'];
      }
      
      $res['encre']['prix_calcule'] = ($res['encre']['moyenne_totale']['nb_p'] > 0) 
          ? $prix_pack_encre / $res['encre']['moyenne_totale']['nb_p'] 
          : 0;
      
      // Chercher le prix unitaire avec la même logique
      $prix_unite_encre = 0;
      if ($tambour_utilise && isset($prix[$machine_key][$tambour_utilise]['unite'])) {
          $prix_unite_encre = $prix[$machine_key][$tambour_utilise]['unite'];
      } elseif (isset($prix[$machine_key]['tambour_noir']['unite'])) {
          $prix_unite_encre = $prix[$machine_key]['tambour_noir']['unite'];
      } elseif (isset($prix[$machine_key]['encre']['unite'])) {
          $prix_unite_encre = $prix[$machine_key]['encre']['unite'];
      }
      
      ($res['encre']['prix_calcule'] < $prix_unite_encre) 
          ? $res['encre']['color'] = "green" 
          : $res['encre']['color'] = "red";
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
