<?php

it('checks if background_ocr endpoint rejects missing job id', function () {
    $scriptPath = __DIR__ . '/../../api/background_ocr.php';

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath);

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $response = implode("\n", $output);
    expect($response)->toContain('Job ID manquant');
});
