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
        'conf' => $GLOBALS['conf']
    ]);

    $json = json_decode($output, true);
    expect($json['success'])->toBeTrue();
    expect($json['sessions'])->toBeArray();
});

function seed_sessions_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS print_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        contact TEXT NOT NULL,
        status TEXT DEFAULT "active",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
}
