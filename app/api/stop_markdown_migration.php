<?php
/**
 * API pour stopper la migration Markdown en cours.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

// Tuer le processus
if (PHP_OS_FAMILY === 'Windows') {
    // Sur Windows, on cherche les process php.exe avec process_markdown_chunks
    exec('wmic process where "commandline like \'%process_markdown_chunks%\' and name=\'php.exe\'" delete', $output, $returnCode);
} else {
    // Sur Linux
    exec('pkill -f "process_markdown_chunks.php"', $output, $returnCode);
}

// Mettre à jour le statut
$statusFile = __DIR__ . '/../logs/markdown_status.json';
if (file_exists($statusFile)) {
    $statusData = json_decode(file_get_contents($statusFile), true);
    if ($statusData) {
        $statusData['running'] = false;
        $statusData['stopped_at'] = date('Y-m-d H:i:s');
        file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// Ajouter un log
$logFile = __DIR__ . '/../logs/markdown_migration.log';
$line = "[" . date('Y-m-d H:i:s') . "] === STOP demandé par l'utilisateur ===\n";
@file_put_contents($logFile, $line, FILE_APPEND);

echo json_encode(['success' => true, 'message' => 'Migration stoppée.']);
