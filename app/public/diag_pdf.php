<?php
require_once __DIR__ . '/../controler/functions/paths.php';
require_once __DIR__ . '/../controler/functions/utilities.php';

header('Content-Type: text/plain');
echo "getDataDir(): " . getDataDir() . "\n";
echo "getTmpDir(): " . getTmpDir() . "\n";
echo "is_dir(getTmpDir()): " . (is_dir(getTmpDir()) ? 'YES' : 'NO') . "\n";
echo "is_writable(getTmpDir()): " . (is_writable(getTmpDir()) ? 'YES' : 'NO') . "\n";
