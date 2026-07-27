<?php

require_once __DIR__ . '/../../models/admin/TirageManager.php';

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

it('TirageManager: getMachines() retourne un array', function () {
    $manager = new TirageManager($GLOBALS['conf']);
    $machines = $manager->getMachines();
    expect($machines)->toBeArray();
});

it('TirageManager: getLastTirages() avec une machine dupli', function () {
    $this->pdo->exec("INSERT INTO dupli (nom_machine, prix, paye, contact, date) VALUES ('Riso', 25, 'non', 'Client A', '2026-01-15')");

    $manager = new TirageManager($GLOBALS['conf']);
    $tirages = $manager->getLastTirages('Riso', 'ORDER BY date DESC', 1, 10);
    expect($tirages)->toBeArray();
});

it('TirageManager: getPrixEnAttente() retourne le montant pour une machine', function () {
    $this->pdo->exec("INSERT INTO dupli (nom_machine, prix, paye, contact, date) VALUES ('Riso', 50, 'non', 'Client', '2026-01-15')");

    $manager = new TirageManager($GLOBALS['conf']);
    $attente = $manager->getPrixEnAttente('Riso');
    expect($attente)->not->toBeNull();
});
