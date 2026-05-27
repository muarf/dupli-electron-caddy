<?php
/**
 * Script de maintenance SÉCURISÉ pour re-analyser la bibliothèque
 * (Pages GS + Tags Fréquence, SANS toucher aux vecteurs)
 */
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$manager = new BibliothequeManager();
$db = pdo_connect();

$files = $db->query("SELECT id FROM bibliotheque_files")->fetchAll(PDO::FETCH_ASSOC);

echo "Démarrage du nettoyage sécurisé (METADATA ONLY) de " . count($files) . " fichiers...\n";

foreach ($files as $file) {
    echo ".";
    $manager->reanalyzeMetadata($file['id']);
}

echo "\nFini ! Métadonnées à jour sans risque pour les vecteurs.\n";
