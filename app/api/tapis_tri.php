<?php
/**
 * API Tapis de Tri des Impressions
 * Endpoint pour la gestion du tri en atelier :
 * - action=list     : liste des impressions récentes non encore archivées
 * - action=save     : enregistrement définitif du lot sélectionné (dans photocop/dupli)
 * - action=delete   : suppression/annulation de jobs sélectionnés
 */

// Headers CORS et JSON
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/tirage.php';
require_once __DIR__ . '/../controler/functions/pricing.php';
require_once __DIR__ . '/../controler/functions/secure_delete.php';
require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';
require_once __DIR__ . '/../models/tirage_multimachines.php';

try {
    $db = create_database_manager();
    $con_pdo = pdo_connect();

    $action = $_GET['action'] ?? '';
    if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? 'save';
    } else if (empty($action)) {
        $action = 'list';
    }

    if ($action === 'list') {
        // --- 1. LISTE DES JOBS DU TAPIS DE TRI ---
        if (!$db->tableExists('print_jobs')) {
            ob_end_clean();
            echo json_encode(['success' => true, 'jobs' => [], 'total' => 0]);
            exit;
        }

        // Récupérer les jobs non encore clôturés dans recorded_print_jobs et récents (dernières 24h)
        $sql = "
            SELECT
                pj.id,
                pj.job_id,
                pj.document,
                pj.document_display_name,
                pj.printer_name,
                pj.status,
                pj.pages_printed,
                pj.total_pages,
                pj.size,
                pj.paper_size,
                pj.duplex,
                pj.color_mode,
                pj.copies,
                pj.fill_rate,
                pj.thumbnail_url,
                pj.calculated_price,
                pj.created_at,
                pj.timestamp
            FROM print_jobs pj
            LEFT JOIN recorded_print_jobs rpj ON pj.id = rpj.print_job_id
            WHERE rpj.print_job_id IS NULL
              AND pj.created_at > datetime('now', '-24 hours')
            ORDER BY pj.created_at DESC, pj.id DESC
            LIMIT 100
        ";

        $jobs = $db->select($sql);

        // Récupérer les tarifs pour calculer le prix à la volée si calculated_price == 0
        $prix_data = get_price();

        foreach ($jobs as &$job) {
            $calcPrice = floatval($job['calculated_price'] ?? 0);
            if ($calcPrice <= 0) {
                // Calcul du prix estimé
                $printerName = $job['printer_name'];
                $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);

                $copies = max(1, intval($job['copies'] ?? 1));
                $rawPages = max(1, intval($job['total_pages'] ?? 1));
                
                // Si total_pages reçu représente les pages du document unitaire
                // (ex: document de 35p avec 7 copies -> 35p par exemplaire, total 245 faces)
                $pagesPerCopy = $rawPages;
                $totalPagesImprimees = $pagesPerCopy * $copies;

                $isDuplex = intval($job['duplex'] ?? 0) === 1;
                $isColor = stripos($job['color_mode'] ?? '', 'color') !== false;
                $fillRate = floatval($job['fill_rate'] ?? 0.5);
                if ($fillRate > 1.0) $fillRate = $fillRate / 100.0;
                $fillRate = max(0.05, min(1.0, $fillRate));
                $docName = $job['document'] ?? '';
                $isA3 = (stripos($docName, 'A3') !== false) || (stripos($job['paper_size'] ?? '', 'A3') !== false);
                $taille = $isA3 ? 'A3' : 'A4';

                if ($mapping) {
                    $mType = strtolower($mapping['machine_type'] ?? '');
                    $mId = intval($mapping['machine_id'] ?? 1);

                    if ($mType === 'photocop') {
                        $photocop = $con_pdo->query("SELECT marque, type_encre FROM photocopieurs WHERE id = $mId")->fetch(PDO::FETCH_ASSOC);
                        if ($photocop) {
                            $marque = $photocop['marque'];
                            $typeEncre = $photocop['type_encre'];
                            $machinePrices = function_exists('getMachinePrices') ? getMachinePrices($con_pdo, $marque) : [];
                            $photocopKey = 'photocop_' . $mId;
                            $prices = $prix_data[$photocopKey] ?? $machinePrices;

                            $costPerFace = 0;
                            $fillMult = $isColor ? ($fillRate / 0.5) : 1.0;

                            if ($typeEncre === 'toner') {
                                if ($isColor) {
                                    $costPerFace += (($prices['cyan']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += (($prices['magenta']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += (($prices['jaune']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += ($prices['noir']['unite'] ?? 0);
                                    $costPerFace += ($prices['tambour']['unite'] ?? 0);
                                    $costPerFace += ($prices['dev']['unite'] ?? 0);
                                } else {
                                    $costPerFace += ($prices['noir']['unite'] ?? 0);
                                    $costPerFace += ($prices['tambour']['unite'] ?? 0);
                                    $costPerFace += ($prices['dev']['unite'] ?? 0);
                                }
                            } else {
                                // ComColor encre
                                if ($isColor) {
                                    $costPerFace += (($prices['bleue']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += (($prices['couleur']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += (($prices['jaune']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += (($prices['rouge']['unite'] ?? 0) * $fillMult);
                                    $costPerFace += ($prices['noire']['unite'] ?? 0);
                                } else {
                                    $costPerFace += ($prices['noire']['unite'] ?? 0);
                                }
                            }

                            $prixPapier = ($taille === 'A4') ? ($prix_data['papier']['A4'] ?? 0.01) : ($prix_data['papier']['A3'] ?? 0.02);
                            if ($taille === 'A4') {
                                $costPerFace = $costPerFace / 2;
                            }

                            $sheetsPerCopy = $isDuplex ? ceil($pagesPerCopy / 2) : $pagesPerCopy;
                            $nbFeuillesTotal = $sheetsPerCopy * $copies;

                            $coutPapier = $nbFeuillesTotal * $prixPapier;
                            $coutEncre = $totalPagesImprimees * $costPerFace;
                            $calcPrice = round($coutPapier + $coutEncre, 2);
                        }
                    } else if ($mType === 'dupli') {
                        $dupliKey = 'dupli_' . $mId;
                        $prixMaster = $prix_data[$dupliKey]['master']['unite'] ?? 0.40;
                        $prixPassage = $prix_data[$dupliKey]['tambour_noir']['unite'] ?? 0.002;
                        $prixPapier = ($taille === 'A4') ? ($prix_data['papier']['A4'] ?? 0.01) : ($prix_data['papier']['A3'] ?? 0.02);

                        if ($taille === 'A4') {
                            $prixMaster = $prixMaster / 2;
                            $prixPassage = $prixPassage / 2;
                        }

                        $nbMasters = max(1, intval($pagesPerCopy));
                        $nbPassages = $totalPagesImprimees;
                        $nbFeuilles = $isDuplex ? ceil($nbPassages / 2) : $nbPassages;

                        $coutMasters = $nbMasters * $prixMaster;
                        $coutPassages = $nbPassages * $prixPassage;
                        $coutPapier = $nbFeuilles * $prixPapier;
                        $calcPrice = round($coutMasters + $coutPassages + $coutPapier, 2);
                    }
                }

                if ($calcPrice <= 0) {
                    $calcPrice = round(max(0.10, $totalPages * 0.05), 2);
                }

                $job['calculated_price'] = $calcPrice;
                // Mettre à jour en base pour ne pas recalculer à chaque appel
                $db->execute("UPDATE print_jobs SET calculated_price = ? WHERE id = ?", [$calcPrice, $job['id']]);
            }
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'jobs' => $jobs, 'total' => count($jobs)]);
        exit;
    }

    if ($action === 'save') {
        // --- 2. ENREGISTREMENT D'UN LOT SÉLECTIONNÉ ---
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        $contact = trim($input['contact'] ?? '');
        if (empty($contact)) {
            throw new Exception("Le nom du contact est obligatoire pour enregistrer le tirage.");
        }

        $jobIds = isset($input['job_ids']) && is_array($input['job_ids']) ? array_map('intval', $input['job_ids']) : [];

        if (empty($jobIds)) {
            throw new Exception("Aucun document sélectionné.");
        }

        $idsList = implode(',', $jobIds);
        $selectedJobs = $con_pdo->query("SELECT * FROM print_jobs WHERE id IN ($idsList)")->fetchAll(PDO::FETCH_ASSOC);

        if (empty($selectedJobs)) {
            throw new Exception("Les documents sélectionnés sont introuvables.");
        }

        // Calcul du prix total global pour ventiler le montant payé (cb = combien ils ont payé)
        $totalOrderPrice = 0.0;
        foreach ($selectedJobs as $j) {
            $totalOrderPrice += floatval($j['calculated_price'] ?? 0);
        }
        $totalOrderPrice = round($totalOrderPrice, 2);

        $paye = (isset($input['paye']) && ($input['paye'] === 'oui' || $input['paye'] === true || $input['paye'] === '1')) ? 'oui' : 'non';
        if ($paye === 'oui') {
            $cbGlobal = (isset($input['cb']) && is_numeric($input['cb'])) ? floatval($input['cb']) : $totalOrderPrice;
        } else {
            $cbGlobal = 0.0;
        }
        $cbRemaining = $cbGlobal;
        $totalItemsCount = count($selectedJobs);
        $currentItemIndex = 0;

        $mot = trim($input['mot'] ?? '');
        $date = time();

        $con_pdo->beginTransaction();

        $savedCount = 0;
        $totalPrice = 0.0;

        foreach ($selectedJobs as $job) {
            $currentItemIndex++;
            $printerName = $job['printer_name'];
            $jobPrice = floatval($job['calculated_price'] ?? 0);
            $docName = $job['document'] ?? 'Document';
            $rawPages = max(1, intval($job['total_pages'] ?? 1));
            $copies = max(1, intval($job['copies'] ?? 1));
            $pagesPerCopy = $rawPages;
            $totalPagesImprimees = $pagesPerCopy * $copies;
            $isDuplex = intval($job['duplex'] ?? 0) === 1;
            $duplexStr = $isDuplex ? 'oui' : 'non';
            $isA3 = (stripos($docName, 'A3') !== false) || (stripos($job['paper_size'] ?? '', 'A3') !== false);
            $taille = $isA3 ? 'A3' : 'A4';
            $thumbUrl = $job['thumbnail_url'] ?? '';

            if ($currentItemIndex === $totalItemsCount) {
                $itemCb = round($cbRemaining, 2);
            } else {
                $itemCb = ($totalOrderPrice > 0) ? round($cbGlobal * ($jobPrice / $totalOrderPrice), 2) : 0.0;
                $cbRemaining -= $itemCb;
            }

            $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);
            $machineType = $mapping ? strtolower($mapping['machine_type'] ?? 'photocop') : 'photocop';
            $machineId = $mapping ? intval($mapping['machine_id'] ?? 1) : 1;

            $jobMot = empty($mot) ? $docName : ($mot . ' - ' . $docName);

            if ($machineType === 'dupli') {
                // Enregistrement dans table dupli
                $dupliMachine = $con_pdo->query("SELECT marque, modele FROM duplicopieurs WHERE id = $machineId")->fetch(PDO::FETCH_ASSOC);
                $nomMachine = $dupliMachine ? ($dupliMachine['marque'] === $dupliMachine['modele'] ? $dupliMachine['marque'] : ($dupliMachine['marque'] . ' ' . $dupliMachine['modele'])) : 'Duplicopieur';

                // Compteurs
                $lastCounters = $con_pdo->query("SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = " . $con_pdo->quote($nomMachine) . " ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $masterAv = $lastCounters ? intval($lastCounters['master_ap']) : 0;
                $passageAv = $lastCounters ? intval($lastCounters['passage_ap']) : 0;

                $nbMasters = max(1, intval($pagesPerCopy));
                $nbPassages = $totalPagesImprimees;
                $masterAp = $masterAv + $nbMasters;
                $passageAp = $passageAv + $nbPassages;

                $tirageGlobalId = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $nomMachine);

                $insertDupli = $con_pdo->prepare("
                    INSERT INTO dupli 
                    (type, contact, master_av, master_ap, passage_av, passage_ap, rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id, tambour, tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertDupli->execute([
                    'duplicopieur',
                    $contact,
                    strval($masterAv),
                    strval($masterAp),
                    strval($passageAv),
                    strval($passageAp),
                    $duplexStr,
                    strval($jobPrice),
                    $paye,
                    strval($itemCb),
                    $jobMot,
                    strval($date),
                    $nomMachine,
                    $machineId,
                    'tambour_noir',
                    $tirageGlobalId,
                    $docName,
                    $thumbUrl,
                    $copies,
                    $taille
                ]);

            } else {
                // Photocopieur (ComColor ou autre) -> table photocop
                $photoMachine = $con_pdo->query("SELECT marque FROM photocopieurs WHERE id = $machineId")->fetch(PDO::FETCH_ASSOC);
                $marque = $photoMachine ? $photoMachine['marque'] : 'comcolor';

                $sheetsPerCopy = $isDuplex ? ceil($pagesPerCopy / 2) : $pagesPerCopy;
                $nbFeuillesTotal = $sheetsPerCopy * $copies;

                $tirageGlobalId = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $marque);

                $insertPhoto = $con_pdo->prepare("
                    INSERT INTO photocop 
                    (type, marque, contact, nb_f, rv, prix, paye, cb, mot, date, tirage_global_id, document_name, thumbnail_url, nb_exemplaires, taille)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertPhoto->execute([
                    'photocopieur',
                    $marque,
                    $contact,
                    strval($nbFeuillesTotal),
                    $duplexStr,
                    strval($jobPrice),
                    $paye,
                    strval($itemCb),
                    $jobMot,
                    strval($date),
                    $tirageGlobalId,
                    $docName,
                    $thumbUrl,
                    $copies,
                    $taille
                ]);
            }

            // Clôturer le job dans recorded_print_jobs (INSERT OR REPLACE pour écraser les anciens IDs recyclés)
            $mark = $con_pdo->prepare("INSERT OR REPLACE INTO recorded_print_jobs (job_id, printer_name, print_job_id, recorded_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
            $mark->execute([strval($job['job_id']), $printerName, $job['id']]);

            // Supprimer définitivement le job du tapis (print_jobs)
            $con_pdo->prepare("DELETE FROM print_jobs WHERE id = ?")->execute([$job['id']]);

            $savedCount++;
            $totalPrice += $jobPrice;
        }

        $con_pdo->commit();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => "Tirage enregistré avec succès pour $contact ($totalPrice €)",
            'contact' => $contact,
            'total_price' => round($totalPrice, 2),
            'paye' => $paye,
            'cb' => round($cbGlobal, 2),
            'saved_count' => $savedCount
        ]);
        exit;
    }

    if ($action === 'delete') {
        // --- 3. SUPPRESSION / REJET DES JOBS SÉLECTIONNÉS ---
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $jobIds = isset($input['job_ids']) && is_array($input['job_ids']) ? array_map('intval', $input['job_ids']) : [];

        if (empty($jobIds)) {
            throw new Exception("Aucun job sélectionné pour la suppression.");
        }

        $idsString = implode(',', $jobIds);

        // Récupérer les fichiers miniatures / documents pour nettoyage sécurisé
        $jobsWithFiles = $db->select("SELECT document, thumbnail_url FROM print_jobs WHERE id IN ($idsString)");

        foreach ($jobsWithFiles as $job) {
            if (!empty($job['document'])) {
                secure_delete($job['document']);
            }
            if (!empty($job['thumbnail_url'])) {
                $baseDir = dirname(__DIR__) . '/public/';
                $relPath = ltrim(parse_url($job['thumbnail_url'], PHP_URL_PATH), '/');
                $fullThumb = str_replace('/', DIRECTORY_SEPARATOR, $baseDir . $relPath);
                if (file_exists($fullThumb)) {
                    secure_delete($fullThumb);
                }
            }
        }

        $db->execute("DELETE FROM print_jobs WHERE id IN ($idsString)");

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => count($jobIds) . ' document(s) supprimé(s) du tapis de tri.'
        ]);
        exit;
    }

    throw new Exception("Action non reconnue : " . htmlspecialchars($action));

} catch (Throwable $e) {
    if (isset($con_pdo) && $con_pdo->inTransaction()) {
        $con_pdo->rollBack();
    }
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
