<?php

// Vérifier download_backup AVANT toute autre chose pour éviter la pollution du buffer
$page_check = key($_GET) ?? '';
if ($page_check === 'download_backup') {
    // Désactiver tout affichage et compression AVANT tout
    if (ini_get('zlib.output_compression')) {
        ini_set('zlib.output_compression', 'Off');
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    ini_set('display_errors', 0);
    error_reporting(0);

    // Rediriger vers le fichier API dédié pour éviter toute pollution du buffer
    $api_file = __DIR__ . '/api/download_backup.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        die('Fichier API non trouvé');
    }
}

// Configuration normale du script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration cross-platform des chemins temporaires (avant session_start)
$temp_dir = sys_get_temp_dir();
$session_path = $temp_dir . DIRECTORY_SEPARATOR . 'duplicator_sessions';
$error_log_path = $temp_dir . DIRECTORY_SEPARATOR . 'duplicator_errors.log';

// Créer le répertoire de sessions s'il n'existe pas
if (!is_dir($session_path)) {
    mkdir($session_path, 0777, true);
}

// Configurer les chemins temporaires
session_save_path($session_path);
ini_set('error_log', $error_log_path);
ini_set('upload_tmp_dir', $temp_dir);

session_start();

// Charger les fonctions de base et i18n AVANT les gestionnaires d'erreurs
include(__DIR__ . '/../controler/func.php');
require_once __DIR__ . '/../controler/functions/i18n.php';
require_once __DIR__ . '/../controler/functions/machines.php';

// Gestionnaire d'erreur global pour éviter les pages blanches
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        $error = "Erreur PHP [$severity]: $message dans $file ligne $line";
        error_log($error);

        // Déterminer la page actuelle
        $currentPage = key($_GET) ?? 'accueil';

        // Créer un tableau d'erreur standardisé
        $errorArray = [
            'errors' => ["Erreur système : " . $message],
            'page' => $currentPage,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Rediriger vers la page d'erreur ou la page actuelle avec erreur
        if ($currentPage === 'imposition') {
            return template(__DIR__ . "/../view/imposition.html.php", $errorArray);
        } elseif ($currentPage === 'imposition_brochure') {
            return template(__DIR__ . "/../view/imposition_brochure.html.php", $errorArray);
        } elseif ($currentPage === 'imposition_livre') {
            return template(__DIR__ . "/../view/imposition_livre.html.php", $errorArray);
        } elseif ($currentPage === 'unimpose') {
            return template(__DIR__ . "/../view/unimpose.html.php", $errorArray);
        } else {
            return template(__DIR__ . "/../view/accueil.html.php", $errorArray);
        }
    }
    return false;
});

// Gestionnaire d'exception global
set_exception_handler(function ($exception) {
    $error = "Exception non capturée : " . $exception->getMessage() . " dans " . $exception->getFile() . " ligne " . $exception->getLine();
    error_log($error);

    $currentPage = key($_GET) ?? 'accueil';
    $errorArray = [
        'errors' => ["Erreur critique : " . $exception->getMessage()],
        'page' => $currentPage,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if ($currentPage === 'imposition') {
        return template(__DIR__ . "/../view/imposition.html.php", $errorArray);
    } elseif ($currentPage === 'imposition_brochure') {
        return template(__DIR__ . "/../view/imposition_brochure.html.php", $errorArray);
    } elseif ($currentPage === 'imposition_livre') {
        return template(__DIR__ . "/../view/imposition_livre.html.php", $errorArray);
    } elseif ($currentPage === 'unimpose') {
        return template(__DIR__ . "/../view/unimpose.html.php", $errorArray);
    } else {
        return template(__DIR__ . "/../view/accueil.html.php", $errorArray);
    }
});

// conf.php sera inclus après l'exécution du modèle pour avoir la bonne base active

// Gérer le changement de langue
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en', 'es', 'de'])) {
    setLanguage($_GET['lang']);

    // Si il n'y a que le paramètre lang (pas de page spécifique), rediriger vers accueil
    if (count($_GET) === 1) {
        header('Location: ?accueil&lang=' . $_GET['lang']);
        exit;
    }

    // Sinon, ne pas rediriger - continuer avec la page demandée
    // (le paramètre lang est déjà dans l'URL)
}

$page = key($_GET) ?? 'accueil';

// Vérifier si on accède à la racine sans paramètres
if (empty($_GET) && (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/')) {
    // Afficher la page de chargement
    include(__DIR__ . '/index.html');
    exit;
}

// Gestion des endpoints API (doit être avant ajax_delete_machine)
if ($page === 'check_ghostscript') {
    $api_file = __DIR__ . '/../api/check_ghostscript.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'convert_emf_to_png') {
    $api_file = __DIR__ . '/../api/convert-emf-to-png.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'convert_pcl_to_png') {
    $api_file = __DIR__ . '/../api/convert-pcl-to-png.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'convert_xps_to_png') {
    $api_file = __DIR__ . '/../api/convert-xps-to-png.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'convert_ps_to_png') {
    $api_file = __DIR__ . '/../api/convert-ps-to-png.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'ajax_edit_tambours') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/ajax_edit_tambours.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'ajax_get_tambour_prices') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/ajax_get_tambour_prices.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'download_pdf') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/download_pdf.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        die('Fichier API non trouvé');
    }
}

if ($page === 'download_png') {
    $file = $_GET['file'] ?? '';
    $dir = $_GET['dir'] ?? '';

    if (empty($file)) {
        http_response_code(400);
        die('Fichier non spécifié');
    }

    // Déterminer le répertoire temporaire selon le contexte (utiliser resolveTempDir pour la cohérence)
    $base_tmp = resolveTempDir();
    if (!empty($dir)) {
        $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_pdf_to_png' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
    } else {
        $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_pdf_to_png' . DIRECTORY_SEPARATOR;
    }

    // Sécuriser le nom de fichier
    $filename = basename($file);
    $filepath = $tmp_dir . $filename;

    // Vérifier que le fichier existe et est dans le bon répertoire
    $real_filepath = realpath($filepath);
    $real_tmp_dir = realpath($tmp_dir);
    if (!file_exists($filepath) || !$real_filepath || !$real_tmp_dir || strpos($real_filepath, $real_tmp_dir) !== 0) {
        http_response_code(404);
        die('Fichier non trouvé ou expiré');
    }

    // Vérifier l'extension (PNG ou ZIP)
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'zip'])) {
        http_response_code(400);
        die('Type de fichier non autorisé');
    }

    // Définir les headers selon le type
    if ($extension === 'png') {
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }

    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($filepath);
    exit;
}

if ($page === 'download_merged') {
    $file = $_GET['file'] ?? '';
    $session = $_GET['session'] ?? '';

    if (empty($file) || empty($session)) {
        http_response_code(400);
        die('Paramètres manquants');
    }

    $tmp_dir = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_pdf_merge' . DIRECTORY_SEPARATOR . $session . DIRECTORY_SEPARATOR;
    $filename = basename($file);
    $filepath = $tmp_dir . $filename;

    if (!file_exists($filepath) || strpos(realpath($filepath), realpath($tmp_dir)) !== 0) {
        http_response_code(404);
        die('Fichier non trouvé ou expiré');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($filepath);
    exit;
}

if ($page === 'download_resized') {
    $file = $_GET['file'] ?? '';
    $session = $_GET['session'] ?? '';

    if (empty($file) || empty($session)) {
        http_response_code(400);
        die('Paramètres manquants');
    }

    $tmp_dir = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_resizer' . DIRECTORY_SEPARATOR . $session . DIRECTORY_SEPARATOR;
    $filename = basename($file);
    $filepath = $tmp_dir . $filename;

    if (!file_exists($filepath) || strpos(realpath($filepath), realpath($tmp_dir)) !== 0) {
        http_response_code(404);
        die('Fichier non trouvé ou expiré');
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($filepath);
    exit;
}

if ($page === 'download_organized') {
    $session = $_GET['sess'] ?? $_GET['session'] ?? '';
    $file = $_GET['file'] ?? '';
    
    if (empty($session) && empty($file)) {
        http_response_code(400);
        die('Session ou fichier non spécifié');
    }
    
    $tmp_dir = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR;
    
    if (!empty($session)) {
        $session_dir = $tmp_dir . $session . DIRECTORY_SEPARATOR;
        $filepath = $session_dir . 'output.pdf';
    } else {
        $filepath = $tmp_dir . basename($file);
    }
    
    if (file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="organized.pdf"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        die('Fichier non trouvé: ' . $filepath);
    }
}

if ($page === 'organizer_thumb') {
    $file = $_GET['file'] ?? '';
    $sess = $_GET['sess'] ?? '';
    if (empty($file) || empty($sess)) {
        http_response_code(400);
        die('Paramètres manquants');
    }
    
    // Utiliser getTmpDir() pour la cohérence avec l'upload
    $tmp_dir = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $sess . DIRECTORY_SEPARATOR;
    $thumbs_dir = $tmp_dir . 'thumbs' . DIRECTORY_SEPARATOR;
    
    // Chercher dans thumbs/ puis à la racine
    $filepath = $thumbs_dir . basename($file);
    if (!file_exists($filepath)) {
        $filepath = $tmp_dir . basename($file);
    }
    
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($tmp_dir)) === 0) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        die('Vignette non trouvée: ' . $filepath);
    }
}

if ($page === 'view_pdf') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/view_pdf.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        die('Fichier API non trouvé');
    }
}

if ($page === 'get-machine-template') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/get-machine-template.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'upload_aide_pdf') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/upload_aide_pdf.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'upload_bibliotheque') {
    $api_file = __DIR__ . '/../api/upload_bibliotheque.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'search_bibliotheque') {
    $api_file = __DIR__ . '/../api/search_bibliotheque.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'preview_directory') {
    $api_file = __DIR__ . '/../api/preview_directory.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'index_file') {
    $api_file = __DIR__ . '/../api/index_file.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'delete_bibliotheque_file') {
    $api_file = __DIR__ . '/../api/delete_bibliotheque_file.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'rename_bibliotheque_file') {
    $api_file = __DIR__ . '/../api/rename_bibliotheque_file.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'start_indexing') {
    $api_file = __DIR__ . '/../api/start_indexing.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'get_indexing_status') {
    $api_file = __DIR__ . '/../api/get_indexing_status.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'bibliotheque_maintenance') {
    $api_file = __DIR__ . '/../api/bibliotheque_maintenance.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'run_background_maintenance') {
    $api_file = __DIR__ . '/../api/run_background_maintenance.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}


if ($page === 'manage_mappings' || isset($_GET['manage_mappings'])) {
    $api_file = __DIR__ . '/../api/manage_mappings.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'save_auto_print' || isset($_GET['save_auto_print'])) {
    $api_file = __DIR__ . '/../api/save_auto_print.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'get_bibliotheque_thumbnail') {
    $api_file = __DIR__ . '/../api/get_bibliotheque_thumbnail.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'chat_rag') {
    $api_file = __DIR__ . '/../api/chat_rag.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'save_ai_settings') {
    $api_file = __DIR__ . '/../api/save_ai_settings.php';
    if (file_exists($api_file)) { require_once $api_file; exit; }
    http_response_code(500); echo json_encode(['error' => 'API file not found']); exit;
}

if ($page === 'studio_process') {
    $api_file = __DIR__ . '/../api/studio_process.php';
    if (file_exists($api_file)) { require_once $api_file; exit; }
    http_response_code(500); echo json_encode(['error' => 'API studio_process non trouvée']); exit;
}

if ($page === 'download_studio') {
    $file = $_GET['file'] ?? '';
    if (empty($file)) { http_response_code(400); die('Fichier non spécifié'); }
    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
    $filename = basename($file);
    $filepath = $tmpDir . $filename;
    $realFile = realpath($filepath);
    $realDir  = realpath($tmpDir);
    if (!$realFile || !$realDir || strpos($realFile, $realDir) !== 0 || !file_exists($filepath)) {
        http_response_code(404); die('Fichier non trouvé ou expiré');
    }
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mimeMap = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filepath);
    exit;
}

if ($page === 'preview_studio') {
    $file = $_GET['file'] ?? '';
    if (empty($file)) { http_response_code(400); die('Fichier non spécifié'); }
    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
    $filename = basename($file);
    $filepath = $tmpDir . $filename;
    $realFile = realpath($filepath);
    $realDir  = realpath($tmpDir);
    if (!$realFile || !$realDir || strpos($realFile, $realDir) !== 0 || !file_exists($filepath)) {
        http_response_code(404); die('Fichier non trouvé ou expiré');
    }
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mimeMap = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'pdf' => 'application/pdf'];
    $mime = $mimeMap[$ext] ?? 'image/png';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-store');
    header('X-Frame-Options: SAMEORIGIN');
    readfile($filepath);
    exit;
}

if ($page === 'trigger_vectorization') {
    $api_file = __DIR__ . '/../api/trigger_vectorization.php';
    if (file_exists($api_file)) { require_once $api_file; exit; }
    http_response_code(500); echo json_encode(['error' => 'API file not found']); exit;
}
if ($page === 'get_bibliotheque_tags') {
    $api_file = __DIR__ . '/../api/get_bibliotheque_tags.php';
    if (file_exists($api_file)) { require_once $api_file; exit; }
    http_response_code(500); echo json_encode(['error' => 'API file not found']); exit;
}
if ($page === 'get_bibliotheque_file') {
    $api_file = __DIR__ . '/../api/get_bibliotheque_file.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API file not found']);
        exit;
    }
}

if ($page === 'bibliotheque_list') {
    $api_file = __DIR__ . '/../api/bibliotheque_list.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'API bibliotheque_list non trouvée']);
        exit;
    }
}

if ($page === 'get_bibliotheque_file_info') {
    $api_file = __DIR__ . '/../api/get_bibliotheque_file_info.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API info non trouvée']);
        exit;
    }
}

if ($page === 'update_bibliotheque_metadata') {
    $api_file = __DIR__ . '/../api/update_bibliotheque_metadata.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API update non trouvée']);
        exit;
    }
}

if ($page === 'check_print_jobs') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/check-print-jobs.php';
    error_log("[API] check_print_jobs demandé, fichier: " . $api_file);
    error_log("[API] Fichier existe: " . (file_exists($api_file) ? 'OUI' : 'NON'));
    if (file_exists($api_file)) {
        error_log("[API] Inclusion du fichier API");
        require_once $api_file;
        error_log("[API] Fichier API exécuté, exit");
        exit;
    } else {
        error_log("[API] Fichier API non trouvé!");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé', 'path' => $api_file]);
        exit;
    }
}

if ($page === 'get_pending_jobs') {
    $api_file = __DIR__ . '/../api/get_pending_jobs.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API non trouvée']);
        exit;
    }
}

if ($page === 'get_session_jobs') {
    $api_file = __DIR__ . '/../api/get_session_jobs.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API non trouvée']);
        exit;
    }
}

if ($page === 'get_session_staging_jobs') {
    $api_file = __DIR__ . '/../api/get_session_staging_jobs.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API non trouvée']);
        exit;
    }
}

if ($page === 'delete_session_job') {
    $api_file = __DIR__ . '/../api/delete_session_job.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API non trouvée']);
        exit;
    }
}

if ($page === 'sessions') {
    // API multi-session pour gestion des contacts/sessions d'impression
    $api_file = __DIR__ . '/../api/sessions.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'secure_purge') {
    $api_file = __DIR__ . '/../api/secure_purge.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'print_notification') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/print-notification.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
    }
}

if ($page === 'manage_mappings') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/manage_mappings.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'save_auto_print') {
    // Inclure le fichier API depuis le dossier api/
    $api_file = __DIR__ . '/../api/save_auto_print.php';
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fichier API non trouvé']);
        exit;
    }
}

if ($page === 'download_unimposed') {
    $file = $_GET['file'] ?? '';
    // Utiliser le répertoire temporaire système (via resolveTempDir pour la cohérence)
    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;

    // Sécuriser le nom de fichier
    $filename = basename($file);
    $filePath = $tmpDir . $filename;

    if (file_exists($filePath) && is_readable($filePath) && strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf') {
        // Désactiver la compression de sortie pour éviter les problèmes de téléchargement
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        die('Fichier non trouvé ou expiré.');
    }
}

if ($page === 'download_processed') {
    $file = $_GET['file'] ?? '';
    $dir = $_GET['dir'] ?? '';

    if (empty($file)) {
        http_response_code(400);
        die('Fichier non spécifié');
    }

    // Déterminer le répertoire temporaire selon le contexte (utiliser resolveTempDir pour la cohérence)
    $base_tmp = resolveTempDir();
    if (!empty($dir)) {
        $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
    } else {
        $tmp_dir = $base_tmp . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR;
    }

    // Sécuriser le nom de fichier
    $filename = basename($file);
    $filepath = $tmp_dir . $filename;

    // Vérifier que le fichier existe et est dans le bon répertoire
    $real_filepath = realpath($filepath);
    $real_tmp_dir = realpath($tmp_dir);
    if (!file_exists($filepath) || !$real_filepath || !$real_tmp_dir || strpos($real_filepath, $real_tmp_dir) !== 0) {
        http_response_code(404);
        die('Fichier non trouvé ou expiré');
    }

    // Déterminer le type MIME
    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mime_types = array(
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif'
    );

    $mime_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';

    // Définir les headers
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    readfile($filepath);
    exit;
}

if ($page === 'view_aide_pdf') {
    // Servir les PDFs d'aide depuis le répertoire résolu
    // Inclure les fonctions nécessaires sans vérification d'authentification
    require_once __DIR__ . '/../controler/conf.php';
    require_once __DIR__ . '/../controler/func.php';

    // Définir les fonctions de résolution de répertoire (copie depuis upload_aide_pdf.php)
    if (!function_exists('resolveAidePdfDir')) {
        function normalizePath($path)
        {
            $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
            $path = trim($path);
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        function resolveAidePdfDir()
        {
            // Priorité 1 : Variable d'environnement
            $envDir = getenv('DUPLI_AIDE_PDF_DIR');
            if (!empty($envDir)) {
                return normalizePath($envDir);
            }

            // Priorité 2 : Variable d'environnement Electron (comme pour la DB)
            $electronDbPath = getenv('DUPLICATOR_DB_PATH');
            if (!empty($electronDbPath)) {
                $dbDir = dirname($electronDbPath);
                return normalizePath($dbDir . DIRECTORY_SEPARATOR . 'aide_pdfs');
            }

            // Priorité 3 : Détection AppImage
            $current_dir = getcwd();
            if (strpos($current_dir, '.mount') !== false || strpos($current_dir, 'AppDir') !== false) {
                $home_dir = $_SERVER['HOME'] ?? getenv('HOME') ?? '/tmp';
                return normalizePath($home_dir . '/.config/Duplicator/aide_pdfs');
            }

            // Priorité 4 : Linux/Unix avec XDG_CONFIG_HOME
            if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
                $xdg = getenv('XDG_CONFIG_HOME');
                if (!empty($xdg)) {
                    return normalizePath($xdg . DIRECTORY_SEPARATOR . 'Duplicator' . DIRECTORY_SEPARATOR . 'aide_pdfs');
                }
                $home = getenv('HOME');
                if (!empty($home) && is_dir($home)) {
                    return normalizePath($home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'Duplicator' . DIRECTORY_SEPARATOR . 'aide_pdfs');
                }

                // Pour les utilisateurs système (www-data, etc.) sur Linux, utiliser /tmp ou /var/tmp
                $tmpDir = getenv('TMPDIR');
                if (empty($tmpDir)) {
                    $tmpDir = '/tmp';
                }
                if (is_dir($tmpDir) && is_writable($tmpDir)) {
                    return normalizePath($tmpDir . DIRECTORY_SEPARATOR . 'duplicator-aide-pdfs');
                }
            }

            // Dernier recours : répertoire public/uploads/aide_pdfs (pour développement/web)
            return normalizePath(__DIR__ . '/../public/uploads/aide_pdfs');
        }
    }

    $filename = $_GET['file'] ?? '';
    if (empty($filename)) {
        http_response_code(400);
        die('Nom de fichier manquant');
    }

    // Sécuriser le nom de fichier
    $filename = basename($filename);
    $filePath = resolveAidePdfDir() . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        die('Fichier non trouvé');
    }

    // Vérifier que c'est bien un PDF
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        http_response_code(403);
        die('Type de fichier non autorisé');
    }

    // Servir le fichier PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}


if ($page === 'ajax_delete_machine') {
    // Vérifier l'authentification admin
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }

    // Vérifier que c'est bien une requête POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        exit;
    }

    // Vérifier que les paramètres sont présents
    if (!isset($_POST['machine_id']) || !isset($_POST['machine_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants']);
        exit;
    }

    $machine_id = $_POST['machine_id'];
    $machine_type = $_POST['machine_type'];

    // Valider le type de machine
    if (!in_array($machine_type, ['duplicopieur', 'photocopieur'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Type de machine invalide']);
        exit;
    }

    // Valider l'ID de la machine
    if (!is_numeric($machine_id) || $machine_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de machine invalide']);
        exit;
    }

    try {
        // Inclure les fichiers nécessaires
        require_once '../controler/func.php';
        require_once '../models/admin/MachineManager.php';

        // Configuration de la base de données
        require_once '../controler/conf.php';

        // Créer l'instance du gestionnaire de machines
        $machineManager = new AdminMachineManager($conf);

        // Supprimer la machine
        $result = $machineManager->deleteMachine($machine_id, $machine_type);

        // Retourner la réponse
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur : ' . $e->getMessage()]);
        exit;
    }
}


$page_secure = array('base', 'accueil', 'devis', 'tirage_multimachines', 'changement', 'admin', 'admin_aide_machines', 'admin_translations', 'installation', 'setup', 'setup_save', 'setup_upload', 'create_password', 'stats', 'imposition', 'imposition_brochure', 'imposition_livre', 'unimpose', 'imposition_tracts', 'png_to_pdf', 'pdf_to_png', 'pdf_merge', 'resizer', 'pdf_organizer', 'riso_separator', 'image_processor', 'taux_remplissage', 'aide_machines', 'error', 'lang', 'ajax_edit_tambours', 'ajax_get_tambour_prices', 'download_pdf', 'download_png', 'download_organized', 'organizer_thumb', 'download_merged', 'download_resized', 'download_unimposed', 'download_processed', 'download_backup', 'view_pdf', 'get-machine-template', 'upload_aide_pdf', 'view_aide_pdf', 'bibliotheque', 'bibliotheque_list', 'get_bibliotheque_file_info', 'update_bibliotheque_metadata', 'upload_bibliotheque', 'search_bibliotheque', 'preview_directory', 'index_file', 'start_indexing', 'get_indexing_status', 'delete_bibliotheque_file', 'get_bibliotheque_thumbnail', 'get_bibliotheque_file', 'chat_rag', 'check_print_jobs', 'print_notification', 'auto_tirage', 'sessions', 'get_session_jobs', 'get_session_staging_jobs', 'get_pending_jobs', 'convert_emf_to_png', 'convert_pcl_to_png', 'convert_xps_to_png', 'secure_purge', 'admin_bibliotheque_ia', 'save_ai_settings', 'trigger_vectorization', 'studio', 'studio_process', 'download_studio', 'preview_studio');

error_log("[PASSWORD_CHECK] ===== DEBUT VERIFICATION =====");
error_log("[PASSWORD_CHECK] Page demandée (avant vérification): " . $page);
error_log("[PASSWORD_CHECK] Page dans page_secure: " . (in_array($page, $page_secure, true) ? 'OUI' : 'NON'));

if (in_array($page, $page_secure, true)) {

    error_log("[PASSWORD_CHECK] Page est dans page_secure, on continue");

    // Inclure la configuration APRÈS l'exécution du modèle pour avoir la bonne base active
    include(__DIR__ . '/../controler/conf.php');

    error_log("[PASSWORD_CHECK] Conf.php chargé");

    // Vérifier s'il faut rediriger vers l'installation ou la création de mot de passe
    error_log("[PASSWORD_CHECK] Page demandée: " . $page);
    error_log("[PASSWORD_CHECK] Condition entrée: " . ($page == 'accueil' || ($page != 'create_password' && $page != 'installation' && $page != 'setup' && $page != 'setup_save') ? 'OUI' : 'NON'));

    if ($page == 'accueil' || ($page != 'create_password' && $page != 'installation' && $page != 'setup' && $page != 'setup_save' && $page != 'setup_upload')) {
        try {
            $db = pdo_connect();
            error_log("[PASSWORD_CHECK] Connexion DB réussie");

            // Vérifier s'il faut rediriger vers l'installation
            if ($page == 'accueil') {
                $has_machines = check_machines_exist();
                error_log("[PASSWORD_CHECK] Machines existent: " . ($has_machines ? 'OUI' : 'NON'));
                if (!$has_machines) {
                    error_log("[PASSWORD_CHECK] Redirection vers installation (pas de machines)");
                    header('Location: ?installation');
                    exit;
                }
            }

            // Vérifier s'il existe un mot de passe admin (sauf pour les pages d'installation/setup)
            if ($page != 'installation' && $page != 'setup' && $page != 'setup_save' && $page != 'setup_upload') {
                error_log("[PASSWORD_CHECK] Vérification mot de passe admin pour page: " . $page);
                try {
                    $query = $db->prepare('SELECT COUNT(*) as count FROM admin_passwords WHERE is_active = 1');
                    $query->execute();
                    $result = $query->fetch(PDO::FETCH_ASSOC);

                    error_log("[PASSWORD_CHECK] Résultat query: " . print_r($result, true));
                    error_log("[PASSWORD_CHECK] Type de count: " . gettype($result['count'] ?? 'NULL'));
                    error_log("[PASSWORD_CHECK] Valeur count: " . ($result['count'] ?? 'NULL'));
                    error_log("[PASSWORD_CHECK] empty(result): " . (empty($result) ? 'OUI' : 'NON'));
                    error_log("[PASSWORD_CHECK] (int)count === 0: " . ((int) ($result['count'] ?? 0) === 0 ? 'OUI' : 'NON'));

                    // COUNT(*) retourne toujours une ligne, donc $result n'est jamais null
                    // Vérifier directement la valeur avec un cast explicite
                    if (empty($result) || (int) $result['count'] === 0) {
                        error_log("[PASSWORD_CHECK] Aucun mot de passe admin détecté - REDIRECTION vers create_password");
                        // Aucun mot de passe admin, rediriger vers la création
                        header('Location: ?create_password');
                        exit;
                    } else {
                        error_log("[PASSWORD_CHECK] Mot de passe admin trouvé, continuer normalement");
                    }
                } catch (PDOException $e) {
                    error_log("[PASSWORD_CHECK] Exception lors de la vérification: " . $e->getMessage());

                    // Si la table admin_passwords n'existe pas, la créer et rediriger vers create_password
                    if (
                        strpos($e->getMessage(), 'no such table: admin_passwords') !== false ||
                        strpos($e->getMessage(), 'admin_passwords') !== false
                    ) {
                        error_log("[PASSWORD_CHECK] Table admin_passwords manquante, création...");
                        try {
                            $db->exec("CREATE TABLE IF NOT EXISTS admin_passwords (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                password_hash TEXT NOT NULL,
                                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                                is_active INTEGER DEFAULT 1
                            )");
                            error_log("[PASSWORD_CHECK] Table admin_passwords créée avec succès");
                            // Rediriger vers create_password pour créer le mot de passe
                            header('Location: ?create_password');
                            exit;
                        } catch (PDOException $e2) {
                            error_log("[PASSWORD_CHECK] Erreur lors de la création de la table: " . $e2->getMessage());
                            // Si la création échoue aussi, rediriger quand même vers create_password
                            header('Location: ?create_password');
                            exit;
                        }
                    }
                    // Pour les autres erreurs, laisser passer (comportement existant)
                }
            } else {
                error_log("[PASSWORD_CHECK] Page exclue de la vérification: " . $page);
            }

            // Exécuter les migrations de base de données (après vérification password)
            // Sauf pour les pages d'installation/setup
            // Cache de session pour éviter de vérifier les migrations à chaque requête
            if ($page != 'installation' && $page != 'setup' && $page != 'setup_save' && $page != 'setup_upload' && $page != 'create_password') {
                if (!isset($_SESSION['_migrations_checked'])) {
                    try {
                        require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';
                        $migrationManager = new DatabaseMigrationManager($conf);
                        $migrationManager->runMigrations();
                        $_SESSION['_migrations_checked'] = true;
                    } catch (Exception $e) {
                        error_log("[MIGRATION] Erreur lors de l'exécution des migrations: " . $e->getMessage());
                        // Ne pas bloquer l'application si les migrations échouent
                    }
                }
            }

        } catch (PDOException $e) {
            error_log("[PASSWORD_CHECK] Exception connexion DB: " . $e->getMessage());
            // Base de données non trouvée, rediriger vers l'installation
            if ($page != 'installation' && $page != 'setup' && $page != 'setup_save' && $page != 'setup_upload' && $page != 'create_password') {
                error_log("[PASSWORD_CHECK] Redirection vers installation (DB non trouvée)");
                header('Location: ?installation');
                exit;
            }
        }
    } else {
        error_log("[PASSWORD_CHECK] Condition non remplie, pas de vérification");
    }

    include(__DIR__ . '/../models/' . $page . '.php');

    // Pages spéciales qui n'utilisent pas le template standard
    if ($page == 'installation' || $page == 'setup' || $page == 'create_password') {
        $content = Action($conf);
        echo $content;
    } else {
        // Pages normales avec header/footer
        include(__DIR__ . '/../models/header.php');
        include(__DIR__ . '/../models/footer.php');
        $header = headerAction($page);
        $footer = footerAction($page);

        // Appeler Action() et récupérer le contenu
        $content = Action($conf);

        // Le base_path est vide car la racine web est désormais toujours le dossier public/
        $base_path = '';

        // Créer le tableau final en préservant les variables du modèle
        $array = array('header' => $header, 'footer' => $footer, 'content' => $content, 'base_path' => $base_path);

        // Si Action() a défini des variables dans $GLOBALS, les ajouter
        if (isset($GLOBALS['model_variables']) && is_array($GLOBALS['model_variables'])) {
            $array = array_merge($GLOBALS['model_variables'], $array);
        }

        echo template(__DIR__ . "/../view/base.html.php", $array);
    }
} else {
    // Page non autorisée - Afficher une erreur claire
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    $error_html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Erreur 403 - Page non autorisée</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .error-box { 
            background: white; 
            border-left: 4px solid #d32f2f; 
            padding: 20px; 
            max-width: 800px; 
            margin: 0 auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #d32f2f; margin-top: 0; }
        code { 
            background: #f5f5f5; 
            padding: 2px 6px; 
            border-radius: 3px;
            color: #c7254e;
        }
        .pages-list {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            max-height: 200px;
            overflow-y: auto;
        }
        .fix-instruction {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>🚫 Erreur 403 - Page non autorisée</h1>
        <p><strong>La page demandée n\'est pas dans la liste des pages autorisées.</strong></p>
        
        <p>Page demandée : <code>' . htmlspecialchars($page) . '</code></p>
        
        <div class="fix-instruction">
            <strong>🔧 Comment corriger :</strong>
            <p>Ajoutez <code>' . htmlspecialchars($page) . '</code> dans le tableau <code>$page_secure</code> des fichiers :</p>
            <ul>
                <li><code>public/index.php</code> (ligne ~152)</li>
                <li><code>index.php</code> (ligne ~319)</li>
            </ul>
        </div>
        
        <details>
            <summary><strong>Pages actuellement autorisées :</strong></summary>
            <div class="pages-list">';

    foreach ($page_secure as $p) {
        $error_html .= '<code>' . htmlspecialchars($p) . '</code> ';
    }

    $error_html .= '</div>
        </details>
        
        <p style="margin-top: 30px;">
            <a href="?accueil" style="color: #1976d2; text-decoration: none;">← Retour à l\'accueil</a>
        </p>
    </div>
</body>
</html>';

    echo $error_html;
    exit;
}

?>