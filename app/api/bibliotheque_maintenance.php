<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
/**
 * API pour la maintenance de la bibliothèque
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Security: En mode serveur, on autorise quand même la maintenance pour le scan profond
// (Normalement bridé à Electron, mais activé ici pour ton usage sur VPS)
/*
if (!isElectron()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Action interdite en mode serveur']);
    exit;
}
*/

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$params = $data['params'] ?? [];

$libManager = new BibliothequeManager();

try {
    switch ($action) {
        case 'check_integrity':
            $results = $libManager->checkIntegrity();
            echo json_encode(['success' => true, 'results' => $results]);
            break;

        case 'clean_orphans':
            $count = $libManager->cleanOrphans();
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        case 'repair_fts':
            $result = $libManager->repairFTS();
            echo json_encode(['success' => $result !== false]);
            break;

        case 'reset_library':
            $deleteFiles = !empty($params['delete_files']);
            $result = $libManager->resetLibrary($deleteFiles);
            echo json_encode(['success' => $result]);
            break;

        case 'regenerate_thumbnails':
            // 1. Préparer (vider miniatures)
            $libManager->prepareRegenerateThumbnails();
            
            // 2. Lancer le job en arrière-plan
            $jobId = uniqid('thumb_', true);
            $job_result = startBackgroundJob('', false, $jobId, 'regenerate_thumbnails');
            
            echo json_encode([
                'success' => $job_result,
                'job_id' => $jobId,
                'message' => 'Régénération des miniatures démarrée en arrière-plan'
            ]);
            break;

        case 'rescan':
            $mode = $params['mode'] ?? 'internal';

            if ($mode !== 'internal' && !isElectron()) {
                echo json_encode(['success' => false, 'error' => 'Le scan de dossiers externes est interdit en mode serveur.']);
                break;
            }

            $allPaths = [];
            
            if ($mode === 'internal' || $mode === 'all') {
                $baseDir = getBibliothequeDir();
                $allPaths[] = $baseDir . DIRECTORY_SEPARATOR . 'files/pdf';
                $allPaths[] = $baseDir . DIRECTORY_SEPARATOR . 'files/png';
            }
            
            if ($mode === 'external' || $mode === 'all') {
                $externalDirs = $libManager->getKnownExternalDirectories();
                foreach ($externalDirs as $dir) {
                    if (is_dir($dir)) $allPaths[] = $dir;
                }
            }
            
            if (empty($allPaths)) {
                echo json_encode(['success' => false, 'error' => 'Aucun dossier à rescanner']);
                break;
            }
            
            $pathStr = implode('|', $allPaths);
            $jobId = uniqid('rescan_', true);
            $job_result = startBackgroundJob($pathStr, true, $jobId, 'index');
            
            echo json_encode([
                'success' => $job_result,
                'job_id' => $jobId,
                'message' => 'Rescan démarré en arrière-plan'
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Helper pour lancer le job en arrière-plan (copié/adapté de start_indexing.php)
 */
function startBackgroundJob($path, $recursive, $jobId, $mode) {
    $phpPath = 'php';
    if (defined('PHP_BINARY') && !empty(PHP_BINARY) && PHP_BINARY !== 'php' && file_exists(PHP_BINARY)) {
        $phpPath = PHP_BINARY;
    } else if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $candidates = [
            __DIR__ . '/../../src-tauri/binaries/php-x86_64-pc-windows-msvc.exe',
            dirname(__DIR__, 2) . '/binaries/php-x86_64-pc-windows-msvc.exe'
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && file_exists($real)) {
                $phpPath = $real;
                break;
            }
        }
    }

    $scriptPath = realpath(__DIR__ . '/../maintenance/background_indexer.php');
    if (!$scriptPath) return false;

    $cmdArgs = [
        $phpPath,
        $scriptPath,
        $path,
        $recursive ? '1' : '0',
        $jobId,
        $mode
    ];

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'start /B "" ' . implode(' ', array_map('escapeshellarg', $cmdArgs));
        pclose(popen($cmd, 'r'));
    } else {
        $cmd = implode(' ', array_map('escapeshellarg', $cmdArgs)) . ' > /dev/null 2>&1 &';
        exec($cmd);
    }

    // Status initial
    $logDir = __DIR__ . '/../logs/indexing_status';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    file_put_contents($logDir . '/' . $jobId . '.json', json_encode([
        'job_id' => $jobId,
        'status' => 'starting',
        'percent' => 0,
        'updated_at' => time()
    ]));

    return true;
}
