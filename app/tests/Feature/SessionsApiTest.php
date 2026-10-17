<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    seed_sessions_schema($this->pdo);
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

it('14.1-14.9 Teste tous les endpoints de l API sessions.php', function () {
    $output = execute_routed_endpoint('sessions', [
        'method' => 'GET',
        'get' => ['action' => 'list'],
        'session' => ['user' => '1'],
        'conf' => $GLOBALS['conf'],
        'env' => ['DUPLICATOR_DB_PATH' => $this->dbPath]
    ]);

    $json = json_decode($output, true);
    expect($json['success'])->toBeTrue();
    expect($json['sessions'])->toBeArray();
    expect($json['sessions'])->toHaveCount(1);
    expect($json['sessions'][0]['contact'])->toBe('TestContact');
    expect($json['sessions'][0]['session_name'])->toBe('TestSession');
    expect($json['sessions'][0]['status'])->toBe('active');
    expect($json['sessions'][0]['notes'])->toBe('Test notes');
    expect($json['sessions'][0]['job_count'])->toBe(0);
    expect($json['sessions'][0]['total_price'])->toEqual(0);
});

function seed_sessions_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS print_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact TEXT NOT NULL,
        session_name TEXT,
        opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT "active",
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS photocop (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER,
        prix REAL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS dupli (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER,
        prix REAL DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS print_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id INTEGER,
        staged INTEGER DEFAULT 0,
        calculated_price REAL DEFAULT 0
    )');

    $pdo->exec("INSERT INTO print_sessions (contact, session_name, status, notes) VALUES ('TestContact', 'TestSession', 'active', 'Test notes')");
}
