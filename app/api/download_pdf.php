<?php
// Endpoint pour télécharger les PDF depuis le répertoire temporaire système
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('Fichier non spécifié');
}

// Inclure la fonction resolveTempDir() pour les impositions
require_once(__DIR__ . '/../controler/functions/utilities.php');

$filename = basename($_GET['file']);
$dir = $_GET['dir'] ?? '';

// Déterminer le répertoire temporaire selon le contexte (utiliser resolveTempDir pour la cohérence)
$base_tmp = resolveTempDir();
if ($dir === 'png_to_pdf') {
    $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_png_to_pdf' . DIRECTORY_SEPARATOR;
} elseif ($dir === 'unimpose') {
    $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
} elseif (strpos($dir, 'image_processor_') === 0) {
    // PDFs traités par image_processor
    $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
} else {
    // Par défaut, utiliser resolveTempDir() pour les impositions (brochure, livre, tracts, imposition)
    // Cela garantit la cohérence avec le répertoire utilisé pour créer les fichiers
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;
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
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Nettoyer le buffer de sortie pour éviter toute pollution du flux PDF
if (ob_get_level()) {
    ob_end_clean();
}

// Lire et envoyer le fichier
readfile($filepath);
exit;
?>
