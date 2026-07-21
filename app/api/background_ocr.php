<?php
/**
 * Script de traitement OCR en arrière-plan
 */
require_once __DIR__ . '/../controler/functions/init.php';

if ($argc < 2) {
    die("Job ID manquant\n");
}

$jobId = $argv[1];
$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
$jobFile = $tmpBase . $jobId . '.json';

if (!file_exists($jobFile)) {
    die("Job $jobId introuvable\n");
}

$jobData = json_decode(file_get_contents($jobFile), true);
if (!$jobData) {
    die("Données du job invalides\n");
}

// Mettre à jour le statut
$jobData['status'] = 'processing';
file_put_contents($jobFile, json_encode($jobData));

$cmd = $jobData['cmd'];
$outPath = $jobData['outPath'];
$toDocx = $jobData['toDocx'] ?? false;
$docxPath = $jobData['docxPath'] ?? '';
$action = $jobData['action'] ?? 'ocr_cleanup';
$uploadedFile = $jobData['uploadedFile'] ?? '';

try {
    $fullCmd = implode(' ', $cmd) . ' 2>&1';
    $output = shell_exec($fullCmd);

    if (!file_exists($outPath) || filesize($outPath) === 0) {
        throw new Exception("Erreur lors du traitement OCR. Logs : " . htmlspecialchars((string)$output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    $finalOutPath = $outPath;
    $downloadUrlParam = $jobData['outFilename'];

    // Si conversion docx demandée
    if ($toDocx) {
        $pythonPath = get_python_path();
        $scriptPath = __DIR__ . '/scripts/pdf_to_docx.py';
        
        $docxCmd = escapeshellarg($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
        $docxOutput = shell_exec($docxCmd);

        if (!file_exists($docxPath) || filesize($docxPath) === 0) {
            throw new Exception("Erreur lors de la conversion en DOCX. Logs : " . htmlspecialchars((string)$docxOutput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }
        $finalOutPath = $docxPath;
        $downloadUrlParam = $jobData['outFilenameDocx'];
    }

    $jobData['status'] = 'done';
    $jobData['download_url'] = '?download_studio&file=' . urlencode($downloadUrlParam) . '&dl_name=' . urlencode(pathinfo($jobData['originalName'], PATHINFO_FILENAME));
    
    // Nettoyer le fichier source si ce n'est pas le fichier final
    if (file_exists($uploadedFile) && $uploadedFile !== $finalOutPath) {
        @unlink($uploadedFile);
    }
    
    file_put_contents($jobFile, json_encode($jobData));

} catch (Exception $e) {
    $jobData['status'] = 'error';
    $jobData['error'] = $e->getMessage();
    file_put_contents($jobFile, json_encode($jobData));
}
