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
            duplex INTEGER DEFAULT 0,
            paper_size TEXT,
            color_mode TEXT,
            copies INTEGER DEFAULT 1,
            orientation TEXT,
            resolution TEXT,
            input_slot TEXT,
            time_submitted TEXT,
            event_type TEXT,
            timestamp TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(job_id, printer_name, timestamp)
        )
    ");
    
    // Ajouter les colonnes si elles n'existent pas (migration)
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN duplex INTEGER DEFAULT 0");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN paper_size TEXT");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN color_mode TEXT");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN copies INTEGER DEFAULT 1");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN orientation TEXT");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN resolution TEXT");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN input_slot TEXT");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN fill_rate REAL DEFAULT 0");
    } catch (Exception $e) {
        // La colonne existe déjà, ignorer l'erreur
    }
    
    // Vérifier si le job existe déjà (dans la dernière heure pour éviter conflits avec job_ids recyclés)
    $newTotalPages = $data['totalPages'] ?? 0;
    $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $existing = $db->select("SELECT id, total_pages FROM print_jobs WHERE job_id = ? AND printer_name = ? AND timestamp > ?", [
        $data['jobId'],
        $data['printerName'],
        $oneHourAgo
    ]);
    
    if (!empty($existing)) {
        // Le job existe - mettre à jour seulement si le nouveau total_pages est supérieur
        $existingPages = (int)($existing[0]['total_pages'] ?? 0);
        if ($newTotalPages > $existingPages) {
            $db->execute("
                UPDATE print_jobs SET 
                    status = ?,
                    total_pages = ?,
                    timestamp = ?
                WHERE id = ?
            ", [
                $data['status'],
                $newTotalPages,
                $data['timestamp'],
                $existing[0]['id']
            ]);
        }
        // Sinon on ne fait rien (on garde la meilleure valeur)
    } else {
        // Nouveau job - insérer
        $db->execute("
            INSERT INTO print_jobs 
            (job_id, document, owner, printer_name, status, pages_printed, total_pages, size, duplex, paper_size, color_mode, copies, orientation, resolution, input_slot, fill_rate, time_submitted, event_type, timestamp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['jobId'],
            $data['document'],
            $data['owner'] ?? null,
            $data['printerName'],
            $data['status'],
            $data['pagesPrinted'] ?? 0,
            $newTotalPages,
            $data['size'] ?? 0,
            isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
            $data['paperSize'] ?? null,
            $data['colorMode'] ?? null,
            $data['copies'] ?? 1,
            $data['orientation'] ?? null,
            $data['resolution'] ?? null,
            $data['inputSlot'] ?? null,
            isset($data['fillRate']) ? floatval($data['fillRate']) : 0.0,
            $data['timeSubmitted'] ?? null,
            $data['eventType'] ?? 'unknown',
            $data['timestamp']
        ]);
    }
    
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

