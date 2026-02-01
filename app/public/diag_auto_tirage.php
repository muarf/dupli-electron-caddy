<?php
require_once __DIR__ . '/../controler/functions/database.php';

try {
    $db = pdo_connect();
    
    echo "=== Recherche dans PHOTOCOP ===\n";
    $stmt = $db->prepare("SELECT DISTINCT mot FROM photocop WHERE mot LIKE 'Auto:%' LIMIT 20");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        print_r($row);
    }
    
    echo "\n=== Recherche dans DUPLI ===\n";
    $stmt = $db->prepare("SELECT DISTINCT mot FROM dupli WHERE mot LIKE 'Auto:%' LIMIT 20");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
