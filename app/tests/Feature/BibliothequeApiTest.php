<?php

require_once __DIR__ . '/../../controler/functions/paths.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo, $this->dataDir] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    run_migrations();
    
    // Le dossier bibliotheque sera créé automatiquement dans $this->dataDir
});

afterEach(function () {
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    // Clean up temporary data dir
    if (isset($this->dataDir) && is_dir($this->dataDir)) {
        exec("rm -rf " . escapeshellarg($this->dataDir));
    }
});

it('indexe un fichier externe dans la bibliothèque', function () {
    $pdfPath = realpath(__DIR__ . '/fixtures/test.pdf');
    
    $result = run_endpoint('api/index_file.php', [], [
        'DUPLICATOR_DATA_DIR' => $this->dataDir,
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
    run_endpoint('api/index_file.php', [], ['DUPLICATOR_DATA_DIR' => $this->dataDir], ['path' => $pdfPath]);
    
    // 2. Search
    $result = run_endpoint('api/search_bibliotheque.php', [
        'q' => 'test'
    ], [
        'DUPLICATOR_DATA_DIR' => $this->dataDir
    ]);
    
    expect($result['success'])->toBe(true);
    expect($result['files'])->toHaveCount(1);
    expect($result['files'][0]['filename'])->toBe('test.pdf');
});

it('supprime un fichier de la bibliothèque', function () {
    // 1. Index
    $pdfPath = realpath(__DIR__ . '/fixtures/test.pdf');
    $index = run_endpoint('api/index_file.php', [], ['DUPLICATOR_DATA_DIR' => $this->dataDir], ['path' => $pdfPath]);
    $id = $index['result']['id'];
    
    // 2. Delete
    $result = run_endpoint('api/delete_bibliotheque_file.php', [], [
        'DUPLICATOR_DATA_DIR' => $this->dataDir
    ], [
        'id' => $id
    ]);
    
    expect($result['success'])->toBe(true);
    
    // Verify in DB
    $count = $this->pdo->query("SELECT COUNT(*) FROM bibliotheque_files WHERE id = $id")->fetchColumn();
    expect((int)$count)->toBe(0);
});
