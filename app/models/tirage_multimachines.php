<?php

require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/pricing.php';
require_once __DIR__ . '/../controler/functions/tirage.php';
require_once __DIR__ . '/../controler/functions/i18n.php';
require_once __DIR__ . '/../controler/functions/SpoolManager.php';

// Gestion AJAX pour récupérer les tambours d'un duplicopieur
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_tambours' && isset($_GET['duplicopieur_id'])) {
    $duplicopieur_id = intval($_GET['duplicopieur_id']);

    try {
        $con = pdo_connect();
        $db = pdo_connect();

        $query = $db->prepare('SELECT tambours FROM duplicopieurs WHERE id = ? AND actif = 1');
        $query->execute([$duplicopieur_id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);

        $tambours = ['tambour_noir']; // Fallback par défaut

        if ($result && !empty($result['tambours'])) {
            try {
                $tambours = json_decode($result['tambours'], true);
                if (!is_array($tambours)) {
                    $tambours = ['tambour_noir'];
                }
            } catch (Exception $e) {
                $tambours = ['tambour_noir'];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'tambours' => $tambours]);
        exit;

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Gestion AJAX pour récupérer les compteurs d'une machine
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_last_counters' && isset($_GET['machine'])) {
    $machine = $_GET['machine'];

    try {
        $con = pdo_connect();
        $db = pdo_connect();

        // Vérifier si c'est un duplicopieur ou un photocopieur (SQLite compatible)
        $query = $db->prepare('SELECT COUNT(*) as count FROM duplicopieurs WHERE (TRIM(marque) || " " || TRIM(modele) = ? OR marque = ?) AND actif = 1');
        $query->execute([$machine, $machine]);
        $is_duplicopieur = $query->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        if ($is_duplicopieur) {
            // C'est un duplicopieur, récupérer les compteurs depuis la table dupli
            $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
            $query_counters->execute([$machine]);
            $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
            $query_counters->closeCursor(); // Bonne pratique : fermer le curseur

            if ($last_counters) {
                $counters = [
                    'master_av' => ceil($last_counters['master_ap']),
                    'passage_av' => ceil($last_counters['passage_ap'])
                ];
            } else {
                $counters = ['master_av' => 0, 'passage_av' => 0];
            }
        } else {
            // C'est un photocopieur, utiliser la fonction existante
            $counters = get_last_counters_photocop($machine);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'counters' => $counters]);
        exit;

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/**
 * Fonctions utilitaires pour les prix des photocopieurs
 */
function getMachinePrices($db, $machine_name)
{
    // CORRECTION DEADLOCK : Utiliser la connexion passée en paramètre (pas de nouvelle connexion pendant une transaction)

    // Déterminer la clé de la machine selon la nouvelle structure
    $machine_type = '';
    $machine_id = 0;

    // Vérifier si c'est un photocopieur
    $query1 = $db->prepare('SELECT id, type_encre FROM photocopieurs WHERE marque = ? AND actif = 1');
    $query1->execute([$machine_name]);
    $photocop = $query1->fetch(PDO::FETCH_ASSOC);
    $query1->closeCursor(); // CORRECTION CRITIQUE : Fermer le curseur avant la prochaine requête

    error_log("DEBUG getMachinePrices - machine_name: $machine_name");
    error_log("DEBUG getMachinePrices - photocop trouvé: id=" . ($photocop['id'] ?? 'N/A') . ", type_encre=" . ($photocop['type_encre'] ?? 'N/A'));

    if ($photocop) {
        // C'est un photocopieur
        $machine_type = 'photocop';
        $machine_id = $photocop['id'];
        error_log("DEBUG getMachinePrices - machine_type: $machine_type, machine_id: $machine_id");
    } else {
        // Pour les duplicopieurs, utiliser dupli_1
        $machine_type = 'dupli';
        $machine_id = 1;
        error_log("DEBUG getMachinePrices - Pas de photocopieur trouvé, utilisation dupli_1");
    }

    $query2 = $db->prepare('SELECT type, unite, pack FROM prix WHERE machine_type = ? AND machine_id = ?');
    $query2->execute([$machine_type, $machine_id]);
    $prices = [];

    error_log("DEBUG getMachinePrices - Requête prix: machine_type=$machine_type, machine_id=$machine_id");

    // CORRECTION DEADLOCK : Utiliser fetchAll() pour libérer immédiatement le curseur SQLite
    $rows = $query2->fetchAll(PDO::FETCH_ASSOC);
    $query2->closeCursor(); // Fermer explicitement
    foreach ($rows as $row) {
        $prices[$row['type']] = [
            'unite' => floatval($row['unite']),
            'pack' => floatval($row['pack'])
        ];
        error_log("DEBUG getMachinePrices - Prix ajouté: " . $row['type'] . " = " . $row['unite']);
    }

    error_log("DEBUG getMachinePrices - Prix finaux: " . count($prices) . " éléments");

    return $prices;
}

/**
 * Fonction optimisée pour calculer le prix d'une brochure photocopieur
 * Évite les requêtes DB répétées et les logs excessifs
 */
function calculateBrochurePriceOptimized($brochure, $prix_papier_a3, $prix_papier_a4, $machine_prices, $machine_type_detected, $machine_name, $fill_rate = 0.5)
{
    $nb_exemplaires = intval($brochure['nb_exemplaires']);
    $nb_feuilles = intval($brochure['nb_feuilles']);
    $nb_f_total = $nb_exemplaires * $nb_feuilles;
    $taille = $brochure['taille'];
    $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui';
    $couleur = isset($brochure['couleur']) && $brochure['couleur'] == 'oui';
    $feuilles_payees = isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui';

    // Calcul rapide
    if (isset($brochure['nb_pages'])) {
        $nb_p = $nb_exemplaires * floatval($brochure['nb_pages']);
    } else {
        $nb_p = $rv ? $nb_f_total * 2 : $nb_f_total;
    }
    $prix_papier = ($taille == 'A4') ? $prix_papier_a4 : $prix_papier_a3;
    $prix_papier_total = $feuilles_payees ? 0 : ($nb_f_total * $prix_papier);

    // Calcul coût par page optimisé avec taux de remplissage
    try {
        $cost_per_page = calculatePageCost($machine_name, $machine_type_detected, $machine_prices, $couleur, $rv, $fill_rate);
    } catch (Exception $e) {
        $cost_per_page = 0.01; // Prix de secours
    }

    // Ajuster selon la taille
    if ($taille === 'A4')
        $cost_per_page = $cost_per_page / 2;

    $prix_encre_total = $nb_p * $cost_per_page;
    return $prix_papier_total + $prix_encre_total;
}

function determineMachineType($db, $machine_name)
{
    // CORRECTION DEADLOCK : Utiliser la connexion passée en paramètre (pas de nouvelle connexion pendant une transaction)

    // Vérifier si c'est un photocopieur
    $query_type1 = $db->prepare('SELECT id, type_encre FROM photocopieurs WHERE marque = ? AND actif = 1');
    $query_type1->execute([$machine_name]);
    $photocop = $query_type1->fetch(PDO::FETCH_ASSOC);
    $query_type1->closeCursor(); // CORRECTION CRITIQUE : Fermer le curseur avant la prochaine requête

    if ($photocop) {
        // C'est un photocopieur, utiliser le type_encre de la table
        return $photocop['type_encre'];
    } else {
        // Pour les duplicopieurs, utiliser dupli_1
        $machine_type = 'dupli';
        $machine_id = 1;

        $query_type2 = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine_type = ? AND machine_id = ? AND type IN ("tambour", "dev")');
        $query_type2->execute([$machine_type, $machine_id]);
        $result = $query_type2->fetch(PDO::FETCH_ASSOC);
        $query_type2->closeCursor(); // CORRECTION CRITIQUE : Fermer le curseur

        return ($result['count'] > 0) ? 'toner' : 'encre';
    }
}

function calculatePageCost($machine_name, $machine_type, $prices, $is_color, $is_duplex, $fill_rate = 0.5, $return_breakdown = false)
{
    error_log("DEBUG calculatePageCost - ENTREE avec prix fixes, fill_rate=$fill_rate, is_color=" . ($is_color ? 'OUI' : 'NON'));

    $fixed_cost = 0;
    $variable_cost = 0;

    // Calculer le multiplicateur de taux de remplissage (50% = ×1, 100% = ×2)
    // Pivot à 50% : 0.5 -> 1.0x, 0.25 -> 0.5x, 0.75 -> 1.5x
    $fill_rate_multiplier = $is_color ? ($fill_rate / 0.5) : 1.0;

    try {
        if ($machine_type === 'toner') {
            error_log("DEBUG calculatePageCost - BRANCHE TONER");
            if ($is_color) {
                // Couleur : cyan + magenta + yellow (AVEC taux de remplissage)
                $variable_cost += ($prices['cyan']['unite'] ?? 0);
                $variable_cost += ($prices['magenta']['unite'] ?? 0);
                // Support both 'jaune' (DB) and 'yellow' (UI convention)
                $variable_cost += ($prices['jaune']['unite'] ?? $prices['yellow']['unite'] ?? 0);
                
                // Le noir, le tambour et le dev sont fixes (pas de taux)
                $fixed_cost += ($prices['noir']['unite'] ?? 0);
                $fixed_cost += ($prices['tambour']['unite'] ?? 0);
                $fixed_cost += ($prices['dev']['unite'] ?? 0);
            } else {
                // Noir et blanc : noir + tambour + dev (pas de taux de remplissage)
                $fixed_cost += ($prices['noir']['unite'] ?? 0);
                $fixed_cost += ($prices['tambour']['unite'] ?? 0);
                $fixed_cost += ($prices['dev']['unite'] ?? 0);
            }
        } else {
            error_log("DEBUG calculatePageCost - BRANCHE ENCRE");
            if ($is_color) {
                // Couleur : bleue + couleur + jaune + rouge (AVEC taux de remplissage)
                $variable_cost += ($prices['bleue']['unite'] ?? 0);
                $variable_cost += ($prices['couleur']['unite'] ?? 0);
                $variable_cost += ($prices['jaune']['unite'] ?? 0);
                $variable_cost += ($prices['rouge']['unite'] ?? 0);
                
                // Le noir reste fixe
                $fixed_cost += ($prices['noire']['unite'] ?? 0);
            } else {
                // Noir et blanc : seulement noire (pas de taux de remplissage)
                $fixed_cost += ($prices['noire']['unite'] ?? 0);
            }
        }

        if ($return_breakdown) {
            return [
                'fixed_cost' => $fixed_cost,
                'variable_cost_base' => $variable_cost,
                'variable_cost_adjusted' => $variable_cost * $fill_rate_multiplier,
                'multiplier' => $fill_rate_multiplier,
                'total' => $fixed_cost + ($variable_cost * $fill_rate_multiplier)
            ];
        }

        $total = $fixed_cost + ($variable_cost * $fill_rate_multiplier);
        error_log("DEBUG calculatePageCost - COÛT FINAL: $total (fixed: $fixed_cost, variable_base: $variable_cost, multiplier: $fill_rate_multiplier)");
        return $total;

    } catch (Exception $e) {
        error_log("DEBUG calculatePageCost - ERREUR: " . $e->getMessage());
        return $return_breakdown ? ['fixed_cost' => 0.01, 'variable_cost_base' => 0, 'variable_cost_adjusted' => 0, 'multiplier' => 1, 'total' => 0.01] : 0.01;
    }
}

function Action($conf = null)
{
    error_log("=== NOUVEAU TEST MULTIMACHINES " . date('Y-m-d H:i:s') . " ===");
    error_log("=== TEST LOG SIMPLE " . date('H:i:s') . " ===");
    error_log("=== POST DATA DEBUG - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("=== POST DATA DEBUG - POST count: " . count($_POST));
    error_log("=== POST DATA DEBUG - POST keys: " . implode(', ', array_keys($_POST)));
    error_log("=== POST DATA DEBUG - POST content: " . substr(serialize($_POST), 0, 500));
    $con = pdo_connect();
    $array = array();
    $array['errors'] = array();
    $array['contact'] = '';
    $array['machines'] = array();
    $array['prix_total'] = 0;

    // Debug seulement si demandé dans l'URL
    if (isset($_GET['debug'])) {
        $array['debug']['test'] = "DEBUG ACTIVÉ - " . date('H:i:s');
    }

    // Debug: vérifier si on est en POST (seulement si debug dans l'URL)
    if (isset($_GET['debug']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $array['debug']['post'] = "POST détecté";

        // Debug: vérifier si on a le bouton 'ok'
        if (isset($_POST['ok'])) {
            $array['debug']['ok'] = "Bouton 'ok' détecté";
        } else {
            $array['debug']['ok'] = "Bouton 'ok' NON détecté";
        }

        // Debug: vérifier si on a le bouton 'enregistrer'
        if (isset($_POST['enregistrer'])) {
            $array['debug']['enregistrer'] = "Bouton 'enregistrer' détecté";
        } else {
            $array['debug']['enregistrer'] = "Bouton 'enregistrer' NON détecté";
        }

        // Debug: vérifier si on a des machines
        if (isset($_POST['machines'])) {
            $array['debug']['machines'] = "Machines détectées: " . count($_POST['machines']);
        } else {
            $array['debug']['machines'] = "Aucune machine détectée";
        }

        // Debug: afficher toutes les clés POST
        $array['debug']['post_keys'] = "Clés POST: " . implode(', ', array_keys($_POST));
    }

    // Récupérer la liste des duplicopieurs actifs
    try {
        $db = pdo_connect();
        $query = $db->query('SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele');
        $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);

        // Debug: vérifier les duplicopieurs récupérés (seulement si debug dans l'URL)
        // Debug des duplicopieurs (seulement si debug dans l'URL)
        if (isset($_GET['debug'])) {
            $array['debug']['duplicopieurs_count'] = count($duplicopieurs);
            $array['debug']['duplicopieurs_data'] = $duplicopieurs;
        }

        // Parser les tambours pour chaque duplicopieur
        foreach ($duplicopieurs as $index => $dup) {
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
            $duplicopieurs[$index]['tambours_parsed'] = $tambours;
        }

        // Debug après traitement (seulement si debug dans l'URL)
        if (isset($_GET['debug'])) {
            $array['debug']['duplicopieurs_after_processing'] = $duplicopieurs;
        }
        $array['duplicopieurs'] = $duplicopieurs;

        // Si un seul duplicopieur, le sélectionner automatiquement
        if (count($array['duplicopieurs']) == 1) {
            $array['duplicopieur_selectionne'] = $array['duplicopieurs'][0];
        }
    } catch (Exception $e) {
        $array['duplicopieurs'] = array();
        $array['duplicopieur_selectionne'] = null;
    }

    // Récupérer la liste des photocopieurs disponibles (exclure les duplicopieurs)
    $duplicopieurs_names = [];
    foreach ($array['duplicopieurs'] as $dup) {
        $machine_name = $dup['marque'] . ' ' . $dup['modele'];
        if ($dup['marque'] === $dup['modele']) {
            $machine_name = $dup['marque'];
        }
        $duplicopieurs_names[] = $machine_name;
    }

    $photocopiers = [];
    if (!empty($duplicopieurs_names)) {
        $placeholders = str_repeat('?,', count($duplicopieurs_names) - 1) . '?';
        $query = $db->prepare("SELECT DISTINCT marque FROM photocopieurs WHERE marque NOT IN ($placeholders) AND actif = 1");
        $query->execute($duplicopieurs_names);
        $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
    } else {
        $query = $db->query('SELECT DISTINCT marque FROM photocopieurs WHERE actif = 1');
        $photocopiers = $query->fetchAll(PDO::FETCH_OBJ);
    }
    $array['photocopiers'] = $photocopiers;

    // Récupérer automatiquement les valeurs "AVANT" pour le duplicopieur par défaut
    if (isset($array['duplicopieur_selectionne'])) {
        // Construire le nom de la machine duplicopieur sélectionnée
        $machine_name = $array['duplicopieur_selectionne']['marque'] . ' ' . $array['duplicopieur_selectionne']['modele'];
        if ($array['duplicopieur_selectionne']['marque'] === $array['duplicopieur_selectionne']['modele']) {
            $machine_name = $array['duplicopieur_selectionne']['marque'];
        }

        // Récupérer les derniers compteurs pour cette machine spécifique
        $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
        $query_counters->execute([$machine_name]);
        $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
        $query_counters->closeCursor(); // Bonne pratique : fermer le curseur

        if ($last_counters) {
            $array['master_av'] = ceil($last_counters['master_ap']);
            $array['passage_av'] = ceil($last_counters['passage_ap']);
        } else {
            $array['master_av'] = 0;
            $array['passage_av'] = 0;
        }
    } else {
        $array['master_av'] = 0;
        $array['passage_av'] = 0;
    }

    // Récupérer les prix depuis la base de données
    $array['prix_data'] = get_price();

    // CHARGEMENT DES JOBS STAGÉS SI SESSION_ID PRÉSENT
    // Si on a un session_id dans l'URL et qu'on n'est PAS en train de POST (confirmation ou enregistrement),
    // alors charger les jobs stagés depuis print_jobs pour pré-remplir le formulaire
    if (isset($_GET['session_id']) && !isset($_POST['ok']) && !isset($_POST['enregistrer'])) {
        $session_id_to_load = intval($_GET['session_id']);
        
        if ($session_id_to_load > 0) {
            try {
                error_log("DEBUG - Chargement des jobs stagés pour session_id=$session_id_to_load");
                
                // Charger les jobs stagés
                $stmt_staged = $db->prepare("
                    SELECT 
                        id, job_id, document, printer_name, total_pages, copies,
                        fill_rate, color_mode, duplex, paper_size, thumbnail_url,
                        calculated_price, machine_type, machine_id, machine_name, contact
                    FROM print_jobs
                    WHERE session_id = ? AND staged = 1
                    ORDER BY created_at ASC
                ");
                $stmt_staged->execute([$session_id_to_load]);
                $staged_jobs = $stmt_staged->fetchAll(PDO::FETCH_ASSOC);
                $stmt_staged->closeCursor();
                
                if (!empty($staged_jobs)) {
                    error_log("DEBUG - Trouvé " . count($staged_jobs) . " jobs stagés");
                    
                    // Utiliser le contact du premier job
                    $array['contact'] = $staged_jobs[0]['contact'] ?? '';
                    $array['session_id'] = $session_id_to_load;
                    
                    // Transformer chaque job en machine
                    foreach ($staged_jobs as $job) {
                        $machine = [
                            'db_id' => $job['id'],
                            'job_id' => $job['job_id'],
                            'document_name' => $job['document'],
                            'thumbnail_url' => $job['thumbnail_url'],
                            'contact' => $job['contact'],
                            'fill_rate' => $job['fill_rate'],
                            'prix' => $job['calculated_price']
                        ];
                        
                        if ($job['machine_type'] === 'dupli') {
                            // Job de duplicopieur
                            $machine['type'] = 'duplicopieur';
                            $machine['machine_id'] = $job['machine_id'];
                            $machine['duplicopieur_id'] = $job['machine_id'];
                            $machine['machine'] = $job['machine_name'];
                            
                            // Estimer les valeurs (approximation)
                            $machine['nb_masters'] = ceil($job['total_pages'] / $job['copies']);
                            $machine['nb_passages'] = $job['total_pages'];
                            $machine['rv'] = $job['duplex'] ? 'oui' : 'non';
                            $machine['A4'] = (stripos($job['paper_size'], 'A4') !== false) ? 'A4' : 'A3';
                            $machine['feuilles_payees'] = 'non';
                            $machine['mode_saisie'] = 'manuel';
                            
                            // Récupérer les derniers compteurs pour cette machine
                            $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                            $query_counters->execute([$job['machine_name']]);
                            $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
                            $query_counters->closeCursor();
                            
                            if ($last_counters) {
                                $machine['master_av'] = ceil($last_counters['master_ap']);
                                $machine['passage_av'] = ceil($last_counters['passage_ap']);
                                $machine['master_ap'] = $machine['master_av'] + $machine['nb_masters'];
                                $machine['passage_ap'] = $machine['passage_av'] + $machine['nb_passages'];
                            } else {
                                $machine['master_av'] = 0;
                                $machine['passage_av'] = 0;
                                $machine['master_ap'] = $machine['nb_masters'];
                                $machine['passage_ap'] = $machine['nb_passages'];
                            }
                            
                        } else if ($job['machine_type'] === 'photocop') {
                            // Job de photocopieur
                            $machine['type'] = 'photocopieur';
                            $machine['machine_id'] = $job['machine_id'];
                            $machine['machine'] = $job['machine_name'];
                            
                            // Créer une brochure unique
                            $is_color = (stripos($job['color_mode'], 'color') !== false);
                            $is_duplex = $job['duplex'];
                            $taille = (stripos($job['paper_size'], 'A4') !== false) ? 'A4' : 'A3';
                            
                            $nb_exemplaires = $job['copies'];
                            $pages_per_copy = $job['total_pages'] / $nb_exemplaires;
                            $nb_feuilles = $is_duplex ? ceil($pages_per_copy / 2) : $pages_per_copy;
                            
                            $machine['brochures'] = [[
                                'nb_exemplaires' => $nb_exemplaires,
                                'nb_feuilles' => $nb_feuilles,
                                'nb_pages' => $pages_per_copy,
                                'taille' => $taille,
                                'rv' => $is_duplex ? 'oui' : 'non',
                                'couleur' => $is_color ? 'oui' : 'non',
                                'feuilles_payees' => 'non'
                            ]];
                        } else {
                            // Type inconnu, on saute
                            continue;
                        }
                        
                        $array['machines'][] = $machine;
                        $array['prix_total'] += floatval($job['calculated_price']);
                    }
                    
                    error_log("DEBUG - Jobs stagés chargés et transformés en " . count($array['machines']) . " machines");
                }
            } catch (Exception $e) {
                error_log("ERREUR chargement jobs stagés: " . $e->getMessage());
            }
        }
    }

    // Debug pour comprendre pourquoi la condition ne fonctionne pas (seulement si debug dans l'URL)
    if (isset($_GET['debug'])) {
        $array['debug']['post_check'] = "Contact isset: " . (isset($_POST['contact']) ? 'OUI' : 'NON') .
            " | OK isset: " . (isset($_POST['ok']) ? 'OUI' : 'NON') .
            " | OK value: '" . ($_POST['ok'] ?? 'UNDEFINED') . "'";
    }

    // Traitement des données POST - Affichage de la page de confirmation
    if (isset($_POST['contact']) && isset($_POST['ok'])) {
        error_log("DEBUG - ENTREE DANS CONFIRMATION (bouton ok)");
        if (isset($_GET['debug'])) {
            $array['debug']['confirmation'] = "ENTRÉE DANS LA CONFIRMATION - " . date('H:i:s');
        }
        // Définir les machines pour l'affichage du formulaire
        $array['machines'] = $_POST['machines'];
        $array['contact'] = addslashes($_POST['contact']);
        $array['session_id'] = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;

        // Calculer le prix total pour l'affichage
        $array['prix_total'] = 0;
        if (isset($_GET['debug'])) {
            $array['debug']['machines_count'] = "Nombre de machines à traiter: " . count($_POST['machines']);
        }


        foreach ($_POST['machines'] as $index => $machine) {
            $array['debug']['machine_' . $index] = "Machine " . $index . " - Type: " . $machine['type'];
            if ($machine['type'] === 'duplicopieur') {
                // Calcul duplicopieur
                $mode_saisie = $machine['mode_saisie'] ?? 'compteurs';

                if ($mode_saisie === 'compteurs') {
                    // Mode compteurs
                    $master_av = ceil(floatval($machine['master_av'] ?? 0));
                    $master_ap = ceil(floatval($machine['master_ap'] ?? 0));
                    $passage_av = ceil(floatval($machine['passage_av'] ?? 0));
                    $passage_ap = ceil(floatval($machine['passage_ap'] ?? 0));

                    $nb_masters = max(0, $master_ap - $master_av);
                    $nb_passages = max(0, $passage_ap - $passage_av);
                } else {
                    // Mode manuel
                    $nb_masters = ceil(floatval($machine['nb_masters'] ?? 0));
                    $nb_passages = ceil(floatval($machine['nb_passages'] ?? 0));
                }

                // Calculer nb_f selon les options
                $nb_f = $nb_passages;
                if (isset($machine['rv']) && $machine['rv'] == 'oui') {
                    $nb_f = $nb_f / 2;
                }
                if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui') {
                    $nb_f = 0;
                }

                // Déterminer la taille
                $taille = 'A3';
                if (isset($machine['A4']) && $machine['A4'] == 'A4') {
                    $taille = 'A4';
                }

                // Récupérer les prix
                $prix_data = get_price();
                $prix_master = 0;
                $prix_passage = 0;
                $prix_papier = 0;

                // NOUVELLE STRUCTURE : Utiliser l'ID du duplicopieur sélectionné
                $duplicopieur_id = $machine['duplicopieur_id'] ?? $array['duplicopieur_selectionne']['id']; // Utiliser l'ID du duplicopieur sélectionné
                $machine_key = 'dupli_' . $duplicopieur_id;
                $prix_master = $prix_data[$machine_key]['master']['unite'] ?? 0;

                // Prix des passages selon le tambour sélectionné (comme le JavaScript)
                $tambour_selected = $machine['tambour'] ?? '';
                $prix_passage = 0;

                // Debug
                error_log("DEBUG tirage_multimachines: machine_key=$machine_key, tambour_selected=$tambour_selected");
                error_log("DEBUG tirage_multimachines: prix_data structure: " . print_r($prix_data[$machine_key] ?? 'NOT_FOUND', true));

                if (!empty($tambour_selected) && isset($prix_data[$machine_key][$tambour_selected]['unite'])) {
                    $prix_passage = $prix_data[$machine_key][$tambour_selected]['unite'];
                    error_log("DEBUG tirage_multimachines: Using tambour_selected price: $prix_passage");
                } elseif (isset($prix_data[$machine_key]['tambour_noir']['unite'])) {
                    // Fallback sur le tambour noir si pas de tambour spécifique
                    $prix_passage = $prix_data[$machine_key]['tambour_noir']['unite'];
                    error_log("DEBUG tirage_multimachines: Using tambour_noir fallback price: $prix_passage");
                } else {
                    error_log("DEBUG tirage_multimachines: No price found for machine_key=$machine_key");
                }

                // Prix du papier selon la taille
                if ($taille === 'A3') {
                    $prix_papier = $prix_data['papier']['A3'] ?? 0;
                } else {
                    $prix_papier = $prix_data['papier']['A4'] ?? 0;
                }

                // NOUVELLE LOGIQUE : A4 = A3/2 pour masters et passages
                if ($taille === 'A4') {
                    $prix_master = $prix_master / 2;
                    $prix_passage = $prix_passage / 2;
                }

                // Calculer le prix total
                $prix_total = ($nb_masters * $prix_master) + ($nb_passages * $prix_passage) + ($nb_f * $prix_papier);
                $array['machines'][$index]['prix'] = round($prix_total, 2);
                $array['machines'][$index]['nb_masters'] = $nb_masters;
                $array['machines'][$index]['nb_passages'] = $nb_passages;

                // Calculer les valeurs avant/après pour l'enregistrement
                if ($mode_saisie === 'compteurs') {
                    // Mode compteurs - utiliser les valeurs du formulaire
                    $array['machines'][$index]['master_av'] = $master_av;
                    $array['machines'][$index]['master_ap'] = $master_ap;
                    $array['machines'][$index]['passage_av'] = $passage_av;
                    $array['machines'][$index]['passage_ap'] = $passage_ap;
                } else {
                    // Mode manuel - calculer à partir des dernières valeurs
                    // Utiliser la machine duplicopieur sélectionnée
                    if (isset($array['duplicopieur_selectionne'])) {
                        $machine_name = $array['duplicopieur_selectionne']['marque'] . ' ' . $array['duplicopieur_selectionne']['modele'];
                        if ($array['duplicopieur_selectionne']['marque'] === $array['duplicopieur_selectionne']['modele']) {
                            $machine_name = $array['duplicopieur_selectionne']['marque'];
                        }

                        $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                        $query_counters->execute([$machine_name]);
                        $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
                        $query_counters->closeCursor(); // CORRECTION DEADLOCK : Fermer le curseur SQLite

                        if ($last_counters) {
                            $master_av = ceil($last_counters['master_ap']);
                            $passage_av = ceil($last_counters['passage_ap']);
                        } else {
                            $master_av = 0;
                            $passage_av = 0;
                        }
                    } else {
                        $master_av = 0;
                        $passage_av = 0;
                    }

                    $array['machines'][$index]['master_av'] = $master_av;
                    $array['machines'][$index]['master_ap'] = $master_av + $nb_masters;
                    $array['machines'][$index]['passage_av'] = $passage_av;
                    $array['machines'][$index]['passage_ap'] = $passage_av + $nb_passages;
                }

                $array['prix_total'] += $prix_total;
            } else if ($machine['type'] === 'photocopieur') {
                // Calcul photocopieur
                error_log("DEBUG CONFIRMATION - DEBUT photocopieur index=$index, machine=" . ($machine['machine'] ?? 'N/A'));
                $prix_total = 0;
                if (isset($_GET['debug'])) {
                    $array['debug']['photocopieur_' . $index] = "Machine " . $index . " (photocopieur) détectée";
                }

                // OPTIMISATION : Récupérer les prix UNE SEULE FOIS avant la boucle (comme dans l'enregistrement)
                error_log("DEBUG CONFIRMATION - AVANT getMachinePrices");
                $machine_prices = getMachinePrices($db, $machine['machine']);
                error_log("DEBUG CONFIRMATION - APRES getMachinePrices, AVANT determineMachineType");
                $machine_type_detected = determineMachineType($db, $machine['machine']);
                error_log("DEBUG CONFIRMATION - APRES determineMachineType");

                if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                    if (isset($_GET['debug'])) {
                        $array['debug']['photocopieur_' . $index] .= " - Brochures trouvées: " . count($machine['brochures']);
                    }
                    foreach ($machine['brochures'] as $brochure_index => $brochure) {
                        if (isset($_GET['debug'])) {
                            $array['debug']['photocopieur_' . $index] .= " - Brochure " . $brochure_index . ": " . print_r($brochure, true);
                        }

                        if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                            $nb_exemplaires = intval($brochure['nb_exemplaires']);
                            $nb_feuilles = intval($brochure['nb_feuilles']);
                            $taille = $brochure['taille'];
                            $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui';
                            $couleur = isset($brochure['couleur']) && $brochure['couleur'] == 'oui';
                            $feuilles_payees = isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui';

                            // Récupérer le taux de remplissage (valeur par défaut 0.5 = 50%)
                            $fill_rate = isset($machine['fill_rate']) ? floatval($machine['fill_rate']) : 0.5;

                            // Normalisation : si > 1, on suppose que c'est un pourcentage (ex: 36.5), on convertit en ratio (0.365)
                            if ($fill_rate > 1.0) {
                                $fill_rate = $fill_rate / 100.0;
                            }

                            if (isset($_GET['debug'])) {
                                $array['debug']['photocopieur_' . $index] .= " - Calcul pour: " . $nb_exemplaires . " exemplaires, " . $nb_feuilles . " feuilles, " . $taille . ", rv=" . ($rv ? 'oui' : 'non') . ", couleur=" . ($couleur ? 'oui' : 'non') . ", feuilles_payees=" . ($feuilles_payees ? 'oui' : 'non') . ", fill_rate=" . $fill_rate;
                            }

                            // Calculer le prix comme le JavaScript
                            $nbPages = $nb_exemplaires * $nb_feuilles;
                            $prixPapier = $array['prix_data']['papier'][$taille] ?? 0;
                            $coutPapier = $feuilles_payees ? 0 : ($nbPages * $prixPapier);

                            // Calculer le coût par page selon le type de machine et les couleurs (avec taux de remplissage)
                            $breakdown = calculatePageCost($machine['machine'], $machine_type_detected, $machine_prices, $couleur, $rv, $fill_rate, true);
                            $cost_per_page = $breakdown['total'];

                            // Ajuster selon la taille (A3 = prix normal, A4 = prix/2)
                            if ($taille === 'A4') {
                                $cost_per_page = $cost_per_page / 2;
                                $breakdown['fixed_cost'] /= 2;
                                $breakdown['variable_cost_base'] /= 2;
                                $breakdown['variable_cost_adjusted'] /= 2;
                            }

                            // Calculer le coût d'encre
                            $nbPagesEncre = $nbPages; // Pages pour l'encre
                            if ($rv)
                                $nbPagesEncre = $nbPages * 2; // Recto-verso = 2 fois plus de pages pour l'encre
                            
                            $prixEncre = $nbPagesEncre * $cost_per_page;
                            $prixBrochure = $coutPapier + $prixEncre;
                            $prix_total += $prixBrochure;

                            // Store breakdown for UI
                            if (!isset($array['machines'][$index]['breakdown'])) {
                                $array['machines'][$index]['breakdown'] = [
                                    'papier' => 0,
                                    'noir' => 0,
                                    'couleurs' => 0,
                                    'multiplier' => $breakdown['multiplier']
                                ];
                            }
                            $array['machines'][$index]['breakdown']['papier'] += $coutPapier;
                            $array['machines'][$index]['breakdown']['noir'] += $nbPagesEncre * $breakdown['fixed_cost'];
                            $array['machines'][$index]['breakdown']['couleurs'] += $nbPagesEncre * $breakdown['variable_cost_adjusted'];
                            $array['machines'][$index]['breakdown']['total_encre'] = $array['machines'][$index]['breakdown']['noir'] + $array['machines'][$index]['breakdown']['couleurs'];
                            $array['machines'][$index]['breakdown']['total'] = $array['machines'][$index]['breakdown']['papier'] + $array['machines'][$index]['breakdown']['total_encre'];
                            $array['machines'][$index]['breakdown']['prix_encre_page'] = $cost_per_page;
                            $array['machines'][$index]['breakdown']['nb_pages_encre'] = $nbPagesEncre;
                            $array['machines'][$index]['breakdown']['prix_papier_unite'] = $prixPapier;
                            $array['machines'][$index]['breakdown']['nb_pages_papier'] = $nbPages;
                            $array['machines'][$index]['breakdown']['variable_cost_base'] = $breakdown['variable_cost_base']; 
                            $array['machines'][$index]['breakdown']['is_color'] = $couleur;
                            $array['machines'][$index]['breakdown']['fr_percent'] = $fill_rate * 100;
                            
                            // Mettre à jour le prix de la machine avec la valeur calculée
                            $array['machines'][$index]['prix'] = $array['machines'][$index]['breakdown']['total'];

                            if (isset($_GET['debug'])) {
                                $array['debug']['photocopieur_' . $index] .= " - Calcul détaillé: " . $nbPages . " pages, papier=" . $prixPapier . "€, encre=" . $prixEncre . "€, coutPapier=" . $coutPapier . "€, total=" . $prixBrochure . "€";
                            }
                        } else {
                            if (isset($_GET['debug'])) {
                                $array['debug']['photocopieur_' . $index] .= " - Brochure ignorée (champs vides)";
                            }
                        }
                    }
                } else {
                    if (isset($_GET['debug'])) {
                        $array['debug']['photocopieur_' . $index] .= " - Aucune brochure trouvée";
                    }
                }

                $array['machines'][$index]['prix'] = round($prix_total, 2);
                $array['prix_total'] += $prix_total;
                if (isset($_GET['debug'])) {
                    $array['debug']['photocopieur_' . $index] .= " - Prix final: " . $prix_total;
                }
            }
        }
    }

    // Traitement des données POST - Enregistrement en BDD
    error_log("DEBUG POST CHECK - contact isset: " . (isset($_POST['contact']) ? 'OUI' : 'NON') . ", enregistrer isset: " . (isset($_POST['enregistrer']) ? 'OUI' : 'NON'));
    error_log("DEBUG POST CHECK - POST keys: " . implode(', ', array_keys($_POST)));
    if (isset($_POST['contact']) && isset($_POST['enregistrer'])) {
        error_log("DEBUG - ENTREE DANS ENREGISTREMENT (bouton enregistrer)");
        // Augmenter le timeout pour éviter les timeouts - CORRECTION TIMEOUT
        set_time_limit(120); // Augmenté de 60 à 120 secondes
        ini_set('max_execution_time', 120); // Force PHP timeout
        // Debug simple pour vérifier que le code est exécuté (seulement si debug dans l'URL)
        if (isset($_GET['debug'])) {
            $array['debug']['simple'] = "CODE D'ENREGISTREMENT EXÉCUTÉ !";
            $array['debug']['enregistrement'] = "=== DEBUG ENREGISTREMENT ===";
            $array['debug']['enregistrement'] .= "<br>POST reçu: " . print_r($_POST, true);
            $array['debug']['enregistrement'] .= "<br>Contact: " . ($_POST['contact'] ?? 'NON DÉFINI');
            $array['debug']['enregistrement'] .= "<br>Machines: " . (isset($_POST['machines']) ? count($_POST['machines']) : 'NON DÉFINI');
        }
        // Définir les machines pour l'affichage du formulaire
        $array['machines'] = $_POST['machines'] ?? [];

        // Vérifier qu'on a des machines
        if (empty($array['machines'])) {
            error_log("DEBUG ENREGISTREMENT - ERREUR: Aucune machine fournie");
            $array['errors'][] = "Aucune machine fournie pour l'enregistrement";
            return $array;
        }

        // OPTIMISATION : Récupérer les prix UNE SEULE FOIS pour toutes les machines
        error_log("DEBUG ENREGISTREMENT - Récupération globale des prix AVANT la boucle");
        $prix_data_global = get_price();
        error_log("DEBUG ENREGISTREMENT - Prix globaux récupérés avec succès");

        // Calculer le prix pour chaque machine AVANT l'enregistrement
        error_log("DEBUG ENREGISTREMENT - Début calcul prix pour " . count($array['machines']) . " machines");
        foreach ($array['machines'] as $index => $machine) {
            error_log("DEBUG ENREGISTREMENT - Traitement machine $index de type: " . $machine['type']);
            if (isset($_GET['debug'])) {
                $array['debug']['machine_' . $index] = "Machine " . $index . " - Type: " . $machine['type'];
                $array['debug']['machine_type_check_' . $index] = "Type check: " . ($machine['type'] === 'duplicopieur' ? 'TRUE' : 'FALSE');
            }
            if ($machine['type'] === 'duplicopieur') {
                if (isset($_GET['debug'])) {
                    $array['debug']['duplicopieur_debug_' . $index] = "ENTRÉE DANS LE CALCUL DUPLICOPIEUR " . $index;
                }
                // Calcul duplicopieur
                $mode_saisie = $machine['mode_saisie'] ?? 'compteurs';

                if ($mode_saisie === 'compteurs') {
                    // Mode compteurs
                    $master_av = ceil(floatval($machine['master_av'] ?? 0));
                    $master_ap = ceil(floatval($machine['master_ap'] ?? 0));
                    $passage_av = ceil(floatval($machine['passage_av'] ?? 0));
                    $passage_ap = ceil(floatval($machine['passage_ap'] ?? 0));

                    $nb_masters = max(0, $master_ap - $master_av);
                    $nb_passages = max(0, $passage_ap - $passage_av);
                } else {
                    // Mode manuel
                    $nb_masters = ceil(floatval($machine['nb_masters'] ?? 0));
                    $nb_passages = ceil(floatval($machine['nb_passages'] ?? 0));
                }

                // Calculer nb_f selon les options
                $nb_f = $nb_passages;
                if (isset($machine['rv']) && $machine['rv'] == 'oui') {
                    $nb_f = $nb_passages / 2;
                }
                if (isset($machine['feuilles_payees']) && $machine['feuilles_payees'] == 'oui') {
                    $nb_f = 0;
                }
                // Suppression de la division par 2 pour A4 car elle est déjà appliquée aux prix unitaires

                // Déterminer la taille selon les options
                $taille = 'A3'; // Par défaut A3
                if (isset($machine['A4']) && $machine['A4'] == 'A4') {
                    $taille = 'A4';
                }

                // NOUVELLE STRUCTURE : Calculer le prix directement comme le JavaScript pour être cohérent
                // Utiliser les prix globaux au lieu d'appeler get_price() à chaque fois
                $prix_data = $prix_data_global;
                $duplicopieur_id = $machine['duplicopieur_id'] ?? $array['duplicopieur_selectionne']['id']; // Utiliser l'ID du duplicopieur sélectionné
                $machine_key = 'dupli_' . $duplicopieur_id;
                $prix_master = $prix_data[$machine_key]['master']['unite'] ?? 0;

                // Prix des passages selon le tambour sélectionné (comme le JavaScript)
                $tambour_selected = $machine['tambour'] ?? '';
                $prix_passage = 0;

                if (!empty($tambour_selected) && isset($prix_data[$machine_key][$tambour_selected]['unite'])) {
                    $prix_passage = $prix_data[$machine_key][$tambour_selected]['unite'];
                } elseif (isset($prix_data[$machine_key]['tambour_noir']['unite'])) {
                    // Fallback sur le tambour noir si pas de tambour spécifique
                    $prix_passage = $prix_data[$machine_key]['tambour_noir']['unite'];
                }

                $prix_papier = ($taille === 'A3') ? ($prix_data['papier']['A3'] ?? 0) : ($prix_data['papier']['A4'] ?? 0);

                // NOUVELLE LOGIQUE : A4 = A3/2 pour masters et passages
                if ($taille === 'A4') {
                    $prix_master = $prix_master / 2;
                    $prix_passage = $prix_passage / 2;
                }

                $prix_total = ($nb_masters * $prix_master) + ($nb_passages * $prix_passage) + ($nb_f * $prix_papier);
                $array['machines'][$index]['prix'] = round($prix_total, 2);
                $array['prix_total'] += $prix_total;

                // Debug pour duplicopieur (seulement si debug dans l'URL)
                if (isset($_GET['debug'])) {
                    $array['debug']['duplicopieur_' . $index] = "Machine " . $index . " (duplicopieur) détectée - Masters: " . $nb_masters . ", Passages: " . $nb_passages . ", Feuilles: " . $nb_f . ", Taille: " . $taille . ", RV: " . (isset($machine['rv']) ? $machine['rv'] : 'non') . ", Couleur: " . (isset($machine['couleur']) ? $machine['couleur'] : 'non') . ", A4: " . (isset($machine['A4']) ? $machine['A4'] : 'non') . " - Calcul détaillé: " . $nb_masters . " masters × " . $prix_master . "€ + " . $nb_passages . " passages × " . $prix_passage . "€ + " . $nb_f . " feuilles × " . $prix_papier . "€ = " . $prix_total . "€ - Prix final: " . round($prix_total, 2);
                }

            } else if ($machine['type'] === 'photocopieur') {
                // Calcul photocopieur - OPTIMISÉ POUR ÉVITER TIMEOUT
                error_log("DEBUG ENREGISTREMENT - ENTREE DANS CALCUL PHOTOCOPIEUR machine $index");
                $prix_machine = 0;

                // OPTIMISATION : Utiliser les prix globaux récupérés avant la boucle
                error_log("DEBUG ENREGISTREMENT - Utilisation des prix globaux");
                try {
                    $prix_data = $prix_data_global;
                    error_log("DEBUG ENREGISTREMENT - Prix globaux utilisés avec succès");
                    $prix_papier_a3 = $prix_data['papier']['A3'] ?? 0.02;
                    $prix_papier_a4 = $prix_data['papier']['A4'] ?? 0.01;
                    error_log("DEBUG ENREGISTREMENT - Prix papier récupérés: A3=$prix_papier_a3, A4=$prix_papier_a4");
                } catch (Exception $e) {
                    error_log("DEBUG ENREGISTREMENT - ERREUR dans get_price(): " . $e->getMessage());
                    $prix_papier_a3 = 0.02;
                    $prix_papier_a4 = 0.01;
                }

                // OPTIMISATION : Récupérer les prix machine UNE SEULE FOIS
                error_log("DEBUG ENREGISTREMENT - Récupération prix machine (une seule fois)");
                try {
                    $machine_prices = getMachinePrices($db, $machine['machine']);
                    $machine_type_detected = determineMachineType($db, $machine['machine']);
                    error_log("DEBUG ENREGISTREMENT - Prix machine récupérés pour: " . $machine['machine']);
                } catch (Exception $e) {
                    error_log("DEBUG ENREGISTREMENT - ERREUR prix machine: " . $e->getMessage());
                    $machine_prices = [
                        'noire' => ['unite' => 0.03],
                        'bleue' => ['unite' => 0.05],
                        'rouge' => ['unite' => 0.05],
                        'jaune' => ['unite' => 0.05]
                    ];
                    $machine_type_detected = 'encre';
                }

                if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                    error_log("DEBUG ENREGISTREMENT - Début boucle brochures optimisée, count: " . count($machine['brochures']));
                    foreach ($machine['brochures'] as $brochure_index => $brochure) {
                        if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                            // Utilisation de la fonction optimisée
                            // Récupérer le taux de remplissage (valeur par défaut 0.5 = 50%)
                            $fill_rate = isset($machine['fill_rate']) ? floatval($machine['fill_rate']) : 0.5;

                            // Normalisation : si > 1, on suppose que c'est un pourcentage (ex: 36.5), on convertit en ratio (0.365)
                            if ($fill_rate > 1.0) {
                                $fill_rate = $fill_rate / 100.0;
                            }

                            $prix_brochure = calculateBrochurePriceOptimized(
                                $brochure,
                                $prix_papier_a3,
                                $prix_papier_a4,
                                $machine_prices,
                                $machine_type_detected,
                                $machine['machine'],
                                $fill_rate
                            );
                            $prix_machine += $prix_brochure;

                            // Debug: Log du prix final de la brochure
                            error_log("DEBUG ENREGISTREMENT - Prix brochure: " . $prix_brochure);
                            error_log("DEBUG ENREGISTREMENT - Prix machine total: " . $prix_machine);
                        }
                    }
                }

                $array['machines'][$index]['prix'] = $prix_machine;
                $array['prix_total'] += $prix_machine;
            }
        }

        // Validation des données
        if (empty($_POST['contact'])) {
            $array['errors'][] = "Veuillez entrer votre nom/contact.";
        }

        if (empty($_POST['machines']) || !is_array($_POST['machines'])) {
            $array['errors'][] = "Veuillez ajouter au moins une machine.";
        }

        // Validation spécifique pour chaque machine
        if (isset($_POST['machines']) && is_array($_POST['machines'])) {
            foreach ($_POST['machines'] as $index => $machine) {
                if (empty($machine['type'])) {
                    $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez sélectionner un type.";
                }

                if ($machine['type'] === 'duplicopieur') {
                    // Vérifier le mode de saisie
                    $mode_saisie = $machine['mode_saisie'] ?? 'compteurs';

                    if ($mode_saisie === 'compteurs') {
                        // Mode compteurs
                        if (!isset($machine['master_av']) || !is_numeric($machine['master_av']) || intval($machine['master_av']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de masters AVANT valide.";
                        }

                        if (!isset($machine['master_ap']) || !is_numeric($machine['master_ap']) || intval($machine['master_ap']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de masters APRÈS valide.";
                        }

                        if (!isset($machine['passage_av']) || !is_numeric($machine['passage_av']) || intval($machine['passage_av']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de passages AVANT valide.";
                        }

                        if (!isset($machine['passage_ap']) || !is_numeric($machine['passage_ap']) || intval($machine['passage_ap']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de passages APRÈS valide.";
                        }
                    } else {
                        // Mode manuel
                        if (!isset($machine['nb_masters']) || !is_numeric($machine['nb_masters']) || intval($machine['nb_masters']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de masters valide.";
                        }

                        if (!isset($machine['nb_passages']) || !is_numeric($machine['nb_passages']) || intval($machine['nb_passages']) < 0) {
                            $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez entrer un nombre de passages valide.";
                        }
                    }
                } else if ($machine['type'] === 'photocopieur') {
                    if (empty($machine['machine'])) {
                        $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez sélectionner une photocopieuse.";
                    }

                    if (empty($machine['brochures']) || !is_array($machine['brochures'])) {
                        $array['errors'][] = "Machine #" . ($index + 1) . " : Veuillez ajouter au moins une brochure.";
                    }
                }
            }
        }

        // Si pas d'erreurs, traiter les données
        if (empty($array['errors'])) {
            $contact = addslashes($_POST['contact']);
            $date = time();

            // Récupérer les valeurs paye et cb depuis les champs globaux du formulaire
            $paye = $_POST['paye'] ?? "non";
            $cb = floatval($_POST['cb'] ?? 0);

            $mot = addslashes($_POST['mot'] ?? '');
            $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : null;

            // Charger la classe de migration pour générer les IDs
            require_once __DIR__ . '/migrations/DatabaseMigrationManager.php';

            // Démarrer une transaction
            $db->beginTransaction();

            try {
                foreach ($_POST['machines'] as $index => $machine) {
                    if ($machine['type'] === 'duplicopieur') {
                        // Enregistrement duplicopieur dans table dupli
                        // Déterminer la taille selon les options
                        $machine_name = 'A3'; // Par défaut A3
                        if (isset($machine['A4']) && $machine['A4'] == 'A4') {
                            $machine_name = 'A4';
                        }
                        $type = "tirage";

                        // Déterminer le mode de saisie
                        $mode_saisie = $machine['mode_saisie'] ?? 'compteurs';

                        if ($mode_saisie === 'compteurs') {
                            // Mode compteurs
                            $master_av = ceil(floatval($machine['master_av'] ?? 0));
                            $master_ap = ceil(floatval($machine['master_ap'] ?? 0));
                            $passage_av = ceil(floatval($machine['passage_av'] ?? 0));
                            $passage_ap = ceil(floatval($machine['passage_ap'] ?? 0));
                        } else {
                            // Mode manuel - convertir en compteurs
                            // Utiliser la machine duplicopieur sélectionnée
                            if (isset($array['duplicopieur_selectionne'])) {
                                $machine_name = $array['duplicopieur_selectionne']['marque'] . ' ' . $array['duplicopieur_selectionne']['modele'];
                                if ($array['duplicopieur_selectionne']['marque'] === $array['duplicopieur_selectionne']['modele']) {
                                    $machine_name = $array['duplicopieur_selectionne']['marque'];
                                }

                                $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                                $query_counters->execute([$machine_name]);
                                $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
                                $query_counters->closeCursor(); // Bonne pratique : fermer le curseur

                                if ($last_counters) {
                                    $master_av = ceil($last_counters['master_ap']);
                                    $passage_av = ceil($last_counters['passage_ap']);
                                } else {
                                    $master_av = 0;
                                    $passage_av = 0;
                                }
                            } else {
                                $master_av = 0;
                                $passage_av = 0;
                            }

                            $master_ap = $master_av + ceil(floatval($machine['nb_masters'] ?? 0));
                            $passage_ap = $passage_av + ceil(floatval($machine['nb_passages'] ?? 0));
                        }

                        $rv = $machine['rv'] ?? 'non';
                        $prix = round(floatval($array['machines'][$index]['prix'] ?? 0), 2);

                        // Déterminer le nom de la machine et l'ID du duplicopieur
                        $nom_machine = 'Duplicopieur';
                        $duplicopieur_id = $array['duplicopieur_selectionne']['id'];
                        if (isset($machine['duplicopieur_id']) && !empty($machine['duplicopieur_id'])) {
                            $duplicopieur_id = intval($machine['duplicopieur_id']);
                            // Récupérer le nom de la machine depuis la table duplicopieurs
                            $query_dup = $db->prepare('SELECT marque, modele FROM duplicopieurs WHERE id = ?');
                            $query_dup->execute([$duplicopieur_id]);
                            $dup = $query_dup->fetch(PDO::FETCH_ASSOC);
                            $query_dup->closeCursor(); // CORRECTION CRITIQUE : Fermer le curseur
                            if ($dup) {
                                $nom_machine = $dup['marque'] . ' ' . $dup['modele'];
                                if ($dup['marque'] === $dup['modele']) {
                                    $nom_machine = $dup['marque'];
                                }
                            }
                        }

                        // Générer l'identifiant global pour cette machine spécifique
                        $tirage_global_id = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $nom_machine);

                        // Vérifier si ce job existe déjà en base de données (Auto-Tirage)
                        $db_id = isset($machine['db_id']) ? intval($machine['db_id']) : 0;

                        if ($db_id > 0) {
                            error_log("[DEDUPLICATION] Mise à jour d'un job dupli existant ID: $db_id");
                            $sql = 'UPDATE dupli SET contact = ?, master_av = ?, master_ap = ?, passage_av = ?, passage_ap = ?, rv = ?, prix = ?, paye = ?, cb = ?, mot = ?, date = ?, nom_machine = ?, duplicopieur_id = ?, tambour = ?, tirage_global_id = ?, session_id = ?, document_name = ?, thumbnail_url = ? WHERE id = ?';
                            $params = [$machine['contact'] ?? $contact, $master_av, $master_ap, $passage_av, $passage_ap, $rv, $prix, $paye, $cb, $mot, $date, $nom_machine, $duplicopieur_id, $machine['tambour'] ?? null, $tirage_global_id, $session_id, $machine['document_name'] ?? null, $machine['thumbnail_url'] ?? null, $db_id];
                            $query = $db->prepare($sql);
                            $query->execute($params);
                        } else {
                            // Insérer dans la table dupli (Enregistrement Manuel)
                            $sql = 'INSERT INTO dupli (type, contact, master_av, master_ap, passage_av, passage_ap, rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id, tambour, tirage_global_id, session_id, document_name, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                            $params = [$type, $machine['contact'] ?? $contact, $master_av, $master_ap, $passage_av, $passage_ap, $rv, $prix, $paye, $cb, $mot, $date, $nom_machine, $duplicopieur_id, $machine['tambour'] ?? null, $tirage_global_id, $session_id, $machine['document_name'] ?? null, $machine['thumbnail_url'] ?? null];
                            $query = $db->prepare($sql);
                            $query->execute($params);
                        }

                        // Marquer comme enregistré s'il y a un job_id (Auto-Tirage)
                        if (isset($machine['job_id']) && !empty($machine['job_id'])) {
                            error_log("[DEDUPLICATION] Marquage job dupli: " . $machine['job_id']);
                            $mark = $db->prepare("INSERT OR IGNORE INTO recorded_print_jobs (job_id, printer_name) VALUES (?, ?)");
                            $mark->execute([strval($machine['job_id']), $machine['printer_name'] ?? $nom_machine]);
                            
                            // Nettoyage immédiat du Spool Windows
                            SpoolManager::deleteSpoolFiles($machine['job_id']);
                            
                            // Supprimer le job de la table de staging print_jobs
                            $del_staging = $db->prepare("DELETE FROM print_jobs WHERE job_id = ? AND printer_name = ?");
                            $del_staging->execute([strval($machine['job_id']), $machine['printer_name'] ?? $nom_machine]);
                            error_log("[STAGING] Job dupli supprimé de print_jobs après insertion définitive");
                        }

                    } else if ($machine['type'] === 'photocopieur') {
                        // Enregistrement photocopieur dans table photocop
                        $marque = $machine['machine'];

                        // Générer l'identifiant global pour cette machine spécifique
                        $tirage_global_id = DatabaseMigrationManager::generateTirageGlobalId($date, $contact, $marque);

                        // Utiliser le prix calculé pour cette machine
                        $prix_machine_calcule = round(floatval($array['machines'][$index]['prix'] ?? 0), 2);

                        // Debug: Log du prix final transmis à insert_photocop
                        error_log("DEBUG ENREGISTREMENT - Prix final transmis à insert_photocop: " . $prix_machine_calcule);

                        // Debug: Log des brochures reçues
                        error_log("DEBUG ENREGISTREMENT - Brochures reçues: " . count($machine['brochures']) . " brochures");

                        // Traiter les brochures pour récupérer les infos nécessaires à l'enregistrement
                        if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                            foreach ($machine['brochures'] as $brochure) {
                                if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                                    $nb_exemplaires = intval($brochure['nb_exemplaires']);
                                    $nb_feuilles = intval($brochure['nb_feuilles']);
                                    $nb_f_total = $nb_exemplaires * $nb_feuilles;
                                    $taille = $brochure['taille'];
                                    $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui' ? 'oui' : 'non';

                                    // Vérifier si ce job existe déjà en base de données (Auto-Tirage)
                                    $db_id = isset($machine['db_id']) ? intval($machine['db_id']) : 0;

                                    if ($db_id > 0) {
                                        error_log("[DEDUPLICATION] Mise à jour d'un job photocop existant ID: $db_id");
                                        $sql = 'UPDATE photocop SET contact = ?, nb_f = ?, rv = ?, prix = ?, paye = ?, cb = ?, mot = ?, date = ?, marque = ?, tirage_global_id = ?, session_id = ?, document_name = ?, thumbnail_url = ? WHERE id = ?';
                                        $params = [$machine['contact'] ?? $contact, $nb_f_total, $rv, $prix_machine_calcule, $paye, $cb, $mot, $date, $marque, $tirage_global_id, $session_id, $machine['document_name'] ?? null, $machine['thumbnail_url'] ?? null, $db_id];
                                        $query = $db->prepare($sql);
                                        $query->execute($params);
                                    } else {
                                        // Insérer dans la table photocop avec le prix transmis
                                        error_log("DEBUG ENREGISTREMENT - Tentative insertion photocop: type=photocopieur, marque=$marque, nb_f_total=$nb_f_total, prix=$prix_machine_calcule, session=$session_id");
                                        insert_photocop(
                                            'photocopieur',
                                            $marque,
                                            $machine['contact'] ?? $contact,
                                            $nb_f_total,
                                            $rv,
                                            $prix_machine_calcule,
                                            $paye,
                                            $cb,
                                            $mot,
                                            $date,
                                            $db,
                                            $tirage_global_id,
                                            $session_id,
                                            $machine['document_name'] ?? null,
                                            $machine['thumbnail_url'] ?? null
                                        );
                                    }
                                    error_log("DEBUG ENREGISTREMENT - Fin traitement brochure");
                                }
                            }
                        }

                        // Marquer comme enregistré s'il y a un job_id (Auto-Tirage)
                        if (isset($machine['job_id']) && !empty($machine['job_id'])) {
                            error_log("[DEDUPLICATION] Marquage job photocop: " . $machine['job_id']);
                            $mark = $db->prepare("INSERT OR IGNORE INTO recorded_print_jobs (job_id, printer_name) VALUES (?, ?)");
                            $mark->execute([strval($machine['job_id']), $machine['printer_name'] ?? $marque]);

                            // Nettoyage immédiat du Spool Windows
                            SpoolManager::deleteSpoolFiles($machine['job_id']);
                            
                            // Supprimer le job de la table de staging print_jobs
                            $del_staging = $db->prepare("DELETE FROM print_jobs WHERE job_id = ? AND printer_name = ?");
                            $del_staging->execute([strval($machine['job_id']), $machine['printer_name'] ?? $marque]);
                            error_log("[STAGING] Job photocop supprimé de print_jobs après insertion définitive");
                        }
                    }
                }

                // Valider la transaction
                $db->commit();

                // Clôturer la session si elle existe
                if ($session_id) {
                    try {
                        // Calculer le total final de la session
                        $priceQuery = $db->prepare("
                            SELECT 
                                (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM photocop WHERE session_id = ?) +
                                (SELECT COALESCE(SUM(CAST(prix AS REAL)), 0) FROM dupli WHERE session_id = ?) as total
                        ");
                        $priceQuery->execute([$session_id, $session_id]);
                        $total_price = (float)$priceQuery->fetchColumn();

                        // Fermer la session
                        $updateQuery = $db->prepare("
                            UPDATE print_sessions 
                            SET status = 'closed', 
                                closed_at = datetime('now'),
                                total_price = ?
                            WHERE id = ?
                        ");
                        $updateQuery->execute([$total_price, $session_id]);
                        error_log("[SESSIONS] Session $session_id clôturée après enregistrement multimachines. Total: $total_price");
                    } catch (Exception $e) {
                        error_log("[SESSIONS] Erreur lors de la clôture de la session $session_id: " . $e->getMessage());
                    }
                }

                // Message de succès
                $array['success_message'] = "Tirage enregistré avec succès !";

            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $db->rollBack();
                $array['errors'][] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }

    // Traitement pour récupération des valeurs AVANT (duplicopieur)
    if (isset($_POST['contact']) && !isset($_POST['ok'])) {
        $machine = 'dupli';
        $last = get_last_number($machine);
        $array['master_av'] = $last['master_av'];
        $array['passage_av'] = $last['passage_av'];
        $array['contact'] = addslashes($_POST['contact']);
    }

    // Traitement pour récupération des valeurs AVANT (duplicopieur)

    // Assigner debug pour le template
    $debug = $array['debug'] ?? null;

    return template("../view/tirage_multimachines.html.php", $array);
}

/**
 * Génère le HTML d'une machine pour les nouvelles machines ajoutées via AJAX
 */
function generateMachineHTML($index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers)
{
    // Inclure le système de traduction
    require_once __DIR__ . '/../controler/functions/i18n.php';

    // Récupérer les dernières valeurs de compteurs pour le duplicopieur par défaut
    $master_av = 0;
    $passage_av = 0;

    if ($duplicopieur_selectionne) {
        // Construire le nom de la machine duplicopieur sélectionnée
        $machine_name = $duplicopieur_selectionne['marque'] . ' ' . $duplicopieur_selectionne['modele'];
        if ($duplicopieur_selectionne['marque'] === $duplicopieur_selectionne['modele']) {
            $machine_name = $duplicopieur_selectionne['marque'];
        }

        // Récupérer les derniers compteurs pour cette machine spécifique
        $db = pdo_connect();
        $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
        $query_counters->execute([$machine_name]);
        $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
        $query_counters->closeCursor();

        if ($last_counters) {
            $master_av = ceil($last_counters['master_ap']);
            $passage_av = ceil($last_counters['passage_ap']);
        }
    }

    // Utiliser le template partiel pour générer le HTML
    ob_start();
    include __DIR__ . '/../view/partials/machine_item.html.php';
    return ob_get_clean();
}

