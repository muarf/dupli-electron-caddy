<?php
// Test simple pour déclencher une erreur et voir notre page d'erreur
require_once __DIR__ . '/controler/error_handler.php';

// Initialiser le gestionnaire d'erreur
$errorHandler = ErrorHandler::getInstance();
$errorHandler->initialize();

// Provoquer une erreur fatale pour tester
call_user_func('function_qui_n_existe_pas');
?>



