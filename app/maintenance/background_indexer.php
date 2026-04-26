<?php
// Script de maintenance exécuté en arrière-plan
// Reçoit : chemin/action (argv1), récursif (argv2), jobId (argv3), mode (argv4)

// Ignore user abort and remove time limit
ignore_user_abort(true);
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Définir que nous sommes en CLI pour éviter les headers ou affichages intempestifs
if (php_sapi_name() !== 'cli') {
    die("Ce script ne peut être exécuté qu'en ligne de commande.");
}

require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

// Arguments
$param = $argv[1] ?? '';
$recursive = ($argv[2] ?? '0') === '1';
$jobId = $argv[3] ?? '';
$mode = $argv[4] ?? 'index'; // 'index', 'regenerate_thumbnails'

if (empty($jobId)) {
    die("JobId manquant.\n");
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

function generateThumbnail($filePath, $type) {
    try {
        $baseDir = getBibliothequeDir();
        $thumbSubDir = 'thumbnails/' . $type;
        $thumbDir = $baseDir . DIRECTORY_SEPARATOR . $thumbSubDir;
        
        if (!is_dir($thumbDir)) {
            if (!mkdir($thumbDir, 0777, true)) {
                return null;
            }
        }
        
        $filename = md5($filePath . filemtime($filePath)) . '.png';
        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = $thumbSubDir . '/' . $filename;
        
        if (file_exists($thumbPath)) return $relativePath;

        if ($type === 'pdf') {
            $gs = get_ghostscript_path();
            // Workaround for Windows special characters
            $tmpPdf = sys_get_temp_dir() . '/gs_temp_' . uniqid() . '.pdf';
            if (!copy($filePath, $tmpPdf)) return null;

            $cmd = sprintf(
                '%s -q -dQUIET -dSAFER -dBATCH -dNOPAUSE -dNOPROMPT -sDEVICE=png16m -dMaxBitmap=500000000 -dAlignToPixels=0 -dGridFitTT=2 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -r72 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s',
                escapeshellarg($gs),
                escapeshellarg($thumbPath),
                escapeshellarg($tmpPdf)
            );
            
            exec($cmd . ' 2>&1', $output, $returnVar);
            @unlink($tmpPdf);
            
            if ($returnVar === 0 && file_exists($thumbPath)) {
                resizeImage($thumbPath, 200, 200);
                return $relativePath;
            }
        } else if ($type === 'png') {
            if (copy($filePath, $thumbPath)) {
                resizeImage($thumbPath, 200, 200);
                return $relativePath;
            }
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function resizeImage($file, $w, $h) {
    $src = null;
    $dst = null;
    try {
        if (!file_exists($file)) return;
        
        // Get dimensions
        $info = getimagesize($file);
        if (!$info) return;
        
        list($width, $height) = $info;
        
        // Check if image is extremely large to avoid memory crash even with 512MB
        // Estimated memory: width * height * 4 bytes for GD (32-bit color)
        $estimatedMemory = $width * $height * 4;
        if ($estimatedMemory > 200 * 1024 * 1024) { // > 200MB estimated
             error_log("resizeImage: Image too large ($width x $height). Skipping resize to avoid crash.");
             return;
        }

        $r = $width / $height;
        if ($w/$h > $r) {
            $newwidth = $h*$r;
            $newheight = $h;
        } else {
            $newheight = $w/$r;
            $newwidth = $w;
        }
        
        $src = imagecreatefrompng($file);
        if (!$src) return;

        $dst = imagecreatetruecolor($newwidth, $newheight);
        if (!$dst) {
            imagedestroy($src);
            return;
        }

        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        imagepng($dst, $file);
        
    } catch (Throwable $e) {
        error_log("resizeImage error: " . $e->getMessage());
    } finally {
        if ($src) imagedestroy($src);
        if ($dst) imagedestroy($dst);
    }
}

function extractPdfMetadata($path) {
    $pageCount = 0;
    $extractedText = '';
    try {
        // Ignorer les fichiers de plus de 30 Mo pour l'extraction de texte (risqué pour la RAM)
        if (filesize($path) > 30 * 1024 * 1024) {
            error_log("extractPdfMetadata: File too large (>30MB), skipping text extraction for $path");
            return ['pages' => 0, 'text' => '[Fichier trop lourd pour l\'indexation du texte]'];
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();
        $pageCount = count($pages);
        
        $maxTextLength = 500000;
        $extractedText = $pdf->getText();
        if (strlen($extractedText) > $maxTextLength) {
            $extractedText = substr($extractedText, 0, $maxTextLength) . '...';
        }
        
        unset($pdf);
        unset($pages);
        unset($parser);
    } catch (Throwable $e) {
        error_log("Error extracting PDF metadata for $path: " . $e->getMessage());
    }
    return ['pages' => $pageCount, 'text' => $extractedText];
}

try {
    $pdo = pdo_connect();

    if ($mode === 'index_text') {
        updateStatus('indexing_text', 0, 'Préparation...');
        
        $stmt = $pdo->query("SELECT id, filepath FROM bibliotheque_files WHERE file_type = 'pdf' AND (extracted_text IS NULL OR extracted_text = '')");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalFiles = count($files);
        
        if ($totalFiles === 0) {
            updateStatus('completed', 100);
            exit(0);
        }

        $indexedCount = 0;
        $errorCount = 0;
        $updStmt = $pdo->prepare("UPDATE bibliotheque_files SET page_count = ?, extracted_text = ? WHERE id = ?");

        foreach ($files as $index => $file) {
            $percent = round(($index / $totalFiles) * 100);
            updateStatus('indexing_text', $percent, basename($file['filepath']), $totalFiles, $indexedCount, $errorCount);

            $metadata = extractPdfMetadata($file['filepath']);
            $updStmt->execute([$metadata['pages'], $metadata['text'], $file['id']]);
            $indexedCount++;
            
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }
        updateStatus('completed', 100, '', $totalFiles, $indexedCount, $errorCount);
        exit(0);
    }

    if ($mode === 'regenerate_thumbnails') {
        updateStatus('indexing', 0, 'Préparation...');
        
        $stmt = $pdo->query("SELECT id, filepath, file_type FROM bibliotheque_files");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalFiles = count($files);
        
        if ($totalFiles === 0) {
            updateStatus('completed', 100);
            exit(0);
        }

        $indexedCount = 0;
        $errorCount = 0;
        $updStmt = $pdo->prepare("UPDATE bibliotheque_files SET thumbnail_path = ? WHERE id = ?");

        foreach ($files as $index => $file) {
            $percent = round(($index / $totalFiles) * 100);
            if ($index % 5 === 0 || $index === $totalFiles - 1) {
                updateStatus('indexing', $percent, basename($file['filepath']), $totalFiles, $indexedCount, $errorCount);
            }

            $thumb = generateThumbnail($file['filepath'], $file['file_type']);
            if ($thumb) {
                $updStmt->execute([$thumb, $file['id']]);
                $indexedCount++;
            } else {
                $errorCount++;
            }
        }
        updateStatus('completed', 100, '', $totalFiles, $indexedCount, $errorCount);
    } 
    else {
        // Mode index / rescan
        $paths = explode('|', $param);
        $allFiles = [];
        
        updateStatus('scanning', 0);
        foreach ($paths as $path) {
            if (empty($path)) continue;
            $found = scanDirectoryForLibrary($path, $recursive);
            $allFiles = array_merge($allFiles, $found);
        }
        
        $totalFiles = count($allFiles);
        if ($totalFiles === 0) {
            updateStatus('completed', 100);
            exit(0);
        }

        updateStatus('indexing', 0, '', $totalFiles, 0, 0);
        
        $indexedCount = 0;
        $errorCount = 0;
        
        $checkStmt = $pdo->prepare("SELECT id FROM bibliotheque_files WHERE filepath = ?");
        $insertStmt = $pdo->prepare("
            INSERT INTO bibliotheque_files 
            (filename, filepath, file_type, file_size, is_external, created_at, thumbnail_path, source_directory) 
            VALUES (?, ?, ?, ?, 1, datetime('now'), ?, ?)
        ");
        
        foreach ($allFiles as $index => $file) {
            $percent = round(($index / $totalFiles) * 100);
            if ($index % 5 === 0 || $index === $totalFiles - 1) {
                updateStatus('indexing', $percent, basename($file['path']), $totalFiles, $indexedCount, $errorCount);
            }
            
            try {
                $checkStmt->execute([$file['path']]);
                if ($checkStmt->fetch()) {
                    $indexedCount++;
                    continue;
                }
                
                $thumb = generateThumbnail($file['path'], $file['type']);
                $insertStmt->execute([
                    $file['filename'],
                    $file['path'],
                    $file['type'],
                    $file['size'],
                    $thumb,
                    dirname($file['path'])
                ]);
                $indexedCount++;
            } catch (Exception $e) {
                $errorCount++;
            }
        }
        updateStatus('completed', 100, '', $totalFiles, $indexedCount, $errorCount);
    }

} catch (Exception $e) {
    error_log("Erreur fatale job bibliotheque $jobId : " . $e->getMessage());
    updateStatus('fatal_error', 0, '', 0, 0, 0, "Erreur fatale: " . $e->getMessage());
}
