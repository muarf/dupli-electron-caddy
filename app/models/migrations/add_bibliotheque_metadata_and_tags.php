<?php
/**
 * Migration : Ajouter metadata_json et tags à la table bibliotheque_files
 */
function migrate_add_bibliotheque_metadata_and_tags($db) {
    error_log("[MIGRATION] Ajout des colonnes metadata_json et tags à bibliotheque_files");

    // 1. Ajouter metadata_json
    try {
        // En SQLite, on ne peut pas utiliser IF NOT EXISTS dans ALTER TABLE, 
        // donc on utilise un try/catch ou on vérifie via PRAGMA (fait dans applyMigration)
        $db->exec("ALTER TABLE bibliotheque_files ADD COLUMN metadata_json TEXT");
        error_log("[MIGRATION] Colonne metadata_json ajoutée");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            error_log("[MIGRATION] La colonne metadata_json existe déjà");
        } else {
            error_log("[MIGRATION] Erreur metadata_json: " . $e->getMessage());
        }
    }

    // 2. Ajouter tags
    try {
        $db->exec("ALTER TABLE bibliotheque_files ADD COLUMN tags TEXT");
        error_log("[MIGRATION] Colonne tags ajoutée");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            error_log("[MIGRATION] La colonne tags existe déjà");
        } else {
            error_log("[MIGRATION] Erreur tags: " . $e->getMessage());
        }
    }
}
