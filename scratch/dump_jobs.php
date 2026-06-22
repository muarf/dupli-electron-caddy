<?php
require_once __DIR__ . '/../app/controler/conf.php';
require_once __DIR__ . '/../app/controler/functions/database.php';
$db = create_database_manager();
$jobs = $db->select('SELECT id, job_id, document, timestamp, created_at FROM print_jobs ORDER BY created_at DESC LIMIT 5');
echo json_encode($jobs, JSON_PRETTY_PRINT) . "\n";
