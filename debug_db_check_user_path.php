<?php
// Utiliser le chemin fourni par l'utilisateur
$db_path = 'C:\Users\Dupli\AppData\Roaming\dupli-electron\duplinew.sqlite';

if (!file_exists($db_path)) {
    echo "ERREUR: Le fichier de base de données n'existe pas à l'emplacement : $db_path\n";
    exit;
}

echo "Connexion à la base de données : $db_path\n";

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier tables
    echo "\n--- Vérification des tables ---\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('print_jobs', 'recorded_print_jobs')")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables trouvées : " . implode(", ", $tables) . "\n";
    
    if (in_array('print_jobs', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM print_jobs")->fetchColumn();
        echo "Nombre d'entrées dans print_jobs : $count\n";
        
        echo "\n--- 10 Dernières impressions (print_jobs) ---\n";
        $stmt = $pdo->query("SELECT id, printer_name, document, status, timestamp FROM print_jobs ORDER BY id DESC LIMIT 10");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($jobs as $job) {
             $date = date('d/m/Y H:i:s', $job['timestamp'] / 1000);
             echo "[{$job['id']}] $date - {$job['document']} ({$job['printer_name']}) - Status: {$job['status']}\n";
        }
    } else {
        echo "TABLE print_jobs MANQUANTE !\n";
    }

    if (in_array('recorded_print_jobs', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM recorded_print_jobs")->fetchColumn();
        echo "\nNombre d'entrées dans recorded_print_jobs : $count\n";
        
        echo "\n--- 10 Derniers enregistrements (recorded_print_jobs) ---\n";
        $stmt = $pdo->query("SELECT * FROM recorded_print_jobs ORDER BY id DESC LIMIT 10");
        $recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recs as $rec) {
             echo "[{$rec['id']}] Job ID: {$rec['job_id']} - Printer: {$rec['printer_name']}\n";
        }
    } else {
        echo "TABLE recorded_print_jobs MANQUANTE !\n";
    }

} catch (PDOException $e) {
    echo "Erreur PDO : " . $e->getMessage() . "\n";
}
