<?php

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS dupli (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        passage_av REAL,
        passage_ap REAL,
        date INTEGER,
        prix REAL DEFAULT 0,
        paye TEXT DEFAULT \'non\',
        contact TEXT DEFAULT \'\',
        nom_machine TEXT DEFAULT \'\'
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

it('calcule la somme des impressions depuis la base de données', function () {
    $now = time();
    $this->pdo->exec("INSERT INTO dupli (passage_av, passage_ap, date) VALUES (100, 200, $now)");

    $totalPassages = $this->pdo->query('SELECT SUM(passage_ap - passage_av) FROM dupli')->fetchColumn();
    expect((float)$totalPassages)->toBe(100.0);
});

it('compte le nombre total de tirages', function () {
    $this->pdo->exec("INSERT INTO dupli (passage_av, passage_ap, prix, paye, contact, date) VALUES (0, 100, 25, 'non', 'A', " . time() . ")");
    $this->pdo->exec("INSERT INTO dupli (passage_av, passage_ap, prix, paye, contact, date) VALUES (0, 200, 35, 'oui', 'B', " . time() . ")");

    $count = $this->pdo->query('SELECT COUNT(*) FROM dupli')->fetchColumn();
    expect((int)$count)->toBe(2);
});

it('calcule le montant total impayé', function () {
    $this->pdo->exec("INSERT INTO dupli (passage_av, passage_ap, prix, paye, contact, date) VALUES (0, 100, 25, 'non', 'A', " . time() . ")");
    $this->pdo->exec("INSERT INTO dupli (passage_av, passage_ap, prix, paye, contact, date) VALUES (0, 200, 35, 'oui', 'B', " . time() . ")");

    $total = $this->pdo->query("SELECT SUM(prix) FROM dupli WHERE paye = 'non'")->fetchColumn();
    expect((float)$total)->toBe(25.0);
});
