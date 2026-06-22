<?php
$logPath = 'C:\\Users\\Dupli\\AppData\\Local\\dupli-electron-caddy\\logs\\native_debug.log';
if (!file_exists($logPath)) {
    echo "Log file NOT found at: $logPath\n";
    exit;
}

echo "=== Last 50 lines of native_debug.log ===\n";
$lines = file($logPath);
$lastLines = array_slice($lines, -50);
foreach ($lastLines as $line) {
    echo $line;
}
