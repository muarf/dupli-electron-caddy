<?php
$db_path = 'C:\Users\Dupli\AppData\Roaming\dupli-electron\duplinew.sqlite';

if (!file_exists($db_path)) {
    echo "DB not found\n";
    exit;
}

try {
    $pdo = new PDO("sqlite:$db_path");
    $triggers = $pdo->query("SELECT name, tbl_name, sql FROM sqlite_master WHERE type = 'trigger'")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($triggers)) {
        echo "No triggers found.\n";
    } else {
        foreach ($triggers as $t) {
            echo "Trigger: {$t['name']} ON {$t['tbl_name']}\n";
            echo "SQL: {$t['sql']}\n\n";
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
