<?php

require_once __DIR__ . '/../../models/pdf_organizer.php';
require_once __DIR__ . '/../../controler/functions/paths.php';

uses()->group('unit', 'organizer');

test('PDF Organizer can generate thumbnails from a PDF via CLI', function () {
    // Preparation
    $session_id = 'test_sess_' . uniqid();
    $tmp_base = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $session_id . DIRECTORY_SEPARATOR;
    $originals_dir = $tmp_base . 'originals' . DIRECTORY_SEPARATOR;
    $thumbs_dir = $tmp_base . 'thumbs' . DIRECTORY_SEPARATOR;

    if (!is_dir($originals_dir)) mkdir($originals_dir, 0777, true);
    if (!is_dir($thumbs_dir)) mkdir($thumbs_dir, 0777, true);

    $test_pdf = __DIR__ . '/../../../tests/assets/blank_A4_4pages.pdf';
    $file_id = 'test_file';
    $dest_path = $originals_dir . $file_id . '.pdf';
    copy($test_pdf, $dest_path);

    // Mock $_FILES and $_POST if needed, but here we test the logic inside handleUpload manually or via globals
    $_POST['session_id'] = $session_id;

    // Simulate handleUpload logic for binary check
    $magick = get_binary_path('magick');
    $bin_dir = dirname($magick);
    putenv("PATH=$bin_dir;" . getenv("PATH"));
    
    $cmd = escapeshellarg($magick) . " -density 72 " . escapeshellarg($dest_path) . " -quality 85 -scene 1 " . escapeshellarg($thumbs_dir . $file_id . "_page_%03d.png");
    exec($cmd);

    $pages = glob($thumbs_dir . $file_id . '_page_*.png');
    
    expect($pages)->not->toBeEmpty();
    expect(count($pages))->toBe(4);
    expect(file_exists($pages[0]))->toBeTrue();

    // Cleanup
    foreach (glob($tmp_base . "*") as $f) {
        if (is_dir($f)) {
            foreach (glob($f . "/*") as $sf) unlink($sf);
            rmdir($f);
        } else unlink($f);
    }
    rmdir($tmp_base);
});

test('PDF Organizer can merge pages into a new PDF', function () {
    $session_id = 'test_merge_' . uniqid();
    $tmp_base = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $session_id . DIRECTORY_SEPARATOR;
    $originals_dir = $tmp_base . 'originals' . DIRECTORY_SEPARATOR;
    mkdir($originals_dir, 0777, true);

    $test_pdf = __DIR__ . '/../../../tests/assets/blank_A4_4pages.pdf';
    $file_id = 'f1';
    copy($test_pdf, $originals_dir . $file_id . '.pdf');

    // Simulate handleGenerate input
    $_POST['session_id'] = $session_id;
    $structure = [
        ['type' => 'page', 'file_id' => $file_id, 'page_num' => 1, 'rotation' => 0],
        ['type' => 'blank'],
        ['type' => 'page', 'file_id' => $file_id, 'page_num' => 2, 'rotation' => 90],
    ];
    $_POST['structure'] = json_encode($structure);

    // Call handleGenerate
    $result = handleGenerate();

    expect($result['success'])->toBeTrue();
    $output_pdf = $tmp_base . 'output.pdf';
    expect(file_exists($output_pdf))->toBeTrue();
    expect(filesize($output_pdf))->toBeGreaterThan(0);

    // Cleanup
    // (Optional: keep for manual inspection if needed, but normally cleanup)
});
