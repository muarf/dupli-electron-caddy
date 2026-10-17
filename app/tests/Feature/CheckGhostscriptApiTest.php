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

    // Create a temporary script that exits with code 1 to reliably simulate gs failure
    $tempBase = tempnam(sys_get_temp_dir(), 'fake_gs');
    if (PHP_OS_FAMILY === 'Windows') {
        $fakeGs = $tempBase . '.bat';
        file_put_contents($fakeGs, "@echo off\r\nexit /b 1\r\n");
    } else {
        $fakeGs = $tempBase;
        file_put_contents($fakeGs, "#!/bin/sh\nexit 1\n");
        chmod($fakeGs, 0755);
    }

    try {
        $payload = [
            'method' => 'GET',
            'env' => [
                'DUPLICATOR_GS_PATH' => $fakeGs
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
    } finally {
        if (file_exists($tempBase)) {
            @unlink($tempBase);
        }
        if (file_exists($fakeGs)) {
            @unlink($fakeGs);
        }
    }
});
