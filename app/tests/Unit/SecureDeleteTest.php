<?php

require_once __DIR__ . '/../../controler/functions/secure_delete.php';

test('secure_delete should return true for non-existent file', function () {
    $path = sys_get_temp_dir() . '/non_existent_' . uniqid();
    expect(secure_delete($path))->toBeTrue();
});

test('secure_delete should delete an existing file', function () {
    $path = tempnam(sys_get_temp_dir(), 'test_secure_delete_');
    file_put_contents($path, 'Secret data that should be overwritten');
    
    expect(file_exists($path))->toBeTrue();
    
    $result = secure_delete($path);
    
    expect($result)->toBeTrue();
    expect(file_exists($path))->toBeFalse();
});

test('secure_delete should handle empty files', function () {
    $path = tempnam(sys_get_temp_dir(), 'test_secure_delete_empty_');
    file_put_contents($path, '');
    
    expect(file_exists($path))->toBeTrue();
    
    $result = secure_delete($path);
    
    expect($result)->toBeTrue();
    expect(file_exists($path))->toBeFalse();
});

test('secure_delete should handle large files', function () {
    $path = tempnam(sys_get_temp_dir(), 'test_secure_delete_large_');
    // Create a 2MB file (bigger than the 1MB chunk size in secure_delete)
    $handle = fopen($path, 'wb');
    for ($i = 0; $i < 2048; $i++) {
        fwrite($handle, str_repeat('A', 1024));
    }
    fclose($handle);
    
    expect(filesize($path))->toBe(2 * 1024 * 1024);
    
    $result = secure_delete($path);
    
    expect($result)->toBeTrue();
    expect(file_exists($path))->toBeFalse();
});
