<?php

require_once __DIR__ . '/../../controler/functions/security.php';

beforeEach(function () {
    $_SESSION = [];
});

it('génère et valide un token CSRF conforme', function () {
    $token = generate_csrf_token();
    expect($token)->toBeString();
    expect(strlen($token))->toBe(64);
    expect(verify_csrf_token($token))->toBeTrue();
});

it('rejette les tokens CSRF invalides ou altérés', function () {
    generate_csrf_token();
    expect(verify_csrf_token('invalid_token_hash_1234567890'))->toBeFalse();
    expect(verify_csrf_token(''))->toBeFalse();
});

it('valide un chemin sûr dans le répertoire autorisé', function () {
    $baseDir = [sys_get_temp_dir()];
    $safePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'valid_file.pdf';
    file_put_contents($safePath, 'dummy content');

    expect(validate_safe_path($safePath, $baseDir))->toBeTrue();

    if (file_exists($safePath)) {
        unlink($safePath);
    }
});

it('rejette les tentatives de Path Traversal', function () {
    $baseDir = [sys_get_temp_dir()];
    expect(validate_safe_path('../../etc/passwd', $baseDir))->toBeFalse();
    expect(validate_safe_path(sys_get_temp_dir() . '/../passwd', $baseDir))->toBeFalse();
});

it('rejette un chemin vide', function () {
    expect(validate_safe_path(''))->toBeFalse();
});
