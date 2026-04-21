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
    $jsonInput = file_get_contents('php://input');
    if (empty($jsonInput) && PHP_SAPI === 'cli') {
        $jsonInput = file_get_contents('php://stdin');
    }
    $input = json_decode($jsonInput, true);

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

    // ID du job original (pour suppression après traitement)
    $original_job_id = isset($input['job_id']) ? $input['job_id'] : null;
    $internal_id = isset($input['internal_id']) ? intval($input['internal_id']) : null;
    $session_id = isset($input['session_id']) ? intval($input['session_id']) : null;

    // Log the full payload for debugging
    $log_entry = "[" . date('Y-m-d H:i:s') . "] [PAYLOAD] " . json_encode($input) . "\n";
    file_put_contents(__DIR__ . '/debug_log.txt', $log_entry, FILE_APPEND);

    $db = create_database_manager();

    // 0. Validation de la session si fournie
    if ($session_id) {
        $sessionExists = $db->selectOne("SELECT id, status FROM print_sessions WHERE id = ?", [$session_id]);

        if (!$sessionExists) {
            file_put_contents(__DIR__ . '/debug_log.txt', "[ERROR] Session ID $session_id INEXISTANTE.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => "La session (ID: $session_id) n'existe pas. Veuillez rafraîchir la page."]);
            exit;
        }

        if ($sessionExists['status'] !== 'active') {
            file_put_contents(__DIR__ . '/debug_log.txt', "[ERROR] Session ID $session_id FERMÉE.\n", FILE_APPEND);
            echo json_encode(['success' => false, 'error' => "La session (ID: $session_id) est fermée. Veuillez rafraîchir la page."]);
            exit;
        }
    }

    // 1. Trouver le mapping
    $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);

    if (!$mapping) {
        // Pas de mapping, on arrête là
        echo json_encode(['success' => false, 'error' => "Imprimante '$printerName' non reconnue. Veuillez la configurer dans l'admin."]);
        exit;
    }

    // DEBUG: Log keys to see potential case mismatch
    $keys = array_keys($mapping);
    file_put_contents(__DIR__ . '/debug_log.txt', "KEYS FOUND: " . implode(', ', $keys) . "\n", FILE_APPEND);

    // Robust retrieval: valid machine_type or Machine_Type or MACHINE_TYPE
    $machine_type = null;
    foreach ($mapping as $key => $val) {
        if (strtolower($key) === 'machine_type') {
            $machine_type = $val;
            break;
        }
    }
    // Fallback if null (should not happen if key exists)
    if ($machine_type === null && isset($mapping['machine_type']))
        $machine_type = $mapping['machine_type']; // standard
    if ($machine_type === null)
        $machine_type = ''; // Default to empty string

    $machine_id = null;
    foreach ($mapping as $key => $val) {
        if (strtolower($key) === 'machine_id') {
            $machine_id = $val;
            break;
        }
    }
    if ($machine_id === null && isset($mapping['machine_id']))
        $machine_id = $mapping['machine_id'];
    $con_pdo = pdo_connect(); // Connexion brute pour les fonctions legacy si besoin
    
    // DEBUG: Log connection result
    file_put_contents(__DIR__ . '/debug_log.txt', "DEBUG: pdo_connect called\n", FILE_APPEND);
    if (!$con_pdo) {
        file_put_contents(__DIR__ . '/debug_log.txt', "ERROR: pdo_connect returned null\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/debug_log.txt', "DEBUG: PDO connected OK\n", FILE_APPEND);
    }

    $date = time();
    $paye = "non"; // Par défaut
    $cb = 0;
    $mot = "Auto: " . $document;

    // Mode simulation
    $simulate = isset($input['simulate']) && $input['simulate'] === true;

    $price = 0;
    $message = "";
    $details = [];

    // DEBUG trace
    file_put_contents(__DIR__ . '/debug_log.txt', "DEBUG: Before beginTransaction\n", FILE_APPEND);
    $con_pdo->beginTransaction();
    file_put_contents(__DIR__ . '/debug_log.txt', "DEBUG: After beginTransaction OK\n", FILE_APPEND);

// SÉCURITÉ ANTI-DOUBLON SESSION : Vérifier si ce job unique n'a pas déjà été enregistré par un autre onglet
        if ($original_job_id && !$simulate) {
            $platform = $input['platform'] ?? 'windows';
            
            $maxHours = 2;
            $checkSql = "SELECT print_job_id, recorded_at FROM recorded_print_jobs 
                     WHERE (job_id = ? AND printer_name = ?)";
            $checkParams = [strval($original_job_id), $printerName];

            $check = $con_pdo->prepare($checkSql);
            $check->execute($checkParams);
            $existingJob = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($existingJob) {
                $recordedAt = strtotime($existingJob['recorded_at']);
                $hoursAgo = (time() - $recordedAt) / 3600;
                
                if ($hoursAgo > $maxHours) {
                    file_put_contents(__DIR__ . '/debug_log.txt', "[INFO] Ancien job ($hoursAgo h) supprimé pour ID recyclé\n", FILE_APPEND);
                    $con_pdo->exec("DELETE FROM recorded_print_jobs WHERE job_id = " . intval($original_job_id) . " AND printer_name = '" . $printerName . "'");
                } 
                elseif ($internal_id && !empty($existingJob['print_job_id'])) {
                    if ($existingJob['print_job_id'] == $internal_id) {
                        // Même job interne - On autorise la mise à jour (ex: passage de 5 à 30 pages)
                        file_put_contents(__DIR__ . '/debug_log.txt', "[INFO] Mise à jour métadonnées autorisée pour job interne $internal_id\n", FILE_APPEND);
                    }
                    else {
                        // print_job_id différent - c'est un nouveau job avec le même job_id Windows (recycled)
                        $con_pdo->exec("DELETE FROM recorded_print_jobs WHERE job_id = " . intval($original_job_id) . " AND printer_name = '" . $printerName . "'");
                    }
                }
                else {
                    $con_pdo->rollBack();
                    echo json_encode(['success' => true, 'message' => 'Job déjà enregistré.', 'already_recorded' => true]);
                    exit;
                }
            }

            // Marquer (ou confirmer le marquage)
            $mark = $con_pdo->prepare("INSERT OR IGNORE INTO recorded_print_jobs (job_id, printer_name, print_job_id) VALUES (?, ?, ?)");
            $mark->execute([strval($original_job_id), $printerName, $internal_id]);
        }

    file_put_contents(__DIR__ . '/debug_log.txt', "INIT: Printer='$printerName', Type='$machine_type'\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_log.txt', "HEX: " . bin2hex($machine_type) . "\n", FILE_APPEND);

    // CORRECTION AUTOMATIQUE COMCOLOR - RETIRÉE SUR DEMANDE
    // $has_comcolor = (stripos($printerName, 'ComColor') !== false);
    // // Si la base de données renvoie un type vide pour la ComColor, on force 'photocop'
    // $match_name = stripos($printerName, 'ComColor');
    // $is_empty = empty($machine_type);

    // file_put_contents(__DIR__ . '/debug_log.txt', "DEBUG FIX COND: NameMatch=" . var_export($match_name, true) . ", IsEmpty=" . var_export($is_empty, true) . "\n", FILE_APPEND);

    // if ($is_empty && $match_name !== false) {
    //     $machine_type = 'photocop';
    //     file_put_contents(__DIR__ . '/debug_log.txt', "FIX APPLIED: Forced 'photocop'\n", FILE_APPEND);
    // }

    // Strict check debug
    $is_photocop = ($machine_type === 'photocop');
    file_put_contents(__DIR__ . '/debug_log.txt', "Check 'photocop': " . ($is_photocop ? 'TRUE' : 'FALSE') . "\n", FILE_APPEND);

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

        // Calcul du multiplicateur de remplissage (Pivot 50% = 1.0)
        $fill_rate_multiplier = $color ? ($fill_rate / 0.5) : 1.0;

        // Calcul du coût unitaire par face imprimée (SANS diviser par 2 pour A4 ici)
        $cost_per_face = 0;
        $photocop_key = 'photocop_' . $machine_id;
        $prices = isset($prix_data[$photocop_key]) ? $prix_data[$photocop_key] : $machine_prices;

        if ($machine_type_detected === 'toner') {
            if ($color) {
                // Couleur : CMJ (avec taux) + Noir + Tambour + Dev (SANS taux)
                $cost_per_face += (($prices['cyan']['unite'] ?? 0) * $fill_rate_multiplier);
                $cost_per_face += (($prices['magenta']['unite'] ?? 0) * $fill_rate_multiplier);
                $cost_per_face += (($prices['jaune']['unite'] ?? 0) * $fill_rate_multiplier);
                
                // Fixes
                $cost_per_face += ($prices['noir']['unite'] ?? 0);
                $cost_per_face += ($prices['tambour']['unite'] ?? 0);
                $cost_per_face += ($prices['dev']['unite'] ?? 0);
            } else {
                // Noir et blanc : Noir + Tambour + Dev (Fixes)
                $cost_per_face += ($prices['noir']['unite'] ?? 0);
                $cost_per_face += ($prices['tambour']['unite'] ?? 0);
                $cost_per_face += ($prices['dev']['unite'] ?? 0);
            }
        } else {
            // Encre (ComColor)
            if ($color) {
                // Couleur : Bleue + Couleur + Jaune + Rouge (avec taux) + Noire (SANS taux)
                $cost_per_face += (($prices['bleue']['unite'] ?? 0) * $fill_rate_multiplier);
                $cost_per_face += (($prices['couleur']['unite'] ?? 0) * $fill_rate_multiplier);
                $cost_per_face += (($prices['jaune']['unite'] ?? 0) * $fill_rate_multiplier);
                $cost_per_face += (($prices['rouge']['unite'] ?? 0) * $fill_rate_multiplier);
                
                // Fixe
                $cost_per_face += ($prices['noire']['unite'] ?? 0);
            } else {
                // Noir et blanc : Noire (Fixe)
                $cost_per_face += ($prices['noire']['unite'] ?? 0);
            }
        }

        // Déterminer taille papier (approximatif si pas donné, supposons A4 par défaut)
        // Si le document contient "A3", on assume A3.
        $is_A3 = (stripos($document, 'A3') !== false) || (isset($input['paper_size']) && stripos($input['paper_size'], 'A3') !== false);
        $taille = $is_A3 ? 'A3' : 'A4';

        if ($taille === 'A4') {
            $cost_per_face = $cost_per_face / 2;
            $prix_papier = $prix_papier_a4;
        } else {
            $prix_papier = $prix_papier_a3;
        }

        // Calcul total
        // Note: calculatePageCost est remplacé par le calcul inline plus précis pour Auto Tirage ci-dessus
        
        // Calcul du nombre de feuilles (physique) pour le papier
        // On calcule d'abord les feuilles par exemplaire, puis on multiplie par le nombre de copies
        $pages_per_copy = $total_pages / $copies;
        $sheets_per_copy = $duplex ? ceil($pages_per_copy / 2) : $pages_per_copy;
        $nb_feuilles_total = $sheets_per_copy * $copies;

        // Coût papier
        $cout_papier = $nb_feuilles_total * $prix_papier;

        // Coût encre (pages logiques)
        $cout_encre = $total_pages * $cost_per_face;

        $price = round($cout_papier + $cout_encre, 2);

        if (!$simulate) {
            // STAGING: Mettre à jour print_jobs au lieu d'insérer dans photocop
            // L'insertion définitive se fera lors de la validation sur tirage_multimachines
            file_put_contents(__DIR__ . '/debug_log.txt', "STAGING: nb_f=$nb_feuilles_total, price=$price, session=$session_id\n", FILE_APPEND);

            $stmt_staging = $con_pdo->prepare("
                UPDATE print_jobs SET 
                    session_id = ?,
                    calculated_price = ?,
                    machine_type = 'photocop',
                    machine_id = ?,
                    machine_name = ?,
                    contact = ?,
                    staged = 1,
                    document = ?,
                    total_pages = ?,
                    copies = ?,
                    thumbnail_url = ?
                WHERE job_id = ? AND printer_name = ?
            ");
            $stmt_staging->execute([
                $session_id,
                $price,
                $machine_id,
                $marque,
                $contact,
                $document,
                $total_pages,
                $copies,
                $input['thumbnail_url'] ?? null,
                strval($original_job_id),
                $printerName
            ]);

            $message = "Ajouté à la session : $total_pages pages ($taille) -> $price €";
        } else {
            $message = "Simulation : $total_pages pages ($taille)";
        }

        $details = [
            'id' => $inserted_id ?? null,
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

        // Un duplicopieur nécessite 1 Master par page unique du document
        // et 1 Passage par page totale imprimée (pages * copies)
        $nb_masters = $pages;
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
            // STAGING: Mettre à jour print_jobs au lieu d'insérer dans dupli
            // L'insertion définitive se fera lors de la validation sur tirage_multimachines
            file_put_contents(__DIR__ . '/debug_log.txt', "STAGING DUPLI: price=$price, session=$session_id\n", FILE_APPEND);

            $stmt_staging = $con_pdo->prepare("
                UPDATE print_jobs SET 
                    session_id = ?,
                    calculated_price = ?,
                    machine_type = 'dupli',
                    machine_id = ?,
                    machine_name = ?,
                    contact = ?,
                    staged = 1,
                    document = ?,
                    total_pages = ?,
                    copies = ?,
                    thumbnail_url = ?
                WHERE job_id = ? AND printer_name = ?
            ");
            $stmt_staging->execute([
                $session_id,
                $price,
                $machine_id,
                $nom_machine,
                $contact,
                $document,
                $total_pages,
                $copies,
                $input['thumbnail_url'] ?? null,
                strval($original_job_id),
                $printerName
            ]);

            $message = "Ajouté à la session : 1 Master, $nb_passages Passages -> $price €";
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
    } else {
        // Unknown machine type
        $message = "Type de machine inconnu: $machine_type";
        $details = [
            'type' => 'unknown',
            'machine' => $printerName,
            'machine_id' => $machine_id,
            'price' => 0,
            'error' => "Type de machine inconnu: $machine_type"
        ];
        error_log("CRITICAL save_auto_print: Unknown machine type '$machine_type'");
    } // End of machine type block

    if (!$simulate && isset($original_job_id) && $original_job_id) {
        // Supprimer des jobs temporaires
        // EDIT: On conserve l'historique brut pour le moniteur (à la demande de l'utilisateur)
        // $del = $con_pdo->prepare("DELETE FROM print_jobs WHERE job_id = ?");
        // $del->execute([$original_job_id]);
        // $del = $con_pdo->prepare("DELETE FROM print_jobs WHERE job_id = ?");
        // $del->execute([$original_job_id]);

        // Marquer comme définitivement enregistré pour éviter les doublons au redémarrage/nouvelle session
        $mark = $con_pdo->prepare("INSERT OR IGNORE INTO recorded_print_jobs (job_id, printer_name, print_job_id) VALUES (?, ?, ?)");
        $mark->execute([strval($original_job_id), $printerName, $internal_id]);
    }

    if ($con_pdo) {
        $con_pdo->commit();
    } else {
        error_log("CRITICAL: con_pdo is null at commit time!");
    }

    $final_log = [
        'success' => true,
        'message_null' => is_null($message),
        'message_val' => $message,
        'details_null' => is_null($details),
        'details_count' => is_array($details) ? count($details) : 'not_array'
    ];
    error_log("DEBUG save_auto_print FINAL: " . json_encode($final_log));

    echo json_encode([
        'success' => true,
        'message' => $message ?? "MessageNullFallback",
        'details' => $details ?? [],
        'debug_info' => "Printer: $printerName | Type: '$machine_type' (Hex: " . bin2hex($machine_type) . ") | Keys: " . implode(',', array_keys($mapping)) . " | IsPhotocop: " . ($machine_type === 'photocop' ? 'YES' : 'NO')
    ]);

} catch (Throwable $e) {
    if (isset($con_pdo) && $con_pdo->inTransaction()) {
        $con_pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>