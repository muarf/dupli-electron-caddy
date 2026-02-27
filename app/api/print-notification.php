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

    // 0. Vérifier si le job a déjà été enregistré définitivement RECEMMENT (2 heures max)
    // Cela évite les faux positifs quand Windows recycle les Job IDs (48, 49...) après un certain temps.
    $platform = $data['platform'] ?? 'windows';
    
    $checkSql = "SELECT 1 FROM recorded_print_jobs WHERE job_id = ? AND recorded_at > datetime('now', '-2 hours')";
    $checkParams = [strval($data['jobId'])];
    
    if ($platform !== 'linux') {
        // Sur Windows, l'ID n'est courageusement unique que PAR imprimante
        $checkSql .= " AND printer_name = ?";
        $checkParams[] = $data['printerName'];
    }
    // Sur Linux, l'ID CUPS est unique au système, donc on ignore printer_name pour fusionner les détections multiples

    $alreadyRecorded = $db->selectOne($checkSql, $checkParams);

    if ($alreadyRecorded) {
        error_log(sprintf("[DOUBLON] Job %s sur %s déjà enregistré (%s), ignoré.", $data['jobId'], $data['printerName'], $platform));
        echo json_encode(['success' => true, 'message' => 'Job déjà enregistré, ignoré']);
        exit;
    }

    // 0b. Vérifier si le job est déjà dans print_jobs (non validé) pour éviter les doublons de détection simultanée
    $checkPendingSql = "SELECT 1 FROM print_jobs WHERE job_id = ? AND created_at > datetime('now', '-5 minutes')";
    $checkPendingParams = [strval($data['jobId'])];
    if ($platform !== 'linux') {
        $checkPendingSql .= " AND printer_name = ?";
        $checkPendingParams[] = $data['printerName'];
    }
    
    if ($db->selectOne($checkPendingSql, $checkPendingParams)) {
        echo json_encode(['success' => true, 'message' => 'Job déjà en attente, ignoré']);
        exit;
    }

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
            fill_rate REAL DEFAULT 0,
            color_mode TEXT DEFAULT 'unknown',
            duplex INTEGER DEFAULT 0,
            thumbnail_url TEXT,
            paper_size TEXT,
            copies INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            session_id INTEGER,
            calculated_price REAL DEFAULT 0,
            machine_type TEXT,
            machine_id INTEGER,
            machine_name TEXT,
            contact TEXT,
            staged INTEGER DEFAULT 0,
            UNIQUE(job_id, printer_name, timestamp)
        )
    ");

    // S'assurer que les nouvelles colonnes existent (si version antérieure sans migrations)
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN document_full_path TEXT");
    } catch(Exception $e) {}
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN document_display_name TEXT");
    } catch(Exception $e) {}

    // Extraire le nom de fichier pour l'affichage (avec trace du chemin complet)
    $documentFull = $data['document'];
    $documentDisplay = basename($data['document']); // Extrait toujours le nom de fichier
    
    // Insérer ou mettre à jour le job d'impression
    $db->execute("
        INSERT OR REPLACE INTO print_jobs 
        (job_id, document, document_full_path, document_display_name, owner, printer_name, status, pages_printed, total_pages, size, time_submitted, event_type, timestamp, fill_rate, color_mode, duplex, thumbnail_url, paper_size, copies)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ", [
        $data['jobId'],
        $documentDisplay,              // 'document' contient maintenant le nom court (rétrocompatibilité)
        $documentFull,                 // 'document_full_path' garde le chemin complet (trace)
        $documentDisplay,              // 'document_display_name' pour l'affichage UI
        $data['owner'] ?? null,
        $data['printerName'],
        $data['status'],
        $data['pagesPrinted'] ?? 0,
        $data['totalPages'] ?? 0,
        $data['size'] ?? 0,
        $data['timeSubmitted'] ?? null,
        $data['eventType'] ?? 'unknown',
        $data['timestamp'],
        $data['fillRate'] ?? 0,
        $data['colorMode'] ?? 'unknown',
        isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
        $data['thumbnailUrl'] ?? '',
        $data['paperSize'] ?? '',
        $data['copies'] ?? 1
    ]);

    // Log pour le débogage (optionnel)
    error_log(sprintf(
        "Impression détectée (Succès): Job %s - Document: %s - Imprimante: %s - Status: %s",
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

