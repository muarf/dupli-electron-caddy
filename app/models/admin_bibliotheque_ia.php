<?php
/**
 * Modèle — Page Admin IA & Bibliothèque
 */
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/SettingsManager.php';

if (!function_exists('Action')) {
function Action($conf = null) {
    // Vérification session admin
    if (!isset($_SESSION['user'])) {
        return template(__DIR__ . '/../view/admin.login.html.php', []);
    }
    return template(__DIR__ . '/../view/admin.bibliotheque_ia.html.php', []);
}
}
