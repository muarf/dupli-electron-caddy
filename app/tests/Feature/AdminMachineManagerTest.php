<?php

require_once __DIR__ . '/../../models/admin/MachineManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    seed_admin_machine_schema($this->pdo);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('ajoute un duplicopieur avec ses prix et son aide', function () {
    $manager = new AdminMachineManager($GLOBALS['conf']);

    $result = $manager->addMachine([
        'machine_type' => 'duplicopieur',
        'machine_name' => 'Riso SF',
        'master_counter' => 120,
        'passage_counter' => 240,
        'tambours' => ['tambour_noir', 'tambour_bleu'],
        'prix_tambour_unite' => [0.1, 0.2],
        'prix_tambour_pack' => [12, 18],
    ]);

    expect($result['success'])->toContain('Riso SF');

    $dupCount = $this->pdo->query('SELECT COUNT(*) FROM duplicopieurs')->fetchColumn();
    expect((int)$dupCount)->toBe(1);

    $prixCount = $this->pdo->query('SELECT COUNT(*) FROM prix')->fetchColumn();
    expect((int)$prixCount)->toBe(3); // master + 2 tambours

    $aideCount = $this->pdo->query('SELECT COUNT(*) FROM aide_machines')->fetchColumn();
    expect((int)$aideCount)->toBe(1);
});

it('retourne les machines avec leurs compteurs', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif, tambours) VALUES (1, 'Riso', 'SF', 1, '[\"tambour_noir\"]')");
    $this->pdo->exec("INSERT INTO dupli (nom_machine, master_ap, passage_ap) VALUES ('Riso SF', 150, 300)");

    $manager = new AdminMachineManager($GLOBALS['conf']);
    $machines = $manager->getMachines();

    expect($machines)->toHaveCount(1);
    expect($machines[0]['name'])->toBe('Riso SF');
    expect($machines[0]['master_counter'])->toEqualWithDelta(150, 0.001);
    expect($machines[0]['passage_counter'])->toEqualWithDelta(300, 0.001);
});

it('supprime un duplicopieur et nettoie les tables associées', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif, tambours) VALUES (1, 'Canon', 'Canon', 1, '[\"tambour_noir\"]')");
    $this->pdo->exec("INSERT INTO cons (machine, type, nb_p, nb_m) VALUES ('canon', 'passage', 1000, 0)");
    $this->pdo->exec("INSERT INTO dupli (nom_machine, master_av, master_ap, passage_av, passage_ap, type, contact, prix, paye, cb, mot, date) VALUES ('Canon', 10, 12, 100, 120, 'tirage', 'test', 0, 'non', 0, '', strftime('%s','now'))");

    $manager = new AdminMachineManager($GLOBALS['conf']);
    $result = $manager->deleteMachine(1, 'duplicopieur');

    expect($result['success'])->toContain('Canon');
    expect((int)$this->pdo->query('SELECT COUNT(*) FROM duplicopieurs')->fetchColumn())->toBe(0);
    expect((int)$this->pdo->query('SELECT COUNT(*) FROM dupli')->fetchColumn())->toBe(0);
    expect((int)$this->pdo->query('SELECT COUNT(*) FROM cons')->fetchColumn())->toBe(0);
});

it('supprime un photocopieur et supprime les consommations associées', function () {
    $this->pdo->exec("INSERT INTO photocopieurs (id, marque, modele, type_encre, actif) VALUES (1, 'Ricoh', 'C300', 'toner', 1)");
    $this->pdo->exec("INSERT INTO cons (machine, type, nb_p, nb_m) VALUES ('ricoh c300', 'passage', 500, 0)");

    $manager = new AdminMachineManager($GLOBALS['conf']);
    $result = $manager->deleteMachine(1, 'photocopieur');

    expect($result['success'])->toContain('Ricoh');
    expect((int)$this->pdo->query('SELECT COUNT(*) FROM photocopieurs')->fetchColumn())->toBe(0);
    expect((int)$this->pdo->query('SELECT COUNT(*) FROM cons')->fetchColumn())->toBe(0);
});

function seed_admin_machine_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, supporte_a3 INTEGER, supporte_a4 INTEGER, actif INTEGER, tambours TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE prix (id INTEGER PRIMARY KEY AUTOINCREMENT, machine_type TEXT, machine_id INTEGER, type TEXT, unite REAL, pack REAL)');
    $pdo->exec('CREATE TABLE dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, contact TEXT, master_av REAL, master_ap REAL, passage_av REAL, passage_ap REAL, rv TEXT, prix REAL, paye TEXT, cb REAL, mot TEXT, date INTEGER, nom_machine TEXT, duplicopieur_id INTEGER)');
    $pdo->exec('CREATE TABLE cons (id INTEGER PRIMARY KEY AUTOINCREMENT, date INTEGER, machine TEXT, type TEXT, nb_p REAL, nb_m REAL, tambour TEXT)');
    $pdo->exec('CREATE TABLE aide_machines (id INTEGER PRIMARY KEY AUTOINCREMENT, machine TEXT, contenu_aide TEXT)');
    $pdo->exec('CREATE TABLE photocopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, type_encre TEXT, actif INTEGER)');
}

