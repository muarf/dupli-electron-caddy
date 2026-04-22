<?php

// Empêcher l'exécution si le fichier est inclus par un autre script (ex: scan Pest)
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename(__FILE__) === basename($argv[0])) {
    $payload = $argv[1] ?? '{}';
    $config = json_decode($payload, true);
    if (!is_array($config)) {
        $config = [];
    }

    $_POST = $config['post'] ?? [];
    $_GET = $config['get'] ?? [];
    $_SERVER['REQUEST_METHOD'] = $config['method'] ?? 'POST';

    if (!empty($config['env']) && is_array($config['env'])) {
        foreach ($config['env'] as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    define('DUPLICATOR_DISABLE_DIRECT_OUTPUT', true);

    if (!function_exists('template')) {
        function template($template_file, $variables = [])
        {
            return [
                'template' => $template_file,
                'variables' => $variables,
            ];
        }
    }

    require_once __DIR__ . '/../../controler/functions/database.php';
    require_once __DIR__ . '/../../controler/functions/paths.php';
    require_once __DIR__ . '/../../controler/functions/machines.php';
    require_once __DIR__ . '/../../controler/functions/tirage.php';
    require_once __DIR__ . '/../../models/migrations/DatabaseMigrationManager.php';

    [$dbPath, $pdo] = create_test_sqlite_database();
    configure_sqlite_conf($dbPath);

    require_once __DIR__ . '/../../models/tirage_multimachines.php';

    $result = Action();

    echo json_encode($result);
}

