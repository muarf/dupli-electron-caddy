<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gestionnaire d'erreur global pour éviter les pages blanches
set_error_handler(function($severity, $message, $file, $line) {
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
        } elseif ($currentPage === 'unimpose') {
            return template(__DIR__ . "/../view/unimpose.html.php", $errorArray);
        } else {
            return template(__DIR__ . "/../view/accueil.html.php", $errorArray);
        }
    }
    return false;
});

// Gestionnaire d'exception global
set_exception_handler(function($exception) {
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
    } elseif ($currentPage === 'unimpose') {
        return template(__DIR__ . "/../view/unimpose.html.php", $errorArray);
    } else {
        return template(__DIR__ . "/../view/accueil.html.php", $errorArray);
    }
});

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

include(__DIR__ . '/../controler/func.php');
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


$page_secure = array('base','accueil','devis','tirage_multimachines','changement','admin','admin_aide_machines','installation','setup','setup_save','setup_upload','stats','imposition','unimpose','imposition_tracts','png_to_pdf','pdf_to_png','riso_separator','taux_remplissage','aide_machines','error');

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
    include(__DIR__ . '/../controler/conf.php');
    
    include(__DIR__ . '/../models/'.$page.'.php');
    
    // Pages spéciales qui n'utilisent pas le template standard
    if ($page == 'installation' || $page == 'setup') {
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
        
        // Créer le tableau final en préservant les variables du modèle
        $array = array( 'header' => $header,'footer'=> $footer, 'content' => $content);
        
        // Si Action() a défini des variables dans $GLOBALS, les ajouter
        if (isset($GLOBALS['model_variables']) && is_array($GLOBALS['model_variables'])) {
            $array = array_merge($GLOBALS['model_variables'], $array);
    }
        
        echo template(__DIR__ . "/../view/base.html.php", $array);
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
