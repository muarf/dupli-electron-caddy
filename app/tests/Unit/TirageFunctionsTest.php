<?php

beforeEach(function () {
    require_once __DIR__ . '/../../controler/functions/database.php';
    require_once __DIR__ . '/../../controler/functions/tirage.php';

    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    seed_tirage_schema($this->pdo);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }

    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('insère un tirage photocopieur avec insert_photocop', function () {
    insert_photocop('A4', 'Ricoh', 'Alice', 200, 'oui', 45.5, 'non', 'oui', 'notes', 1700000000);

    $row = $this->pdo->query('SELECT * FROM photocop')->fetch(PDO::FETCH_ASSOC);
    expect($row)->toMatchArray([
        'type' => 'A4',
        'marque' => 'Ricoh',
        'contact' => 'Alice',
        'nb_f' => 200,
        'rv' => 'oui',
        'prix' => 45.5,
        'paye' => 'non',
        'cb' => 'oui',
        'mot' => 'notes',
    ]);
});

it('marque un tirage photocopieur comme payé', function () {
    insert_photocop('A4', 'Ricoh', 'Bob', 50, 'non', 10, 'non', 'non', '', 1700000001);
    $id = (int) $this->pdo->query('SELECT id FROM photocop ORDER BY id DESC LIMIT 1')->fetchColumn();

    marquer_comme_paye($id, 'Ricoh');

    $status = $this->pdo->query('SELECT paye FROM photocop WHERE id = ' . (int) $id)->fetchColumn();
    expect($status)->toBe('oui');
});

it('renvoie les derniers tirages photocopieurs paginés', function () {
    foreach (range(1, 5) as $i) {
        insert_photocop('A4', 'Ricoh', 'Client ' . $i, 10 * $i, 'non', 5 * $i, 'non', 'non', 'mot', 1700000000 + $i);
    }

    $results = last('Ricoh', 'ORDER BY date DESC', 1, 2);

    expect($results)->toHaveCount(3); // 2 entrées + pagination
    expect($results[0]['contact'])->toBe('Client 5');
    expect($results[1]['contact'])->toBe('Client 4');
    expect((int) $results['pagination']['total_entries'])->toBe(5);
});

it('supprime un tirage duplicopieur par nom de machine', function () {
    seed_duplicopieur($this->pdo, 1, 'Riso', 'SF', 1);
    $this->pdo->exec("INSERT INTO dupli (nom_machine, date, contact, prix, mot, paye) VALUES ('Riso SF', 1700000000, 'Coop', 100, '', 'non')");
    $id = (int) $this->pdo->query('SELECT id FROM dupli ORDER BY id DESC LIMIT 1')->fetchColumn();

    del_tirage($id, 'Riso SF');

    $count = (int) $this->pdo->query('SELECT COUNT(*) FROM dupli')->fetchColumn();
    expect($count)->toBe(0);
});

function seed_tirage_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER, tambours TEXT)');
    $pdo->exec('CREATE TABLE dupli (id INTEGER PRIMARY KEY AUTOINCREMENT, duplicopieur_id INTEGER, nom_machine TEXT, master_ap REAL, passage_ap REAL, date INTEGER, contact TEXT, prix REAL, mot TEXT, paye TEXT)');
    $pdo->exec('CREATE TABLE photocop (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, marque TEXT, contact TEXT, nb_f INTEGER, rv TEXT, prix REAL, paye TEXT, cb TEXT, mot TEXT, date INTEGER)');
    $pdo->exec('CREATE TABLE photocopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, type_encre TEXT, actif INTEGER)');
    $pdo->exec('CREATE TABLE A4 (id INTEGER PRIMARY KEY AUTOINCREMENT, contact TEXT)');
    $pdo->exec('CREATE TABLE A3 (id INTEGER PRIMARY KEY AUTOINCREMENT, contact TEXT)');
}

function seed_duplicopieur(PDO $pdo, int $id, string $marque, string $modele, int $actif): void
{
    $stmt = $pdo->prepare('INSERT INTO duplicopieurs (id, marque, modele, actif, tambours) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$id, $marque, $modele, $actif, json_encode(['tambour_noir'])]);
}

