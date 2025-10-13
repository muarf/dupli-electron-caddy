<?php
/**
 * Fonctions de gestion des statistiques
 * 
 * @author Duplicator Team
 * @version 1.0
 */

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/database.php';

/**
 * Nombre feuilles depuis duplicopieur
 */
function nombre_feuilles_depuis_duplicopieur($duplicop_id, $now, $ago)
{
            $db        = pdo_connect();
    $sql       = "";
    $nbf_total = array();
    
    if (!empty($now)) {
        $nbf_total['ago'] = $now - $ago;
        $sql              = ' WHERE date < ' . $now . ' AND date >' . $nbf_total['ago'] . ' AND duplicopieur_id = ' . intval($duplicop_id);
    } else {
        $sql = ' WHERE duplicopieur_id = ' . intval($duplicop_id);
    }
    
    $query                 = $db->query('select sum(rv) as nbr from dupli ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbf']      = $result->nbr ?? 0;
    $query                 = $db->query('select sum(prix) as nbr from dupli ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prix']     = $result->nbr ?? 0;
    $query                 = $db->query('select sum(cb) as nbr from dupli ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prixpaye'] = $result->nbr ?? 0;
    $query                 = $db->query('select count(*) as nbr from dupli ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbt']      = $result->nbr ?? 0;
    if (!empty($nbf_total['nbt'])) {
        $nbf_total['moy'] = $nbf_total['nbf'] / $nbf_total['nbt'];
    } else {
        $nbf_total['moy'] = $nbf_total['nbf'];
    }
    $nbf_total['benef'] = $nbf_total['prixpaye'] - $nbf_total['prix'];
    
    return $nbf_total;
}

/**
 * Nombre feuilles depuis
 */
function nombre_feuilles_depuis($machine, $now, $ago)
{
            $db        = pdo_connect();
    $sql       = "";
    $nbf_total = array();
    
    // Gérer le cas spécial de la table A3 qui a été renommée en 'dupli'
    $table_name = $machine;
    if ($machine === 'a3') {
        $table_name = 'dupli';
    }
    
    if (!empty($now)) {
        $nbf_total['ago'] = $now - $ago;
        $sql              = ' WHERE date < ' . $now . ' AND date >' . $nbf_total['ago'] . '';
    }
    $query                 = $db->query('select sum(rv) as nbr from ' . $table_name . ' ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbf']      = $result->nbr;
    $query                 = $db->query('select sum(prix) as nbr from ' . $table_name . ' ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prix']     = $result->nbr;
    $query                 = $db->query('select sum(cb) as nbr from ' . $table_name . ' ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prixpaye'] = $result->nbr;
    $query                 = $db->query('select count(*) as nbr from ' . $table_name . '' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbt']      = $result->nbr;
    if (!empty($nbf_total['nbt'])) {
        $nbf_total['moy'] = $nbf_total['nbf'] / $nbf_total['nbt'];
    } else {
        $nbf_total['moy'] = $nbf_total['nbf'];
    }
    $nbf_total['benef'] = $nbf_total['prixpaye'] - $nbf_total['prix'];
    
    return $nbf_total;
}

/**
 * Nombre feuilles depuis photocop
 */
function nombre_feuilles_depuis_photocop($photocop_name, $now, $ago)
{
            $db        = pdo_connect();
    $sql       = "";
    $nbf_total = array();
    if (!empty($now)) {
        $nbf_total['ago'] = $now - $ago;
        $sql              = ' WHERE marque = "' . $photocop_name . '" AND date < ' . $now . ' AND date >' . $nbf_total['ago'] . '';
    } else {
        $sql              = ' WHERE marque = "' . $photocop_name . '"';
    }
    $query                 = $db->query('select sum(nb_f) as nbr from photocop ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbf']      = $result->nbr ? $result->nbr : 0;
    $query                 = $db->query('select sum(prix) as nbr from photocop ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prix']     = $result->nbr ? $result->nbr : 0;
    $query                 = $db->query('select sum(cb) as nbr from photocop ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['prixpaye'] = $result->nbr ? $result->nbr : 0;
    $query                 = $db->query('select count(*) as nbr from photocop ' . $sql . '');
    $result                = $query->fetch(PDO::FETCH_OBJ);
    $nbf_total['nbt']      = $result->nbr ? $result->nbr : 0;
    if (!empty($nbf_total['nbt'])) {
        $nbf_total['moy'] = $nbf_total['nbf'] / $nbf_total['nbt'];
    } else {
        $nbf_total['moy'] = $nbf_total['nbf'];
    }
    $nbf_total['benef'] = $nbf_total['prixpaye'] - $nbf_total['prix'];
    
    return $nbf_total;
}

/**
 * Stats par mois
 */
function stats_par_mois($machine)
{
    $db = pdo_connect();
    $db     = pdo_connect();
    
    // Gérer le cas spécial de la table A3 qui a été renommée en 'dupli'
    $table_name = $machine;
    if ($machine === 'a3') {
        $table_name = 'dupli';
    }
    
    $query  = $db->query('SELECT date from ' . $table_name . ' order by id asc limit 1');
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    // Si la table est vide, retourner des statistiques vides
    if (!$result) {
        return array(
            'nb_f' => 0,
            'nb_t' => 0,
            'nb_moy_par_mois' => 0,
            'fin' => 0
        );
    }
    
    $now    = time();
    $i      = 0;
    $mois   = 86400 * 30;
    $stat   = array();
    
    while ($now >= $result->date) {
        $stat[$i] = nombre_feuilles_depuis($machine, $now, $mois);
        $now      = $stat[$i]['ago'];
        $i++;
    }
    $stats_par_mois['nb_f'] = 0;
    $stats_par_mois['nb_t'] = 0;
    $stats_par_mois['nb_moy_par_mois'] = 0;
    for ($i = 0; $i < count($stat); $i++) {
        $stats_par_mois['nb_f']  += $stat[$i]['nbf'] ;
        $stats_par_mois['nb_t']  += $stat[$i]['nbt'] ;
        $stats_par_mois['nb_moy_par_mois'] += $stat[$i]['moy'] ;
    }
    $stats_par_mois['fin'] = count($stat);
    
    return $stats_par_mois;
}

/**
 * Stats par mois pour photocopieurs
 */
function stats_par_mois_photocop($photocop_name)
{
    $db = pdo_connect();
    
    $query  = $db->query('SELECT date from photocop WHERE marque = "' . $photocop_name . '" order by id asc limit 1');
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    // Si la table est vide, retourner des statistiques vides
    if (!$result) {
        return array(
            'nb_f' => 0,
            'nb_t' => 0,
            'nb_moy_par_mois' => 0,
            'fin' => 0
        );
    }
    
    $now    = time();
    $i      = 0;
    $mois   = 86400 * 30;
    $stat   = array();
    
    while ($now >= $result->date) {
        $stat[$i] = nombre_feuilles_depuis_photocop($photocop_name, $now, $mois);
        $now      = $stat[$i]['ago'];
        $i++;
    }
    $stats_par_mois['nb_f'] = 0;
    $stats_par_mois['nb_t'] = 0;
    $stats_par_mois['nb_moy_par_mois'] = 0;
    for ($i = 0; $i < count($stat); $i++) {
        $stats_par_mois['nb_f']  += $stat[$i]['nbf'] ;
        $stats_par_mois['nb_t']  += $stat[$i]['nbt'] ;
        $stats_par_mois['nb_moy_par_mois'] += $stat[$i]['moy'] ;
    }
    $stats_par_mois['fin'] = count($stat);
    
    return $stats_par_mois;
}

/**
 * Select first entry
 */
function select_first_entry($machine)
{
    $db = pdo_connect();
    $db     = pdo_connect();
    
    // Gérer le cas spécial de la table A3 qui a été renommée en 'dupli'
    $table_name = $machine;
    if ($machine === 'a3') {
        $table_name = 'dupli';
    }
    
    $query  = $db->query('SELECT date from ' . $table_name . ' order by id asc limit 1');
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
}

/**
 * Calcule les statistiques globales de toutes les machines
 * 
 * @param int $fin Nombre de mois
 * @param int $nb_t Nombre de tirages
 * @param int $nb_f Nombre de feuilles
 * @param int $nb_moy_par_mois Moyenne par mois
 * @param int $ca_voulu CA voulu
 * @param int $ca_cb_paye CA payé par CB
 * @param int $ca_declare_paye CA déclaré payé
 * @return array Statistiques globales
 */
function blablastats($fin=0,$nb_t=0,$nb_f=0,$nb_moy_par_mois=0,$ca_voulu = 0,$ca_cb_paye =0,$ca_declare_paye =0)
{
    $db = pdo_connect();
    $machines = array('a4','dupli');
    foreach ($machines as $machine){
        $stats_par_mois[$machine] = stats_par_mois($machine);
        foreach($stats_par_mois as $mois){
            $fin += $mois['fin'];
            $nb_t += $mois['nb_t'];
            $nb_f += $mois['nb_f'];
            $nb_moy_par_mois += $mois['nb_moy_par_mois'];
        }
        // Gérer le cas spécial de la table A3 qui a été renommée en 'dupli'
        $table_name = $machine;
        if ($machine === 'a3') {
            $table_name = 'dupli';
        }
        
        $query= $db->query('select * FROM '.$table_name.'');
        while($result = $query->fetch(PDO::FETCH_OBJ))
        {
            $ca_voulu += (!is_numeric($result->prix)) ? 0: $result->prix;
            $ca_cb_paye += (!is_numeric($result->cb)) ? 0: $result->cb; 
            if(is_numeric($result->prix))
            {
                if($result->paye == "oui"){ $ca_declare_paye += $result->prix; }
            }
        }      
    }
    
    // Ajouter les statistiques des photocopieurs
    $photocopiers = $db->query("SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach($photocopiers as $photocop_name) {
        $stats_par_mois_photocop = stats_par_mois_photocop($photocop_name);
        $fin += $stats_par_mois_photocop['fin'];
        $nb_t += $stats_par_mois_photocop['nb_t'];
        $nb_f += $stats_par_mois_photocop['nb_f'];
        $nb_moy_par_mois += $stats_par_mois_photocop['nb_moy_par_mois'];
        
        // Ajouter les CA des photocopieurs
        $query = $db->prepare("SELECT * FROM photocop WHERE marque = ?");
        $query->execute([$photocop_name]);
        while($result = $query->fetch(PDO::FETCH_OBJ))
        {
            $ca_voulu += (!is_numeric($result->prix)) ? 0: $result->prix;
            $ca_cb_paye += (!is_numeric($result->cb)) ? 0: $result->cb; 
            if(is_numeric($result->prix))
            {
                if($result->paye == "oui"){ $ca_declare_paye += $result->prix; }
            }
        }
    }
    $doit = $ca_voulu - $ca_declare_paye;
    $benf = $ca_cb_paye - $ca_voulu;
    
    // Éviter la division par zéro
    if ($fin > 0) {
        $nb_t_par_mois = round(($nb_t / $fin),2);
        $nbf_par_mois = round(($nb_f / $fin),2);
        $nb_moy_par_mois = round($nb_moy_par_mois / $fin,2);
    } else {
        $nb_t_par_mois = 0;
        $nbf_par_mois = 0;
        $nb_moy_par_mois = 0;
    }
    $stats = array(
        'nb_f' => $nb_f,
        'nb_t' => $nb_t,
        'nb_t_par_mois' => $nb_t_par_mois,
        'nbf_par_mois' => $nbf_par_mois,
        'nb_moy_par_mois' => $nb_moy_par_mois,
        'ca' => $ca_voulu,
        'ca2' => $ca_declare_paye,
        'ca1' => $ca_cb_paye,
        'benf' => $benf,
        'doit' => $doit
    );
    return $stats;
}

/**
 * Statistiques par machine avec pagination
 */
function stats_by_machine($machine, $page)
{
    $db    = pdo_connect();
    $now   = time();
    $i     = 0;
    $stat  = array();
    $first = select_first_entry($machine);
    
    // Si la table est vide, retourner des statistiques vides
    if (!$first) {
        return array(
            'stat' => array(),
            'fin' => 0,
            'reste' => 0,
            'ii' => 0,
            'nb_page' => 0
        );
    }
    
    $first = $first->date;
    $mois  = 86400 * 30;
    while ($now >= $first) {
        // echo $machine.' '. $now.' '.$mois ;
        $stat[$i] = nombre_feuilles_depuis($machine, $now, $mois);
        $now      = $stat[$i]['ago'];
        $i++;
    }
    
    // Filtrer les mois avec des données réelles
    $stat_filtered = array();
    foreach($stat as $month_data) {
        if(($month_data['nbf'] ?? 0) > 0 || ($month_data['nbt'] ?? 0) > 0) {
            $stat_filtered[] = $month_data;
        }
    }
    
    $fin     = count($stat_filtered); // nombre d'entrée filtré
    
    // Éviter la division par zéro
    if ($fin > 0) {
        $nb_page = round($fin / 12); //nombres de pages a paginér
        $reste   = fmod($fin, 12);
    } else {
        $nb_page = 0;
        $reste   = 0;
    }
    
    $fin     = 12 * $page;
    $ii      = $fin - 12;
    if ($page == $nb_page + 1) {
        $reste = 12 - $reste;
        $fin   = $fin - $reste;
    } // fin ede la pagination
    $stats_by_machine = array(
        'stat' => $stat_filtered,
        'fin' => $fin,
        'reste' => $reste,
        'ii' => $ii,
        'nb_page' => $nb_page
    );
    return $stats_by_machine;
}

/**
 * Statistiques par duplicopieur avec pagination
 */
function stats_by_machine_duplicopieur($duplicop_id, $page)
{
    $db    = pdo_connect();
    $now   = time();
    $i     = 0;
    $stat  = array();
    
    // Récupérer la première entrée pour ce duplicopieur spécifique
    $query = $db->prepare('SELECT date FROM dupli WHERE duplicopieur_id = ? ORDER BY id ASC LIMIT 1');
    $query->execute([$duplicop_id]);
    $first = $query->fetch(PDO::FETCH_OBJ);
    
    // Si la table est vide ou pas d'entrée pour ce duplicopieur, retourner des statistiques vides
    if (!$first) {
        return array(
            'stat' => array(),
            'fin' => 0,
            'reste' => 0,
            'ii' => 0,
            'nb_page' => 0
        );
    }
    
    $first = $first->date;
    $mois  = 86400 * 30;
    while ($now >= $first) {
        $stat[$i] = nombre_feuilles_depuis_duplicopieur($duplicop_id, $now, $mois);
        $now      = $stat[$i]['ago'];
        $i++;
    }
    
    // Filtrer les mois avec des données réelles
    $stat_filtered = array();
    foreach($stat as $month_data) {
        if(($month_data['nbf'] ?? 0) > 0 || ($month_data['nbt'] ?? 0) > 0) {
            $stat_filtered[] = $month_data;
        }
    }
    
    $fin     = count($stat_filtered); // nombre d'entrée filtré
    
    // Éviter la division par zéro
    if ($fin > 0) {
        $nb_page = round($fin / 12); //nombres de pages a paginér
        $reste   = fmod($fin, 12);
    } else {
        $nb_page = 0;
        $reste   = 0;
    }
    
    $fin     = 12 * $page;
    $ii      = $fin - 12;
    if ($page == $nb_page + 1) {
        $reste = 12 - $reste;
        $fin   = $fin - $reste;
    } // fin ede la pagination
    $stats_by_machine = array(
        'stat' => $stat_filtered,
        'fin' => $fin,
        'reste' => $reste,
        'ii' => $ii,
        'nb_page' => $nb_page
    );
    return $stats_by_machine;
}

/**
 * Statistiques par photocopieur avec pagination
 */
function stats_by_machine_photocop($photocop_name, $page)
{
    $db    = pdo_connect();
    $now   = time();
    $i     = 0;
    $stat  = array();
    
    // Récupérer la première entrée pour ce photocopieur spécifique
    $query = $db->query('SELECT date FROM photocop WHERE marque = "' . $photocop_name . '" ORDER BY id ASC LIMIT 1');
    $first = $query->fetch(PDO::FETCH_OBJ);
    
    // Si la table est vide ou pas d'entrée pour ce photocopieur, retourner des statistiques vides
    if (!$first) {
        return array(
            'stat' => array(),
            'fin' => 0,
            'reste' => 0,
            'ii' => 0,
            'nb_page' => 0
        );
    }
    
    $first = $first->date;
    $mois  = 86400 * 30;
    while ($now >= $first) {
        $stat[$i] = nombre_feuilles_depuis_photocop($photocop_name, $now, $mois);
        $now      = $stat[$i]['ago'];
        $i++;
    }
    
    // Filtrer les mois avec des données réelles
    $stat_filtered = array();
    foreach($stat as $month_data) {
        if(($month_data['nbf'] ?? 0) > 0 || ($month_data['nbt'] ?? 0) > 0) {
            $stat_filtered[] = $month_data;
        }
    }
    
    $fin     = count($stat_filtered); // nombre d'entrée filtré
    
    // Éviter la division par zéro
    if ($fin > 0) {
        $nb_page = round($fin / 12); //nombres de pages a paginér
        $reste   = fmod($fin, 12);
    } else {
        $nb_page = 0;
        $reste   = 0;
    }
    
    $fin     = 12 * $page;
    $ii      = $fin - 12;
    if ($page == $nb_page + 1) {
        $reste = 12 - $reste;
        $fin   = $fin - $reste;
    } // fin ede la pagination
    $stats_by_machine = array(
        'stat' => $stat_filtered,
        'fin' => $fin,
        'reste' => $reste,
        'ii' => $ii,
        'nb_page' => $nb_page
    );
    return $stats_by_machine;
}

