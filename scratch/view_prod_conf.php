<?php
$confPath = 'C:\\Program Files\\Duplicator Alpha\\resources\\app\\app\\controler\\conf.php';
if (!file_exists($confPath)) {
    echo "Production config NOT found at: $confPath\n";
    exit;
}

echo "=== Production conf.php ===\n";
echo file_get_contents($confPath);
