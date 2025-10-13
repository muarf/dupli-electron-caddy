<?php
// Endpoint pour télécharger les PDF depuis le répertoire temporaire système
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Fichier non spécifié');
}

$filename = basename($_GET['file']);
$tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
$filepath = $tmp_dir . $filename;

// Vérifier que le fichier existe et est dans le bon répertoire
$real_filepath = realpath($filepath);
$real_tmp_dir = realpath($tmp_dir);
if (!file_exists($filepath) || !$real_filepath || !$real_tmp_dir || strpos($real_filepath, $real_tmp_dir) !== 0) {
    http_response_code(404);
    die('Fichier non trouvé');
}

// Vérifier l'extension
if (substr(strtolower($filename), -4) !== '.pdf') {
    http_response_code(400);
    die('Type de fichier non autorisé');
}

// Définir les headers pour le téléchargement
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Lire et envoyer le fichier
readfile($filepath);
?>
