<?php

require_once __DIR__ . '/../../controler/functions/paths.php';
require_once __DIR__ . '/../../controler/functions/utilities.php';

beforeEach(function () {
    $this->downloadScript = realpath(__DIR__ . '/../../api/download_pdf.php');
    $this->viewScript = realpath(__DIR__ . '/../../api/view_pdf.php');

    $this->tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR;
    if (!is_dir($this->tmpDir)) {
        mkdir($this->tmpDir, 0777, true);
    }
    $this->pdfFile = $this->tmpDir . 'sample.pdf';
    file_put_contents($this->pdfFile, '%PDF-1.4');
});

afterEach(function () {
    if (file_exists($this->pdfFile)) {
        unlink($this->pdfFile);
    }
});

it('permet de télécharger un PDF existant', function () {
    $output = run_pdf_script($this->downloadScript, [
        'file' => basename($this->pdfFile),
    ]);

    expect($output)->toBe(file_get_contents($this->pdfFile));
});

it('refuse les fichiers non PDF', function () {
    $badFile = $this->tmpDir . 'danger.txt';
    file_put_contents($badFile, 'oops');
    $output = run_pdf_script($this->downloadScript, [
        'file' => basename($badFile),
    ]);

    expect($output)->toContain('Type de fichier non autorisé');

    unlink($badFile);
});

it('renvoie une erreur 404 pour un fichier manquant sur view', function () {
    $output = run_pdf_script($this->viewScript, [
        'file' => 'missing.pdf',
    ]);

    expect($output)->toContain('Fichier non trouvé');
});

function run_pdf_script(string $scriptPath, array $getParams): string
{
    $runner = realpath(__DIR__ . '/../helpers/run_endpoint.php');
    $config = [
        'method' => 'GET',
        'get' => $getParams,
    ];

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg($runner) . ' ' .
        escapeshellarg($scriptPath) . ' ' .
        escapeshellarg(base64_encode(json_encode($config)));

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return implode("\n", $output);
}


