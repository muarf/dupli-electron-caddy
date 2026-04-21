<?php
/**
 * Fonctions de diagnostic et de santé du système
 */

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
 * Retourne la commande d'installation pour un paquet donné
 */
function get_package_install_help(string $type, string $pkg_key, string $distro): string
{
$commands = [
        'debian' => ['pref' => 'sudo apt-get install -y ', 'ext_prefix' => 'php8.5-'],
        'fedora' => ['pref' => 'sudo dnf install -y ', 'ext_prefix' => 'php-'],
        'arch' => ['pref' => 'sudo pacman -S --noconfirm ', 'ext_prefix' => 'php-'],
        'windows' => ['pref' => 'Activez dans php.ini : ', 'ext_prefix' => 'php_'],
        'unknown' => ['pref' => 'sudo apt-get install -y ', 'ext_prefix' => 'php-']
    ];

    if (PHP_OS_FAMILY === 'Windows') {
        $distro = 'windows';
    }

    $d = $commands[$distro] ?? $commands['unknown'];
    
    if ($type === 'bin') {
        $bins = [
            'ghostscript' => 'ghostscript',
            'imagemagick' => 'imagemagick',
            'gpcl6' => 'ghostscript',
            'gxps' => 'libgxps-utils'
        ];
        if ($distro === 'windows') {
            return "Téléchargez et installez " . ($bins[$pkg_key] ?? $pkg_key);
        }
        if ($distro === 'debian') {
            if ($pkg_key === 'gxps') return 'sudo apt-get install -y libgxps-utils';
            if ($pkg_key === 'gpcl6') return 'sudo apt-get install -y ghostscript (inclut gpcl6)';
            if ($pkg_key === 'ghostscript') return 'sudo apt-get install -y ghostscript';
            if ($pkg_key === 'imagemagick') return 'sudo apt-get install -y imagemagick';
        }
        return $d['pref'] . ($bins[$pkg_key] ?? $pkg_key);
    }

    $exts = [
        'imagick' => ($distro === 'fedora' ? 'pecl-imagick' : 'imagick'),
        'gd' => 'gd',
        'sqlite3' => ($distro === 'arch' ? 'sqlite' : 'sqlite3'),
        'mbstring' => 'mbstring',
        'xml' => 'xml'
    ];

    return $d['pref'] . $d['ext_prefix'] . ($exts[$pkg_key] ?? $pkg_key);
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
        'debian' => ['pref' => 'sudo apt-get install -y ', 'ext_prefix' => 'php8.5-'],
        'fedora' => ['pref' => 'sudo dnf install -y ', 'ext_prefix' => 'php-'],
        'arch' => ['pref' => 'sudo pacman -S --noconfirm ', 'ext_prefix' => 'php-'],
        'windows' => ['pref' => 'Modules à activer : ', 'ext_prefix' => 'extension='],
        'unknown' => ['pref' => 'sudo apt-get install -y ', 'ext_prefix' => 'php-']
    ];

    // Sur PHP Windows "unknown" (souvent serveur mutualisé ou IIS), on ne propose pas sudo
    if (PHP_OS_FAMILY === 'Windows' && $distro === 'unknown') {
        $distro = 'windows';
    }

    $d = $commands[$distro] ?? $commands['unknown'];
    
    $bins = [
        'ghostscript' => 'ghostscript',
        'imagemagick' => 'imagemagick',
        'gpcl6' => 'ghostscript',
        'gxps' => 'libgxps-utils'
    ];
    
    $exts = [
        'imagick' => ($distro === 'fedora' ? 'pecl-imagick' : 'imagick'),
        'gd' => 'gd',
        'sqlite3' => ($distro === 'arch' ? 'sqlite' : 'sqlite3'),
        'mbstring' => 'mbstring',
        'xml' => 'xml'
    ];

    $resolved_pkgs = [];
    foreach ($packages as $pkg) {
        if ($pkg['type'] === 'bin') {
            $resolved_pkgs[] = $bins[$pkg['key']] ?? $pkg['key'];
        } else {
            $resolved_pkgs[] = $d['ext_prefix'] . ($exts[$pkg['key']] ?? $pkg['key']);
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

    $results['php_extensions'] = [
        'imagick' => [
            'name' => 'PHP Imagick', 
            'status' => extension_loaded('imagick') || shell_exec('php8.5 -m 2>/dev/null | grep imagick'),
            'critical' => true, 
            'help' => $is_electron_windows ? 'Téléchargez php_imagick.dll et activez "extension=imagick" dans ' . $php_ini_path : 'sudo apt-get install -y php8.5-imagick (ou vérifiez php8.5)'
        ],
        'gd' => [
            'name' => 'PHP GD', 
            'status' => extension_loaded('gd') || shell_exec('php8.5 -m 2>/dev/null | grep gd'),
            'critical' => true, 
            'help' => $is_electron_windows ? 'Décommentez "extension=gd" dans ' . $php_ini_path : 'sudo apt-get install -y php8.5-gd (ou vérifiez php8.5)'
        ],
        'sqlite3' => [
            'name' => 'PHP SQLite3', 
            'status' => extension_loaded('sqlite3') || shell_exec('php8.5 -m 2>/dev/null | grep sqlite3'),
            'critical' => true, 
            'help' => $is_electron_windows ? 'Décommentez "extension=sqlite3" dans ' . $php_ini_path : 'sudo apt-get install -y php8.5-sqlite3 (ou vérifiez php8.5)'
        ],
        'mbstring' => ['name' => 'PHP Mbstring', 'status' => extension_loaded('mbstring') || shell_exec('php8.5 -m 2>/dev/null | grep mbstring'), 'critical' => true, 'help' => 'sudo apt-get install -y php8.5-mbstring'],
        'xml' => ['name' => 'PHP XML', 'status' => extension_loaded('xml') || shell_exec('php8.5 -m 2>/dev/null | grep -E "xml|SimpleXML"'), 'critical' => true, 'help' => 'sudo apt-get install -y php8.5-xml'],
    ];

    // === Vérifier Ghostscript ===
    // 1. Priorité au binaire local
    $gs_local_path = realpath(__DIR__ . '/../../../bin/win-x64/' . ($is_windows ? 'gs.exe' : 'gs'));
    if ($gs_local_path && file_exists($gs_local_path)) {
        $results['dependencies']['ghostscript']['status'] = true;
        $results['dependencies']['ghostscript']['path'] = $gs_local_path;
        $cmd = escapeshellarg($gs_local_path) . " -v 2>&1";
        $results['dependencies']['ghostscript']['version'] = trim(shell_exec($cmd));
    } else {
        // Fallback gswin64c.exe
        $gs_local_path = realpath(__DIR__ . '/../../../bin/win-x64/' . ($is_windows ? 'gswin64c.exe' : 'gs'));
        if ($gs_local_path && file_exists($gs_local_path)) {
            $results['dependencies']['ghostscript']['status'] = true;
            $results['dependencies']['ghostscript']['path'] = $gs_local_path;
            $results['dependencies']['ghostscript']['version'] = trim(shell_exec(escapeshellarg($gs_local_path) . " -v 2>&1"));
        } else {
            // 2. Fallback binaire global
            $gs_cmd = $is_windows ? 'where gs.exe 2>NUL' : 'which gs 2>/dev/null';
            $gs_path = trim(shell_exec($gs_cmd));
            if (!$gs_path && $is_windows) $gs_path = trim(shell_exec('where gswin64c.exe 2>NUL'));
            
            if ($gs_path) {
                $gs_path = explode("\n", str_replace("\r", "", $gs_path))[0];
                $results['dependencies']['ghostscript']['status'] = true;
                $results['dependencies']['ghostscript']['path'] = $gs_path;
                $results['dependencies']['ghostscript']['version'] = trim(shell_exec(escapeshellarg($gs_path) . " --version 2>&1"));
            }
        }
    }

    // === Vérifier GhostPCL (gpcl6) - souvent inclus dans ghostscript ===
    $gpcl_local_path = realpath(__DIR__ . '/../../../bin/win-x64/' . ($is_windows ? 'gpcl6.exe' : 'gpcl6'));
    if ($gpcl_local_path && file_exists($gpcl_local_path)) {
        $results['dependencies']['gpcl6']['status'] = true;
        $results['dependencies']['gpcl6']['path'] = $gpcl_local_path;
        $results['dependencies']['gpcl6']['version'] = trim(shell_exec(escapeshellarg($gpcl_local_path) . " -v 2>&1"));
    } elseif ($results['dependencies']['ghostscript']['status'] && !$is_windows) {
        // Sur Linux, gpcl6 est inclus dans ghostscript
        $results['dependencies']['gpcl6']['status'] = true;
        $results['dependencies']['gpcl6']['path'] = $results['dependencies']['ghostscript']['path'];
        $results['dependencies']['gpcl6']['version'] = $results['dependencies']['ghostscript']['version'];
    }

    // === Vérifier GhostXPS (gxps) ===
    $gxps_local_path = realpath(__DIR__ . '/../../../bin/win-x64/' . ($is_windows ? 'gxps.exe' : 'gxps'));
    if ($gxps_local_path && file_exists($gxps_local_path)) {
        $results['dependencies']['gxps']['status'] = true;
        $results['dependencies']['gxps']['path'] = $gxps_local_path;
        $results['dependencies']['gxps']['version'] = trim(shell_exec(escapeshellarg($gxps_local_path) . " -v 2>&1"));
    } elseif ($results['dependencies']['ghostscript']['status'] && !$is_windows) {
        // Vérifier si libgxps-utils est installé
        $gxps_check = trim(shell_exec('which xpstops 2>/dev/null'));
        if ($gxps_check) {
            $results['dependencies']['gxps']['status'] = true;
            $results['dependencies']['gxps']['path'] = $gxps_check;
            $results['dependencies']['gxps']['version'] = 'xpstops (libgxps-utils)';
        }
    }

    // === Vérifier ImageMagick ===
    // 1. Priorité au binaire local
    $magick_local_path = realpath(__DIR__ . '/../../../bin/win-x64/' . ($is_windows ? 'magick.exe' : 'magick'));
    if ($magick_local_path && file_exists($magick_local_path)) {
        $results['dependencies']['imagemagick']['status'] = true;
        $results['dependencies']['imagemagick']['path'] = $magick_local_path;
        $cmd = escapeshellarg($magick_local_path) . " -version 2>&1";
        $lines = explode("\n", trim(shell_exec($cmd)));
        $results['dependencies']['imagemagick']['version'] = trim($lines[0] ?? '');
    } else {
        // 2. Fallback binaire global
        $magick_cmd = $is_windows ? 'where magick.exe 2>NUL' : 'which magick 2>/dev/null';
        $magick_path = trim(shell_exec($magick_cmd));
        if (!$magick_path && !$is_windows) {
            $magick_path = trim(shell_exec('which convert 2>/dev/null'));
        }
        
        if ($magick_path) {
            $magick_path = explode("\n", str_replace("\r", "", $magick_path))[0];
            $results['dependencies']['imagemagick']['status'] = true;
            $results['dependencies']['imagemagick']['path'] = $magick_path;
            $cmd = escapeshellarg($magick_path) . ($is_windows ? " -version" : " -version | head -n 1");
            $lines = explode("\n", trim(shell_exec($cmd)));
            $results['dependencies']['imagemagick']['version'] = trim($lines[0] ?? '');
        }
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
