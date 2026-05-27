<?php
/**
 * Script d'indexation en arrière-plan optimisé pour le scan profond (PHP Pur)
 */
ignore_user_abort(true);
set_time_limit(0);
ini_set('memory_limit', '2048M');

if (php_sapi_name() !== 'cli') {
    die("Ce script ne peut être exécuté qu'en ligne de commande.");
}

require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Arguments
$param = $argv[1] ?? '';
$recursive = ($argv[2] ?? '0') === '1';
$jobId = $argv[3] ?? '';
$mode = $argv[4] ?? 'index';

$logFile = __DIR__ . '/../logs/bibliotheque_scan.log';
$statusDir = __DIR__ . '/../logs/indexing_status';
if (!is_dir($statusDir)) mkdir($statusDir, 0777, true);
$statusFile = $statusDir . '/' . $jobId . '.json';

function logScan($msg) {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
}

function updateStatus($status, $percent, $currentFile = '', $total = 0, $indexed = 0, $errors = 0) {
    global $statusFile, $jobId;
    $data = [
        'job_id' => $jobId,
        'status' => $status,
        'percent' => $percent,
        'current_file' => $currentFile,
        'scanned_count' => $total,
        'indexed_count' => $indexed,
        'error_count' => $errors,
        'updated_at' => time()
    ];
    file_put_contents($statusFile, json_encode($data));
}

logScan("Démarrage du Job $jobId (Mode: $mode)");

try {
    $libManager = new BibliothequeManager();
    $paths = explode('|', $param);
    $allFiles = [];
    
    updateStatus('scanning', 0);
    foreach ($paths as $path) {
        if (empty($path)) continue;
        logScan("Scan du dossier : $path");
        $found = scanDirectoryForLibrary($path, $recursive);
        $allFiles = array_merge($allFiles, $found);
    }
    
    $totalFiles = count($allFiles);
    logScan("Total fichiers trouvés : $totalFiles");

    if ($totalFiles === 0) {
        updateStatus('completed', 100);
        logScan("Fin du job : aucun fichier trouvé.");
        exit(0);
    }

    $indexedCount = 0;
    $errorCount = 0;

    foreach ($allFiles as $index => $file) {
        $percent = round(($index / $totalFiles) * 100);
        $filename = basename($file['path']);
        
        updateStatus('indexing', $percent, $filename, $totalFiles, $indexedCount, $errorCount);
        
        logScan("Indexation ($index/$totalFiles) : $filename");

        try {
            // addExternalFile gère en interne le skip si déjà indexé (si $forceUpdate = false)
            $result = $libManager->addExternalFile($file['path'], $forceUpdate);
            $indexedCount++;
        } catch (Exception $e) {
            $errorCount++;
            logScan("ERREUR sur $filename : " . $e->getMessage());
        }
        
        if ($index % 10 === 0) gc_collect_cycles();
    }

    updateStatus('completed', 100, '', $totalFiles, $indexedCount, $errorCount);
    logScan("Job terminé avec succès. $indexedCount fichiers traités, $errorCount erreurs.");

} catch (Exception $e) {
    logScan("ERREUR FATALE : " . $e->getMessage());
    updateStatus('fatal_error', 0, '', 0, 0, 0);
}
