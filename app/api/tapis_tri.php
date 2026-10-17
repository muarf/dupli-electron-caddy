<?php
/**
 * API Tapis de Tri des impressions
 * 
 * Endpoints:
 * - GET  ?tapis_tri&action=list : Récupérer les jobs non traités des dernières 24h
 * - POST ?tapis_tri&action=checkout_direct : Encaissement express au comptoir
 * - POST ?tapis_tri&action=assign_to_session : Assigner un lot de jobs à une session (existante ou nouvelle)
 * - POST ?tapis_tri&action=delete_jobs : Supprimer des jobs du tapis (et du spooler si actif)
 * - POST ?tapis_tri&action=calculate_price : Recalculer le prix unitaire d'un job
 */

$allowedOrigins = ['http://127.0.0.1:8000', 'http://localhost:8000', 'http://tauri.localhost', 'tauri://localhost', 'https://tauri.localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$hostOrigin = $scheme . '://' . $host;

if (in_array($origin, $allowedOrigins, true) || $origin === $hostOrigin) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/tirage.php';
require_once __DIR__ . '/../models/tirage_multimachines.php';
require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';

try {
    $db = create_database_manager();
    $con_pdo = pdo_connect();

    $action = $_GET['action'] ?? '';
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true) ?? [];
        }
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
        if (empty($action) && isset($input['action'])) {
            $action = $input['action'];
        }
    } else {
        $input = $_GET;
    }

    switch ($action) {
        case 'list':
            handleListJobs($db, $con_pdo);
            break;

        case 'checkout_direct':
            handleCheckoutDirect($db, $con_pdo, $input);
            break;

        case 'assign_to_session':
            handleAssignToSession($db, $con_pdo, $input);
            break;

        case 'delete_jobs':
            handleDeleteJobs($db, $con_pdo, $input);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Action '$action' inconnue"]);
            break;
    }

} catch (Throwable $e) {
    error_log("[TAPIS_TRI] Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}

/**
 * Liste tous les jobs en attente sur le tapis de tri (dernières 24 heures, non payés/archivés)
 */
function handleListJobs($db, $con_pdo) {
    // Vérifier si la table print_jobs existe
    if (!$db->tableExists('print_jobs')) {
        echo json_encode(['success' => true, 'jobs' => [], 'total' => 0]);
        return;
    }

    // Récupérer les jobs non encore archivés dans recorded_print_jobs
    // et non encore finalisés (staged = 0 ou session_id IS NULL)
    $sql = "
        SELECT 
            pj.id,
            pj.job_id,
            pj.document,
            pj.document_display_name,
            pj.document_full_path,
            pj.printer_name,
            pj.status,
            pj.pages_printed,
            pj.total_pages,
            pj.copies,
            pj.fill_rate,
            pj.color_mode,
            pj.duplex,
            pj.paper_size,
            pj.thumbnail_url,
            pj.timestamp,
            pj.created_at,
            pj.calculated_price,
            pj.machine_name,
            pj.machine_type,
            pj.machine_id,
            pj.session_id,
            pj.staged
        FROM print_jobs pj
        LEFT JOIN recorded_print_jobs rpj ON pj.id = rpj.print_job_id
        WHERE rpj.print_job_id IS NULL
          AND (pj.session_id IS NULL OR pj.staged = 0)
          AND pj.created_at > datetime('now', '-24 hours')
        ORDER BY pj.created_at DESC, pj.id DESC
        LIMIT 100
    ";

    $jobs = $db->select($sql);

    // Calculer les prix pour les jobs qui n'en ont pas encore
    foreach ($jobs as &$job) {
        if (empty($job['calculated_price']) || floatval($job['calculated_price']) <= 0) {
            $computed = computeJobEstimatedPrice($con_pdo, $job);
            if ($computed > 0) {
                $job['calculated_price'] = $computed;
                // Sauvegarder pour les prochains appels
                $con_pdo->prepare("UPDATE print_jobs SET calculated_price = ? WHERE id = ?")
                        ->execute([$computed, $job['id']]);
            }
        }
        $job['calculated_price'] = round(floatval($job['calculated_price'] ?? 0), 2);
    }

    echo json_encode([
        'success' => true,
        'jobs' => $jobs,
        'total' => count($jobs)
    ]);
}

/**
 * Encaissement express direct au comptoir (Client de passage ou nommé)
 */
function handleCheckoutDirect($db, $con_pdo, $input) {
    $jobIds = $input['job_ids'] ?? [];
    $contact = trim($input['contact'] ?? 'Passage');
    if ($contact === '') {
        $contact = 'Passage';
    }
    $paymentMethod = $input['payment_method'] ?? 'especes'; // 'especes', 'cb', 'cheque'
    $amountReceived = floatval($input['amount_received'] ?? 0);
    $manualItems = $input['manual_items'] ?? []; // Éventuels tirages manuels ajoutés au panier

    if (empty($jobIds) && empty($manualItems)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Aucun document sélectionné pour l'encaissement."]);
        return;
    }

    $isCb = ($paymentMethod === 'cb') ? 'oui' : 'non';
    $isPaid = 'oui';
    $paymentLabel = match ($paymentMethod) {
        'cb' => 'CB',
        'cheque' => 'Chèque',
        default => 'Espèces'
    };
    $mot = "Encaissement direct (" . $paymentLabel . ")";
    $dateFormatted = date('d/m/Y');
    $dateTime = date('Y-m-d H:i:s');

    $con_pdo->beginTransaction();

    try {
        $totalPrice = 0.0;
        $processedCount = 0;
        $tirageGlobalId = DatabaseMigrationManager::generateTirageGlobalId($dateFormatted, $contact, 'TapisDeTri');

        // 1. Traiter les jobs du tapis de tri
        if (!empty($jobIds)) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $jobs = $db->select("SELECT * FROM print_jobs WHERE id IN ($placeholders)", $jobIds);

            foreach ($jobs as $job) {
                $jobPrice = floatval($job['calculated_price'] ?? 0);
                if ($jobPrice <= 0) {
                    $jobPrice = computeJobEstimatedPrice($con_pdo, $job);
                }
                $totalPrice += $jobPrice;

                $machineType = $job['machine_type'] ?? 'photocop';
                $docName = $job['document_display_name'] ?: $job['document'];
                $thumbnail = $job['thumbnail_url'] ?? null;
                $copies = intval($job['copies'] ?? 1);
                $totalPages = intval($job['total_pages'] ?? 1);
                $duplex = ($job['duplex'] == 1 || $job['duplex'] === true || $job['duplex'] === '1') ? 'oui' : 'non';
                $paperSize = strtoupper($job['paper_size'] ?? 'A4');
                if (strpos($paperSize, 'A3') !== false) {
                    $taille = 'A3';
                } else {
                    $taille = 'A4';
                }

                // Insertion dans photocop (ou dupli si applicable)
                if ($machineType === 'dupli') {
                    $con_pdo->prepare("
                        INSERT INTO dupli (
                            type, contact, master_av, master_ap, passage_av, passage_ap,
                            rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id,
                            tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille
                        ) VALUES (?, ?, '0', '0', '0', '0', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $job['color_mode'] ?? 'Noir',
                        $contact,
                        $duplex,
                        $jobPrice,
                        $isPaid,
                        $isCb,
                        $mot,
                        $dateFormatted,
                        $job['machine_name'] ?? 'Duplicopieur',
                        intval($job['machine_id'] ?? 1),
                        $tirageGlobalId,
                        $docName,
                        $thumbnail,
                        $copies,
                        $taille
                    ]);
                } else {
                    // Par défaut photocop
                    $nbFeuilles = ($duplex === 'oui') ? ceil($totalPages / 2) : $totalPages;
                    $con_pdo->prepare("
                        INSERT INTO photocop (
                            type, marque, contact, nb_f, rv, prix, paye, cb, mot, date,
                            tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        (stripos($job['color_mode'] ?? '', 'color') !== false) ? 'couleur' : 'noir',
                        $job['machine_name'] ?? $job['printer_name'],
                        $contact,
                        $nbFeuilles,
                        $duplex,
                        $jobPrice,
                        $isPaid,
                        $isCb,
                        $mot,
                        $dateFormatted,
                        $tirageGlobalId,
                        $docName,
                        $thumbnail,
                        $copies,
                        $taille
                    ]);
                }

                // Enregistrer dans recorded_print_jobs pour ne plus réapparaître
                $con_pdo->prepare("
                    INSERT OR IGNORE INTO recorded_print_jobs (job_id, printer_name, print_job_id, recorded_at)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    strval($job['job_id']),
                    $job['printer_name'],
                    $job['id'],
                    $dateTime
                ]);

                // Mettre à jour print_jobs comme complété
                $con_pdo->prepare("
                    UPDATE print_jobs 
                    SET status = 'completed', staged = 0, contact = ?
                    WHERE id = ?
                ")->execute([$contact, $job['id']]);

                $processedCount++;
            }
        }

        // 2. Traiter les tirages manuels supplémentaires ajoutés au panier
        foreach ($manualItems as $mItem) {
            $mPrice = floatval($mItem['prix'] ?? 0);
            $totalPrice += $mPrice;
            $mType = $mItem['type'] ?? 'photocop';
            $mMarque = $mItem['marque'] ?? 'Manuel';
            $mNbF = intval($mItem['nb_f'] ?? 1);
            $mRv = ($mItem['rv'] ?? 'non') === 'oui' ? 'oui' : 'non';
            $mTaille = strtoupper($mItem['taille'] ?? 'A4');
            $mColor = ($mItem['couleur'] ?? 'non') === 'oui' ? 'couleur' : 'noir';

            $con_pdo->prepare("
                INSERT INTO photocop (
                    type, marque, contact, nb_f, rv, prix, paye, cb, mot, date,
                    tirage_global_id, document_name, nb_exemplaires, taille
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $mColor,
                $mMarque,
                $contact,
                $mNbF,
                $mRv,
                $mPrice,
                $isPaid,
                $isCb,
                $mot . " [Ajout Manuel]",
                $dateFormatted,
                $tirageGlobalId,
                $mItem['label'] ?? 'Tirage manuel',
                1,
                $mTaille
            ]);
            $processedCount++;
        }

        $con_pdo->commit();

        $totalPrice = round($totalPrice, 2);
        $change = 0.0;
        if ($amountReceived > 0 && $amountReceived >= $totalPrice) {
            $change = round($amountReceived - $totalPrice, 2);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Encaissement validé avec succès.',
            'total_price' => $totalPrice,
            'amount_received' => $amountReceived,
            'change' => $change,
            'items_count' => $processedCount,
            'tirage_global_id' => $tirageGlobalId,
            'contact' => $contact,
            'payment_method' => $paymentMethod
        ]);

    } catch (Throwable $e) {
        $con_pdo->rollBack();
        throw $e;
    }
}

/**
 * Assigne les jobs sélectionnés à une session (existante ou nouvelle)
 */
function handleAssignToSession($db, $con_pdo, $input) {
    $jobIds = $input['job_ids'] ?? [];
    $sessionId = isset($input['session_id']) && $input['session_id'] !== '' ? intval($input['session_id']) : null;
    $contact = trim($input['contact'] ?? '');

    if (empty($jobIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Aucun document sélectionné."]);
        return;
    }

    $con_pdo->beginTransaction();

    try {
        // Création de session à la volée si demandé
        if (!$sessionId && !empty($contact)) {
            $sessionName = trim($input['session_name'] ?? '');
            $stmt = $con_pdo->prepare("
                INSERT INTO print_sessions (contact, session_name, status, opened_at)
                VALUES (?, ?, 'active', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$contact, $sessionName]);
            $sessionId = intval($con_pdo->lastInsertId());
        } elseif ($sessionId) {
            $session = $db->selectOne("SELECT contact FROM print_sessions WHERE id = ?", [$sessionId]);
            if ($session && empty($contact)) {
                $contact = $session['contact'];
            }
        }

        if (!$sessionId) {
            throw new Exception("Session introuvable ou contact non spécifié.");
        }

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $updateSql = "
            UPDATE print_jobs 
            SET session_id = ?, contact = ?, staged = 1 
            WHERE id IN ($placeholders)
        ";
        $params = array_merge([$sessionId, $contact], $jobIds);
        $con_pdo->prepare($updateSql)->execute($params);

        $con_pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Impressions assignées à la session.',
            'session_id' => $sessionId,
            'contact' => $contact,
            'assigned_count' => count($jobIds)
        ]);

    } catch (Throwable $e) {
        $con_pdo->rollBack();
        throw $e;
    }
}

/**
 * Supprime les jobs sélectionnés du tapis
 */
function handleDeleteJobs($db, $con_pdo, $input) {
    $jobIds = $input['job_ids'] ?? [];
    if (empty($jobIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Aucun identifiant fourni."]);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $con_pdo->prepare("DELETE FROM print_jobs WHERE id IN ($placeholders)")->execute($jobIds);

    echo json_encode([
        'success' => true,
        'message' => 'Impressions supprimées du tapis.',
        'deleted_count' => count($jobIds)
    ]);
}

/**
 * Calcule une estimation précise du prix pour un job
 */
function computeJobEstimatedPrice($con_pdo, $job): float {
    try {
        $printerName = $job['printer_name'] ?? '';
        $db = create_database_manager();
        $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);

        $totalPages = intval($job['total_pages'] ?? 1);
        $copies = intval($job['copies'] ?? 1);
        $globalTotalPages = max(1, $totalPages * $copies);
        $isColor = (stripos($job['color_mode'] ?? '', 'color') !== false);
        $duplex = ($job['duplex'] == 1 || $job['duplex'] === true || $job['duplex'] === '1');
        $paperSize = strtoupper($job['paper_size'] ?? 'A4');
        $isA3 = (strpos($paperSize, 'A3') !== false || stripos($job['document'] ?? '', 'A3') !== false);
        $taille = $isA3 ? 'A3' : 'A4';

        if (!$mapping) {
            // Pas de mapping spécifique : tarif de repli standard (ex: 0.10€ par page N&B, 0.25€ couleur)
            $unitPrice = $isColor ? 0.25 : 0.10;
            if ($taille === 'A3') $unitPrice *= 2;
            return round($globalTotalPages * $unitPrice, 2);
        }

        $machineType = $mapping['machine_type'] ?? 'photocop';
        $machineId = intval($mapping['machine_id'] ?? 1);

        if ($machineType === 'photocop') {
            $machine = $con_pdo->query("SELECT marque, type_encre FROM photocopieurs WHERE id = $machineId")->fetch(PDO::FETCH_ASSOC);
            if (!$machine) return 0.20;

            $marque = $machine['marque'];
            $machine_type_detected = $machine['type_encre'] ?? 'toner';

            $machine_prices = getMachinePrices($con_pdo, $marque);
            $prix_data = get_price();
            $prix_papier_a4 = $prix_data['papier']['A4'] ?? 0.01;
            $prix_papier_a3 = $prix_data['papier']['A3'] ?? 0.02;

            $rawFill = floatval($job['fill_rate'] ?? 0.5);
            if ($rawFill > 1.0) $rawFill = $rawFill / 100.0;
            $fillRate = max(0.05, min(1.0, $rawFill));
            $fillRateMultiplier = $isColor ? ($fillRate / 0.5) : 1.0;

            $costPerFace = 0;
            $photocopKey = 'photocop_' . $machineId;
            $prices = isset($prix_data[$photocopKey]) ? $prix_data[$photocopKey] : $machine_prices;

            if ($machine_type_detected === 'toner') {
                if ($isColor) {
                    $costPerFace += (($prices['cyan']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += (($prices['magenta']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += (($prices['jaune']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += ($prices['noir']['unite'] ?? 0);
                    $costPerFace += ($prices['tambour']['unite'] ?? 0);
                    $costPerFace += ($prices['dev']['unite'] ?? 0);
                } else {
                    $costPerFace += ($prices['noir']['unite'] ?? 0);
                    $costPerFace += ($prices['tambour']['unite'] ?? 0);
                    $costPerFace += ($prices['dev']['unite'] ?? 0);
                }
            } else {
                if ($isColor) {
                    $costPerFace += (($prices['bleue']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += (($prices['couleur']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += (($prices['jaune']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += (($prices['rouge']['unite'] ?? 0) * $fillRateMultiplier);
                    $costPerFace += ($prices['noire']['unite'] ?? 0);
                } else {
                    $costPerFace += ($prices['noire']['unite'] ?? 0);
                }
            }

            if ($taille === 'A4') {
                $costPerFace = $costPerFace / 2;
                $prixPapier = $prix_papier_a4;
            } else {
                $prixPapier = $prix_papier_a3;
            }

            $sheetsPerCopy = $duplex ? ceil($totalPages / 2) : $totalPages;
            $nbFeuillesTotal = $sheetsPerCopy * $copies;
            $coutPapier = $nbFeuillesTotal * $prixPapier;
            $coutEncre = $globalTotalPages * $costPerFace;

            return round($coutPapier + $coutEncre, 2);
        }

        return 0.20;
    } catch (Throwable $e) {
        error_log("[computeJobEstimatedPrice] " . $e->getMessage());
        return 0.15;
    }
}
