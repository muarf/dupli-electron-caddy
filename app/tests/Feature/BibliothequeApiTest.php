<?php

require_once __DIR__ . '/../../controler/functions/paths.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    run_migrations();
    
    $this->bibliothequeDir = __DIR__ . '/fixtures/bibliotheque_test';
    if (!is_dir($this->bibliothequeDir)) {
        mkdir($this->bibliothequeDir, 0777, true);
    }
    
    // Create migrations if needed (The API handles it usually, but let's be safe)
    // RunMigrations was used in search_bibliotheque.php
});

afterEach(function () {
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    // Clean up bibliotheque test dir
    if (is_dir($this->bibliothequeDir)) {
        // exec("rm -rf " . escapeshellarg($this->bibliothequeDir));
    }
});

it('indexe un fichier externe dans la bibliothèque', function () {
    $pdfPath = realpath(__DIR__ . '/fixtures/test.pdf');
    
    $result = run_endpoint('api/index_file.php', [], [
        'DUPLICATOR_DB_PATH' => $this->dbPath,
    ], [
        'path' => $pdfPath
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
    expect($result)->toHaveKey('result');
    
    // The API index_file returns {success:true, result:{id:X, status:success}}
    $id = $result['result']['id'] ?? null;
    expect($id)->not->toBeNull();
    
    // Verify in DB
    $file = $this->pdo->query("SELECT * FROM bibliotheque_files WHERE id = " . $id)->fetch();
    expect($file['filename'])->toBe('test.pdf');
});

it('recherche un fichier dans la bibliothèque', function () {
    // 1. Index first
    $pdfPath = realpath(__DIR__ . '/fixtures/test.pdf');
    run_endpoint('api/index_file.php', [], ['DUPLICATOR_DB_PATH' => $this->dbPath], ['path' => $pdfPath]);
    
    // 2. Search
    $result = run_endpoint('api/search_bibliotheque.php', [
        'q' => 'test'
    ], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ]);
    
    expect($result['success'])->toBe(true);
    expect($result['files'])->toHaveCount(1);
    expect($result['files'][0]['filename'])->toBe('test.pdf');
});

it('supprime un fichier de la bibliothèque', function () {
    // 1. Index
    $pdfPath = realpath(__DIR__ . '/fixtures/test.pdf');
    $index = run_endpoint('api/index_file.php', [], ['DUPLICATOR_DB_PATH' => $this->dbPath], ['path' => $pdfPath]);
    $id = $index['result']['id'];
    
    // 2. Delete
    $result = run_endpoint('api/delete_bibliotheque_file.php', [], [
        'DUPLICATOR_DB_PATH' => $this->dbPath
    ], [
        'id' => $id
    ]);
    
    expect($result['success'])->toBe(true);
    
    // Verify in DB
    $count = $this->pdo->query("SELECT COUNT(*) FROM bibliotheque_files WHERE id = $id")->fetchColumn();
    expect((int)$count)->toBe(0);
});
