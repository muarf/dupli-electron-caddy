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

    // S'assurer que les colonnes nécessaires existent
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN job_uuid TEXT");
    } catch(Exception $e) {}
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN document_full_path TEXT");
    } catch(Exception $e) {}
    try {
        $db->execute("ALTER TABLE print_jobs ADD COLUMN document_display_name TEXT");
    } catch(Exception $e) {}

    $jobUuid = $data['jobUuid'] ?? null;
    $jobId = strval($data['jobId']);
    $platform = $data['platform'] ?? 'windows';

    // 0. Vérifier si le job a déjà été enregistré définitivement RECEMMENT
    $checkSql = "SELECT 1 FROM recorded_print_jobs WHERE ";
    $checkParams = [];
    if ($jobUuid) {
        $checkSql .= "job_uuid = ?";
        $checkParams[] = $jobUuid;
    } else {
        $checkSql .= "job_id = ? AND recorded_at > datetime('now', '-2 hours')";
        $checkParams[] = $jobId;
        if ($platform !== 'linux') {
            $checkSql .= " AND printer_name = ?";
            $checkParams[] = $data['printerName'];
        }
    }

    $alreadyRecorded = $db->selectOne($checkSql, $checkParams);

    if ($alreadyRecorded) {
        echo json_encode(['success' => true, 'message' => 'Job déjà enregistré, ignoré', 'already_recorded' => true]);
        exit;
    }

    // 0b. Vérifier si le job est déjà dans print_jobs (non validé)
    $checkPendingSql = "SELECT id, fill_rate, thumbnail_url, total_pages, status FROM print_jobs WHERE ";
    $checkPendingParams = [];
    if ($jobUuid) {
        $checkPendingSql .= "job_uuid = ?";
        $checkPendingParams[] = $jobUuid;
    } else {
        $checkPendingSql .= "job_id = ? AND created_at > datetime('now', '-10 minutes')";
        $checkPendingParams[] = $jobId;
        if ($platform !== 'linux') {
            $checkPendingSql .= " AND printer_name = ?";
            $checkPendingParams[] = $data['printerName'];
        }
    }
    
    // Inférence du colorMode si absent
    $colorModeStr = $data['colorMode'] ?? 'unknown';
    if (($colorModeStr === 'unknown' || empty($colorModeStr)) && isset($data['isGrayscale'])) {
        $colorModeStr = $data['isGrayscale'] ? 'Monochrome' : 'Color';
    }
    
    $existingJob = $db->selectOne($checkPendingSql, $checkPendingParams);
    
    if ($existingJob) {
        $newPages = intval($data['totalPages'] ?? 0);
        $oldPages = intval($existingJob['total_pages'] ?? 0);
        $newStatus = $data['status'] ?? '';
        $oldStatus = $existingJob['status'] ?? '';
        $newFill = floatval($data['fillRate'] ?? 0);
        $oldFill = floatval($existingJob['fill_rate'] ?? 0);
        $newThumb = !empty($data['thumbnailUrl']);
        $oldThumb = !empty($existingJob['thumbnail_url']);

        // Autoriser l'update si :
        // - Plus de pages détectées
        // - Statut différent
        // - Fill rate différent (plus précis)
        // - Thumbnail arrive alors qu'elle manquait
        $hasBetterData = ($newPages > $oldPages) || 
                         ($newStatus !== $oldStatus) ||
                         (abs($newFill - $oldFill) > 0.01) ||
                         ($newThumb && !$oldThumb);
        
        if (!$hasBetterData) {
            echo json_encode(['success' => true, 'message' => 'Données identiques, ignoré']);
            exit;
        }
    }

    // Extraire le nom de fichier
    $documentFull = $data['document'] ?? 'Sans Nom';
    $documentDisplay = basename($documentFull);
    
    // Vérifier si job existe déjà (pour UPDATE au lieu de INSERT)
    $existingJobId = $db->selectOne(
        "SELECT id FROM print_jobs WHERE job_id = ? AND printer_name = ?",
        [strval($data['jobId']), $data['printerName']]
    );
    
    if ($existingJobId) {
        // UPDATE si job existe déjà (préserve le timestamp original)
        $db->execute("
            UPDATE print_jobs SET
                document = ?,
                document_full_path = ?,
                document_display_name = ?,
                status = ?,
                pages_printed = ?,
                total_pages = CASE WHEN CAST(? AS INTEGER) > total_pages THEN ? ELSE total_pages END,
                size = ?,
                fill_rate = CASE WHEN ? > 0 THEN ? ELSE fill_rate END,
                color_mode = COALESCE(NULLIF(?, 'unknown'), color_mode),
                duplex = ?,
                thumbnail_url = COALESCE(NULLIF(?, ''), thumbnail_url),
                paper_size = ?,
                copies = ?,
                job_uuid = ?
            WHERE id = ?
        ", [
            $documentDisplay,
            $documentFull,
            $documentDisplay,
            $data['status'],
            $data['pagesPrinted'] ?? 0,
            $data['totalPages'] ?? 0,
            $data['totalPages'] ?? 0,
            $data['size'] ?? 0,
            $data['fillRate'] ?? 0,
            $data['fillRate'] ?? 0,
            $colorModeStr,
            isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
            $data['thumbnailUrl'] ?? '',
            $data['paperSize'] ?? '',
            $data['copies'] ?? 1,
            $jobUuid,
            $existingJobId['id']
        ]);
    } else {
        // INSERT nouveau job
        $db->execute("
            INSERT INTO print_jobs 
            (job_id, job_uuid, document, document_full_path, document_display_name, owner, printer_name, status, pages_printed, total_pages, size, time_submitted, event_type, timestamp, fill_rate, color_mode, duplex, thumbnail_url, paper_size, copies)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['jobId'],
            $jobUuid,
            $documentDisplay,
            $documentFull,
            $documentDisplay,
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
            $colorModeStr,
            isset($data['duplex']) ? ($data['duplex'] ? 1 : 0) : 0,
            $data['thumbnailUrl'] ?? '',
            $data['paperSize'] ?? '',
            $data['copies'] ?? 1
        ]);
    }

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

