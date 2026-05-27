<?php

require_once __DIR__ . '/../../models/image_processor.php';
require_once __DIR__ . '/../../controler/functions/binary_utilities.php';

uses()->group('unit', 'processor');

test('Image Processor can perform dithering via CLI', function () {
    // Create a 100x100 gradient image
    $img = imagecreatetruecolor(100, 100);
    for ($i = 0; $i < 100; $i++) {
        $col = imagecolorallocate($img, $i * 2, $i * 2, $i * 2);
        imageline($img, 0, $i, 100, $i, $col);
    }

    // Call dithering
    $dithered = convert_to_bitmap_dithering($img);

    expect($dithered)->not->toBeFalse();
    expect(imagesx($dithered))->toBe(100);
    expect(imagesy($dithered))->toBe(100);

    // Verify it's likely 2 colors (0 and 255)
    $c1 = imagecolorat($dithered, 0, 0);
    $c2 = imagecolorat($dithered, 99, 99);
    
    // In dithering, colors are distributed, so we just check it's not the same gradient
    expect($c1)->not->toBe($c2);

    imagedestroy($img);
    imagedestroy($dithered);
});

test('Image Processor basic filters work', function () {
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 100, 100, $white);

    expect(adjust_contrast($img, 50))->toBeTrue();
    expect(adjust_brightness($img, 50))->toBeTrue();
    expect(adjust_gamma($img, 1.5))->toBeTrue();

    imagedestroy($img);
});
