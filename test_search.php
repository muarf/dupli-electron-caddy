<?php
require_once __DIR__ . '/app/controler/functions/database.php';

try {
    $db = pdo_connect();
    echo "SQLite Version: " . $db->query("SELECT sqlite_version()")->fetchColumn() . "\n";
    
    // Check FTS5
    $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bibliotheque_files_fts'");
    $exists = $check->fetch() !== false;
    echo "FTS5 Table 'bibliotheque_files_fts' exists: " . ($exists ? "YES" : "NO") . "\n";
    
    if ($exists) {
        $count = $db->query("SELECT count(*) FROM bibliotheque_files_fts")->fetchColumn();
        echo "FTS5 Table row count: " . $count . "\n";
        
        $search = "France*";
        $sql = "SELECT b.id, b.filename, bibliotheque_files_fts.rank 
                FROM bibliotheque_files b 
                JOIN bibliotheque_files_fts ON bibliotheque_files_fts.rowid = b.id 
                WHERE bibliotheque_files_fts MATCH ? 
                LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute([$search]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Search for '$search' results count: " . count($results) . "\n";
        foreach ($results as $r) {
            echo " - " . $r['filename'] . " (Rank: " . $r['rank'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
