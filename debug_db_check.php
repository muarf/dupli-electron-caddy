<?php
require_once __DIR__ . '/app/controler/functions/database.php';

try {
    $db = pdo_connect();
    
    // Check if table exists
    $tableExists = false;
    if (isset($GLOBALS['conf']['db_type']) && $GLOBALS['conf']['db_type'] === 'sqlite') {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='print_jobs'");
        $tableExists = $stmt->fetch() !== false;
    } else {
        $stmt = $db->query("SHOW TABLES LIKE 'print_jobs'");
        $tableExists = $stmt->rowCount() > 0;
    }
    
    if (!$tableExists) {
        echo "Table 'print_jobs' does NOT exist.\n";
        exit;
    }
    
    // Count total rows
    $count = $db->query("SELECT COUNT(*) FROM print_jobs")->fetchColumn();
    echo "Total rows in print_jobs: $count\n\n";
    
    // Get last 10 entries
    $stmt = $db->query("SELECT id, printer_name, document, status, timestamp, created_at FROM print_jobs ORDER BY id DESC LIMIT 10");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($jobs)) {
        echo "No jobs found in print_jobs table.\n";
    } else {
        echo "Last 10 entries:\n";
        echo str_pad("ID", 6) . str_pad("PRINTER", 20) . str_pad("STATUS", 15) . str_pad("TIME", 20) . "DOCUMENT\n";
        echo str_repeat("-", 80) . "\n";
        
        foreach ($jobs as $job) {
            $time = date('Y-m-d H:i:s', $job['timestamp'] / 1000); // Assuming JS timestamp
            if ($job['timestamp'] < 2000000000) $time = date('Y-m-d H:i:s', $job['timestamp']); // Unix timestamp
            
            echo str_pad($job['id'], 6) . 
                 str_pad(substr($job['printer_name'], 0, 18), 20) . 
                 str_pad(substr($job['status'], 0, 13), 15) . 
                 str_pad($time, 20) . 
                 substr($job['document'], 0, 30) . "\n";
        }
    }

    echo "\n\nChecking recorded_print_jobs table:\n";
    // Check recorded_print_jobs
    $stmt = $db->query("SELECT count(*) FROM recorded_print_jobs");
    $recordedCount = $stmt->fetchColumn();
    echo "Total rows in recorded_print_jobs: $recordedCount\n";
    
     // Get last 5 recorded entries
    $stmt = $db->query("SELECT * FROM recorded_print_jobs ORDER BY id DESC LIMIT 5");
    $recordedParams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($recordedParams);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
