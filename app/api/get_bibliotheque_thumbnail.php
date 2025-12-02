<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    die('File not specified');
}

$baseDir = getBibliothequeDir();
// Sécuriser le chemin
$filename = basename($file);
$path = $baseDir . DIRECTORY_SEPARATOR . $file;

// Validation basique pour s'assurer qu'on reste dans le dossier thumbnails
// Le paramètre file devrait être du style "thumbnails/pdf/hash.png"
// Donc on doit autoriser les / mais vérifier qu'on ne remonte pas
if (strpos($file, '..') !== false) {
    http_response_code(403);
    die('Invalid path');
}

if (!file_exists($path)) {
    http_response_code(404);
    die('Image not found');
}

$mime = mime_content_type($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=86400');

readfile($path);





