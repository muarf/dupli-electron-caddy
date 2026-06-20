<?php
/**
 * API de Purge Sécurisée Automatique (Retention Policy)
 * Appelé par l'application Electron au démarrage et périodiquement.
 * Supprime les jobs > 7 jours et nettoie les fichiers associés de manière sécurisée.
 */

// Désactiver l'affichage des erreurs pour le JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    require_once(__DIR__ . '/../controler/func.php');
    require_once(__DIR__ . '/../controler/conf.php');
    require_once(__DIR__ . '/../controler/functions/database.php');
    require_once(__DIR__ . '/../controler/functions/secure_delete.php');

    // Initialiser la DB
    $db = create_database_manager();

    // 1. Identifier les jobs vieux de plus de 7 jours
    // On regarde dans print_jobs ET recorded_print_jobs
    // La politique de rétention est stricte : 7 jours max de conservation
    
    $retentionDays = 7;
    $cutoffDate = date('Y-m-d H:i:s', strtotime("-$retentionDays days"));

    $stats = [
        'jobs_deleted' => 0,
        'files_shredded' => 0,
        'errors' => []
    ];

    // --- A. Nettoyage de la table principale print_jobs (spool actif/récent) ---
    // Note: Normalement print_jobs est vidé après impression/session, mais on nettoie au cas où
    $oldActiveJobs = $db->select("SELECT id, document, thumbnail_url FROM print_jobs WHERE timestamp < ?", [$cutoffDate]);

    foreach ($oldActiveJobs as $job) {
        // 1. Suppression sécurisée des fichiers
        if (!empty($job['document'])) {
            if (secure_delete($job['document'])) {
                $stats['files_shredded']++;
            }
        }
        if (!empty($job['thumbnail_url'])) {
            // thumbnail_url est souvent relative ou URL absolue, il faut résoudre le chemin local
            // Exemple URL: http://.../thumbnails/file.png ou juste thumbnails/file.png
            $thumbPath = resolve_local_path($job['thumbnail_url']);
            if ($thumbPath && secure_delete($thumbPath)) {
                $stats['files_shredded']++;
            }
        }

        // 2. Suppression base de données
        $db->execute("DELETE FROM print_jobs WHERE id = ?", [$job['id']]);
        $stats['jobs_deleted']++;
    }

    // --- B. Nettoyage de l'historique recorded_print_jobs ---
    // Cette table ne contient que les références logiques (job_id, printer_name, timestamps)
    // Les fichiers sont gérés via print_jobs.
    
    // On vérifie si la colonne created_at existe, sinon on skip ou on utilise une autre colonne
    // Suppression simple des enregistrements vieux
    try {
        $oldHistoryJobs = $db->select("SELECT job_id, printer_name FROM recorded_print_jobs WHERE recorded_at < ?", [$cutoffDate]);

        foreach ($oldHistoryJobs as $job) {
            // Suppression base de données uniquement (pas de fichiers liés ici)
            $db->execute("DELETE FROM recorded_print_jobs WHERE job_id = ? AND printer_name = ?", [$job['job_id'], $job['printer_name']]);
            $stats['jobs_deleted']++;
        }
    } catch (Exception $ex) {
         // Si erreur (ex: colonne created_at manquante ou autre), on log mais on ne bloque pas tout
         error_log("[SECURE PURGE] Erreur nettoyage historique: " . $ex->getMessage());
         $stats['errors'][] = "History purge failed: " . $ex->getMessage();
    }

    // --- C. Nettoyage des logs obsolètes ou trop volumineux ---
    // On nettoie le fichier de log principal s'il est trop gros (> 50MB) ou trop vieux (rotation basique)
    // Ici on va juste truncate si trop gros pour l'exemple, ou supprimer les vieux fichiers de log si on en avait.
    // Pour l'instant on se concentre sur les données utilisateurs (jobs).
    
    // --- D. Nettoyage automatique des dossiers de miniatures obsolètes (> 7 jours) ---
    $thumbnailsBaseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'thumbnails';
    if (is_dir($thumbnailsBaseDir)) {
        $dirs = scandir($thumbnailsBaseDir);
        $oneWeekAgo = time() - (7 * 24 * 60 * 60);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            $dirPath = $thumbnailsBaseDir . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($dirPath)) {
                $mtime = filemtime($dirPath);
                if ($mtime < $oneWeekAgo) {
                    // Supprimer récursivement le répertoire de miniatures obsolète
                    rrmdir_secure($dirPath);
                    $stats['jobs_deleted']++; // Comptabiliser dans les stats globales de nettoyage
                }
            }
        }
    }

    // Loguer l'exécution de la purge
    if ($stats['jobs_deleted'] > 0) {
        error_log("[SECURE PURGE] Purge automatique effectuée : " . json_encode($stats));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Purge sécurisée terminée',
        'stats' => $stats,
        'cutoff_date' => $cutoffDate
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Convertit une URL de thumbnail ou un chemin relatif en chemin système absolu
 */
function resolve_local_path($urlOrPath) {
    // Si c'est déjà un chemin absolu Windows
    if (preg_match('/^[a-zA-Z]:\\\\/', $urlOrPath)) {
        return $urlOrPath;
    }

    // Nettoyer l'URL pour garder la partie relative
    // Ex: http://localhost:8000/thumbnails/xyz.png -> thumbnails/xyz.png
    $relativePath = parse_url($urlOrPath, PHP_URL_PATH);
    $relativePath = ltrim($relativePath, '/'); 

    // Construire le chemin depuis la racine publique de l'app
    // On suppose que ce script est dans app/api/
    $baseDir = dirname(__DIR__) . '/public/'; // app/public/
    
    $fullPath = $baseDir . $relativePath;
    
    // Normaliser les slashs
    $fullPath = str_replace('/', DIRECTORY_SEPARATOR, $fullPath);

    return $fullPath;
}

/**
 * Supprime récursivement un répertoire et tous ses fichiers
 */
function rrmdir_secure($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $filePath = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($filePath) && !is_link($filePath)) {
                    rrmdir_secure($filePath);
                } else {
                    @unlink($filePath);
                }
            }
        }
        @rmdir($dir);
    }
}
