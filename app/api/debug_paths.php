<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../controler/functions/paths.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';

$res = [
    'DUPLICATOR_DB_PATH' => getenv('DUPLICATOR_DB_PATH'),
    'getDataDir' => getDataDir(),
    'getBibliothequeDir' => getBibliothequeDir(),
    'PHP_OS_FAMILY' => PHP_OS_FAMILY,
    'DIRECTORY_SEPARATOR' => DIRECTORY_SEPARATOR,
    'CWD' => getcwd(),
];

echo json_encode($res, JSON_PRETTY_PRINT);
