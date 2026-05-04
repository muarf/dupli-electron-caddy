<?php
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$db = pdo_connect();
$files = $db->query("SELECT id, filepath FROM bibliotheque_files")->fetchAll(PDO::FETCH_ASSOC);

$missing = 0;
foreach ($files as $file) {
    if (!file_exists($file['filepath'])) {
        $missing++;
    }
}

echo "Total en base : " . count($files) . "\n";
echo "Fichiers manquants sur le disque : $missing\n";
