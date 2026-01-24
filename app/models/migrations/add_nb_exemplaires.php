<?php
/**
 * Migration: Ajouter colonne nb_exemplaires
 * 
 * Permet de persister le nombre d'exemplaires pour recalculer
 * correctement le nombre de pages par exemplaire lors du rechargement.
 */

function migrate_add_nb_exemplaires($db) {
    echo "➡️  Ajout colonne nb_exemplaires...\n";
    
    $tables = ['photocop', 'dupli'];
    
    foreach ($tables as $table) {
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$check->fetch()) {
            continue;
        }
        
        // Vérifier si colonne existe déjà
        $pragma = $db->query("PRAGMA table_info($table)");
        $columns = $pragma->fetchAll(PDO::FETCH_ASSOC);
        $column_exists = false;
        
        foreach ($columns as $col) {
            if ($col['name'] === 'nb_exemplaires') {
                $column_exists = true;
                break;
            }
        }
        
        if (!$column_exists) {
            try {
                $db->exec("ALTER TABLE $table ADD COLUMN nb_exemplaires INTEGER DEFAULT 1");
                echo "   ✓ Colonne nb_exemplaires ajoutée à $table\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erreur ajout colonne à $table: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Colonne nb_exemplaires déjà présente dans $table\n";
        }
    }
}
