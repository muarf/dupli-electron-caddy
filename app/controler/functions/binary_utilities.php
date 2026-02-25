<?php
/**
 * Utilitaires pour la détection et l'exécution des binaires système
 */

/**
 * Retourne le chemin vers l'exécutable Ghostscript
 * 
 * @return string|null Chemin vers gs ou null si non trouvé
 */
function get_ghostscript_path(): ?string
{
    // 1. Essayer le Ghostscript système via 'which gs'
    $gs_path = trim(shell_exec('which gs 2>/dev/null'));
    if ($gs_path && is_executable($gs_path)) {
        return $gs_path;
    }

    // 2. Fallbacks spécifiques à la plateforme
    if (PHP_OS_FAMILY === 'Windows') {
        $gs_win = realpath(__DIR__ . '/../../ghostscript/gswin64c.exe');
        if ($gs_win && file_exists($gs_win)) {
            return $gs_win;
        }
    } else {
        // Sur Linux, si 'which gs' a échoué, on peut essayer des chemins courants
        $common_paths = ['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'];
        foreach ($common_paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
    }

    return null;
}

/**
 * Exécute une commande Ghostscript et gère les erreurs
 * 
 * @param string $args Arguments de la commande (sans l'exécutable)
 * @return array ['success' => bool, 'output' => string, 'error' => string]
 */
function run_ghostscript(string $args): array
{
    $gs_path = get_ghostscript_path();
    if (!$gs_path) {
        return [
            'success' => false,
            'output' => '',
            'error' => "Ghostscript n'est pas installé sur ce système. Sur Linux, installez-le avec : sudo apt-get install ghostscript"
        ];
    }

    $full_command = escapeshellarg($gs_path) . " " . $args . " 2>&1";
    exec($full_command, $output, $returnCode);

    return [
        'success' => ($returnCode === 0),
        'output' => implode("\n", $output),
        'error' => ($returnCode !== 0) ? "Erreur Ghostscript (code $returnCode)" : ""
    ];
}
