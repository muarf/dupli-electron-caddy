<?php

if (!function_exists('create_test_sqlite_database')) {
    function create_test_sqlite_database(): array
    {
        // Créer un dossier temporaire unique pour ce test
        $dataDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_test_data_' . uniqid();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        
        $path = $dataDir . DIRECTORY_SEPARATOR . 'duplinew.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        create_essential_tables($pdo);
        
        return [$path, $pdo, $dataDir];
    }
}

if (!function_exists('create_essential_tables')) {
    function create_essential_tables(PDO $db): void
    {
        $tables = [
            'print_sessions' => "CREATE TABLE IF NOT EXISTS print_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact TEXT NOT NULL,
                session_name TEXT,
                opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME NULL,
                status TEXT DEFAULT 'active' CHECK(status IN ('active', 'closed')),
                total_price REAL DEFAULT 0.0,
                notes TEXT
            )",
            'duplicopieurs' => "CREATE TABLE IF NOT EXISTS duplicopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL,
                supporte_a3 INTEGER DEFAULT 1,
                supporte_a4 INTEGER DEFAULT 1,
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                tambours TEXT
            )",
            'photocopieurs' => "CREATE TABLE IF NOT EXISTS photocopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL DEFAULT '',
                type_encre TEXT NOT NULL DEFAULT 'toner' CHECK(type_encre IN ('encre','toner')),
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(marque, modele)
            )",
            'dupli' => "CREATE TABLE IF NOT EXISTS dupli (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT '',
                document_name TEXT NOT NULL DEFAULT '',
                nb_exemplaires INTEGER NOT NULL DEFAULT 1,
                thumbnail_url TEXT DEFAULT NULL,
                type TEXT NOT NULL DEFAULT 'dupli',
                contact TEXT NOT NULL DEFAULT '',
                master_av TEXT NOT NULL DEFAULT '0',
                master_ap TEXT NOT NULL DEFAULT '0',
                passage_av TEXT NOT NULL DEFAULT '0',
                passage_ap TEXT NOT NULL DEFAULT '0',
                rv TEXT NOT NULL DEFAULT 'non',
                prix TEXT NOT NULL DEFAULT '0',
                paye TEXT NOT NULL DEFAULT 'non',
                cb TEXT NOT NULL DEFAULT 'non',
                mot TEXT NOT NULL DEFAULT '',
                date TEXT NOT NULL DEFAULT '',
                nom_machine TEXT DEFAULT 'Duplicopieur',
                duplicopieur_id INTEGER DEFAULT 1,
                tambour TEXT DEFAULT NULL,
                tirage_global_id TEXT DEFAULT NULL,
                session_id INTEGER DEFAULT NULL
            )",
            'photocop' => "CREATE TABLE IF NOT EXISTS photocop (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL DEFAULT 'photocop',
                marque TEXT DEFAULT NULL,
                contact TEXT NOT NULL DEFAULT '',
                nb_f TEXT NOT NULL DEFAULT '0',
                rv TEXT NOT NULL DEFAULT 'non',
                paye TEXT NOT NULL DEFAULT 'non',
                prix TEXT NOT NULL DEFAULT '0',
                cb TEXT NOT NULL DEFAULT 'non',
                mot TEXT NOT NULL DEFAULT '',
                date TEXT NOT NULL DEFAULT '',
                document_name TEXT DEFAULT '',
                thumbnail_url TEXT DEFAULT NULL,
                nb_exemplaires INTEGER DEFAULT 1,
                taille TEXT DEFAULT '',
                tirage_global_id TEXT DEFAULT NULL,
                session_id INTEGER DEFAULT NULL
            )",
            'print_jobs' => "CREATE TABLE IF NOT EXISTS print_jobs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_id TEXT,
                    document TEXT NOT NULL,
                    printer_name TEXT,
                    status TEXT,
                    pages_printed INTEGER DEFAULT 0,
                    total_pages INTEGER DEFAULT 0,
                    timestamp TEXT,
                    thumbnail_url TEXT,
                    session_id INTEGER,
                    staged INTEGER DEFAULT 0,
                    calculated_price REAL DEFAULT 0
                )"
        ];
        
        foreach ($tables as $sql) {
            $db->exec($sql);
        }
    }
}

if (!function_exists('configure_sqlite_conf')) {
    function configure_sqlite_conf(string $path): void
    {
        $GLOBALS['conf'] = [
            'db_type' => 'sqlite',
            'db_path' => $path,
            'dsn' => 'sqlite:' . $path,
            'login' => '',
            'pass' => '',
            'uploaddir' => sys_get_temp_dir() . '/',
        ];
    }
}
