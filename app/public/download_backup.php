<?php
// Endpoint pour télécharger les sauvegardes de base de données
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Fichier non spécifié');
}

$filename = basename($_GET['file']);

// Obtenir le chemin du répertoire de sauvegarde
$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'sauvegarde' . DIRECTORY_SEPARATOR;
$filepath = $backup_dir . $filename;

// Vérifier que le fichier existe et est dans le bon répertoire
if (!file_exists($filepath) || !str_starts_with(realpath($filepath), realpath($backup_dir))) {
    http_response_code(404);
    die('Fichier non trouvé');
}

// Vérifier l'extension (seulement .sqlite)
if (!str_ends_with(strtolower($filename), '.sqlite')) {
    http_response_code(400);
    die('Type de fichier non autorisé');
}

// Définir les headers pour le téléchargement
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Lire et envoyer le fichier
readfile($filepath);
?>
