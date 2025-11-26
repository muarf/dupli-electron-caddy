<?php
// API ultra-légère pour vérifier la progression sans charger tout le framework
// Cela évite les timeouts 504 dus à la charge serveur ou aux verrous de session

// Désactiver toute compression de sortie qui pourrait bufferiser
if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', 1);
}
ini_set('zlib.output_compression', 0);

// Headers CORS et JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Récupérer la clé
$key = isset($_GET['key']) ? $_GET['key'] : '';

// Validation basique de la clé pour sécurité (alphanumeric + dot + underscore)
if (empty($key) || !preg_match('/^[a-zA-Z0-9._]+$/', $key)) {
    echo json_encode(['status' => 'error', 'message' => 'Clé invalide']);
    exit;
}

// Construire le chemin du fichier (même logique que image_processor.php)
$tmp_dir = sys_get_temp_dir();
$filename = 'duplicator_image_processor_progress_' . $key . '.json';
$filepath = $tmp_dir . DIRECTORY_SEPARATOR . $filename;

// Vérifier l'existence
if (file_exists($filepath)) {
    // Lire le fichier sans verrouillage bloquant
    $content = @file_get_contents($filepath);
    if ($content === false) {
        echo json_encode(['status' => 'error', 'message' => 'Erreur de lecture']);
    } else {
        echo $content;
    }
} else {
    echo json_encode(['status' => 'not_found']);
}
exit;


