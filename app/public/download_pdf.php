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
if (!file_exists($filepath) || !str_starts_with(realpath($filepath), realpath($tmp_dir))) {
    http_response_code(404);
    die('Fichier non trouvé');
}

// Vérifier l'extension
if (!str_ends_with(strtolower($filename), '.pdf')) {
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
