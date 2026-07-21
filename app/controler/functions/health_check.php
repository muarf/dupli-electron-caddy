<?php
/**
 * Fonctions de diagnostic et de santé du système
 */
require_once __DIR__ . '/binary_utilities.php';

/**
 * Détecte la distribution Linux
 */
function get_linux_distro_info(): string
{
    if (PHP_OS_FAMILY !== 'Linux') return 'unknown';
    
    if (file_exists('/etc/os-release')) {
        $os_release = file_get_contents('/etc/os-release');
        if (preg_match('/^ID=(.+)$/m', $os_release, $matches)) {
            $id = trim($matches[1], '"\'');
            if (in_array($id, ['ubuntu', 'debian', 'linuxmint'])) return 'debian';
            if (in_array($id, ['fedora', 'rhel', 'centos'])) return 'fedora';
            if (in_array($id, ['arch', 'manjaro'])) return 'arch';
        }
        if (preg_match('/^ID_LIKE=(.+)$/m', $os_release, $matches)) {
            $id_like = trim($matches[1], '"\'');
            if (strpos($id_like, 'debian') !== false) return 'debian';
            if (strpos($id_like, 'fedora') !== false) return 'fedora';
            if (strpos($id_like, 'arch') !== false) return 'arch';
        }
    }
    return 'unknown';
}

/**
 * Retourne le préfixe des paquets d'extension PHP selon la distro
 */
function get_php_extension_prefix(string $distro): string
{
    if ($distro === 'fedora' || $distro === 'arch' || $distro === 'unknown') {
        return 'php-';
    }
    if ($distro === 'windows') {
        return 'php_';
    }
    if ($distro === 'debian') {
        $php_ver = (PHP_VERSION_ID >= 80400) ? '8.4' : ((PHP_VERSION_ID >= 80300) ? '8.3' : '8.5'); // Fallback intelligent
        if (defined('PHP_MAJOR_VERSION') && defined('PHP_MINOR_VERSION')) {
            $php_ver = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        }
        return "php{$php_ver}-";
    }
    return 'php-';
}

/**
 * Retourne la commande d'installation pour un paquet donné
 */
function get_package_install_help(string $type, string $pkg_key, string $distro): string
{
    $commands = [
        'debian' => ['pref' => 'sudo apt-get install -y '],
        'fedora' => ['pref' => 'sudo dnf install -y '],
        'arch' => ['pref' => 'sudo pacman -S --noconfirm '],
        'windows' => ['pref' => 'Activez dans php.ini : '],
        'unknown' => ['pref' => 'sudo apt-get install -y ']
    ];

    if ($distro === 'unknown' && PHP_OS_FAMILY === 'Windows') {
        $distro = 'windows';
    }

    $d = $commands[$distro] ?? $commands['unknown'];
    
    if ($type === 'bin') {
        $bins = [
            'ghostscript' => 'ghostscript',
            'imagemagick' => 'imagemagick',
            'gpcl6' => 'ghostscript',
            'gxps' => 'libgxps-utils',
            'ocrmypdf' => 'ocrmypdf',
            'tesseract' => 'tesseract-ocr tesseract-ocr-fra',
            'jbig2' => 'jbig2'
        ];
        if ($distro === 'windows') {
            return "Téléchargez et installez " . ($bins[$pkg_key] ?? $pkg_key);
        }
        if ($distro === 'debian') {
            if ($pkg_key === 'gxps') return 'sudo apt-get install -y libgxps-utils';
            if ($pkg_key === 'gpcl6') return 'sudo apt-get install -y ghostscript (inclut gpcl6)';
            if ($pkg_key === 'ghostscript') return 'sudo apt-get install -y ghostscript';
            if ($pkg_key === 'imagemagick') return 'sudo apt-get install -y imagemagick';
            if ($pkg_key === 'ocrmypdf') return 'sudo apt-get install -y ocrmypdf';
            if ($pkg_key === 'tesseract') return 'sudo apt-get install -y tesseract-ocr tesseract-ocr-fra';
            if ($pkg_key === 'jbig2') return 'sudo apt-get install -y jbig2';
        }
        return $d['pref'] . ($bins[$pkg_key] ?? $pkg_key);
    }

    $exts = [
        'imagick' => ($distro === 'fedora' ? 'pecl-imagick' : 'imagick'),
        'gd' => 'gd',
        'sqlite3' => ($distro === 'arch' ? 'sqlite' : 'sqlite3'),
        'mbstring' => 'mbstring',
        'xml' => 'xml',
        'zip' => 'zip',
        'curl' => 'curl'
    ];

    $prefix = get_php_extension_prefix($distro);
    return $d['pref'] . $prefix . ($exts[$pkg_key] ?? $pkg_key);
}

/**
 * Retourne une commande d'installation groupée pour plusieurs paquets
 */
function get_aggregated_install_command(array $packages): string
{
    $distro = get_linux_distro_info();
    if (PHP_OS_FAMILY === 'Windows') {
        $distro = 'windows';
    }

    $commands = [
        'debian' => ['pref' => 'sudo apt-get install -y '],
        'fedora' => ['pref' => 'sudo dnf install -y '],
        'arch' => ['pref' => 'sudo pacman -S --noconfirm '],
        'windows' => ['pref' => 'Modules à activer : '],
        'unknown' => ['pref' => 'sudo apt-get install -y ']
    ];

    if (PHP_OS_FAMILY === 'Windows' && $distro === 'unknown') {
        $distro = 'windows';
    }

    $d = $commands[$distro] ?? $commands['unknown'];
    $prefix = get_php_extension_prefix($distro);
    
    $bins = [
        'ghostscript' => 'ghostscript',
        'imagemagick' => 'imagemagick',
        'gpcl6' => 'ghostscript',
        'gxps' => 'libgxps-utils',
        'ocrmypdf' => 'ocrmypdf',
        'tesseract' => 'tesseract-ocr tesseract-ocr-fra',
        'jbig2' => 'jbig2'
    ];
    
    $exts = [
        'imagick' => ($distro === 'fedora' ? 'pecl-imagick' : 'imagick'),
        'gd' => 'gd',
        'sqlite3' => ($distro === 'arch' ? 'sqlite' : 'sqlite3'),
        'mbstring' => 'mbstring',
        'xml' => 'xml',
        'curl' => 'curl'
    ];

    $resolved_pkgs = [];
    foreach ($packages as $pkg) {
        if ($pkg['type'] === 'bin') {
            $resolved_pkgs[] = $bins[$pkg['key']] ?? $pkg['key'];
        } else {
            $resolved_pkgs[] = $prefix . ($exts[$pkg['key']] ?? $pkg['key']);
        }
    }

    if ($distro === 'windows') {
        return $d['pref'] . implode(', ', array_unique($resolved_pkgs));
    }

    return $d['pref'] . implode(' ', array_unique($resolved_pkgs));
}

/**
 * Vérifie les dépendances critiques du système
 * 
 * @return array Résultats du diagnostic
 */
function check_system_dependencies(): array
{
    $distro = get_linux_distro_info();
    
    $results = [
        'critical_missing' => false,
        'dependencies' => [
            'ghostscript' => [
                'name' => 'Ghostscript',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'ghostscript', $distro)
            ],
            'gpcl6' => [
                'name' => 'GhostPCL (gpcl6)',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => false,
                'help' => 'Inclut dans ghostscript (sudo apt-get install -y ghostscript). Optionnel pour imprimantes Kyocera PCL.'
            ],
            'gxps' => [
                'name' => 'GhostXPS (gxps)',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => false,
                'help' => 'sudo apt-get install -y libgxps-utils'
            ],
            'imagemagick' => [
                'name' => 'ImageMagick',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'imagemagick', $distro)
            ],
            'ocrmypdf' => [
                'name' => 'OCRmyPDF',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'ocrmypdf', $distro)
            ],
            'tesseract' => [
                'name' => 'Tesseract OCR',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'tesseract', $distro)
            ],
            'jbig2' => [
                'name' => 'JBIG2 Encoder',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'jbig2', $distro)
            ]
        ],
        'php_extensions' => [],
        'permissions' => []
    ];

    // Détecter l'OS courant
    $is_windows = PHP_OS_FAMILY === 'Windows';
    $php_ini_path = php_ini_loaded_file() ?: 'votre fichier php.ini';

    // Si on est sous Windows, l'aide à l'installation d'extensions est spécifique
    $is_electron_windows = $is_windows && (strpos(__DIR__, 'dupli-electron') !== false || strpos(__DIR__, 'dupli-php-dev') !== false);

    $php_bin = escapeshellarg(PHP_BINARY);
    $results['php_extensions'] = [
        'gd' => [
            'name' => 'PHP GD', 
            'status' => extension_loaded('gd') || @shell_exec("$php_bin -m 2>/dev/null | grep gd"),
            'critical' => true, 
            'help' => $is_electron_windows ? 'Décommentez "extension=gd" dans ' . $php_ini_path : get_package_install_help('ext', 'gd', $distro)
        ],
        'sqlite3' => [
            'name' => 'PHP SQLite3', 
            'status' => extension_loaded('sqlite3') || @shell_exec("$php_bin -m 2>/dev/null | grep sqlite3"),
            'critical' => true, 
            'help' => $is_electron_windows ? 'Décommentez "extension=sqlite3" dans ' . $php_ini_path : get_package_install_help('ext', 'sqlite3', $distro)
        ],
        'mbstring' => ['name' => 'PHP Mbstring', 'status' => extension_loaded('mbstring') || @shell_exec("$php_bin -m 2>/dev/null | grep mbstring"), 'critical' => true, 'help' => get_package_install_help('ext', 'mbstring', $distro)],
        'xml' => ['name' => 'PHP XML', 'status' => extension_loaded('xml') || @shell_exec("$php_bin -m 2>/dev/null | grep -E \"xml|SimpleXML\""), 'critical' => true, 'help' => get_package_install_help('ext', 'xml', $distro)],
        'curl' => ['name' => 'PHP CURL', 'status' => extension_loaded('curl') || @shell_exec("$php_bin -m 2>/dev/null | grep curl"), 'critical' => true, 'help' => get_package_install_help('ext', 'curl', $distro)],
    ];

    // === Vérifier Ghostscript ===
    $gs_path = get_ghostscript_path();
    if ($gs_path && (file_exists($gs_path) || trim(shell_exec(($is_windows ? "where " : "which ") . escapeshellarg($gs_path) . " 2>&1")))) {
        $results['dependencies']['ghostscript']['status'] = true;
        $results['dependencies']['ghostscript']['path'] = $gs_path;
        $results['dependencies']['ghostscript']['version'] = trim(shell_exec(escapeshellarg($gs_path) . ($is_windows ? " -v 2>&1" : " --version 2>&1")));
    }

    // === Vérifier GhostPCL (gpcl6) ===
    $gpcl_path = get_gpcl6_path();
    if ($gpcl_path && (file_exists($gpcl_path) || trim(shell_exec(($is_windows ? "where " : "which ") . escapeshellarg($gpcl_path) . " 2>&1")))) {
        $results['dependencies']['gpcl6']['status'] = true;
        $results['dependencies']['gpcl6']['path'] = $gpcl_path;
        $results['dependencies']['gpcl6']['version'] = trim(shell_exec(escapeshellarg($gpcl_path) . " -v 2>&1"));
    } elseif ($results['dependencies']['ghostscript']['status'] && !$is_windows) {
        $results['dependencies']['gpcl6']['status'] = true;
        $results['dependencies']['gpcl6']['path'] = $results['dependencies']['ghostscript']['path'];
        $results['dependencies']['gpcl6']['version'] = $results['dependencies']['ghostscript']['version'];
    }

    // === Vérifier GhostXPS (gxps) ===
    $gxps_path = get_gxps_path();
    if ($gxps_path && (file_exists($gxps_path) || trim(shell_exec(($is_windows ? "where " : "which ") . escapeshellarg($gxps_path) . " 2>&1")))) {
        $results['dependencies']['gxps']['status'] = true;
        $results['dependencies']['gxps']['path'] = $gxps_path;
        $results['dependencies']['gxps']['version'] = trim(shell_exec(escapeshellarg($gxps_path) . " -v 2>&1"));
    } elseif ($results['dependencies']['ghostscript']['status'] && !$is_windows) {
        $gxps_check = trim(shell_exec('which xpstops 2>/dev/null'));
        if ($gxps_check) {
            $results['dependencies']['gxps']['status'] = true;
            $results['dependencies']['gxps']['path'] = $gxps_check;
            $results['dependencies']['gxps']['version'] = 'xpstops (libgxps-utils)';
        }
    }

    // === Vérifier ImageMagick ===
    $magick_path = get_magick_path();
    if ($magick_path && (file_exists($magick_path) || trim(shell_exec(($is_windows ? "where " : "which ") . escapeshellarg($magick_path) . " 2>&1")))) {
        $results['dependencies']['imagemagick']['status'] = true;
        $results['dependencies']['imagemagick']['path'] = $magick_path;
        $cmd = escapeshellarg($magick_path) . " -version 2>&1";
        $lines = explode("\n", trim(shell_exec($cmd)));
        $results['dependencies']['imagemagick']['version'] = trim($lines[0] ?? '');
    } elseif (!$is_windows) {
        $convert_path = trim(shell_exec('which convert 2>/dev/null'));
        if ($convert_path) {
            $results['dependencies']['imagemagick']['status'] = true;
            $results['dependencies']['imagemagick']['path'] = $convert_path;
            $lines = explode("\n", trim(shell_exec(escapeshellarg($convert_path) . " -version | head -n 1")));
            $results['dependencies']['imagemagick']['version'] = trim($lines[0] ?? '');
        }
    }

    // === Vérifier OCRmyPDF ===
    $ocrmypdf_path = trim(shell_exec(($is_windows ? "where " : "which ") . "ocrmypdf 2>&1"));
    if ($ocrmypdf_path && (file_exists($ocrmypdf_path) || strpos($ocrmypdf_path, 'not found') === false)) {
        $results['dependencies']['ocrmypdf']['status'] = true;
        $results['dependencies']['ocrmypdf']['path'] = $ocrmypdf_path;
        $results['dependencies']['ocrmypdf']['version'] = trim(shell_exec(escapeshellarg($ocrmypdf_path) . " --version 2>&1"));
    }

    // === Vérifier Tesseract ===
    $tesseract_path = trim(shell_exec(($is_windows ? "where " : "which ") . "tesseract 2>&1"));
    if ($tesseract_path && (file_exists($tesseract_path) || strpos($tesseract_path, 'not found') === false)) {
        $results['dependencies']['tesseract']['status'] = true;
        $results['dependencies']['tesseract']['path'] = $tesseract_path;
        $lines = explode("\n", trim(shell_exec(escapeshellarg($tesseract_path) . " --version 2>&1")));
        $results['dependencies']['tesseract']['version'] = trim($lines[0] ?? '');
    }

    // === Vérifier JBIG2 ===
    $jbig2_path = trim(shell_exec(($is_windows ? "where " : "which ") . "jbig2 2>&1"));
    if ($jbig2_path && (file_exists($jbig2_path) || strpos($jbig2_path, 'not found') === false)) {
        $results['dependencies']['jbig2']['status'] = true;
        $results['dependencies']['jbig2']['path'] = $jbig2_path;
        $results['dependencies']['jbig2']['version'] = trim(shell_exec(escapeshellarg($jbig2_path) . " -V 2>&1"));
    }

    // Vérifier les permissions
    require_once __DIR__ . '/bibliotheque.php';
    // Définition des dossiers à vérifier (utilisant les chemins centralisés)
    $folders_to_check = [
        'tmp' => getTmpDir(),
        'uploads' => getUploadsDir(),
        'bibliotheque' => getBibliothequeDir()
    ];

    foreach ($folders_to_check as $key => $abs_path) {
        if ($abs_path) {
            $is_writable = is_writable($abs_path);
            $results['permissions'][$key] = [
                'name' => ucfirst($key),
                'path' => $abs_path,
                'status' => $is_writable,
                'critical' => true
            ];
            if (!$is_writable) $results['critical_missing'] = true;
        } else {
            $results['permissions'][$key] = [
                'name' => ucfirst($key),
                'path' => $abs_path,
                'status' => false,
                'critical' => true,
                'error' => 'Dossier inexistant'
            ];
            $results['critical_missing'] = true;
        }
    }

    // Vérifier si des dépendances critiques manquent
    foreach ($results['dependencies'] as $dep) {
        if ($dep['critical'] && !$dep['status']) {
            $results['critical_missing'] = true;
            break;
        }
    }
    
    foreach ($results['php_extensions'] as $ext) {
        if ($ext['critical'] && !$ext['status']) {
            $results['critical_missing'] = true;
            break;
        }
    }

    return $results;
}

/**
 * Calculer la commande d'installation globale basée sur les résultats du diagnostic
 */
function get_global_install_command(array $health_check_results): ?string
{
    $missing_pkgs = [];
    
    foreach ($health_check_results['dependencies'] as $key => $dep) {
        if (!$dep['status'] && $dep['critical']) {
            $missing_pkgs[] = ['type' => 'bin', 'key' => $key];
        }
    }
    
    foreach ($health_check_results['php_extensions'] as $key => $ext) {
        if (!$ext['status'] && $ext['critical']) {
            $missing_pkgs[] = ['type' => 'ext', 'key' => $key];
        }
    }
    
    if (empty($missing_pkgs)) return null;
    
    return get_aggregated_install_command($missing_pkgs);
}
