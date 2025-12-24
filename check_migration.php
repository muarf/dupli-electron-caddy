<?php
/**
 * Script diagnostic - Vérifier état migration multi-session
 */

require_once __DIR__ . '/app/controler/functions/database.php';

echo "=== DIAGNOSTIC MIGRATION MULTI-SESSION ===\n\n";

try {
    $db = pdo_connect();
    
    // 1. Vérifier table print_sessions
    echo "1. Table print_sessions : ";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='print_sessions'")->fetchAll();
    if (count($tables) > 0) {
        echo "✓ EXISTE\n";
        
        // Afficher structure
        $schema = $db->query("PRAGMA table_info(print_sessions)")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Colonnes: ";
        foreach ($schema as $col) {
            echo $col['name'] . ", ";
        }
        echo "\n";
    } else {
        echo "✗ N'EXISTE PAS\n";
    }
    
    // 2. Vérifier colonne session_id dans photocop
    echo "\n2. Colonne session_id dans photocop : ";
    $photocopCols = $db->query("PRAGMA table_info(photocop)")->fetchAll(PDO::FETCH_ASSOC);
    $hasSessionId = false;
    foreach ($photocopCols as $col) {
        if ($col['name'] === 'session_id') {
            $hasSessionId = true;
            break;
        }
    }
    echo $hasSessionId ? "✓ EXISTE\n" : "✗ N'EXISTE PAS\n";
    
    // 3. Vérifier colonne session_id dans dupli
    echo "\n3. Colonne session_id dans dupli : ";
    $dupliCols = $db->query("PRAGMA table_info(dupli)")->fetchAll(PDO::FETCH_ASSOC);
    $hasSessionIdDupli = false;
    foreach ($dupliCols as $col) {
        if ($col['name'] === 'session_id') {
            $hasSessionIdDupli = true;
            break;
        }
    }
    echo $hasSessionIdDupli ? "✓ EXISTE\n" : "✗ N'EXISTE PAS\n";
    
    // 4. Vérifier schema_migrations
    echo "\n4. Migrations appliquées : ";
    $migrations = $db->query("SELECT migration_name, applied_at FROM schema_migrations ORDER BY applied_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo count($migrations) . " total\n";
    foreach ($migrations as $mig) {
        echo "   - " . $mig['migration_name'] . " (" . $mig['applied_at'] . ")\n";
    }
    
    echo "\n=== FIN DIAGNOSTIC ===\n";
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
