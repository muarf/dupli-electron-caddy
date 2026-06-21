<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    $this->conf = $GLOBALS['conf'];

    seed_action_schema($this->pdo);
    seed_action_fixtures($this->pdo);
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

it('calcule le prix total pour le flux de confirmation', function () {
    $post = build_action_payload();
    $post['ok'] = '1';

    $result = run_action_request([
        'post' => $post,
        'env' => ['DUPLICATOR_DB_PATH' => $this->conf['db_path']],
    ]);
    
    $vars = $result['vars']['variables'] ?? [];
    if (!empty($vars['errors'])) {
        error_log("ERREURS CONFIRMATION: " . print_r($vars['errors'], true));
        error_log("STDERR: " . $result['stderr']);
    }

    expect($vars['contact'])->toBe('Alice');
    expect($vars['prix_total'])->toEqualWithDelta(62.0, 0.001);
    expect($vars['machines'][0]['prix'])->toEqualWithDelta(50.5, 0.001);
    expect($vars['machines'][1]['prix'])->toEqualWithDelta(11.5, 0.001);
});

it('enregistre les tirages lors de la soumission finale', function () {
    $post = build_action_payload();
    $post['enregistrer'] = '1';
    $post['paye'] = 'non';
    $post['cb'] = 0;
    $post['mot'] = 'Test';

    $result = run_action_request([
        'post' => $post,
        'env' => ['DUPLICATOR_DB_PATH' => $this->conf['db_path']],
    ]);
    
    $vars = $result['vars']['variables'] ?? [];
    if (!empty($vars['errors'] ?? [])) {
        error_log("ERREURS ENREGISTREMENT: " . print_r($vars['errors'], true));
        error_log("STDERR: " . $result['stderr']);
    }

    expect($vars['errors'] ?? [])->toBeEmpty();
    if (!isset($vars['success_message'])) {
    }
    expect($vars['success_message'] ?? '')->toContain('succès');

    $dupliRow = $this->pdo->query('SELECT contact, prix FROM dupli')->fetch(PDO::FETCH_ASSOC);
    expect($dupliRow['contact'])->toBe('Alice');
    expect(floatval($dupliRow['prix']))->toEqualWithDelta(50.5, 0.001);

    $photocopRow = $this->pdo->query('SELECT prix FROM photocop')->fetch(PDO::FETCH_ASSOC);
    expect(floatval($photocopRow['prix']))->toEqualWithDelta(11.5, 0.001);
});

function seed_action_schema(PDO $pdo): void
{
    $pdo->exec('DROP TABLE IF EXISTS duplicopieurs');
    $pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER,
        tambours TEXT
    )');

    $pdo->exec('DROP TABLE IF EXISTS dupli');
    $pdo->exec('CREATE TABLE IF NOT EXISTS dupli (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        contact TEXT,
        master_av INTEGER,
        master_ap INTEGER,
        passage_av INTEGER,
        passage_ap INTEGER,
        rv TEXT,
        prix REAL,
        paye TEXT,
        cb REAL,
        mot TEXT,
        date INTEGER,
        nom_machine TEXT,
        duplicopieur_id INTEGER,
        tambour TEXT,
        tirage_global_id TEXT,
        session_id TEXT,
        document_name TEXT DEFAULT "",
        thumbnail_url TEXT
    )');

    $pdo->exec('DROP TABLE IF EXISTS photocop');
    $pdo->exec('CREATE TABLE IF NOT EXISTS photocop (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT,
        marque TEXT,
        contact TEXT,
        nb_f INTEGER,
        rv TEXT,
        prix REAL,
        paye TEXT,
        cb REAL,
        mot TEXT,
        date INTEGER,
        tirage_global_id TEXT,
        session_id TEXT,
        document_name TEXT DEFAULT "",
        thumbnail_url TEXT
    )');

    $pdo->exec('DROP TABLE IF EXISTS photocopieurs');
    $pdo->exec('CREATE TABLE IF NOT EXISTS photocopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT DEFAULT "",
        type_encre TEXT,
        actif INTEGER
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS print_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        status TEXT,
        closed_at DATETIME,
        total_price REAL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS print_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id TEXT,
        document TEXT,
        printer_name TEXT,
        total_pages INTEGER,
        copies INTEGER,
        fill_rate REAL,
        color_mode TEXT,
        duplex INTEGER,
        paper_size TEXT,
        thumbnail_url TEXT,
        calculated_price REAL,
        machine_type TEXT,
        machine_id INTEGER,
        machine_name TEXT,
        session_id INTEGER,
        staged INTEGER DEFAULT 0,
        contact TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS recorded_print_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id TEXT,
        printer_name TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(job_id, printer_name)
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS prix (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine_type TEXT,
        machine_id INTEGER,
        type TEXT,
        pack REAL,
        unite REAL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS papier (
        id INTEGER PRIMARY KEY,
        prix REAL
    )');
}

function seed_action_fixtures(PDO $pdo): void
{
    $pdo->exec("INSERT INTO duplicopieurs (id, marque, modele, actif, tambours) VALUES (1, 'Riso', 'SF', 1, '[\"tambour_noir\"]')");
    $pdo->exec("INSERT INTO photocopieurs (id, marque, type_encre, actif) VALUES (1, 'Ricoh', 'toner', 1)");
    $pdo->exec("INSERT INTO papier (id, prix) VALUES (1, 0.02)");

    $stmt = $pdo->prepare('INSERT INTO prix (machine_type, machine_id, type, pack, unite) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(['dupli', 1, 'master', 45, 4.5]);
    $stmt->execute(['dupli', 1, 'tambour_noir', 10, 0.1]);

    $stmt->execute(['photocop', 1, 'cyan', 20, 0.02]);
    $stmt->execute(['photocop', 1, 'magenta', 20, 0.02]);
    $stmt->execute(['photocop', 1, 'yellow', 20, 0.02]);
    $stmt->execute(['photocop', 1, 'noir', 20, 0.01]);
    $stmt->execute(['photocop', 1, 'tambour', 5, 0.003]);
    $stmt->execute(['photocop', 1, 'dev', 5, 0.002]);
}

function run_action_request(array $config): array
{
    $runner = realpath(__DIR__ . '/../helpers/run_action.php');
    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg($runner) . ' ' .
        escapeshellarg(base64_encode(json_encode($config)));

    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];
    $process = proc_open($command, $descriptorspec, $pipes);
    if (!is_resource($process)) return [];
    
    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    $exitCode = proc_close($process);
    
    if ($exitCode !== 0) {
        throw new RuntimeException("run_action failed with code $exitCode - Output: $output - Stderr: $stderr");
    }
    
    return [
        'vars' => json_decode($output, true) ?: [],
        'stderr' => $stderr
    ];
}

function build_action_payload(): array
{
    return [
        'contact' => 'Alice',
        'machines' => [
            [
                'type' => 'duplicopieur',
                'mode_saisie' => 'compteurs',
                'duplicopieur_id' => 1,
                'master_av' => 100,
                'master_ap' => 105,
                'passage_av' => 1000,
                'passage_ap' => 1200,
                'rv' => 'non',
                'feuilles_payees' => 'non',
                'tambour' => 'tambour_noir',
            ],
            [
                'type' => 'photocopieur',
                'machine' => 'Ricoh',
                'fill_rate' => 0.5,
                'brochures' => [
                    [
                        'nb_exemplaires' => 50,
                        'nb_feuilles' => 4,
                        'taille' => 'A4',
                        'rv' => 'non',
                        'couleur' => 'oui',
                        'feuilles_payees' => 'non',
                    ],
                ],
            ],
        ],
    ];
}

