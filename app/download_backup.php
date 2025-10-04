<?php
/**
 * Téléchargement sécurisé des sauvegardes de base de données
 */

session_start();

// Vérifier l'authentification admin
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    die('Accès refusé');
}

// Vérifier que le fichier est spécifié
if (!isset($_GET['file']) || empty($_GET['file'])) {
    http_response_code(400);
    die('Nom de fichier requis');
}

$filename = basename($_GET['file']); // Sécurité : éviter les chemins relatifs

// Vérifier l'extension
if (!preg_match('/\.sqlite$/', $filename)) {
    http_response_code(400);
    die('Format de fichier non autorisé');
}

// Chemin vers le répertoire de sauvegarde
$backup_dir = __DIR__ . '/sauvegarde/';
$filepath = $backup_dir . $filename;

// Vérifier que le fichier existe
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Fichier de sauvegarde non trouvé');
}

// Vérifier que c'est bien un fichier (pas un répertoire)
if (!is_file($filepath)) {
    http_response_code(400);
    die('Le chemin spécifié n\'est pas un fichier');
}

// Obtenir la taille du fichier
$filesize = filesize($filepath);
if ($filesize === false) {
    http_response_code(500);
    die('Impossible de lire le fichier');
}

// Définir les headers pour le téléchargement
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Lire et envoyer le fichier
$handle = fopen($filepath, 'rb');
if ($handle === false) {
    http_response_code(500);
    die('Impossible d\'ouvrir le fichier');
}

// Envoyer le fichier par chunks pour éviter les problèmes de mémoire
while (!feof($handle)) {
    $chunk = fread($handle, 8192); // 8KB chunks
    if ($chunk !== false) {
        echo $chunk;
        flush();
    }
}

fclose($handle);
exit;
?>
