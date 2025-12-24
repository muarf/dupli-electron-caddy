<?php
require_once __DIR__ . '/app/controler/functions/database.php';

echo "=== DIAGNOSTIC IMPRESSIONS ===\n\n";

try {
    $db = pdo_connect();
    
    // 1. print_jobs
    echo "\u{1f4cb} print_jobs:\n";
    $jobs = $db->query("SELECT id, job_id, document, created_at FROM print_jobs ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jobs as $j) echo "\u2713 {$j['job_id']} | {$j['document']} | {$j['created_at']}\n";
    
    //  2. photocop
    echo "\n\u{1f4cb} photocop:\n";
    $photos = $db->query("SELECT id, nom, contact, date, session_id FROM photocop ORDER BY date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($photos as $p) echo "\u2713 {$p['nom']} | {$p['contact']} | Session: " . ($p['session_id'] ?? 'NULL') . " | {$p['date']}\n";
    
    // 3. sessions
    echo "\n\u{1f4cb} Sessions actives:\n";
    $sessions = $db->query("SELECT * FROM print_sessions WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sessions as $s) echo "\u2713 ID:{$s['id']} | {$s['contact']} | Jobs:{$s['job_count']} | {$s['opened_at']}\n";
    
} catch (Exception $e) {
    echo "\u274c ERREUR: {$e->getMessage()}\n";
}
