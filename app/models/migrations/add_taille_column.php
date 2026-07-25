<?php
/**
 * Migration: Ajouter colonne taille aux tables photocop et dupli
 * 
 * Stocke la taille du papier (A3/A4) pour chaque tirage.
 * Utilise COALESCE(?, 'A4') dans les requêtes existantes comme fallback,
 * mais la colonne doit exister pour que les INSERT fonctionnent.
 */

function migrate_add_taille_column($db) {
    echo "➡️  Ajout colonne taille aux tables photocop et dupli...\n";
    
    $tables = ['photocop', 'dupli'];
    
    foreach ($tables as $table) {
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$check->fetch()) {
            echo "   ⚠️  Table $table n'existe pas, skip\n";
            continue;
        }
        
        $pragma = $db->query("PRAGMA table_info($table)");
        $columns = $pragma->fetchAll(PDO::FETCH_ASSOC);
        $column_exists = false;
        
        foreach ($columns as $col) {
            if ($col['name'] === 'taille') {
                $column_exists = true;
                break;
            }
        }
        
        if (!$column_exists) {
            try {
                $db->exec("ALTER TABLE $table ADD COLUMN taille TEXT DEFAULT 'A3'");
                echo "   ✓ Colonne taille ajoutée à $table\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erreur ajout colonne taille à $table: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Colonne taille déjà présente dans $table\n";
        }
    }
    
    echo "✅ Migration add_taille_column terminée\n\n";
}
