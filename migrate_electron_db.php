<?php
/**
 * Exécuter migration sur la DB Electron
 */

// Forcer le chemin DB Electron
$dbPath = 'C:\Users\Dupli\AppData\Roaming\dupli-electron\duplinew.sqlite';

echo "=== MIGRATION SUR DB ELECTRON ===\n";
echo "DB Path: $dbPath\n\n";

if (!file_exists($dbPath)) {
    die("ERREUR: DB n'existe pas à cet emplacement!\n");
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connexion DB OK\n";
    
    // Charger et exécuter migration
    require_once __DIR__ . '/app/models/migrations/add_multi_session_support.php';
    
    echo "Démarrage migration...\n\n";
    migrate_add_multi_session_support($db);
    
    echo "\n✅ Migration terminée!\n";
    
    // Marquer comme appliquée
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration_name TEXT UNIQUE NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO schema_migrations (migration_name) VALUES (?)");
    $stmt->execute(['multi_session_support']);
    
    echo "✅ Migration enregistrée\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
