<?php
$path = 'c:\\Users\\Dupli\\AppData\\Roaming\\Duplicator Alpha\\duplinew.sqlite';

try {
    $db = new PDO("sqlite:$path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->query("SELECT * FROM print_jobs WHERE id = 6966");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);
    
    echo "\n=== checking if 6966 exists in recorded_print_jobs ===\n";
    $stmt = $db->prepare("SELECT * FROM recorded_print_jobs WHERE print_job_id = ? OR job_id = ?");
    $stmt->execute([6966, 3]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($r);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
