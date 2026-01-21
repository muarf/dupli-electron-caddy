<?php
// Utiliser le chemin local trouvé
$db_path = __DIR__ . '/app/duplinew.sqlite';

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
        $stmt = $pdo->query("SELECT id, printer_name, document, status, timestamp, CASE WHEN status='Completed' THEN 1 ELSE 0 END as completed, (SELECT COUNT(*) FROM recorded_print_jobs WHERE job_id = print_jobs.job_id) as recorded FROM print_jobs ORDER BY id DESC LIMIT 10");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($jobs as $job) {
             $date = date('d/m/Y H:i:s', $job['timestamp'] / 1000);
             echo "[{$job['id']}] $date - {$job['document']} ({$job['printer_name']}) - Recorded: {$job['recorded']}\n";
        }
    } else {
        echo "TABLE print_jobs MANQUANTE !\n";
    }

    if (in_array('recorded_print_jobs', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM recorded_print_jobs")->fetchColumn();
        echo "\nNombre d'entrées dans recorded_print_jobs : $count\n";
    } else {
        echo "TABLE recorded_print_jobs MANQUANTE !\n";
    }

} catch (PDOException $e) {
    echo "Erreur PDO : " . $e->getMessage() . "\n";
}
