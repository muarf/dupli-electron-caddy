<?php
/**
 * Module de gestion des sauvegardes
 * Gère la création, restauration et suppression des sauvegardes
 */

class BackupManager {
    private $conf;
    private $backup_dir;
    
    public function __construct($conf) {
        $this->conf = $conf;
        
        // Résoudre un répertoire de sauvegarde portable et configurable
        $resolved_dir = $this->resolveBackupDir($conf);
        $this->backup_dir = rtrim($resolved_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Log pour débogage
        error_log('BackupManager: Répertoire de sauvegarde résolu: ' . $this->backup_dir);
        
        // Vérifier que le répertoire n'est pas dans une AppImage (read-only)
        // Vérification stricte : si le chemin contient .mount, AppDir ou app.asar.unpacked, utiliser le fallback
        if (strpos($this->backup_dir, '.mount') !== false || 
            strpos($this->backup_dir, 'AppDir') !== false || 
            strpos($this->backup_dir, 'app.asar.unpacked') !== false) {
            error_log('BackupManager: ATTENTION - Répertoire dans AppImage détecté: ' . $this->backup_dir . ', utilisation du fallback');
            $this->backup_dir = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
            error_log('BackupManager: Nouveau répertoire (fallback): ' . $this->backup_dir);
        }
        
        // Vérifier une dernière fois AVANT de créer le répertoire
        // Si le chemin est toujours dans une AppImage, forcer l'utilisation du fallback
        if (strpos($this->backup_dir, '.mount') !== false || 
            strpos($this->backup_dir, 'AppDir') !== false || 
            strpos($this->backup_dir, 'app.asar.unpacked') !== false) {
            error_log('BackupManager: DERNIÈRE VÉRIFICATION - Répertoire toujours dans AppImage, utilisation du fallback');
            $this->backup_dir = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
        }
        
        // Créer le dossier de sauvegarde s'il n'existe pas
        if (!is_dir($this->backup_dir)) {
            $parentDir = dirname($this->backup_dir);
            
            // Vérifier que le répertoire parent n'est pas dans une AppImage
            if (strpos($parentDir, '.mount') !== false || 
                strpos($parentDir, 'AppDir') !== false || 
                strpos($parentDir, 'app.asar.unpacked') !== false) {
                error_log('BackupManager: Répertoire parent dans AppImage, utilisation du fallback');
                $this->backup_dir = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
                $parentDir = dirname($this->backup_dir);
            }
            
            // Vérifier que le répertoire parent est accessible en écriture
            if (!is_writable($parentDir) && !@mkdir($parentDir, 0755, true)) {
                error_log('BackupManager: Impossible de créer le répertoire parent: ' . $parentDir);
                // Utiliser le fallback
                $this->backup_dir = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
                $parentDir = dirname($this->backup_dir);
            }
            
            // Créer le répertoire de sauvegarde
            if (!@mkdir($this->backup_dir, 0755, true)) {
                $error = error_get_last();
                error_log('BackupManager: Erreur lors de la création du répertoire: ' . $this->backup_dir . ' - ' . ($error['message'] ?? 'Erreur inconnue'));
                // Essayer un répertoire de secours
                $this->backup_dir = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
                $parentDir = dirname($this->backup_dir);
                if (!is_dir($parentDir)) {
                    @mkdir($parentDir, 0755, true);
                }
                if (!is_dir($this->backup_dir)) {
                    @mkdir($this->backup_dir, 0755, true);
                }
            }
        }
        
        // Vérifier que le répertoire est accessible en écriture
        if (!is_writable($this->backup_dir)) {
            error_log('BackupManager: Répertoire non accessible en écriture: ' . $this->backup_dir);
            // Essayer un répertoire de secours
            $fallback = $this->getFallbackBackupDir() . DIRECTORY_SEPARATOR;
            $fallbackParent = dirname($fallback);
            if (is_writable($fallbackParent) || @mkdir($fallbackParent, 0755, true)) {
                $this->backup_dir = $fallback;
                if (!is_dir($this->backup_dir)) {
                    @mkdir($this->backup_dir, 0755, true);
                }
            } else {
                error_log('BackupManager: Impossible de créer un répertoire de sauvegarde accessible en écriture');
            }
        }
        
        error_log('BackupManager: Répertoire de sauvegarde final: ' . $this->backup_dir);
    }
    
    /**
     * Créer une sauvegarde de la base de données active
     */
    public function createBackup($backup_name = '') {
        $result = array();
        
        try {
            // Vérifier que le répertoire de sauvegarde est accessible
            if (!is_dir($this->backup_dir)) {
                $result['error'] = "Le répertoire de sauvegarde n'existe pas : $this->backup_dir";
                return $result;
            }
            
            if (!is_writable($this->backup_dir)) {
                $result['error'] = "Le répertoire de sauvegarde n'est pas accessible en écriture : $this->backup_dir";
                return $result;
            }
            
            // Obtenir le chemin de la base SQLite active
            $current_db_path = $this->conf['db_path'];
            
            if (!file_exists($current_db_path)) {
                $result['error'] = "Fichier de base de données non trouvé : $current_db_path";
                return $result;
            }
            
            // Générer le nom du fichier
            $timestamp = date('Y-m-d_H-i-s');
            $db_name = basename($current_db_path, '.sqlite');
            $filename = $backup_name ? $backup_name . '_' . $timestamp . '.sqlite' : $db_name . '_backup_' . $timestamp . '.sqlite';
            $filepath = $this->backup_dir . $filename;
            
            // Copier le fichier SQLite
            if (copy($current_db_path, $filepath)) {
                $result['success'] = "Sauvegarde créée avec succès : $filename";
                $result['filename'] = $filename;
                $result['size'] = $this->formatFileSize(filesize($filepath));
            } else {
                $error = error_get_last();
                $errorMsg = $error['message'] ?? 'Erreur inconnue';
                $result['error'] = "Erreur lors de la copie du fichier de base de données : $errorMsg";
                error_log("Erreur copy() : $errorMsg (source: $current_db_path, dest: $filepath)");
            }
            
        } catch (Exception $e) {
            $result['error'] = "Erreur lors de la création de la sauvegarde : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Restaurer une sauvegarde
     */
    public function restoreBackup($backup_file) {
        $result = array();
        
        try {
            $filepath = $this->backup_dir . $backup_file;
            
            if (!file_exists($filepath)) {
                $result['error'] = "Fichier de sauvegarde non trouvé : $backup_file";
                return $result;
            }
            
            // Obtenir le chemin de la base SQLite active
            $current_db_path = $this->conf['db_path'];
            
            // Créer une sauvegarde de sécurité avant restauration
            $safety_backup = $current_db_path . '.safety_' . date('Y-m-d_H-i-s');
            if (file_exists($current_db_path)) {
                copy($current_db_path, $safety_backup);
            }
            
            // Restaurer la sauvegarde en copiant le fichier
            if (copy($filepath, $current_db_path)) {
                $result['success'] = "Sauvegarde restaurée avec succès : $backup_file";
                $result['safety_backup'] = basename($safety_backup);
            } else {
                $result['error'] = "Erreur lors de la copie du fichier de sauvegarde";
            }
            
        } catch (Exception $e) {
            $result['error'] = "Erreur lors de la restauration : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Supprimer une sauvegarde
     */
    public function deleteBackup($backup_file) {
        $result = array();
        
        try {
            $filepath = $this->backup_dir . $backup_file;
            
            if (!file_exists($filepath)) {
                $result['error'] = "Fichier de sauvegarde non trouvé : $backup_file";
                return $result;
            }
            
            if (unlink($filepath)) {
                $result['success'] = "Sauvegarde supprimée avec succès : $backup_file";
            } else {
                $result['error'] = "Erreur lors de la suppression du fichier";
            }
            
        } catch (Exception $e) {
            $result['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Obtenir la liste des sauvegardes disponibles
     */
    public function getBackupsList() {
        $backups = array();
        
        if (is_dir($this->backup_dir)) {
            $files = glob($this->backup_dir . '*.sqlite');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $backups[] = array(
                    'filename' => $filename,
                    'size' => $this->formatFileSize(filesize($file)),
                    'date' => date('d/m/Y H:i', filemtime($file))
                );
            }
            
            // Trier par date de modification (plus récent en premier)
            usort($backups, function($a, $b) {
                return filemtime($this->backup_dir . $b['filename']) - filemtime($this->backup_dir . $a['filename']);
            });
        }
        
        return $backups;
    }
    
    /**
     * Charger une sauvegarde depuis un fichier uploadé
     */
    public function uploadBackup($uploaded_file) {
        $result = array();
        
        try {
            // Vérifier que le fichier a été uploadé
            if (!isset($uploaded_file['tmp_name']) || !is_uploaded_file($uploaded_file['tmp_name'])) {
                $result['error'] = "Aucun fichier valide n'a été uploadé";
                return $result;
            }
            
            // Vérifier l'extension du fichier
            $file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            if ($file_extension !== 'sqlite') {
                $result['error'] = "Le fichier doit être un fichier SQLite (.sqlite)";
                return $result;
            }
            
            // Vérifier la taille du fichier (max 50MB)
            if ($uploaded_file['size'] > 50 * 1024 * 1024) {
                $result['error'] = "Le fichier est trop volumineux (maximum 50MB)";
                return $result;
            }
            
            // Générer un nom unique pour le fichier
            $timestamp = date('Y-m-d_H-i-s');
            $filename = 'uploaded_' . $timestamp . '.sqlite';
            $filepath = $this->backup_dir . $filename;
            
            // Déplacer le fichier uploadé
            if (move_uploaded_file($uploaded_file['tmp_name'], $filepath)) {
                $result['success'] = "Fichier uploadé avec succès : $filename";
                $result['filename'] = $filename;
                $result['size'] = $this->formatFileSize(filesize($filepath));
            } else {
                $result['error'] = "Erreur lors de l'upload du fichier";
            }
            
        } catch (Exception $e) {
            $result['error'] = "Erreur lors de l'upload : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Formater la taille d'un fichier
     */
    private function formatFileSize($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        
        return round($size, 1) . ' ' . $units[$i];
    }

    private function resolveBackupDir(array $conf) {
        // 0) Détecter d'abord si on est dans une AppImage (AVANT toute autre résolution)
        // Si on est dans une AppImage, utiliser directement le fallback pour éviter tout risque
        $script_dir = __DIR__;
        $current_dir = getcwd();
        $isAppImageEarly = (
            strpos($script_dir, '.mount') !== false || 
            strpos($script_dir, 'AppDir') !== false ||
            strpos($current_dir, '.mount') !== false || 
            strpos($current_dir, 'AppDir') !== false ||
            strpos($script_dir, 'app.asar.unpacked') !== false ||
            strpos($current_dir, 'app.asar.unpacked') !== false
        );
        
        // Si on est dans une AppImage, utiliser directement le fallback (sauf si config explicite)
        if ($isAppImageEarly) {
            error_log('BackupManager resolveBackupDir: AppImage détectée tôt, utilisation directe du fallback');
            // Vérifier d'abord la config explicite et l'env (priorité)
            if (!empty($conf['backup_dir'])) {
                $explicitPath = $this->normalizePath($conf['backup_dir']);
                // Vérifier que le chemin explicite n'est pas dans l'AppImage
                if (strpos($explicitPath, '.mount') === false && 
                    strpos($explicitPath, 'AppDir') === false && 
                    strpos($explicitPath, 'app.asar.unpacked') === false) {
                    return $explicitPath;
                }
            }
            
            $envDir = getenv('DUPLI_BACKUP_DIR');
            if (!empty($envDir)) {
                $envPath = $this->normalizePath($envDir);
                // Vérifier que le chemin env n'est pas dans l'AppImage
                if (strpos($envPath, '.mount') === false && 
                    strpos($envPath, 'AppDir') === false && 
                    strpos($envPath, 'app.asar.unpacked') === false) {
                    return $envPath;
                }
            }
            
            // Sinon, utiliser directement le fallback
            return $this->getFallbackBackupDir();
        }
        
        // 1) Priorité config explicite
        if (!empty($conf['backup_dir'])) {
            return $this->normalizePath($conf['backup_dir']);
        }
        
        // 2) Variable d'environnement
        $envDir = getenv('DUPLI_BACKUP_DIR');
        if (!empty($envDir)) {
            return $this->normalizePath($envDir);
        }
        
        // 3) Détecter si on est dans une AppImage (plus fiable avec __DIR__)
        // Vérifier __DIR__ (chemin du fichier PHP) et getcwd() pour détecter l'AppImage
        $script_dir = __DIR__;
        $current_dir = getcwd();
        $isAppImage = (
            strpos($script_dir, '.mount') !== false || 
            strpos($script_dir, 'AppDir') !== false ||
            strpos($current_dir, '.mount') !== false || 
            strpos($current_dir, 'AppDir') !== false ||
            strpos($script_dir, 'app.asar.unpacked') !== false ||
            strpos($current_dir, 'app.asar.unpacked') !== false
        );
        
        // Vérifier aussi via le chemin de la base de données (si dans AppImage, il devrait être dans ~/.config)
        if (!$isAppImage && !empty($conf['db_path'])) {
            $dbPath = $conf['db_path'];
            // Si la DB est dans un répertoire .mount ou AppDir, on est dans une AppImage
            if (strpos($dbPath, '.mount') !== false || strpos($dbPath, 'AppDir') !== false || strpos($dbPath, 'app.asar.unpacked') !== false) {
                $isAppImage = true;
            }
        }
        
        // Log pour débogage
        error_log('BackupManager resolveBackupDir: script_dir=' . $script_dir . ', current_dir=' . $current_dir . ', isAppImage=' . ($isAppImage ? 'true' : 'false'));
        
        // 4) Défaut selon OS
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $appData = getenv('APPDATA');
            if (!empty($appData)) {
                return $this->normalizePath($appData . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
            }
            $userProfile = getenv('USERPROFILE');
            if (!empty($userProfile)) {
                return $this->normalizePath($userProfile . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
            }
            // Dernier recours Windows: utiliser un dossier local au projet (seulement si pas AppImage)
            if (!$isAppImage) {
                return $this->normalizePath(__DIR__ . '/../../sauvegarde');
            }
        }
        
        // Linux/Unix: utiliser XDG_CONFIG_HOME ou ~/.config
        // Pour AppImage, toujours utiliser le répertoire home de l'utilisateur
        if ($isAppImage) {
            // Pour AppImage, on DOIT utiliser un répertoire dans le home de l'utilisateur
            // Ne jamais utiliser un répertoire dans l'AppImage (read-only)
            error_log('BackupManager resolveBackupDir: AppImage détectée, utilisation du home utilisateur');
            
            $home = $_SERVER['HOME'] ?? getenv('HOME');
            if (empty($home)) {
                error_log('BackupManager resolveBackupDir: HOME non défini, utilisation de /tmp');
                $tmpDir = getenv('TMPDIR') ?: '/tmp';
                return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
            }
            
            // Utiliser le même répertoire que la base de données si possible
            $dbPath = $conf['db_path'] ?? '';
            if (!empty($dbPath)) {
                // Si la DB est dans le home, utiliser le même répertoire
                if (strpos($dbPath, $home) === 0) {
                    $dbDir = dirname($dbPath);
                    $backupPath = $this->normalizePath($dbDir . DIRECTORY_SEPARATOR . 'sauvegarde');
                    // Vérifier que le chemin n'est pas dans l'AppImage
                    if (strpos($backupPath, '.mount') === false && 
                        strpos($backupPath, 'AppDir') === false && 
                        strpos($backupPath, 'app.asar.unpacked') === false) {
                        // Essayer de créer le répertoire parent si nécessaire
                        $parentDir = dirname($backupPath);
                        if (is_dir($parentDir) || @mkdir($parentDir, 0755, true)) {
                            return $backupPath;
                        }
                    }
                }
            }
            
            // Sinon utiliser ~/.config/Duplicator/sauvegarde (cohérent avec conf.php)
            $homePath = $this->normalizePath($home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'Duplicator' . DIRECTORY_SEPARATOR . 'sauvegarde');
            // Vérifier que le chemin n'est pas dans l'AppImage
            if (strpos($homePath, '.mount') === false && 
                strpos($homePath, 'AppDir') === false && 
                strpos($homePath, 'app.asar.unpacked') === false) {
                $parentDir = dirname($homePath);
                // Essayer de créer le répertoire parent si nécessaire
                if (is_dir($parentDir) || @mkdir($parentDir, 0755, true)) {
                    return $homePath;
                }
            }
            
            // Si on arrive ici, utiliser /tmp (jamais l'AppImage)
            error_log('BackupManager resolveBackupDir: Utilisation de /tmp pour AppImage');
            $tmpDir = getenv('TMPDIR') ?: '/tmp';
            return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
        } else {
            // Pas AppImage: utiliser XDG_CONFIG_HOME ou ~/.config
            $xdg = getenv('XDG_CONFIG_HOME');
            if (!empty($xdg)) {
                $xdgPath = $this->normalizePath($xdg . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
                // Vérifier que le répertoire parent est accessible
                if (is_dir(dirname($xdgPath)) || @mkdir(dirname($xdgPath), 0755, true)) {
                    return $xdgPath;
                }
            }
            
            $home = getenv('HOME');
            if (!empty($home) && is_dir($home)) {
                $homePath = $this->normalizePath($home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
                // Vérifier que le répertoire parent est accessible
                if (is_dir(dirname($homePath)) || @mkdir(dirname($homePath), 0755, true)) {
                    return $homePath;
                }
            }
        }
        
        // Pour les utilisateurs système (www-data, etc.), utiliser /tmp ou /var/tmp
        $tmpDir = getenv('TMPDIR');
        if (empty($tmpDir)) {
            $tmpDir = '/tmp';
        }
        if (is_dir($tmpDir) && is_writable($tmpDir)) {
            return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
        }
        
        // Dernier recours Unix: dossier local au projet (seulement si pas AppImage)
        // IMPORTANT: Ne jamais utiliser ce chemin si on est dans une AppImage (read-only)
        // Vérifier TOUJOURS que __DIR__ n'est pas dans une AppImage avant d'utiliser ce chemin
        $scriptDirCheck = __DIR__;
        $isScriptInAppImage = (
            strpos($scriptDirCheck, '.mount') !== false || 
            strpos($scriptDirCheck, 'AppDir') !== false ||
            strpos($scriptDirCheck, 'app.asar.unpacked') !== false
        );
        
        if (!$isAppImage && !$isScriptInAppImage) {
            $localPath = $this->normalizePath(__DIR__ . '/../../sauvegarde');
            // Vérifier une dernière fois que le chemin résolu n'est pas dans une AppImage
            if (strpos($localPath, '.mount') === false && strpos($localPath, 'AppDir') === false && strpos($localPath, 'app.asar.unpacked') === false) {
                return $localPath;
            }
        }
        
        // Si on arrive ici, utiliser /tmp (jamais l'AppImage)
        error_log('BackupManager resolveBackupDir: Utilisation de /tmp comme dernier recours');
        return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
    }
    
    /**
     * Obtenir un répertoire de sauvegarde de secours
     */
    /**
     * Obtenir un répertoire de sauvegarde de secours
     * Garantit toujours un répertoire accessible en écriture, jamais dans l'AppImage
     */
    private function getFallbackBackupDir() {
        // Essayer d'abord le home de l'utilisateur
        $home = $_SERVER['HOME'] ?? getenv('HOME');
        if (!empty($home) && is_dir($home)) {
            $fallbackPath = $this->normalizePath($home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'Duplicator' . DIRECTORY_SEPARATOR . 'sauvegarde');
            // Vérifier que le chemin n'est pas dans l'AppImage
            if (strpos($fallbackPath, '.mount') === false && 
                strpos($fallbackPath, 'AppDir') === false && 
                strpos($fallbackPath, 'app.asar.unpacked') === false) {
                return $fallbackPath;
            }
        }
        
        // Sinon utiliser /tmp (toujours accessible en écriture)
        $tmpDir = getenv('TMPDIR');
        if (empty($tmpDir)) {
            $tmpDir = '/tmp';
        }
        return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
    }

    private function normalizePath($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $path = trim($path);
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
}
?>


