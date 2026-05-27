<?php

// Usage: php run_setup_action.php '{"dbPath":"/tmp/foo.sqlite","post":{...}}'

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $payload = $argv[1] ?? '{}';
    $config = json_decode($payload, true);
    if (!is_array($config)) {
        $config = [];
    }

    $dbPath = $config['dbPath'] ?? null;
    $post = $config['post'] ?? [];

    if (!$dbPath) {
        fwrite(STDERR, "dbPath manquant\n");
        exit(2);
    }

// Définir la variable d'environnement AVANT tous les includes pour que conf.php l'utilise
putenv('DUPLICATOR_DB_PATH=' . $dbPath);
$_ENV['DUPLICATOR_DB_PATH'] = $dbPath;

$_GET = [];
$_POST = $post;
$_FILES = [];
$_COOKIE = [];
$_SESSION = [];

$_SERVER = array_merge([
    'REQUEST_METHOD' => 'POST',
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/setup',
    'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
], $config['server'] ?? []);

$conf = [
    'db_type' => 'sqlite',
    'db_path' => $dbPath,
    'dsn' => 'sqlite:' . $dbPath,
    'login' => '',
    'pass' => '',
    'uploaddir' => sys_get_temp_dir() . '/',
];

// Rendre la conf visible par pdo_connect()
$GLOBALS['conf'] = $conf;

// Créer un fichier SQLite vide à l'emplacement demandé pour éviter la création de duplinew.sqlite
if (!file_exists($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Fermer immédiatement; setup_save créera la structure ensuite
    $pdo = null;
}

// Ne rien afficher ici pour éviter d'envoyer des headers avant les redirect du setup

require_once dirname(__DIR__, 1) . '/Feature/Support/PdfTestHelpers.php';
require_once dirname(__DIR__, 2) . '/controler/functions/database.php';
require_once dirname(__DIR__, 2) . '/controler/functions/machines.php';
require_once dirname(__DIR__, 2) . '/models/setup_save.php';

// Redéfinir la conf après tous les includes (qui peuvent avoir inclus conf.php)
$GLOBALS['conf'] = $conf;

// Exécuter le setup; en CLI, Action() retourne un statut au lieu de rediriger
$status = Action($conf);

// Émettre un JSON minimal pour permettre au test de récupérer le chemin DB utilisé
echo json_encode([
    'status' => $status,
    'db_used' => $dbPath,
]) . PHP_EOL;
}


