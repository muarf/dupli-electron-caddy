<?php
// Script de maintenance exécuté en arrière-plan
// Reçoit : chemin (argv1), récursif (argv2), jobId (argv3)

// Ignore user abort and remove time limit
ignore_user_abort(true);
set_time_limit(0);

// Définir que nous sommes en CLI pour éviter les headers ou affichages intempestifs
if (php_sapi_name() !== 'cli') {
    die("Ce script ne peut être exécuté qu'en ligne de commande.");
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';

// Arguments
$path = $argv[1] ?? '';
$recursive = ($argv[2] ?? '0') === '1';
$jobId = $argv[3] ?? '';

if (empty($path) || empty($jobId)) {
    die("Arguments manquants.\n");
}

$logDir = __DIR__ . '/../logs/indexing_status';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$statusFile = $logDir . '/' . $jobId . '.json';

function updateStatus($status, $percent, $currentFile = '', $scannedCount = 0, $indexedCount = 0, $errorCount = 0, $errorMsg = '') {
    global $statusFile, $jobId;
    $data = [
        'job_id' => $jobId,
        'status' => $status,
        'percent' => $percent,
        'current_file' => $currentFile,
        'scanned_count' => $scannedCount,
        'indexed_count' => $indexedCount,
        'error_count' => $errorCount,
        'error_msg' => $errorMsg,
        'updated_at' => time()
    ];
    file_put_contents($statusFile, json_encode($data));
}

function generatePdfThumbnail($pdfPath) {
    try {
        $baseDir = getBibliothequeDir();
        $thumbDir = $baseDir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'pdf';
        
        if (!is_dir($thumbDir)) {
            if (!mkdir($thumbDir, 0777, true)) {
                return null;
            }
        }
        
        $filename = md5(uniqid($pdfPath, true)) . '.png';
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = 'thumbnails/pdf/' . $filename;
        
        $gs = 'gs';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $gs = 'gswin64c.exe';
            if (!trim(shell_exec('where gswin64c.exe 2>NUL'))) {
                $gs = 'gswin32c.exe';
            }
        }
        
        $cmd = sprintf(
            '%s -q -dQUIET -dSAFER -dBATCH -dNOPAUSE -dNOPROMPT -sDEVICE=png16m -dMaxBitmap=500000000 -dAlignToPixels=0 -dGridFitTT=2 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -r72 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s',
            escapeshellarg($gs),
            escapeshellarg($thumbPath),
            escapeshellarg($pdfPath)
        );
        
        exec($cmd . ' 2>&1', $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($thumbPath)) {
            return $relativePath;
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

try {
    updateStatus('scanning', 0);
    
    // 1. Scanner les fichiers
    $files = scanDirectoryForLibrary($path, $recursive);
    $totalFiles = count($files);
    
    if ($totalFiles === 0) {
        updateStatus('completed', 100, '', 0, 0, 0);
        exit(0);
    }
    
    updateStatus('indexing', 0, '', $totalFiles, 0, 0);
    
    // 2. Traiter chaque fichier et l'insérer
    $pdo = pdo_connect();
    $indexedCount = 0;
    $errorCount = 0;
    
    // Préparer les requêtes
    $checkStmt = $pdo->prepare("SELECT id FROM bibliotheque_files WHERE filepath = ?");
    $insertStmt = $pdo->prepare("
        INSERT INTO bibliotheque_files 
        (filename, filepath, file_type, file_size, is_external, created_at, thumbnail_path) 
        VALUES (?, ?, ?, ?, 1, datetime('now'), ?)
    ");
    
    set_time_limit(0); // Pas de timeout
    
    foreach ($files as $index => $file) {
        $percent = round(($index / $totalFiles) * 100);
        
        // Mettre à jour le statut tous les 5 fichiers ou à la fin
        if ($index % 5 === 0 || $index === $totalFiles - 1) {
            updateStatus('indexing', $percent, basename($file['path']), $totalFiles, $indexedCount, $errorCount);
        }
        
        try {
            // Vérifier si le fichier existe déjà
            $checkStmt->execute([$file['path']]);
            if ($checkStmt->fetch()) {
                // Déjà indexé, on l'ajoute juste au compte des succès
                $indexedCount++;
                continue;
            }
            
            $thumbnailPath = null;
            if ($file['type'] === 'pdf') {
                $thumbnailPath = generatePdfThumbnail($file['path']);
            }
            
            // Insérer
            $insertStmt->execute([
                $file['filename'],
                $file['path'],
                $file['type'],
                $file['size'],
                $thumbnailPath
            ]);
            
            $indexedCount++;

            
        } catch (Exception $e) {
            $errorCount++;
            error_log("Erreur indexation fichier " . $file['path'] . " : " . $e->getMessage());
        }
    }
    
    // Terminé
    updateStatus('completed', 100, '', $totalFiles, $indexedCount, $errorCount);
    
} catch (Exception $e) {
    // Erreur fatale
    error_log("Erreur fatale job indexation $jobId : " . $e->getMessage());
    updateStatus('fatal_error', 0, '', 0, 0, 0, "Erreur fatale: " . $e->getMessage());
}
