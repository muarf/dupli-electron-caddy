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
    $is_windows = PHP_OS_FAMILY === 'Windows';
    // 1. Vérifier la variable d'environnement (priorité haute)
    if ($env_var) {
        $env_path = getenv($env_var);
        if ($env_path && (file_exists($env_path) || ($path = shell_exec("which " . escapeshellarg($env_path) . " 2>/dev/null")) !== null && trim($path) !== "")) {
            return $env_path;
        }
    }

    // 2. Vérifier dans les dossiers de l'application (bin/)
    $platform = get_binary_platform_dir();
    $ext = (PHP_OS_FAMILY === 'Windows') ? '.exe' : '';
    
    // On tente plusieurs profondeurs car la structure peut varier selon le mode (dev vs packagé)
    $search_paths = [
        __DIR__ . "/../../../bin/$platform/$name$ext", // Mode dev: app/controler/functions/../../../bin/
        __DIR__ . "/../../bin/$platform/$name$ext",    // Cas où app/ est la racine
        dirname(__DIR__, 3) . "/bin/$platform/$name$ext" // Chemin absolu calculé
    ];

    foreach ($search_paths as $path) {
        if (file_exists($path)) {
            return realpath($path) ?: $path;
        }
    }

    // Cas spécial pour ImageMagick : fallback magick <-> convert
    if ($name === 'magick' || $name === 'convert') {
        $alt_name = ($name === 'magick' ? 'convert' : 'magick');
        $alt_local_path = realpath(__DIR__ . "/../../../bin/$platform/$alt_name$ext");
        if ($alt_local_path && file_exists($alt_local_path) && ($is_windows || is_executable($alt_local_path))) {
            return $alt_local_path;
        }
    }

    // 3. Fallback vers les anciens dossiers (compatibilité initiale)
    $legacy_map = [
        'magick' => "/../../../bin/$platform/magick$ext"
    ];

    if (isset($legacy_map[$name])) {
        $legacy_path = realpath(__DIR__ . $legacy_map[$name]);
        if ($legacy_path && file_exists($legacy_path)) {
            // Sur Linux ARM64, on vérifie que ce n'est pas un binaire x64 par erreur
            if (!$is_windows && strpos($platform, 'arm64') !== false) {
                $file_info = @shell_exec("file " . escapeshellarg($legacy_path));
                if ($file_info && strpos($file_info, 'x86-64') !== false) {
                    // C'est un binaire x64 sur un système ARM64 -> on ignore pour forcer le fallback système
                    return $name; 
                }
            }
            return $legacy_path;
        }
    }

    if (!$is_windows) {
        $sys_path = ($path = shell_exec("which " . escapeshellarg($name) . " 2>/dev/null")) !== null ? trim($path) : "";
        if ($sys_path) return $sys_path;

        // Fallback spécial linux magick/convert/identify
        if ($name === 'magick') {
            $convert_path = ($path = shell_exec("which convert 2>/dev/null")) !== null ? trim($path) : "";
            if ($convert_path) return $convert_path;
        } elseif ($name === 'convert') {
            $magick_path = ($path = shell_exec("which magick 2>/dev/null")) !== null ? trim($path) : "";
            if ($magick_path) return $magick_path;
        } elseif ($name === 'identify') {
            $magick_path = ($path = shell_exec("which magick 2>/dev/null")) !== null ? trim($path) : "";
            if ($magick_path) return $magick_path . " identify";
        }
    }

    return $name;
}

/**
 * Exécute un binaire système de manière sécurisée
 * 
 * @param string $name Nom du binaire
 * @param string $args Arguments de la commande
 * @param string|null $env_var Variable d'environnement pour le chemin
 * @return array ['success' => bool, 'output' => string, 'error' => string]
 */
function run_binary(string $name, string $args, ?string $env_var = null): array
{
    $path = get_binary_path($name, $env_var) ?: $name;
    $full_command = escapeshellarg($path) . " " . $args . " 2>&1";
    
    exec($full_command, $output, $returnCode);

    return [
        'success' => ($returnCode === 0),
        'output' => implode("\n", $output),
        'error' => ($returnCode !== 0) ? "Erreur $name (code $returnCode)" : "",
        'command' => $full_command
    ];
}

/**
 * Retourne le chemin vers l'exécutable Ghostscript
 * 
 * @return string Chemin vers gs
 */
function get_ghostscript_path(): string
{
    $gs = get_binary_path('gs', 'DUPLICATOR_GS_PATH');
    
    // Sur Windows, si 'gs' n'est pas trouvé dans bin/, chercher dans le dossier ghostscript/ local (spécifique Alpha/Beta packagé)
    if (PHP_OS_FAMILY === 'Windows' && $gs === 'gs') {
        $local_gs_paths = [
            __DIR__ . "/../../../ghostscript/gswin64c.exe", // Mode dev / racine dépôt
            __DIR__ . "/../../ghostscript/gswin64c.exe"    // Mode packagé
        ];
        foreach ($local_gs_paths as $path) {
            if (file_exists($path)) {
                return realpath($path) ?: $path;
            }
        }
        
        $gswin64c = get_binary_path('gswin64c');
        if ($gswin64c !== 'gswin64c') {
            return $gswin64c;
        }
    }
    
    return $gs ?: 'gs';
}

/**
 * Retourne le chemin vers l'exécutable ImageMagick
 * 
 * @return string
 */
function get_magick_path(): string
{
    return get_binary_path('magick') ?: 'magick';
}

/**
 * Retourne le chemin vers l'exécutable GhostPCL
 * 
 * @return string
 */
function get_gpcl6_path(): string
{
    return get_binary_path('gpcl6') ?: 'gpcl6';
}

/**
 * Retourne le chemin vers l'exécutable GhostXPS
 * 
 * @return string
 */
function get_gxps_path(): string
{
    return get_binary_path('gxps') ?: 'gxps';
}

/**
 * Exécute une commande Ghostscript et gère les erreurs
 * 
 * @param string $args Arguments de la commande (sans l'exécutable)
 * @return array ['success' => bool, 'output' => string, 'error' => string]
 */
function run_ghostscript(string $args): array
{
    $path = get_ghostscript_path();
    return run_binary($path, $args, 'DUPLICATOR_GS_PATH');
}

/**
 * Exécute une commande ImageMagick (magick)
 * 
 * @param string $args Arguments
 * @return array
 */
function run_imagemagick(string $args): array
{
    return run_binary('magick', $args, 'DUPLICATOR_MAGICK_PATH');
}

/**
 * Exécute une commande PCL (gpcl6)
 * 
 * @param string $args Arguments
 * @return array
 */
function run_gpcl6(string $args): array
{
    return run_binary('gpcl6', $args, 'DUPLICATOR_GPCL_PATH');
}

/**
 * Exécute une commande XPS (gxps)
 * 
 * @param string $args Arguments
 * @return array
 */
function run_gxps(string $args): array
{
    return run_binary('gxps', $args, 'DUPLICATOR_GXPS_PATH');
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

/**
 * Version de escapeshellarg qui préserve le caractère '%' sous Windows.
 * Utile pour les patterns d'output Ghostscript (ex: %d, %03d).
 */
function escape_shell_arg_with_percent(string $arg): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Entourer de guillemets doubles et doubler les guillemets doubles internes
        return '"' . str_replace('"', '""', $arg) . '"';
    }
    return escapeshellarg($arg);
}

/**
 * Retourne le chemin vers l'interpréteur Python.
 *
 * - Windows : utilise le Python embeddable déposé dans bin/win-x64/python/python.exe.
 *             Fallback : 'python' dans le PATH (mode développement).
 * - Linux/macOS : utilise le venv local app/api/venv_fonts si disponible,
 *                 sinon python3 du système.
 *
 * @return string Chemin absolu ou nom de commande système
 */
function get_python_path(): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $local_paths = [
            __DIR__ . '/../../../bin/win-x64/python/python.exe',
            __DIR__ . '/../../bin/win-x64/python/python.exe',
        ];
        foreach ($local_paths as $path) {
            $real = realpath($path);
            if ($real && file_exists($real)) {
                return $real;
            }
        }
        // Mode dev Windows : Python dans le PATH
        return 'python';
    }

    // Linux/macOS : privilégier le venv local (contient pdf2docx, python-docx…)
    $venv_paths = [
        __DIR__ . '/../../api/venv_fonts/bin/python',
        __DIR__ . '/../../../app/api/venv_fonts/bin/python',
    ];
    foreach ($venv_paths as $path) {
        $real = realpath($path);
        if ($real && file_exists($real) && is_executable($real)) {
            return $real;
        }
    }

    $sys = trim((string)(shell_exec('which python3 2>/dev/null') ?? ''));
    return $sys ?: 'python3';
}

/**
 * Retourne le chemin vers l'exécutable Tesseract.
 * Sur Windows, cherche bin/win-x64/tesseract.exe via get_binary_path().
 *
 * @return string
 */
function get_tesseract_path(): string
{
    return get_binary_path('tesseract', 'DUPLICATOR_TESSERACT_PATH') ?: 'tesseract';
}

/**
 * Retourne le chemin vers l'exécutable pdftotext (poppler).
 * Sur Windows, cherche bin/win-x64/pdftotext.exe via get_binary_path().
 *
 * @return string
 */
function get_pdftotext_path(): string
{
    return get_binary_path('pdftotext', 'DUPLICATOR_PDFTOTEXT_PATH') ?: 'pdftotext';
}

/**
 * Retourne le chemin vers l'exécutable ExifTool.
 * Sur Windows, cherche bin/win-x64/exiftool.exe via get_binary_path().
 *
 * @return string
 */
function get_exiftool_path(): string
{
    return get_binary_path('exiftool', 'DUPLICATOR_EXIFTOOL_PATH') ?: 'exiftool';
}
