<?php
$db_path = 'C:\\Users\\Dupli\\AppData\\Roaming\\Duplicator Alpha\\duplinew.sqlite';
try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM recorded_print_jobs");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total recorded print jobs: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo "  job_id: {$row['job_id']} | printer: {$row['printer_name']} | recorded_at: {$row['recorded_at']} | print_job_id: {$row['print_job_id']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
