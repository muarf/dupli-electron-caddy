<?php
// Modèle pour la page auto_tirage
// Pas de logique complexe ici, tout est géré en JS sur la vue

function Action($conf)
{
    ob_start();
    include(__DIR__ . '/../view/auto_tirage.html.php');
    $content = ob_get_clean();
    return $content;
}
?>