<?php
/**
 * Vérifie si Ghostscript fonctionne correctement
 * Supporte Windows et Linux (x64/ARM64)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../controler/functions/binary_utilities.php';

$result = [
    'available' => false,
    'error' => null,
    'error_code' => null
];

$gs_path = get_ghostscript_path();

if (!$gs_path || (PHP_OS_FAMILY === 'Windows' && strpos($gs_path, 'gs') === 0 && !file_exists($gs_path))) {
    $result['error'] = 'Ghostscript non trouvé';
    $result['error_code'] = 'NOT_FOUND';
    echo json_encode($result);
    exit;
}

// Tester si Ghostscript peut s'exécuter
$gs_res = run_ghostscript("-v");

if ($gs_res['success']) {
    $result['available'] = true;
    $result['path'] = $gs_path;
    $result['version'] = $gs_res['output'];
} else {
    // Sur Windows, code spécifique pour DLL manquante
    if (PHP_OS_FAMILY === 'Windows' && strpos($gs_res['output'], 'DLL') !== false) {
        $result['error'] = 'Ghostscript ne peut pas s\'exécuter. DLL manquante (probablement Visual C++ Redistributable).';
        $result['error_code'] = 'DLL_NOT_FOUND';
    } else {
        $result['error'] = 'Ghostscript ne peut pas s\'exécuter : ' . $gs_res['error'];
        $result['error_code'] = 'EXECUTION_FAILED';
    }
    $result['output'] = $gs_res['output'];
}

echo json_encode($result);
?>



