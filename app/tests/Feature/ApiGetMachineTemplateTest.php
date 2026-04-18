<?php

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER,
        tambours TEXT
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS photocopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER,
        type_encre TEXT DEFAULT "toner"
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS dupli (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        duplicopieur_id INTEGER,
        nom_machine TEXT,
        master_ap INTEGER,
        passage_ap INTEGER
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS photocop (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT
    )');
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

function run_get_machine_template(string $dbPath, array $params): array
{
    $runner = realpath(__DIR__ . '/../helpers/run_endpoint.php');
    $script = realpath(__DIR__ . '/../../api/get-machine-template.php');

    $config = [
        'method' => 'GET',
        'get' => $params,
        'env' => ['DUPLICATOR_DB_PATH' => $dbPath],
    ];

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg($runner) . ' ' .
        escapeshellarg($script) . ' ' .
        escapeshellarg(json_encode($config));

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return json_decode(implode("\n", $output), true);
}

it('retourne le template avec un duplicopieur pré-sélectionné', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (marque, modele, actif, tambours) VALUES ('Riso','SF','1','[\"noir\",\"rouge\"]')");
    $this->pdo->exec("INSERT INTO photocopieurs (marque, modele, actif) VALUES ('Ricoh','Pro',1)");

    $response = run_get_machine_template($this->dbPath, ['index' => 1]);

    expect($response['success'])->toBeTrue();
    expect($response['html'])->toContain('Riso');
    expect($response['html'])->toContain('Ricoh');
    expect($response['html'])->toContain('name="machines[1]');
});

it('exclut les marques déjà présentes côté duplicopieur', function () {
    $this->pdo->exec("INSERT INTO duplicopieurs (marque, modele, actif, tambours) VALUES ('Canon','Canon',1,'[\"noir\"]')");
    $this->pdo->exec("INSERT INTO photocopieurs (marque, modele, actif) VALUES ('Canon','C123',1)");
    $this->pdo->exec("INSERT INTO photocopieurs (marque, modele, actif) VALUES ('Xerox','Alta',1)");

    $response = run_get_machine_template($this->dbPath, ['index' => 2]);

    expect($response['success'])->toBeTrue();
    expect($response['html'])->toContain('Xerox');
    expect($response['html'])->not->toContain('Canon C123');
});

