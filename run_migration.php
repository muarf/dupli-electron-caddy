<?php
/**
 * Forcer l'exécution de la migration multi-session
 */

require_once __DIR__ . '/app/controler/functions/database.php';
require_once __DIR__ . '/app/models/migrations/add_multi_session_support.php';

echo "=== EXÉCUTION MIGRATION MULTI-SESSION ===\n\n";

try {
    $db = pdo_connect();
    
    echo "Connexion DB OK\n";
    echo "Démarrage migration...\n\n";
    
    // Exécuter la migration
    migrate_add_multi_session_support($db);
    
    echo "\n✅ Migration terminée avec succès!\n";
    
    // Marquer comme appliquée
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration_name TEXT UNIQUE NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO schema_migrations (migration_name) VALUES (?)");
    $stmt->execute(['multi_session_support']);
    
    echo "✅ Migration enregistrée dans schema_migrations\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
