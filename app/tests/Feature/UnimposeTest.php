<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/unimpose.php';
require_once __DIR__ . '/../../models/unimpose_logic.php';
require_once __DIR__ . '/Support/PdfTestHelpers.php';

it('désimpose un livret simple en doublant le nombre de pages', function () {
    $input = createSamplePdf(2);
    $output = tempnam(sys_get_temp_dir(), 'dupli_unimpose_') . '.pdf';

    try {
        $resultFile = unimpose_booklet($input, $output);

        expect($resultFile)->toBe($output);
        expect(file_exists($resultFile))->toBeTrue();

        $inspector = new TCPDI();
        $pageCount = $inspector->setSourceFile($resultFile);
        expect($pageCount)->toBe(4);
    } finally {
        cleanupPath($input);
        cleanupPath($output);
    }
});

it('lève une exception lorsque le fichier source est introuvable', function () {
    $output = tempnam(sys_get_temp_dir(), 'dupli_unimpose_missing_') . '.pdf';
    cleanupPath($output);

expect(fn () => unimpose_booklet('/tmp/fichier_inexistant.pdf', $output))
    ->toThrow(Exception::class, 'Le fichier PDF n\'existe pas ou n\'est pas lisible.');
});

it('Action signale une erreur quand le fichier uploadé n’est pas un PDF', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'dupli_txt_');
    file_put_contents($tmpFile, 'demo');

    $originalServer = $_SERVER;
    $originalFiles = $_FILES;

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_FILES = [
        'pdf' => [
            'name' => 'document.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ],
    ];

    try {
        $html = Action([]);
        expect($html)->toContain('Le fichier doit être un PDF');
    } finally {
        $_SERVER = $originalServer;
        $_FILES = $originalFiles;
        cleanupPath($tmpFile);
    }
});


