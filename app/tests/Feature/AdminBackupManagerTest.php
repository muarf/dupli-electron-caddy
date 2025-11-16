<?php

require_once __DIR__ . '/../../models/admin/BackupManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    file_put_contents($this->dbPath, 'original');

    $this->conf = $GLOBALS['conf'];
    $this->backupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_backup_' . bin2hex(random_bytes(5));
    mkdir($this->backupDir);

    $this->manager = new BackupManager($this->conf);
    $prop = new ReflectionProperty(BackupManager::class, 'backup_dir');
    $prop->setAccessible(true);
    $prop->setValue($this->manager, rtrim($this->backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    if (isset($this->backupDir) && is_dir($this->backupDir)) {
        $files = glob($this->backupDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->backupDir);
    }
});

it('cree une sauvegarde sqlite dans le dossier dédié', function () {
    $result = $this->manager->createBackup('testbackup');

    expect($result)->toHaveKey('success');
    expect($result['filename'])->toEndWith('.sqlite');
    expect(file_exists($this->backupDir . DIRECTORY_SEPARATOR . $result['filename']))->toBeTrue();
});

it('restore une sauvegarde et crée un safety backup', function () {
    $backupFile = $this->backupDir . DIRECTORY_SEPARATOR . 'manual.sqlite';
    file_put_contents($backupFile, 'restored');

    $result = $this->manager->restoreBackup('manual.sqlite');

    expect($result)->toHaveKey('success');
    expect(file_get_contents($this->dbPath))->toBe('restored');
    $safety = glob($this->dbPath . '.safety_*');
    expect($safety)->not->toBeEmpty();
    expect(file_get_contents($safety[0]))->toBe('original');
});

it('supprime une sauvegarde existante', function () {
    $backup = $this->backupDir . DIRECTORY_SEPARATOR . 'old.sqlite';
    file_put_contents($backup, 'data');

    $result = $this->manager->deleteBackup('old.sqlite');

    expect($result)->toHaveKey('success');
    expect(file_exists($backup))->toBeFalse();
});

it('retourne la liste des sauvegardes triee par date', function () {
    $older = $this->backupDir . DIRECTORY_SEPARATOR . 'older.sqlite';
    $newer = $this->backupDir . DIRECTORY_SEPARATOR . 'newer.sqlite';
    file_put_contents($older, 'old');
    sleep(1);
    file_put_contents($newer, 'new');

    $list = $this->manager->getBackupsList();

    expect($list)->toHaveCount(2);
    expect($list[0]['filename'])->toBe('newer.sqlite');
    expect($list[1]['filename'])->toBe('older.sqlite');
});

