<?php
/**
 * API pour supprimer définitivement un job de session (photocop ou dupli)
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../controler/functions/database.php';

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : ''; // 'photocop' ou 'dupli'

    if (!$id || !in_array($type, ['photocop', 'dupli'])) {
        throw new Exception("Paramètres ID ou Type manquants ou invalides.");
    }

    $db = create_database_manager()->getConnection();
    
    // Sécuriser le nom de la table (ne pas utiliser de variables directement dans le FROM)
    $table = ($type === 'photocop') ? 'photocop' : 'dupli';
    
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
