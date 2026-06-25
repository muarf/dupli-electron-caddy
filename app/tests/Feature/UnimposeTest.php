<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../models/unimpose_logic.php';
require_once __DIR__ . '/Support/PdfTestHelpers.php';

it('désimpose un livret simple en doublant le nombre de pages', function () {
    $input = createSamplePdf(2); // PDF de 2 planches (4 pages au total si imposé en livret)
    $output = tempnam(sys_get_temp_dir(), 'dupli_unimpose_') . '.pdf';

    try {
        $unimposer = new UnimposeBooklet($input, $output);
        $result = $unimposer->unimposeBooklet();

        expect($result)->toBe($output);
        expect(file_exists($output))->toBeTrue();

        $inspector = new TCPDI();
        $pageCount = $inspector->setSourceFile($output);
        expect($pageCount)->toBe(4);
    } finally {
        cleanupPath($input);
        cleanupPath($output);
    }
});

it('retourne false lorsque le fichier source est introuvable', function () {
    $output = tempnam(sys_get_temp_dir(), 'dupli_unimpose_missing_') . '.pdf';
    cleanupPath($output);

    $unimposer = new UnimposeBooklet('/tmp/fichier_inexistant.pdf', $output);
    $result = $unimposer->unimposeBooklet();

    expect($result)->toBeFalse();
});
