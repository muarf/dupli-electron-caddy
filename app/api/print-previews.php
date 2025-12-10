<?php
/**
 * API pour servir les miniatures PNG des impressions
 */

header('Content-Type: application/json');

// Obtenir le chemin vers les previews
$previewsDir = getenv('APPDATA') . '\\Duplicator\\print_previews\\';

if (!is_dir($previewsDir)) {
    echo json_encode(['error' => 'Preview directory not found']);
    exit;
}

// Action: lister les previews pour un job
if (isset($_GET['job_id'])) {
    $jobId = basename($_GET['job_id']); // Sécurité
    $jobDir = $previewsDir . $jobId . '\\';
    
    if (!is_dir($jobDir)) {
        echo json_encode(['job_id' => $jobId, 'pages' => []]);
        exit;
    }
    
    $pages = [];
    $files = glob($jobDir . 'page_*.png');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $pageNum = (int)preg_replace('/[^0-9]/', '', $filename);
        $pages[] = [
            'page' => $pageNum,
            'filename' => $filename,
            'url' => '?get_preview&job_id=' . urlencode($jobId) . '&filename=' . urlencode($filename),
            'size' => filesize($file)
        ];
    }
    
    // Trier par numéro de page
    usort($pages, function($a, $b) {
        return $a['page'] - $b['page'];
    });
    
    echo json_encode([
        'job_id' => $jobId,
        'page_count' => count($pages),
        'pages' => $pages
    ]);
    exit;
}

// Action: servir une image PNG
if (isset($_GET['get_preview'])) {
    $jobId = basename($_GET['job_id']);
    $filename = basename($_GET['filename']);
    
    $filePath = $previewsDir . $jobId . '\\' . $filename;
    
    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    // Servir l'image
    header('Content-Type: image/png');
 header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=86400'); // Cache 24h
    readfile($filePath);
    exit;
}

// Action: supprimer les previews d'un ou plusieurs jobs
if (isset($_POST['delete_jobs']) && isset($_POST['job_ids'])) {
    $jobIds = json_decode($_POST['job_ids'], true);
    $deleted = 0;
    
    foreach ($jobIds as $jobId) {
        $jobId = basename($jobId);
        $jobDir = $previewsDir . $jobId . '\\';
        
        if (is_dir($jobDir)) {
            // Supprimer tous les fichiers dans le dossier
            $files = glob($jobDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            // Supprimer le dossier
            if (rmdir($jobDir)) {
                $deleted++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
        'requested' => count($jobIds)
    ]);
    exit;
}

// Action: purger toutes les previews
if (isset($_POST['purge_all'])) {
    $deleted = 0;
    $jobDirs = glob($previewsDir . '*', GLOB_ONLYDIR);
    
    foreach ($jobDirs as $jobDir) {
        // Supprimer tous les fichiers
        $files = glob($jobDir . '\\*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // Supprimer le dossier
        if (rmdir($jobDir)) {
            $deleted++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'purged' => $deleted
    ]);
    exit;
}

// Par défaut: lister tous les jobs avec previews
$jobs = [];
$jobDirs = glob($previewsDir . '*', GLOB_ONLYDIR);

foreach ($jobDirs as $jobDir) {
    $jobId = basename($jobDir);
    $pngCount = count(glob($jobDir . '\\page_*.png'));
    
    if ($pngCount > 0) {
        $jobs[] = [
            'job_id' => $jobId,
            'page_count' => $pngCount
        ];
    }
}

echo json_encode([
    'total_jobs' => count($jobs),
    'jobs' => $jobs
]);
