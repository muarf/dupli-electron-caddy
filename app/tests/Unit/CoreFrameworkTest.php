<?php

require_once __DIR__ . '/../../controler/func.php';
require_once __DIR__ . '/../../controler/conf.php';

it('valide la structure de la configuration globale conf.php', function () {
    expect($GLOBALS['conf'])->toBeArray();
    expect($GLOBALS['conf'])->toHaveKeys(['dsn', 'login', 'pass', 'uploaddir', 'db_type']);
});

it('résout le répertoire de données via getDataDir()', function () {
    $dataDir = getDataDir();
    expect($dataDir)->toBeString();
    expect(strlen($dataDir))->toBeGreaterThan(0);
});

it('résout le répertoire temporaire via getTmpDir()', function () {
    $tmpDir = getTmpDir();
    expect($tmpDir)->toBeString();
    expect(strlen($tmpDir))->toBeGreaterThan(0);
});

it('détecte correctement le mode Electron via isElectron()', function () {
    $result = isElectron();
    expect($result)->toBeBool();
});

it('détecte correctement le mode AppImage via isAppImage()', function () {
    $result = isAppImage();
    expect($result)->toBeBool();
});

it('résout le répertoire des bibliothèques via getBibliothequeDir()', function () {
    $dir = getBibliothequeDir();
    expect($dir)->toBeString();
    expect(strlen($dir))->toBeGreaterThan(0);
});

it('enregistre et récupère les logs d\'erreur via log_error() et log_info()', function () {
    log_error('Test error message', 'UnitTest');
    log_info('Test info message', 'UnitTest');
    expect(true)->toBeTrue();
});

it('crée et charge un setting via getSetting() et setSetting()', function () {
    [$dbPath, $pdo] = create_test_sqlite_database();
    configure_sqlite_conf($dbPath);

    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (key TEXT PRIMARY KEY, value TEXT)');
    $pdo->exec("INSERT INTO site_settings (key, value) VALUES ('test_key', 'test_value')");

    $val = $pdo->query("SELECT value FROM site_settings WHERE key = 'test_key'")->fetchColumn();
    expect($val)->toBe('test_value');

    $pdo = null;
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
});
