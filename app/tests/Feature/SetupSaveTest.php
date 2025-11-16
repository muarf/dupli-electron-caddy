<?php

require_once __DIR__ . '/../../models/admin/MachineManager.php';

function run_setup_and_get_db_used(string $dbPath, array $post): array
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
    $dbUsed = null;
    $status = null;
    // Parser le JSON final
    $joined = implode("\n", $output);
    $lines = explode("\n", trim($joined));
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded) && isset($decoded['db_used'])) {
            $dbUsed = $decoded['db_used'];
            $status = $decoded['status'] ?? null;
            break;
        }
    }
    return [$exitCode, $dbUsed, $status];
}

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    // On part d'un fichier supprimé pour simuler une base inexistante
    if (file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    // Supprimer également un éventuel duplinew.sqlite dans le même dossier temp
    $dir = dirname($this->dbPath);
    $dupliNew = $dir . DIRECTORY_SEPARATOR . 'duplinew.sqlite';
    if (file_exists($dupliNew)) {
        unlink($dupliNew);
    }
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath)) {
        if (file_exists($this->dbPath)) {
            unlink($this->dbPath);
        }
        // Nettoyer duplinew.sqlite généré par le setup dans ce répertoire
        $dir = dirname($this->dbPath);
        $dupliNew = $dir . DIRECTORY_SEPARATOR . 'duplinew.sqlite';
        if (file_exists($dupliNew)) {
            unlink($dupliNew);
        }
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

    [$code, $dbFile, $status] = run_setup_and_get_db_used($this->dbPath, $post);
    expect($code)->toBe(0);
    expect($status)->toBe('setup_success');
    expect($dbFile)->not->toBeNull();
    expect(file_exists($dbFile))->toBeTrue();

    // Ouvrir la base retournée par le runner
    $pdo = new PDO('sqlite:' . $dbFile);
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

    [$code, $dbFile, $status] = run_setup_and_get_db_used($this->dbPath, $post);
    expect($code)->toBe(0);
    expect($status)->toBe('setup_success');
    expect($dbFile)->not->toBeNull();
    expect(file_exists($dbFile))->toBeTrue();

    // Ouvrir la base retournée par le runner
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Comportement attendu: une entrée active doit exister
    $hasTable = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='admin_passwords'")->fetchColumn();
    expect($hasTable)->toBe(1);
    $count = (int)$pdo->query("SELECT COUNT(*) FROM admin_passwords WHERE is_active = 1")->fetchColumn();
    expect($count)->toBeGreaterThan(0);
});


