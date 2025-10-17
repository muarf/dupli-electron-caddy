<?php
// Inclure le système de traduction principal
require_once __DIR__ . '/../controler/functions/i18n.php';

function footerAction($page){ 
	$page = array('page' => $page );
	return template("../view/footer.html.php", $page);
}
?>
