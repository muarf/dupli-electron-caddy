<?php
$logPath = 'C:\\Program Files\\Duplicator Alpha\\resources\\app\\app\\api\\debug_log.txt';
if (!file_exists($logPath)) {
    echo "Production debug log NOT found at: $logPath\n";
    exit;
}

echo "=== Last 50 lines of production debug_log.txt ===\n";
$lines = file($logPath);
$lastLines = array_slice($lines, -50);
foreach ($lastLines as $line) {
    echo $line;
}
