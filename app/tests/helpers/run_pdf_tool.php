<?php

if ($argc < 2) {
    fwrite(STDERR, "Payload manquant\n");
    exit(1);
}

$json = $argv[1];
if (file_exists($json)) {
    $json = file_get_contents($json);
}
$payload = json_decode($json, true);

if (!is_array($payload)) {
    fwrite(STDERR, "Payload invalide\n");
    exit(1);
}

$module = $payload['module'] ?? null;
$function = $payload['function'] ?? null;
$args = $payload['args'] ?? [];

if (!$module || !$function) {
    fwrite(STDERR, "Module ou fonction manquant\n");
    exit(1);
}

if (!empty($payload['env']) && is_array($payload['env'])) {
    foreach ($payload['env'] as $key => $value) {
        putenv($key . '=' . $value);
        $_SERVER[$key] = $value;
    }
}

if (!empty($payload['conf']) && is_array($payload['conf'])) {
    $GLOBALS['conf'] = $payload['conf'];
}

$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false) {
    fwrite(STDERR, "Impossible de déterminer la racine du projet\n");
    exit(1);
}
chdir($projectRoot . '/controler');

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/controler/functions/paths.php';
require_once $projectRoot . '/controler/functions/i18n.php';
require_once $projectRoot . '/controler/functions/utilities.php';
require_once $projectRoot . '/controler/functions/database.php';
require_once $projectRoot . '/controler/functions/binary_utilities.php';

try {
    switch ($module) {
        case 'imposition':
            require_once $projectRoot . '/models/imposition.php';
            require_once $projectRoot . '/models/imposition_livre.php';
            require_once $projectRoot . '/models/imposition_brochure.php';
            break;
        case 'imposition_tracts':
            require_once $projectRoot . '/models/imposition_tracts.php';
            break;
        case 'png_to_pdf':
            require_once $projectRoot . '/models/png_to_pdf.php';
            break;
        case 'pdf_to_png':
            require_once $projectRoot . '/models/pdf_to_png.php';
            break;
        case 'taux_remplissage':
            require_once $projectRoot . '/models/taux_remplissage.php';
            break;
        case 'riso_separator':
            require_once $projectRoot . '/models/riso_separator.php';
            break;
        default:
            throw new RuntimeException('Module inconnu: ' . $module);
    }

    if (!function_exists($function)) {
        throw new RuntimeException("Fonction {$function} introuvable");
    }

    $result = call_user_func_array($function, $args);

    echo json_encode([
        'status' => 'ok',
        'result' => $result,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(2);
}

