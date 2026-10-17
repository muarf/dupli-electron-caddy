<?php

require_once __DIR__ . '/../../models/admin/PriceManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, pack REAL, unite REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS papier (id INTEGER PRIMARY KEY, prix REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS photocopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, type_encre TEXT, actif INTEGER)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS cons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT, type TEXT, date INTEGER,
        nb_p INTEGER DEFAULT 0, nb_m INTEGER DEFAULT 0, tambour TEXT DEFAULT ""
    )');

    $this->pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (1, 'Riso', 'SF 9350', 1)");
    $this->pdo->exec("INSERT INTO photocopieurs (id, marque, modele, type_encre, actif) VALUES (1, 'Ricoh', 'MP C3003', 'toner', 1)");
    $this->pdo->exec("INSERT INTO papier (id, prix) VALUES (1, 0.02)");
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('PriceManager: getPrices() retourne les prix existants', function () {
    $stmt = $this->pdo->prepare('INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['dupli', 1, 'master', 45, 4.5]);

    $manager = new PriceManager($GLOBALS['conf']);
    $prices = $manager->getPrices();
    expect($prices)->toBeArray();
    expect($prices)->toHaveKey('dupli_1');
});

it('PriceManager: insertPrice() insère un prix via dupli_1', function () {
    $manager = new PriceManager($GLOBALS['conf']);
    $manager->insertPrice('dupli_1', 'master', 50, 5.0);

    $row = $this->pdo->query("SELECT pack, unite FROM prix WHERE machine_type = 'dupli' AND machine_id = 1 AND type = 'master'")->fetch(PDO::FETCH_ASSOC);
    expect(floatval($row['pack']))->toEqualWithDelta(50, 0.01);
    expect(floatval($row['unite']))->toBe(5.0);
});

it('PriceManager: insertPapier() met à jour le prix du papier', function () {
    $manager = new PriceManager($GLOBALS['conf']);
    $manager->insertPapier(0.05);

    $val = $this->pdo->query('SELECT prix FROM papier WHERE id = 1')->fetchColumn();
    expect(floatval($val))->toEqualWithDelta(0.05, 0.001);
});

it('PriceManager: getPhotocopieurs() retourne la liste', function () {
    $manager = new PriceManager($GLOBALS['conf']);
    $list = $manager->getPhotocopieurs();
    expect($list)->toBeArray();
    expect(count($list))->toBeGreaterThan(0);
});

it('PriceManager: getDuplicopieurs() retourne la liste', function () {
    $manager = new PriceManager($GLOBALS['conf']);
    $list = $manager->getDuplicopieurs();
    expect($list)->toBeArray();
    expect(count($list))->toBeGreaterThan(0);
});

it('PriceManager: getConsommables() retourne les consommables', function () {
    $manager = new PriceManager($GLOBALS['conf']);

    $row = $this->pdo->query("SELECT id FROM duplicopieurs WHERE marque = 'Riso' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeFalse();

    $consos = $manager->getConsommables('Riso');
    expect($consos)->toBeArray();
})->skip('getConsommables relies on get_last_number() from machines.php which is not loaded in test env');
