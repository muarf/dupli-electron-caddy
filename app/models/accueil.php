<?php
require_once __DIR__ . '/../controler/functions/news.php';
require_once __DIR__ . '/../controler/functions/email.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/simple_i18n.php';

function Action(){
  $db = pdo_connect();
  $result['news']= get_last_news();
  if(isset($_POST['email']))
  {     
      $email = htmlentities($_POST['email']);
      $result['email'] = add_email_to_mailing_list($email);
  }
  
  // Récupérer le paramètre d'affichage de la liste de diffusion
  $db = pdo_connect();
  $query = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_name = ?');
  $query->execute(['show_mailing_list']);
  $result_setting = $query->fetch(PDO::FETCH_OBJ);
  $result['show_mailing_list'] = $result_setting ? $result_setting->setting_value : '1';
  
  return template("../view/accueil.html.php",$result);
}
?>
