<?php
require_once __DIR__ . '/app/controler/functions/database.php';

try {
    $db = pdo_connect();
    
    echo "--- Structure de la table 'photocop' ---\n";
    $stmt = $db->query("PRAGMA table_info(photocop)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['name'] . " (" . $col['type'] . ")\n";
    }
    
    echo "\n--- Structure de la table 'dupli' ---\n";
    $stmt = $db->query("PRAGMA table_info(dupli)");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['name'] . " (" . $col['type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
