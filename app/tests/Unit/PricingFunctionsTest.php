<?php

beforeEach(function () {
    require_pricing_dependencies();

    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    seed_pricing_schema($this->pdo);
    seed_pricing_fixtures($this->pdo);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('récupère les prix structurés pour dupli et photocop', function () {
    $prices = get_price();

    expect($prices)->toHaveKey('dupli_1');
    expect($prices['dupli_1']['master']['unite'])->toBe(4.5);
    expect($prices['photocop_1']['noire']['unite'])->toBe(0.08);
    expect($prices['papier']['A4'])->toBe(0.02);
});

it('insère ou met à jour un prix machine', function () {
    insert_prix('dupli', 1, 'encre', 5.5, 1.1);

    $row = $this->pdo->query("SELECT pack, unite FROM prix WHERE machine_type = 'dupli' AND machine_id = 1 AND type = 'encre'")->fetch(PDO::FETCH_ASSOC);
    expect(floatval($row['pack']))->toBe(5.5);
    expect(floatval($row['unite']))->toBe(1.1);
});

it('met à jour le prix du papier', function () {
    insert_papier(0.03);

    $value = $this->pdo->query('SELECT prix FROM papier WHERE id = 1')->fetchColumn();
    expect(floatval($value))->toBe(0.03);
});

it('calcule le montant dû pour un duplicopieur actif', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (1, 'Riso', 'SF', 1)");
    $this->pdo->exec("INSERT INTO dupli (duplicopieur_id, nom_machine, prix, paye) VALUES (1, 'Riso SF', 30, 'non')");
    $this->pdo->exec("INSERT INTO dupli (duplicopieur_id, nom_machine, prix, paye) VALUES (1, 'Riso SF', 20, 'oui')");

    expect(floatval(prix_du('Riso SF')))->toBe(30.0);
});

it('calcule le montant dû pour une marque de photocopieur', function () {
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 15, 'non')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 25, 'non')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, paye) VALUES ('Ricoh', 40, 'oui')");

    expect(floatval(prix_du('Ricoh')))->toBe(40.0);
});

function seed_pricing_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, pack REAL, unite REAL)');
    $pdo->exec('CREATE TABLE papier (id INTEGER PRIMARY KEY, prix REAL)');
    $pdo->exec('CREATE TABLE dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, duplicopieur_id INTEGER, nom_machine TEXT, prix REAL, paye TEXT)');
    $pdo->exec('CREATE TABLE duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER)');
    $pdo->exec('CREATE TABLE photocop (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, prix REAL, paye TEXT)');
}

function seed_pricing_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT INTO papier (id, prix) VALUES (1, 0.02)");

    // Prix dupli A3
    $stmt = $pdo->prepare('INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['dupli', 1, 'master', 45, 4.5]);
    $stmt->execute(['dupli', 1, 'encre', 9, 0.9]);

    // Prix photocop génériques
    $stmt->execute(['photocop', 1, 'noire', 2, 0.08]);
    $stmt->execute(['photocop', 1, 'couleur', 4, 0.12]);
}

