<?php
/**
 * Script de diagnostic pour le CI GitHub Actions
 * Tente d'identifier la cause du crash 255
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "=== DIAGNOSTIC PHP CI ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "Current Dir: " . getcwd() . "\n";

echo "\n--- Vérification des extensions ---\n";
$required = ['pdo', 'pdo_sqlite', 'sqlite3', 'gd', 'mbstring', 'xml', 'ctype', 'dom'];
foreach ($required as $ext) {
    echo "Extension $ext: " . (extension_loaded($ext) ? "✅" : "❌") . "\n";
}

echo "\n--- Vérification des fichiers clés ---\n";
$files = [
    '../vendor/autoload.php',
    '../controler/func.php',
    '../controler/conf.php',
    '../models/migrations/DatabaseMigrationManager.php'
];
foreach ($files as $file) {
    echo "File $file: " . (file_exists(__DIR__ . '/' . $file) ? "✅" : "❌") . "\n";
}

echo "\n--- Test Autoloader ---\n";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "Autoloader chargé ✅\n";
} catch (Throwable $e) {
    echo "Erreur chargement Autoloader: " . $e->getMessage() . " ❌\n";
}

echo "\n--- Test Chargement Application ---\n";
try {
    require_once __DIR__ . '/../controler/func.php';
    echo "Fichiers application chargés ✅\n";
} catch (Throwable $e) {
    echo "Erreur chargement Application: " . $e->getMessage() . " ❌\n";
    exit(1);
}

echo "\n--- Test PDO SQLite ---\n";
try {
    $tempDb = tempnam(sys_get_temp_dir(), 'db_test');
    echo "Temp DB path: $tempDb\n";
    $pdo = new PDO('sqlite:' . $tempDb);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE test (id INTEGER PRIMARY KEY, val TEXT)");
    $pdo->exec("INSERT INTO test (val) VALUES ('hello')");
    $res = $pdo->query("SELECT val FROM test")->fetchColumn();
    echo "PDO SQLite Test: " . ($res === 'hello' ? "SUCCESS ✅" : "FAILED ❌") . "\n";
    unlink($tempDb);
} catch (Throwable $e) {
    echo "PDO SQLite Exception: " . $e->getMessage() . " ❌\n";
}

echo "\n--- Test Migration Manager Instanciation ---\n";
try {
    require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';
    if (class_exists('DatabaseMigrationManager')) {
        echo "Classe DatabaseMigrationManager trouvée ✅\n";
        global $conf;
        $manager = new DatabaseMigrationManager($conf);
        echo "Instance DatabaseMigrationManager créée ✅\n";
    } else {
        echo "Classe DatabaseMigrationManager INTROUVABLE ❌\n";
    }
} catch (Throwable $e) {
    echo "Erreur Migration Manager: " . $e->getMessage() . " ❌\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
