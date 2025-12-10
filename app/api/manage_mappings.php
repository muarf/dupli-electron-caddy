<?php
/**
 * API pour gérer les mappings imprimante système <-> machine BDD
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';

// S'assurer que les migrations sont passées (création de la table si nécessaire)
try {
    $migrationManager = new DatabaseMigrationManager($conf);
    $migrationManager->runMigrations();
} catch (Exception $e) {
    error_log("Erreur migration dans manage_mappings: " . $e->getMessage());
}

$db = create_database_manager();

// GET: Récupérer tous les mappings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $mappings = $db->select("SELECT * FROM printer_mappings ORDER BY system_printer_name");
        echo json_encode(['success' => true, 'mappings' => $mappings]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST: Ajouter ou mettre à jour un mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['system_printer_name']) || !isset($input['machine_type']) || !isset($input['machine_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Champs manquants']);
        exit;
    }

    try {
        // Validation basique
        $validTypes = ['photocop', 'dupli'];
        if (!in_array($input['machine_type'], $validTypes)) {
            throw new Exception("Type de machine invalide");
        }

        // Insert or Replace (SQLite compatible)
        $sql = "INSERT OR REPLACE INTO printer_mappings (system_printer_name, machine_type, machine_id, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";

        $db->execute($sql, [
            $input['system_printer_name'],
            $input['machine_type'],
            $input['machine_id']
        ]);

        echo json_encode(['success' => true, 'message' => 'Mapping enregistré']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>