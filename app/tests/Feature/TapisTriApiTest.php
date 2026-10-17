<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    seed_tapis_tri_schema($this->pdo);
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

it('lists pending jobs on the tapis de tri', function () {
    $output = execute_routed_endpoint('tapis_tri', [
        'method' => 'GET',
        'get' => ['action' => 'list'],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);

    $json = json_decode($output, true);
    expect($json)->toBeArray();
    expect($json['success'])->toBeTrue();
    expect($json['jobs'])->toHaveCount(2);
    expect($json['jobs'][0]['document'])->toBe('Doc_Client2.pdf');
    expect($json['jobs'][1]['document'])->toBe('Doc_Client1.pdf');
    expect($json['jobs'][1]['calculated_price'])->toBeGreaterThan(0);
});

it('handles direct checkout for selected jobs', function () {
    $output = execute_routed_endpoint('tapis_tri', [
        'method' => 'POST',
        'get' => ['action' => 'checkout_direct'],
        'post' => [
            'job_ids' => [1],
            'contact' => 'Client Express',
            'payment_method' => 'especes',
            'amount_received' => 10.0
        ],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);

    $json = json_decode($output, true);
    expect($json)->toBeArray();
    expect($json['success'])->toBeTrue();
    expect($json['items_count'])->toBe(1);
    expect($json['contact'])->toBe('Client Express');
    expect($json['change'])->toBeGreaterThanOrEqual(0);

    // Vérifier que le job 1 n'est plus listé sur le tapis
    $listOutput = execute_routed_endpoint('tapis_tri', [
        'method' => 'GET',
        'get' => ['action' => 'list'],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);
    $listJson = json_decode($listOutput, true);
    expect($listJson['jobs'])->toHaveCount(1);
    expect($listJson['jobs'][0]['id'])->toBe(2);
});

it('assigns selected jobs to a session', function () {
    $output = execute_routed_endpoint('tapis_tri', [
        'method' => 'POST',
        'get' => ['action' => 'assign_to_session'],
        'post' => [
            'job_ids' => [2],
            'contact' => 'Nouveau Client',
            'session_name' => 'Dossier Projet'
        ],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);

    $json = json_decode($output, true);
    expect($json['success'])->toBeTrue();
    expect($json['assigned_count'])->toBe(1);
    expect($json['contact'])->toBe('Nouveau Client');
});

it('deletes rejected jobs from the tapis de tri', function () {
    $output = execute_routed_endpoint('tapis_tri', [
        'method' => 'POST',
        'get' => ['action' => 'delete_jobs'],
        'post' => [
            'job_ids' => [2]
        ],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);

    $json = json_decode($output, true);
    expect($json['success'])->toBeTrue();
    expect($json['deleted_count'])->toBe(1);
});

function seed_tapis_tri_schema(PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS print_jobs');
    $pdo->exec('CREATE TABLE print_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id TEXT NOT NULL,
        document TEXT NOT NULL,
        document_display_name TEXT,
        document_full_path TEXT,
        printer_name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT "pending",
        pages_printed INTEGER DEFAULT 0,
        total_pages INTEGER DEFAULT 1,
        copies INTEGER DEFAULT 1,
        fill_rate REAL DEFAULT 0.05,
        color_mode TEXT DEFAULT "Monochrome",
        duplex INTEGER DEFAULT 0,
        paper_size TEXT DEFAULT "A4",
        thumbnail_url TEXT,
        timestamp TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        session_id INTEGER,
        calculated_price REAL DEFAULT 0.20,
        machine_type TEXT DEFAULT "photocop",
        machine_id INTEGER DEFAULT 1,
        machine_name TEXT DEFAULT "TestCopier",
        contact TEXT,
        staged INTEGER DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS recorded_print_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        job_id TEXT NOT NULL,
        printer_name TEXT NOT NULL,
        print_job_id INTEGER,
        recorded_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS printer_mappings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        system_printer_name TEXT NOT NULL UNIQUE,
        machine_type TEXT NOT NULL,
        machine_id INTEGER NOT NULL
    )');

    $now = date('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO print_jobs (id, job_id, document, printer_name, status, total_pages, copies, calculated_price, timestamp, created_at) VALUES (1, 'spool_1', 'Doc_Client1.pdf', 'Office_Printer', 'pending', 5, 1, 0.50, '$now', '$now')");
    $pdo->exec("INSERT INTO print_jobs (id, job_id, document, printer_name, status, total_pages, copies, calculated_price, timestamp, created_at) VALUES (2, 'spool_2', 'Doc_Client2.pdf', 'Office_Printer', 'pending', 2, 2, 0.40, '$now', '$now')");
}
