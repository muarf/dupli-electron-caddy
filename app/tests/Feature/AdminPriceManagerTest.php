<?php

require_once __DIR__ . '/../../models/admin/PriceManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    seed_price_manager_schema($this->pdo);
    seed_price_manager_fixtures($this->pdo);

    $this->manager = new PriceManager($GLOBALS['conf']);
});

afterEach(function () {
    unset($this->manager);
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('met à jour le prix d un duplicopieur existant', function () {
    $this->manager->insertPrice('dupli_1', 'master', 60, 5.5);

    $row = $this->pdo->query("SELECT pack, unite FROM prix WHERE machine_type='dupli' AND machine_id=1 AND type='master'")->fetch(PDO::FETCH_ASSOC);
    expect(floatval($row['pack']))->toBe(60.0);
    expect(floatval($row['unite']))->toBe(5.5);
});

it('met à jour le prix du papier A4', function () {
    $this->manager->insertPapier(0.025);

    $value = $this->pdo->query('SELECT prix FROM papier WHERE id = 1')->fetchColumn();
    expect(floatval($value))->toBe(0.025);
});

it('retourne les consommables calculés pour un duplicopieur', function () {
    $stmt = $this->pdo->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([time() - 3600, 'Riso SF', 'encre', 1000, 0]);
    $stmt->execute([time(), 'Riso SF', 'encre', 1600, 0]);

    $res = $this->manager->getConsommables('Riso SF');

    expect($res['encre']['moyenne_totale']['nb_p'])->toBeGreaterThan(0);
    expect($res['encre']['nb_actuel'])->toBeNumeric();
});

function seed_price_manager_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, tambours TEXT, actif INTEGER)');
    $pdo->exec('CREATE TABLE prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, pack REAL, unite REAL)');
    $pdo->exec('CREATE TABLE papier (id INTEGER PRIMARY KEY, prix REAL)');
    $pdo->exec('CREATE TABLE cons (id INTEGER PRIMARY KEY AUTOINCREMENT, date INTEGER, machine TEXT, type TEXT, nb_p REAL, nb_m REAL)');
    $pdo->exec('CREATE TABLE dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, nom_machine TEXT, master_ap REAL, passage_ap REAL)');
}

function seed_price_manager_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, tambours, actif) VALUES (1, 'Riso', 'SF', '[\"tambour_noir\"]', 1)");
    $pdo->exec("INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES ('dupli', 1, 'master', 45, 4.5)");
    $pdo->exec("INSERT INTO papier (id, prix) VALUES (1, 0.02)");
    $pdo->exec("INSERT INTO dupli (nom_machine, master_ap, passage_ap) VALUES ('Riso SF', 200, 2000)");
}

