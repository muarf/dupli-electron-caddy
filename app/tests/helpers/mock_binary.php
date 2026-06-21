<?php
// Mock script in PHP
if (!isset($argc) || php_sapi_name() !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

echo "Mock binary called with args: " . implode(' ', $argv) . "\n";

foreach ($argv as $arg) {
    if (strpos($arg, '-sOutputFile=') === 0) {
        $out = substr($arg, 13);
        $out = str_replace('"', '', $out);
        if (strpos($out, '%d') !== false) {
            $realOut = str_replace('%d', '1', $out);
            @touch($realOut);
            echo "Mock: Touched $realOut\n";
        } else {
            @touch($out);
            echo "Mock: Touched $out\n";
        }
    }
}

// Special case for ImageMagick which doesn't use -sOutputFile
if (in_array('-density', $argv) || in_array('density', $argv)) {
    foreach ($argv as $arg) {
        if (substr($arg, -4) === '.png') {
            @touch($arg);
            echo "Mock (IM): Touched $arg\n";
            break;
        }
    }
}
exit(0);
