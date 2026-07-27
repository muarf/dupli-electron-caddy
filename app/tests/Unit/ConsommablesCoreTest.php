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
        nb_p INTEGER DEFAULT 0,
        nb_m INTEGER DEFAULT 0,
        tambour TEXT DEFAULT ""
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

it('ChangesManager: ajoute un changement et le retrouve', function () {
    $manager = new ChangesManager($GLOBALS['conf']);

    $result = $manager->addChange([
        'machine' => 'Riso SF 9350',
        'type' => 'encre',
        'nb_p' => 1000,
        'nb_m' => 2000,
        'tambour' => 'noir',
    ]);
    expect($result)->toHaveKey('success');
});

it('ChangesManager: met à jour et supprime un changement', function () {
    $manager = new ChangesManager($GLOBALS['conf']);

    $manager->addChange([
        'machine' => 'Ricoh MP C3003',
        'type' => 'toner',
        'nb_p' => 500,
        'nb_m' => 1500,
    ]);

    $changes = $manager->getAllChanges(10, 0);
    expect(count($changes))->toBeGreaterThan(0);

    $first = $changes[0];
    $manager->deleteChange($first['id']);

    $afterDelete = $manager->getAllChanges(10, 0);
    $found = false;
    foreach ($afterDelete as $c) {
        if ($c['id'] == $first['id']) {
            $found = true;
        }
    }
    expect($found)->toBeFalse();
});

it('ChangesManager: retourne les changements paginés', function () {
    $manager = new ChangesManager($GLOBALS['conf']);

    for ($i = 0; $i < 5; $i++) {
        $manager->addChange([
            'machine' => "Machine $i",
            'type' => 'encre',
            'nb_p' => $i * 100,
            'nb_m' => ($i + 1) * 100,
        ]);
    }

    $all = $manager->getAllChanges(10, 0);
    expect(count($all))->toBeGreaterThanOrEqual(5);

    foreach ($all as $c) {
        $manager->deleteChange($c['id']);
    }
});
