<?php

if (!isset($argc) || php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Payload manquant.\n");
    exit(1);
}

$payloadFile = $argv[1];
if (!file_exists($payloadFile)) {
    fwrite(STDERR, "Fichier payload introuvable: {$payloadFile}\n");
    exit(1);
}

$payload = json_decode(file_get_contents($payloadFile), true);
if (!is_array($payload)) {
    fwrite(STDERR, "Payload JSON invalide.\n");
    exit(1);
}

$_POST = $payload['post'] ?? [];
$_FILES = [];

if (isset($payload['files']) && is_array($payload['files'])) {
    foreach ($payload['files'] as $key => $fileInfo) {
        if (!file_exists($fileInfo['tmp_name'])) {
            fwrite(STDERR, "Fichier temporaire introuvable pour {$key}: {$fileInfo['tmp_name']}\n");
            exit(1);
        }
        $_FILES[$key] = [
            'name' => $fileInfo['name'],
            'type' => $fileInfo['type'],
            'tmp_name' => $fileInfo['tmp_name'],
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($fileInfo['tmp_name']),
        ];
    }
}

// Support pour les uploads de fichiers multiples (comme merge ou riso layers)
if (isset($payload['multi_files']) && is_array($payload['multi_files'])) {
    foreach ($payload['multi_files'] as $key => $filesArray) {
        $_FILES[$key] = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => [],
        ];
        foreach ($filesArray as $i => $fileInfo) {
            $_FILES[$key]['name'][$i] = $fileInfo['name'];
            $_FILES[$key]['type'][$i] = $fileInfo['type'];
            $_FILES[$key]['tmp_name'][$i] = $fileInfo['tmp_name'];
            $_FILES[$key]['error'][$i] = UPLOAD_ERR_OK;
            $_FILES[$key]['size'][$i] = filesize($fileInfo['tmp_name']);
        }
    }
}

$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false) {
    fwrite(STDERR, "Impossible de déterminer la racine du projet.\n");
    exit(1);
}

// Se positionner dans public/ pour simuler un appel HTTP direct
chdir($projectRoot . '/public');

// Exécuter le point d'entrée studio_process
require_once $projectRoot . '/api/studio_process.php';
