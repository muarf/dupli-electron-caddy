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
            mkdir($this->backup_dir, 0755, true);
        }
        
        // Optionnel: journaliser si non inscriptible
        if (!is_writable($this->backup_dir)) {
             error_log('Backup dir not writable: ' . $this->backup_dir);
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
        
        // 3) Défaut selon OS
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
            return $this->normalizePath($xdg . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
        }
        $home = getenv('HOME');
        if (!empty($home)) {
            return $this->normalizePath($home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'dupli-electron' . DIRECTORY_SEPARATOR . 'sauvegarde');
        }
        
        // Dernier recours Unix: dossier local au projet
        return $this->normalizePath(__DIR__ . '/../../sauvegarde');
    }

    private function normalizePath($path) {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $path = trim($path);
        return rtrim($path, DIRECTORY_SEPARATOR);
    }
}
?>


