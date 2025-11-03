<?php
// Test simple pour voir si notre gestionnaire d'erreur fonctionne
require_once __DIR__ . '/index.php';

// Provoquer une erreur simple
trigger_error("Test d'erreur", E_USER_ERROR);
?>



