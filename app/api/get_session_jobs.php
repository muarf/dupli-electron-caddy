<?php
/**
 * API pour charger les jobs d'une session
 * GET ?get_session_jobs&session_id=X
 */

require_once __DIR__ . '/../controler/functions/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = pdo_connect();
    $session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
    
    if (!$session_id) {
        echo json_encode(['jobs' => []]);
        exit;
    }
    
    // Charger jobs photocop de cette session
    $stmt1 = $db->prepare("
        SELECT 
            id,
            'photocop' as table_source,
            marque as printerName,
            '' as document,
            nb_f as pages,
            1 as copies,
            prix,
            0 as paper_cost,
            0 as ink_cost,
            paye as papierPaye,
            date
        FROM photocop
        WHERE session_id = ?
        ORDER BY date DESC
    ");
    $stmt1->execute([$session_id]);
    $photocop_jobs = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    
    // Charger jobs dupli de cette session
    $stmt2 = $db->prepare("
        SELECT 
            id,
            'dupli' as table_source,
            nom_machine as printerName,
            '' as document,
            (CAST(passage_ap AS INTEGER) - CAST(passage_av AS INTEGER)) as pages,
            1 as copies,
            prix,
            0 as paper_cost,
            0 as ink_cost,
            paye as papierPaye,
            date
        FROM dupli
        WHERE session_id = ?
        ORDER BY date DESC
    ");
    $stmt2->execute([$session_id]);
    $dupli_jobs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Combiner les deux
    $all_jobs = array_merge($photocop_jobs, $dupli_jobs);
    
    // Trier par date
    usort($all_jobs, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    echo json_encode(['jobs' => $all_jobs]);
    
} catch (Exception $e) {
    error_log("[GET_SESSION_JOBS] Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur', 'message' => $e->getMessage()]);
}
