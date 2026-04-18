<?php


beforeEach(function () {
    require_pricing_dependencies();
    
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    run_migrations();

    $this->manager = new TirageManager($GLOBALS['conf']);
});

afterEach(function () {
    $this->pdo = null;
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

test('it groups multiple jobs by tirage_global_id', function () {
    // Insert 2 jobs with same global ID
    $globalId = 'group_abc_123';
    $this->pdo->exec("INSERT INTO photocop (marque, prix, tirage_global_id, contact, date) VALUES ('Ricoh', 10.5, '$globalId', 'John Doe', '17.04.26')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, tirage_global_id, contact, date) VALUES ('Ricoh', 5.0,  '$globalId', 'John Doe', '17.04.26')");
    
    // Insert 1 job without global ID
    $this->pdo->exec("INSERT INTO photocop (marque, prix, contact, date) VALUES ('Ricoh', 2.0, 'Jane Smith', '17.04.26')");

    // Use Reflection to access private method for precise testing
    $reflection = new ReflectionClass(TirageManager::class);
    $method = $reflection->getMethod('groupTiragesByGlobalId');
    $method->setAccessible(true);

    $allTirages = $this->pdo->query("SELECT * FROM photocop")->fetchAll(PDO::FETCH_ASSOC);
    $grouped = $method->invoke($this->manager, $allTirages);

    // Should have 2 groups: one for 'group_abc_123' and one for the single job
    expect($grouped)->toHaveCount(2);
    
    // Find the group with global ID
    $groupData = null;
    foreach ($grouped as $g) {
        if ($g['tirage_global_id'] === $globalId) {
            $groupData = $g;
        }
    }

    expect($groupData)->not->toBeNull();
    expect($groupData['count'])->toBe(2);
    expect(floatval($groupData['prix_total']))->toBe(15.5);
    expect($groupData['tirages'])->toHaveCount(2);
});

test('it detects unpaid status in a group', function () {
    $globalId = 'group_mixed';
    $this->pdo->exec("INSERT INTO photocop (marque, prix, tirage_global_id, paye) VALUES ('Ricoh', 10, '$globalId', 'oui')");
    $this->pdo->exec("INSERT INTO photocop (marque, prix, tirage_global_id, paye) VALUES ('Ricoh', 5,  '$globalId', 'non')");
    
    $reflection = new ReflectionClass(TirageManager::class);
    $method = $reflection->getMethod('groupTiragesByGlobalId');
    $method->setAccessible(true);

    $allTirages = $this->pdo->query("SELECT * FROM photocop")->fetchAll(PDO::FETCH_ASSOC);
    $grouped = $method->invoke($this->manager, $allTirages);

    expect($grouped[0]['all_paid'])->toBeFalse();
});
