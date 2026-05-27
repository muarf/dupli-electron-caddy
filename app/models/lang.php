<?php
// Inclure le système de traduction principal
require_once __DIR__ . '/../controler/functions/i18n.php';

if (!function_exists('Action')) {
function Action($conf) {
    // Cette page ne devrait jamais être appelée directement
    // Le changement de langue est géré dans index.php
    // Rediriger vers l'accueil
    header('Location: ?accueil');
    exit;
}
}
?>

