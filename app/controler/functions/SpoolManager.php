<?php
/**
 * Gestionnaire de fichiers Spool Windows
 * Permet de localiser et supprimer les fichiers SPL et SHD associés à un Job ID
 */

require_once __DIR__ . '/secure_delete.php';

class SpoolManager {

    /**
     * Dossier de spool Windows standard
     */
    private static $spoolDir = 'C:\\Windows\\System32\\spool\\PRINTERS\\';

    /**
     * Tente de trouver le fichier SPL associé à un Job ID
     * Logique dupliquée/adaptée de convert-pcl-to-png.php
     * 
     * @param int $jobId
     * @return string|null Chemin absolu du fichier SPL ou null si non trouvé
     */
    public static function findSpoolFile($jobId) {
        $jobId = intval($jobId);
        if ($jobId <= 0) return null;

        $dir = self::$spoolDir;
        if (!is_dir($dir)) return null;

        // 1. Essai nommage standard (ex: 00105.SPL)
        // Le format est généralement 5 chiffres avec padding zeros
        $standardName = sprintf('%s%05d.SPL', $dir, $jobId);
        if (file_exists($standardName)) {
            return $standardName;
        }

        // 2. Scan des fichiers SHD pour trouver le mapping
        // Certains drivers ou configurations (File Pooling) utilisent des noms aléatoires (ex: FP00123.SPL)
        // Le lien se fait via le fichier .SHD qui contient le Job ID binaire
        $shdFiles = glob($dir . '*.SHD');
        $mostRecentFpSpl = null;
        $mostRecentTime = 0;

        foreach ($shdFiles as $shdFile) {
            $baseName = pathinfo($shdFile, PATHINFO_FILENAME);
            $correspondingSpl = $dir . $baseName . '.SPL';

            // Optimisation: check si le SPL existe avant d'ouvrir le SHD
            if (!file_exists($correspondingSpl)) continue;

            // Tracking pour fallback (logiciel "File Pooling" FPxxxxx)
            if (strpos($baseName, 'FP') === 0) {
                $mtime = filemtime($correspondingSpl);
                if ($mtime > $mostRecentTime) {
                    $mostRecentTime = $mtime;
                    $mostRecentFpSpl = $correspondingSpl;
                }
            }

            // Lire le SHD pour trouver l'ID
            $shdJobId = self::readJobIdFromShd($shdFile);
            if ($shdJobId == $jobId) {
                return $correspondingSpl;
            }
        }

        // 3. Fallback: Si on n'a rien trouvé mais qu'il y a des fichiers FP récents
        // C'est risqué de deviner, on préfère ne rien retourner si pas de certitude absolue via SHD ou Nom Standard
        // Sauf si on veut reproduire la logique "best effort" de convert-pcl-to-png
        // Pour la suppression, soyons PRUDENTS. Si on ne trouve pas l'ID exact, on ne supprime pas.
        
        return null;
    }

    /**
     * Lit le Job ID depuis un fichier SHD (Shadow File)
     */
    private static function readJobIdFromShd($shdPath) {
        if (!file_exists($shdPath) || filesize($shdPath) < 16) {
            return 0;
        }
        
        $handle = fopen($shdPath, 'rb');
        if (!$handle) return 0;
        
        $data = fread($handle, 16);
        fclose($handle);
        
        if (strlen($data) < 16) return 0;

        // Essai offset 12 (Windows 10/11/Server récents)
        $jobId = unpack('V', substr($data, 12, 4))[1];
        if ($jobId > 0 && $jobId < 1000000) return $jobId; // Job IDs sont généralement petits

        // Fallback offset 8 (Vieux Windows)
        $jobId = unpack('V', substr($data, 8, 4))[1];
        if ($jobId > 0 && $jobId < 1000000) return $jobId;

        return 0;
    }

    /**
     * Supprime de manière sécurisée les fichiers de spool (SPL et SHD) pour un Job ID donné
     * 
     * @param int $jobId
     * @return array Résultat de l'opération
     */
    public static function deleteSpoolFiles($jobId) {
        $result = [
            'success' => false,
            'files_deleted' => [],
            'errors' => []
        ];

        try {
            $splPath = self::findSpoolFile($jobId);
            
            if ($splPath) {
                // Fichier SPL trouvé
                if (secure_delete($splPath)) {
                    $result['files_deleted'][] = basename($splPath);
                } else {
                    $result['errors'][] = "Impossible de supprimer le fichier SPL: " . basename($splPath);
                }

                // Chercher le fichier SHD correspondant (même nom de base)
                $baseName = pathinfo($splPath, PATHINFO_FILENAME); // ex: 00105 ou FP01234
                $shdPath = dirname($splPath) . DIRECTORY_SEPARATOR . $baseName . '.SHD';
                
                if (file_exists($shdPath)) {
                    if (secure_delete($shdPath)) {
                        $result['files_deleted'][] = basename($shdPath);
                    } else {
                        $result['errors'][] = "Impossible de supprimer le fichier SHD: " . basename($shdPath);
                    }
                }
            } else {
                // Pas de fichier trouvé, peut-être déjà supprimé ou ID introuvable
                // On ne considère pas ça comme une erreur critique
                $result['errors'][] = "Aucun fichier de spool trouvé pour le Job ID $jobId";
            }

            $result['success'] = count($result['files_deleted']) > 0;

        } catch (Exception $e) {
            $result['errors'][] = "Erreur inattendue: " . $e->getMessage();
        }

        // Log
        if (!empty($result['files_deleted'])) {
            error_log("[SPOOL MANAGER] Suppression fichiers Job $jobId : " . implode(', ', $result['files_deleted']));
        }

        return $result;
    }
}
