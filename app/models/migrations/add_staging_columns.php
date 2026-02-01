<?php
/**
 * Migration: Ajouter les colonnes de staging à print_jobs
 * 
 * Permet d'utiliser print_jobs comme table intermédiaire avant insertion
 * dans les tables définitives (photocop, dupli).
 */

function migrate_add_staging_columns($db) {
    echo "➡️  Ajout des colonnes staging à print_jobs...\n";
    
    // Liste des colonnes à ajouter
    $columns = [
        'session_id' => 'INTEGER',
        'calculated_price' => 'REAL DEFAULT 0',
        'machine_type' => 'TEXT',
        'machine_id' => 'INTEGER',
        'machine_name' => 'TEXT',
        'contact' => 'TEXT',
        'staged' => 'INTEGER DEFAULT 0'
    ];
    
    // Vérifier si la table print_jobs existe
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='print_jobs'");
    if (!$tableCheck->fetch()) {
        echo "   ⚠️  Table print_jobs n'existe pas encore, skip\n";
        return;
    }
    
    // Récupérer les colonnes existantes
    $pragma = $db->query("PRAGMA table_info(print_jobs)");
    $existingColumns = [];
    while ($col = $pragma->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['name'];
    }
    
    // Ajouter les colonnes manquantes
    foreach ($columns as $colName => $colType) {
        if (!in_array($colName, $existingColumns)) {
            try {
                $db->exec("ALTER TABLE print_jobs ADD COLUMN $colName $colType");
                echo "   ✓ Colonne $colName ajoutée\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erreur ajout colonne $colName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Colonne $colName déjà existe\n";
        }
    }
    
    // Créer index sur staged pour performances
    try {
        $db->exec("CREATE INDEX IF NOT EXISTS idx_print_jobs_staged ON print_jobs(staged)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_print_jobs_session ON print_jobs(session_id)");
        echo "   ✓ Index créés\n";
    } catch (Exception $e) {
        // Index existe déjà, ignorer
    }
    
    echo "✅ Migration staging terminée\n\n";
}
