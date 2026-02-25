<?php
/**
 * Fonctions utilitaires pour la bibliothèque de documents
 */

/**
 * Résout le dossier de stockage de la bibliothèque
 * Doit être aligné sur l'emplacement de la base de données pour AppImage/Windows
 */
function getBibliothequeDir() {
    // Priorité 1 : Variable d'environnement spécifique
    $envDir = getenv('DUPLI_BIBLIOTHEQUE_DIR');
    if (!empty($envDir)) {
        return normalizePath($envDir);
    }
    
    // Récupérer le chemin de la base de données configuré
    // On inclut conf.php pour avoir $conf['db_path'] si disponible, ou on réplique la logique
    global $conf;
    
    // Si $conf est défini et contient db_path
    if (isset($conf['db_path']) && !empty($conf['db_path'])) {
        $dbDir = dirname($conf['db_path']);
        return normalizePath($dbDir . DIRECTORY_SEPARATOR . 'bibliotheque');
    }
    
    // Fallback : Logique dupliquée de conf.php pour être sûr
    if (getenv('DUPLICATOR_DB_PATH')) {
        $dbPath = getenv('DUPLICATOR_DB_PATH');
        $dbDir = dirname($dbPath);
        return normalizePath($dbDir . DIRECTORY_SEPARATOR . 'bibliotheque');
    }
    
    // Détection AppImage
    $current_dir = getcwd();
    if (strpos($current_dir, '.mount') !== false || strpos($current_dir, 'AppDir') !== false) {
        $home_dir = $_SERVER['HOME'] ?? getenv('HOME') ?? '/tmp';
        return normalizePath($home_dir . '/.config/Duplicator/bibliotheque');
    }
    
    // Développement local (à côté de duplinew.sqlite qui est dans ../)
    return normalizePath(__DIR__ . '/../../../bibliotheque');
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
