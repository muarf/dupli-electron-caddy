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

$uploadedFile = $jobData['uploadedFile'];

try {
    require_once __DIR__ . '/../models/taux_remplissage.php';
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $uploadedFile);
    finfo_close($finfo);

    if ($mime === 'application/pdf') {
        $result = analyze_pdf_ink_coverage_gs($uploadedFile);
    } else {
        $result = calculate_fill_rate($uploadedFile);
    }

    $jobData['status'] = 'done';
    $jobData['result'] = $result;
    file_put_contents($jobFile, json_encode($jobData));
} catch (Exception $e) {
    $jobData['status'] = 'error';
    $jobData['error'] = $e->getMessage();
    file_put_contents($jobFile, json_encode($jobData));
}
