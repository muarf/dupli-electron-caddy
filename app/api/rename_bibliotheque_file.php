<?php
// API pour renommer un fichier dans la bibliothèque
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json; charset=utf-8');

// Lire le body JSON
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$id = $data['id'] ?? null;
$newName = $data['newName'] ?? null;

if (!$id || !$newName) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
    exit;
}

try {
    $manager = new BibliothequeManager();
    $result = $manager->renameFile($id, $newName);
    
    echo json_encode(['success' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
