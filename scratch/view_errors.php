<?php
$logPath = 'C:\\Users\\Dupli\\AppData\\Local\\Temp\\duplicator_errors.log';
if (!file_exists($logPath)) {
    echo "Log file NOT found at: $logPath\n";
    exit;
}

echo "=== Last 50 lines of duplicator_errors.log ===\n";
$lines = file($logPath);
$lastLines = array_slice($lines, -50);
foreach ($lastLines as $line) {
    echo $line;
}
