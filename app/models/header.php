<?php
// Inclure le système de traduction principal
require_once __DIR__ . '/../controler/functions/i18n.php';

function headerAction($page){ 
	// Initialiser le système de traduction
	I18nManager::getInstance();
	
	$page = array('page' => $page );
	return template("../view/header.html.php", $page);
}
?>
