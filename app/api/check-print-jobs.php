<?php
/**
 * Script pour vérifier les enregistrements d'impression dans la base de données
 */

// Désactiver l'affichage des erreurs pour éviter de polluer le JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Définir les headers AVANT toute sortie
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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


            // Nettoyer le buffer
            ob_end_clean();
            $db = create_database_manager(); // Assurez-vous que cette fonction existe et retourne une instance PDO ou wrapper

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
                        if (!empty($job['job_id']) && !empty($job['printer_name'])) {
                            $jobId = intval($job['job_id']);
                            $printer = $job['printer_name'];

                            // Échapper le nom de l'imprimante pour PowerShell
                            // On remplace ' par '' pour l'échappement PowerShell
                            $escapedPrinter = str_replace("'", "''", $printer);

                            // 1. D'ABORD : Commande PowerShell robuste pour supprimer le job de la file Windows
                            // On itère sur toutes les imprimantes car le nom peut différer entre la DB et Windows
                            $cmd = "powershell.exe -Command \"Get-Printer | ForEach-Object { Remove-PrintJob -PrinterName \$_.Name -ID $jobId -ErrorAction SilentlyContinue }\"";

                            error_log("[API] Tentative d'annulation robuste du job Windows ID $jobId");

                            // Exécuter silencieusement
                            exec($cmd . " 2>&1", $output, $returnVar);

                            if ($returnVar !== 0) {
                                error_log("[API] Échec annulation job $jobId (ou job déjà supprimé)");
                            } else {
                                error_log("[API] Commande d'annulation job $jobId envoyée");
                            }

                            // 2. ENSUITE : Nettoyage manuel des fichiers de spool (SPL/SHD)
                            // Nécessaire car KeepPrintedJobs=true empêche Windows de les supprimer lui-même
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

                // ----------------------------------------------------------------------------
                // --- NOUVEAU: Suppression SÉCURISÉE des fichiers (Shredding) ---
                $jobsToDelete = $db->select("SELECT document, thumbnail_url FROM print_jobs WHERE id IN ($ids_string)");
                
                // Fonction locale pour résoudre le chemin (similaire à secure_purge.php)
                // Idéalement à mettre dans utilities.php mais on le fait inline pour éviter de toucher trop de fichiers pour l'instant
                $resolvePath = function($urlOrPath) {
                    if (empty($urlOrPath)) return null;
                    if (preg_match('/^[a-zA-Z]:\\\\/', $urlOrPath)) return $urlOrPath;
                    $relativePath = parse_url($urlOrPath, PHP_URL_PATH);
                    $relativePath = ltrim($relativePath, '/'); 
                    $baseDir = dirname(__DIR__) . '/../public/'; 
                    return str_replace('/', DIRECTORY_SEPARATOR, $baseDir . $relativePath);
                };

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
    require_once(__DIR__ . '/../controler/conf.php');
    require_once(__DIR__ . '/../controler/functions/database.php');
    require_once(__DIR__ . '/../controler/functions/utilities.php');

    // Nettoyer le buffer de sortie
    ob_end_clean();
    $db = create_database_manager();

    // Vérifier si la table existe
    if (!$db->tableExists('print_jobs')) {
        echo json_encode([
            'success' => false,
            'error' => 'La table print_jobs n\'existe pas encore',
            'message' => 'Aucune impression n\'a été détectée pour le moment'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Gestion du filtre d'historique
    $show_history = isset($_GET['history']) && $_GET['history'] === 'true';
    $where_clause = $show_history ? "" : "WHERE rpj.job_id IS NULL";

    $sql = "
        SELECT
            pj.id,
            pj.job_id,
            pj.document,
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
            (CASE WHEN rpj.job_id IS NOT NULL THEN 1 ELSE 0 END) as is_recorded
        FROM print_jobs pj
        LEFT JOIN recorded_print_jobs rpj ON pj.job_id = rpj.job_id AND pj.printer_name = rpj.printer_name
        $where_clause
        ORDER BY pj.timestamp DESC
        LIMIT 50
    ";

    // Récupérer tous les jobs d'impression non encore enregistrés, triés par date décroissante
    $jobs = $db->select($sql);

    // Compter le total
    $total = $db->count('print_jobs');

    // Statistiques par imprimante
    $statsByPrinter = $db->select("
        SELECT 
            printer_name,
            COUNT(*) as total_jobs,
            SUM(pages_printed) as total_pages
        FROM print_jobs 
        GROUP BY printer_name
        ORDER BY total_jobs DESC
    ");

    // Statistiques par statut
    $statsByStatus = $db->select("
        SELECT 
            status,
            COUNT(*) as count
        FROM print_jobs 
        GROUP BY status
        ORDER BY count DESC
    ");

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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    // Nettoyer le buffer avant d'envoyer l'erreur
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
