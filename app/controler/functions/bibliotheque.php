<?php
/**
 * Fonctions utilitaires pour la bibliothèque de documents
 */

// La fonction getBibliothequeDir() est désormais centralisée dans paths.php
// Elle est chargée via func.php -> paths.php

/**
 * Normalise un chemin
 */
if (!function_exists('normalizePath')) {
    function normalizePath($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $path = trim($path);
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
}

/**
 * Scanne un dossier pour trouver des PDF et PNG
 * @param string $dir Dossier à scanner
 * @param bool $recursive Scanner les sous-dossiers
 * @return array Liste des fichiers trouvés
 */
function scanDirectoryForLibrary($dir, $recursive = false) {
    $files = [];
    
    if (!is_dir($dir) || !is_readable($dir)) {
        return [];
    }
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            // Exclure les dossiers de miniatures (système)
            if (strpos(strtolower($item), 'thumbnail') !== false) {
                continue;
            }
            if ($recursive) {
                $files = array_merge($files, scanDirectoryForLibrary($path, true));
            }
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            // On ne scanne que les PDF pour la bibliothèque principale
            if ($ext === 'pdf') {
                $files[] = [
                    'path' => $path,
                    'filename' => $item,
                    'type' => $ext,
                    'size' => filesize($path),
                    'mtime' => filemtime($path)
                ];
            }
        }
    }
    
    return $files;
}

/**
 * Vérifie si un fichier est sûr (pas de traversée de répertoire, etc.)
 * Pour les fichiers internes à la bibliothèque
 */
function validateBibliothequePath($path) {
    $baseDir = getBibliothequeDir();
    $realPath = realpath($path);
    $realBaseDir = realpath($baseDir);
    
    return $realPath && $realBaseDir && strpos($realPath, $realBaseDir) === 0;
}

/**
 * Vérifie que l'utilisateur a le droit d'accéder aux API de la bibliothèque.
 * Bloque l'accès si un mot de passe est configuré et non fourni.
 */
function requireBibliothequeAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Si l'utilisateur est administrateur, on autorise d'office
    if (isset($_SESSION['user'])) {
        return true;
    }
    
    require_once __DIR__ . '/../database.php';
    require_once __DIR__ . '/../../models/SettingsManager.php';
    
    $db = pdo_connect();
    $settings = new SettingsManager($db);
    $bib_password = $settings->get('bibliotheque_password', '');
    
    // S'il n'y a pas de mot de passe configuré, on autorise
    if (empty($bib_password)) {
        return true;
    }
    
    // Si la session "bibliotheque" est validée, on autorise
    if (isset($_SESSION['bib_authenticated']) && $_SESSION['bib_authenticated'] === true) {
        return true;
    }
    
    // Sinon, on refuse l'accès
    // Si c'est une requête AJAX (fetch, xhr) on renvoie 403
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['error' => 'Authentication required for library', 'auth_required' => true]);
        exit;
    }
    
    // Si l'URL contient api/ ou si l'on est dans un script api, 
    // l'accès direct au PDF (via href direct) redirige vers la vue bibliotheque.
    header('Location: ?bibliotheque');
    exit;
}
