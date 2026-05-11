<?php
/**
 * API pour récupérer tous les tags uniques de la bibliothèque
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json');

try {
    $manager = new BibliothequeManager();
    $tags = $manager->getAllUniqueTags();
    echo json_encode($tags);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
