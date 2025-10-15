<?php
// Inclure le système de traduction
require_once __DIR__ . '/../controler/functions/simple_i18n.php';

function footerAction($page){ 
	$page = array('page' => $page );
	return template("../view/footer.html.php", $page);
}
?>
