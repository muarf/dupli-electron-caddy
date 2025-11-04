<?php
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../models/tirage_multimachines.php';

// Récupérer l'index de la machine
$index = isset($_GET['index']) ? (int)$_GET['index'] : 1;

// Charger les données nécessaires (duplicopieurs et photocopieurs)
$con = pdo_connect();

// Récupérer les duplicopieurs
$duplicopieurs = [];
$query = $con->prepare("SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele");
$query->execute();
$duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);

// Parser les tambours pour chaque duplicopieur
foreach($duplicopieurs as $index_dup => $dup) {
    $tambours = [];
    if (!empty($dup['tambours'])) {
        try {
            $tambours = json_decode($dup['tambours'], true);
            if (!is_array($tambours)) {
                $tambours = ['tambour_noir']; // Fallback
            }
        } catch (Exception $e) {
            $tambours = ['tambour_noir']; // Fallback
        }
    } else {
        $tambours = ['tambour_noir']; // Fallback pour les anciens duplicopieurs
    }
    $duplicopieurs[$index_dup]['tambours_parsed'] = $tambours;
}

// Récupérer les photocopieurs (exclure les marques de duplicopieurs)
$duplicopieurs_names = [];
foreach ($duplicopieurs as $dup) {
    $machine_name = $dup['marque'] . ' ' . $dup['modele'];
    if ($dup['marque'] === $dup['modele']) {
        $machine_name = $dup['marque'];
    }
    $duplicopieurs_names[] = $machine_name;
}

// Debug: log les noms de duplicopieurs à exclure
file_put_contents('/tmp/debug_get_machine.txt', "DEBUG get-machine-template.php - duplicopieurs_names: " . json_encode($duplicopieurs_names) . "\n", FILE_APPEND);

$photocopiers = [];
if (!empty($duplicopieurs_names)) {
    $placeholders = str_repeat('?,', count($duplicopieurs_names) - 1) . '?';
    $query = $con->prepare("SELECT * FROM photocopieurs WHERE marque NOT IN ($placeholders) AND actif = 1 ORDER BY marque");
    $query->execute($duplicopieurs_names);
    $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
} else {
    $query = $con->query('SELECT * FROM photocopieurs WHERE actif = 1 ORDER BY marque');
    $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
}

// Debug: log les photocopieurs trouvés
file_put_contents('/tmp/debug_get_machine.txt', "DEBUG get-machine-template.php - photocopiers trouvés: " . count($photocopiers) . "\n", FILE_APPEND);
foreach ($photocopiers as $photo) {
    file_put_contents('/tmp/debug_get_machine.txt', "DEBUG get-machine-template.php - photocopieur: " . $photo->marque . "\n", FILE_APPEND);
}

// Si un seul duplicopieur, le sélectionner automatiquement
$duplicopieur_selectionne = null;
if (count($duplicopieurs) == 1) {
    $duplicopieur_selectionne = $duplicopieurs[0];
}

// Générer le HTML de la machine
$html = generateMachineHTML($index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers);

// Retourner en JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'html' => $html]);
?>