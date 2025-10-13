<?php
function headerAction($page){ 
	$page = array('page' => $page );
	return template("../view/header.html.php", $page);
}
?>
