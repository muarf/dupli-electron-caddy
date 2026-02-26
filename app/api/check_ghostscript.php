<?php
/**
 * Vérifie si Ghostscript fonctionne correctement
 * Appelé au démarrage de l'application sous Windows
 */

header('Content-Type: application/json; charset=utf-8');

$result = [
    'available' => false,
    'error' => null,
    'error_code' => null
];

if (PHP_OS_FAMILY !== 'Windows') {
    $gs_path = get_ghostscript_path();
    if ($gs_path) {
        $result['available'] = true;
        $result['path'] = $gs_path;
        $result['version'] = trim(shell_exec(escapeshellarg($gs_path) . " -v 2>&1"));
    } else {
        $result['available'] = false;
        $result['error'] = "Ghostscript n'est pas installé sur ce système Linux.";
        $result['error_code'] = 'NOT_FOUND';
    }
    echo json_encode($result);
    exit;
}

// Sous Windows, tester gswin64c.exe
$gs_command = __DIR__ . '/../ghostscript/gswin64c.exe';

if (!file_exists($gs_command)) {
    $result['error'] = 'Ghostscript non trouvé';
    $result['error_code'] = 'NOT_FOUND';
    echo json_encode($result);
    exit;
}

// Tester si Ghostscript peut s'exécuter (commande -v)
$command = escapeshellarg($gs_command) . " -v 2>&1";
$output = [];
$returnVar = 0;
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    $result['available'] = true;
    $result['version'] = implode("\n", $output);
} else {
    // Code d'erreur -1073741515 = STATUS_DLL_NOT_FOUND (DLL manquante)
    $result['error'] = 'Ghostscript ne peut pas s\'exécuter. DLL manquante (probablement Visual C++ Redistributable).';
    $result['error_code'] = $returnVar === -1073741515 ? 'DLL_NOT_FOUND' : 'EXECUTION_FAILED';
    $result['return_code'] = $returnVar;
    $result['output'] = implode("\n", $output);
}

echo json_encode($result);



