<?php
/**
 * Dupli Studio - Éditeur Unifié PDF & Image
 * 
 * Phase 1 : Socle (Upload, Prévisualisation, Structure)
 * Regroupe tous les outils de manipulation PDF/Image dans une interface unique.
 */
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/paths.php');

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;

    return template("../view/studio.html.php", array(
        'errors' => $errors,
        'success' => $success
    ));
}
}
