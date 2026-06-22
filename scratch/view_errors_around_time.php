<?php
$logPath = 'C:\\Users\\Dupli\\AppData\\Local\\Temp\\duplicator_errors.log';
if (!file_exists($logPath)) {
    echo "Log file NOT found at: $logPath\n";
    exit;
}

echo "=== Logs matching '15:05' or '15:06' or 'Job 3' or 'Job 2' ===\n";
$lines = file($logPath);
foreach ($lines as $line) {
    if (strpos($line, '15:05') !== false || 
        strpos($line, '15:06') !== false || 
        strpos($line, 'Job 3') !== false || 
        strpos($line, 'Job 2') !== false || 
        strpos($line, 'Impression') !== false) {
        echo $line;
    }
}
