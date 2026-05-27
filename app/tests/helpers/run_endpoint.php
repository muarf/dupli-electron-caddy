<?php

if (isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    $scriptPath = $argv[1] ?? null;
    $payload = $argv[2] ?? '{}';

    if ($scriptPath === null || !file_exists($scriptPath)) {
        fwrite(STDERR, "Script introuvable\n");
        exit(1);
    }

    $config = json_decode($payload, true);
    if (!is_array($config)) {
        $config = [];
    }

    $_GET = $config['get'] ?? [];
    $_POST = $config['post'] ?? [];
    $_FILES = $config['files'] ?? [];
    $_COOKIE = $config['cookie'] ?? [];
    $_SESSION = [];

    $serverDefaults = [
        'REQUEST_METHOD' => $config['method'] ?? 'GET',
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => '/' . basename($scriptPath),
        'CONTENT_TYPE' => $config['content_type'] ?? 'application/x-www-form-urlencoded',
    ];

    $_SERVER = array_merge($serverDefaults, $config['server'] ?? []);

    if (!empty($config['conf'])) {
        $GLOBALS['conf'] = $config['conf'];
    }

    if (!empty($config['env']) && is_array($config['env'])) {
        foreach ($config['env'] as $key => $value) {
            putenv($key . '=' . $value);
            $_SERVER[$key] = $value;
        }
    }

    if (!empty($config['session'])) {
        $sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_test_sessions';
        if (!is_dir($sessionPath)) {
            mkdir($sessionPath, 0777, true);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_save_path($sessionPath);
        $sessionId = $config['session_id'] ?? ('test' . bin2hex(random_bytes(8)));
        session_id($sessionId);
        session_start();
        $_SESSION = $config['session'];
        session_write_close();
        $_COOKIE[session_name()] = $sessionId;
    }

    require $scriptPath;
}
