<?php

it('checks if check-print-jobs endpoints handles get_linux_thumb correctly', function () {
    $scriptPath = __DIR__ . '/../../api/check-print-jobs.php';

    // Create a dummy thumbnail
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_thumbnails';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    $dummyThumb = $tempDir . DIRECTORY_SEPARATOR . 'dummy.png';
    file_put_contents($dummyThumb, 'dummy image content');

    $payload = [
        'method' => 'GET',
        'get' => ['get_linux_thumb' => 'dummy.png'],
        'server' => [
            'HTTP_ORIGIN' => 'http://localhost:8000'
        ]
    ];

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg(__DIR__ . '/../helpers/run_endpoint.php') . ' ' .
        escapeshellarg($scriptPath) . ' ' .
        escapeshellarg(base64_encode(json_encode($payload)));

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);

    $responseStr = implode("\n", $output);
    expect($responseStr)->toContain('dummy image content');

    unlink($dummyThumb);
});
