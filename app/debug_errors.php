<?php
// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Debug des Erreurs PHP</h1>";

echo "<h2>Configuration PHP :</h2>";
echo "<ul>";
echo "<li>display_errors: " . (ini_get('display_errors') ? 'Activé' : 'Désactivé') . "</li>";
echo "<li>error_reporting: " . ini_get('error_reporting') . "</li>";
echo "<li>log_errors: " . (ini_get('log_errors') ? 'Activé' : 'Désactivé') . "</li>";
echo "<li>Version PHP: " . phpversion() . "</li>";
echo "</ul>";

echo "<h2>Test de connexion à la base :</h2>";
try {
    require_once 'controler/functions/database.php';
    $db = pdo_connect();
    echo "<p style='color: green;'>✅ Connexion à la base réussie</p>";
    
    // Test des tables
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables trouvées (" . count($tables) . ") :</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table) . "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur de connexion : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Test du SQLiteDatabaseManager :</h2>";
try {
    require_once 'models/admin/SQLiteDatabaseManager.php';
    $conf = ['db_path' => 'duplinew.sqlite'];
    $manager = new SQLiteDatabaseManager($conf);
    echo "<p style='color: green;'>✅ SQLiteDatabaseManager créé avec succès</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur SQLiteDatabaseManager : " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Logs d'erreur récents :</h2>";
$error_logs = [
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    '/tmp/duplicator_errors.log'
];

foreach ($error_logs as $log_file) {
    if (file_exists($log_file)) {
        echo "<h3>$log_file :</h3>";
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -10); // 10 dernières lignes
        echo "<pre style='background: #f5f5f5; padding: 10px; font-size: 12px;'>";
        echo htmlspecialchars(implode('', $recent_lines));
        echo "</pre>";
        break;
    }
}
?>

