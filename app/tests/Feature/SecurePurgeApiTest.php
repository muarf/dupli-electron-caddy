<?php

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    
    // Create schema
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS print_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT, thumbnail_url TEXT, timestamp DATETIME)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS recorded_print_jobs (job_id INTEGER, printer_name TEXT, recorded_at DATETIME)');

    // Mock local file system
    $this->tempDir = sys_get_temp_dir() . '/purge_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/documents', 0777, true);
    mkdir($this->tempDir . '/thumbnails', 0777, true);

    putenv("DUPLICATOR_DB_PATH=" . $this->dbPath);
    configure_sqlite_conf($this->dbPath);
});

afterEach(function () {
    putenv("DUPLICATOR_DB_PATH"); // Clear
    $this->pdo = null;
    if (file_exists($this->dbPath)) unlink($this->dbPath);
    
    // Recursive delete temp dir
    if (is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

test('secure_purge api deletes old jobs and files', function () {
    $oldDate = date('Y-m-d H:i:s', strtotime('-10 days'));
    $newDate = date('Y-m-d H:i:s', strtotime('-1 day'));

    // 1. Setup old job with files
    $docOld = $this->tempDir . '/documents/old.pdf';
    $thumbOld = $this->tempDir . '/thumbnails/old.png';
    file_put_contents($docOld, 'Sensitive old PDF content');
    file_put_contents($thumbOld, 'Sensitive old Thumbnail content');
    
    $this->pdo->prepare("INSERT INTO print_jobs (document, thumbnail_url, timestamp) VALUES (?, ?, ?)")
              ->execute([$docOld, 'thumbnails/old.png', $oldDate]);
    
    // 2. Setup new job with files
    $docNew = $this->tempDir . '/documents/new.pdf';
    file_put_contents($docNew, 'New job content');
    
    $this->pdo->prepare("INSERT INTO print_jobs (document, timestamp) VALUES (?, ?)")
              ->execute([$docNew, $newDate]);

    // 3. Execution (Capture output)
    ob_start();
    try {
        require __DIR__ . '/../../api/secure_purge.php';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $output = ob_get_clean();
    
    if (empty($output) && isset($error)) {
        throw new Exception("Script failed with error: " . $error);
    }
    
    $response = json_decode($output, true);
    
    if ($response === null) {
        throw new Exception("Invalid JSON output: " . $output);
    }

    // 4. Assertions
    expect($response['success'])->toBeTrue();
    expect($response['stats']['jobs_deleted'])->toBeGreaterThan(0);

    // Old files should be GONE
    expect(file_exists($docOld))->toBeFalse();
    // New files should REMAIN
    expect(file_exists($docNew))->toBeTrue();

    // DB Check
    $count = $this->pdo->query("SELECT COUNT(*) FROM print_jobs")->fetchColumn();
    expect(intval($count))->toBe(1);
});
