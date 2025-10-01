<?php
/**
 * Fonctions de gestion des nouvelles
 * 
 * @author Duplicator Team
 * @version 1.0
 */

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/database.php';

/**
 * Récupère les 3 dernières nouvelles
 * 
 * @return array Tableau des dernières nouvelles avec date formatée
 */
function get_last_news()
{
    $db = pdo_connect();
    $query = $db->query('SELECT * FROM news order by id DESC limit 0,3');
    $i = 0;
    $array = array(); // Initialisation de $array
    
    // Configuration de la locale française pour le formatage de date
    setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'french');
    
    while ($result = $query->fetch(PDO::FETCH_OBJ)) {
        // Utiliser date() avec format français au lieu de strftime()
        $date_formatted = date('l j F Y', $result->time);
        
        // Traduire les noms anglais en français si nécessaire
        $date_formatted = str_replace(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday',
             'January', 'February', 'March', 'April', 'May', 'June',
             'July', 'August', 'September', 'October', 'November', 'December'],
            ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche',
             'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
            $date_formatted
        );
        
        $array[$i] = array(
            'time' => $date_formatted,
            'titre' => $result->titre,
            'news' => $result->news
        );
        $i++;
    }
    return $array;
}
