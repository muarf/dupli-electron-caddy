<?php
/**
 * API pour récupérer les dernières lignes du log de migration Markdown.
 */
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

$logFile = __DIR__ . '/../logs/markdown_migration.log';

if (!file_exists($logFile)) {
    echo "Aucun log disponible pour le moment.";
    exit;
}

// On récupère les 30 dernières lignes
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false || count($lines) === 0) {
    echo "Le fichier de log est vide.";
    exit;
}

$lastLines = array_slice($lines, -50);
echo implode("\n", $lastLines);
