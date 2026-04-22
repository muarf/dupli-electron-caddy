<?php

require_once __DIR__ . '/../../controler/functions/health_check.php';

test('get_package_install_help returns correct command for debian', function () {
    $cmd = get_package_install_help('bin', 'ghostscript', 'debian');
    expect($cmd)->toBe('sudo apt-get install -y ghostscript');
    
    $cmd = get_package_install_help('ext', 'gd', 'debian');
    expect($cmd)->toBe('sudo apt-get install -y php-gd');
});

test('get_package_install_help returns correct command for fedora', function () {
    $cmd = get_package_install_help('bin', 'ghostscript', 'fedora');
    expect($cmd)->toBe('sudo dnf install -y ghostscript');
    
    $cmd = get_package_install_help('ext', 'gd', 'fedora');
    expect($cmd)->toBe('sudo dnf install -y php-gd');
});

test('get_aggregated_install_command groups packages correctly', function () {
    $packages = [
        ['type' => 'bin', 'key' => 'ghostscript'],
        ['type' => 'ext', 'key' => 'gd'],
        ['type' => 'ext', 'key' => 'mbstring']
    ];
    
    // This function calls get_linux_distro_info(), which might return 'debian' on this system
    $cmd = get_aggregated_install_command($packages);
    
    expect($cmd)->toContain('ghostscript');
    expect($cmd)->toContain('php-gd');
    expect($cmd)->toContain('php-mbstring');
});

test('get_global_install_command handles empty results', function () {
    $results = [
        'dependencies' => [
            'ghostscript' => ['status' => true, 'critical' => true]
        ],
        'php_extensions' => [
            'gd' => ['status' => true, 'critical' => true]
        ]
    ];
    expect(get_global_install_command($results))->toBeNull();
});

test('get_global_install_command return command when dependency missing', function () {
    $results = [
        'dependencies' => [
            'ghostscript' => ['status' => false, 'critical' => true, 'key' => 'ghostscript']
        ],
        'php_extensions' => [
            'gd' => ['status' => true, 'critical' => true]
        ]
    ];
    
    $cmd = get_global_install_command($results);
    expect($cmd)->not->toBeNull();
    expect($cmd)->toContain('ghostscript');
});
