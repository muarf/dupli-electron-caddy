<?php
/**
 * Fonctions utilitaires pour la bibliothèque de documents
 */

if (!function_exists('getBibliothequeDir')) {
    /**
     * Résout le dossier de stockage de la bibliothèque
     * Utilise la logique centralisée de paths.php
     */
    function getBibliothequeDir()
    {
        if (function_exists('getDataDir')) {
            $path = getDataDir() . DIRECTORY_SEPARATOR . 'bibliotheque';
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
            return $path;
        }

        // Fallback si paths.php n'est pas chargé (ne devrait pas arriver)
        return normalizePath(__DIR__ . '/../../../bibliotheque');
    }
}

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
            if ($recursive) {
                $files = array_merge($files, scanDirectoryForLibrary($path, true));
            }
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'pdf' || $ext === 'png') {
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
