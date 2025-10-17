<?php
// Ne pas démarrer automatiquement la session - laisser index.php le faire
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Configuration SQLite
// Priorité 1 : Variable d'environnement d'Electron (garantit persistence userData)
// Priorité 2 : Détection AppImage
// Priorité 3 : Développement local

if (getenv('DUPLICATOR_DB_PATH')) {
    // Chemin fourni par Electron - Utiliser celui-ci en priorité
    $sqlite_db_path = getenv('DUPLICATOR_DB_PATH');
    
    // Créer le répertoire s'il n'existe pas (seulement si on est en dehors de l'AppImage)
    $db_dir = dirname($sqlite_db_path);
    if (!is_dir($db_dir) && is_writable(dirname($db_dir))) {
        mkdir($db_dir, 0755, true);
    }
} else {
    // Fallback sur la détection automatique
    $current_dir = getcwd();
    if (strpos($current_dir, '.mount') !== false || strpos($current_dir, 'AppDir') !== false) {
        // AppImage : utiliser le répertoire home de l'utilisateur
        $home_dir = $_SERVER['HOME'] ?? getenv('HOME') ?? '/tmp';
        $sqlite_db_path = $home_dir . '/.config/Duplicator/duplinew.sqlite';
        
        // Créer le répertoire s'il n'existe pas
        $db_dir = dirname($sqlite_db_path);
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
    } else {
        // Développement : utiliser le répertoire de l'app
        $sqlite_db_path = __DIR__ . '/../duplinew.sqlite';
    }
}

// Ne pas créer automatiquement la base de données
// Laisser l'installation le faire

// Configuration SQLite
$conf['dsn'] = 'sqlite:' . $sqlite_db_path;
$conf['login'] = ''; // Pas de login pour SQLite
$conf['pass'] = '';  // Pas de mot de passe pour SQLite
$conf['uploaddir'] = __DIR__ . '/../public/tmp/';


// Stocker le type de base de données
$conf['db_type'] = 'sqlite';
$conf['db_path'] = $sqlite_db_path;

// Debug: logger la configuration
if (function_exists('log_info')) {
    log_info("Configuration SQLite chargée - Base: $sqlite_db_path", 'conf.php');
}
?>