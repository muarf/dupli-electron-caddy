<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/functions/database.php';

$db = create_database_manager();

try {
    $jobIdWindows = "7";
    $printerName = "RISO ComColor FW5230";
    
    echo "Finding job...\n";
    $existingJob = $db->selectOne(
        "SELECT id FROM print_jobs WHERE job_id = ? AND printer_name = ? ORDER BY created_at DESC LIMIT 1",
        [$jobIdWindows, $printerName]
    );
    
    if ($existingJob) {
        $targetId = $existingJob['id'];
        echo "Found job ID: $targetId\n";
        
        echo "Updating job...\n";
        $sql = "UPDATE print_jobs SET thumbnail_url = ?, fill_rate = ?, color_mode = ?, total_pages = ? WHERE id = ?";
        $params = ["/thumbnails/7/page_0.png", 5.0, "Color", 12, $targetId];
        $affected = $db->execute($sql, $params);
        echo "Done, affected rows: $affected\n";
    } else {
        echo "Job not found in database!\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
