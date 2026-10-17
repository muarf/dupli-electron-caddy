<?php
/**
 * API pour convertir un spool PostScript en PNG via Ghostscript
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/SpoolManager.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Job ID manquant']);
    exit;
}

try {
    $db = create_database_manager();
    
    // 1. Chercher les infos du job
    $job = $db->selectOne("SELECT printer_name FROM print_jobs WHERE job_id = ? ORDER BY created_at DESC", [$job_id]);
    
    if (!$job) {
        throw new Exception("Job non trouvé dans la base");
    }

    $printerName = $job['printer_name'];
    
    // 2. Trouver le fichier SPL
    $splFile = SpoolManager::findSpoolFile($job_id);
    if (!$splFile || !file_exists($splFile)) {
        throw new Exception("Fichier spool introuvable pour le job $job_id");
    }

    // 3. Dossier de sortie
    $outputDir = __DIR__ . "/../public/thumbnails/$job_id/";
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // 4. Conversion via Ghostscript
    // gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile="page_%d.png" input.spl
    $gsPath = get_ghostscript_path();
    $outputPath = $outputDir . "page_%d.png";
    
    $args = "-dNOPAUSE -dBATCH -dSAFER -sDEVICE=png16m -r150 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($outputPath) . " " . escapeshellarg($splFile);
    
    $res = run_ghostscript($args);

    if (!$res['success']) {
        throw new Exception("Erreur Ghostscript: " . $res['output']);
    }

    // 5. Lister les pages générées et filtrer la dernière si elle est vide
    $allPages = [];
    $files = scandir($outputDir);
    foreach ($files as $file) {
        if (strpos($file, 'page_') === 0 && strpos($file, '.png') !== false) {
            $allPages[] = $file;
        }
    }

    // Trier les pages numériquement
    usort($allPages, function($a, $b) {
        preg_match('/page_(\d+)/', $a, $mA);
        preg_match('/page_(\d+)/', $b, $mB);
        return (int)$mA[1] - (int)$mB[1];
    });

    $pages = [];
    $totalFound = count($allPages);
    
    foreach ($allPages as $index => $file) {
        $filePath = $outputDir . $file;
        $isLast = ($index === $totalFound - 1);
        
        // Si c'est la dernière page et qu'elle fait moins de 2KB, 
        // c'est probablement une page de garde PostScript ou une page vide générée par erreur par GS
        if ($isLast && $totalFound > 1 && filesize($filePath) < 2048) {
            @unlink($filePath);
            continue;
        }

        $pages[] = [
            'path' => "thumbnails/$job_id/$file",
            'url' => "http://127.0.0.1:8001/thumbnails/$job_id/$file"
        ];
    }

    echo json_encode([
        'success' => true,
        'job_id' => $job_id,
        'page_count' => count($pages),
        'pages' => $pages,
        'base_url' => "http://127.0.0.1:8001/thumbnails/$job_id/"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
