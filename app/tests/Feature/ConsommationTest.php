<?php

require_once __DIR__ . '/../../controler/functions/consommation.php';
require_once __DIR__ . '/../../controler/functions/pricing.php';
require_once __DIR__ . '/../../controler/functions/machines.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    seed_consommation_schema($this->pdo);
    seed_consommation_fixtures($this->pdo);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('insère un changement d encre et le retrouve via get_cons', function () {
    insert_cons(time(), 'Riso SF', 'encre', 1200, 0);

    $data = get_cons('Riso SF');

    expect($data['encre']['nb_actuel'])->toBeGreaterThan(0);
    expect(['green', 'red'])->toContain($data['encre']['color']);
});

it('calcule les consommations pour un photocopieur', function () {
    insert_cons_photocop(500, 'noire');
    insert_cons_photocop(800, 'noire');

    $data = get_cons('photocop');

    expect($data['photocop']['moyenne_total']['nb_p'])->toBeGreaterThan(0);
    expect($data['photocop']['class'])->toBeString();
});

function seed_consommation_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, unite REAL, pack REAL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cons (id TEXT, date INTEGER, machine TEXT, type TEXT, nb_p REAL, nb_m REAL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, master_ap REAL, passage_ap REAL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS photocop (id INTEGER PRIMARY KEY AUTOINCREMENT, date INTEGER, nb_f REAL)');
}

function seed_consommation_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (1, 'Riso', 'SF', 1)");
    $pdo->exec("INSERT INTO dupli (master_ap, passage_ap) VALUES (200, 2000)");
    $pdo->exec("INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES ('dupli', 1, 'master', 4.5, 45)");
    $pdo->exec("INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES ('dupli', 1, 'encre', 0.12, 10)");
    $pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (2, 'Ricoh', 'C7100', 1)");
    $pdo->exec("INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES ('photocop', 1, 'cyan', 0.02, 20)");
    $pdo->exec("INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES ('photocop', 1, 'noire', 0.01, 15)");
}

