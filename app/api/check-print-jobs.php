<?php
/**
 * Script pour vérifier les enregistrements d'impression dans la base de données
 */

// Désactiver l'affichage des erreurs pour éviter de polluer le JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Définir les headers AVANT toute sortie
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Empêcher toute sortie avant le JSON
ob_start();

try {
    require_once(__DIR__ . '/../controler/conf.php');
    require_once(__DIR__ . '/../controler/functions/database.php');
    require_once(__DIR__ . '/../controler/functions/utilities.php');
    
    // Nettoyer le buffer de sortie
    ob_end_clean();
    $db = create_database_manager();
    
    // Vérifier si la table existe
    if (!$db->tableExists('print_jobs')) {
        echo json_encode([
            'success' => false,
            'error' => 'La table print_jobs n\'existe pas encore',
            'message' => 'Aucune impression n\'a été détectée pour le moment'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Récupérer tous les jobs d'impression, triés par date décroissante
    $jobs = $db->select("
        SELECT
            id,
            job_id,
            document,
            owner,
            printer_name,
            status,
            pages_printed,
            total_pages,
            size,
            paper_size,
            duplex,
            color_mode,
            copies,
            time_submitted,
            event_type,
            timestamp,
            created_at
        FROM print_jobs
        ORDER BY timestamp DESC
        LIMIT 50
    ");
    
    // Compter le total
    $total = $db->count('print_jobs');
    
    // Statistiques par imprimante
    $statsByPrinter = $db->select("
        SELECT 
            printer_name,
            COUNT(*) as total_jobs,
            SUM(pages_printed) as total_pages
        FROM print_jobs 
        GROUP BY printer_name
        ORDER BY total_jobs DESC
    ");
    
    // Statistiques par statut
    $statsByStatus = $db->select("
        SELECT 
            status,
            COUNT(*) as count
        FROM print_jobs 
        GROUP BY status
        ORDER BY count DESC
    ");
    
    echo json_encode([
        'success' => true,
        'total_jobs' => $total,
        'jobs' => $jobs,
        'stats' => [
            'by_printer' => $statsByPrinter,
            'by_status' => $statsByStatus
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Nettoyer le buffer avant d'envoyer l'erreur
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    // Nettoyer le buffer avant d'envoyer l'erreur
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

