<?php

require_once __DIR__ . '/../../controler/functions/ImpositionProcessor.php';

// Mock class to record calls
class PdfSpy {
    public $calls = [];
    public $pages = 0;

    public function AddPage($orientation, $format) {
        $this->pages++;
        $this->calls[] = ['method' => 'AddPage', 'args' => [$orientation, $format]];
    }

    public function importPage($pageNumber) {
        return "template_$pageNumber";
    }

    public function useTemplate($templateId, $x, $y, $w, $h) {
        $this->calls[] = ['method' => 'useTemplate', 'args' => [$templateId, $x, $y, $w, $h]];
    }

    public function StartTransform() {
        $this->calls[] = ['method' => 'StartTransform'];
    }

    public function StopTransform() {
        $this->calls[] = ['method' => 'StopTransform'];
    }

    public function Rotate($angle, $x, $y) {
        $this->calls[] = ['method' => 'Rotate', 'args' => [$angle, $x, $y]];
    }
}

// Global helper mocks (they are expected by ImpositionProcessor)
if (!function_exists('resizeToA5')) {
    function resizeToA5($pdf, $template, $pw, $ph, $force) {
        return [0, 0, $pw, $ph];
    }
}
if (!function_exists('addPageNumber')) {
    function addPageNumber($pdf, $num, $x, $y, $w, $h, $rot) {}
}

test('it places 8 pages correctly for A5 imposition', function () {
    $pdf = new PdfSpy();
    $orderedPages = [8, 1, 2, 7, 6, 3, 4, 5]; // Example 8 pages
    $pageCount = 8;
    $pagesPerSheet = 8;
    $a3W = 420;
    $a3H = 297;
    $pageW = 148;
    $pageH = 210;
    $gutter = 0;
    
    $templateIdsPreview = [];

    ImpositionProcessor::processA5Imposition(
        $pdf,
        null, // No preview
        $templateIdsPreview,
        $orderedPages,
        $pageCount,
        $pagesPerSheet,
        $a3W,
        $a3H,
        $pageW,
        $pageH,
        $gutter,
        false, // forceResize
        false, // previewMode
        false, // add_crop_marks
        'livre',
        'fullsize',
        0, // bleed
        'standard',
        false // render_trim_numbers
    );

    // Should have 2 pages (recto + verso)
    expect($pdf->pages)->toBe(2);

    // Should have 8 calls to useTemplate
    $useTemplateCalls = array_filter($pdf->calls, fn($c) => $c['method'] === 'useTemplate');
    expect($useTemplateCalls)->toHaveCount(8);

    // Verify rotations (A5 head-to-head / tête-bêche uses 180 deg for the bottom row)
    $rotateCalls = array_filter($pdf->calls, fn($c) => $c['method'] === 'Rotate');
    // In processA5Side, 2 pages out of 4 are rotated 180 deg (row 1)
    // So for 2 sides, it should be 4 rotation calls
    expect($rotateCalls)->toHaveCount(4);
    
    foreach ($rotateCalls as $call) {
        expect($call['args'][0])->toBe(180);
    }
});
