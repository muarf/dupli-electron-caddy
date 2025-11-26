<?php
// Endpoint pour télécharger les PDF depuis le répertoire temporaire système
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Fichier non spécifié');
}

$filename = basename($_GET['file']);
$dir = $_GET['dir'] ?? '';

// Déterminer le répertoire temporaire selon le contexte
if ($dir === 'png_to_pdf') {
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_png_to_pdf' . DIRECTORY_SEPARATOR;
} elseif ($dir === 'unimpose') {
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
} elseif (strpos($dir, 'image_processor_') === 0) {
    // PDFs traités par image_processor
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
} else {
    // Par défaut, utiliser le répertoire temporaire système pour les impositions
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
}

$filepath = $tmp_dir . $filename;

// Vérifier que le fichier existe et est dans le bon répertoire
$real_filepath = realpath($filepath);
$real_tmp_dir = realpath($tmp_dir);
if (!file_exists($filepath) || !$real_filepath || !$real_tmp_dir || strpos($real_filepath, $real_tmp_dir) !== 0) {
    http_response_code(404);
    die('Fichier non trouvé ou expiré');
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
