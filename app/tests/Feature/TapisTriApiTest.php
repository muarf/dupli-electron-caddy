<?php

beforeEach(function () {
    require_once __DIR__ . '/../../controler/functions/database.php';
    require_once __DIR__ . '/../../controler/functions/tirage.php';

    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    // Mettre à niveau print_jobs avec les colonnes manquantes du test helper
    $columnsToAdd = [
        "ALTER TABLE print_jobs ADD COLUMN copies INTEGER DEFAULT 1",
        "ALTER TABLE print_jobs ADD COLUMN duplex INTEGER DEFAULT 0",
        "ALTER TABLE print_jobs ADD COLUMN color_mode TEXT DEFAULT 'Monochrome'",
        "ALTER TABLE print_jobs ADD COLUMN fill_rate REAL DEFAULT 0.05",
        "ALTER TABLE print_jobs ADD COLUMN paper_size TEXT DEFAULT 'A4'",
        "ALTER TABLE print_jobs ADD COLUMN document_display_name TEXT",
        "ALTER TABLE print_jobs ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE print_jobs ADD COLUMN contact TEXT"
    ];

    foreach ($columnsToAdd as $sql) {
        try {
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            // Déjà existante
        }
    }

    $this->pdo->exec("
        CREATE TABLE IF NOT EXISTS recorded_print_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id TEXT NOT NULL,
            printer_name TEXT NOT NULL,
            print_job_id INTEGER,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS printer_mappings (
            system_printer_name TEXT PRIMARY KEY,
            machine_type TEXT NOT NULL,
            machine_id INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS prix (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            machine_type TEXT NOT NULL,
            machine_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            unite REAL NOT NULL,
            pack INTEGER NOT NULL
        );
    ");

    // Données de base
    $this->pdo->exec("
        INSERT INTO photocopieurs (id, marque, modele, type_encre, actif) VALUES (1, 'comcolor', 'FW5230', 'encre', 1);
        INSERT INTO duplicopieurs (id, marque, modele, actif) VALUES (18, 'Duplicopieur', 'Duplicopieur', 1);
        INSERT INTO printer_mappings (system_printer_name, machine_type, machine_id) VALUES ('RISO ComColor FW5230', 'photocop', 1);
    ");
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }

    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('liste les jobs récents du tapis de tri', function () {
    $this->pdo->exec("
        INSERT INTO print_jobs (job_id, document, printer_name, status, total_pages, copies, calculated_price, thumbnail_url, timestamp, created_at)
        VALUES ('101', 'brochure.pdf', 'RISO ComColor FW5230', 'completed', 20, 2, 2.50, '/thumb1.png', '2026-10-18T10:00:00Z', datetime('now'));
    ");

    $_GET = ['action' => 'list', 'tapis_tri' => '1'];
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start();
    require __DIR__ . '/../../api/tapis_tri.php';
    $output = ob_get_clean();

    $data = json_decode($output, true);
    expect($data)->toHaveKey('success', true);
    expect($data['jobs'])->toHaveCount(1);
    expect($data['jobs'][0]['document'])->toBe('brochure.pdf');
    expect((float)$data['jobs'][0]['calculated_price'])->toBe(2.50);
});

it('enregistre un tirage avec contact et payé en espèces', function () {
    $this->pdo->exec("
        INSERT INTO print_jobs (id, job_id, document, printer_name, status, total_pages, copies, calculated_price, thumbnail_url, timestamp, created_at)
        VALUES (42, '102', 'affiche.pdf', 'RISO ComColor FW5230', 'completed', 50, 1, 5.00, '/thumb2.png', '2026-10-18T10:00:00Z', datetime('now'));
    ");

    $_GET = ['action' => 'save', 'tapis_tri' => '1'];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    // Mock direct dans $_POST
    $_POST = [
        'action' => 'save',
        'contact' => 'Camarade Jean',
        'paye' => 'oui',
        'cb' => 5.00,
        'mot' => 'Affiches manif',
        'job_ids' => [42]
    ];

    ob_start();
    require __DIR__ . '/../../api/tapis_tri.php';
    $output = ob_get_clean();

    $res = json_decode($output, true);
    expect($res)->toHaveKey('success', true);
    expect($res['contact'])->toBe('Camarade Jean');
    expect($res['paye'])->toBe('oui');

    // Vérifier l'insertion dans la table photocop
    $row = $this->pdo->query("SELECT * FROM photocop WHERE contact = 'Camarade Jean'")->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeEmpty();
    expect($row['paye'])->toBe('oui');
    expect((float)$row['cb'])->toBe(5.00); // Montant payé (combien ils ont payé)
    expect((float)$row['prix'])->toBe(5.00);

    // Vérifier que le job est clôturé dans recorded_print_jobs
    $rec = $this->pdo->query("SELECT * FROM recorded_print_jobs WHERE print_job_id = 42")->fetch(PDO::FETCH_ASSOC);
    expect($rec)->not->toBeEmpty();
});

it('enregistre un tirage non paye avec cb a 0', function () {
    $this->pdo->exec("
        INSERT INTO print_jobs (id, job_id, document, printer_name, status, total_pages, copies, calculated_price, thumbnail_url, timestamp, created_at)
        VALUES (43, '103', 'tract.pdf', 'RISO ComColor FW5230', 'completed', 10, 1, 1.20, '/thumb3.png', '2026-10-18T10:00:00Z', datetime('now'));
    ");

    $_GET = ['action' => 'save', 'tapis_tri' => '1'];
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $_POST = [
        'action' => 'save',
        'contact' => 'Camarade Claire',
        'paye' => 'non',
        'mot' => 'A payer plus tard',
        'job_ids' => [43]
    ];

    ob_start();
    require __DIR__ . '/../../api/tapis_tri.php';
    $output = ob_get_clean();

    $res = json_decode($output, true);
    expect($res)->toHaveKey('success', true);
    expect($res['contact'])->toBe('Camarade Claire');
    expect($res['paye'])->toBe('non');

    $row = $this->pdo->query("SELECT * FROM photocop WHERE contact = 'Camarade Claire'")->fetch(PDO::FETCH_ASSOC);
    expect($row)->not->toBeEmpty();
    expect($row['paye'])->toBe('non');
    expect((float)$row['cb'])->toBe(0.00); // 0 quand non payé
    expect((float)$row['prix'])->toBe(1.20);
});

