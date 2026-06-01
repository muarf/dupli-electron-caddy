<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/models/migrations/DatabaseMigrationManager.php';

// Simuler l'environnement
global $conf;
$manager = new DatabaseMigrationManager($conf);

echo "Lancement des migrations...\n";
$manager->runMigrations();
echo "Terminé.\n";
