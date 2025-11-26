<?php
/**
 * API pour recevoir les notifications d'impression depuis le moniteur Windows
 * 
 * Cette API reçoit les informations sur les jobs d'impression depuis le module
 * de surveillance Node.js et les enregistre dans la base de données.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que la requête est en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalide: ' . json_last_error_msg()]);
    exit;
}

// Valider les données requises
$requiredFields = ['jobId', 'document', 'printerName', 'status', 'timestamp'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Champ manquant: $field"]);
        exit;
    }
}

try {
    // Inclure les fichiers nécessaires
    require_once(__DIR__ . '/../controler/functions/database.php');
    require_once(__DIR__ . '/../controler/functions/utilities.php');
    
    // Créer le gestionnaire de base de données
    $db = create_database_manager();
    
    // Vérifier si la table existe, sinon la créer
    $db->execute("
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
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(job_id, printer_name, timestamp)
        )
    ");
    
    // Insérer ou mettre à jour le job d'impression
    $db->execute("
        INSERT OR REPLACE INTO print_jobs 
        (job_id, document, owner, printer_name, status, pages_printed, total_pages, size, time_submitted, event_type, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $data['jobId'],
        $data['document'],
        $data['owner'] ?? null,
        $data['printerName'],
        $data['status'],
        $data['pagesPrinted'] ?? 0,
        $data['totalPages'] ?? 0,
        $data['size'] ?? 0,
        $data['timeSubmitted'] ?? null,
        $data['eventType'] ?? 'unknown',
        $data['timestamp']
    ]);
    
    // Log pour le débogage (optionnel)
    error_log(sprintf(
        "Impression détectée: Job %s - Document: %s - Imprimante: %s - Status: %s",
        $data['jobId'],
        $data['document'],
        $data['printerName'],
        $data['status']
    ));
    
    // Réponse de succès
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Notification d\'impression enregistrée',
        'jobId' => $data['jobId'],
        'printerName' => $data['printerName']
    ]);
    
} catch (Exception $e) {
    error_log('Erreur lors de l\'enregistrement de la notification d\'impression: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erreur serveur',
        'message' => $e->getMessage()
    ]);
}

