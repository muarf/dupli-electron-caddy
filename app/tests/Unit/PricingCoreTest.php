<?php

require_once __DIR__ . '/../../controler/func.php';
require_once __DIR__ . '/../../controler/conf.php';
require_once __DIR__ . '/../../controler/functions/pricing.php';

beforeEach(function () {
    require_pricing_dependencies();
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, pack REAL, unite REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS papier (id INTEGER PRIMARY KEY, prix REAL)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, duplicopieur_id INTEGER, nom_machine TEXT, prix REAL, paye TEXT)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS photocop (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, prix REAL, paye TEXT)');

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

it('get_price() retourne les prix structurés', function () {
    $stmt = $this->pdo->prepare('INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['dupli', 1, 'master', 45, 4.5]);
    $stmt->execute(['photocop', 1, 'noire', 2, 0.08]);

    $prices = get_price();
    expect($prices)->toBeArray();
    expect($prices)->toHaveKey('dupli_1');
    expect($prices)->toHaveKey('papier');
});

it('insert_prix() insère un prix machine', function () {
    insert_prix('dupli', 1, 'encre', 5.5, 1.1);

    $row = $this->pdo->query("SELECT pack, unite FROM prix WHERE machine_type = 'dupli' AND machine_id = 1 AND type = 'encre'")->fetch(PDO::FETCH_ASSOC);
    expect(floatval($row['pack']))->toBe(5.5);
    expect(floatval($row['unite']))->toBe(1.1);
});

it('insert_papier() met à jour le prix du papier', function () {
    insert_papier(0.03);
    $value = $this->pdo->query('SELECT prix FROM papier WHERE id = 1')->fetchColumn();
    expect(floatval($value))->toBe(0.03);
});

it('prix_du() calcule le montant dû pour un duplicopieur', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (1, 'Riso', 'SF', 1)");
    $this->pdo->exec("INSERT INTO dupli (duplicopieur_id, nom_machine, prix, paye) VALUES (1, 'Riso SF', 30, 'non')");
    $this->pdo->exec("INSERT INTO dupli (duplicopieur_id, nom_machine, prix, paye) VALUES (1, 'Riso SF', 20, 'oui')");

    expect(floatval(prix_du('Riso SF')))->toBe(30.0);
});

it('prix_du() calcule le montant dû pour un photocopieur', function () {
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 15, 'non')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 25, 'non')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 40, 'oui')");

    expect(floatval(prix_du('Ricoh')))->toBe(40.0);
});
