<?php
// Test pour reproduire l'erreur EditManager
require_once __DIR__ . '/controler/error_handler.php';

// Initialiser le gestionnaire d'erreur
$errorHandler = ErrorHandler::getInstance();
$errorHandler->initialize();

// Simuler l'erreur EditManager
require_once __DIR__ . '/models/admin/EditManager.php';

// Créer une instance avec une config vide pour reproduire l'erreur
$conf = [];
$editManager = new EditManager($conf);

// Essayer de supprimer un tirage - cela va provoquer l'erreur
$editManager->deleteTirage(1101, 'comcolor');
?>



