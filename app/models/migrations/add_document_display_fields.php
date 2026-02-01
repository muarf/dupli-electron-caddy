<?php
/**
 * Migration: Ajouter colonnes document_full_path et document_display_name
 * 
 * Permet de séparer le chemin complet (trace/debug) du nom d'affichage (UI)
 * pour résoudre le problème d'affichage des chemins longs dans l'interface.
 */

function migrate_add_document_display_fields($db) {
    echo "➡️  Ajout colonnes document_full_path et document_display_name à print_jobs...\n";
    
    // Vérifier si la table print_jobs existe
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='print_jobs'");
    if (!$check->fetch()) {
        echo "   ⚠️  Table print_jobs n'existe pas, skip\n";
        return;
    }
    
    // Colonnes à ajouter
    $cols = [
        'document_full_path' => 'TEXT',
        'document_display_name' => 'TEXT'
    ];
    
    foreach ($cols as $colName => $colType) {
        $pragma = $db->query("PRAGMA table_info(print_jobs)");
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
                $db->exec("ALTER TABLE print_jobs ADD COLUMN $colName $colType");
                echo "   ✓ Colonne $colName ajoutée à print_jobs\n";
            } catch (Exception $e) {
                echo "   ⚠️  Erreur ajout colonne $colName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✓ Colonne $colName déjà présente dans print_jobs\n";
        }
    }
    
    // Remplir les nouvelles colonnes pour les données existantes
    echo "   ➡️  Migration des données existantes...\n";
    try {
        // Récupérer tous les jobs existants qui n'ont pas encore les nouveaux champs remplis
        $jobs = $db->query("SELECT id, document FROM print_jobs WHERE document_full_path IS NULL OR document_display_name IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($jobs) > 0) {
            $stmt = $db->prepare("UPDATE print_jobs SET document_full_path = ?, document_display_name = ? WHERE id = ?");
            
            foreach ($jobs as $job) {
                $fullPath = $job['document'];
                $displayName = basename($fullPath); // Utilise PHP basename() qui gère à la fois \ et /
                
                $stmt->execute([$fullPath, $displayName, $job['id']]);
            }
            
            echo "   ✓ " . count($jobs) . " enregistrement(s) migré(s)\n";
        } else {
            echo "   ✓ Aucun enregistrement à migrer\n";
        }
    } catch (Exception $e) {
        echo "   ⚠️  Erreur migration données: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Migration add_document_display_fields terminée\n\n";
}
