<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];

    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    seed_tambours_schema($this->pdo);
    seed_tambours_fixtures($this->pdo);

    $this->conf = $GLOBALS['conf'];
});

afterEach(function () {
    $_GET = [];
    $_POST = [];
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('retourne les prix des tambours pour un duplicopieur', function () {
    $output = run_ajax_get_tambour_prices(['machine_id' => 1], $this->conf);

    $json = json_decode($output, true);

    expect($json['success'])->toBeTrue();
    expect($json['prices']['tambour_noir']['unite'])->toBe(0.12);
});

it('renvoie une erreur si machine_id est manquant', function () {
    $output = run_ajax_get_tambour_prices([], $this->conf);
    $json = json_decode($output, true);

    expect($json['error'])->toContain('machine');
});

it('met à jour la liste des tambours et leurs prix', function () {
    $payload = [
        'machine_id' => 1,
        'tambours' => ['tambour_noir', 'tambour_bleu'],
        'prix_tambour_unite' => [0.15, 0.25],
        'prix_tambour_pack' => [12, 18],
    ];

    $output = run_ajax_edit_tambours($payload, $this->conf);

    $json = json_decode($output, true);
    expect($json['success'])->toContain('Tambours mis à jour');

    $prices = $this->pdo->query('SELECT type, unite, pack FROM prix WHERE machine_type="dupli" AND machine_id=1 ORDER BY type')->fetchAll(PDO::FETCH_ASSOC);
    expect($prices)->toHaveCount(3); // master + 2 tambours
    expect($prices[1]['type'])->toBe('tambour_bleu');
});

function run_ajax_get_tambour_prices(array $post, array $conf): string
{
    return execute_routed_endpoint('ajax_get_tambour_prices', [
        'method' => 'POST',
        'post' => $post,
        'session' => ['user' => 'admin'],
        'conf' => $conf,
        'env' => ['DUPLICATOR_DB_PATH' => $conf['db_path']],
    ]);
}

function run_ajax_edit_tambours(array $post, array $conf): string
{
    return execute_routed_endpoint('ajax_edit_tambours', [
        'method' => 'POST',
        'post' => $post,
        'session' => ['user' => 'admin'],
        'conf' => $conf,
        'env' => ['DUPLICATOR_DB_PATH' => $conf['db_path']],
    ]);
}


function seed_tambours_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER,
        tambours TEXT,
        updated_at TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS prix (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine_type TEXT,
        machine_id INTEGER,
        type TEXT,
        unite REAL,
        pack REAL
    )');
}

function seed_tambours_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif, tambours) VALUES (1, 'Riso', 'SF', 1, '[\"tambour_noir\"]')");
    $stmt = $pdo->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['dupli', 1, 'master', 4.5, 45]);
    $stmt->execute(['dupli', 1, 'tambour_noir', 0.12, 10]);
}

