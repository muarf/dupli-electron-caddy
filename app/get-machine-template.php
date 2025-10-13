<?php
require_once 'controler/functions/database.php';
require_once 'models/tirage_multimachines.php';

// Récupérer l'index de la machine
$index = isset($_GET['index']) ? (int)$_GET['index'] : 1;

// Charger les données nécessaires (duplicopieurs et photocopieurs)
$con = pdo_connect();

// Récupérer les duplicopieurs
$duplicopieurs = [];
$query = $con->prepare("SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele");
$query->execute();
$duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les photocopieurs
$photocopiers = [];
$query = $con->prepare("SELECT * FROM photocopieurs WHERE actif = 1 ORDER BY marque");
$query->execute();
$photocopiers = $query->fetchAll(PDO::FETCH_OBJ);

// Pas de duplicopieur pré-sélectionné pour les nouvelles machines
$duplicopieur_selectionne = null;

// Générer le HTML de la machine
$html = generateMachineHTML($index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers);

// Retourner en JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'html' => $html]);
?>