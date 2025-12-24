<?php
require_once __DIR__ . '/app/controler/functions/database.php';

echo "Cr\u00e9ation table print_jobs...\n";

try {
    $db = pdo_connect();
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS print_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id TEXT NOT NULL,
            document TEXT NOT NULL,
            owner TEXT,
            printer_name TEXT NOT NULL,
            status TEXT NOT NULL,
            pages_printed INTEGER DEFAULT 0,
            total_pages INTEGER DEFAULT 0,
            size INTEGER DEFAULT 0,
            time_submitted TEXT,
            event_type TEXT,
            timestamp TEXT NOT NULL,
            fill_rate REAL DEFAULT 0,
            color_mode TEXT DEFAULT 'unknown',
            duplex INTEGER DEFAULT 0,
            thumbnail_url TEXT,
            paper_size TEXT,
            copies INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(job_id, printer_name, timestamp)
        )
    ");
    
    echo "\u2705 Table print_jobs cr\u00e9\u00e9e avec succ\u00e8s!\n";
    
    // V\u00e9rifier
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='print_jobs'")->fetchAll();
    echo "V\u00e9rification: " . (count($tables) > 0 ? "\u2713 Table existe" : "\u274c Table n'existe pas") . "\n";
    
} catch (Exception $e) {
    echo "\u274c ERREUR: {$e->getMessage()}\n";
}
