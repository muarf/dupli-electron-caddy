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
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

debugLog("API Called. REQUEST: " . print_r($_REQUEST, true));

// #region agent log
function __agentNdjson(string $hypothesisId, string $message, array $data = [], string $runId = 'pre'): void
{
    try {
        $payload = [
            'sessionId' => '8593f8',
            'runId' => $runId,
            'hypothesisId' => $hypothesisId,
            'location' => 'app/api/convert-pcl-to-png.php',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        // Workspace root from app/api/ -> ../../
        $logPath = __DIR__ . '/../../debug-8593f8.log';
        @file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    } catch (Throwable $e) {
        // ignore
    }
}
// #endregion

// Helper: find a binary signature offset in a file (limited scan)
function __findSigOffset(string $filePath, string $needle, int $maxBytes = 10485760): int
{
    $h = @fopen($filePath, 'rb');
    if (!$h) return -1;
    $chunkSize = 65536;
    $overlap = max(0, strlen($needle) - 1);
    $offset = 0;
    $prev = '';
    while (!feof($h) && $offset < $maxBytes) {
        $readLen = min($chunkSize, $maxBytes - $offset);
        $buf = fread($h, $readLen);
        if ($buf === false || $buf === '') break;
        $hay = $prev . $buf;
        $pos = strpos($hay, $needle);
        if ($pos !== false) {
            fclose($h);
            return max(0, ($offset - strlen($prev)) + $pos);
        }
        $prev = substr($hay, -$overlap);
        $offset += strlen($buf);
    }
    fclose($h);
    return -1;
}

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

// Chemin vers l'exécutable GhostPCL
$gpclPath = get_binary_path('gpcl6', 'DUPLICATOR_GPCL_PATH');
$ghostscriptPath = get_ghostscript_path();

__agentNdjson('B', 'convert_pcl_to_png: start', [
    'job_id' => $_REQUEST['job_id'] ?? null,
    'gpcl6_path' => $gpclPath,
    'gs_path' => $ghostscriptPath,
]);

if (!$gpclPath) {
    debugLog("FATAL: GhostPCL executable not found");
    __agentNdjson('B', 'FATAL: gpcl6 introuvable', []);
    echo json_encode(['error' => 'GhostPCL not found']);
    exit;
}

debugLog("GhostPCL Path: $gpclPath");
debugLog("Ghostscript Path: $ghostscriptPath");

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
    __agentNdjson('B', 'SPL introuvable', ['jobId' => $jobId, 'spoolDir' => $spoolDir]);
    echo json_encode(['error' => 'SPL file not found', 'job_id' => $jobId]);
    exit;
}

// LOG HEADER (HEX) to identify format
$headerHandle = @fopen($splFile, 'rb');
$isPcl = false;
$isPostScript = false;
$psOffset = -1;
if ($headerHandle) {
    $headerData = fread($headerHandle, 65536); // Read 64KB to find signatures deeper in file
    fclose($headerHandle);
    $hexHeader = bin2hex(substr($headerData, 0, 64));
    debugLog("SPL Header (Hex snippet): " . $hexHeader);
    __agentNdjson('B', 'Header sniff', [
        'jobId' => $jobId,
        'hex64' => $hexHeader,
        'has_ps_sig' => (strpos($headerData, "%!PS") !== false) || (strpos($headerData, "%!PS-Adobe") !== false) || (stripos($headerData, "PostScript") !== false),
        'has_pcl_sig' => (strpos($headerData, "\x1B%-12345X") !== false) || (strpos($headerData, "\x1BE") !== false) || (strpos($headerData, "\x1B&") !== false) || (stripos($headerData, "hp-PCL XL") !== false),
    ]);
    
    // Check for PCL XL signature (HP's binary PCL) - common in Kyocera
    if (stripos($headerData, "hp-PCL XL") !== false) {
        $isPcl = true;
    }
    // Check for PJL or PCL signatures
    elseif (strpos($headerData, "\x1B%-12345X") !== false || strpos($headerData, "\x1BE") !== false || strpos($headerData, "\x1B&") !== false) {
        $isPcl = true;
    }
}

if (!$isPcl) {
    // Detect PostScript (common for "... PS" drivers)
    if ((strpos($headerData, "%!PS") !== false) || (stripos($headerData, "PostScript") !== false)) {
        $isPostScript = true;
        $psOffset = strpos($headerData, "%!PS");
        if ($psOffset === false) $psOffset = strpos($headerData, "%!PS-Adobe");
        if ($psOffset === false) $psOffset = stripos($headerData, "PostScript");
        $psOffset = ($psOffset === false) ? -1 : (int)$psOffset;
        debugLog("PostScript signature detected in header. Will use Ghostscript instead of GhostPCL.");
        __agentNdjson('B', 'PostScript détecté dans header (fallback gs)', ['jobId' => $jobId, 'offset' => $psOffset]);
    } else {
        // Some drivers prepend a binary header (e.g., UTF-16 path metadata). Search deeper in file.
        $found = __findSigOffset($splFile, "%!PS", 10485760); // scan up to 10MB
        if ($found < 0) $found = __findSigOffset($splFile, "%!PS-Adobe", 10485760);
        if ($found >= 0) {
            $isPostScript = true;
            $psOffset = $found;
            debugLog("PostScript signature detected deeper in file at offset $psOffset. Will extract and use Ghostscript.");
            __agentNdjson('B', 'PostScript détecté en profondeur (fallback gs)', ['jobId' => $jobId, 'offset' => $psOffset]);
        }
    }

    debugLog("Checking for Kyocera specific signature (!R!)...");
    if (strpos($headerData, "!R!") !== false) {
        $isPcl = true;
        debugLog("Kyocera signature found. Enabling PCL mode.");
    } else {
        if (!$isPostScript) {
            debugLog("SAFEGUARD: Non-PCL and no PostScript signature detected (first 5MB). Aborting to avoid converter loop.");
            __agentNdjson('B', 'SAFEGUARD: non-PCL non-PS -> abort', ['jobId' => $jobId]);
            echo json_encode(['error' => 'Not a valid PCL file', 'job_id' => $jobId]);
            exit;
        }
    }
}

// Special handling for PostScript/Kyocera or custom offsets
$fileToConvert = $splFile;
$offset = 0;

// Auto-detect offset to align with PCL XL stream start
if ($isPcl) {
    // Strategy: Search for the ") HP-PCL XL" signature that Kyocera uses
    // This signature appears after the !R!SIR2;EXIT; prefix and PJL commands
    
    $pclxlSig = strpos($headerData, ") HP-PCL XL");
    if ($pclxlSig !== false) {
        $offset = $pclxlSig;
        debugLog("Found ) HP-PCL XL signature at offset $offset.");
    } else {
        // Fallback 1: Try case-insensitive search
        $pclxlSigLower = stripos($headerData, ") hp-pcl xl");
        if ($pclxlSigLower !== false) {
            $offset = $pclxlSigLower;
            debugLog("Found ) hp-pcl xl signature (case-insensitive) at offset $offset.");
        } else {
            // Fallback 2: Look for first ESC sequence (standard PCL)
            $firstEsc = strpos($headerData, "\x1B");
            if ($firstEsc !== false && $firstEsc > 0) {
                $offset = $firstEsc;
                debugLog("Auto-detected PCL start at first ESC sequence, offset $offset.");
            }
        }
    }
}

if ($offset > 0) {
    debugLog("Extracting data from offset $offset using streaming...");
    $tempFile = __DIR__ . '/../../logs/temp_job_' . $jobId . '.pcl';
    
    $src = @fopen($splFile, 'rb');
    $dst = @fopen($tempFile, 'wb');
    
    if ($src && $dst) {
        fseek($src, $offset);
        while (!feof($src)) {
            $chunk = fread($src, 8192); // Read 8KB at a time
            if ($chunk === false) break;
            fwrite($dst, $chunk);
        }
        fclose($src);
        fclose($dst);
        $fileToConvert = $tempFile;
        debugLog("Created temporary file for conversion via streaming: $tempFile");
    } else {
        if ($src) fclose($src);
        if ($dst) fclose($dst);
        debugLog("ERROR: Could not open files for streaming extraction");
    }
}

// If PostScript detected deeper, extract clean PS stream starting at %!PS
if ($isPostScript && !$isPcl && $psOffset > 0) {
    debugLog("Extracting PostScript stream from offset $psOffset using streaming...");
    $tempPs = __DIR__ . '/../../logs/temp_job_' . $jobId . '.ps';
    $src = @fopen($splFile, 'rb');
    $dst = @fopen($tempPs, 'wb');
    if ($src && $dst) {
        fseek($src, $psOffset);
        while (!feof($src)) {
            $chunk = fread($src, 8192);
            if ($chunk === false) break;
            fwrite($dst, $chunk);
        }
        fclose($src);
        fclose($dst);
        $fileToConvert = $tempPs;
        debugLog("Created temporary PS file for conversion: $tempPs");
        __agentNdjson('B', 'PS temp créé', ['jobId' => $jobId, 'temp' => $tempPs]);
    } else {
        if ($src) fclose($src);
        if ($dst) fclose($dst);
        debugLog("ERROR: Could not open files for PostScript extraction");
        __agentNdjson('B', 'ERREUR extraction PS', ['jobId' => $jobId]);
    }
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

$realSplFile = realpath($fileToConvert);
$outputImage = $outputDir . 'page_%d.png';

// Choisir le convertisseur:
// - PCL: GhostPCL (gpcl6)
// - PostScript: Ghostscript (gs/gswin64c)
$converterPath = $gpclPath;
$converterKind = 'gpcl6';
if ($isPostScript && !$isPcl) {
    $converterPath = $ghostscriptPath;
    $converterKind = 'ghostscript';
}

$realGsPath = realpath($converterPath);
if (!$realGsPath) {
    debugLog("ERROR: Could not resolve absolute path for converter: $converterPath");
}
__agentNdjson('B', 'Converter selected', [
    'jobId' => $jobId,
    'converterKind' => $converterKind,
    'converterPath' => $converterPath,
    'realConverterPath' => $realGsPath ?: null,
    'isPcl' => $isPcl,
    'isPostScript' => $isPostScript,
]);

$command = escapeshellarg($realGsPath ?: $converterPath) . " -dNOPAUSE -dBATCH -dSAFER -dQUIET -sDEVICE=png16m -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($outputImage) . " " . escapeshellarg($realSplFile ?: $splFile) . " 2>&1";

debugLog("Running command: $command");

$output = [];
$returnVar = 0;

// Utiliser proc_open avec timeout pour éviter de bloquer l'app
$timeout = 120; // 120 secondes max pour les gros jobs
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
    
    // Clean up temp file if created
    if ($fileToConvert !== $splFile && file_exists($fileToConvert)) {
        @unlink($fileToConvert);
        debugLog("Deleted temporary file: $fileToConvert");
    }
    
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

// Renommer les pages pour commencer à 0 (cohérent avec EMF/XPS)
// Ghostscript sort page_1.png, page_2.png... on veut page_0.png, page_1.png...
$pngFiles = glob($outputDir . 'page_*.png');
natsort($pngFiles);

foreach ($pngFiles as $file) {
    if (preg_match('/page_(\d+)\.png$/', $file, $matches)) {
        $oldIndex = intval($matches[1]);
        $newIndex = $oldIndex - 1;
        if ($newIndex >= 0) {
            $newFile = str_replace("page_$oldIndex.png", "page_$newIndex.png", $file);
            rename($file, $newFile);
        }
    }
}

// Rafraîchir la liste après renommage
$pngFiles = glob($outputDir . 'page_*.png');
debugLog("Generated " . count($pngFiles) . " PNG files (renumbered 0-based)");

if (empty($pngFiles)) {
    debugLog("No PNG files generated after renumbering");
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
        'size' => filesize($file),
        'url' => $baseUrl . "page_$pageNum.png"
    ];
}

// Trier par numéro de page
usort($pages, function ($a, $b) {
    return $a['page'] - $b['page'];
});

echo json_encode([
    'success' => true,
    'job_id' => $jobId,
    'page_count' => count($pages),
    'pages' => $pages,
    'base_url' => $baseUrl
], JSON_UNESCAPED_SLASHES);
