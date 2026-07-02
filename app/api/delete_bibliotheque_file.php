<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$jsonInput = file_get_contents('php://input');
if (empty($jsonInput) && PHP_SAPI === 'cli') {
    $jsonInput = file_get_contents('php://stdin');
}
$data = json_decode($jsonInput, true);
$id = $data['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID is required']);
    exit;
}

try {
    $manager = new BibliothequeManager();
    $deleteFromDisk = isset($data['delete_from_disk']) ? (bool)$data['delete_from_disk'] : true;
    
    $success = $manager->deleteFile($id, $deleteFromDisk);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}





