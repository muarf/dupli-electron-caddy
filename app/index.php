<?php

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Charger le gestionnaire d'erreurs personnalisé
require_once __DIR__ . '/controler/functions/error_handler.php';

// Définir un gestionnaire d'erreur personnalisé pour toutes les erreurs
set_error_handler(function($severity, $message, $file, $line) {
    // Ne pas traiter les erreurs supprimées par @
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Créer les données d'erreur
    $errorData = [
        'type' => 'Erreur PHP',
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'severity' => $severity,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
    ];
    
    // Afficher notre page d'erreur personnalisée élégante
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!function_exists('show_error_page')) {
        require_once __DIR__ . '/controler/functions/error_handler.php';
    }
    if (!function_exists('template')) {
        require_once __DIR__ . '/controler/func.php';
    }
    
    // Mapper les types d'erreurs
    $error_types = [
        E_ERROR => 'Erreur Fatale',
        E_WARNING => 'Avertissement',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'Erreur Utilisateur',
        E_USER_WARNING => 'Avertissement Utilisateur',
        E_USER_NOTICE => 'Notice Utilisateur'
    ];
    
    $error_type_name = isset($error_types[$severity]) ? $error_types[$severity] : 'Erreur';
    
    echo show_error_page($message, $error_type_name, $file, $line);
    exit;
});

// Gestionnaire d'exceptions
set_exception_handler(function($exception) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!function_exists('show_error_page')) {
        require_once __DIR__ . '/controler/functions/error_handler.php';
    }
    if (!function_exists('template')) {
        require_once __DIR__ . '/controler/func.php';
    }
    
    echo show_error_page(
        $exception->getMessage(), 
        'Exception', 
        $exception->getFile(), 
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    exit;
});

// Gestionnaire d'erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!function_exists('show_error_page')) {
            require_once __DIR__ . '/controler/functions/error_handler.php';
        }
        if (!function_exists('template')) {
            require_once __DIR__ . '/controler/func.php';
        }
        
        echo show_error_page(
            $error['message'], 
            'Erreur Fatale', 
            $error['file'], 
            $error['line']
        );
    }
});

// Fonction pour afficher notre page d'erreur personnalisée
function displayCustomErrorPage($errorData) {
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Déterminer la page actuelle
    $currentPage = key($_GET) ?? 'accueil';
    
    // Générer le header et footer
    $header = generateErrorHeader($currentPage);
    $footer = generateErrorFooter($currentPage);
    
    // Déterminer si on doit afficher les détails
    $isDebugMode = true; // Toujours afficher les détails pour le debug
    $showDetails = $isDebugMode || in_array($errorData['type'], ['Erreur fatale', 'Exception', 'Fatal Error']);
    
    // Afficher la page d'erreur
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Erreur - Duplicator</title>
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/bootstrap.min.js"></script>
        <!-- Preload des polices Font Awesome pour améliorer les performances -->
        <link rel="preload" href="fonts/fontawesome-webfont.woff2" as="font" type="font/woff2" crossorigin="anonymous">
        
        <!-- CSS Font Awesome avec font-display: swap -->
        <link href="css/font-awesome.min.css" rel="stylesheet" type="text/css">
        <link href="css/bootstrap.css" rel="stylesheet" type="text/css">
        <style>
            .error-container { margin-top: 50px; margin-bottom: 50px; }
            .error-icon { font-size: 4em; color: #d9534f; margin-bottom: 20px; }
            .error-details { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-top: 20px; }
            .error-stack { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; white-space: pre-wrap; }
            .error-actions { margin-top: 30px; }
            .error-actions .btn { margin-right: 10px; margin-bottom: 10px; }
            .error-timestamp { color: #6c757d; font-size: 0.9em; }
            .error-severity { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; background: #d9534f; color: white; }
        </style>
    </head>
    <body style="padding-bottom: 60px;">
        <?= $header ?>
        
        <div class="container error-container">
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <div class="text-center">
                        <div class="error-icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        
                        <h1>Une erreur s'est produite</h1>
                        <p class="lead">Désolé, une erreur inattendue s'est produite lors du traitement de votre demande.</p>
                        
                        <div class="error-timestamp">
                            <i class="fa fa-clock-o"></i> <?= htmlspecialchars($errorData['timestamp']) ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-danger" style="margin-top: 30px;">
                        <h3>
                            <span class="error-severity">
                                <?= htmlspecialchars($errorData['type']) ?>
                            </span>
                        </h3>
                        
                        <div style="margin-top: 15px;">
                            <strong>Message d'erreur :</strong>
                            <p style="margin-top: 10px; font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 3px;">
                                <?= htmlspecialchars($errorData['message']) ?>
                            </p>
                        </div>
                        
                        <?php if ($showDetails): ?>
                            <div class="error-details">
                                <h4><i class="fa fa-cog"></i> Détails techniques</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Fichier :</strong><br>
                                        <code><?= htmlspecialchars($errorData['file']) ?></code>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Ligne :</strong><br>
                                        <code><?= htmlspecialchars($errorData['line']) ?></code>
                                    </div>
                                </div>
                                
                                <?php if (isset($errorData['trace'])): ?>
                                    <div style="margin-top: 15px;">
                                        <strong>Stack trace :</strong>
                                        <div class="error-stack"><?= htmlspecialchars($errorData['trace']) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($errorData['url'])): ?>
                                    <div style="margin-top: 15px;">
                                        <strong>URL :</strong><br>
                                        <code><?= htmlspecialchars($errorData['url']) ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center error-actions">
                        <h4>Que souhaitez-vous faire ?</h4>
                        
                        <a href="?accueil" class="btn btn-primary btn-lg">
                            <i class="fa fa-home"></i> Retour à l'accueil
                        </a>
                        
                        <button onclick="history.back()" class="btn btn-default btn-lg">
                            <i class="fa fa-arrow-left"></i> Page précédente
                        </button>
                        
                        <button onclick="location.reload()" class="btn btn-info btn-lg">
                            <i class="fa fa-refresh"></i> Recharger la page
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <?= $footer ?>
    </body>
    </html>
    <?php
}

// Fonctions helper pour générer header et footer
function generateErrorHeader($page) {
    return '<div class="navbar navbar-default navbar-static-top">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="?accueil">
                    <span><big>Duplicator.</big></span>
                </a>
            </div>
        </div>
    </div>';
}

function generateErrorFooter($page) {
    return '<div class="navbar navbar-default navbar-fixed-bottom">
        <div class="container">
            <p class="navbar-text text-center">Codé avec ❤️ pour Duplicator</p>
        </div>
    </div>';
}

// Configuration cross-platform des chemins temporaires
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

include(__DIR__ . '/controler/func.php');
// conf.php sera inclus après l'exécution du modèle pour avoir la bonne base active


$page = key($_GET) ?? 'accueil';

// Vérifier si on accède à la racine sans paramètres
if (empty($_GET) && (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/')) {
    // Afficher la page de chargement
    include(__DIR__ . '/index.html');
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
        require_once __DIR__ . '/controler/func.php';
        require_once __DIR__ . '/models/admin/MachineManager.php';
        
        // Configuration de la base de données
        require_once __DIR__ . '/controler/conf.php';
        
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


$page_secure = array('base','accueil','devis','tirage_multimachines','changement','admin','admin_aide_machines','installation','setup','setup_save','setup_upload','stats','imposition','imposition_tracts','unimpose','png_to_pdf','pdf_to_png','riso_separator','taux_remplissage','aide_machines','error');

if(in_array($page, $page_secure,true)){
    
    // Vérifier s'il faut rediriger vers l'installation
    if ($page == 'accueil') {
        try {
            $db = pdo_connect();
            $has_machines = check_machines_exist();
            if (!$has_machines) {
                header('Location: ?installation');
                exit;
            }
        } catch (PDOException $e) {
            // Base de données non trouvée, rediriger vers l'installation
            header('Location: ?installation');
            exit;
        }
    }
    
    // Inclure la configuration APRÈS l'exécution du modèle pour avoir la bonne base active
    include(__DIR__ . '/controler/conf.php');
    
    include(__DIR__ . '/models/'.$page.'.php');
    
    // Pages spéciales qui n'utilisent pas le template standard
    if ($page == 'installation' || $page == 'setup') {
        $content = Action($conf);
        echo $content;
    } else {
        // Pages normales avec header/footer
        include(__DIR__ . '/models/header.php');
        include(__DIR__ . '/models/footer.php');
        $header = headerAction($page);
        $footer = footerAction($page);
        
        // Appeler Action() et récupérer le contenu
        error_log("=== INDEX.PHP - AVANT Action() - POST count: " . count($_POST) . " - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        $content = Action($conf);
        error_log("=== INDEX.PHP - APRÈS Action() - SUCCÈS");
        
        // Créer le tableau final en préservant les variables du modèle
        $array = array( 'header' => $header,'footer'=> $footer, 'content' => $content);
        
        // Si Action() a défini des variables dans $GLOBALS, les ajouter
        if (isset($GLOBALS['model_variables']) && is_array($GLOBALS['model_variables'])) {
            $array = array_merge($GLOBALS['model_variables'], $array);
    }
        
        echo template(__DIR__ . "/view/base.html.php", $array);
    }
} 
else {
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
            <p>Ajoutez <code>' . htmlspecialchars($page) . '</code> dans le tableau <code>$page_secure</code> du fichier :</p>
            <ul>
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
