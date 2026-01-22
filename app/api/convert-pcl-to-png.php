<?php
/**
 * API pour convertir les fichiers PCL/RAW depuis le spool en PNG
 * Utilisé par le module C++ pour générer les previews quand EMF échoue
 */

// Augmenter la mémoire et le temps d'exécution
ini_set('memory_limit', '1024M');
set_time_limit(300);

header('Content-Type: application/json');

// Log debug function
function debugLog($msg)
{
    $logFile = __DIR__ . '/../../logs/debug_api.log';
    $timestamp = date('H:i:s');
    @file_put_contents($logFile, "[$timestamp] [PCL] $msg\n", FILE_APPEND);
}

debugLog("API Called. REQUEST: " . print_r($_REQUEST, true));

// Helper function to recursively delete a directory
function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . DIRECTORY_SEPARATOR . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}

// Chemin vers l'exécutable GhostPCL (installé manuellement)
$gsPath = __DIR__ . '/../../ghostscript/gpcl6win64.exe';

if (!file_exists($gsPath)) {
    // Fallback si GhostPCL n'est pas trouvé (pour dev ou si non installé)
    debugLog("GhostPCL non trouvé à $gsPath, essai avec gswin64c.exe (risque d'échec sur PCL)");
    $gsPath = __DIR__ . '/../../ghostscript/gswin64c.exe';
}

if (!file_exists($gsPath)) {
    debugLog("FATAL: Ghostscript executable not found at $gsPath");
    // If we can't find any Ghostscript executable, we should exit or return an error.
    // For now, let's try a system default as a last resort, but log the fatal error.
    $gsPath = 'gswin64c'; // Fallback to system default if nothing else found
}

debugLog("GS Path: $gsPath");

if (!file_exists($gsPath) && $gsPath !== 'gswin64c') { // Only check if it's not the system default
    debugLog("Ghostscript not found at $gsPath, using system default");
    $gsPath = 'gswin64c';
}

// Récupérer le job ID
$jobId = isset($_REQUEST['job_id']) ? intval($_REQUEST['job_id']) : 0;
debugLog("Job ID: $jobId");

if ($jobId == 0) {
    debugLog("Invalid Job ID");
    echo json_encode(['error' => 'Invalid job_id']);
    exit;
}

// Chemin vers le dossier spool
$spoolDir = 'C:\\Windows\\System32\\spool\\PRINTERS\\';

// Fonction pour lire le Job ID depuis un fichier SHD
function readJobIdFromShd($shdPath)
{
    if (!file_exists($shdPath) || filesize($shdPath) < 16) {
        return 0;
    }
    $handle = fopen($shdPath, 'rb');
    if (!$handle)
        return 0;
    $data = fread($handle, 16);
    fclose($handle);
    if (strlen($data) < 16)
        return 0;

    // Try offset 12 (Windows 10+)
    $jobId = unpack('V', substr($data, 12, 4))[1];
    if ($jobId > 0 && $jobId < 100000)
        return $jobId;

    // Fallback: offset 8 (older Windows)
    $jobId = unpack('V', substr($data, 8, 4))[1];
    if ($jobId > 0 && $jobId < 100000)
        return $jobId;

    return 0;
}

// Trouver le fichier SPL - supports standard naming AND File Pooling
$splFile = null;

// Step 1: Try standard naming (00105.SPL)
$standardName = sprintf('%s%05d.SPL', $spoolDir, $jobId);
if (file_exists($standardName)) {
    $splFile = $standardName;
    debugLog("Found SPL via standard naming: $splFile");
} else {
    // Step 2: Scan SHD files
    debugLog("Standard SPL not found, scanning SHD files...");
    $shdFiles = glob($spoolDir . '*.SHD');
    $mostRecentFpSpl = null;
    $mostRecentTime = 0;

    foreach ($shdFiles as $shdFile) {
        $shdSize = filesize($shdFile);
        $baseName = pathinfo($shdFile, PATHINFO_FILENAME);
        $correspondingSpl = $spoolDir . $baseName . '.SPL';

        // Track FP files for fallback (regardless of SHD content)
        if (strpos($baseName, 'FP') === 0 && file_exists($correspondingSpl)) {
            $mtime = filemtime($correspondingSpl);
            if ($mtime > $mostRecentTime) {
                $mostRecentTime = $mtime;
                $mostRecentFpSpl = $correspondingSpl;
            }
        }

        if ($shdSize > 0) {
            // SHD has content - try to read job ID
            $shdJobId = readJobIdFromShd($shdFile);
            if ($shdJobId == $jobId && file_exists($correspondingSpl)) {
                $splFile = $correspondingSpl;
                debugLog("Found SPL via SHD mapping: $splFile (SHD Job ID: $shdJobId)");
                break;
            }
        }
    }

    // Step 3: Fallback to most recent FP file
    if (!$splFile && $mostRecentFpSpl) {
        $splFile = $mostRecentFpSpl;
        debugLog("Using most recent FP SPL as fallback: $splFile");
    }
}

// Verify we found a SPL file
if (!$splFile || !file_exists($splFile)) {
    debugLog("SPL file not found for Job $jobId");
    echo json_encode(['error' => 'SPL file not found', 'job_id' => $jobId]);
    exit;
}

// LOG HEADER (HEX) to identify format
$headerHandle = @fopen($splFile, 'rb');
$isPcl = false;
if ($headerHandle) {
    $headerData = fread($headerHandle, 64);
    fclose($headerHandle);
    $hexHeader = bin2hex($headerData);
    debugLog("SPL Header (Hex): " . $hexHeader);
    debugLog("SPL Header (Text): " . addcslashes($headerData, "\0..\37!@\177..\377"));
    
    // Check for PJL or PCL signatures
    if (strpos($headerData, "\x1B%-12345X") !== false || strpos($headerData, "\x1BE") !== false || strpos($headerData, "\x1B&") !== false) {
        $isPcl = true;
    }
}

if (!$isPcl) {
    debugLog("SAFEGUARD: Non-PCL data detected in convert-pcl-to-png.php. Aborting to avoid GhostPCL loop.");
    echo json_encode(['error' => 'Not a valid PCL file', 'job_id' => $jobId]);
    exit;
}

// Créer le dossier de sortie (Accessible publiquement)
$outputDir = __DIR__ . '/../public/thumbnails/' . $jobId . '/';
if (is_dir($outputDir)) {
    // Nettoyer si existe déjà (peut-être une tentative précédente)
    rrmdir($outputDir);
}

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
    debugLog("Created output dir: $outputDir");
}

// URL de base pour les thumbnails
$baseUrl = 'http://127.0.0.1:8001/thumbnails/' . $jobId . '/';

// Conversion avec Ghostscript
// On utilise le fichier SPL directement. Ghostscript est assez intelligent pour ignorer les en-têtes RAW/PJL souvent.

$realGsPath = realpath($gsPath);
$realSplFile = realpath($splFile);
$outputImage = $outputDir . 'page_%d.png';

if (!$realGsPath) {
    debugLog("ERROR: Could not resolve absolute path for GhostPCL: $gsPath");
}

$command = sprintf(
    '"%s" -dNOPAUSE -dBATCH -dSAFER -dQUIET -sDEVICE=png16m -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile="%s" "%s" 2>&1',
    $realGsPath ?: $gsPath,
    $outputImage,
    $realSplFile ?: $splFile
);

debugLog("Running command: $command");

$output = [];
$returnVar = 0;

// Utiliser proc_open avec timeout pour éviter de bloquer l'app
$timeout = 30; // 30 secondes max
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

// Sur Windows bypass_shell => true est souvent préférable quand la commande est déjà citée
$process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

if (is_resource($process)) {
    // Fermer stdin
    fclose($pipes[0]);
    
    // Lire stdout de manière non-bloquante avec timeout
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    
    $startTime = time();
    $stdout = '';
    $stderr = '';
    $timedOut = false;
    
    while (true) {
        $status = proc_get_status($process);
        
        // Lire les sorties
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        
        // Vérifier si terminé
        if (!$status['running']) {
            $returnVar = $status['exitcode'];
            break;
        }
        
        // Vérifier timeout
        if ((time() - $startTime) > $timeout) {
            debugLog("TIMEOUT: Killing process after {$timeout}s");
            
            // Tuer le processus sur Windows
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $pid = $status['pid'];
                // Tuer le processus et ses enfants
                exec("taskkill /F /T /PID $pid 2>&1", $killOutput, $killResult);
                debugLog("Kill result: $killResult - " . implode(" ", $killOutput));
            } else {
                proc_terminate($process, 9);
            }
            
            $timedOut = true;
            $returnVar = -1;
            break;
        }
        
        usleep(100000); // 100ms
    }
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    $output = array_merge(explode("\n", $stdout), explode("\n", $stderr));
    
    if ($timedOut) {
        debugLog("Process timed out after {$timeout} seconds");
        echo json_encode(['error' => 'Conversion timeout', 'timeout' => $timeout]);
        exit;
    }
} else {
    debugLog("Failed to start process");
    $returnVar = -1;
}

debugLog("Command result: $returnVar");
debugLog("Command output: " . implode("\n", $output));

// Vérifier si des fichiers ont été créés
$pngFiles = glob($outputDir . 'page_*.png');
debugLog("Generated " . count($pngFiles) . " PNG files");

if (empty($pngFiles)) {
    // Si échec, peut-être que Ghostscript n'aime pas l'en-tête SPL.
    // Tentative 2: Copier sans les premiers octets (header SPL simple)?
    // Non, c'est risqué sans savoir.
    // Mais on peut essayer de voir si c'est du PDF encapsulé ou autre.
    // Pour l'instant, on rapporte l'erreur.

    debugLog("Conversion failed (No PNGs)");
    echo json_encode([
        'error' => 'Ghostscript conversion failed (No PNGs generated)',
        'return_code' => $returnVar,
        'output' => implode("\n", $output),
        'command' => $command
    ]);
    exit;
}

// Retourner les chemins des PNG
$pages = [];
foreach ($pngFiles as $file) {
    $filename = basename($file);
    preg_match('/page_(\d+)\.png/', $filename, $matches);
    $pageNum = isset($matches[1]) ? intval($matches[1]) : 0;

    $pages[] = [
        'page' => $pageNum,
        'path' => $file,
        'size' => filesize($file)
    ];
}

// Trier par numéro de page
usort($pages, function ($a, $b) {
    return $a['page'] - $b['page'];
});

// Ajouter l'URL pour chaque page
foreach ($pages as &$page) {
    // Ghostscript page numbers start at 1 usually, ensure 0-based index if needed or keep as is.
    // filename is page_1.png, page_2.png etc.
    // baseUrl expects page_X.png
    $page['url'] = $baseUrl . 'page_' . $page['page'] . '.png';
}

// Fallback thumbnail (page 1)
// If page_1.png exists, make a copy as page_0.png if needed by frontend, 
// OR just rely on frontend using the list. 
// Old EMF logic generated page_%d.png starting at 0 usually (ImageMagick). 
// Ghostscript usually starts at 1. 
// Let's normalize to 0-based for consistency with EMF script if that one produced 0-based.
// Checking convert-emf-to-png.php... it used ImageMagick %d. IM starts at 0 for multi-page images usually.
// GS starts at 1. 
// Let's rename them to be 0-based to avoid confusion? 
// Or just let the array dictate. 
// Frontend likely expects page_0.png for the main thumb.

$firstPage = $outputDir . 'page_1.png';
$zeroPage = $outputDir . 'page_0.png';

if (file_exists($firstPage) && !file_exists($zeroPage)) {
    copy($firstPage, $zeroPage);
    // Add page 0 to list if not present?
    // Actually, let's just make sure page_0 exists for the thumbnail URL default.
}

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'page_count' => count($pages),
    'pages' => $pages,
    'base_url' => $baseUrl
], JSON_UNESCAPED_SLASHES);
