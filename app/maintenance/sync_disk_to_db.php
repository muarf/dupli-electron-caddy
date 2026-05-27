<?php
/**
 * Script pour synchroniser les fichiers du disque vers la base
 * (Ajoute ce qui manque sans toucher à l'existant)
 */
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$manager = new BibliothequeManager();
$db = pdo_connect();

// 1. Lister tous les PDF du disque
$dir = __DIR__ . '/../bibliotheque';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$diskFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
        $diskFiles[] = realpath($file->getPathname());
    }
}

// 2. Lister tous les PDF de la base
$stmt = $db->query("SELECT filepath FROM bibliotheque_files");
$dbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Trouver les manquants
$missing = array_diff($diskFiles, $dbFiles);

echo "Fichiers sur le disque : " . count($diskFiles) . "\n";
echo "Fichiers en base : " . count($dbFiles) . "\n";
echo "Fichiers à ajouter : " . count($missing) . "\n";

foreach ($missing as $path) {
    echo "Ajout de : " . basename($path) . "... ";
    try {
        $manager->addExternalFile($path, false); // false = NE PAS FORCER si déjà là
        echo "OK\n";
    } catch (Exception $e) {
        echo "ERREUR : " . $e->getMessage() . "\n";
    }
}

echo "Synchronisation terminée.\n";
