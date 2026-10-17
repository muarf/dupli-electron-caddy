<?php

require_once __DIR__ . '/../../models/BibliothequeManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS bibliotheque_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        original_name TEXT,
        stored_name TEXT,
        file_path TEXT,
        file_size INTEGER,
        mime_type TEXT,
        created_at TEXT,
        metadata_json TEXT,
        tags TEXT
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

it('gère l ajout et la récupération de fichiers dans la bibliothèque', function () {
    $stmt = $this->pdo->prepare('INSERT INTO bibliotheque_files (original_name, stored_name, file_path, file_size, mime_type, tags) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute(['manuel.pdf', 'manuel_123.pdf', '/tmp/manuel_123.pdf', 1024, 'application/pdf', 'riso,aide']);

    $count = $this->pdo->query('SELECT COUNT(*) FROM bibliotheque_files')->fetchColumn();
    expect((int)$count)->toBe(1);

    $file = $this->pdo->query('SELECT * FROM bibliotheque_files WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    expect($file['original_name'])->toBe('manuel.pdf');
    expect($file['tags'])->toBe('riso,aide');
});
