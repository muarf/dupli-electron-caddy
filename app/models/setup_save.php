<?php
require_once __DIR__ . '/../controler/functions/database.php';

function Action($conf = null){
    // Initialiser la configuration si elle n'est pas fournie
    if ($conf === null) {
        include(__DIR__ . '/../controler/conf.php');
    }
    
    // Vérifier si la base de données existe, sinon la créer
    try {
        $db = pdo_connect();
    } catch (PDOException $e) {
        // Base de données n'existe pas, la créer
        require_once __DIR__ . '/admin/SQLiteDatabaseManager.php';
        $dbManager = new SQLiteDatabaseManager($conf);
        $result = $dbManager->createDatabase('duplinew', 'sqlite', '');
        
        if (isset($result['error'])) {
            die('Erreur création BDD: ' . $result['error']);
        }
        
        // Maintenant essayer de se connecter
        try {
            $db = pdo_connect();
        } catch (PDOException $e2) {
            die('Erreur connexion après création: ' . $e2->getMessage());
        }
    }
    
    // Vérifier si des machines ont déjà été enregistrées
    $has_machines = check_machines_exist();
    
    if ($has_machines) {
        // Des machines existent déjà, rediriger vers l'accueil
        header('Location: ?accueil');
        exit;
    }
    
    // Traitement du formulaire POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors = array();
        $success = true;
        
        // Validation du mot de passe administrateur
        if (empty($_POST['admin_password'])) {
            $errors[] = "Veuillez définir un mot de passe administrateur.";
            $success = false;
        } elseif ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
            $errors[] = "Les mots de passe ne correspondent pas.";
            $success = false;
        } elseif (strlen($_POST['admin_password']) < 6) {
            $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
            $success = false;
        }
        
        // Validation des données
        if (empty($_POST['machines'])) {
            $errors[] = "Veuillez sélectionner au moins une machine.";
            $success = false;
        }
        
        // Si pas d'erreurs, créer la structure de la base de données puis enregistrer les machines
        if ($success) {
            try {
                // D'abord, créer la structure de la base de données
                require_once __DIR__ . '/admin/SQLiteDatabaseManager.php';
                $dbManager = new SQLiteDatabaseManager($conf);
                
                // Obtenir la connexion à la base de données
                $db = pdo_connect();
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Créer les tables essentielles
                $dbManager->createEssentialTables($db);
                
                // Inclure MachineManager
                require_once __DIR__ . '/admin/MachineManager.php';
                $machineManager = new AdminMachineManager($conf);
                
                $machines = $_POST['machines'];
                $added_machines = 0;
                
                foreach ($machines as $machine_data) {
                    // Préparer les données pour MachineManager
                    $data = array(
                        'machine_type' => $machine_data['type'],
                        'machine_name' => $machine_data['name'],
                        'master_counter' => intval($machine_data['master_counter'] ?? 0),
                        'passage_counter' => intval($machine_data['passage_counter'] ?? 0),
                        'tambours' => ['tambour_noir'] // Par défaut
                    );
                    
                    // Ajouter les prix selon le type de machine
                    if ($machine_data['type'] === 'duplicopieur') {
                        $data['prix_master_unite'] = floatval($machine_data['prix_master_unite'] ?? 0.4);
                        $data['prix_master_pack'] = floatval($machine_data['prix_master_pack'] ?? 0.4);
                        
                        // Gérer les tambours
                        if (isset($machine_data['tambours']) && is_array($machine_data['tambours'])) {
                            $tambours = [];
                            $prix_tambour_unite = [];
                            $prix_tambour_pack = [];
                            
                            foreach ($machine_data['tambours'] as $tambour) {
                                $tambours[] = $tambour['name'];
                                $prix_tambour_unite[] = floatval($tambour['unite'] ?? 0.002);
                                $prix_tambour_pack[] = floatval($tambour['pack'] ?? 11);
                            }
                            
                            $data['tambours'] = $tambours;
                            $data['prix_tambour_unite'] = $prix_tambour_unite;
                            $data['prix_tambour_pack'] = $prix_tambour_pack;
                        } else {
                            // Valeurs par défaut
                            $data['tambours'] = ['tambour_noir'];
                            $data['prix_tambour_unite'] = [0.002];
                            $data['prix_tambour_pack'] = [11];
                        }
                    } else if ($machine_data['type'] === 'photocop_encre') {
                        // Prix pour photocopieuse encre
                        $data['noire_unite'] = floatval($machine_data['noire_unite'] ?? 0.015);
                        $data['noire_pack'] = floatval($machine_data['noire_pack'] ?? 140);
                        $data['bleue_unite'] = floatval($machine_data['bleue_unite'] ?? 0.005);
                        $data['bleue_pack'] = floatval($machine_data['bleue_pack'] ?? 140);
                        $data['rouge_unite'] = floatval($machine_data['rouge_unite'] ?? 0.005);
                        $data['rouge_pack'] = floatval($machine_data['rouge_pack'] ?? 140);
                        $data['jaune_unite'] = floatval($machine_data['jaune_unite'] ?? 0.005);
                        $data['jaune_pack'] = floatval($machine_data['jaune_pack'] ?? 140);
                    } else if ($machine_data['type'] === 'photocop_toner') {
                        // Prix pour photocopieuse toner
                        $data['toner_noir_prix'] = floatval($machine_data['toner_noir_prix'] ?? 80);
                        $data['toner_noir_prix_copie'] = floatval($machine_data['toner_noir_prix_copie'] ?? 0.00348);
                        $data['toner_cyan_prix'] = floatval($machine_data['toner_cyan_prix'] ?? 80);
                        $data['toner_cyan_prix_copie'] = floatval($machine_data['toner_cyan_prix_copie'] ?? 0.00444);
                        $data['toner_magenta_prix'] = floatval($machine_data['toner_magenta_prix'] ?? 80);
                        $data['toner_magenta_prix_copie'] = floatval($machine_data['toner_magenta_prix_copie'] ?? 0.00444);
                        $data['toner_jaune_prix'] = floatval($machine_data['toner_jaune_prix'] ?? 80);
                        $data['toner_jaune_prix_copie'] = floatval($machine_data['toner_jaune_prix_copie'] ?? 0.00444);
                        $data['tambour_prix'] = floatval($machine_data['tambour_prix'] ?? 200);
                        $data['tambour_prix_copie'] = floatval($machine_data['tambour_prix_copie'] ?? 0.00167);
                        $data['dev_prix'] = floatval($machine_data['dev_prix'] ?? 300);
                        $data['dev_prix_copie'] = floatval($machine_data['dev_prix_copie'] ?? 0.00250);
                    }
                    
                    // Ajouter la machine
                    $result = $machineManager->addMachine($data);
                    
                    if (isset($result['error'])) {
                        $errors[] = $result['error'];
                        $success = false;
                    } else {
                        $added_machines++;
                    }
                }
                
                if ($success && $added_machines > 0) {
                    // Configuration des prix du papier
                    if (isset($_POST['prix_papier_A3']) && !empty($_POST['prix_papier_A3'])) {
                        $prix_A3 = floatval($_POST['prix_papier_A3']);
                        $prix_A4 = $prix_A3 / 2;
                        
                        $db = pdo_connect();
                        $query = $db->prepare('INSERT INTO papier (prix) VALUES (?) ON DUPLICATE KEY UPDATE prix = VALUES(prix)');
                        $query->execute(array($prix_A4));
                    }
                    
                    // Créer le mot de passe administrateur
                    $admin_password = $_POST['admin_password'];
                    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                    
                    $db = pdo_connect();
                    $query = $db->prepare('INSERT INTO admin_passwords (password_hash, is_active) VALUES (?, 1)');
                    $query->execute(array($password_hash));
                    
                    // Rediriger vers l'accueil avec un message de succès
                    header('Location: ?accueil&setup=success');
                    exit;
                }
                
            } catch (Exception $e) {
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
                $success = false;
            }
        }
    }
    
    // Si on arrive ici, il y a eu des erreurs ou ce n'est pas un POST
    // Rediriger vers la page de setup avec les erreurs
    if (!empty($errors)) {
        $_SESSION['setup_errors'] = $errors;
    }
    
    header('Location: ?setup');
    exit;
}

/**
 * Configure les prix des machines et consommables
 */
function configure_prices($db) {
    // Prix des feuilles
    if (isset($_POST['prix_papier_A3']) && !empty($_POST['prix_papier_A3'])) {
        $prix_A3 = floatval($_POST['prix_papier_A3']);
        $prix_A4 = $prix_A3 / 2;
        
        error_log("DEBUG: Tentative INSERT/UPDATE dans table papier");
        // Insérer/MAJ prix papier
        $query = $db->prepare('INSERT INTO papier (prix) VALUES (?) ON DUPLICATE KEY UPDATE prix = VALUES(prix)');
        $query->execute(array($prix_A4)); // On stocke le prix A4, A3 sera calculé
    }
    
    // Prix des consommables A3
    if (isset($_POST['prix_encre_A3']) && !empty($_POST['prix_encre_A3'])) {
        $prix_encre_A3_pack = isset($_POST['prix_encre_A3_pack']) ? $_POST['prix_encre_A3_pack'] : $_POST['prix_encre_A3'];
        insert_prix_with_pack($db, 'A3', 'encre', $_POST['prix_encre_A3'], $prix_encre_A3_pack);
    }
    if (isset($_POST['prix_master_A3']) && !empty($_POST['prix_master_A3'])) {
        $prix_master_A3_pack = isset($_POST['prix_master_A3_pack']) ? $_POST['prix_master_A3_pack'] : $_POST['prix_master_A3'];
        insert_prix_with_pack($db, 'A3', 'master', $_POST['prix_master_A3'], $prix_master_A3_pack);
    }
    
    // Prix des consommables A4 (automatiquement la moitié de A3)
    if (isset($_POST['prix_encre_A3']) && !empty($_POST['prix_encre_A3'])) {
        $prix_encre_A4 = floatval($_POST['prix_encre_A3']) / 2;
        $prix_encre_A4_pack = isset($_POST['prix_encre_A3_pack']) ? floatval($_POST['prix_encre_A3_pack']) / 2 : $prix_encre_A4;
        insert_prix_with_pack($db, 'A4', 'encre', $prix_encre_A4, $prix_encre_A4_pack);
    }
    if (isset($_POST['prix_master_A3']) && !empty($_POST['prix_master_A3'])) {
        $prix_master_A4 = floatval($_POST['prix_master_A3']) / 2;
        $prix_master_A4_pack = isset($_POST['prix_master_A3_pack']) ? floatval($_POST['prix_master_A3_pack']) / 2 : $prix_master_A4;
        insert_prix_with_pack($db, 'A4', 'master', $prix_master_A4, $prix_master_A4_pack);
    }
    
    // Prix des photocopieurs par couleur et par machine
    if (isset($_POST['photocop_prix']) && is_array($_POST['photocop_prix'])) {
        foreach ($_POST['photocop_prix'] as $index => $couleurs) {
            $photocop_names = $_POST['photocop_names'];
            $photocop_name = isset($photocop_names[$index]) ? $photocop_names[$index] : 'Photocopieuse ' . ($index + 1);
            $machine_id = strtolower(str_replace(' ', '_', $photocop_name));
            
            $prix_total_unite = 0;
            $prix_total_pack = 0;
            
            foreach ($couleurs as $couleur => $types) {
                if (isset($types['unite']) && !empty($types['unite'])) {
                    $prix_unite = $types['unite'];
                    $prix_pack = isset($types['pack']) ? $types['pack'] : $prix_unite;
                    
                    insert_prix_with_pack($db, $machine_id, $couleur, $prix_unite, $prix_pack);
                    
                    // Accumuler pour calculer le prix "couleur"
                    $prix_total_unite += $prix_unite;
                    $prix_total_pack += $prix_pack;
                }
            }
            
            // Insérer le prix "couleur" (somme des 4 couleurs)
            if ($prix_total_unite > 0) {
                insert_prix_with_pack($db, $machine_id, 'couleur', $prix_total_unite, $prix_total_pack);
            }
        }
    }
}

/**
 * Insère ou met à jour un prix (fonction locale pour setup_save)
 */
function insert_prix_setup($db, $machine, $type, $prix_unite) {
    // Vérifier si le prix existe déjà
    $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine = ? AND type = ?');
    $query->execute(array($machine, $type));
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if ($result->count > 0) {
        // Mise à jour
        $query = $db->prepare('UPDATE prix SET unite = ? WHERE machine = ? AND type = ?');
        $query->execute(array($prix_unite, $machine, $type));
    } else {
        // Insertion
        $query = $db->prepare('INSERT INTO prix (machine, type, unite, pack) VALUES (?, ?, ?, ?)');
        $query->execute(array($machine, $type, $prix_unite, $prix_unite)); // pack = unite par défaut
    }
}

/**
 * Insère ou met à jour un prix avec prix unité et pack séparés
 */
function insert_prix_with_pack($db, $machine, $type, $prix_unite, $prix_pack) {
    error_log("DEBUG: insert_prix_with_pack - machine: $machine, type: $type");
    // Vérifier si le prix existe déjà
    $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine = ? AND type = ?');
    $query->execute(array($machine, $type));
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if ($result->count > 0) {
        // Mise à jour
        error_log("DEBUG: UPDATE prix pour $machine/$type");
        $query = $db->prepare('UPDATE prix SET unite = ?, pack = ? WHERE machine = ? AND type = ?');
        $query->execute(array($prix_unite, $prix_pack, $machine, $type));
    } else {
        // Insertion
        error_log("DEBUG: INSERT prix pour $machine/$type");
        $query = $db->prepare('INSERT INTO prix (machine, type, unite, pack) VALUES (?, ?, ?, ?)');
        $query->execute(array($machine, $type, $prix_unite, $prix_pack));
    }
}

/**
 * Initialise la table cons avec les changements initiaux de consommables
 */
function initialize_cons_table($db) {
    error_log("DEBUG: Début initialize_cons_table");
    $current_time = time();
    
    // Initialiser les consommables pour A3 si sélectionné
    if (isset($_POST['machines']) && in_array('a3', $_POST['machines'])) {
        error_log("DEBUG: Initialisation consommables A3");
        // Encre A3
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
        $query->execute(array($current_time, 'a3', 'encre', 0, 0));
        
        // Master A3
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
        $query->execute(array($current_time, 'a3', 'master', 0, 0));
    }
    
    // Initialiser les consommables pour A4 si sélectionné
    if (isset($_POST['machines']) && in_array('a4', $_POST['machines'])) {
        error_log("DEBUG: Initialisation consommables A4");
        // Encre A4
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
        $query->execute(array($current_time, 'a4', 'encre', 0, 0));
        
        // Master A4
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
        $query->execute(array($current_time, 'a4', 'master', 0, 0));
    }
    
    // Initialiser les consommables pour les photocopieurs
    if (isset($_POST['machines']) && in_array('photocop', $_POST['machines'])) {
        $photocop_counters = $_POST['photocop_counters'];
        $photocop_names = $_POST['photocop_names'];
        
        for ($i = 0; $i < count($photocop_counters); $i++) {
            $photocop_name = isset($photocop_names[$i]) ? $photocop_names[$i] : 'Photocopieuse ' . ($i + 1);
            $photocop_counter = isset($photocop_counters[$i]) ? $photocop_counters[$i] : 0;
            
            error_log("DEBUG: Initialisation consommables photocopieuse $photocop_name");
            
            // Créer une entrée initiale dans la table cons pour cette photocopieuse
            $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)');
            $query->execute(array($current_time, $photocop_name, 'initialisation', $photocop_counter, 0));
        }
    }
}
?>
