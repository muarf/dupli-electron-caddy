<?php
/**
 * Migration: Ajouter le support multi-session pour les impressions
 * 
 * Cette migration crée:
 * - Table print_sessions (1 contact = 1 session active max)
 * - Colonnes session_id dans photocop, dupli, print_jobs_raw
 * - Index pour performances
 */

function migrate_add_multi_session_support($db) {
    echo "➡️  Ajout support multi-session...\n";
    
    // 1. Créer table print_sessions si n'existe pas
    $db->exec("
        CREATE TABLE IF NOT EXISTS print_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contact TEXT NOT NULL,
            session_name TEXT,
            opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME NULL,
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'closed')),
            total_price REAL DEFAULT 0.0,
            notes TEXT
        )
    ");
    echo "   ✓ Table print_sessions créée\n";
    
    // 2. Créer index unique pour contrainte: 1 contact = 1 session active
    // Note: SQLite index avec WHERE clause nécessite version >= 3.8.0
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_sessions_active_contact 
                   ON print_sessions(contact) WHERE status = 'active'");
        echo "   ✓ Index unique contact actif créé\n";
    } catch (Exception $e) {
        // Si échec (vieille version SQLite), créer index simple
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_contact ON print_sessions(contact)");
        echo "   ⚠️  Index simple créé (contrainte unique sera gérée par code)\n";
    }
    
    // 3. Index status pour requêtes filtrées
    $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_status ON print_sessions(status)");
    
    // 4. Ajouter colonne session_id aux tables existantes
    $tables_to_update = [
        'photocop' => 'Table photocopieurs',
        'dupli' => 'Table duplicopieurs',
        'print_jobs_raw' => 'Table jobs bruts'
    ];
    
    foreach ($tables_to_update as $table => $description) {
        // Vérifier si table existe
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$check->fetch()) {
            echo "   ⚠️  Table $table n'existe pas encore, skip\n";
            continue;
        }
        
        // Vérifier si colonne existe déjà
        $pragma = $db->query("PRAGMA table_info($table)");
        $columns = $pragma->fetchAll(PDO::FETCH_ASSOC);
        $column_exists = false;
        
        foreach ($columns as $col) {
            if ($col['name'] === 'session_id') {
                $column_exists = true;
                break;
            }
        }
        
        if (!$column_exists) {
            try {
                $db->exec("ALTER TABLE $table ADD COLUMN session_id INTEGER REFERENCES print_sessions(id)");
                echo "   ✓ Colonne session_id ajoutée à $description\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erreur ajout colonne à $table: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Colonne session_id déjà existe dans $description\n";
        }
        
        // Créer index pour performances
        $index_name = "idx_{$table}_session";
        try {
            $db->exec("CREATE INDEX IF NOT EXISTS $index_name ON $table(session_id)");
        } catch (Exception $e) {
            // Index existe déjà ou autre erreur, ignorer
        }
    }
    
    echo "   ✓ Index créés pour toutes les tables\n";
    echo "✅ Migration multi-session terminée\n\n";
}
