<?php
/**
 * Utilitaires pour la détection et l'exécution des binaires système
 */

/**
 * Retourne le dossier des binaires selon l'OS et l'architecture
 * 
 * @return string Nom du dossier (ex: linux-arm64, win-x64)
 */
function get_binary_platform_dir(): string
{
    $os = strtolower(PHP_OS_FAMILY);
    if ($os === 'windows') {
        return 'win-x64';
    }
    
    // Pour Linux et macOS, on vérifie l'architecture
    $arch = php_uname('m');
    if ($arch === 'aarch64' || $arch === 'arm64') {
        return $os . '-arm64';
    }
    
    return $os . '-x64';
}

/**
 * Retourne le chemin vers un binaire avec fallback système
 * 
 * @param string $name Nom du binaire (ex: gs, magick, gpcl6, gxps)
 * @param string|null $env_var Nom de la variable d'environnement optionnelle
 * @return string|null Chemin vers le binaire ou le nom de la commande système
 */
function get_binary_path(string $name, ?string $env_var = null): ?string
{
    // 1. Vérifier la variable d'environnement (priorité haute)
    if ($env_var) {
        $env_path = getenv($env_var);
        if ($env_path && (file_exists($env_path) || trim(shell_exec("which " . escapeshellarg($env_path) . " 2>/dev/null")))) {
            return $env_path;
        }
    }

    $is_windows = (PHP_OS_FAMILY === 'Windows');
    $ext = $is_windows ? '.exe' : '';
    $platform = get_binary_platform_dir();

    // 2. Vérifier dans les dossiers de l'application (bin/)
    $local_path = realpath(__DIR__ . "/../../../bin/$platform/$name$ext");
    if ($local_path && file_exists($local_path) && ($is_windows || is_executable($local_path))) {
        return $local_path;
    }

    // 3. Fallback vers les anciens dossiers (compatibilité initiale)
    $legacy_map = [
        'gs' => "/../../../ghostscript/gswin64c$ext",
        'gpcl6' => "/../../../ghostscript/gpcl6win64$ext",
        'gxps' => "/../../../ghostscript/gxpswin64$ext",
        'magick' => "/../../../imagemagick/magick$ext"
    ];

    if (isset($legacy_map[$name])) {
        $legacy_path = realpath(__DIR__ . $legacy_map[$name]);
        if ($legacy_path && file_exists($legacy_path)) {
            // Sur Linux ARM64, on vérifie que ce n'est pas un binaire x64 par erreur
            if (!$is_windows && strpos($platform, 'arm64') !== false) {
                $file_info = shell_exec("file " . escapeshellarg($legacy_path));
                if (strpos($file_info, 'x86-64') !== false) {
                    // C'est un binaire x64 sur un système ARM64 -> on ignore pour forcer le fallback système
                    return $name; 
                }
            }
            return $legacy_path;
        }
    }

    // 4. Fallback système final
    return $name;
}

/**
 * Retourne le chemin vers l'exécutable Ghostscript
 * 
 * @return string Chemin vers gs
 */
function get_ghostscript_path(): string
{
    return get_binary_path('gs', 'DUPLICATOR_GS_PATH') ?: 'gs';
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
    
    // Sur Windows, si on utilise le binaire système et qu'il n'est pas dans le PATH, 
    // l'exécution échouera. Mais run_endpoint l'utilise déjà.
    
    $full_command = escapeshellarg($gs_path) . " " . $args . " 2>&1";
    exec($full_command, $output, $returnCode);

    return [
        'success' => ($returnCode === 0),
        'output' => implode("\n", $output),
        'error' => ($returnCode !== 0) ? "Erreur Ghostscript (code $returnCode)" : ""
    ];
}

/**
 * Log de débogage pour les APIs de conversion
 */
function debugLog(string $msg): void
{
    $logFile = realpath(__DIR__ . '/../../../logs/debug_api.log') ?: __DIR__ . '/../../../logs/debug_api.log';
    $timestamp = date('H:i:s');
    @file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND);
}
