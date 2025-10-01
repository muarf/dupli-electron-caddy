<?php
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/stats.php';

function Action(){
  $db = pdo_connect();
  $result['stats'] = blablastats();
  if(isset($_GET['page'])){ $page = $_GET['page'] ;}else {$page= 1 ;}
  if(isset($_GET['pagea4'])){ $pagea4 = $_GET['pagea4']; }else {$pagea4= 1 ;}
  $result['stat']['dupli'] = stats_by_machine('dupli',$page);
  $result['stat']['a4'] = stats_by_machine('a4',$pagea4);
  
  // Récupérer les duplicopieurs installés
  $db = pdo_connect();
  $query = $db->query("SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele");
  $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
  $result['duplicopieurs_installes'] = $duplicopieurs;
  
  // Récupérer les statistiques pour chaque duplicopieur
  $result['stat']['duplicopieurs'] = array();
  foreach($duplicopieurs as $duplicop) {
      $machine_name = $duplicop['marque'];
      if ($duplicop['marque'] !== $duplicop['modele']) {
          $machine_name = $duplicop['marque'] . ' ' . $duplicop['modele'];
      }
      
      $page_param = 'page' . strtolower(str_replace(' ', '_', $machine_name));
      if(isset($_GET[$page_param])) {
          $current_page = $_GET[$page_param];
      } else {
          $current_page = 1;
      }
      $result['stat']['duplicopieurs'][$machine_name] = stats_by_machine_duplicopieur($duplicop['id'], $current_page);
  }
  
  // Récupérer les photocopieurs installés
  $query = $db->query("SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != '' ORDER BY marque");
  $photocopiers = $query->fetchAll(PDO::FETCH_COLUMN);
  $result['photocopiers_installes'] = $photocopiers;
  
  // Récupérer les statistiques pour chaque photocopieur
  $result['stat']['photocopiers'] = array();
  foreach($photocopiers as $photocop_name) {
      $page_param = 'page' . strtolower(str_replace(' ', '_', $photocop_name));
      if(isset($_GET[$page_param])) {
          $current_page = $_GET[$page_param];
      } else {
          $current_page = 1;
      }
      $result['stat']['photocopiers'][$photocop_name] = stats_by_machine_photocop($photocop_name, $current_page);
  }
  
  return template("../view/stats.html.php",$result);
}
?>


