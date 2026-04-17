<?php
/**
 * API pour convertir les fichiers XPS depuis le spool en PNG
 * Utilisé par le module C++ pour générer les previews XPS
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
    @file_put_contents($logFile, "[$timestamp] [XPS] $msg\n", FILE_APPEND);
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

// Chemin vers l'exécutable GhostXPS
$gsPath = getenv('DUPLICATOR_GXPS_PATH') ?: realpath(__DIR__ . '/../../ghostscript/gxpswin64.exe');

if (!$gsPath || (!file_exists($gsPath) && $gsPath === realpath(__DIR__ . '/../../ghostscript/gxpswin64.exe'))) {
    debugLog("FATAL: GhostXPS executable not found");
    echo json_encode(['error' => 'GhostXPS not found']);
    exit;
}

debugLog("GhostXPS Path: $gsPath");

// Récupérer le job ID
$jobId = isset($_REQUEST['job_id']) ? intval($_REQUEST['job_id']) : 0;
debugLog("Job ID: $jobId");

if ($jobId == 0) {
    debugLog("Invalid Job ID");
    echo json_encode(['error' => 'Invalid job_id']);
    exit;
}

// Chemin vers le dossier spool
$spoolDir = getenv('DUPLICATOR_SPOOL_PATH') ?: 'C:\\Windows\\System32\\spool\\PRINTERS\\';

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

// Trouver le fichier SPL correspondant au Job ID
$splFile = null;

// 1. Chercher d'abord dans le spool standard
$files = glob($spoolDir . '*.SPL');
foreach ($files as $file) {
    $baseName = basename($file, '.SPL');
    $shdFile = $spoolDir . $baseName . '.SHD';

    if (file_exists($shdFile)) {
        $foundJobId = readJobIdFromShd($shdFile);
        if ($foundJobId == $jobId) {
            $splFile = $file;
            debugLog("Found SPL file (standard): $splFile");
            break;
        }
    }
}

// 2. Si pas trouvé, chercher dans File Pooling
if (!$splFile) {
    $filePoolDir = $spoolDir . 'FilePool\\';
    if (is_dir($filePoolDir)) {
        $poolFiles = glob($filePoolDir . '*.SPL');
        foreach ($poolFiles as $file) {
            $baseName = basename($file, '.SPL');
            $shdFile = $filePoolDir . $baseName . '.SHD';

            if (file_exists($shdFile)) {
                $foundJobId = readJobIdFromShd($shdFile);
                if ($foundJobId == $jobId) {
                    $splFile = $file;
                    debugLog("Found SPL file (FilePool): $splFile");
                    break;
                }
            }
        }
    }
}

if (!$splFile || !file_exists($splFile)) {
    debugLog("SPL file not found for Job ID $jobId");
    echo json_encode(['error' => 'SPL file not found', 'job_id' => $jobId]);
    exit;
}

debugLog("Using SPL file: $splFile (size: " . filesize($splFile) . " bytes)");

// Shadow Copy Strategy:
// The Windows Spooler locks the file aggressively and deletes it quickly.
// Instead of waiting on the original file, we'll try to copy what we can to a temp file
// and keep appending until we have a valid ZIP structure.

// Créer le répertoire temporaire plus tôt
$tempDir = sys_get_temp_dir();
$shadowFile = $tempDir . DIRECTORY_SEPARATOR . 'shadow_' . $jobId . '.spl';
debugLog("Shadow Copy Strategy: Copying $splFile to $shadowFile");

$maxWait = 30; // Increased to 30 seconds for large files
$startTime = time();
$lastSize = 0;
$stableCount = 0;

while (time() - $startTime < $maxWait) {
    // Try to copy current content of SPL to shadow file
    // Suppress warnings as fopen might fail if locked
    $sourceHandle = @fopen($splFile, 'rb');
    
    if ($sourceHandle) {
        $destHandle = fopen($shadowFile, 'wb'); // Overwrite each time
        if ($destHandle) {
            stream_copy_to_stream($sourceHandle, $destHandle);
            fclose($destHandle);
        }
        fclose($sourceHandle);
    } else {
        // Source is locked. If we have a shadow file, checking it won't help if it was incomplete before.
        // Unless we couldn't create it at all yet.
        debugLog("Source SPL locked. Waiting...");
    }

    // Check if shadow file exists and is valid
    if (file_exists($shadowFile)) {
        clearstatcache(true, $shadowFile);
        $currentSize = filesize($shadowFile);
        
        // Check for EOCD in shadow file
        // Look in last 4KB (standard max comment length in ZIP is 64KB but usually EOCD is at very end)
        $fh = fopen($shadowFile, 'rb');
        fseek($fh, 0, SEEK_END);
        $fsize = ftell($fh);
        
        if ($fsize > 22) {
            $seekSize = min($fsize, 4096);
            fseek($fh, -$seekSize, SEEK_END); 
            $tail = fread($fh, $seekSize);
            
            if (strpos($tail, "PK\x05\x06") !== false) {
                debugLog("Valid ZIP EOCD signature found in shadow file ($currentSize bytes). Success!");
                fclose($fh);
                // Use the shadow file as the source for extraction
                $splFile = $shadowFile; 
                break;
            }
        }
        fclose($fh);
        
        debugLog("Shadow file incomplete ($currentSize bytes). Retrying...");
    }
    
    usleep(250000); // Wait 250ms
}

if (!file_exists($shadowFile)) {
     debugLog("Failed to create shadow file.");
     echo json_encode(['error' => 'Failed to access SPL file']);
     exit;
}

// Proceed with extraction using the shadow file (or original if copy failed but somehow exists)
// Lire l'en-tête pour vérifier la signature XPS (PK\x03\x04)
$fh = fopen($splFile, 'rb');
if (!$fh) {
    debugLog("Failed to open SPL file");
    echo json_encode(['error' => 'Failed to open SPL file']);
    exit;
}

$header = fread($fh, 64 * 1024); // Read 64KB for header analysis
fclose($fh);

$isXps = (substr($header, 0, 4) === "PK\x03\x04");
$offset = 0;

if (!$isXps) {
    // Search for XPS signature in header
    $pkPos = strpos($header, "PK\x03\x04");
    if ($pkPos !== false) {
        $offset = $pkPos;
        $isXps = true;
        debugLog("Found XPS signature at offset $offset");
    } else {
        debugLog("No XPS signature found in file");
        echo json_encode(['error' => 'Not an XPS file']);
        exit;
    }
} else {
    debugLog("XPS signature found at offset 0");
}

// Créer un fichier temporaire avec les données XPS propres
// Créer un fichier temporaire avec les données XPS propres
$tempFile = $tempDir . DIRECTORY_SEPARATOR . 'xps_job_' . $jobId . '.xps';

debugLog("Extracting XPS data to temporary file: $tempFile");

// Stream extraction to avoid memory issues with large files
$fhIn = fopen($splFile, 'rb');
$fhOut = fopen($tempFile, 'wb');

if (!$fhIn || !$fhOut) {
    debugLog("Failed to create temporary file");
    echo json_encode(['error' => 'Failed to create temporary file']);
    exit;
}

// Skip to offset
fseek($fhIn, $offset);

// Copy remaining data in chunks
$chunkSize = 8192;
while (!feof($fhIn)) {
    $chunk = fread($fhIn, $chunkSize);
    fwrite($fhOut, $chunk);
}

fclose($fhIn);
fclose($fhOut);

debugLog("Temporary XPS file created (" . filesize($tempFile) . " bytes)");

// Dossier de sortie pour les PNGs
$thumbnailsDir = __DIR__ . '/../public/thumbnails';
if (!is_dir($thumbnailsDir)) {
    mkdir($thumbnailsDir, 0777, true);
}
$outputDir = realpath($thumbnailsDir) . DIRECTORY_SEPARATOR . $jobId . DIRECTORY_SEPARATOR;
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Nettoyer les anciens PNGs
$oldPngs = glob($outputDir . '*.png');
foreach ($oldPngs as $oldPng) {
    unlink($oldPng);
}

$outputImage = $outputDir . 'page_%d.png';

// Construire la commande GhostXPS
$command = sprintf(
    '"%s" -dNOPAUSE -dBATCH -dSAFER -dQUIET -sDEVICE=png16m -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile="%s" "%s" 2>&1',
    $gsPath,
    $outputImage,
    $tempFile
);

debugLog("Command: $command");

// Execute command using exec() instead of proc_open (proc_open fails in PHP-FPM context)
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

$stdout = implode("\n", $output);
debugLog("Command output: " . $stdout);
debugLog("Exit code: $exitCode");

// Renumber pages to start at 0 (consistent with ImageMagick/EMF output usually expected by frontend)
// Ghostscript outputs page_1.png, page_2.png... we want page_0.png, page_1.png...
$pngFiles = glob($outputDir . 'page_*.png');
natsort($pngFiles); // Ensure 1, 2, 10 order

foreach ($pngFiles as $file) {
    if (preg_match('/page_(\d+)\.png$/', $file, $matches)) {
        $oldIndex = intval($matches[1]);
        $newIndex = $oldIndex - 1;
        if ($newIndex >= 0) {
            $newFile = str_replace("page_{$oldIndex}.png", "page_{$newIndex}.png", $file);
            rename($file, $newFile);
        }
    }
}

// Vérifier les PNGs générés (après renommage)
$pngFiles = glob($outputDir . 'page_*.png');
debugLog("Generated " . count($pngFiles) . " PNG files (renumbered 0-based)");

if (count($pngFiles) == 0) {
    debugLog("Conversion failed (No PNGs)");
    echo json_encode([
        'error' => 'Conversion failed',
        'output' => $stdout
    ]);
    exit;
}

// Nettoyer le fichier temporaire (et shadow)
if (file_exists($tempFile)) {
    unlink($tempFile);
    debugLog("Deleted temporary file: $tempFile");
}
if (isset($shadowFile) && file_exists($shadowFile)) {
    unlink($shadowFile);
    debugLog("Deleted shadow file: $shadowFile");
}

// Construire le résultat JSON
$pages = [];
foreach ($pngFiles as $pngFile) {
    $pageNum = intval(preg_replace('/[^0-9]/', '', basename($pngFile)));
    // Note: pageNum is now 0-based from the filename (page_0.png -> 0)
    
    // Path should be relative to public root: thumbnails/JOBID/page_X.png
    $relativePath = 'thumbnails/' . $jobId . '/' . basename($pngFile);
    
    $pages[] = [
        'page' => $pageNum + 1, // Display page is usually 1-based in UI
        'path' => $relativePath,
        'size' => filesize($pngFile)
    ];
}

// Trier par numéro de page
usort($pages, function ($a, $b) {
    return $a['page'] - $b['page'];
});

debugLog("Success: " . count($pages) . " pages converted");

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'pages' => $pages,
    'total_pages' => count($pages)
]);
