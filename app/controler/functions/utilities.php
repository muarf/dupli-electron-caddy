<?php
/**
 * Fonctions utilitaires pour l'application Duplicator
 * 
 * Ce fichier contient toutes les fonctions utilitaires générales,
 * de sécurité, de gestion des emails et des paramètres du site.
 */

/**
 * Supprimer un email ou tous les emails
 */
function delete_mail($email = null)
{
    $con = pdo_connect();
    $db  = pdo_connect();
    
    if ($email) {
        // Supprimer un email spécifique
        $query = $db->prepare('DELETE FROM email WHERE email = ?');
        $query->execute([$email]);
    } else {
        // Supprimer tous les emails (comportement original)
        $db->query('DELETE From email') or die('<div class="alert alert-danger"><strong>Danger!</strong> Une erreur s\'est produite.<a href="javascript:" onclick="history.go(-1); return false"></div>');
    }
}

/**
 * Compter et récupérer tous les emails
 */
function count_emails()
{
    $emails =array();
    $con    = pdo_connect();
    $db     = pdo_connect();
    $query  = $db->query('SELECT count(*) as nbr from email');
    $result = $query->fetch(PDO::FETCH_OBJ);
    if (!empty($result->nbr)) {
        $query  = $db->query('SELECT * from email');
        while($result1 = $query->fetch(PDO::FETCH_OBJ))
            {
                $emails[] = $result1->email;
            }
    }
    return $emails;
}

/**
 * Récupérer les mots-clés de tous les tirages
 */
function get_mots()
{
      $con = pdo_connect();
      $db = pdo_connect();
      $query = $db->query('SELECT * FROM photocop WHERE mot != "" order by id DESC');
      $i = 0;
      if(empty($query)){ goto two;}
      while($result =$query->fetch(PDO::FETCH_OBJ))
      {

         $timest =  $result->date;
         $mots['photocop'][$i]['date'] = date('d.m.y',$timest);
         $mots['photocop'][$i]['mot'] = $result->mot;
         $mots['photocop'][$i]['id'] = $result->id;
         if(strlen($result->contact)> 13) { $mots['photocop'][$i]['contact']= substr($result->contact, 0, 10).'...'; }
         else { $mots['photocop'][$i]['contact'] = $result->contact;}
         $i++;
      }
      two:
      $query = $db->query('SELECT * FROM a4 WHERE mot != "" order by id DESC');
      $i = 0;
      if(empty($query)){ goto three;}
      while($result =$query->fetch(PDO::FETCH_OBJ))
      {
         $timest =  $result->date;
         $mots['A4'][$i]['date'] = date('d.m.y',$timest);
         $mots['A4'][$i]['mot'] = $result->mot;
         $mots['A4'][$i]['id'] = $result->id;
         if(strlen($result->contact)> 13) { $mots['A4'][$i]['contact']= substr($result->contact, 0, 10).'...'; }
         else { $mots['A4'][$i]['contact'] = $result->contact;}
         $i++;
      }
    		three:
    	          $query = $db->query('SELECT * FROM dupli WHERE mot != "" order by id DESC');
    		$i=0;
    		if(empty($query)){ goto four;}
        while($result =$query->fetch(PDO::FETCH_OBJ))
      {
          $timest =  $result->date;
         $mots['A3'][$i]['date'] = date('d.m.y',$timest);
         $mots['A3'][$i]['mot'] = $result->mot;
         $mots['A3'][$i]['id'] = $result->id;
         if(strlen($result->contact)> 13) { $mots['A3'][$i]['contact']= substr($result->contact, 0, 10).'...'; }
         else { $mots['A3'][$i]['contact'] = $result->contact;}
         $i++;
      }
      four:
   	return $mots;
}

/**
 * Mettre à jour une news
 */
function update_news($titre,$texte,$id)
{
	$con = pdo_connect();
    $db = pdo_connect();
    $query = $db->prepare('UPDATE news SET titre = :titre, news =:texte WHERE id ='.$_POST['id2'].' ');
    $query->bindParam(':titre', $titre);
    $query->bindParam(':texte', $texte);
    $query->execute() or die ('<div class="alert alert-danger"><strong>Danger!</strong> Une erreur s\'est produite.<a href="javascript:" onclick="history.go(-1); return false"></div>');
}

/**
 * Insérer une nouvelle news
 */
function insert_news($titre,$texte)
{
 $con = pdo_connect();
 $db = pdo_connect();
 $temps = time();
 $query = $db->prepare('INSERT into news (time, titre, news) VALUES (:temps,:titre,:news)');
 $query->bindParam(':temps', $temps);
 $query->bindParam(':titre', $titre);
 $query->bindParam(':news', $texte);
 $query->execute() or die ('<div class="alert alert-danger"> <strong>Danger!</strong> Une erreur s\'est produite.<a href="javascript:" onclick="history.go(-1); return false"></div>');
}

/**
 * Supprimer une news
 */
function delete_news($id){
	$con = pdo_connect();
    $db = pdo_connect();
      $db->query('DELETE from news where id = '.$_POST['id'].'') or die ('<div class="alert alert-danger">  <strong>Danger!</strong> Une erreur s\'est produite.<a href="javascript:" onclick="history.go(-1); return false"></div>');
    }

/**
 * Récupérer une ou toutes les news
 */
function get_news($id)
{
	$con = pdo_connect();
   		$db = pdo_connect();
        
        // Vérifier si $id est vide ou non numérique
        if (empty($id) || !is_numeric($id)) {
            // Retourner toutes les news
            $query = $db->query('SELECT * from news ORDER BY id DESC');
            $i = 0;
            $array = array();
            while($result = $query->fetch(PDO::FETCH_OBJ)){
                $array[$i]['titre'] = $result->titre;
                $array[$i]['temps'] = date('d.m.y',$result->time);
                $array[$i]['news'] = $result->news;
                $array[$i]['id'] = $result->id;
                $i++;
            }
            return $array;
        }
        
        $id = ceil($id);
    	if(!empty($id))
    	{
    		$query = $db->query('SELECT * from news WHERE id = '.$id.'');
    		$result = $query->fetch(PDO::FETCH_OBJ);
    		if ($result) {
    		    $array['titre'] = $result->titre;
     		    $array['temps'] = date('d.m.y',$result->time);
     		    $array['news'] = $result->news;
     		    $array['id'] = $result->id;
    		}
        	
        	}
        	else
        	{
        		$query = $db->query('SELECT * from news ');
        		$i = 0 ;
        		while($result = $query->fetch(PDO::FETCH_OBJ)){
        			$array[$i]['titre'] = $result->titre;
        			$array[$i]['temps'] = date('d.m.y',$result->time);
        			$array[$i]['news'] = $result->news;
        			$array[$i]['id'] = $result->id;
        	
        			$i++;
        		}
        		$result = $array;
        	}
        	return $result;
}

/**
 * Sécuriser les données POST
 */
function secure_post($POST){
    $key = array_keys($_POST);
    foreach ($key as $value){
    	
      $_POST[$value]= htmlentities($_POST[$value]);
    }
  }

/**
 * Récupérer un paramètre du site
 */
function get_site_setting($setting_name, $default_value = null)
{
    $con = pdo_connect();
    $db = pdo_connect();
    
    $query = $db->prepare('SELECT setting_value FROM site_settings WHERE setting_name = ?');
    $query->execute(array($setting_name));
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if ($result) {
        return $result->setting_value;
    }
    
    return $default_value;
}

/**
 * Mettre à jour un paramètre du site
 */
function update_site_setting($setting_name, $setting_value)
{
    $con = pdo_connect();
    $db = pdo_connect();
    
    try {
        $sql = "INSERT INTO site_settings (setting_name, setting_value, updated_at) 
                VALUES (:setting_name, :setting_value, CURRENT_TIMESTAMP) 
                ON CONFLICT(setting_name) DO UPDATE SET 
                setting_value = excluded.setting_value, 
                updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':setting_name', $setting_name);
        $stmt->bindParam(':setting_value', $setting_value);
        $result = $stmt->execute();
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error updating site setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonction de rendu des templates
 */
function template($template_file, $variables = array())
{
    // Extraire les variables pour les rendre disponibles dans le template
    if (is_array($variables)) {
        extract($variables);
    }
    
    // Démarrer la capture de sortie
    ob_start();
    
    // Inclure le fichier template
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        // Essayer avec un chemin relatif depuis le répertoire courant
        $relative_path = __DIR__ . '/../../' . $template_file;
        if (file_exists($relative_path)) {
            include $relative_path;
        } else {
            throw new Exception("Template file not found: " . $template_file);
        }
    }
    
    // Récupérer le contenu et nettoyer le buffer
    $content = ob_get_clean();
    
    return $content;
}
?>