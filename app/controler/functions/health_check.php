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
        'debian' => ['pref' => 'sudo apt-get install ', 'ext_prefix' => 'php-'],
        'fedora' => ['pref' => 'sudo dnf install ', 'ext_prefix' => 'php-'],
        'arch' => ['pref' => 'sudo pacman -S ', 'ext_prefix' => 'php-'],
        'unknown' => ['pref' => 'Installez ', 'ext_prefix' => 'php-']
    ];

    $d = $commands[$distro] ?? $commands['unknown'];
    
    if ($type === 'bin') {
        $bins = [
            'ghostscript' => 'ghostscript',
            'imagemagick' => 'imagemagick'
        ];
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
    $commands = [
        'debian' => ['pref' => 'sudo apt-get install -y ', 'ext_prefix' => 'php-'],
        'fedora' => ['pref' => 'sudo dnf install -y ', 'ext_prefix' => 'php-'],
        'arch' => ['pref' => 'sudo pacman -S --noconfirm ', 'ext_prefix' => 'php-'],
        'unknown' => ['pref' => 'sudo apt-get install ', 'ext_prefix' => 'php-']
    ];

    $d = $commands[$distro] ?? $commands['unknown'];
    
    $bins = [
        'ghostscript' => 'ghostscript',
        'imagemagick' => 'imagemagick'
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
            'imagemagick' => [
                'name' => 'ImageMagick',
                'status' => false,
                'version' => null,
                'path' => null,
                'critical' => true,
                'help' => get_package_install_help('bin', 'imagemagick', $distro)
            ]
        ],
        'php_extensions' => [
            'imagick' => ['name' => 'PHP Imagick', 'status' => extension_loaded('imagick'), 'critical' => true, 'help' => get_package_install_help('ext', 'imagick', $distro)],
            'gd' => ['name' => 'PHP GD', 'status' => extension_loaded('gd'), 'critical' => true, 'help' => get_package_install_help('ext', 'gd', $distro)],
            'sqlite3' => ['name' => 'PHP SQLite3', 'status' => extension_loaded('sqlite3'), 'critical' => true, 'help' => get_package_install_help('ext', 'sqlite3', $distro)],
            'mbstring' => ['name' => 'PHP Mbstring', 'status' => extension_loaded('mbstring'), 'critical' => true, 'help' => get_package_install_help('ext', 'mbstring', $distro)],
            'xml' => ['name' => 'PHP XML', 'status' => extension_loaded('xml'), 'critical' => true, 'help' => get_package_install_help('ext', 'xml', $distro)],
        ],
        'permissions' => []
    ];

    // Vérifier Ghostscript
    $gs_path = trim(shell_exec('which gs 2>/dev/null'));
    if ($gs_path) {
        $results['dependencies']['ghostscript']['status'] = true;
        $results['dependencies']['ghostscript']['path'] = $gs_path;
        $results['dependencies']['ghostscript']['version'] = trim(shell_exec("$gs_path --version 2>&1"));
    }

    // Vérifier ImageMagick
    $magick_path = trim(shell_exec('which magick 2>/dev/null'));
    if (!$magick_path) {
        $magick_path = trim(shell_exec('which convert 2>/dev/null'));
    }
    if ($magick_path) {
        $results['dependencies']['imagemagick']['status'] = true;
        $results['dependencies']['imagemagick']['path'] = $magick_path;
        $results['dependencies']['imagemagick']['version'] = trim(shell_exec("$magick_path -version | head -n 1"));
    }

    // Vérifier les permissions
    $folders_to_check = [
        'tmp' => '/../../public/tmp',
        'uploads' => '/../../public/uploads',
        'bibliotheque' => '/../../../bibliotheque'
    ];

    foreach ($folders_to_check as $key => $rel_path) {
        $abs_path = realpath(__DIR__ . '/' . $rel_path);
        if ($abs_path) {
            $is_writable = is_writable($abs_path);
            $results['permissions'][$key] = [
                'name' => ucfirst($key),
                'path' => $rel_path,
                'status' => $is_writable,
                'critical' => true
            ];
            if (!$is_writable) $results['critical_missing'] = true;
        } else {
            $results['permissions'][$key] = [
                'name' => ucfirst($key),
                'path' => $rel_path,
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
