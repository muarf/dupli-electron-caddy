<?php

use function Pest\Laravel\get;

beforeEach(function () {
    $this->spoolDir = realpath(__DIR__ . '/fixtures/spool') . DIRECTORY_SEPARATOR;
    $this->mockBinary = realpath(__DIR__ . '/../helpers/mock_binary.sh');
    
    // Create thumbnails dir if not exists
    $this->thumbDir = dirname(__DIR__, 2) . '/public/thumbnails';
    if (!is_dir($this->thumbDir)) {
        mkdir($this->thumbDir, 0777, true);
    }
});

afterEach(function () {
    // Clean up thumbnails
    if (is_dir($this->thumbDir)) {
        // We don't want to delete everything if other tests use it, 
        // but for now it's okay.
    }
});

it('convertit un EMF depuis le spool (Mock)', function () {
    // Set environment variables for the current process
    // Note: Since we use run_endpoint helper which might run in a separate process, 
    // we must pass them in the config.
    
    $result = run_endpoint('api/convert-emf-to-png.php', [
        'job_id' => 123,
    ], [
        'DUPLICATOR_SPOOL_PATH' => $this->spoolDir,
        'DUPLICATOR_MAGICK_PATH' => $this->mockBinary
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('page_count');
    expect($result['page_count'])->toBeGreaterThan(0);
});

it('convertit un PCL depuis le spool (Mock)', function () {
    $result = run_endpoint('api/convert-pcl-to-png.php', [
        'job_id' => 124, 
    ], [
        'DUPLICATOR_SPOOL_PATH' => $this->spoolDir,
        'DUPLICATOR_GPCL_PATH' => $this->mockBinary
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
});

it('convertit un XPS depuis le spool (Mock)', function () {
    $result = run_endpoint('api/convert-xps-to-png.php', [
        'job_id' => 125,
    ], [
        'DUPLICATOR_SPOOL_PATH' => $this->spoolDir,
        'DUPLICATOR_GXPS_PATH' => $this->mockBinary
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
});
