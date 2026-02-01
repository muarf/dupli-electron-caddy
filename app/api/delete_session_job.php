<?php
/**
 * API pour supprimer définitivement un job de session (photocop ou dupli)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../controler/functions/database.php';

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : ''; // 'photocop', 'dupli' ou 'print_jobs'

    if (!$id || !in_array($type, ['photocop', 'dupli', 'print_jobs'])) {
        throw new Exception("Paramètres ID ou Type manquants ou invalides.");
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
        'message' => $success ? "Job supprimé avec succès." : "Erreur lors de la suppression."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
