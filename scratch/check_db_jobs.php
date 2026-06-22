<?php
$path = 'C:\\Program Files\\Duplicator Alpha\\resources\\app\\app\\duplinew.sqlite';

try {
    if (!file_exists($path)) {
        echo "Database NOT found at: $path\n";
        exit;
    }
    
    echo "=== Database found at: $path ===\n";
    $db = new PDO("sqlite:$path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== print_jobs from today ===\n";
    $stmt = $db->query("SELECT id, job_id, document, printer_name, status, total_pages, paper_size, created_at FROM print_jobs WHERE created_at LIKE '2026-05-24%' ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    echo "\n=== recorded_print_jobs from today ===\n";
    $stmt = $db->query("SELECT job_id, printer_name, print_job_id, recorded_at FROM recorded_print_jobs WHERE recorded_at LIKE '2026-05-24%' ORDER BY recorded_at ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
