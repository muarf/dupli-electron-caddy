<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];
    $this->conf = $GLOBALS['conf'];
});

afterEach(function () {
    $_GET = [];
    $_POST = [];
});

it('renvoie une erreur 400 si le paramètre file est manquant', function () {
    $output = run_get_pdf_path([]);
    $json = json_decode($output, true);

    expect($json['success'])->toBeFalse();
    expect($json['error'])->toContain('non spécifié');
});

it('renvoie une erreur 400 si l extension n est pas .pdf', function () {
    $output = run_get_pdf_path(['file' => 'test.txt']);
    $json = json_decode($output, true);

    expect($json['success'])->toBeFalse();
    expect($json['error'])->toContain('non autorisé');
});

it('bloque les tentatives de path traversal', function () {
    $output = run_get_pdf_path(['file' => '../../../../etc/passwd']);
    $json = json_decode($output, true);

    expect($json['success'])->toBeFalse();
});

it('retourne le chemin absolu réel pour un fichier PDF existant dans le dossier temporaire', function () {
    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
    if (!file_exists($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }
    $testFile = $tmpDir . 'test_document.pdf';
    file_put_contents($testFile, '%PDF-1.4 Fake PDF Header');

    $output = run_get_pdf_path(['file' => 'test_document.pdf', 'dir' => 'unimpose']);
    $json = json_decode($output, true);

    expect($json['success'])->toBeTrue();
    expect($json['path'])->toBe(realpath($testFile));

    // Nettoyage
    if (file_exists($testFile)) {
        unlink($testFile);
    }
});

function run_get_pdf_path(array $get): string
{
    return execute_routed_endpoint('get_pdf_path', [
        'method' => 'GET',
        'get' => $get,
        'conf' => $GLOBALS['conf'],
    ]);
}
