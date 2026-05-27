<?php
/**
 * Migration: Ajouter colonnes document_name et thumbnail_url
 * 
 * Permet de persister le nom original du document et l'aperçu PDF
 * même après redémarrage ou clôture de session.
 */

function migrate_persist_job_extra_data($db) {
    echo "➡️  Ajout colonnes extra (document_name, thumbnail_url)...\n";
    
    $tables = ['photocop', 'dupli'];
    
    foreach ($tables as $table) {
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$check->fetch()) {
            echo "   ⚠️  Table $table n'existe pas, skip\n";
            continue;
        }
        
        // Colonnes à ajouter
        $cols = [
            'document_name' => 'TEXT',
            'thumbnail_url' => 'TEXT',
            'nb_exemplaires' => 'INTEGER DEFAULT 1'
        ];
        
        foreach ($cols as $colName => $colType) {
            $pragma = $db->query("PRAGMA table_info($table)");
            $columns = $pragma->fetchAll(PDO::FETCH_ASSOC);
            $column_exists = false;
            
            foreach ($columns as $col) {
                if ($col['name'] === $colName) {
                    $column_exists = true;
                    break;
                }
            }
            
            if (!$column_exists) {
                try {
                    $db->exec("ALTER TABLE $table ADD COLUMN $colName $colType");
                    echo "   ✓ Colonne $colName ajoutée à $table\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Erreur ajout colonne $colName à $table: " . $e->getMessage() . "\n";
                }
            } else {
                echo "   ✓ Colonne $colName déjà présente dans $table\n";
            }
        }
    }
    
    echo "✅ Migration persist_job_extra_data terminée\n\n";
}
