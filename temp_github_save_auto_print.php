<?php
/**
 * API pour enregistrer automatiquement une impression
 * Reçoit les données du moniteur d'impression + pseudo utilisateur
 */

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/tirage.php'; // Pour insert_photocop
require_once __DIR__ . '/../models/tirage_multimachines.php'; // Pour les fonctions de calcul de prix
require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php'; // Pour generateTirageGlobalId

// POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validation des champs obligatoires
    $required = ['printerName', 'pages', 'contact', 'document'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Champ manquant: $field");
        }
    }

    $printerName = $input['printerName'];
    $contact = $input['contact'];
    $document = $input['document'];
    $pages = intval($input['pages']); // Pages physiques (feuilles si recto-verso ?)
    // Note: Le moniteur envoie 'pages' comme nombre de faces logiques imprimées habituellement.
    // Vérifier ce que le moniteur envoie. Supposons 'total_pages' (logique) et 'pages_printed' (physique)
    // Le mapping table utilise: pages = total_pages (logique).

    $copies = intval($input['copies'] ?? 1);
    $total_pages = intval($input['total_pages'] ?? ($pages * $copies));
    $duplex = isset($input['duplex']) && ($input['duplex'] === true || $input['duplex'] === 1 || $input['duplex'] === '1');
    $color = isset($input['color_mode']) && (stripos($input['color_mode'], 'color') !== false);
    $fill_rate = floatval($input['fill_rate'] ?? 0.5);
    // Normalisation : si > 1, on suppose que c'est un pourcentage (ex: 36.5), on convertit en ratio (0.365)
    if ($fill_rate > 1.0) {
        $fill_rate = $fill_rate / 100.0;
    }

    $db = create_database_manager();

    // 1. Trouver le mapping
    $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);

    if (!$mapping) {
        // Pas de mapping, on arrête là
        echo json_encode(['success' => false, 'error' => "Imprimante '$printerName' non reconnue. Veuillez la configurer dans l'admin."]);
        exit;
    }

    $machine_type = $mapping['machine_type'];
    $machine_id = $mapping['machine_id'];
    $con_pdo = pdo_connect(); // Connexion brute pour les fonctions legacy si besoin

    $date = time();
    $paye = "non"; // Par défaut
    $cb = 0;
    $mot = "Auto: " . $document;

    // Mode simulation
    $simulate = isset($input['simulate']) && $input['simulate'] === true;

    $price = 0;
    $message = "";
    $details = [];

    $con_pdo->beginTransaction();

    if ($machine_type === 'photocop') {
        // --- PHOTOCOPIEUR ---
        $machine = $con_pdo->query("SELECT marque, type_encre FROM photocopieurs WHERE id = " . intval($machine_id))->fetch(PDO::FETCH_ASSOC);
        if (!$machine)
            throw new Exception("Photocopieur ID $machine_id introuvable");

        $marque = $machine['marque'];
        $machine_type_detected = $machine['type_encre']; // 'encre' ou 'toner'

        // Récupérer les prix
        $machine_prices = getMachinePrices($con_pdo, $marque);
        $prix_data = get_price();
        $prix_papier_a4 = $prix_data['papier']['A4'] ?? 0.01;
        $prix_papier_a3 = $prix_data['papier']['A3'] ?? 0.02;

        // Calcul du coût unitaire par page (encre + maintenance)
        $cost_per_page = calculatePageCost($marque, $machine_type_detected, $machine_prices, $color, $duplex, $fill_rate);

        // Déterminer taille papier (approximatif si pas donné, supposons A4 par défaut)
        // Si le document contient "A3", on assume A3.
        $is_A3 = (stripos($document, 'A3') !== false) || (isset($input['paper_size']) && stripos($input['paper_size'], 'A3') !== false);
        $taille = $is_A3 ? 'A3' : 'A4';

        if ($taille === 'A4') {
            $cost_per_page = $cost_per_page / 2;
            $prix_papier = $prix_papier_a4;
        } else {
            $prix_papier = $prix_papier_a3;
        }

        // Calcul total
        // Note: insert_photocop attend le nombre de feuilles total (nb_f_total), et le prix calculé si on veut forcer ?
        // Non, insert_photocop prend $prix.

        // Calcul du nombre de feuilles (physique) pour le papier
        // Si duplex, nb_feuilles = ceil(total_pages / 2)
        $nb_feuilles_total = $duplex ? ceil($total_pages / 2) : $total_pages;

        // Coût papier
        $cout_papier = $nb_feuilles_total * $prix_papier;

        // Coût encre (pages logiques, mais si duplex = x2 encre ? NON, calculatePageCost renvoie le prix pour UNE FACE imprimée)
        // Donc on multiplie par le nombre total de faces (total_pages)
        $cout_encre = $total_pages * $cost_per_page;

        $price = round($cout_papier + $cout_encre, 2);

        if (!$simulate) {
            // Générer tirage_global_id
            $tirage_global_id = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $marque);

            // Insertion
            insert_photocop(
                'photocopieur',
                $marque,
                $contact,
                $nb_feuilles_total, // Note: le champ s'appelle 'quantite' dans la table, souvent utilisé pour feuilles.
                $duplex ? 'oui' : 'non',
                $price,
                $paye,
                $cb,
                $mot,
                $date,
                $con_pdo,
                $tirage_global_id
            );

            $message = "Enregistré sur $marque : $total_pages pages ($taille) -> $price €";
        } else {
            $message = "Simulation : $total_pages pages ($taille)";
        }

        $details = [
            'type' => 'photocop',
            'machine' => $marque,
            'machine_id' => $machine_id,
            'pages' => $total_pages,
            'taille' => $taille,
            'copies' => $copies,
            'cout_papier' => $cout_papier,
            'cout_encre' => $cout_encre,
            'price' => $price,
            'duplex' => $duplex,
            'color' => $color,
            'fill_rate_percent' => number_format($fill_rate * 100, 2, ',', ''),
            'nb_feuilles' => $nb_feuilles_total
        ];

    } else if ($machine_type === 'dupli') {
        // --- DUPLICOPIEUR ---
        $machine = $con_pdo->query("SELECT marque, modele FROM duplicopieurs WHERE id = " . intval($machine_id))->fetch(PDO::FETCH_ASSOC);
        if (!$machine)
            throw new Exception("Duplicopieur ID $machine_id introuvable");

        $nom_machine = $machine['marque'] . ' ' . $machine['modele'];
        if ($machine['marque'] === $machine['modele'])
            $nom_machine = $machine['marque'];

        // Hypothèse Auto: 1 Master, N Passages (= total_pages)
        // C'est le cas le plus courant pour un nouveau job d'impression.
        // Si c'est "copies", le driver d'impression envoie 1 job avec N copies.
        $nb_masters = 1;
        $nb_passages = $total_pages;

        // Récupérer compteurs actuels
        $query_counters = $con_pdo->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
        $query_counters->execute([$nom_machine]);
        $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);

        $master_av = $last_counters ? ceil($last_counters['master_ap']) : 0;
        $passage_av = $last_counters ? ceil($last_counters['passage_ap']) : 0;

        $master_ap = $master_av + $nb_masters;
        $passage_ap = $passage_av + $nb_passages;

        // Prix
        $prix_data = get_price();
        $machine_key = 'dupli_' . $machine_id;

        $prix_master = $prix_data[$machine_key]['master']['unite'] ?? 0;
        $prix_passage = $prix_data[$machine_key]['tambour_noir']['unite'] ?? 0; // Défaut noir

        // Si couleur détectée (peu probable sur dupli via driver simple, mais bon)
        // On garde noir par défaut pour auto.

        // Taille (A4 ou A3)
        $is_A3 = (stripos($document, 'A3') !== false) || (isset($input['paper_size']) && stripos($input['paper_size'], 'A3') !== false);
        $taille_papier = $is_A3 ? 'A3' : 'A4';

        $prix_papier = ($taille_papier === 'A3') ? ($prix_data['papier']['A3'] ?? 0) : ($prix_data['papier']['A4'] ?? 0);

        // Ajustement A4 (A3 / 2)
        if ($taille_papier === 'A4') {
            $prix_master = $prix_master / 2;
            $prix_passage = $prix_passage / 2;
        }

        $nb_f = $nb_passages; // Feuilles = Passages
        if ($duplex)
            $nb_f = $nb_f / 2;

        $cout_masters = $nb_masters * $prix_master;
        $cout_passages = $nb_passages * $prix_passage;
        $cout_papier = $nb_f * $prix_papier;

        $price = round($cout_masters + $cout_passages + $cout_papier, 2);

        if (!$simulate) {
            // Tirage ID
            $tirage_global_id = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $nom_machine);

            // Insert
            $sql = 'INSERT INTO dupli (type, contact, master_av, master_ap, passage_av, passage_ap, rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id, tambour, tirage_global_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $con_pdo->prepare($sql);
            $stmt->execute([
                "tirage",
                $contact,
                $master_av,
                $master_ap,
                $passage_av,
                $passage_ap,
                $duplex ? 'oui' : 'non',
                $price,
                $paye,
                $cb,
                $mot,
                $date,
                $nom_machine,
                $machine_id,
                'tambour_noir', // Défaut
                $tirage_global_id
            ]);

            $message = "Enregistré sur $nom_machine : 1 Master, $nb_passages Passages -> $price €";
        } else {
            $message = "Simulation : $nb_masters M, $nb_passages P";
        }

        global $conf; // Ensure we access global conf

        // Extract available drums
        $tambours = [];
        if (isset($prix_data[$machine_key])) {
            foreach ($prix_data[$machine_key] as $type => $data) {
                if ($type !== 'master') {
                     // Clean up name for display? Usually type is 'tambour_noir', 'tambour_bleu', etc.
                     // Or just 'noire', 'bleue' depending on how it's stored.
                     // tirage_multimachines uses $type directly as value.
                     $tambours[] = [
                         'value' => $type,
                         'label' => ucfirst(str_replace('tambour_', '', $type)), // Simple label formatting
                         'price' => $data['unite'] ?? 0
                     ];
                }
            }
        }

        $details = [
            'type' => 'duplicopieur',
            'machine' => $nom_machine,
            'machine_id' => $machine_id,
            'nb_masters' => $nb_masters,
            'nb_passages' => $nb_passages,
            'master_av' => $master_av,
            'master_ap' => $master_ap,
            'passage_av' => $passage_av,
            'passage_ap' => $passage_ap,
            'taille' => $taille_papier,
            'duplex' => $duplex,
            'cout_masters' => $cout_masters,
            'cout_passages' => $cout_passages,
            'cout_papier' => $cout_papier,
            'price' => $price,
            'nb_feuilles' => $nb_f,
            'tambours' => $tambours, // Add drums
            'debug_db_path' => $conf['db_path'] ?? 'unknown',
            'debug_nom_machine' => $nom_machine
        ];
    }

    $con_pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => $details
    ]);

} catch (Exception $e) {
    if (isset($con_pdo) && $con_pdo->inTransaction()) {
        $con_pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>