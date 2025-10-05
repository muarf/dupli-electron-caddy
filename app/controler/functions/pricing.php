<?php
/**
 * Fonctions de gestion des tarifications
 * 
 * @author Duplicator Team
 * @version 1.0
 */

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/database.php';

/**
 * Récupère tous les prix des machines et du papier
 * 
 * @return array Tableau des prix organisés par machine et type
 */
function get_price()
{
    $db = pdo_connect();
    
    // Initialiser le tableau avec des valeurs par défaut
    $prix = array();
    
    try {
        // Récupérer les prix avec les clés simples
        $query = $db->query('
            SELECT p.*, 
                   CASE 
                       WHEN p.machine_type = "dupli" THEN CONCAT("dupli_", p.machine_id)
                       WHEN p.machine_type = "photocop" THEN CONCAT("photocop_", p.machine_id)
                       ELSE CONCAT(p.machine_type, "_", p.machine_id)
                   END as machine_key
            FROM prix p
            ORDER BY p.machine_type, p.machine_id, p.type
        ');
        
        if ($query) {
            // CORRECTION DEADLOCK SQLite : Utiliser fetchAll() pour libérer immédiatement le curseur
            $results = $query->fetchAll(PDO::FETCH_OBJ);
            foreach($results as $result)
            {
                // Pour les duplicopieurs, structurer différemment les tambours
                if ($result->machine_type === 'dupli') {
                    // Si c'est un tambour (tambour_noir, tambour_bleu, etc.)
                    if (strpos($result->type, 'tambour_') === 0) {
                        $prix[$result->machine_key][$result->type]['unite'] = $result->unite;
                        $prix[$result->machine_key][$result->type]['pack'] = $result->pack;
                    } else {
                        // Pour master et encre, garder la structure actuelle
                        $prix[$result->machine_key][$result->type]['unite'] = $result->unite;
                        $prix[$result->machine_key][$result->type]['pack'] = $result->pack;
                    }
                } else {
                    // Pour les autres machines (photocopieurs), garder la structure actuelle
                    $prix[$result->machine_key][$result->type]['unite'] = $result->unite;
                    $prix[$result->machine_key][$result->type]['pack'] = $result->pack;
                }
            }
        }
        
        $query = $db->query('select * from papier');
        if ($query) {
            $result = $query->fetch(PDO::FETCH_OBJ);
            if ($result) {
                $prix['papier']['A3'] = $result->prix*2;
                $prix['papier']['A4'] = $result->prix;
            } else {
                // Valeurs par défaut si pas de données
                $prix['papier']['A3'] = 0.02;
                $prix['papier']['A4'] = 0.01;
            }
        } else {
            // Valeurs par défaut si pas de données
            $prix['papier']['A3'] = 0.02;
            $prix['papier']['A4'] = 0.01;
        }
    } catch (Exception $e) {
        // En cas d'erreur, retourner des valeurs par défaut
        $prix['papier']['A3'] = 0.02;
        $prix['papier']['A4'] = 0.01;
    }

    return $prix;
}

/**
 * Insère ou met à jour un prix pour une machine
 * 
 * @param string $machine_type Type de machine (dupli, photocop, etc.)
 * @param int $machine_id ID de la machine
 * @param string $type Type de consommable (master, encre, etc.)
 * @param float $prix_pack Prix du pack
 * @param float $prix_unite Prix à l'unité
 */
function insert_prix($machine_type, $machine_id, $type, $prix_pack, $prix_unite)
{
    $db = pdo_connect();
    
    // Vérifier si l'entrée existe déjà
    $check_query = $db->prepare('SELECT id FROM prix WHERE machine_type = ? AND machine_id = ? AND type = ?');
    $check_query->execute([$machine_type, $machine_id, $type]);
    
    if ($check_query->fetch()) {
        // Mettre à jour l'entrée existante
        $query = $db->prepare('UPDATE prix SET pack = ?, unite = ? WHERE machine_type = ? AND machine_id = ? AND type = ?');
        $query->execute([$prix_pack, $prix_unite, $machine_type, $machine_id, $type]);
    } else {
        // Insérer une nouvelle entrée
        $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES (?, ?, ?, ?, ?)');
        $query->execute([$machine_type, $machine_id, $type, $prix_pack, $prix_unite]);
    }
}

/**
 * Calcule le prix d'un devis pour une machine
 * 
 * @param string $machine Nom de la machine
 * @param float $nb_f Nombre de feuilles
 * @param float $nb_p Nombre de passages
 * @param float $nb_m Nombre de masters
 * @param string $couleur Couleur (pour photocopieurs)
 * @param string $avec_papier Inclure le prix du papier
 * @return array Prix calculé
 */
function get_price_devis($machine, $nb_f, $nb_p, $nb_m, $couleur = NULL, $avec_papier = 'oui')
{	
    $price = get_price();
    
    // Convertir les paramètres en nombres
    $nb_f = floatval($nb_f);
    $nb_p = floatval($nb_p);
    $nb_m = floatval($nb_m);
    
    // Vérifier que $price est un tableau valide
    if (!is_array($price)) {
        $price = array();
        $price['papier'] = array('A3' => 0.02, 'A4' => 0.01);
    }
    
    // Déterminer le type de machine et la taille
    if(($machine =="pA4") OR ($machine =="pA3"))
    { 
        $taille = substr($machine,-2);
        $price['devis']['machine'] = "photocop"; 
    }
    elseif($machine == "comcolor" || in_array($machine, array('comcolor'))) // Machine photocopieuse spécifique
    {
        $taille = "A4"; // Par défaut A4 pour les photocopieuses spécifiques
        $price['devis']['machine'] = "photocop";
    }
    else{ 
        $price['devis']['machine'] = $machine;
        $taille = $machine;
    }
    
    // Vérifier que les clés existent avant de les utiliser
    if ($avec_papier == 'oui') {
        $price['devis']['f'] = $nb_f * (isset($price['papier'][$taille]) ? $price['papier'][$taille] : 0);
        $price['papier'] = isset($price['papier'][$taille]) ? $price['papier'][$taille] : 0;
    } else {
        $price['devis']['f'] = 0;
        $price['papier'] = 0;
    }
    $price['devis']['m'] = $nb_m * (isset($price[$taille]['master']['unite']) ? $price[$taille]['master']['unite'] : 0);
    $price['devis']['p'] = $nb_p * (isset($price[$taille]['encre']['unite']) ? $price[$taille]['encre']['unite'] : 0);
    $price['devis']['t'] = $price['devis']['p'] + $price['devis']['m'] + $price['devis']['f'];
    
    if($price['devis']['machine'] =="photocop")
    {
        if($couleur == "oui") { $type = "couleur"; } else { $type = "noire"; }
        
        // Pour les machines spécifiques comme comcolor, utiliser les prix de cette machine
        if($machine == "comcolor" || in_array($machine, array('comcolor'))) {
            $machine_key = strtolower(str_replace(' ', '_', $machine));
            $unite = isset($price[$machine_key][$type]['unite']) ? $price[$machine_key][$type]['unite'] : 0;
        } else {
            // Pour les machines génériques pA3/pA4
            $unite = isset($price['photocop'][$type]['unite']) ? $price['photocop'][$type]['unite'] : 0;
        }
        
        ($taille =="A3") ? $unite = $unite : $unite = $unite/2;
        $price['devis']['p'] = $nb_p * $unite;
        $price['devis']['t'] = $price['devis']['f'] +  $price['devis']['p'];
    }
		
    return $price;
}

/**
 * Calcule le montant dû pour une machine
 * 
 * @param string $machine Nom de la machine
 * @return float Montant dû
 */
function prix_du($machine)
{
    $db = pdo_connect();
    
    // Vérifier si c'est un duplicopieur (nom complet comme "Ricoh dx4545")
    $query_check = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND CONCAT(marque, " ", modele) = ?');
    $query_check->execute([$machine]);
    $is_duplicopieur = $query_check->fetchColumn() > 0;
    
    if ($is_duplicopieur) {
        // C'est un duplicopieur, utiliser la table dupli avec filtre par duplicopieur_id
        $query_dup = $db->prepare('SELECT id FROM duplicopieurs WHERE actif = 1 AND CONCAT(marque, " ", modele) = ?');
        $query_dup->execute([$machine]);
        $duplicopieur_id = $query_dup->fetchColumn();
        
        if ($duplicopieur_id) {
            $query = $db->prepare('SELECT sum(prix) AS nbr FROM dupli WHERE duplicopieur_id = ? AND paye = "non"');
            $query->execute([$duplicopieur_id]);
        } else {
            // Fallback si pas trouvé
            $query = $db->query('SELECT sum(prix) AS nbr FROM dupli WHERE paye = "non"');
        }
    } else if ($machine === 'A3' || $machine === 'A4' || $machine === 'dupli') {
        // Pour A3, A4, et dupli (ancien système), utiliser la table dupli sans filtre
        $query = $db->query('SELECT sum(prix) AS nbr FROM dupli WHERE paye = "non"');
    } else {
        // Pour les photocopieurs, utiliser la table photocop avec filtre par marque
        $query = $db->prepare('SELECT sum(prix) AS nbr FROM photocop WHERE marque = ? AND paye = "non"');
        $query->execute(array($machine));
    }
    
    $result = $query->fetch(PDO::FETCH_OBJ);
    $euros = $result->nbr;
    
    return $euros;
}

/**
 * Met à jour le prix du papier
 * 
 * @param float $papier Nouveau prix du papier
 */
function insert_papier($papier)
{
    $db = pdo_connect();
    $query = $db->prepare('UPDATE papier set prix = :papier WHERE id = 1');
    $query->bindparam(':papier',$papier);
    $query->execute() or die();
}
