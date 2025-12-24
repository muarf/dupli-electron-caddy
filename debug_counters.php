<?php
// Try to locate DB
$candidates = [
    'C:\Users\Dupli\AppData\Roaming\Duplicator\duplinew.sqlite',
    'C:\Users\Dupli\AppData\Roaming\dupli-electron\duplinew.sqlite',
    __DIR__ . '/app/duplinew.sqlite'
];

$dbPath = null;
foreach ($candidates as $p) {
    if (file_exists($p)) {
        $dbPath = $p;
        break;
    }
}

if (!$dbPath) {
    die("Could not find database in candidates.\n");
}

echo "Using DB: $dbPath\n";
putenv("DUPLICATOR_DB_PATH=$dbPath");

require_once __DIR__ . '/app/controler/functions/database.php';
$pdo = pdo_connect();

echo "--- Duplicopieurs ---\n";
$stmt = $pdo->query("SELECT * FROM duplicopieurs");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nom = $row['marque'] . ' ' . $row['modele'];
    if ($row['marque'] === $row['modele']) $nom = $row['marque'];
    echo "ID: {$row['id']} | Marque: {$row['marque']} | Modele: {$row['modele']} | Calculated Name: '$nom'\n";
    
    // Check dupli entries for this specific calculated name
    $stmt2 = $pdo->prepare("SELECT id, master_ap, passage_ap, date FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1");
    $stmt2->execute([$nom]);
    $last = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($last) {
        echo "   -> Last History: ID {$last['id']} (Date: {$last['date']}) | Master AP: {$last['master_ap']} | Passage AP: {$last['passage_ap']}\n";
    } else {
        echo "   -> NO HISTORY FOUND for '$nom'\n";
    }
}

echo "\n--- Recent Dupli Entries (All) ---\n";
$stmt = $pdo->query("SELECT id, nom_machine, master_ap, passage_ap, date FROM dupli ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Machine: '{$row['nom_machine']}' | Master AP: {$row['master_ap']} | Passage AP: {$row['passage_ap']} | Date: {$row['date']}\n";
}
