<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../controler/conf.php';
    require_once __DIR__ . '/../controler/func.php';
    require_once __DIR__ . '/../models/BibliothequeManager.php';

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id'])) {
        throw new Exception("ID manquant");
    }

    $manager = new BibliothequeManager();
    $success = $manager->updateMetadata($data['id'], $data);

    echo json_encode([
        'success' => $success,
        'message' => $success ? "Mise à jour effectuée" : "Aucun changement effectué"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
