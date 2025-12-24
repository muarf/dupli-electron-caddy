<?php
// Script to dump printer_mappings table
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the DB path explicitly as per user input
putenv('DUPLICATOR_DB_PATH=C:\Users\Dupli\AppData\Roaming\dupli-electron/duplinew.sqlite');

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';

try {
    $db = create_database_manager();
    $rows = $db->select("SELECT * FROM printer_mappings");
    
    echo "--- DUMP OF printer_mappings ---\n";
    foreach ($rows as $row) {
        echo "Printer: " . $row['system_printer_name'] . "\n";
        echo "  Machine Type: '" . $row['machine_type'] . "'\n";
        echo "  Machine ID:   " . $row['machine_id'] . "\n";
        // Check for invisible characters
        echo "  Hex Dump (Type): " . bin2hex($row['machine_type']) . "\n";
        echo "--------------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
