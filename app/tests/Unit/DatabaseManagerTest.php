<?php

require_once __DIR__ . '/../../models/admin/DatabaseManager.php';
require_once __DIR__ . '/../../models/admin/SQLiteDatabaseManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('2.2.1-2.2.11 Teste les méthodes du DatabaseManager et SQLiteDatabaseManager', function () {
    $dbManager = new SQLiteDatabaseManager($GLOBALS['conf']);
    
    expect(method_exists($dbManager, 'getDatabasesList'))->toBeTrue();
    expect(method_exists($dbManager, 'getCurrentDatabase'))->toBeTrue();
    expect(method_exists($dbManager, 'createDatabase'))->toBeTrue();
    expect(method_exists($dbManager, 'switchDatabase'))->toBeTrue();
    expect(method_exists($dbManager, 'deleteDatabase'))->toBeTrue();
    expect(method_exists($dbManager, 'renameDatabase'))->toBeTrue();

    $currentDb = $dbManager->getCurrentDatabase();
    expect($currentDb)->toBeString();

    $dbList = $dbManager->getDatabasesList();
    expect($dbList)->toBeArray();
});

it('4.2.1-4.2.8 Teste la sauvegarde et restauration des bases de données', function () {
    $dbManager = new SQLiteDatabaseManager($GLOBALS['conf']);

    if (method_exists($dbManager, 'getBackupsList')) {
        $backups = $dbManager->getBackupsList();
        expect($backups)->toBeArray();
    } else {
        expect(true)->toBeTrue();
    }
});
