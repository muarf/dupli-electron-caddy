<?php

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    run_migrations();
});

afterEach(function () {
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('crée une nouvelle session d\'impression', function () {
    $result = run_endpoint('api/sessions.php', [
        'action' => 'create'
    ], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ], [
        'contact' => 'Jean Dupont',
        'session_name' => 'Test Session'
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('session_id');
    
    // Verify in DB
    $session = $this->pdo->query("SELECT * FROM print_sessions WHERE id = " . $result['session_id'])->fetch();
    expect($session['contact'])->toBe('Jean Dupont');
    expect($session['status'])->toBe('active');
});

it('liste les sessions actives', function () {
    // 1. Create a session
    $this->pdo->exec("INSERT INTO print_sessions (contact, status, opened_at) VALUES ('Alice', 'active', datetime('now'))");
    
    $result = run_endpoint('api/sessions.php', [
        'action' => 'list'
    ], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ]);
    
    expect($result)->toHaveKey('sessions');
    expect($result['sessions'])->toHaveCount(1);
    expect($result['sessions'][0]['contact'])->toBe('Alice');
});

it('réassigne un job à une autre session', function () {
    // 1. Create two sessions
    $this->pdo->exec("INSERT INTO print_sessions (id, contact, status) VALUES (1, 'S1', 'active')");
    $this->pdo->exec("INSERT INTO print_sessions (id, contact, status) VALUES (2, 'S2', 'active')");
    
    // 2. Create a job in session 1
    $this->pdo->exec("INSERT INTO photocop (id, session_id, prix, type, contact, nb_f, rv, paye, cb, mot, date) 
                      VALUES (10, 1, '5.0', 'A4', 'S1', '1', 'recto', 'oui', 'non', '', datetime('now'))");
    
    // 3. Reassign to session 2
    $result = run_endpoint('api/sessions.php', [
        'action' => 'reassign_job'
    ], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ], [
        'job_id' => 10,
        'job_table' => 'photocop',
        'to_session' => 2
    ]);
    
    expect($result['success'])->toBe(true);
    
    // Verify in DB
    $job = $this->pdo->query("SELECT session_id FROM photocop WHERE id = 10")->fetch();
    expect((int)$job['session_id'])->toBe(2);
});

it('ferme une session et calcule le prix total', function () {
    // 1. Create session and jobs
    $this->pdo->exec("INSERT INTO print_sessions (id, contact, status) VALUES (5, 'Bob', 'active')");
    $this->pdo->exec("INSERT INTO photocop (session_id, prix, type, contact, nb_f, rv, paye, cb, mot, date) 
                      VALUES (5, '10.5', 'A4', 'Bob', '2', 'recto', 'oui', 'non', '', datetime('now'))");
    $this->pdo->exec("INSERT INTO dupli (session_id, prix, type, contact, master_av, master_ap, passage_av, passage_ap, rv, paye, cb, mot, date) 
                      VALUES (5, '20.0', 'A4', 'Bob', '1', '1', '10', '10', 'recto', 'oui', 'non', '', datetime('now'))");
    
    // 2. Close session
    $result = run_endpoint('api/sessions.php', [
        'action' => 'close'
    ], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ], [
        'session_id' => 5
    ]);
    
    expect($result['success'])->toBe(true);
    expect((float)$result['total_price'])->toBe(30.5);
    
    // Verify status in DB
    $status = $this->pdo->query("SELECT status FROM print_sessions WHERE id = 5")->fetchColumn();
    expect($status)->toBe('closed');
});
