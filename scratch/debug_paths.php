<?php
require_once __DIR__ . '/../app/controler/functions/binary_utilities.php';
echo "Platform: " . get_binary_platform_dir() . "\n";
echo "GS Path: " . get_ghostscript_path() . "\n";
echo "Magick Path: " . get_magick_path() . "\n";
if (file_exists(get_ghostscript_path())) {
    echo "GS File exists!\n";
} else {
    echo "GS File NOT found!\n";
}
?>
