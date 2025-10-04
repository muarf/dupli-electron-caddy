<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure func.php AVANT les gestionnaires d'erreur pour avoir accès à template()
include(__DIR__ . '/../controler/func.php');

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
        } elseif ($currentPage === 'admin') {
            return template(__DIR__ . "/../view/admin.login.html.php", $errorArray);
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
    } elseif ($currentPage === 'admin') {
        return template(__DIR__ . "/../view/admin.login.html.php", $errorArray);
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


$page_secure = array('base','accueil','devis','tirage_multimachines','changement','admin','installation','setup','setup_save','stats','imposition','unimpose');

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
     header("Status", true, 403);
} 

?>
