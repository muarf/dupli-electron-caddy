<?php

// Usage: php run_setup_action.php '{"dbPath":"/tmp/foo.sqlite","post":{...}}'

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

require_once dirname(__DIR__, 1) . '/Feature/Support/PdfTestHelpers.php';
require_once dirname(__DIR__, 2) . '/controler/functions/database.php';
require_once dirname(__DIR__, 2) . '/models/setup_save.php';

// Exécute le setup; cela peut faire des header() et exit(), ce qui est acceptable ici.
Action($conf);


