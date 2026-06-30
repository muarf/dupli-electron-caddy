<?php

require_once __DIR__ . '/../../controler/functions/SpoolManager.php';

beforeEach(function () {
    $this->tempSpoolDir = sys_get_temp_dir() . '/spool_test_' . uniqid() . '/';
    mkdir($this->tempSpoolDir, 0777, true);
    
    // Inject the temporary spool directory into SpoolManager via Reflection
    $reflection = new ReflectionClass(SpoolManager::class);
    $property = $reflection->getProperty('spoolDir');
    $property->setValue(null, $this->tempSpoolDir);
});

afterEach(function () {
    // Cleanup temporary directory
    if (is_dir($this->tempSpoolDir)) {
        $files = glob($this->tempSpoolDir . '*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempSpoolDir);
    }
});

function createMockShd($path, $jobId) {
    // Create a buffer of 16 bytes
    $data = str_repeat("\0", 16);
    // Pack jobId at offset 12 (4 bytes little-endian)
    $packed = pack('V', $jobId);
    $data = substr_replace($data, $packed, 12, 4);
    file_put_contents($path, $data);
}

test('findSpoolFile detects standard naming', function () {
    $jobId = 123;
    $splFile = $this->tempSpoolDir . '00123.SPL';
    file_put_contents($splFile, 'Fake SPL data');
    
    $found = SpoolManager::findSpoolFile($jobId);
    expect($found)->toBe($splFile);
});

test('findSpoolFile detects file pooling naming via SHD', function () {
    $jobId = 456;
    $shdFile = $this->tempSpoolDir . 'FP00456.SHD';
    $splFile = $this->tempSpoolDir . 'FP00456.SPL';
    
    createMockShd($shdFile, $jobId);
    file_put_contents($splFile, 'Fake SPL data');
    
    $found = SpoolManager::findSpoolFile($jobId);
    expect($found)->toBe($splFile);
});

test('findSpoolFile returns null if ID mismatch in SHD', function () {
    $jobId = 789;
    $shdFile = $this->tempSpoolDir . 'FP00111.SHD';
    $splFile = $this->tempSpoolDir . 'FP00111.SPL';
    
    createMockShd($shdFile, 111); // ID differs from 789
    file_put_contents($splFile, 'Fake SPL data');
    
    $found = SpoolManager::findSpoolFile($jobId);
    expect($found)->toBeNull();
});

test('deleteSpoolFiles deletes both SPL and SHD', function () {
    $jobId = 999;
    $shdFile = $this->tempSpoolDir . 'FP00999.SHD';
    $splFile = $this->tempSpoolDir . 'FP00999.SPL';
    
    createMockShd($shdFile, $jobId);
    file_put_contents($splFile, 'Fake SPL data');
    
    expect(file_exists($shdFile))->toBeTrue();
    expect(file_exists($splFile))->toBeTrue();
    
    $result = SpoolManager::deleteSpoolFiles($jobId);
    
    expect($result['success'])->toBeTrue();
    expect(file_exists($shdFile))->toBeFalse();
    expect(file_exists($splFile))->toBeFalse();
});
