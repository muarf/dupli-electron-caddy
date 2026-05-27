<?php
/**
 * API pour obtenir le chemin absolu d'un fichier PDF depuis son URL de téléchargement
 */

if (!isset($_GET['file'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Fichier non spécifié']));
}

require_once(__DIR__ . '/../controler/functions/utilities.php');

$filename = basename($_GET['file']);
$dir = $_GET['dir'] ?? '';

// Déterminer le répertoire temporaire selon le contexte (même logique que download_pdf.php)
if ($dir === 'png_to_pdf') {
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_png_to_pdf' . DIRECTORY_SEPARATOR;
} elseif ($dir === 'unimpose') {
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
} elseif (strpos($dir, 'image_processor_') === 0) {
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
} else {
    // Par défaut, utiliser resolveTempDir() pour les impositions
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;
}

$filepath = $tmp_dir . $filename;

// Vérifier que le fichier existe et est dans le bon répertoire
$real_filepath = realpath($filepath);
$real_tmp_dir = realpath($tmp_dir);
if (!file_exists($filepath) || !$real_filepath || !$real_tmp_dir || strpos($real_filepath, $real_tmp_dir) !== 0) {
    http_response_code(404);
    die(json_encode(['success' => false, 'error' => 'Fichier non trouvé ou expiré']));
}

// Vérifier l'extension
if (substr(strtolower($filename), -4) !== '.pdf') {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Type de fichier non autorisé']));
}

// Retourner le chemin absolu
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'path' => $real_filepath,
    'filename' => $filename
]);
?>

