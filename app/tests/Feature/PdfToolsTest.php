<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Support/PdfTestHelpers.php';

it('réordonne les pages A5 en complétant jusqu’au multiple de 8', function () {
    $pages = runPdfTool('imposition', 'reordering_pages_a5', [10]);

    expect($pages)->toBeArray();
    expect(count($pages))->toBe(16);

    foreach (range(1, 10) as $pageNumber) {
        expect(in_array($pageNumber, $pages, true))->toBeTrue();
    }
    expect(min($pages))->toBeGreaterThanOrEqual(1);
    expect(max($pages))->toBeLessThanOrEqual(10);
});

it('retourne la séquence exacte pour 32 pages A6', function () {
    $sequence = runPdfTool('imposition', 'imposition_for_sheet', [0, 32]);

    expect($sequence)->toBe([
        1, 32, 25, 8, 16, 17, 24, 9, 7, 26, 31, 2, 10, 23, 18, 15,
    ]);
});

it('ajoute des pages blanches pour atteindre un multiple choisi', function () {
    $pdfPath = createSamplePdf(3);
    $result = runPdfTool('imposition', 'padPdfToMultiple', [$pdfPath, 8]);

    try {
        expect($result['page_count'])->toBe(8);
        expect(file_exists($result['file']))->toBeTrue();

        $inspector = new TCPDI();
        $pageCount = $inspector->setSourceFile($result['file']);
        expect($pageCount)->toBe(8);
    } finally {
        cleanupPath($pdfPath);
        if (!empty($result['temp_file'])) {
            cleanupPath($result['temp_file']);
        } elseif (!empty($result['file']) && $result['file'] !== $pdfPath) {
            cleanupPath($result['file']);
        }
    }
});

it('convertit une série de PNG en PDF multi-pages', function () {
    [$pngOne, $fillOne] = createSamplePng(60, 40, 0.5);
    [$pngTwo, $fillTwo] = createSamplePng(40, 60, 0.3);
    $outputPdf = tempnam(sys_get_temp_dir(), 'dupli_png_pdf_') . '.pdf';

    try {
        $conversion = runPdfTool('png_to_pdf', 'convert_png_to_pdf', [[ $pngOne, $pngTwo ], $outputPdf, 'A4', 'P']);
        expect($conversion)->toBeTrue();
        expect(file_exists($outputPdf))->toBeTrue();

        $inspector = new TCPDI();
        $pageCount = $inspector->setSourceFile($outputPdf);
        expect($pageCount)->toBe(2);
    } finally {
        cleanupPath($pngOne);
        cleanupPath($pngTwo);
        cleanupPath($outputPdf);
    }
});

it('convertit un PDF en PNG grâce à Ghostscript', function () {
    $pdfPath = createSamplePdf(2);
    $outputDir = sys_get_temp_dir() . '/dupli_png_' . uniqid() . '/';

    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    try {
        $files = runPdfTool('pdf_to_png', 'convert_pdf_to_png', [$pdfPath, $outputDir, 72, 'test']);
        expect($files)->toHaveCount(2);
        foreach ($files as $file) {
            expect(file_exists($file))->toBeTrue();
        }
    } finally {
        cleanupPath($pdfPath);
        cleanupPath($outputDir);
    }
});

it('analyse le format d’un PDF pour l’imposition de tracts', function () {
    $pdfPath = createSamplePdf(1);
    try {
        $analysis = runPdfTool('imposition_tracts', 'analyzePDFFormat', [[
            'type' => 'application/pdf',
            'tmp_name' => $pdfPath,
        ]]);

        expect($analysis['success'])->toBeTrue();
        expect($analysis['format'])->toBe('A4');
        expect($analysis['page_count'])->toBe(1);
    } finally {
        cleanupPath($pdfPath);
    }
});

it('calcule un taux de remplissage cohérent', function () {
    [$pngPath] = createSamplePng(20, 20, 0.5);
    try {
        $stats = runPdfTool('taux_remplissage', 'calculate_fill_rate', [$pngPath, 250]);
        expect($stats['fill_rate'])->toEqualWithDelta(50, 1);
        expect($stats['filled_pixels'])->toEqualWithDelta($stats['total_pixels'] / 2, $stats['total_pixels'] * 0.1);
    } finally {
        cleanupPath($pngPath);
    }
});

it('convertit un PDF en image pour analyse du taux de remplissage', function () {
    $pdfPath = createSamplePdf(1);
    $outputDir = sys_get_temp_dir() . '/dupli_analysis_' . uniqid() . '/';
    mkdir($outputDir, 0777, true);

    try {
        $imagePath = runPdfTool('taux_remplissage', 'convert_pdf_to_image_for_analysis', [$pdfPath, $outputDir, 1, 72]);
        expect(file_exists($imagePath))->toBeTrue();
    } finally {
        cleanupPath($pdfPath);
        cleanupPath($outputDir);
    }
});

it('retourne les tambours dynamiques pour le séparateur Riso', function () {
    [$dbPath, $pdo] = create_test_sqlite_database();
    configure_sqlite_conf($dbPath);

    $pdo->exec('CREATE TABLE duplicopieurs (id INTEGER PRIMARY KEY AUTOINCREMENT, marque TEXT, modele TEXT, actif INTEGER, tambours TEXT)');
    $pdo->exec("INSERT INTO duplicopieurs (marque, modele, actif, tambours) VALUES ('Riso', 'SF', 1, '[\"tambour_noir\",\"tambour_fluo\"]')");

    try {
        $html = runPdfTool('riso_separator', 'Action', [$GLOBALS['conf']], [
            'env' => ['DUPLICATOR_DB_PATH' => $dbPath],
            'conf' => $GLOBALS['conf'],
        ]);

        expect($html)->toContain('riso-container');
        expect($html)->toContain('Séparateur Riso');
    } finally {
        if (isset($pdo)) {
            $pdo = null;
        }
        cleanupPath($dbPath);
    }
});

/**
 * Lance le helper CLI pour exécuter une fonction de module dans un sous-processus.
 */
function runPdfTool(string $module, string $function, array $args = [], array $options = [])
{
    $payload = [
        'module' => $module,
        'function' => $function,
        'args' => $args,
    ];

    if (!empty($options['env'])) {
        $payload['env'] = $options['env'];
    }
    if (!empty($options['conf'])) {
        $payload['conf'] = $options['conf'];
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg(__DIR__ . '/../helpers/run_pdf_tool.php') . ' ' .
        escapeshellarg(json_encode($payload));

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException('run_pdf_tool a échoué: ' . implode("\n", $output));
    }

    $response = json_decode(implode("\n", $output), true);
    if (!is_array($response) || ($response['status'] ?? '') !== 'ok') {
        throw new RuntimeException('Erreur helper: ' . ($response['message'] ?? 'réponse invalide'));
    }

    return $response['result'];
}


