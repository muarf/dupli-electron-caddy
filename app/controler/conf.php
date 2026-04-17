<?php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Inclure les fonctions de chemins si elles ne sont pas chargées
require_once __DIR__ . '/functions/paths.php';

// Configuration SQLite
// Priorité 1 : Variable d'environnement d'Electron (garantit persistence userData)
// Priorité 2 : Détection AppImage
// Priorité 3 : Développement local

$sqlite_db_path = getenv('DUPLICATOR_DB_PATH') ?: (getDataDir() . DIRECTORY_SEPARATOR . 'duplinew.sqlite');
$db_dir = dirname($sqlite_db_path);
if (!is_dir($db_dir)) {
    @mkdir($db_dir, 0755, true);
}

// Ne pas créer automatiquement la base de données
// Laisser l'installation le faire

// Configuration SQLite
$conf['dsn'] = 'sqlite:' . $sqlite_db_path;
$conf['login'] = ''; // Pas de login pour SQLite
$conf['pass'] = '';  // Pas de mot de passe pour SQLite
$conf['uploaddir'] = getTmpDir() . DIRECTORY_SEPARATOR;


// Stocker le type de base de données
$conf['db_type'] = 'sqlite';
$conf['db_path'] = $sqlite_db_path;

// Debug: logger la configuration
if (function_exists('log_info')) {
    log_info("Configuration SQLite chargée - Base: $sqlite_db_path", 'conf.php');
}