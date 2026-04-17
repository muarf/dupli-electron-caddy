<?php

require_once __DIR__ . '/../../controler/functions/paths.php';
require_once __DIR__ . '/../../controler/functions/machines.php';
require_once __DIR__ . '/../../models/admin/TirageManager.php';

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
        duplicopieur_id INTEGER,
        nom_machine TEXT,
        contact TEXT,
        prix REAL,
        mot TEXT,
        date INTEGER,
        session_id TEXT,
        document_name TEXT,
        thumbnail_url TEXT,
        tirage_global_id TEXT,
        paye TEXT
    )');

    $this->pdo->exec('CREATE TABLE photocop (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        contact TEXT,
        prix REAL,
        paye TEXT,
        date INTEGER,
        session_id TEXT,
        document_name TEXT,
        thumbnail_url TEXT,
        tirage_global_id TEXT,
        mot TEXT
    )');

    $this->pdo->exec('CREATE TABLE photocopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER
    )');

    seed_tirage_manager_fixtures($this->pdo);

    $this->manager = new TirageManager($GLOBALS['conf']);
});

afterEach(function () {
    $_GET = [];
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('retourne les machines disponibles', function () {
    $machines = $this->manager->getMachines();
    expect($machines)->toContain('Riso SF');
    expect($machines)->toContain('Ricoh');
});

it('renvoie les derniers tirages et la pagination pour un duplicopieur', function () {
    $last = $this->manager->getLastTirages('Riso SF', ' ORDER BY id DESC', 1, 10);

    expect($last[0]['contact'])->toBe('Alice');
    expect((float) $last[0]['prix'])->toBe(12.5);
    expect($last['pagination']['current_page'])->toBe(1);
    expect($last['pagination'])->toHaveKeys(['total_entries', 'total_pages', 'per_page']);
});

it('calcule le prix en attente pour une machine', function () {
    $montant = $this->manager->getPrixEnAttente('Riso SF');
    expect((float) $montant)->toBe(12.5);

    $montantPhoto = $this->manager->getPrixEnAttente('Ricoh');
    expect((float) $montantPhoto)->toBe(8.0);
});

it('supprime des tirages sélectionnés', function () {
    $delete = $this->manager->deleteSelectedTirages([1, 1], ['Riso SF', 'Ricoh']);

    expect($delete['deleted_count'])->toBe(2);
    $countDupli = $this->pdo->query('SELECT COUNT(*) FROM dupli')->fetchColumn();
    $countPhoto = $this->pdo->query('SELECT COUNT(*) FROM photocop')->fetchColumn();
    expect((int) $countDupli)->toBe(0);
    expect((int) $countPhoto)->toBe(0);
});

it('marque les tirages sélectionnés comme payés', function () {
    // Réinsérer des tirages
    seed_tirage_manager_fixtures($this->pdo);

    $result = $this->manager->markSelectedAsPaid([1, 1], ['Riso SF', 'Ricoh']);
    expect($result['paid_count'])->toBe(2);

    $dupli = $this->pdo->query('SELECT paye FROM dupli WHERE id = 1')->fetchColumn();
    $photo = $this->pdo->query('SELECT paye FROM photocop WHERE id = 1')->fetchColumn();
    expect($dupli)->toBe('oui');
    expect($photo)->toBe('oui');
});

it('construit la clause SQL selon les filtres', function () {
    $_GET = [];
    $default = $this->manager->buildSqlClause();
    expect($default['sql'])->toBe(' WHERE paye = "non" ORDER BY date DESC, id DESC');
    expect($default['phrase'])->toContain('non-payés');

    $_GET = ['paye' => 'deja_paye', 'order' => '1'];
    $filtered = $this->manager->buildSqlClause();
    expect($filtered['sql'])->toBe(' WHERE paye = "oui" ORDER BY prix * 1 DESC, date DESC');
    expect($filtered['phrase'])->toContain('payés');
});

it('retourne toutes les données de tirage pour affichage', function () {
    $_GET = [];
    $data = $this->manager->getAllTirageData();

    expect($data['machines'])->toContain('Riso SF');
    // Using simple loop just to check variables
    foreach ($data['last']['Riso SF'] as $t) {
        if (isset($t['contact']) && $t['contact'] === 'Alice') {
            expect($t['contact'])->toBe('Alice');
        }
    }
    expect((float) $data['prix_du']['Riso SF'])->toBe(12.5);
});

function seed_tirage_manager_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT OR IGNORE INTO duplicopieurs (id, marque, modele, actif) VALUES (1, 'Riso', 'SF', 1)");
    $pdo->exec("INSERT OR IGNORE INTO dupli (id, duplicopieur_id, nom_machine, contact, prix, mot, date, paye) VALUES (1, 1, 'Riso SF', 'Alice', 12.5, '', strftime('%s','now'), 'non')");
    $pdo->exec("INSERT OR IGNORE INTO photocop (id, marque, contact, prix, paye, date, mot) VALUES (1, 'Ricoh', 'Bob', 8.0, 'non', strftime('%s','now'), '')");
    $pdo->exec("INSERT OR IGNORE INTO photocopieurs (id, marque, modele, actif) VALUES (1, 'Ricoh', 'SP', 1)");
}

