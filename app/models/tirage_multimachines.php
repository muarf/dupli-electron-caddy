<?php

require_once __DIR__ . '/../controler/functions/database.php';
require_once __DIR__ . '/../controler/functions/pricing.php';
require_once __DIR__ . '/../controler/functions/tirage.php';

// Gestion AJAX pour récupérer les tambours d'un duplicopieur
if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_tambours' && isset($_GET['duplicopieur_id'])) {
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
if(isset($_GET['ajax']) && $_GET['ajax'] === 'get_last_counters' && isset($_GET['machine'])) {
    $machine = $_GET['machine'];
    
    try {
        $con = pdo_connect();
        $db = pdo_connect();
        
        // Vérifier si c'est un duplicopieur ou un photocopieur
        $query = $db->prepare('SELECT COUNT(*) as count FROM duplicopieurs WHERE (CONCAT(marque, " ", modele) = ? OR marque = ?) AND actif = 1');
        $query->execute([$machine, $machine]);
        $is_duplicopieur = $query->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($is_duplicopieur) {
            // C'est un duplicopieur, récupérer les compteurs depuis la table dupli
            $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
            $query_counters->execute([$machine]);
            $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
            
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
function getMachinePrices($db, $machine_name) {
    // Déterminer la clé de la machine selon la nouvelle structure
    $machine_type = '';
    $machine_id = 0;
    
    // Vérifier si c'est un photocopieur
    $query = $db->prepare('SELECT id, type_encre FROM photocopieurs WHERE marque = ? AND actif = 1');
    $query->execute([$machine_name]);
    $photocop = $query->fetch(PDO::FETCH_ASSOC);
    
    error_log("DEBUG getMachinePrices - machine_name: $machine_name");
    error_log("DEBUG getMachinePrices - photocop trouvé: " . print_r($photocop, true));
    
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
    
    $query = $db->prepare('SELECT type, unite, pack FROM prix WHERE machine_type = ? AND machine_id = ?');
    $query->execute([$machine_type, $machine_id]);
    $prices = [];
    
    error_log("DEBUG getMachinePrices - Requête prix: machine_type=$machine_type, machine_id=$machine_id");
    
    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $prices[$row['type']] = [
            'unite' => floatval($row['unite']),
            'pack' => floatval($row['pack'])
        ];
        error_log("DEBUG getMachinePrices - Prix ajouté: " . $row['type'] . " = " . $row['unite']);
    }
    
    error_log("DEBUG getMachinePrices - Prix finaux: " . print_r($prices, true));
    
    return $prices;
}

function determineMachineType($db, $machine_name) {
    // Vérifier si c'est un photocopieur
    $query = $db->prepare('SELECT id, type_encre FROM photocopieurs WHERE marque = ? AND actif = 1');
    $query->execute([$machine_name]);
    $photocop = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($photocop) {
        // C'est un photocopieur, utiliser le type_encre de la table
        return $photocop['type_encre'];
    } else {
        // Pour les duplicopieurs, utiliser dupli_1
        $machine_type = 'dupli';
        $machine_id = 1;
        
        $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine_type = ? AND machine_id = ? AND type IN ("tambour", "dev")');
        $query->execute([$machine_type, $machine_id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        return ($result['count'] > 0) ? 'toner' : 'encre';
    }
}

function calculatePageCost($machine_name, $machine_type, $prices, $is_color, $is_duplex) {
    $cost_per_page = 0;
    
    if ($machine_type === 'toner') {
        // Machine toner : calculer selon les couleurs utilisées
        if ($is_color) {
            // Impression couleur : cyan + magenta + yellow + noir + tambour + dev
            $cost_per_page += ($prices['cyan']['unite'] ?? 0);
            $cost_per_page += ($prices['magenta']['unite'] ?? 0);
            $cost_per_page += ($prices['yellow']['unite'] ?? 0);
            $cost_per_page += ($prices['noir']['unite'] ?? 0);
        } else {
            // Impression noir et blanc : noir + tambour + dev
            $cost_per_page += ($prices['noir']['unite'] ?? 0);
        }
        
        // Ajouter les coûts du tambour et révélateur (unite = coût par page)
        if (isset($prices['tambour'])) {
            $cost_per_page += ($prices['tambour']['unite'] ?? 0);
        }
        
        if (isset($prices['dev'])) {
            $cost_per_page += ($prices['dev']['unite'] ?? 0);
        }
        
    } else {
        // Machine encre : calculer selon les couleurs utilisées
        if ($is_color) {
            // Impression couleur : additionner toutes les couleurs individuelles (pas la valeur "couleur" globale)
            $cost_per_page += ($prices['bleue']['unite'] ?? 0);
            $cost_per_page += ($prices['jaune']['unite'] ?? 0);
            $cost_per_page += ($prices['noire']['unite'] ?? 0);
            $cost_per_page += ($prices['rouge']['unite'] ?? 0);
        } else {
            // Impression noir et blanc : seulement noir
            $cost_per_page += ($prices['noire']['unite'] ?? 0);
        }
    }
    
    return $cost_per_page;
}

function Action() {
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
        foreach($duplicopieurs as $index => $dup) {
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
        if(count($array['duplicopieurs']) == 1) {
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
    
    // Debug pour comprendre pourquoi la condition ne fonctionne pas (seulement si debug dans l'URL)
    if (isset($_GET['debug'])) {
        $array['debug']['post_check'] = "Contact isset: " . (isset($_POST['contact']) ? 'OUI' : 'NON') . 
                                     " | OK isset: " . (isset($_POST['ok']) ? 'OUI' : 'NON') . 
                                     " | OK value: '" . ($_POST['ok'] ?? 'UNDEFINED') . "'";
    }
    
    // Traitement des données POST - Affichage de la page de confirmation
    if (isset($_POST['contact']) && isset($_POST['ok'])) {
        if (isset($_GET['debug'])) {
            $array['debug']['confirmation'] = "ENTRÉE DANS LA CONFIRMATION - " . date('H:i:s');
        }
        // Définir les machines pour l'affichage du formulaire
        $array['machines'] = $_POST['machines'];
        $array['contact'] = addslashes($_POST['contact']);
        
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
                $duplicopieur_id = $machine['duplicopieur_id'] ?? 1; // Fallback sur ID 1 si pas défini
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
                $prix_total = 0;
                if (isset($_GET['debug'])) {
                    $array['debug']['photocopieur_' . $index] = "Machine " . $index . " (photocopieur) détectée";
                }
                
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
                            
                            if (isset($_GET['debug'])) {
                                $array['debug']['photocopieur_' . $index] .= " - Calcul pour: " . $nb_exemplaires . " exemplaires, " . $nb_feuilles . " feuilles, " . $taille . ", rv=" . ($rv ? 'oui' : 'non') . ", couleur=" . ($couleur ? 'oui' : 'non') . ", feuilles_payees=" . ($feuilles_payees ? 'oui' : 'non');
                            }
                            
                            // Calculer le prix comme le JavaScript
                            $nbPages = $nb_exemplaires * $nb_feuilles;
                            $prixPapier = $array['prix_data']['papier'][$taille] ?? 0;
                            $coutPapier = $feuilles_payees ? 0 : ($nbPages * $prixPapier);
                            
                            // NOUVELLE STRUCTURE : Utiliser les fonctions dynamiques comme à l'enregistrement
                            $machine_prices = getMachinePrices($db, $machine['machine']);
                            $machine_type_detected = determineMachineType($db, $machine['machine']);
                            
                            // Calculer le coût par page selon le type de machine et les couleurs
                            $cost_per_page = calculatePageCost($machine['machine'], $machine_type_detected, $machine_prices, $couleur, $rv);
                            
                            // Ajuster selon la taille (A3 = prix normal, A4 = prix/2)
                            if ($taille === 'A4') $cost_per_page = $cost_per_page / 2;
                            
                            // Calculer le coût d'encre
                            $nbPagesEncre = $nbPages; // Pages pour l'encre
                            if ($rv) $nbPagesEncre = $nbPages * 2; // Recto-verso = 2 fois plus de pages pour l'encre
                            $prixEncre = $nbPagesEncre * $cost_per_page;
                            
                            $prixBrochure = $coutPapier + $prixEncre;
                            $prix_total += $prixBrochure;
                            
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
    if (isset($_POST['contact']) && isset($_POST['enregistrer'])) {
        // Debug simple pour vérifier que le code est exécuté (seulement si debug dans l'URL)
        if (isset($_GET['debug'])) {
            $array['debug']['simple'] = "CODE D'ENREGISTREMENT EXÉCUTÉ !";
            $array['debug']['enregistrement'] = "=== DEBUG ENREGISTREMENT ===";
            $array['debug']['enregistrement'] .= "<br>POST reçu: " . print_r($_POST, true);
            $array['debug']['enregistrement'] .= "<br>Contact: " . ($_POST['contact'] ?? 'NON DÉFINI');
            $array['debug']['enregistrement'] .= "<br>Machines: " . (isset($_POST['machines']) ? count($_POST['machines']) : 'NON DÉFINI');
        }
        // Définir les machines pour l'affichage du formulaire
        $array['machines'] = $_POST['machines'];
        
        // Calculer le prix pour chaque machine AVANT l'enregistrement
        foreach ($_POST['machines'] as $index => $machine) {
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
                $prix_data = get_price();
                $duplicopieur_id = $machine['duplicopieur_id'] ?? 1; // Fallback sur ID 1 si pas défini
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
                // Calcul photocopieur
                $prix_machine = 0;
                
                if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                    foreach ($machine['brochures'] as $brochure) {
                        if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                            $nb_exemplaires = intval($brochure['nb_exemplaires']);
                            $nb_feuilles = intval($brochure['nb_feuilles']);
                            $nb_f_total = $nb_exemplaires * $nb_feuilles;
                            $taille = $brochure['taille'];
                            $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui';
                            $couleur = isset($brochure['couleur']) && $brochure['couleur'] == 'oui';
                            $feuilles_payees = isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui';
                            
                            // Calculer le prix dynamiquement
                            $nb_p = $rv ? $nb_f_total * 2 : $nb_f_total;
                            
                            // NOUVELLE STRUCTURE : Prix papier depuis la base de données
                            $prix_data = get_price();
                            $prix_papier = ($taille == 'A4') ? ($prix_data['papier']['A4'] ?? 0.01) : ($prix_data['papier']['A3'] ?? 0.02);
                            $prix_papier_total = $feuilles_payees ? 0 : ($nb_f_total * $prix_papier);
                            
                            // Récupérer les prix de la machine depuis la base de données
                            $machine_prices = getMachinePrices($db, $machine['machine']);
                            $machine_type_detected = determineMachineType($db, $machine['machine']);
                            
                            // Debug: Log des prix récupérés
                            error_log("DEBUG ENREGISTREMENT - Machine: " . $machine['machine']);
                            error_log("DEBUG ENREGISTREMENT - Type détecté: " . $machine_type_detected);
                            error_log("DEBUG ENREGISTREMENT - Prix récupérés: " . print_r($machine_prices, true));
                            
                            // Calculer le coût par page selon le type de machine et les couleurs
                            $cost_per_page = calculatePageCost($machine['machine'], $machine_type_detected, $machine_prices, $couleur, $rv);
                            
                            // Debug: Log du coût par page
                            error_log("DEBUG ENREGISTREMENT - Coût par page: " . $cost_per_page);
                            
                            // Ajuster selon la taille (A3 = prix normal, A4 = prix/2)
                            if ($taille === 'A4') $cost_per_page = $cost_per_page / 2;
                            
                            $prix_encre_total = $nb_p * $cost_per_page;
                            
                            // Debug: Log des calculs finaux
                            error_log("DEBUG ENREGISTREMENT - Nb pages: " . $nb_p);
                            error_log("DEBUG ENREGISTREMENT - Prix encre total: " . $prix_encre_total);
                            error_log("DEBUG ENREGISTREMENT - Prix papier total: " . $prix_papier_total);
                            
                            $prix_brochure = $prix_papier_total + $prix_encre_total;
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
                        $duplicopieur_id = 1;
                        if (isset($machine['duplicopieur_id']) && !empty($machine['duplicopieur_id'])) {
                            $duplicopieur_id = intval($machine['duplicopieur_id']);
                            // Récupérer le nom de la machine depuis la table duplicopieurs
                            $query = $db->prepare('SELECT marque, modele FROM duplicopieurs WHERE id = ?');
                            $query->execute([$duplicopieur_id]);
                            $dup = $query->fetch(PDO::FETCH_ASSOC);
                            if ($dup) {
                                $nom_machine = $dup['marque'] . ' ' . $dup['modele'];
                                if ($dup['marque'] === $dup['modele']) {
                                    $nom_machine = $dup['marque'];
                                }
                            }
                        }
                        
                        // Insérer dans la table dupli
                        $sql = 'INSERT INTO dupli (type, contact, master_av, master_ap, passage_av, passage_ap, rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id, tambour) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                        $params = [$type, $machine['contact'] ?? $contact, $master_av, $master_ap, $passage_av, $passage_ap, $rv, $prix, $paye, $cb, $mot, $date, $nom_machine, $duplicopieur_id, $machine['tambour'] ?? null];
                        
                        // Debug SQL avec var_dump (seulement si debug dans l'URL)
                        if (isset($_GET['debug'])) {
                            $array['debug']['sql_vardump'] = "<pre>SQL: " . $sql . "\nParams: " . print_r($params, true) . "</pre>";
                        }
                        
                        $query = $db->prepare($sql);
                        $query->execute($params);
                        
                    } else if ($machine['type'] === 'photocopieur') {
                        // Enregistrement photocopieur dans table photocop
                        $marque = $machine['machine'];
                        
                        // Utiliser le prix calculé pour cette machine
                        $prix_machine_calcule = round(floatval($array['machines'][$index]['prix'] ?? 0), 2);
                        
                        // Debug: Log du prix final transmis à insert_photocop
                        error_log("DEBUG ENREGISTREMENT - Prix final transmis à insert_photocop: " . $prix_machine_calcule);
                        
                        // Traiter les brochures pour récupérer les infos nécessaires à l'enregistrement
                        if (isset($machine['brochures']) && is_array($machine['brochures'])) {
                            foreach ($machine['brochures'] as $brochure) {
                                if (!empty($brochure['nb_exemplaires']) && !empty($brochure['nb_feuilles']) && !empty($brochure['taille'])) {
                                    $nb_exemplaires = intval($brochure['nb_exemplaires']);
                                    $nb_feuilles = intval($brochure['nb_feuilles']);
                                    $nb_f_total = $nb_exemplaires * $nb_feuilles;
                                    $taille = $brochure['taille'];
                                    $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui' ? 'oui' : 'non';
                                    
                                    // Insérer dans la table photocop avec le prix transmis
                                    insert_photocop(
                                        $taille,
                                        $marque,
                                        $machine['contact'] ?? $contact,
                                        $nb_f_total,
                                        $rv,
                                        $prix_machine_calcule,
                                        $paye,
                                        $cb,
                                        $mot,
                                        $date
                                    );
                                }
                            }
                        }
                    }
                }
                
                // Valider la transaction
                $db->commit();
                
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
function generateMachineHTML($index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers) {
    // Récupérer les dernières valeurs de compteurs pour le duplicopieur par défaut
    $con = pdo_connect()();
    $last_values = ['master_av' => 0, 'passage_av' => 0];
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
        
        if ($last_counters) {
            $last_values['master_av'] = ceil($last_counters['master_ap']);
            $last_values['passage_av'] = ceil($last_counters['passage_ap']);
        }
    }
    
    $html = '<hr>
        <h5>Tirage #' . ($index + 1) . '</h5>
        
        <!-- Contact pour ce tirage -->
        <div class="form-group">
            <label class="col-md-4 control-label" for="contact_' . $index . '">Contact</label>  
            <div class="col-md-4">
                <input id="contact_' . $index . '" name="machines[' . $index . '][contact]" class="form-control input-md" type="text" value="">
                <span class="help-block">Modifiez si différent du contact principal</span>
            </div>
        </div>
        
        <!-- Type de machine -->
        <div class="form-group">
            <label class="col-md-4 control-label">Type de machine</label>
            <div class="col-md-4">
                <div class="radio">
                    <label>
                        <input type="radio" name="machines[' . $index . '][type]" value="duplicopieur" checked onchange="toggleMachineType(' . $index . ')">
                        Duplicopieur
                    </label>
                </div>
                <div class="radio">
                    <label>
                        <input type="radio" name="machines[' . $index . '][type]" value="photocopieur" onchange="toggleMachineType(' . $index . ')">
                        Photocopieur
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Interface duplicopieur -->
        <div id="duplicopieur-interface-' . $index . '" class="machine-interface">
            <div class="form-group">
                <label class="col-md-4 control-label">Duplicopieur</label>
                <div class="col-md-4">';
    
    // Logique duplicopieur (identique à la première machine)
    if(isset($duplicopieur_selectionne)) {
        $html .= '<input type="hidden" name="machines[' . $index . '][duplicopieur_id]" value="' . $duplicopieur_selectionne['id'] . '">
                    <p class="form-control-static">
                        <strong>' . htmlspecialchars($duplicopieur_selectionne['marque']) . ' ' . htmlspecialchars($duplicopieur_selectionne['modele']) . '</strong>
                        <br><small class="text-muted">Supporte A3 et A4</small>
                    </p>';
    } elseif(isset($duplicopieurs) && count($duplicopieurs) > 1) {
        $html .= '<select name="machines[' . $index . '][duplicopieur_id]" class="form-control" required onchange="updateDuplicopieurCounters(this.value, ' . $index . ')">
                    <option value="">Choisir un duplicopieur</option>';
        foreach($duplicopieurs as $dup) {
            // Construire le nom de la machine comme dans le template principal
            $machine_name = $dup['marque'];
            if ($dup['marque'] !== $dup['modele']) {
                $machine_name = $dup['marque'] . ' ' . $dup['modele'];
            }
            $html .= '<option value="' . $dup['id'] . '" data-name="' . htmlspecialchars($machine_name) . '">
                        ' . htmlspecialchars($dup['marque']) . ' ' . htmlspecialchars($dup['modele']) . ' 
                        (' . ($dup['supporte_a3'] ? 'A3' : '') . ($dup['supporte_a3'] && $dup['supporte_a4'] ? '/' : '') . ($dup['supporte_a4'] ? 'A4' : '') . ')
                      </option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<p class="form-control-static text-danger">Aucun duplicopieur disponible</p>';
    }
    
    $html .= '</div>
            </div>
            
            <!-- Sélection du tambour -->
            <div class="form-group" id="tambour-group-' . $index . '" style="display: none;">
                <label class="col-md-4 control-label">Tambour utilisé</label>
                <div class="col-md-4">
                    <select name="machines[' . $index . '][tambour]" class="form-control" id="tambour-select-' . $index . '">
                        
                    </select>
                    <span class="help-block">Choisissez le tambour utilisé pour ce tirage</span>
                </div>
            </div>
            
            <!-- Options duplicopieur -->
            <div class="form-group">
                <label class="col-md-4 control-label" for="A4_' . $index . '">A4</label>
                <div class="col-md-4">
                    <div class="checkbox">
                        <label for="A4_' . $index . '">
                            <input name="machines[' . $index . '][A4]" value="A4" type="checkbox" onchange="calculateTotalPrice()">
                            oui
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 control-label" for="rv_' . $index . '">Recto/verso</label>
                <div class="col-md-4">
                    <div class="checkbox">
                        <label for="rv_' . $index . '">
                            <input name="machines[' . $index . '][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()">
                            oui
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="col-md-4 control-label" for="feuilles_payees_' . $index . '">2ème couleur ? (feuilles déjà payées)</label>
                <div class="col-md-4">
                    <div class="checkbox">
                        <label for="feuilles_payees_' . $index . '">
                            <input name="machines[' . $index . '][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()">
                            oui
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Mode de saisie -->
            <div class="form-group">
                <label class="col-md-4 control-label">Mode de saisie</label>
                <div class="col-md-4">
                    <div class="radio">
                        <label>
                            <input type="radio" name="machines[' . $index . '][mode_saisie]" value="compteurs" checked onchange="toggleSaisieMode(' . $index . ')">
                            Compteurs avant/après
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="machines[' . $index . '][mode_saisie]" value="manuel" onchange="toggleSaisieMode(' . $index . ')">
                            Saisie manuelle
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Mode compteurs -->
            <div id="compteurs-mode-' . $index . '" class="saisie-mode">
                <div class="col-md-1"></div>
                <div class="col-md-5">
                    <legend>Avant</legend>
                    <div class="form-group">
                        <label class="col-md-6 control-label" for="master_av_' . $index . '">Nombre de Masters AVANT</label>  
                        <div class="col-md-6">
                            <input id="master_av_' . $index . '" name="machines[' . $index . '][master_av]" class="form-control input-md" type="number" min="0" value="' . $last_values['master_av'] . '" onchange="calculateTotalPrice()">
                            <span class="help-block">Compteur masters avant utilisation</span>  
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-6 control-label" for="passage_av_' . $index . '">Nombre de Passages AVANT</label>  
                        <div class="col-md-6">
                            <input id="passage_av_' . $index . '" name="machines[' . $index . '][passage_av]" class="form-control input-md" type="number" min="0" value="' . $last_values['passage_av'] . '" onchange="calculateTotalPrice()">
                            <span class="help-block">Compteur passages avant utilisation</span>  
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <legend>Après</legend>
                    <div class="form-group">
                        <label class="col-md-6 control-label" for="master_ap_' . $index . '">Nombre de Masters APRÈS</label>  
                        <div class="col-md-6">
                            <input id="master_ap_' . $index . '" name="machines[' . $index . '][master_ap]" class="form-control input-md" type="number" min="0" value="0" onchange="calculateTotalPrice()">
                            <span class="help-block">Compteur masters après utilisation</span>  
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-6 control-label" for="passage_ap_' . $index . '">Nombre de Passages APRÈS</label>  
                        <div class="col-md-6">
                            <input id="passage_ap_' . $index . '" name="machines[' . $index . '][passage_ap]" class="form-control input-md" type="number" min="0" value="0" onchange="calculateTotalPrice()">
                            <span class="help-block">Compteur passages après utilisation</span>  
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mode manuel -->
            <div id="manuel-mode-' . $index . '" class="saisie-mode" style="display:none;">
                <div class="form-group">
                    <label class="col-md-4 control-label" for="nb_masters_' . $index . '">Nombre de Masters</label>  
                    <div class="col-md-4">
                        <input id="nb_masters_' . $index . '" name="machines[' . $index . '][nb_masters]" class="form-control input-md" type="number" min="1" value="1" onchange="calculateTotalPrice()">
                        <span class="help-block">Nombre de masters utilisés</span>  
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-4 control-label" for="nb_passages_' . $index . '">Nombre de Passages</label>  
                    <div class="col-md-4">
                        <input id="nb_passages_' . $index . '" name="machines[' . $index . '][nb_passages]" class="form-control input-md" type="number" min="1" value="1" onchange="calculateTotalPrice()">
                        <span class="help-block">Nombre de passages effectués</span>  
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Interface photocopieur -->
        <div id="photocopieur-interface-' . $index . '" class="machine-interface" style="display:none;">
            <div class="form-group">
                <label class="col-md-4 control-label" for="marque_' . $index . '">Photocopieuse à utiliser</label>
                <div class="col-md-4">
                    <select id="marque_' . $index . '" name="machines[' . $index . '][machine]" class="form-control">';
    
    // Logique photocopieur
    if (isset($photocopiers) && !empty($photocopiers)) {
        $first_photocop = true;
        foreach ($photocopiers as $photocop) {
            $selected = $first_photocop ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($photocop->marque) . '" ' . $selected . '>' . htmlspecialchars($photocop->marque) . '</option>';
            $first_photocop = false;
        }
    } else {
        $html .= '<option value="">-- Aucune photocopieuse disponible --</option>';
    }
    
    $html .= '</select>
                    <span class="help-block">Quelle photocopieuse utilisez-vous ?</span>
                </div>
            </div>
            
            <!-- Brochures -->
            <div class="brochures-container" data-machine="' . $index . '">
                <h6>Brochures/Tracts à imprimer</h6>
                <div class="brochure-item" data-brochure="0">
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="nb_exemplaires_' . $index . '_0">Nombre d\'exemplaires</label>  
                        <div class="col-md-4">
                            <input id="nb_exemplaires_' . $index . '_0" name="machines[' . $index . '][brochures][0][nb_exemplaires]" class="form-control input-md" type="number" min="1" value="1" onchange="calculateTotalPrice()">
                            <span class="help-block">Nombre d\'exemplaires à imprimer</span>  
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="nb_feuilles_' . $index . '_0">Nombre de feuilles par exemplaire</label>  
                        <div class="col-md-4">
                            <input id="nb_feuilles_' . $index . '_0" name="machines[' . $index . '][brochures][0][nb_feuilles]" class="form-control input-md" type="number" min="1" onchange="calculateTotalPrice()">
                            <span class="help-block">Nombre de feuilles par exemplaire</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="radios_' . $index . '_0">Taille</label>
                        <div class="col-md-4"> 
                            <label class="radio-inline" for="radios-' . $index . '-0-0">
                                <input name="machines[' . $index . '][brochures][0][taille]" id="radios-' . $index . '-0-0" value="A4" checked="checked" type="radio" onchange="calculateTotalPrice()">
                                A4
                            </label> 
                            <label class="radio-inline" for="radios-' . $index . '-0-1">
                                <input name="machines[' . $index . '][brochures][0][taille]" id="radios-' . $index . '-0-1" value="A3" type="radio" onchange="calculateTotalPrice()">
                                A3
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="rv_' . $index . '_0">Recto/verso</label>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label for="rv_' . $index . '_0">
                                    <input name="machines[' . $index . '][brochures][0][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()">
                                    oui
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="couleur_' . $index . '_0">Couleur</label>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label for="couleur_' . $index . '_0">
                                    <input name="machines[' . $index . '][brochures][0][couleur]" value="oui" type="checkbox" onchange="calculateTotalPrice()">
                                    oui
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" for="feuilles_payees_' . $index . '_0">Feuilles déjà payées</label>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label for="feuilles_payees_' . $index . '_0">
                                    <input name="machines[' . $index . '][brochures][0][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()">
                                    oui
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Prix de la machine -->
        <div class="form-group">
            <label class="col-md-4 control-label">Prix de ce tirage</label>
            <div class="col-md-4">
                <div class="form-control-static machine-price" data-machine="' . $index . '">0.00€</div>
            </div>
        </div>
        
        <!-- Bouton supprimer -->
        <div class="form-group">
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <button type="button" class="btn btn-danger btn-sm remove-machine" data-index="' . $index . '">- Supprimer ce tirage</button>
            </div>
        </div>';
    
    return $html;
}

