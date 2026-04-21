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
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

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

// Chemin vers ImageMagick
$imPath = get_binary_path('magick', 'DUPLICATOR_MAGICK_PATH');
debugLog("IM Path: $imPath");

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

// Se positionner au début (on va scanner tout le fichier)
fseek($handle, 0);

// Extraire TOUS les EMF (Signature 0x01000000)
$bufferSize = 65536;
$emfSignature = "\x01\x00\x00\x00";
$emfPositions = [];
$pos = 0;

while (!feof($handle)) {
    $chunk = fread($handle, $bufferSize);
    $lastFound = 0;
    while (($found = strpos($chunk, $emfSignature, $lastFound)) !== false) {
        $actualPos = $pos + $found;
        
        // Vérifier si c'est un vrai début d'EMF (Signature " EMF" à l'offset 40)
        fseek($handle, $actualPos + 40);
        $signature = fread($handle, 4);
        if ($signature === " EMF") {
            $emfPositions[] = $actualPos;
            debugLog("Valid EMF found at offset: $actualPos");
        } else {
            // debugLog("Ignored false positive EMF signature at offset: $actualPos");
        }
        
        $lastFound = $found + 4;
        
        // Sécurité pour ne pas saturer si le fichier est corrompu
        if (count($emfPositions) > 500) break 2;
    }
    
    if (feof($handle)) break;
    
    // Remettre le pointeur pour la lecture du prochain chunk
    fseek($handle, $pos + $bufferSize - 3);
    $pos = ftell($handle);
}

if (empty($emfPositions)) {
    debugLog("No EMF signatures found in SPL");
    fclose($handle);
    echo json_encode(['error' => 'No EMF signatures found in SPL']);
    exit;
}

// Créer le dossier de sortie (Accessible publiquement)
$outputDir = __DIR__ . '/../public/thumbnails/' . $jobId . '/';
if (is_dir($outputDir)) {
    debugLog("Clearing existing output dir: $outputDir");
    rrmdir($outputDir);
}

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
    debugLog("Created output dir: $outputDir");
}

// URL de base pour les thumbnails
$baseUrl = 'http://127.0.0.1:8001/public/thumbnails/' . $jobId . '/';

// Extraire chaque EMF vers un fichier temporaire et convertir
$generatedPages = [];
$pageSizeLimit = 50; // Limite raisonnable pour éviter de bloquer le serveur

foreach ($emfPositions as $index => $startOffset) {
    if ($index >= $pageSizeLimit) {
        debugLog("Reached page limit ($pageSizeLimit). Skipping remaining pages.");
        break;
    }

    // Déterminer la fin (soit la signature suivante, soit la fin du fichier)
    $endOffset = isset($emfPositions[$index + 1]) ? $emfPositions[$index + 1] : filesize($splFile);
    $length = $endOffset - $startOffset;

    if ($length > 2048) {
        fseek($handle, $startOffset);
        $emfData = fread($handle, $length);
        
        $tempEmf = $outputDir . "page_$index.emf";
        file_put_contents($tempEmf, $emfData);
    } else {
        debugLog("Skipping EMF at index $index: Length is too small ($length), likely metadata/header record.");
        continue;
    }
    
    $outputPng = $outputDir . "page_$index.png";
    
    // Conversion avec ImageMagick (Resolution basse 72 DPI)
    $command = sprintf(
        '"%s" -density 72 "%s" -background white -flatten "%s" 2>&1',
        $imPath,
        $tempEmf,
        $outputPng
    );
    
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($outputPng)) {
        $generatedPages[] = [
            'page' => $index,
            'path' => $outputPng,
            'size' => filesize($outputPng),
            'url' => $baseUrl . "page_$index.png"
        ];
    } else {
        debugLog("Conversion failed for page $index. Command: $command");
    }
    
    // Supprimer l'EMF temporaire
    @unlink($tempEmf);
}

fclose($handle);

if (empty($generatedPages)) {
    debugLog("No PNG files generated");
    echo json_encode(['error' => 'No PNG files generated']);
    exit;
}

debugLog("Generated " . count($generatedPages) . " PNG files");

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'page_count' => count($generatedPages),
    'pages' => $generatedPages,
    'base_url' => $baseUrl
], JSON_UNESCAPED_SLASHES);
