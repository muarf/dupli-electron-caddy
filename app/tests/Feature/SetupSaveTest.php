<?php

require_once __DIR__ . '/../../models/admin/MachineManager.php';

function run_setup_with_payload(string $dbPath, array $post): int
{
    $runner = realpath(__DIR__ . '/../helpers/run_setup_action.php');
    $payload = [
        'dbPath' => $dbPath,
        'post' => $post,
    ];
    $cmd = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg($runner) . ' ' .
        escapeshellarg(json_encode($payload));
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    return $exitCode;
}

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    // On part d'un fichier supprimé pour simuler une base inexistante
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('crée la base et insère les machines lors du setup', function () {
    $post = [
        'admin_password' => 'supersecure',
        'admin_password_confirm' => 'supersecure',
        'machines' => [
            [
                'type' => 'duplicopieur',
                'name' => 'Riso SF',
                'master_counter' => 100,
                'passage_counter' => 200,
                'tambours' => [
                    ['name' => 'tambour_noir', 'unite' => 0.002, 'pack' => 11],
                ],
                'prix_master_unite' => 0.4,
                'prix_master_pack' => 0.4,
            ],
        ],
        // Prix requis par le formulaire actuel
        'prix_papier_A3' => 1.0,
    ];

    $code = run_setup_with_payload($this->dbPath, $post);
    expect($code)->toBe(0);
    expect(file_exists($this->dbPath))->toBeTrue();

    $pdo = new PDO('sqlite:' . $this->dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Les machines devraient être présentes
    $hasDuplico = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='duplicopieurs'")->fetchColumn();
    expect($hasDuplico)->toBe(1);
    $dupCount = 0;
    try {
        $dupCount = (int)$pdo->query("SELECT COUNT(*) FROM duplicopieurs")->fetchColumn();
    } catch (Throwable $e) {
        $dupCount = 0;
    }
    expect($dupCount)->toBeGreaterThan(0);
});

it('insère le mot de passe admin lors du setup (test de comportement attendu)', function () {
    $post = [
        'admin_password' => 'supersecure',
        'admin_password_confirm' => 'supersecure',
        'machines' => [
            [
                'type' => 'duplicopieur',
                'name' => 'Riso SF',
                'master_counter' => 100,
                'passage_counter' => 200,
                'tambours' => [
                    ['name' => 'tambour_noir', 'unite' => 0.002, 'pack' => 11],
                ],
                'prix_master_unite' => 0.4,
                'prix_master_pack' => 0.4,
            ],
        ],
        'prix_papier_A3' => 1.0,
    ];

    $code = run_setup_with_payload($this->dbPath, $post);
    expect($code)->toBe(0);

    $pdo = new PDO('sqlite:' . $this->dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Comportement attendu: une entrée active doit exister
    // NOTE: Ce test échouera actuellement à cause d'incompatibilités SQL dans setup_save (diagnostic).
    $hasTable = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='admin_passwords'")->fetchColumn();
    expect($hasTable)->toBe(1);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM admin_passwords WHERE is_active = 1")->fetchColumn();
    expect($count)->toBeGreaterThan(0);
});


