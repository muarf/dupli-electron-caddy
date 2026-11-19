<?php
/**
 * Fonctions partagées pour le calcul des prix et la résolution des machines d'impression
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tirage.php';

/**
 * Résout le nom et le type officiel de machine (comcolor / Duplicopieur) via printer_mappings ou les tables machines
 */
function resolveJobMachineInfo($con_pdo, $db, $job) {
    $machineName = trim(strval($job['machine_name'] ?? ''));
    $machineType = trim(strval($job['machine_type'] ?? ''));
    $machineId = intval($job['machine_id'] ?? 0);
    $printerName = trim(strval($job['printer_name'] ?? ''));

    // 1. Si machine_name est renseigné et n'est pas le nom brut du spouleur Windows
    if (!empty($machineName) && $machineName !== $printerName) {
        try {
            $stmt = $con_pdo->prepare("SELECT marque FROM photocopieurs WHERE marque = ?");
            $stmt->execute([$machineName]);
            $p = $stmt->fetchColumn();
            if ($p) {
                return ['type' => 'photocop', 'name' => $p, 'id' => $machineId ?: 1];
            }

            $stmtD = $con_pdo->prepare("SELECT id, marque, modele FROM duplicopieurs WHERE marque = ? OR (marque || ' ' || modele) = ?");
            $stmtD->execute([$machineName, $machineName]);
            $d = $stmtD->fetch(PDO::FETCH_ASSOC);
            if ($d) {
                $nom = ($d['marque'] === $d['modele']) ? $d['marque'] : ($d['marque'] . ' ' . $d['modele']);
                return ['type' => 'dupli', 'name' => $nom, 'id' => intval($d['id'])];
            }
        } catch (Throwable $e) {}

        // S'il a été explicitement défini (ex: dans les tests ou saisie manuelle), le respecter
        return [
            'type' => !empty($machineType) ? $machineType : 'photocop',
            'name' => $machineName,
            'id' => $machineId ?: 1
        ];
    }

    // 2. Si machine_id et machine_type sont valides
    if ($machineId > 0 && !empty($machineType)) {
        try {
            if ($machineType === 'photocop') {
                $stmt = $con_pdo->prepare("SELECT marque FROM photocopieurs WHERE id = ?");
                $stmt->execute([$machineId]);
                $p = $stmt->fetchColumn();
                if ($p) {
                    return ['type' => 'photocop', 'name' => $p, 'id' => $machineId];
                }
            } elseif ($machineType === 'dupli') {
                $stmtD = $con_pdo->prepare("SELECT id, marque, modele FROM duplicopieurs WHERE id = ?");
                $stmtD->execute([$machineId]);
                $d = $stmtD->fetch(PDO::FETCH_ASSOC);
                if ($d) {
                    $nom = ($d['marque'] === $d['modele']) ? $d['marque'] : ($d['marque'] . ' ' . $d['modele']);
                    return ['type' => 'dupli', 'name' => $nom, 'id' => $machineId];
                }
            }
        } catch (Throwable $e) {}
    }

    // 3. Résoudre via printer_mappings à partir de printer_name
    if (!empty($printerName)) {
        try {
            $mapping = $db->selectOne("SELECT * FROM printer_mappings WHERE system_printer_name = ?", [$printerName]);
            if ($mapping) {
                $mType = $mapping['machine_type'] ?? 'photocop';
                $mId = intval($mapping['machine_id'] ?? 1);
                if ($mType === 'dupli') {
                    $stmtD = $con_pdo->prepare("SELECT id, marque, modele FROM duplicopieurs WHERE id = ?");
                    $stmtD->execute([$mId]);
                    $d = $stmtD->fetch(PDO::FETCH_ASSOC);
                    if ($d) {
                        $nom = ($d['marque'] === $d['modele']) ? $d['marque'] : ($d['marque'] . ' ' . $d['modele']);
                        return ['type' => 'dupli', 'name' => $nom, 'id' => $mId];
                    }
                } else {
                    $stmt = $con_pdo->prepare("SELECT marque FROM photocopieurs WHERE id = ?");
                    $stmt->execute([$mId]);
                    $p = $stmt->fetchColumn();
                    if ($p) {
                        return ['type' => 'photocop', 'name' => $p, 'id' => $mId];
                    }
                }
            }
        } catch (Throwable $e) {}
    }

    // 4. Repli par défaut : premier photocopieur actif en base
    try {
        $p = $con_pdo->query("SELECT id, marque FROM photocopieurs WHERE actif = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($p && !empty($p['marque'])) {
            return ['type' => 'photocop', 'name' => $p['marque'], 'id' => intval($p['id'])];
        }
    } catch (Throwable $e) {}

    return ['type' => 'photocop', 'name' => 'comcolor', 'id' => 1];
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
