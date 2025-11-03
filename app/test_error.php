<?php
require_once __DIR__ . '/controler/functions/error_handler.php';
require_once __DIR__ . '/controler/func.php';
session_start();

echo show_error_page(
    "Ceci est un test d'erreur pour vérifier que la page s'affiche correctement",
    "Test d'erreur",
    "/root/dupli-php-dev/test_error.php",
    10,
    "Ligne 1: Test\nLigne 2: Trace\nLigne 3: Debug",
    "Ceci est une suggestion pour résoudre le problème"
);

