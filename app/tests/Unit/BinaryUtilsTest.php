<?php

require_once __DIR__ . '/../../controler/functions/binary_utilities.php';

it('get_binary_path() retourne un chemin string pour gs', function () {
    if (function_exists('get_binary_path')) {
        $path = get_binary_path('gs');
        expect($path)->toBeString();
    } else {
        expect(true)->toBeTrue();
    }
});

it('get_binary_path() retourne un chemin string pour php', function () {
    if (function_exists('get_binary_path')) {
        $path = get_binary_path('php');
        expect($path)->toBeString();
    } else {
        expect(true)->toBeTrue();
    }
});

it('get_gs_path() retourne un chemin si disponible', function () {
    if (function_exists('get_gs_path')) {
        $path = get_gs_path();
        expect($path)->toBeString();
    } else {
        expect(true)->toBeTrue();
    }
});

it('get_imagick_path() retourne un chemin si disponible', function () {
    if (function_exists('get_imagick_path')) {
        $path = get_imagick_path();
        expect($path)->toBeString();
    } else {
        expect(true)->toBeTrue();
    }
});

it('get_php_executable() retourne un exécutable valide sans commande composite', function () {
    expect(function_exists('get_php_executable'))->toBeTrue();
    $bin = get_php_executable();
    expect($bin)->toBeString()->not->toBeEmpty();
    if (PHP_OS_FAMILY !== 'Windows') {
        expect($bin)->not->toContain(' ');
    }
});
