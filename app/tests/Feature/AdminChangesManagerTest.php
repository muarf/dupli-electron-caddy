<?php

require_once __DIR__ . '/../../models/admin/ChangesManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS cons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT,
        type TEXT,
        date INTEGER,
        nb_p INTEGER,
        nb_m INTEGER,
        tambour TEXT
    )');
    $this->manager = new ChangesManager();
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('ajoute un changement et le retrouve', function () {
    $result = $this->manager->addChange([
        'machine' => 'Riso SF',
        'type' => 'encre',
        'date' => '2025-01-01',
        'nb_p' => 200,
        'nb_m' => 4,
        'tambour' => 'noir',
    ]);

    expect($result)->toHaveKey('success');

    $row = $this->manager->getChange(1);
    expect($row['machine'])->toBe('Riso SF');
    expect((int) $row['nb_p'])->toBe(200);
});

it('met a jour et supprime un changement', function () {
    $this->manager->addChange([
        'machine' => 'Ricoh',
        'type' => 'tambour',
        'date' => time(),
        'nb_p' => 300,
        'nb_m' => 5,
        'tambour' => 'bleu',
    ]);

    $update = $this->manager->updateChange(1, [
        'machine' => 'Ricoh',
        'type' => 'encre',
        'date' => time(),
        'nb_p' => 350,
        'nb_m' => 6,
        'tambour' => 'cyan',
    ]);
    expect($update)->toHaveKey('success');

    $row = $this->manager->getChange(1);
    expect((int) $row['nb_p'])->toBe(350);
    expect($row['type'])->toBe('encre');

    $delete = $this->manager->deleteChange(1);
    expect($delete)->toHaveKey('success');
    expect($this->manager->getAllChanges())->toBe([]);
});

it('retourne les changements paginés', function () {
    foreach (range(1, 3) as $i) {
        $this->manager->addChange([
            'machine' => "Machine $i",
            'type' => 'encre',
            'date' => 1700000000 + $i,
            'nb_p' => 100 * $i,
            'nb_m' => $i,
            'tambour' => '',
        ]);
    }

    $changes = $this->manager->getAllChanges(2, 0);
    expect($changes)->toHaveCount(2);
    expect($changes[0]['machine'])->toBe('Machine 3');
});

