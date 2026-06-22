<?php
function findFile($dir, $filename) {
    if (!is_dir($dir)) return null;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if ($file->getFilename() === $filename) {
            return $file->getPathname();
        }
    }
    return null;
}

$appDataLocal = 'C:\\Users\\Dupli\\AppData\\Local';
$appDataRoaming = 'C:\\Users\\Dupli\\AppData\\Roaming';

echo "Searching AppData\\Local...\n";
$found = findFile($appDataLocal, 'native_debug.log');
if ($found) {
    echo "Found at: $found\n";
} else {
    echo "Not found in AppData\\Local\n";
}

echo "Searching AppData\\Roaming...\n";
$found = findFile($appDataRoaming, 'native_debug.log');
if ($found) {
    echo "Found at: $found\n";
} else {
    echo "Not found in AppData\\Roaming\n";
}
