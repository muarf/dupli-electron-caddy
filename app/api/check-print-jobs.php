<?php
/**
 * Script pour vérifier les enregistrements d'impression dans la base de données
 */

// NOUVEAU: Proxy pour les miniatures sur Linux (car dossier app/public est en lecture seule sur AppImage)
if (isset($_GET['get_linux_thumb'])) {
    $filename = basename($_GET['get_linux_thumb']);
    $thumbPath = "/tmp/dupli_thumbnails/" . $filename;
    if (file_exists($thumbPath)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        readfile($thumbPath);
        exit;
    }
    // Fallback si l'image n'existe pas encore
    http_response_code(404);
    exit;
}

// Désactiver l'affichage des erreurs pour éviter de polluer le JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Définir les headers AVANT toute sortie
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// #region agent log
function __agentNdjson(string $hypothesisId, string $message, array $data = [], string $runId = 'pre'): void
{
    try {
        $payload = [
            'sessionId' => '8593f8',
            'runId' => $runId,
            'hypothesisId' => $hypothesisId,
            'location' => 'app/api/check-print-jobs.php',
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) (microtime(true) * 1000),
        ];
        $logPath = __DIR__ . '/../../debug-8593f8.log';
        @file_put_contents($logPath, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    } catch (Throwable $e) {
        // ignore
    }
}
// #endregion

// Gérer les requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// Gérer les requêtes POST (Actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer le corps de la requête (JSON)
    $input = json_decode(file_get_contents('php://input'), true);

    // Si pas de JSON, regarder $_POST (form-data)
    if (!$input) {
        $input = $_POST;
    }

    if (isset($input['action'])) {
        ob_start(); // Buffer pour capturer les erreurs inattendues

        try {
            require_once(__DIR__ . '/../controler/conf.php');
            require_once(__DIR__ . '/../controler/functions/database.php');
            require_once(__DIR__ . '/../controler/functions/secure_delete.php'); // Inclusion de la purge sécurisée

            // Définir la fonction de résolution de chemin pour toutes les actions de suppression
            $resolvePath = function($urlOrPath) {
                if (empty($urlOrPath)) return null;
                if (preg_match('/^[a-zA-Z]:\\\\/', $urlOrPath)) return $urlOrPath;
                $relativePath = parse_url($urlOrPath, PHP_URL_PATH);
                $relativePath = ltrim($relativePath, '/'); 
                $baseDir = dirname(__DIR__) . '/../public/'; 
                return str_replace('/', DIRECTORY_SEPARATOR, $baseDir . $relativePath);
            };

            // Nettoyer le buffer
            ob_end_clean();
            $db = create_database_manager();

            $action = $input['action'];

            if ($action === 'delete_jobs') {
                if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                    throw new Exception("Aucun ID spécifié pour la suppression");
                }

                $ids = array_map('intval', $input['ids']);
                $ids_string = implode(',', $ids);

                // --- NOUVEAU: Annuler les tâches dans Windows avant de supprimer de la base ---
                try {
                    // Charger le SpoolManager
                    require_once(__DIR__ . '/../controler/functions/SpoolManager.php');

                    // Récupérer les détails des jobs pour l'annulation Windows
                    $jobsToCancel = $db->select("SELECT job_id, printer_name FROM print_jobs WHERE id IN ($ids_string)");

                    foreach ($jobsToCancel as $job) {
                        if (!empty($job['job_id'])) {
                            $jobId = intval($job['job_id']);
                            $printerName = $job['printer_name'] ?? '';

                            // Suppression des fichiers SPL/SHD
                            // Note: La suppression du job Windows est gérée par Electron IPC (frontend)
                            // car PHP-FPM n'a pas accès à powershell.exe via PATH
                            error_log("[API] Suppression fichiers spool pour job $jobId");
                            SpoolManager::deleteSpoolFiles($jobId);
                        }
                    }
                } catch (Exception $cancelEx) {
                    error_log("[API] Erreur technique lors de l'annulation système: " . $cancelEx->getMessage());
                    // On continue la suppression en base même si l'annulation système échoue
                }
                // ----------------------------------------------------------------------------

                // ----------------------------------------------------------------------------
                // --- NOUVEAU: Suppression des enregistrements de paiement liés ---
                $jobsForCleanup = $db->select("SELECT document, printer_name, total_pages, copies FROM print_jobs WHERE id IN ($ids_string)");
                foreach ($jobsForCleanup as $job) {
                    $doc = $job['document'];
                    $printer = $job['printer_name'];
                    
                    // Suppression dans dupli (recherche par nom document et machine)
                    $db->execute("DELETE FROM dupli WHERE document_name = ? AND nom_machine = ?", [$doc, $printer]);
                    
                    // Suppression dans photocop
                    $db->execute("DELETE FROM photocop WHERE document_name = ? AND marque = ?", [$doc, $printer]);
                }
                // ----------------------------------------------------------------------------

                // --- NOUVEAU: Suppression SÉCURISÉE des fichiers (Shredding) ---
                $jobsToDelete = $db->select("SELECT document, thumbnail_url FROM print_jobs WHERE id IN ($ids_string)");
                
                foreach ($jobsToDelete as $job) {
                    if (!empty($job['document'])) {
                        secure_delete($job['document']);
                    }
                    if (!empty($job['thumbnail_url'])) {
                        $thumbPath = $resolvePath($job['thumbnail_url']);
                        if ($thumbPath) {
                            secure_delete($thumbPath);
                        }
                    }
                }
                // ----------------------------------------------------------------------------

                // Utiliser la méthode execute() de DatabaseManager

                $db->execute("DELETE FROM print_jobs WHERE id IN ($ids_string)");

                echo json_encode(['success' => true, 'message' => count($ids) . ' impression(s) supprimée(s) et annulée(s) dans le spooler']);
                exit;

            } elseif ($action === 'delete_by_job_id') {
                if (!isset($input['job_id'])) {
                    throw new Exception("Aucun Job ID spécifié");
                }

                $jobId = strval($input['job_id']);
                
                // On récupère d'abord les infos pour le shredding des fichiers
                $jobsToDelete = $db->select("SELECT id, document, thumbnail_url FROM print_jobs WHERE job_id = ?", [$jobId]);
                
                if (empty($jobsToDelete)) {
                    echo json_encode(['success' => true, 'message' => 'Aucun job trouvé en base pour cet ID']);
                    exit;
                }

                $idsToDelete = [];
                
                foreach ($jobsToDelete as $job) {
                    $idsToDelete[] = $job['id'];
                    
                    if (!empty($job['document'])) {
                        secure_delete($job['document']);
                    }
                    if (!empty($job['thumbnail_url'])) {
                        $thumbPath = $resolvePath($job['thumbnail_url']);
                        if ($thumbPath) {
                            secure_delete($thumbPath);
                        }
                    }
                }

                // Suppression finale en base
                $ids_string = implode(',', $idsToDelete);
                $db->execute("DELETE FROM print_jobs WHERE id IN ($ids_string)");

                echo json_encode(['success' => true, 'message' => 'Job(s) nettoyé(s) par job_id Windows']);
                exit;

            } elseif ($action === 'purge_all') {
                // === PURGE COMPLÈTE (Historique d'impressions seulement) ===
                // Supprime: print_jobs, recorded_print_jobs, et tous les fichiers associés
                // Préserve: photocop, dupli (tables de paiement)
                
                // 1. Récupérer tous les fichiers à supprimer AVANT de vider la DB
                $jobsWithFiles = $db->select("SELECT document, thumbnail_url FROM print_jobs");
                
                $filesDeleted = 0;
                $dirsDeleted = 0;
                
                // 2. Suppression sécurisée des fichiers
                foreach ($jobsWithFiles as $job) {
                    if (!empty($job['document'])) {
                        if (secure_delete($job['document'])) {
                            $filesDeleted++;
                        }
                    }
                    if (!empty($job['thumbnail_url'])) {
                        $thumbPath = $resolvePath($job['thumbnail_url']);
                        if ($thumbPath && secure_delete($thumbPath)) {
                            $filesDeleted++;
                        }
                    }
                }
                
                // 3. Nettoyer les dossiers thumbnails orphelins
                $thumbnailsDir = __DIR__ . '/../public/thumbnails';
                if (is_dir($thumbnailsDir)) {
                    $dirs = scandir($thumbnailsDir);
                    foreach ($dirs as $d) {
                        if ($d === '.' || $d === '..') continue;
                        $dirPath = $thumbnailsDir . '/' . $d;
                        if (is_dir($dirPath)) {
                            // Supprimer tous les fichiers du dossier
                            $files = scandir($dirPath);
                            foreach ($files as $f) {
                                if ($f === '.' || $f === '..') continue;
                                @unlink($dirPath . '/' . $f);
                            }
                            // Supprimer le dossier vide
                            if (@rmdir($dirPath)) {
                                $dirsDeleted++;
                            }
                        }
                    }
                }
                
                // 4. Vider les tables d'historique
                $db->execute("DELETE FROM print_jobs");
                $db->execute("DELETE FROM recorded_print_jobs");
                
                // Note: On ne touche PAS aux tables photocop, dupli, etc. (tables de facturation)
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Purge complète effectuée : $filesDeleted fichiers et $dirsDeleted dossiers supprimés"
                ]);
                exit;

            } elseif ($action === 'regenerate_thumbnails') {
                // === LISTE DES JOBS SANS THUMBNAIL ===
                // Retourne la liste des jobs récents sans thumbnail pour que le JS fasse les conversions
                
                $thirtyMinutesAgo = date('Y-m-d H:i:s', strtotime('-30 minutes'));
                
                // Trouver les jobs sans thumbnail
                $jobsWithoutThumbnail = $db->select("
                    SELECT id, job_id, document, printer_name 
                    FROM print_jobs 
                    WHERE (thumbnail_url IS NULL OR thumbnail_url = '') 
                    AND created_at >= ?
                    ORDER BY created_at DESC
                    LIMIT 5
                ", [$thirtyMinutesAgo]);
                
                echo json_encode([
                    'success' => true, 
                    'jobs' => $jobsWithoutThumbnail
                ]);
                exit;

            } elseif ($action === 'update_thumbnail') {
                // === MISE À JOUR D'UNE THUMBNAIL ===
                // Appelé par le JS après avoir généré une thumbnail via l'API de conversion
                
                if (!isset($input['id']) || !isset($input['thumbnail_url'])) {
                    throw new Exception("Paramètres manquants (id, thumbnail_url)");
                }
                
                $id = intval($input['id']);
                $thumbnailUrl = $input['thumbnail_url'];
                
                $db->execute("UPDATE print_jobs SET thumbnail_url = ? WHERE id = ?", [$thumbnailUrl, $id]);
                
                echo json_encode(['success' => true]);
                exit;

            } elseif ($action === 'update_job_analysis') {
                // === MISE À JOUR COMPLÈTE APRÈS RÉANALYSE C++ ===
                // Met à jour thumbnail, fill_rate, color_mode et total_pages
                
                $targetId = null;
                
                // Priorité: utiliser job_id Windows si fourni
                if (isset($input['job_id'])) {
                    $jobIdWindows = strval($input['job_id']);
                    $printerName = $input['printer_name'] ?? '';
                    
                    // Chercher par job_id Windows
                    $existingJob = $db->selectOne(
                        "SELECT id FROM print_jobs WHERE job_id = ? AND printer_name = ? ORDER BY created_at DESC LIMIT 1",
                        [$jobIdWindows, $printerName]
                    );
                    
                    if ($existingJob) {
                        $targetId = $existingJob['id'];
                    }
                }
                
                // Fallback: utiliser id DB direct
                if (!$targetId && isset($input['id'])) {
                    $targetId = intval($input['id']);
                }
                
                if (!$targetId) {
                    throw new Exception("Paramètre manquant (id ou job_id)");
                }
                
                $thumbnailUrl = $input['thumbnail_url'] ?? null;
                $fillRate = isset($input['fill_rate']) ? floatval($input['fill_rate']) : null;
                // Corriger: (bool)'false' = true en PHP, il faut utiliser filter_var
                $isGrayscaleRaw = $input['is_grayscale'] ?? null;
                $isGrayscale = $isGrayscaleRaw !== null && $isGrayscaleRaw !== false && $isGrayscaleRaw !== 'false' && $isGrayscaleRaw !== '0' && $isGrayscaleRaw !== 0;
                
                // Construire la requête dynamiquement
                $updates = [];
                $params = [];
                
                if ($thumbnailUrl !== null) {
                    $updates[] = "thumbnail_url = ?";
                    $params[] = $thumbnailUrl;
                }
                if ($fillRate !== null) {
                    $updates[] = "fill_rate = ?";
                    $params[] = $fillRate;
                }
                if ($isGrayscaleRaw !== null) {
                    $colorMode = !$isGrayscale ? 'Color' : 'Monochrome';
                    error_log("[update_job_analysis] is_grayscale raw=" . var_export($isGrayscaleRaw, true) . " -> color_mode=" . $colorMode);
                    $updates[] = "color_mode = ?";
                    $params[] = $colorMode;
                }
                if (isset($input['total_pages']) && intval($input['total_pages']) > 0) {
                    $updates[] = "total_pages = ?";
                    $params[] = intval($input['total_pages']);
                }
                
                if (!empty($updates)) {
                    $params[] = $targetId;
                    $sql = "UPDATE print_jobs SET " . implode(', ', $updates) . " WHERE id = ?";
                    $db->execute($sql, $params);
                }
                
                echo json_encode(['success' => true, 'updated_id' => $targetId]);
                exit;

            } else {
                throw new Exception("Action inconnue: " . $action);
            }

        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}


// Empêcher toute sortie avant le JSON
ob_start();

try {
    $t0 = microtime(true);
    __agentNdjson('E', 'check_print_jobs: start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'query' => $_GET ?? null,
    ]);

    require_once(__DIR__ . '/../controler/conf.php');
    require_once(__DIR__ . '/../controler/functions/database.php');
    require_once(__DIR__ . '/../controler/functions/utilities.php');

    // Nettoyer le buffer de sortie
    ob_end_clean();
    $db = create_database_manager();
    __agentNdjson('E', 'check_print_jobs: db connected', ['elapsed_ms' => (int)((microtime(true)-$t0)*1000)]);

    // Vérifier si la table existe
    if (!$db->tableExists('print_jobs')) {
        __agentNdjson('E', 'check_print_jobs: no table', ['elapsed_ms' => (int)((microtime(true)-$t0)*1000)]);
        echo json_encode([
            'success' => false,
            'error' => 'La table print_jobs n\'existe pas encore',
            'message' => 'Aucune impression n\'a été détectée pour le moment'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Purge automatique des vieux enregistrements de traitement (7 jours)
    // Cela évite les collisions dues au recyclage des job_id par Windows
    try {
        $db->execute("DELETE FROM recorded_print_jobs WHERE recorded_at < datetime('now', '-7 days')");
    } catch (Exception $e) {
        // Ignorer silencieusement si la purge échoue pour ne pas bloquer l'affichage
        error_log("Purge recorded_print_jobs failed: " . $e->getMessage());
    }

    // Gestion du filtre d'historique et de session
    $show_history = isset($_GET['history']) && $_GET['history'] === 'true';
    $sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;
    
    if ($show_history) {
        $where_clause = "";
    } else {
        $where_clause = "WHERE rpj.print_job_id IS NULL AND (pj.session_id IS NULL";
        if ($sessionId) {
            $where_clause .= " OR pj.session_id = $sessionId";
        }
        $where_clause .= ")";
    }

    $sql = "
        SELECT
            pj.id,
            pj.job_id,
            pj.document,
            pj.document_full_path,
            pj.document_display_name,
            pj.owner,
            pj.printer_name,
            pj.status,
            pj.pages_printed,
            pj.total_pages,
            pj.size,
            pj.paper_size,
            pj.duplex,
            pj.color_mode,
            pj.copies,
            pj.fill_rate,
            pj.thumbnail_url,
            pj.time_submitted,
            pj.event_type,
            pj.timestamp,
            pj.created_at,
            (CASE WHEN rpj.print_job_id IS NOT NULL THEN 1 ELSE 0 END) as is_recorded
        FROM print_jobs pj
        LEFT JOIN recorded_print_jobs rpj ON pj.id = rpj.print_job_id
        $where_clause
        ORDER BY pj.timestamp DESC
        LIMIT 50
    ";

    // Récupérer tous les jobs d'impression non encore enregistrés, triés par date décroissante
    $tJobs = microtime(true);
    $jobs = $db->select($sql);
    __agentNdjson('E', 'check_print_jobs: jobs selected', [
        'elapsed_ms' => (int)((microtime(true)-$t0)*1000),
        'select_jobs_ms' => (int)((microtime(true)-$tJobs)*1000),
        'jobs_count' => is_array($jobs) ? count($jobs) : null,
        'show_history' => $show_history,
    ]);

    // Compter le total
    $tCount = microtime(true);
    $total = $db->count('print_jobs');
    __agentNdjson('E', 'check_print_jobs: total counted', [
        'elapsed_ms' => (int)((microtime(true)-$t0)*1000),
        'count_ms' => (int)((microtime(true)-$tCount)*1000),
        'total_jobs' => $total,
    ]);

    // Statistiques par imprimante
    $tStats1 = microtime(true);
    $statsByPrinter = $db->select("
        SELECT 
            printer_name,
            COUNT(*) as total_jobs,
            SUM(pages_printed) as total_pages
        FROM print_jobs 
        GROUP BY printer_name
        ORDER BY total_jobs DESC
    ");
    __agentNdjson('E', 'check_print_jobs: stats by_printer', [
        'elapsed_ms' => (int)((microtime(true)-$t0)*1000),
        'stats_printer_ms' => (int)((microtime(true)-$tStats1)*1000),
        'rows' => is_array($statsByPrinter) ? count($statsByPrinter) : null,
    ]);

    // Statistiques par statut
    $tStats2 = microtime(true);
    $statsByStatus = $db->select("
        SELECT 
            status,
            COUNT(*) as count
        FROM print_jobs 
        GROUP BY status
        ORDER BY count DESC
    ");
    __agentNdjson('E', 'check_print_jobs: stats by_status', [
        'elapsed_ms' => (int)((microtime(true)-$t0)*1000),
        'stats_status_ms' => (int)((microtime(true)-$tStats2)*1000),
        'rows' => is_array($statsByStatus) ? count($statsByStatus) : null,
    ]);

    __agentNdjson('E', 'check_print_jobs: success', ['elapsed_ms' => (int)((microtime(true)-$t0)*1000)]);
    echo json_encode([
        'success' => true,
        'total_jobs' => $total,
        'jobs' => $jobs,
        'stats' => [
            'by_printer' => $statsByPrinter,
            'by_status' => $statsByStatus
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Nettoyer le buffer avant d'envoyer l'erreur
    ob_end_clean();
    __agentNdjson('E', 'check_print_jobs: exception', [
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    // Nettoyer le buffer avant d'envoyer l'erreur
    ob_end_clean();
    __agentNdjson('E', 'check_print_jobs: fatal', [
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
