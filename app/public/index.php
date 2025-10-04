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
if (empty($_GET)) {
    header('Location: ?accueil');
    exit;
}

// Vérifier si la page demandée existe
$allowed_pages = ['accueil', 'imposition', 'unimpose', 'admin', 'stats', 'changement', 'tirage_multimachines'];
if (!in_array($page, $allowed_pages)) {
    header('Location: ?accueil');
    exit;
}

// Inclure la configuration après avoir déterminé la page
include(__DIR__ . '/../controler/conf.php');

// Exécuter le modèle correspondant
$model_file = __DIR__ . '/../models/' . $page . '.php';
if (file_exists($model_file)) {
    $array = include($model_file);
} else {
    $array = ['errors' => ['Page non trouvée : ' . $page]];
}

// Afficher le template correspondant
$template_file = __DIR__ . '/../view/' . $page . '.html.php';
if (file_exists($template_file)) {
    echo template($template_file, $array);
} else {
    echo template(__DIR__ . '/../view/accueil.html.php', ['errors' => ['Template non trouvé pour : ' . $page]]);
}

function template($file, $array = []) {
    extract($array);
    ob_start();
    include $file;
    return ob_get_clean();
}