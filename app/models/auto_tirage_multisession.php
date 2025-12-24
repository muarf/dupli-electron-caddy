<?php
/**
 * Model pour auto_tirage_multisession
 */

function Action($conf) {
    ob_start();
    include(__DIR__ . '/../view/auto_tirage_multisession.html.php');
    return ob_get_clean();
}
