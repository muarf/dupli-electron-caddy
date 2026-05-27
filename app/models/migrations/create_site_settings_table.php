<?php

function migrate_create_site_settings_table(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_name TEXT PRIMARY KEY,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}
