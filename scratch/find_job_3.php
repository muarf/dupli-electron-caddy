<?php
$path = 'c:\\Users\\Dupli\\AppData\\Roaming\\Duplicator Alpha\\duplinew.sqlite';

try {
    $db = new PDO("sqlite:$path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Searching for job_id = 3 or document containing 'Cuerpo' ===\n";
    $stmt = $db->query("SELECT id, job_id, document, printer_name, status, total_pages, created_at FROM print_jobs WHERE job_id = 3 OR document LIKE '%Cuerpo%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
