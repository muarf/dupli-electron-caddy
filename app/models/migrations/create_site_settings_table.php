<?php

function migrate_create_site_settings_table(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_name TEXT PRIMARY KEY,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Vérification post-création
    $checkQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_settings'");
    if (!$checkQuery || !$checkQuery->fetch()) {
        throw new Exception("ERREUR: La table 'site_settings' n'a pas pu être créée.");
    }
}
