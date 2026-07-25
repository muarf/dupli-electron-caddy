<?php
// Endpoint dédié pour le téléchargement des sauvegardes SQLite
// Fichier complètement isolé pour éviter toute pollution

// Désactiver tout affichage et compression IMMÉDIATEMENT
ini_set('display_errors', 0);
error_reporting(0);

// Nettoyer tous les buffers AVANT toute autre opération
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Désactiver la compression
if (ini_get('zlib.output_compression')) {
    ini_set('zlib.output_compression', 'Off');
}

// Vider tout buffer résiduel
if (ob_get_length() !== false) {
    ob_clean();
}

if (session_status() === PHP_SESSION_NONE) {
    $session_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0777, true);
    }
    session_save_path($session_path);
    session_start();
}

if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && 
    (!isset($_SESSION['user']) || $_SESSION['user'] !== "1")) {
    http_response_code(403);
    die('Accès réservé à l\'administrateur');
}

// Charger uniquement ce qui est nécessaire pour BackupManager
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/admin/BackupManager.php';

$filename = $_GET['file'] ?? '';
if (empty($filename)) {
    http_response_code(400);
    die('Nom de fichier manquant');
}

$filename = basename($filename);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($extension !== 'sqlite') {
    http_response_code(400);
    die('Type de fichier non autorisé');
}

// Utiliser BackupManager pour obtenir le bon répertoire de sauvegarde
$backupManager = new BackupManager($conf);
$backup_dir = $backupManager->getBackupDir();
$filePath = $backup_dir . $filename;

// Liste des répertoires possibles à vérifier
$possible_dirs = array(
    $backup_dir,
    __DIR__ . '/../sauvegarde/',
    __DIR__ . '/../../sauvegarde/',
    sys_get_temp_dir() . '/duplicator_backups/',
    sys_get_temp_dir() . '/dupli-electron-sauvegarde/',
);

$finalPath = null;

// Chercher le fichier dans tous les répertoires possibles
foreach ($possible_dirs as $dir) {
    $testPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($testPath) && is_file($testPath)) {
        $real_test = realpath($testPath);
        $real_dir = realpath($dir);
        if ($real_test && $real_dir && strpos($real_test, $real_dir) === 0) {
            $finalPath = $testPath;
            break;
        }
    }
}

if (!$finalPath) {
    http_response_code(404);
    die('Fichier non trouvé');
}

header('Content-Type: application/x-sqlite3');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($finalPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Pragma: public');

readfile($finalPath);
exit;
