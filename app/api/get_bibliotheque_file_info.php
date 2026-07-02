<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
/**
 * API pour récupérer les informations détaillées d'un fichier de la bibliothèque
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID manquant']);
    exit;
}

try {
    $manager = new BibliothequeManager();
    $file = $manager->getFile($id);
    
    if ($file) {
        // Décoder le JSON des métadonnées pour plus de facilité en JS
        $file['metadata'] = json_decode($file['metadata_json'] ?? '{}', true);
        echo json_encode(['success' => true, 'file' => $file]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Fichier non trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
