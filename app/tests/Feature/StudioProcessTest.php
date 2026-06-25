<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Support/PdfTestHelpers.php';

beforeEach(function () {
    $this->testTempDir = sys_get_temp_dir() . '/dupli_studio_test_' . uniqid();
    if (!is_dir($this->testTempDir)) {
        mkdir($this->testTempDir, 0777, true);
        chmod($this->testTempDir, 0777);
    }
    putenv('DUPLICATOR_TEMP_DIR=' . $this->testTempDir);
});

afterEach(function () {
    putenv('DUPLICATOR_TEMP_DIR'); // Clear
    if (is_dir($this->testTempDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($this->testTempDir);
    }
});

/**
 * Lance le helper run_studio_process dans un sous-processus.
 */
function runStudioProcess(string $action, array $params = [], array $files = [], array $multiFiles = [])
{
    $payload = [
        'post' => array_merge(['action' => $action], $params),
        'files' => $files,
        'multi_files' => $multiFiles,
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'studio_payload_') . '.json';
    file_put_contents($tempFile, json_encode($payload));

    $command = escapeshellarg(PHP_BINARY) . ' ' .
        escapeshellarg(__DIR__ . '/../helpers/run_studio_process.php') . ' ' .
        escapeshellarg($tempFile) . ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }

    $outputStr = implode("\n", $output);
    $response = json_decode($outputStr, true);

    if (!is_array($response)) {
        if (preg_match('/\{.*\}/s', $outputStr, $matches)) {
            $response = json_decode($matches[0], true);
        }
    }

    if (!is_array($response)) {
        throw new RuntimeException("Studio Process helper a échoué: " . $outputStr);
    }

    return $response;
}

it('impose un PDF en brochure (brochure_type = booklet/leaflet)', function () {
    $pdfPath = createSamplePdf(3);

    try {
        $response = runStudioProcess('impose', [
            'impose_type' => 'brochure',
            'n_up' => 2,
            'resize_mode' => 'percent',
            'scale' => 100,
        ], [
            'file' => [
                'name' => 'brochure.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');

        // Analyser le fichier généré
        preg_match('/file=([^&]+)/', $response['download_url'], $matches);
        $fileName = urldecode($matches[1]);
        $outputFile = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR . $fileName;

        expect(file_exists($outputFile))->toBeTrue();
        $inspector = new TCPDI();
        $pageCount = $inspector->setSourceFile($outputFile);
        // 3 pages + 1 page blanche (padded to 4) = 2 planches (feuilles A4)
        expect($pageCount)->toBe(2);
    } finally {
        cleanupPath($pdfPath);
    }
});

it('impose un PDF en livre (cut & stack)', function () {
    $pdfPath = createSamplePdf(3);

    try {
        $response = runStudioProcess('impose', [
            'impose_type' => 'livre',
            'n_up' => 2,
            'resize_mode' => 'percent',
            'scale' => 100,
        ], [
            'file' => [
                'name' => 'livre.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');

        preg_match('/file=([^&]+)/', $response['download_url'], $matches);
        $fileName = urldecode($matches[1]);
        $outputFile = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR . $fileName;

        expect(file_exists($outputFile))->toBeTrue();
    } finally {
        cleanupPath($pdfPath);
    }
});

it('impose un PDF en tracts (copies identiques N-up)', function () {
    $pdfPath = createSamplePdf(1);

    try {
        $response = runStudioProcess('impose', [
            'impose_type' => 'tracts',
            'output_format' => 'A3',
            'manual_format' => 'auto',
            'duplex_mode' => 'none',
        ], [
            'file' => [
                'name' => 'tract.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('file=');
    } finally {
        cleanupPath($pdfPath);
    }
});

it('redimensionne un PDF', function () {
    $pdfPath = createSamplePdf(1);

    try {
        $response = runStudioProcess('resize', [
            'resize_format' => 'A4',
        ], [
            'file' => [
                'name' => 'source.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($pdfPath);
    }
});

it('exporte une image PNG sous forme de PDF (to_pdf)', function () {
    [$pngPath] = createSamplePng(100, 100, 0.5);

    try {
        $response = runStudioProcess('to_pdf', [
            'dpi' => 96,
        ], [
            'file' => [
                'name' => 'canvas.png',
                'type' => 'image/png',
                'tmp_name' => $pngPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($pngPath);
    }
});

it('génère un PDF multi-pages à partir de calques de couleurs Riso', function () {
    [$png1] = createSamplePng(100, 100, 0.5);
    [$png2] = createSamplePng(100, 100, 0.3);

    try {
        $response = runStudioProcess('riso_pdf', [
            'colors' => ['noir', 'rouge/fluo'],
            'dpi' => 96,
        ], [], [
            'layers' => [
                [
                    'name' => 'layer1.png',
                    'type' => 'image/png',
                    'tmp_name' => $png1,
                ],
                [
                    'name' => 'layer2.png',
                    'type' => 'image/png',
                    'tmp_name' => $png2,
                ],
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($png1);
        cleanupPath($png2);
    }
});

it('calcule le taux de remplissage pour une image (analyze_ink)', function () {
    [$pngPath] = createSamplePng(50, 50, 0.5);

    try {
        $response = runStudioProcess('analyze_ink', [], [
            'file' => [
                'name' => 'sample.png',
                'type' => 'image/png',
                'tmp_name' => $pngPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['result']['fill_rate'])->toEqualWithDelta(50, 5);
    } finally {
        cleanupPath($pngPath);
    }
});

it('convertit un PDF en série d\'images PNG (pdf_to_images)', function () {
    $pdfPath = createSamplePdf(2);

    try {
        $response = runStudioProcess('pdf_to_images', [
            'dpi' => 72,
        ], [
            'file' => [
                'name' => 'doc.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['page_count'])->toBe(2);
        expect($response['page_urls'])->toHaveCount(2);
    } finally {
        cleanupPath($pdfPath);
    }
});

it('fusionne deux PDFs (merge)', function () {
    $pdf1 = createSamplePdf(1);
    $pdf2 = createSamplePdf(1);

    try {
        $response = runStudioProcess('merge', [], [], [
            'files' => [
                [
                    'name' => 'part1.pdf',
                    'type' => 'application/pdf',
                    'tmp_name' => $pdf1,
                ],
                [
                    'name' => 'part2.pdf',
                    'type' => 'application/pdf',
                    'tmp_name' => $pdf2,
                ],
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($pdf1);
        cleanupPath($pdf2);
    }
});

it('désimpose un livret PDF (unimpose)', function () {
    $pdfPath = createSamplePdf(2); // planches du livret

    try {
        $response = runStudioProcess('unimpose', [
            'unimpose_mode' => 'booklet',
        ], [
            'file' => [
                'name' => 'booklet.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($pdfPath);
    }
});

it('organise les pages (organize_pages)', function () {
    $pdfPath = createSamplePdf(1);

    try {
        $response = runStudioProcess('organize_pages', [
            'structure' => json_encode([
                [
                    'type' => 'page',
                    'file_idx' => '0',
                    'page_num' => 1,
                    'rotation' => 90,
                ],
                [
                    'type' => 'blank',
                ],
            ]),
        ], [
            'file_0' => [
                'name' => 'original.pdf',
                'type' => 'application/pdf',
                'tmp_name' => $pdfPath,
            ],
        ]);

        expect($response['success'])->toBeTrue();
        expect($response['download_url'])->toContain('download_studio');
    } finally {
        cleanupPath($pdfPath);
    }
});
