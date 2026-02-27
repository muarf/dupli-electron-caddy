<?php
/**
 * Gestion centralisée des chemins de données de l'application
 * Permet d'assurer la compatibilité avec les environnements en lecture seule (AppImage)
 */

if (!function_exists('getDataDir')) {
    /**
     * Retourne le répertoire racine pour le stockage des données utilisateur
     */
    function getDataDir() {
        // Priorité 1 : Variable d'environnement fournie par Electron
        $envPath = getenv('DUPLICATOR_DB_PATH');
        if (!empty($envPath)) {
            return dirname($envPath);
        }

        // Priorité 2 : Détection AppImage
        $currentDir = getcwd();
        if (strpos($currentDir, '.mount') !== false || strpos($currentDir, 'AppDir') !== false) {
            $homeDir = $_SERVER['HOME'] ?? getenv('HOME') ?? '/tmp';
            return $homeDir . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'Duplicator';
        }

        // Priorité 3 : Développement local (recherche de la racine)
        // On remonte jusqu'à trouver un indicateur de racine (comme le dossier public ou controler)
        $path = __DIR__;
        while ($path && $path !== DIRECTORY_SEPARATOR && !file_exists($path . DIRECTORY_SEPARATOR . 'controler')) {
            $newPath = dirname($path);
            if ($newPath === $path) break;
            $path = $newPath;
        }
        return $path;
    }
}

if (!function_exists('getTmpDir')) {
    /**
     * Retourne le répertoire pour les fichiers temporaires
     */
    function getTmpDir() {
        $path = getDataDir() . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }
}

if (!function_exists('getUploadsDir')) {
    /**
     * Retourne le répertoire pour les fichiers téléversés
     */
    function getUploadsDir() {
        $path = getDataDir() . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }
}

if (!function_exists('getAidePdfDir')) {
    /**
     * Retourne le répertoire pour les PDFs d'aide
     */
    function getAidePdfDir() {
        // Compatibilité avec l'ancienne variable d'env
        $envDir = getenv('DUPLI_AIDE_PDF_DIR');
        if (!empty($envDir)) {
            return $envDir;
        }

        $path = getDataDir() . DIRECTORY_SEPARATOR . 'aide_pdfs';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }
}

/**
 * La fonction getBibliothequeDir est déjà définie ailleurs ou sera centralisée ici par la suite
 * Pour éviter les conflits de redéfinition si bibliotheque.php est chargé.
 */
if (!function_exists('getBibliothequeDir')) {
    function getBibliothequeDir() {
        $path = getDataDir() . DIRECTORY_SEPARATOR . 'bibliotheque';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        return $path;
    }
}
