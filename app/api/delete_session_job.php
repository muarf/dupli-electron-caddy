<?php
/**
 * API pour supprimer définitivement un job de session (photocop ou dupli)
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && 
    (!isset($_SESSION['user']) || $_SESSION['user'] !== "1")) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => __('api.delete_session_job.access_denied')]);
    exit;
}

require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/i18n.php';

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : ''; // 'photocop', 'dupli' ou 'print_jobs'

    if (!$id || !in_array($type, ['photocop', 'dupli', 'print_jobs'])) {
        throw new Exception(__('api.delete_session_job.invalid_params'));
    }

    $db = create_database_manager()->connect();

    // Sécuriser le nom de la table
    $table = 'print_jobs';
    if ($type === 'photocop') $table = 'photocop';
    else if ($type === 'dupli') $table = 'dupli';

    $stmt = $db->prepare("DELETE FROM $table WHERE id = ?");
    $success = $stmt->execute([$id]);

    echo json_encode([
        'success' => $success,
        'message' => $success ? __('api.delete_session_job.success') : __('api.delete_session_job.error')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
