<?php
/**
 * Gestionnaire d'erreurs élégant avec page d'erreur jolie
 */

/**
 * Afficher une page d'erreur élégante
 */
function show_error_page($error_message, $error_type = 'Erreur', $error_file = null, $error_line = null, $error_trace = null, $error_help = null) {
    // Préparer les variables pour la vue
    $error_title = $error_type;
    
    // Inclure le header si pas déjà fait
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    // Nettoyer tous les buffers de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Démarrer un nouveau buffer
    ob_start();
    
    // Charger le header
    if (file_exists(__DIR__ . '/../../view/header.html.php')) {
        include __DIR__ . '/../../view/header.html.php';
    }
    
    // Charger la page d'erreur
    include __DIR__ . '/../../view/error.html.php';
    
    // Charger le footer
    if (file_exists(__DIR__ . '/../../view/footer.html.php')) {
        include __DIR__ . '/../../view/footer.html.php';
    }
    
    $output = ob_get_clean();
    echo $output;
    return $output;
}

/**
 * Gestionnaire d'erreurs PHP personnalisé
 */
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    // Déterminer le type d'erreur
    $error_types = [
        E_ERROR => 'Erreur Fatale',
        E_WARNING => 'Avertissement',
        E_PARSE => 'Erreur de Syntaxe',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Erreur du Core PHP',
        E_CORE_WARNING => 'Avertissement du Core PHP',
        E_COMPILE_ERROR => 'Erreur de Compilation',
        E_COMPILE_WARNING => 'Avertissement de Compilation',
        E_USER_ERROR => 'Erreur Utilisateur',
        E_USER_WARNING => 'Avertissement Utilisateur',
        E_USER_NOTICE => 'Notice Utilisateur',
        E_STRICT => 'Erreur Strict',
        E_RECOVERABLE_ERROR => 'Erreur Récupérable',
        E_DEPRECATED => 'Obsolète',
        E_USER_DEPRECATED => 'Obsolète (Utilisateur)'
    ];
    
    $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Erreur';
    
    // Pour les erreurs non fatales, logger et continuer
    if ($errno !== E_ERROR && $errno !== E_USER_ERROR && $errno !== E_RECOVERABLE_ERROR) {
        error_log("[$error_type] $errstr in $errfile:$errline");
        return false; // Laisser le gestionnaire d'erreurs par défaut gérer
    }
    
    // Obtenir la trace
    $trace = debug_backtrace();
    $trace_string = '';
    foreach ($trace as $i => $t) {
        $trace_string .= "#$i ";
        $trace_string .= isset($t['file']) ? $t['file'] : 'unknown';
        $trace_string .= isset($t['line']) ? '(' . $t['line'] . ')' : '';
        $trace_string .= ': ';
        $trace_string .= isset($t['function']) ? $t['function'] . '()' : '';
        $trace_string .= "\n";
    }
    
    // Afficher la page d'erreur
    echo show_error_page($errstr, $error_type, $errfile, $errline, $trace_string);
    exit;
}

/**
 * Gestionnaire d'exceptions personnalisé
 */
function custom_exception_handler($exception) {
    $error_message = $exception->getMessage();
    $error_file = $exception->getFile();
    $error_line = $exception->getLine();
    $error_trace = $exception->getTraceAsString();
    
    echo show_error_page($error_message, 'Exception', $error_file, $error_line, $error_trace);
    exit;
}

/**
 * Activer le gestionnaire d'erreurs personnalisé
 */
function enable_custom_error_handler() {
    set_error_handler('custom_error_handler');
    set_exception_handler('custom_exception_handler');
}

