<?php

require_once __DIR__ . '/../../controler/functions/tirage.php';
require_once __DIR__ . '/../../models/admin/EditManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE duplicopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER
    )');

    $this->pdo->exec('CREATE TABLE dupli (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        contact TEXT,
        prix REAL,
        nom_machine TEXT,
        paye TEXT
    )');

    $this->pdo->exec('CREATE TABLE photocop (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        contact TEXT,
        prix REAL,
        paye TEXT
    )');

    $this->manager = new EditManager($GLOBALS['conf']);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('met à jour un tirage duplicop et ignore les champs exclus', function () {
    $this->pdo->exec("INSERT INTO dupli (type, contact, prix, nom_machine, paye) VALUES ('duplicopieur', 'Alice', 10.0, 'dupli', 'non')");

    $result = $this->manager->updateTirage(1, [
        'contact' => 'Bob',
        'prix' => 12.5,
        'password' => 'secret',
        'save' => '1',
    ], 'dupli');

    expect($result)->toBeNull(); // update_tirage ne retourne rien
    $row = $this->pdo->query('SELECT contact, prix FROM dupli WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    expect($row['contact'])->toBe('Bob');
    expect((float) $row['prix'])->toBe(12.5);
});

it('supprime un tirage duplicopieur en se basant sur duplicopieurs', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (marque, modele, actif) VALUES ('Riso', 'SF', 1)");
    $this->pdo->exec("INSERT INTO dupli (type, contact, prix, nom_machine, paye) VALUES ('duplicopieur', 'Alice', 10.0, 'Riso', 'non')");

    $result = $this->manager->deleteTirage(1, 'Riso');

    expect($result)->toBeTrue();
    $count = $this->pdo->query('SELECT COUNT(*) FROM dupli')->fetchColumn();
    expect((int) $count)->toBe(0);
});

it('supprime un tirage photocopieur', function () {
    $this->pdo->exec("INSERT INTO photocop (marque, contact, prix, paye) VALUES ('Ricoh', 'Charles', 5.0, 'non')");

    $result = $this->manager->deleteTirage(1, 'Ricoh');

    expect($result)->toBeTrue();
    $count = $this->pdo->query('SELECT COUNT(*) FROM photocop')->fetchColumn();
    expect((int) $count)->toBe(0);
});

it('retourne une erreur lorsque le tirage est introuvable', function () {
    $data = $this->manager->getEditData(99, 'dupli');
    expect($data)->toHaveKey('error');
    expect($data['error'])->toContain('non trouvé');
});

