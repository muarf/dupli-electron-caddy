<?php
/**
 * API pour convertir les fichiers EMF depuis le spool en PNG
 * Utilisé par le module C++ pour générer les previews
 */

// Augmenter la mémoire et le temps d'exécution pour les gros fichiers
ini_set('memory_limit', '1024M');
set_time_limit(300);

header('Content-Type: application/json');

// Log debug function
function debugLog($msg) {
    if (!is_dir(__DIR__ . '/../../logs')) {
        @mkdir(__DIR__ . '/../../logs', 0777, true);
    }
    file_put_contents(__DIR__ . '/../../logs/debug_api.log', date('[H:i:s] ') . $msg . "\n", FILE_APPEND);
}

debugLog("API Called. POST: " . print_r($_POST, true));

// Chemin vers ImageMagick (Portable)
$imPath = __DIR__ . '/../imagemagick/magick.exe';
debugLog("IM Path: $imPath");

if (!file_exists($imPath)) {
    debugLog("IM not found at $imPath, using system default");
    $imPath = 'magick';
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
function readJobIdFromShd($shdPath) {
    if (!file_exists($shdPath) || filesize($shdPath) < 16) {
        return 0;
    }
    $handle = fopen($shdPath, 'rb');
    if (!$handle) return 0;
    $data = fread($handle, 16);
    fclose($handle);
    if (strlen($data) < 16) return 0;
    
    // Try offset 12 (Windows 10+)
    $jobId = unpack('V', substr($data, 12, 4))[1];
    if ($jobId > 0 && $jobId < 100000) return $jobId;
    
    // Fallback: offset 8 (older Windows)
    $jobId = unpack('V', substr($data, 8, 4))[1];
    if ($jobId > 0 && $jobId < 100000) return $jobId;
    
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
        
        debugLog("Found SHD: $baseName (size=$shdSize)");
        
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
            debugLog("  -> SHD Job ID: $shdJobId (looking for: $jobId)");
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

// Ouvrir le fichier SPL en lecture
$handle = fopen($splFile, 'rb');
if (!$handle) {
    debugLog("Failed to open SPL file: $splFile");
    echo json_encode(['error' => 'Failed to open SPL file']);
    exit;
}

// Trouver l'offset EMF (Signature 0x01000000)
// On lit par blocs pour ne pas tout charger en mémoire
$bufferSize = 8192;
$emfSignature = "\x01\x00\x00\x00";
$emfOffset = -1;
$pos = 0;

while (!feof($handle)) {
    $chunk = fread($handle, $bufferSize);
    $found = strpos($chunk, $emfSignature);
    if ($found !== false) {
        $emfOffset = $pos + $found;
        debugLog("EMF Signature found at offset: $emfOffset");
        break;
    }
    // Reculer un peu pour ne pas rater une signature à cheval sur deux blocs
    if (strlen($chunk) > 3) {
        fseek($handle, -3, SEEK_CUR);
        $pos = ftell($handle);
    } else {
        $pos += strlen($chunk);
    }
    
    // Sécurité: ne pas scanner trop loin si c'est pas au début
    if ($pos > 100000) { // Si pas trouvé dans les 100 premiers KB, probablement pas là
         break;
    }
}

if ($emfOffset === -1) {
    debugLog("EMF signature not found in SPL");
    fclose($handle);
    echo json_encode(['error' => 'EMF signature not found in SPL']);
    exit;
}

// Se positionner au début de l'EMF
fseek($handle, $emfOffset);

// Créer le dossier de sortie (Accessible publiquement)
$outputDir = __DIR__ . '/../public/thumbnails/' . $jobId . '/';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
    debugLog("Created output dir: $outputDir");
}

// URL de base pour les thumbnails
$baseUrl = 'http://127.0.0.1:8001/thumbnails/' . $jobId . '/';

// Sauvegarder l'EMF temporaire via stream
$tempEmf = $outputDir . 'temp.emf';
$outHandle = fopen($tempEmf, 'wb');
if (!$outHandle) {
    debugLog("Failed to create temp EMF file");
    fclose($handle);
    echo json_encode(['error' => 'Failed to create temp EMF file']);
    exit;
}

// Copier le reste du fichier SPL vers temp.emf
$copyResult = stream_copy_to_stream($handle, $outHandle);
fclose($handle);
fclose($outHandle);

// Log the size of the temporary EMF file for debugging
if (file_exists($tempEmf)) {
    $emfSize = filesize($tempEmf);
    debugLog("Temp EMF file size: $emfSize bytes");
}

debugLog("Copied $copyResult bytes to $tempEmf");

if ($copyResult === false || $copyResult === 0) {
    debugLog("Failed to copy EMF data (0 bytes)");
    echo json_encode(['error' => 'Empty EMF data extracted']);
    exit;
}

// Convertir avec ImageMagick (Force fond blanc et applatissement pour éviter la transparence comptée comme noir)
$outputPattern = $outputDir . 'page_%d.png';

$command = sprintf(
    '"%s" -density 300 "%s" -background white -flatten "%s" 2>&1',
    $imPath,
    $tempEmf,
    $outputDir . 'page_%d.png'
);

debugLog("Running command: $command");

$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

debugLog("Command result: $returnVar");
debugLog("Command output: " . implode("\n", $output));

// Supprimer l'EMF temporaire
unlink($tempEmf);

if ($returnVar !== 0 && empty(glob($outputDir . 'page_*.png'))) {
    debugLog("Conversion failed");
    echo json_encode([
        'error' => 'ImageMagick conversion failed',
        'return_code' => $returnVar,
        'output' => implode("\n", $output),
        'command' => $command
    ]);
    exit;
}

// Lister les PNG générés
$pngFiles = glob($outputDir . 'page_*.png');
debugLog("Generated " . count($pngFiles) . " PNG files");

if (empty($pngFiles)) {
    echo json_encode(['error' => 'No PNG files generated']);
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
usort($pages, function($a, $b) {
    return $a['page'] - $b['page'];
});

// Ajouter l'URL pour chaque page
foreach ($pages as &$page) {
    $page['url'] = $baseUrl . 'page_' . $page['page'] . '.png';
}

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'page_count' => count($pages),
    'pages' => $pages,
    'base_url' => $baseUrl
], JSON_UNESCAPED_SLASHES);
