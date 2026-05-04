<?php
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$manager = new BibliothequeManager();
$db = pdo_connect();

$files = $db->query("SELECT id, filename FROM bibliotheque_files WHERE file_type = 'pdf' AND page_count = 0")->fetchAll(PDO::FETCH_ASSOC);

echo "Réparation des " . count($files) . " fichiers restants...\n";

foreach ($files as $file) {
    echo "Analyse de [ID {$file['id']}] {$file['filename']}... ";
    try {
        $res = $manager->reanalyzeMetadata($file['id']);
        echo ($res ? "OK" : "ÉCHEC") . "\n";
    } catch (Exception $e) {
        echo "ERREUR : " . $e->getMessage() . "\n";
    }
}
