<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

$fileId = $_GET['id'] ?? '';

if (empty($fileId)) {
    http_response_code(400);
    die('File ID not specified');
}

try {
    $libManager = new BibliothequeManager();
    $file = $libManager->getFile($fileId);
    
    if (!$file) {
        http_response_code(404);
        die('File not found in database');
    }
    
    // Sécuriser le chemin - vérifier qu'on ne remonte pas
    $realFilePath = realpath($file['filepath']);
    $baseDir = getBibliothequeDir();
    $realBaseDir = realpath($baseDir);
    
    // Si c'est un fichier externe, on vérifie juste qu'il existe
    if ($file['is_external'] == 1) {
        if (!$realFilePath || !file_exists($realFilePath)) {
            error_log("Fichier externe non trouvé: " . $file['filepath']);
            http_response_code(404);
            die('External file not found');
        }
    } else {
        // Pour les fichiers internes, vérifier qu'ils sont dans le bon dossier
        if (!$realFilePath || !$realBaseDir || strpos($realFilePath, $realBaseDir) !== 0) {
            error_log("Chemin invalide - realFilePath: " . ($realFilePath ?: 'null') . ", realBaseDir: " . ($realBaseDir ?: 'null'));
            http_response_code(403);
            die('Invalid file path');
        }
    }
    
    // Vérifier que le fichier existe et n'est pas vide
    if (!file_exists($realFilePath)) {
        error_log("Fichier n'existe pas: " . $realFilePath);
        http_response_code(404);
        die('File not found on disk');
    }
    
    $fileSize = filesize($realFilePath);
    if ($fileSize === false || $fileSize == 0) {
        error_log("Fichier vide ou erreur de taille: " . $realFilePath . " (size: " . ($fileSize === false ? 'false' : '0') . ")");
        http_response_code(500);
        die('File is empty or cannot be read');
    }
    
    // Déterminer le type MIME
    $mimeType = mime_content_type($realFilePath);
    if ($file['file_type'] === 'pdf') {
        $mimeType = 'application/pdf';
    } elseif ($file['file_type'] === 'png') {
        $mimeType = 'image/png';
    }
    
    // Définir les headers
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: public, max-age=3600');
    header('Content-Disposition: inline; filename="' . basename($file['filename']) . '"');
    
    // Désactiver la compression de sortie pour éviter les problèmes
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Lire et envoyer le fichier
    $handle = fopen($realFilePath, 'rb');
    if ($handle === false) {
        error_log("Impossible d'ouvrir le fichier: " . $realFilePath);
        http_response_code(500);
        die('Error reading file');
    }
    
    // Envoyer le fichier par chunks pour éviter les problèmes de mémoire
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
    
} catch (Exception $e) {
    error_log("Erreur get_bibliotheque_file: " . $e->getMessage());
    http_response_code(500);
    die('Error serving file');
}

