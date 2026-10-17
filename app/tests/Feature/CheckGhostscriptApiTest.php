<?php

it('checks if ghostscript is available', function () {
    $scriptPath = __DIR__ . '/../../api/check_ghostscript.php';

    $payload = [
        'method' => 'GET',
    ];

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg(__DIR__ . '/../helpers/run_endpoint.php') . ' ' .
        escapeshellarg($scriptPath) . ' ' .
        escapeshellarg(base64_encode(json_encode($payload)));

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(0);

    $jsonResponse = implode("\n", $output);
    $response = json_decode($jsonResponse, true);

    expect($response)->toBeArray();
    expect($response)->toHaveKey('available');
    expect($response['available'])->toBeBool();
    expect($response)->toHaveKey('error');
    expect($response)->toHaveKey('error_code');
});

it('handles error when ghostscript execution fails', function () {
    $scriptPath = __DIR__ . '/../../api/check_ghostscript.php';

    $payload = [
        'method' => 'GET',
        'env' => [
            // This will make get_ghostscript_path() return a valid path but execution will fail due to PATH missing
            'PATH' => '/nonexistent/path'
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

    $jsonResponse = implode("\n", $output);
    $response = json_decode($jsonResponse, true);

    expect($response)->toBeArray();
    expect($response['available'])->toBeFalse();
    expect($response['error'])->toContain("ne peut pas s'exécuter");
    expect($response['error_code'])->toBeIn(['EXECUTION_FAILED', 'DLL_NOT_FOUND', 'NOT_FOUND']);
});
