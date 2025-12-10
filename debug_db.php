<?php
require_once 'app/controler/conf.php';
require_once 'app/controler/functions/database.php';
$db = create_database_manager();
$jobs = $db->select("SELECT * FROM print_jobs ORDER BY id DESC LIMIT 1");
print_r($jobs);
