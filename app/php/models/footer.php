<?php
function footerAction($page){ 
	$page = array('page' => $page );
	return template("../view/footer.html.php", $page);
}
?>
