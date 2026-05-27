<?php
/**
 * API pour charger les jobs en attente depuis print_jobs (table temporaire)
 * GET ?get_pending_jobs
 */

require_once __DIR__ . '/../controler/functions/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = create_database_manager();
    
    // Vérifier si la table existe
    if (!$db->tableExists('print_jobs')) {
        echo json_encode(['jobs' => []]);
        exit;
    }
    
    // Charger tous les jobs non encore assignés (récents)
    // Limiter aux dernières 24h pour éviter l'accumulation
    $jobs = $db->select("
        SELECT 
            id,
            job_id,
            document,
            printer_name as printerName,
            total_pages as pages,
            copies,
            fill_rate,
            color_mode,
            duplex,
            paper_size,
            thumbnail_url,
            timestamp,
            created_at
        FROM print_jobs
        WHERE datetime(created_at) > datetime('now', '-1 day')
        ORDER BY created_at DESC
        LIMIT 100
    ");
    
    echo json_encode(['jobs' => $jobs]);
    
} catch (Exception $e) {
    error_log("[GET_PENDING_JOBS] Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}
