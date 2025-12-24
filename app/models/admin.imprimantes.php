function Action($conf = null)
{
    ob_start();
    include(__DIR__ . '/../view/admin_imprimantes.html.php');
    $content = ob_get_clean();
    return $content;
}

