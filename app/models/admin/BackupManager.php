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
        $this->backup_dir = rtrim($this->resolveBackupDir($conf), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
        // Créer le dossier de sauvegarde s'il n'existe pas
        if (!is_dir($this->backup_dir)) {
            try {
                if (!@mkdir($this->backup_dir, 0755, true)) {
                    $error = error_get_last();
                    if ($error && strpos($error['message'], 'Read-only file system') !== false) {
                        // Si on est dans un système de fichiers en lecture seule, utiliser un fallback
                        error_log('Backup dir in read-only filesystem, using fallback: ' . $this->backup_dir);
                        $this->backup_dir = rtrim($this->getFallbackBackupDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                        if (!is_dir($this->backup_dir)) {
                            @mkdir($this->backup_dir, 0755, true);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error creating backup dir: ' . $e->getMessage());
                // Utiliser un fallback
                $this->backup_dir = rtrim($this->getFallbackBackupDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (!is_dir($this->backup_dir)) {
                    @mkdir($this->backup_dir, 0755, true);
                }
            }
        }
        
        // Optionnel: journaliser si non inscriptible
        if (!is_writable($this->backup_dir)) {
             error_log('Backup dir not writable: ' . $this->backup_dir);
             // Essayer un fallback
             $fallback = $this->getFallbackBackupDir();
             if (is_writable(dirname($fallback))) {
                 $this->backup_dir = rtrim($fallback, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                 if (!is_dir($this->backup_dir)) {
                     @mkdir($this->backup_dir, 0755, true);
                 }
             }
        }
    }
    
    /**
     * Créer une sauvegarde de la base de données active
     */
    public function createBackup($backup_name = '') {
        $result = array();
        
        try {
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
                $result['error'] = "Erreur lors de la copie du fichier de base de données";
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
     * Obtenir le chemin du dossier de sauvegarde
     */
    public function getBackupDir() {
        return $this->backup_dir;
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
        // 1) Priorité config explicite
        if (!empty($conf['backup_dir'])) {
            return $this->normalizePath($conf['backup_dir']);
        }
        
        // 2) Variable d'environnement
        $envDir = getenv('DUPLI_BACKUP_DIR');
        if (!empty($envDir)) {
            return $this->normalizePath($envDir);
        }
        
        // 3) Détection AppImage (avant les autres pour Linux)
        // Vérifier la variable d'environnement APPIMAGE (définie automatiquement par AppImage)
        if (getenv('APPIMAGE') !== false) {
            // AppImage détectée via variable d'environnement
            $home_dir = $_SERVER['HOME'] ?? getenv('HOME');
            
            if (!empty($home_dir) && is_dir($home_dir) && is_writable($home_dir)) {
                $appImagePath = $this->normalizePath($home_dir . '/.config/dupli-electron/sauvegarde');
                // Vérifier que le répertoire parent est accessible
                if (is_dir(dirname($appImagePath)) || @mkdir(dirname($appImagePath), 0755, true)) {
                    return $appImagePath;
                }
            }
            
            // Fallback si HOME n'est pas accessible : dossier temporaire système
            return $this->normalizePath(sys_get_temp_dir() . '/duplicator_backups');
        }
        
        // Vérifier si __DIR__ ou __FILE__ contient .mount (plus fiable que getcwd())
        $script_dir = __DIR__;
        $script_file = __FILE__;
        $current_dir = getcwd();
        
        if (strpos($script_dir, '.mount') !== false || 
            strpos($script_file, '.mount') !== false || 
            strpos($current_dir, '.mount') !== false ||
            strpos($script_dir, 'AppDir') !== false ||
            strpos($script_file, 'AppDir') !== false ||
            strpos($current_dir, 'AppDir') !== false) {
            // AppImage détectée via chemin de fichier
            $home_dir = $_SERVER['HOME'] ?? getenv('HOME');
            
            if (!empty($home_dir) && is_dir($home_dir) && is_writable($home_dir)) {
                $appImagePath = $this->normalizePath($home_dir . '/.config/dupli-electron/sauvegarde');
                // Vérifier que le répertoire parent est accessible
                if (is_dir(dirname($appImagePath)) || @mkdir(dirname($appImagePath), 0755, true)) {
                    return $appImagePath;
                }
            }
            
            // Fallback si HOME n'est pas accessible : dossier temporaire système
            return $this->normalizePath(sys_get_temp_dir() . '/duplicator_backups');
        }
        
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
            // Dernier recours Windows: utiliser un dossier local au projet
            return $this->normalizePath(__DIR__ . '/../../sauvegarde');
        }
        
        // Linux/Unix: utiliser XDG_CONFIG_HOME ou ~/.config
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
        
        // Pour les utilisateurs système (www-data, etc.), utiliser /tmp ou /var/tmp
        $tmpDir = getenv('TMPDIR');
        if (empty($tmpDir)) {
            $tmpDir = '/tmp';
        }
        if (is_dir($tmpDir) && is_writable($tmpDir)) {
            return $this->normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'dupli-electron-sauvegarde');
        }
        
        // Dernier recours Unix: dossier temporaire système
        return $this->normalizePath(sys_get_temp_dir() . '/duplicator_backups');
    }

    private function normalizePath($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $path = trim($path);
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
    
    /**
     * Obtenir un répertoire de sauvegarde de secours (fallback)
     * Utilisé quand le répertoire principal n'est pas accessible en écriture
     */
    private function getFallbackBackupDir() {
        // Windows
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $appData = getenv('APPDATA');
            if (!empty($appData)) {
                return $this->normalizePath($appData . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
            }
            $userProfile = getenv('USERPROFILE');
            if (!empty($userProfile)) {
                return $this->normalizePath($userProfile . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
            }
        }
        
        // Linux/Unix - utiliser XDG_DATA_HOME ou ~/.local/share
        $xdgData = getenv('XDG_DATA_HOME');
        if (!empty($xdgData)) {
            return $this->normalizePath($xdgData . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
        }
        
        $home = getenv('HOME');
        if (!empty($home) && is_dir($home)) {
            return $this->normalizePath($home . DIRECTORY_SEPARATOR . '.local' . DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
        }
        
        // Dernier recours : dossier temporaire système
        return $this->normalizePath(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_backups');
    }
}
