<?php

require_once __DIR__ . '/../../models/admin/MachineManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo, $this->dataDir] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, pack REAL, unite REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS papier (id INTEGER PRIMARY KEY, prix REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS cons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date INTEGER,
        machine TEXT,
        type TEXT,
        nb_p INTEGER DEFAULT 0,
        nb_m INTEGER DEFAULT 0,
        tambour TEXT DEFAULT NULL
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS aide_machines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT UNIQUE,
        contenu_aide TEXT,
        date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    if (isset($this->dataDir) && is_dir($this->dataDir)) {
        array_map('unlink', glob($this->dataDir . '/*'));
        rmdir($this->dataDir);
    }
});

it('AdminMachineManager: getMachines() retourne un array vide sur base vide', function () {
    $manager = new AdminMachineManager($GLOBALS['conf']);
    $machines = $manager->getMachines();
    expect($machines)->toBeArray();
    expect(count($machines))->toBe(0);
});

it('AdminMachineManager: addMachine() ajoute un duplicopieur', function () {
    $manager = new AdminMachineManager($GLOBALS['conf']);
    $result = $manager->addMachine([
        'machine_type' => 'duplicopieur',
        'machine_name' => 'Riso SF 9350',
        'master_counter' => 0,
        'passage_counter' => 0,
        'tambours' => ['tambour_noir'],
    ]);
    expect($result)->not->toBeFalse();
    expect($result)->toHaveKey('success');

    $exists = $this->pdo->query("SELECT COUNT(*) FROM duplicopieurs WHERE marque = 'Riso SF 9350'")->fetchColumn();
    expect((int)$exists)->toBe(1);
});

it('AdminMachineManager: addMachine() ajoute un photocopieur', function () {
    $manager = new AdminMachineManager($GLOBALS['conf']);
    $result = $manager->addMachine([
        'machine_type' => 'photocop_toner',
        'machine_name' => 'Ricoh MP C3003',
    ]);
    expect($result)->not->toBeFalse();
    expect($result)->toHaveKey('success');

    $exists = $this->pdo->query("SELECT COUNT(*) FROM photocopieurs WHERE marque = 'Ricoh MP C3003'")->fetchColumn();
    expect((int)$exists)->toBe(1);
});
