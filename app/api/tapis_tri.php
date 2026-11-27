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
require_once __DIR__ . '/../controler/functions/tirage_calc.php';
require_once __DIR__ . '/../models/tirage_multimachines.php';
require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';

try {
    $db = create_database_manager();
    $con_pdo = pdo_connect();

    // Migration non destructive des colonnes compteurs duplicopieur pour print_jobs
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN master_av INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN master_ap INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN passage_av INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN passage_ap INTEGER DEFAULT 0"); } catch(Exception $e) {}
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN tambour TEXT DEFAULT ''"); } catch(Exception $e) {}
    try { $con_pdo->exec("ALTER TABLE print_jobs ADD COLUMN feuilles_payees INTEGER DEFAULT 0"); } catch(Exception $e) {}

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

        case 'update_job':
            handleUpdateJob($db, $con_pdo, $input);
            break;

        case 'dupli_info':
            handleDupliInfo($con_pdo, $input);
            break;

        case 'assign_to_session':
            handleAssignToSession($db, $con_pdo, $input);
            break;

        case 'delete_jobs':
            handleDeleteJobs($db, $con_pdo, $input);
            break;

        case 'unpaid_accounts':
            handleUnpaidAccounts($db, $con_pdo);
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

    // Calculer les prix et résoudre les machines pour les jobs
    foreach ($jobs as &$job) {
        $resolved = resolveJobMachineInfo($con_pdo, $db, $job);
        $job['machine_name'] = $resolved['name'];
        $job['machine_type'] = $resolved['type'];
        $job['machine_id'] = $resolved['id'];

        if (empty($job['calculated_price']) || floatval($job['calculated_price']) <= 0) {
            $computed = computeJobEstimatedPrice($con_pdo, $job);
            if ($computed > 0) {
                $job['calculated_price'] = $computed;
                // Sauvegarder pour les prochains appels uniquement si l'analyse est terminée (thumbnail_url présent)
                // pour éviter de figer un prix préliminaire calculé avec un encrage à 0% ou des pages incomplètes
                if (!empty($job['thumbnail_url'])) {
                    $con_pdo->prepare("UPDATE print_jobs SET calculated_price = ?, machine_name = ?, machine_type = ?, machine_id = ? WHERE id = ?")
                            ->execute([$computed, $resolved['name'], $resolved['type'], $resolved['id'], $job['id']]);
                }
            }
        } else if (empty($job['machine_name']) || $job['machine_name'] === $job['printer_name']) {
            $con_pdo->prepare("UPDATE print_jobs SET machine_name = ?, machine_type = ?, machine_id = ? WHERE id = ?")
                    ->execute([$resolved['name'], $resolved['type'], $resolved['id'], $job['id']]);
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
 * Encaissement direct au comptoir (Espèces par défaut, As-tu payé ? Oui/Non, montant payé et mot)
 */
function handleCheckoutDirect($db, $con_pdo, $input) {
    $jobIds = $input['job_ids'] ?? [];
    $contact = trim($input['contact'] ?? '');
    if ($contact === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Veuillez renseigner un nom de contact."]);
        return;
    }

    // As-tu payé ? (par défaut 'oui')
    $paye = 'oui';
    if (isset($input['paye'])) {
        $paye = ($input['paye'] === 'oui' || $input['paye'] === true || $input['paye'] === '1') ? 'oui' : 'non';
    }

    $manualItems = $input['manual_items'] ?? []; // Éventuels tirages manuels ajoutés au panier

    if (empty($jobIds) && empty($manualItems)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Aucun document sélectionné pour l'encaissement."]);
        return;
    }

    $dateFormatted = date('d/m/Y');
    $timestamp = time();
    $dateTime = date('Y-m-d H:i:s');

    $con_pdo->beginTransaction();

    try {
        $totalPrice = 0.0;
        $processedCount = 0;
        $tirageGlobalId = DatabaseMigrationManager::generateTirageGlobalId($dateFormatted, $contact, 'TapisDeTri');

        // Récupérer les jobs et calculer le prix total
        $jobs = [];
        if (!empty($jobIds)) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $jobs = $db->select("SELECT * FROM print_jobs WHERE id IN ($placeholders)", $jobIds);
            foreach ($jobs as &$j) {
                $p = floatval($j['calculated_price'] ?? 0);
                if ($p <= 0) {
                    $p = computeJobEstimatedPrice($con_pdo, $j);
                }
                $j['calculated_price'] = $p;
                $totalPrice += $p;
            }
        }

        foreach ($manualItems as $m) {
            $totalPrice += floatval($m['prix'] ?? 0);
        }
        $totalPrice = round($totalPrice, 2);

        // Montant payé (combien)
        $hasExplicitAmount = isset($input['amount_paid']) || isset($input['amount_received']);
        $amountPaid = floatval($input['amount_paid'] ?? $input['amount_received'] ?? 0);
        if ($paye === 'oui' && (!$hasExplicitAmount || $amountPaid <= 0)) {
            $amountPaid = $totalPrice;
        } elseif ($paye === 'non') {
            $amountPaid = 0.0;
        }

        // Un mot (uniquement ce que l'utilisateur a saisi, pas de mot automatique)
        $mot = trim($input['mot'] ?? '');

        $allItemCount = count($jobs) + count($manualItems);
        $cbRestant = $amountPaid;
        $itemIndex = 0;

        // 1. Traiter les jobs du tapis de tri
        foreach ($jobs as $job) {
            $itemIndex++;
            $jobPrice = floatval($job['calculated_price']);

            // Répartition du montant payé (cb)
            if ($paye === 'oui') {
                if ($itemIndex === $allItemCount) {
                    $jobCb = $cbRestant;
                } else {
                    $jobCb = ($totalPrice > 0) ? round($amountPaid * ($jobPrice / $totalPrice), 2) : 0;
                    $cbRestant = max(0, round($cbRestant - $jobCb, 2));
                }
            } else {
                $jobCb = 0;
            }

            $machineInfo = resolveJobMachineInfo($con_pdo, $db, $job);
            $machineType = $machineInfo['type'];
            $machineName = $machineInfo['name'];
            $machineId = $machineInfo['id'];

            $docName = $job['document_display_name'] ?: $job['document'];
            $thumbnail = $job['thumbnail_url'] ?? null;
            $copies = intval($job['copies'] ?? 1);
            $totalPages = intval($job['total_pages'] ?? 1);
            $duplex = ($job['duplex'] == 1 || $job['duplex'] === true || $job['duplex'] === '1') ? 'oui' : 'non';
            $paperSize = strtoupper($job['paper_size'] ?? 'A4');
            $taille = (strpos($paperSize, 'A3') !== false) ? 'A3' : 'A4';

            // Insertion dans photocop (ou dupli si applicable)
            if ($machineType === 'dupli' || $machineType === 'duplicopieur') {
                $masterAv = intval($job['master_av'] ?? 0);
                $masterAp = intval($job['master_ap'] ?? 0);
                $passageAv = intval($job['passage_av'] ?? 0);
                $passageAp = intval($job['passage_ap'] ?? 0);
                $tambourType = !empty($job['tambour']) ? $job['tambour'] : ($job['color_mode'] ?? 'tambour_noir');

                $con_pdo->prepare("
                    INSERT INTO dupli (
                        type, contact, master_av, master_ap, passage_av, passage_ap,
                        rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id,
                        tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $tambourType,
                    $contact,
                    $masterAv,
                    $masterAp,
                    $passageAv,
                    $passageAp,
                    $duplex,
                    $jobPrice,
                    $paye,
                    $jobCb,
                    $mot,
                    $timestamp,
                    $machineName,
                    $machineId,
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
                    $machineName,
                    $contact,
                    $nbFeuilles,
                    $duplex,
                    $jobPrice,
                    $paye,
                    $jobCb,
                    $mot,
                    $timestamp,
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

        // 2. Traiter les tirages manuels supplémentaires ajoutés au panier
        foreach ($manualItems as $mItem) {
            $itemIndex++;
            $mPrice = floatval($mItem['prix'] ?? 0);

            if ($paye === 'oui') {
                if ($itemIndex === $allItemCount) {
                    $mCb = $cbRestant;
                } else {
                    $mCb = ($totalPrice > 0) ? round($amountPaid * ($mPrice / $totalPrice), 2) : 0;
                    $cbRestant = max(0, round($cbRestant - $mCb, 2));
                }
            } else {
                $mCb = 0;
            }

            $mType = $mItem['type'] ?? 'photocop';
            $mMarque = trim(strval($mItem['marque'] ?? ''));
            if (empty($mMarque) || $mMarque === 'Manuel') {
                $mMarque = 'comcolor';
            }
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
                $paye,
                $mCb,
                $mot,
                $timestamp,
                $tirageGlobalId,
                $mItem['label'] ?? 'Tirage manuel',
                1,
                $mTaille
            ]);
            $processedCount++;
        }

        $con_pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Encaissement validé avec succès.',
            'total_price' => $totalPrice,
            'amount_paid' => $amountPaid,
            'amount_received' => $amountPaid,
            'paye' => $paye,
            'mot' => $mot,
            'change' => 0.0,
            'items_count' => $processedCount,
            'tirage_global_id' => $tirageGlobalId,
            'contact' => $contact,
            'payment_method' => 'especes'
        ]);

    } catch (Throwable $e) {
        $con_pdo->rollBack();
        throw $e;
    }
}

/**
 * Met à jour les paramètres d'un tirage (nombre de pages, copies, format, couleur, duplex, etc.) et recalcule son prix
 */
function handleUpdateJob($db, $con_pdo, $input) {
    $jobId = intval($input['job_id'] ?? 0);
    if ($jobId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "ID de job invalide."]);
        return;
    }

    $job = $db->selectOne("SELECT * FROM print_jobs WHERE id = ?", [$jobId]);
    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Job introuvable."]);
        return;
    }

    $docName = isset($input['document_name']) && trim($input['document_name']) !== ''
        ? trim($input['document_name'])
        : ($job['document_display_name'] ?: $job['document']);

    $copies = isset($input['copies']) ? max(1, intval($input['copies'])) : intval($job['copies'] ?? 1);
    $totalPages = isset($input['total_pages']) ? max(1, intval($input['total_pages'])) : intval($job['total_pages'] ?? 1);

    if (isset($input['duplex'])) {
        $duplexVal = $input['duplex'];
        $duplex = ($duplexVal === 'oui' || $duplexVal === 1 || $duplexVal === '1' || $duplexVal === true) ? 1 : 0;
    } else {
        $duplex = intval($job['duplex'] ?? 0);
    }

    if (isset($input['color_mode'])) {
        $colorVal = strtolower(strval($input['color_mode']));
        $colorMode = ($colorVal === 'oui' || $colorVal === 'couleur' || $colorVal === 'color' || $colorVal === '2') ? 'Color' : 'Monochrome';
    } else {
        $colorMode = $job['color_mode'] ?? 'Monochrome';
    }

    $paperSize = isset($input['paper_size']) ? strtoupper(trim($input['paper_size'])) : ($job['paper_size'] ?? 'A4');
    if (strpos($paperSize, 'A3') !== false) {
        $paperSize = 'A3';
    } else {
        $paperSize = 'A4';
    }

    $resolved = resolveJobMachineInfo($con_pdo, $db, [
        'machine_name' => $input['machine_name'] ?? null,
        'machine_type' => $input['machine_type'] ?? null,
        'machine_id' => $input['machine_id'] ?? null,
        'printer_name' => $job['printer_name'] ?? null
    ]);
    $machineType = $resolved['type'];
    $machineId = $resolved['id'];
    $machineName = $resolved['name'];

    $fillRate = isset($input['fill_rate']) ? max(0, min(100, floatval($input['fill_rate']))) : floatval($job['fill_rate'] ?? 0);

    $masterAv = isset($input['master_av']) ? intval($input['master_av']) : intval($job['master_av'] ?? 0);
    $masterAp = isset($input['master_ap']) ? intval($input['master_ap']) : intval($job['master_ap'] ?? 0);
    $passageAv = isset($input['passage_av']) ? intval($input['passage_av']) : intval($job['passage_av'] ?? 0);
    $passageAp = isset($input['passage_ap']) ? intval($input['passage_ap']) : intval($job['passage_ap'] ?? 0);
    $tambour = isset($input['tambour']) ? trim($input['tambour']) : ($job['tambour'] ?? '');
    $feuillesPayees = isset($input['feuilles_payees']) ? (intval($input['feuilles_payees']) ? 1 : 0) : intval($job['feuilles_payees'] ?? 0);

    $tempJob = $job;
    $tempJob['total_pages'] = $totalPages;
    $tempJob['copies'] = $copies;
    $tempJob['duplex'] = $duplex;
    $tempJob['color_mode'] = $colorMode;
    $tempJob['paper_size'] = $paperSize;
    $tempJob['machine_type'] = $machineType;
    $tempJob['machine_id'] = $machineId;
    $tempJob['machine_name'] = $machineName;
    $tempJob['fill_rate'] = $fillRate;
    $tempJob['master_av'] = $masterAv;
    $tempJob['master_ap'] = $masterAp;
    $tempJob['passage_av'] = $passageAv;
    $tempJob['passage_ap'] = $passageAp;
    $tempJob['tambour'] = $tambour;
    $tempJob['feuilles_payees'] = $feuillesPayees;

    if (isset($input['calculated_price']) && is_numeric($input['calculated_price']) && floatval($input['calculated_price']) > 0) {
        $calculatedPrice = round(floatval($input['calculated_price']), 2);
    } else {
        if ($machineType === 'dupli' || $machineType === 'duplicopieur') {
            $calculatedPrice = computeDupliJobPrice($con_pdo, $tempJob);
        } else {
            $calculatedPrice = computeJobEstimatedPrice($con_pdo, $tempJob);
        }
    }

    $stmt = $con_pdo->prepare("
        UPDATE print_jobs
        SET document_display_name = ?,
            copies = ?,
            total_pages = ?,
            duplex = ?,
            color_mode = ?,
            paper_size = ?,
            fill_rate = ?,
            machine_type = ?,
            machine_id = ?,
            machine_name = ?,
            calculated_price = ?,
            master_av = ?,
            master_ap = ?,
            passage_av = ?,
            passage_ap = ?,
            tambour = ?,
            feuilles_payees = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $docName,
        $copies,
        $totalPages,
        $duplex,
        $colorMode,
        $paperSize,
        $fillRate,
        $machineType,
        $machineId,
        $machineName,
        $calculatedPrice,
        $masterAv,
        $masterAp,
        $passageAv,
        $passageAp,
        $tambour,
        $feuillesPayees,
        $jobId
    ]);

    $updatedJob = $db->selectOne("SELECT * FROM print_jobs WHERE id = ?", [$jobId]);

    echo json_encode([
        'success' => true,
        'message' => 'Tirage mis à jour avec succès.',
        'job' => $updatedJob,
        'calculated_price' => $calculatedPrice
    ]);
}

/**
 * Assigne les jobs sélectionnés au compte d'un contact (enregistré comme non payé dans photocop/dupli)
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

    if (empty($contact) && $sessionId) {
        $session = $db->selectOne("SELECT contact FROM print_sessions WHERE id = ?", [$sessionId]);
        if ($session && !empty($session['contact'])) {
            $contact = $session['contact'];
        }
    }

    if (empty($contact)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Veuillez renseigner un nom de compte."]);
        return;
    }

    $dateFormatted = date('d/m/Y');
    $dateTime = date('Y-m-d H:i:s');
    $tirageGlobalId = DatabaseMigrationManager::generateTirageGlobalId($dateFormatted, $contact, 'CompteClient');

    $con_pdo->beginTransaction();

    try {
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $jobs = $db->select("SELECT * FROM print_jobs WHERE id IN ($placeholders)", $jobIds);

        $totalPrice = 0.0;
        $insertedCount = 0;

        foreach ($jobs as $job) {
            $jobPrice = floatval($job['calculated_price'] ?? 0);
            if ($jobPrice <= 0) {
                $jobPrice = computeJobEstimatedPrice($con_pdo, $job);
            }
            $totalPrice += $jobPrice;

            $machineInfo = resolveJobMachineInfo($con_pdo, $db, $job);
            $machineType = $machineInfo['type'];
            $nomMachine = $machineInfo['name'];
            $duplicopieurId = $machineInfo['id'];

            $isDuplex = ($job['duplex'] == 1 || $job['duplex'] === true || $job['duplex'] === '1');
            $rv = $isDuplex ? 'oui' : 'non';
            $nbExemplaires = max(1, intval($job['copies'] ?? 1));
            $totalPages = max(1, intval($job['total_pages'] ?? 1));
            $sheetsPerCopy = $isDuplex ? ceil($totalPages / 2) : $totalPages;
            $nbFeuillesTotal = $sheetsPerCopy * $nbExemplaires;
            $taille = (strpos(strtoupper($job['paper_size'] ?? ''), 'A3') !== false) ? 'A3' : 'A4';
            $docName = $job['document_display_name'] ?? $job['document'] ?? 'Document';
            $thumbUrl = $job['thumbnail_url'] ?? '';
            $mot = trim($input['session_name'] ?? $input['mot'] ?? 'Mis sur compte');

            if ($machineType === 'duplicopieur' || $machineType === 'dupli') {
                $masterAv = intval($job['master_av'] ?? 0);
                $masterAp = intval($job['master_ap'] ?? 0);
                $passageAv = intval($job['passage_av'] ?? 0);
                $passageAp = intval($job['passage_ap'] ?? 0);
                $tambourType = !empty($job['tambour']) ? $job['tambour'] : ($job['color_mode'] ?? 'tambour_noir');

                $con_pdo->prepare("
                    INSERT INTO dupli (
                        type, contact, master_av, master_ap, passage_av, passage_ap,
                        rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id,
                        tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, 'non', 0, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )
                ")->execute([
                    $tambourType,
                    $contact,
                    $masterAv,
                    $masterAp,
                    $passageAv,
                    $passageAp,
                    $rv,
                    $jobPrice,
                    $mot,
                    $dateFormatted,
                    $nomMachine,
                    $duplicopieurId,
                    $tirageGlobalId,
                    $docName,
                    $thumbUrl,
                    $nbExemplaires,
                    $taille
                ]);
            } else {
                $con_pdo->prepare("
                    INSERT INTO photocop (
                        type, nb_f, rv, prix, contact, paye, cb,
                        date, mot, marque, tirage_global_id, document_name,
                        thumbnail_url, nb_exemplaires, taille
                    ) VALUES (
                        ?, ?, ?, ?, ?, 'non', 0,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?
                    )
                ")->execute([
                    $taille,
                    $nbFeuillesTotal,
                    $rv,
                    $jobPrice,
                    $contact,
                    $dateFormatted,
                    $mot,
                    $nomMachine,
                    $tirageGlobalId,
                    $docName,
                    $thumbUrl,
                    $nbExemplaires,
                    $taille
                ]);
            }
            $insertedCount++;
        }

        // Supprimer les jobs traités du tapis de tri
        $con_pdo->prepare("DELETE FROM print_jobs WHERE id IN ($placeholders)")->execute($jobIds);

        // Clôturer la session si elle existait
        if ($sessionId) {
            $con_pdo->prepare("UPDATE print_sessions SET status = 'closed', closed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$sessionId]);
        }

        $con_pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Impressions enregistrées sur le compte de $contact (Non payé).",
            'contact' => $contact,
            'assigned_count' => $insertedCount,
            'total_price' => round($totalPrice, 2),
            'tirage_global_id' => $tirageGlobalId
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
 * Liste tous les comptes clients avec des tirages non payés (pour l'assignation sur compte)
 */
function handleUnpaidAccounts($db, $con_pdo) {
    $sql = "
        SELECT contact, SUM(CAST(prix AS REAL)) as total_du, COUNT(*) as nb_tirages
        FROM (
            SELECT contact, prix FROM photocop WHERE (paye = 'non' OR paye = 'Non') AND contact IS NOT NULL AND TRIM(contact) != ''
            UNION ALL
            SELECT contact, prix FROM dupli WHERE (paye = 'non' OR paye = 'Non') AND contact IS NOT NULL AND TRIM(contact) != ''
        )
        GROUP BY LOWER(TRIM(contact))
        ORDER BY contact COLLATE NOCASE ASC
    ";
    $accounts = $con_pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($accounts as &$acc) {
        $acc['total_du'] = round(floatval($acc['total_du'] ?? 0), 2);
        $acc['nb_tirages'] = intval($acc['nb_tirages'] ?? 0);
    }
    echo json_encode(['success' => true, 'accounts' => $accounts]);
}

/**
 * Informations et derniers compteurs pour un duplicopieur (pour la modale d'édition)
 */
function handleDupliInfo($con_pdo, $input) {
    $machineId = intval($input['machine_id'] ?? 0);
    $machineName = trim($input['machine_name'] ?? '');

    $dupli = null;
    if ($machineId > 0) {
        $stmt = $con_pdo->prepare("SELECT * FROM duplicopieurs WHERE id = ?");
        $stmt->execute([$machineId]);
        $dupli = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$dupli && !empty($machineName)) {
        $stmt = $con_pdo->prepare("SELECT * FROM duplicopieurs WHERE marque = ? OR (marque || ' ' || modele) = ?");
        $stmt->execute([$machineName, $machineName]);
        $dupli = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$dupli) {
        $dupli = $con_pdo->query("SELECT * FROM duplicopieurs WHERE actif = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    if (!$dupli) {
        echo json_encode(['success' => false, 'error' => 'Aucun duplicopieur trouvé.']);
        return;
    }

    $realMachineName = ($dupli['marque'] === $dupli['modele']) ? $dupli['marque'] : ($dupli['marque'] . ' ' . $dupli['modele']);

    // Compteurs précédents
    $stmtC = $con_pdo->prepare("SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? OR duplicopieur_id = ? ORDER BY id DESC LIMIT 1");
    $stmtC->execute([$realMachineName, $dupli['id']]);
    $last = $stmtC->fetch(PDO::FETCH_ASSOC);
    $masterAv = $last ? intval($last['master_ap']) : 0;
    $passageAv = $last ? intval($last['passage_ap']) : 0;

    // Tambours
    $tamboursList = [];
    if (!empty($dupli['tambours'])) {
        $raw = json_decode($dupli['tambours'], true);
        if (is_array($raw)) {
            $translations = [
                'tambour_noir' => 'Tambour Noir',
                'tambour_rouge' => 'Tambour Rouge',
                'tambour_bleu' => 'Tambour Bleu',
                'tambour_vert' => 'Tambour Vert',
                'tambour_jaune' => 'Tambour Jaune'
            ];
            foreach ($raw as $t) {
                $tamboursList[] = [
                    'id' => $t,
                    'label' => $translations[$t] ?? ucfirst(str_replace('_', ' ', $t))
                ];
            }
        }
    }
    if (empty($tamboursList)) {
        $tamboursList[] = ['id' => 'tambour_noir', 'label' => 'Tambour Noir'];
    }

    // Prix
    $prices = [];
    $stmtP = $con_pdo->prepare("SELECT type, unite, pack FROM prix WHERE machine_type = 'dupli' AND machine_id = ?");
    $stmtP->execute([$dupli['id']]);
    while ($row = $stmtP->fetch(PDO::FETCH_ASSOC)) {
        $prices[$row['type']] = floatval($row['unite']);
    }

    echo json_encode([
        'success' => true,
        'duplicopieur_id' => $dupli['id'],
        'machine_name' => $realMachineName,
        'master_av' => $masterAv,
        'passage_av' => $passageAv,
        'tambours' => $tamboursList,
        'prices' => $prices
    ]);
}

/**
 * Calcul du prix d'un job pour duplicopieur selon la logique de tirage_multimachines
 */
function computeDupliJobPrice($con_pdo, $job): float {
    $dupliId = intval($job['machine_id'] ?? 0);
    $paperSize = strtoupper($job['paper_size'] ?? 'A4');
    $taille = (strpos($paperSize, 'A3') !== false) ? 'A3' : 'A4';
    $rv = !empty($job['duplex']) && ($job['duplex'] == 1 || $job['duplex'] === 'oui' || $job['duplex'] === true || $job['duplex'] === '1');
    $feuillesPayees = !empty($job['feuilles_payees']);

    $masterAv = intval($job['master_av'] ?? 0);
    $masterAp = intval($job['master_ap'] ?? 0);
    $passageAv = intval($job['passage_av'] ?? 0);
    $passageAp = intval($job['passage_ap'] ?? 0);

    $nbMasters = max(0, $masterAp - $masterAv);
    $nbPassages = max(0, $passageAp - $passageAv);

    // Si les compteurs n'ont pas été saisis ou différence = 0, estimation de secours
    if ($nbMasters <= 0 && $nbPassages <= 0) {
        $totalPages = max(1, intval($job['total_pages'] ?? 1));
        $copies = max(1, intval($job['copies'] ?? 1));
        $nbMasters = $totalPages;
        $nbPassages = $totalPages * $copies;
    }

    // Récupérer les prix
    $stmt = $con_pdo->prepare("SELECT type, unite FROM prix WHERE machine_type = 'dupli' AND machine_id = ?");
    $stmt->execute([$dupliId]);
    $prixMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $prixMap[$row['type']] = floatval($row['unite']);
    }

    $prixMaster = $prixMap['master'] ?? 0.15;
    $tambour = !empty($job['tambour']) ? $job['tambour'] : 'tambour_noir';
    $prixPassage = $prixMap[$tambour] ?? ($prixMap['tambour_noir'] ?? 0.02);

    $prixData = function_exists('get_price') ? get_price() : [];
    $prixPapier = $prixData['papier'][$taille] ?? ($taille === 'A3' ? 0.02 : 0.01);

    if ($taille === 'A4') {
        $prixMaster = $prixMaster / 2;
        $prixPassage = $prixPassage / 2;
    }

    $nbFeuilles = $rv ? ($nbPassages / 2) : $nbPassages;
    if ($feuillesPayees) {
        $nbFeuilles = 0;
    }

    $coutMasters = $nbMasters * $prixMaster;
    $coutPassages = $nbPassages * $prixPassage;
    $coutPapier = $nbFeuilles * $prixPapier;

    return round($coutMasters + $coutPassages + $coutPapier, 2);
}
