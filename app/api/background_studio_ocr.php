<?php
require_once __DIR__ . '/../controler/functions/init.php';

if ($argc < 2) {
    die("Job ID manquant\n");
}
$jobId = $argv[1];
$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
$jobFile = $tmpBase . $jobId . '.json';

if (!file_exists($jobFile)) {
    die("Job introuvable\n");
}
$jobData = json_decode(file_get_contents($jobFile), true);

$jobData['status'] = 'processing';
file_put_contents($jobFile, json_encode($jobData));

$postData = $jobData['post'];
$uploadedFile = $jobData['uploadedFile'];
$originalName = $jobData['originalName'];

// Simulate $_POST and $_FILES to reuse logic if possible? No, we just write the logic here.
$type = $postData['type'] ?? 'skip_text';
$lang = $postData['lang'] ?? 'fra';
$deskew = ($postData['deskew'] ?? '0') === '1';
$clean = ($postData['clean'] ?? '0') === '1';
$optimize = ($postData['optimize'] ?? '0') === '1';
$toDocx = ($postData['to_docx'] ?? '0') === '1';
$toDocxFlow = ($postData['to_docx_flow'] ?? '0') === '1';
$toDocxDocling = ($postData['to_docx_docling'] ?? '0') === '1';

try {
    $outFilename = pathinfo($originalName, PATHINFO_FILENAME) . '_ocr_' . time() . '.pdf';
    $outPath = $tmpBase . $outFilename;

    if (PHP_OS_FAMILY === 'Windows') {
        $pythonExe = get_python_path();
        $cmd = [escapeshellarg($pythonExe), '-m', 'ocrmypdf'];
        $tesseractExe = get_tesseract_path();
        if ($tesseractExe !== 'tesseract') {
            $tessDir = dirname(realpath($tesseractExe) ?: $tesseractExe);
            putenv('PATH=' . $tessDir . PATH_SEPARATOR . getenv('PATH'));
            $tessdata = $tessDir . DIRECTORY_SEPARATOR . 'tessdata';
            if (is_dir($tessdata)) putenv('TESSDATA_PREFIX=' . $tessdata);
        }
        $gsPath = get_ghostscript_path();
        if ($gsPath !== 'gs') {
            $gsDir = dirname(realpath($gsPath) ?: $gsPath);
            putenv('PATH=' . $gsDir . PATH_SEPARATOR . getenv('PATH'));
        }
    } else {
        $cmd = ['ocrmypdf'];
    }

    if ($type === 'redo_ocr') {
        $cmd[] = '--force-ocr';
    } else {
        $cmd[] = '--skip-text';
    }

    $cmd[] = '--output-type pdf';
    $cmd[] = '--pdf-renderer hocr';
    $cleanLang = preg_replace('/[^a-z\+]/', '', strtolower($lang));
    if (empty($cleanLang)) $cleanLang = 'fra';
    $cmd[] = '--language ' . escapeshellarg($cleanLang);

    if ($deskew) $cmd[] = '--deskew';
    if ($clean) $cmd[] = '--clean';
    if ($optimize) $cmd[] = '--optimize 1';

    $cmd[] = escapeshellarg($uploadedFile);
    $cmd[] = escapeshellarg($outPath);

    $fullCmd = 'PYTHONUNBUFFERED=1 ' . implode(' ', $cmd) . ' 2>&1';
    
    $logFile = $tmpBase . $jobId . '.log';
    file_put_contents($logFile, "Lancement du traitement OCR...\n");

    $descriptorspec = [
       0 => ["pipe", "r"],
       1 => ["file", $logFile, "a"],
       2 => ["file", $logFile, "a"]
    ];

    $process = proc_open($fullCmd, $descriptorspec, $pipes);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $return_value = proc_close($process);
        if ($return_value !== 0) {
            throw new Exception("Erreur lors du traitement OCR. Code de retour : $return_value.");
        }
    } else {
        throw new Exception("Impossible de lancer le processus OCR.");
    }

    if (!file_exists($outPath) || filesize($outPath) === 0) {
        throw new Exception("Erreur lors du traitement OCR. Fichier PDF introuvable.");
    }

    $finalOutPath = $outPath;
    $downloadUrlParam = $outFilename;

    if ($toDocxDocling) {
        $outFilenameDocx = str_replace('.pdf', '_docling.docx', $outFilename);
        $docxPath = $tmpBase . $outFilenameDocx;
        require_once __DIR__ . '/../models/SettingsManager.php';
        $sm = new SettingsManager(pdo_connect());
        $studioSettings = $sm->getAll();
        $doclingApiUrl = trim($studioSettings['studio_api_docling_url'] ?? '');

        if (!empty($doclingApiUrl)) {
            file_put_contents($logFile, "\nConversion vers DOCX (API IA) en cours...\n", FILE_APPEND);
            $pdfData = base64_encode((string)file_get_contents($outPath));
            $ch = curl_init($doclingApiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pdf' => $pdfData, 'filename' => basename($outPath)]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            $vpsResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $vpsResponse) {
                $respData = json_decode($vpsResponse, true);
                if (isset($respData['success']) && $respData['success'] && !empty($respData['docx'])) {
                    file_put_contents($docxPath, base64_decode($respData['docx']));
                    $finalOutPath = $docxPath;
                    $downloadUrlParam = $outFilenameDocx;
                    file_put_contents($logFile, "Conversion terminée.\n", FILE_APPEND);
                } else {
                    throw new Exception("Erreur API Docling VPS : " . ($respData['error'] ?? 'Inconnue'));
                }
            } else {
                throw new Exception("Erreur de connexion à l'API Docling VPS (Code $httpCode)");
            }
        } else {
            file_put_contents($logFile, "\nConversion vers DOCX (Local IA) en cours...\n", FILE_APPEND);
            $pythonPath = get_python_path();
            $scriptPath = __DIR__ . '/scripts/pdf_to_docx_docling.py';
            $docxCmd = 'export HF_HUB_OFFLINE=1; export HF_HOME=' . escapeshellarg(__DIR__ . '/../cache/huggingface') . '; ' . escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
            
            $process = proc_open($docxCmd, $descriptorspec, $pipes);
            if (is_resource($process)) { fclose($pipes[0]); proc_close($process); }
            
            if (!file_exists($docxPath) || filesize($docxPath) === 0) {
                throw new Exception("Erreur conversion docx IA.");
            }
            $finalOutPath = $docxPath;
            $downloadUrlParam = $outFilenameDocx;
            file_put_contents($logFile, "Conversion terminée.\n", FILE_APPEND);
        }
    } elseif ($toDocx) {
        $outFilenameDocx = str_replace('.pdf', '_layout.docx', $outFilename);
        $docxPath = $tmpBase . $outFilenameDocx;
        file_put_contents($logFile, "\nConversion vers DOCX (Layout) en cours...\n", FILE_APPEND);
        $pythonPath = get_python_path();
        $scriptPath = __DIR__ . '/scripts/pdf_to_docx.py';
        $docxCmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
        
        $process = proc_open($docxCmd, $descriptorspec, $pipes);
        if (is_resource($process)) { fclose($pipes[0]); proc_close($process); }
        
        if (!file_exists($docxPath) || filesize($docxPath) === 0) {
            throw new Exception("Erreur conversion DOCX Layout.");
        }
        $finalOutPath = $docxPath;
        $downloadUrlParam = $outFilenameDocx;
        file_put_contents($logFile, "Conversion terminée.\n", FILE_APPEND);
    } elseif ($toDocxFlow) {
        $outFilenameDocx = str_replace('.pdf', '_flow.docx', $outFilename);
        $docxPath = $tmpBase . $outFilenameDocx;
        file_put_contents($logFile, "\nConversion vers DOCX (Flux) en cours...\n", FILE_APPEND);
        $pythonPath = get_python_path();
        $scriptPath = __DIR__ . '/scripts/text_to_docx.py';
        $docxCmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
        
        $process = proc_open($docxCmd, $descriptorspec, $pipes);
        if (is_resource($process)) { fclose($pipes[0]); proc_close($process); }
        
        if (!file_exists($docxPath) || filesize($docxPath) === 0) {
            throw new Exception("Erreur conversion DOCX Flux.");
        }
        $finalOutPath = $docxPath;
        $downloadUrlParam = $outFilenameDocx;
        file_put_contents($logFile, "Conversion terminée.\n", FILE_APPEND);
    }

    $jobData['status'] = 'done';
    $finalExt = pathinfo($downloadUrlParam, PATHINFO_EXTENSION);
    $jobData['download_url'] = '?download_studio&file=' . urlencode($downloadUrlParam) . '&dl_name=' . urlencode(pathinfo($originalName, PATHINFO_FILENAME) . '.' . $finalExt) . '&job_id=' . urlencode($jobId);
    
    if (file_exists($uploadedFile) && $uploadedFile !== $finalOutPath) {
        @unlink($uploadedFile);
    }
    
    file_put_contents($jobFile, json_encode($jobData));

} catch (Exception $e) {
    $jobData['status'] = 'error';
    $jobData['error'] = $e->getMessage();
    file_put_contents($jobFile, json_encode($jobData));
}
