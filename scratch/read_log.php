<?php
$logPath = 'C:/Users/Dupli/.gemini/antigravity-ide/brain/f7c3348d-438e-42df-a04e-9ba2738728d3/.system_generated/tasks/task-776.log';
if (!file_exists($logPath)) {
    echo "Log file does not exist\n";
    exit(1);
}
$lines = file($logPath);
foreach ($lines as $line) {
    if (strlen($line) < 500) {
        echo $line;
    } else {
        echo "[TRUNCATED LINE of length " . strlen($line) . "]\n";
        // Let's print sections of the line
        if (preg_match('/error/i', $line)) {
            echo "Contains 'error':\n";
            preg_match_all('/.{0,100}error.{0,100}/i', $line, $matches);
            foreach ($matches[0] as $match) {
                echo "  ... " . trim($match) . " ...\n";
            }
        }
        if (preg_match('/failed/i', $line)) {
            echo "Contains 'failed':\n";
            preg_match_all('/.{0,100}failed.{0,100}/i', $line, $matches);
            foreach ($matches[0] as $match) {
                echo "  ... " . trim($match) . " ...\n";
            }
        }
        if (preg_match('/invalid/i', $line)) {
            echo "Contains 'invalid':\n";
            preg_match_all('/.{0,100}invalid.{0,100}/i', $line, $matches);
            foreach ($matches[0] as $match) {
                echo "  ... " . trim($match) . " ...\n";
            }
        }
        // Also just print the first 1000 chars of the long line
        echo "Start of long line: " . substr($line, 0, 1000) . "\n";
    }
}
