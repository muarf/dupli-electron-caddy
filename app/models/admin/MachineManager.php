<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/i18n.php';

/**
 * Gestionnaire pour les machines (duplicopieurs et photocopieurs) - Administration
 */
class AdminMachineManager {
    private $conf;
    
    public function __construct($conf) {
        $this->conf = $conf;
    }
    
    /**
     * Ajouter une nouvelle machine
     */
    public function addMachine($data) {
        try {
            if (!class_exists('Pdotest')) {
                require_once __DIR__ . '/../../controler/func.php';
            }
            
            $con = pdo_connect();
            $db = pdo_connect();
            
            // Debug: logger les données reçues
            error_log("DEBUG addMachine - Données reçues: " . print_r($data, true));
            
            $machine_type = $data['machine_type'];
            $machine_name = $data['machine_name'];
            $master_counter = $data['master_counter'] ?? 0;
            $passage_counter = $data['passage_counter'] ?? 0;
            $prix_master_unite = $data['prix_master_unite'] ?? 0.4; // Prix unité par défaut
            $prix_master_pack = $data['prix_master_pack'] ?? 70; // Prix pack par défaut
            
            // Gestion des tambours
            $tambours = $data['tambours'] ?? ['tambour_noir'];
            $prix_tambour_unite = $data['prix_tambour_unite'] ?? [0.002];
            $prix_tambour_pack = $data['prix_tambour_pack'] ?? [11];
            
            error_log("DEBUG addMachine - machine_type: $machine_type, machine_name: $machine_name, master_counter: $master_counter, passage_counter: $passage_counter");
            error_log("DEBUG addMachine - prix_master_unite: $prix_master_unite, prix_master_pack: $prix_master_pack");
            
            if ($machine_type === 'duplicopieur') {
                $this->insertDuplicopieur($db, $machine_name, $master_counter, $passage_counter, $prix_master_unite, $prix_master_pack, $tambours, $prix_tambour_unite, $prix_tambour_pack);
            } else {
                $this->insertPhotocopieur($db, $machine_type, $machine_name, $passage_counter, $data);
            }
            
            // Créer automatiquement une aide pour la nouvelle machine
            $this->createDefaultAide($db, $machine_name);
            
            return ['success' => "Machine $machine_name ajoutée avec succès"];
            
        } catch (Exception $e) {
            return ['error' => "Erreur lors de l'ajout de la machine : " . $e->getMessage()];
        }
    }
    
    /**
     * Insérer un duplicopieur
     */
    private function insertDuplicopieur($db, $name, $master_counter, $passage_counter, $prix_master_unite, $prix_master_pack, $tambours, $prix_tambour_unite, $prix_tambour_pack) {
        $date = time();
        
        // Nettoyer le nom de la machine pour créer un nom de table valide
        $table_name = $this->sanitizeTableName($name);
        
        // Insérer dans la table duplicopieurs avec le modèle comme nom principal
        $tambours_json = json_encode($tambours);
        $query = $db->prepare('INSERT INTO duplicopieurs (marque, modele, supporte_a3, supporte_a4, actif, tambours, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $query->execute([
            $name, // marque = modèle (ce qui nous intéresse)
            $name, // modele = même nom
            1, // supporte_a3 = toujours vrai pour les duplicopieurs
            1, // supporte_a4 = toujours vrai pour les duplicopieurs
            $tambours_json // tambours = liste des tambours en JSON
        ]);
        
        // Récupérer l'ID du duplicopieur créé
        $duplicopieur_id = $db->lastInsertId();
        
        // Insérer les prix dans la table prix
        $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
        
        // Prix du master
        $query->execute(['dupli', $duplicopieur_id, 'master', $prix_master_unite, $prix_master_pack]);
        
        // Prix de chaque tambour
        for ($i = 0; $i < count($tambours); $i++) {
            $tambour = $tambours[$i];
            $prix_unite = isset($prix_tambour_unite[$i]) ? $prix_tambour_unite[$i] : 0.002;
            $prix_pack = isset($prix_tambour_pack[$i]) ? $prix_tambour_pack[$i] : 0;
            $query->execute(['dupli', $duplicopieur_id, $tambour, $prix_unite, $prix_pack]);
        }
        
        // Insérer les compteurs initiaux dans la table dupli avec le nom complet de la machine
        if ($master_counter > 0 || $passage_counter > 0) {
            $query = $db->prepare('INSERT INTO dupli (type, contact, master_av, master_ap, passage_av, passage_ap, rv, prix, paye, cb, mot, date, nom_machine, duplicopieur_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $query->execute([
                'tirage', // type
                'admin', // contact
                $master_counter, // master_av
                $master_counter, // master_ap (même valeur au début)
                $passage_counter, // passage_av
                $passage_counter, // passage_ap (même valeur au début)
                'non', // rv
                0, // prix
                'non', // paye
                0, // cb
                'Initialisation duplicopieur', // mot
                $date, // date
                $name, // nom_machine (nom complet)
                $duplicopieur_id // duplicopieur_id
            ]);
            
            // Créer les enregistrements initiaux dans cons pour les changements de master et de tambours
            // Changement de master initial
            if ($master_counter > 0) {
                $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m, tambour) VALUES (?, ?, "master", 0, ?, NULL)');
                $query->execute([$date, strtolower($name), $master_counter]);
            }
            
            // Changement de tambours initiaux
            if ($passage_counter > 0) {
                foreach ($tambours as $tambour) {
                    $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m, tambour) VALUES (?, ?, "tambour", ?, 0, ?)');
                    $query->execute([$date, strtolower($name), $passage_counter, $tambour]);
                }
            }
        }
        
        return true;
    }
    
    /**
     * Nettoyer le nom pour créer un nom de table valide
     */
    private function sanitizeTableName($name) {
        // Remplacer les espaces et caractères spéciaux par des underscores
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        // S'assurer que le nom commence par une lettre
        if (preg_match('/^[0-9]/', $table_name)) {
            $table_name = 'machine_' . $table_name;
        }
        return strtolower($table_name);
    }
    
    /**
     * Créer la table spécifique à une machine
     */
    private function createMachineTable($db, $table_name) {
        $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` varchar(255) NOT NULL,
            `contact` varchar(255) NOT NULL,
            `master_av` varchar(255) NOT NULL,
            `master_ap` varchar(255) NOT NULL,
            `passage_av` varchar(255) NOT NULL,
            `passage_ap` varchar(255) NOT NULL,
            `rv` varchar(255) NOT NULL,
            `prix` varchar(255) NOT NULL,
            `paye` varchar(255) NOT NULL,
            `cb` varchar(255) NOT NULL,
            `mot` varchar(255) NOT NULL,
            `date` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1";
        
        $db->exec($sql);
        error_log("DEBUG: Table créée pour la machine: {$table_name}");
    }
    
    /**
     * Insérer un photocopieur
     */
    private function insertPhotocopieur($db, $type, $name, $passage_counter, $data) {
        $date = time();
        
        // Déterminer le type d'encre
        $type_encre = ($type === 'photocop_encre') ? 'encre' : 'toner';
        
        // Insérer dans la table photocopieurs
        $query = $db->prepare('INSERT INTO photocopieurs (marque, modele, type_encre, actif) VALUES (?, ?, ?, 1)');
        $query->execute([$name, $name, $type_encre]);
        
        // Toujours créer une entrée dans cons
        $query = $db->prepare('INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, "passage", ?, 0)');
        $query->execute([$date, $name, $passage_counter]);
        
        // Créer automatiquement un historique pour les photocopieurs
        $this->createPhotocopHistory($db, $name, $type, $passage_counter);
        
        if ($type === 'photocop_encre') {
            $this->insertPhotocopieurEncrePrix($db, $name, $data);
        } else if ($type === 'photocop_toner') {
            $this->insertPhotocopieurTonerPrix($db, $name, $data);
        }
    }
    
    /**
     * Créer automatiquement un historique pour un photocopieur
     */
    private function createPhotocopHistory($db, $machine_name, $type, $current_counter) {
        $base_time = time() - (30 * 24 * 3600); // Il y a 30 jours
        
        if ($type === 'photocop_toner') {
            // Créer un historique pour les toners
            $toner_colors = ['noir', 'cyan', 'magenta', 'jaune'];
            $counters = [5000, 6000, 7000, 8000]; // Compteurs différents pour chaque couleur
            
            foreach ($toner_colors as $index => $color) {
                // Premier changement il y a 30 jours
                $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$base_time + ($index * 86400), $machine_name, $color, $counters[$index], 0]);
                
                // Deuxième changement il y a 15 jours
                $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$base_time + (15 * 86400) + ($index * 86400), $machine_name, $color, $counters[$index] + 10000, 0]);
            }
            
            // Ajouter tambour et dev avec historique
            $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$base_time, $machine_name, 'tambour', 10000, 0]);
            
            $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$base_time + (15 * 86400), $machine_name, 'tambour', 25000, 0]);
            
            $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$base_time, $machine_name, 'dev', 15000, 0]);
            
            $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$base_time + (15 * 86400), $machine_name, 'dev', 30000, 0]);
            
        } else if ($type === 'photocop_encre') {
            // Créer un historique pour les encres
            $encre_colors = ['noire', 'bleue', 'rouge', 'jaune'];
            $counters = [3000, 3500, 4000, 4500]; // Compteurs différents pour chaque couleur
            
            foreach ($encre_colors as $index => $color) {
                // Premier changement il y a 30 jours
                $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$base_time + ($index * 86400), $machine_name, $color, $counters[$index], 0]);
                
                // Deuxième changement il y a 15 jours
                $stmt = $db->prepare("INSERT INTO cons (date, machine, type, nb_p, nb_m) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$base_time + (15 * 86400) + ($index * 86400), $machine_name, $color, $counters[$index] + 8000, 0]);
            }
        }
    }
    
    /**
     * Insérer les prix pour un photocopieur à encre
     */
    private function insertPhotocopieurEncrePrix($db, $name, $data) {
        $colors = ['noire', 'bleue', 'rouge', 'jaune'];
        
        // Déterminer machine_type et machine_id selon le nom
        $machine_type = 'photocop';
        
        // Récupérer l'ID réel de la machine depuis la table photocopieurs
        $query = $db->prepare('SELECT id FROM photocopieurs WHERE marque = ? AND actif = 1 ORDER BY id DESC LIMIT 1');
        $query->execute([$name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("DEBUG insertPhotocopieurEncrePrix - ERREUR: Machine '$name' non trouvée dans photocopieurs");
            return false;
        }
        
        $machine_id = $result['id'];
        
        foreach ($colors as $color) {
            $unite = $data[$color . '_unite'] ?? 0;
            $pack = $data[$color . '_pack'] ?? 0;
            
            $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
            $query->execute([$machine_type, $machine_id, $color, $unite, $pack]);
        }
    }
    
    /**
     * Insérer les prix pour un photocopieur à toner
     */
    private function insertPhotocopieurTonerPrix($db, $name, $data) {
        $toner_colors = ['noir', 'cyan', 'magenta', 'jaune'];
        
        // Déterminer machine_type et machine_id selon le nom
        $machine_type = 'photocop';
        
        // Récupérer l'ID réel de la machine depuis la table photocopieurs
        $query = $db->prepare('SELECT id FROM photocopieurs WHERE marque = ? AND actif = 1 ORDER BY id DESC LIMIT 1');
        $query->execute([$name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("DEBUG insertPhotocopieurTonerPrix - ERREUR: Machine '$name' non trouvée dans photocopieurs");
            return false;
        }
        
        $machine_id = $result['id'];
        
        error_log("DEBUG insertPhotocopieurTonerPrix - machine_type: $machine_type, machine_id: $machine_id");
        
        foreach ($toner_colors as $color) {
            $prix_cartouche = $data['toner_' . $color . '_prix'] ?? 80;
            // Prix par défaut selon les capacités Konica Minolta bizhub C360
            $prix_copie_default = ($color === 'noir') ? 0.00348 : 0.00444; // Noir: 23k pages, Couleurs: 18k pages
            $prix_copie = $data['toner_' . $color . '_prix_copie'] ?? $prix_copie_default;
            
            error_log("DEBUG insertPhotocopieurTonerPrix - Inserting $color: prix_cartouche=$prix_cartouche, prix_copie=$prix_copie");
            
            try {
                $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
                $query->execute([$machine_type, $machine_id, $color, $prix_copie, $prix_cartouche]);
                error_log("DEBUG insertPhotocopieurTonerPrix - Successfully inserted $color");
            } catch (Exception $e) {
                error_log("DEBUG insertPhotocopieurTonerPrix - Error inserting $color: " . $e->getMessage());
            }
        }
        
        $tambour_prix_copie = $data['tambour_prix_copie'] ?? 0.00167; // 200€ ÷ 120k pages
        $dev_prix_copie = $data['dev_prix_copie'] ?? 0.00250; // 300€ ÷ 120k pages
        
        try {
            $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
            $query->execute([$machine_type, $machine_id, 'tambour', $tambour_prix_copie, $data['tambour_prix'] ?? 200]);
            error_log("DEBUG insertPhotocopieurTonerPrix - Successfully inserted tambour");
        } catch (Exception $e) {
            error_log("DEBUG insertPhotocopieurTonerPrix - Error inserting tambour: " . $e->getMessage());
        }
        
        try {
            $query = $db->prepare('INSERT INTO prix (machine_type, machine_id, type, unite, pack) VALUES (?, ?, ?, ?, ?)');
            $query->execute([$machine_type, $machine_id, 'dev', $dev_prix_copie, $data['dev_prix'] ?? 300]);
            error_log("DEBUG insertPhotocopieurTonerPrix - Successfully inserted dev");
        } catch (Exception $e) {
            error_log("DEBUG insertPhotocopieurTonerPrix - Error inserting dev: " . $e->getMessage());
        }
    }
    
    /**
     * Récupérer la liste des machines
     */
    public function getMachines() {
        try {
            if (!class_exists('Pdotest')) {
                require_once __DIR__ . '/../../controler/func.php';
            }
            
            $con = pdo_connect();
            $db = pdo_connect();
            
            $machines = [];
            
            // 1. DUPLICOPIEURS : Récupérer UNIQUEMENT depuis la table duplicopieurs
            $query = $db->query('SELECT id, marque, modele, tambours FROM duplicopieurs WHERE actif = 1');
            $duplicopieurs_table = $query->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($duplicopieurs_table as $dup) {
                // Construire le nom de la machine (éviter la duplication si marque = modèle)
                $machine_name = $dup['marque'] . ' ' . $dup['modele'];
                if ($dup['marque'] === $dup['modele']) {
                    $machine_name = $dup['marque'];
                }
                
                // Pour chaque duplicopieur, récupérer les derniers compteurs spécifiques à cette machine
                $query_counters = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                $query_counters->execute([$machine_name]);
                $last_counters = $query_counters->fetch(PDO::FETCH_ASSOC);
                
                $master_counter = $last_counters ? ceil($last_counters['master_ap']) : 0;
                $passage_counter = $last_counters ? ceil($last_counters['passage_ap']) : 0;
                
                $machines[] = [
                    'id' => $dup['id'], // Utiliser l'ID réel de la base de données
                    'type' => 'duplicopieur', // Type simple basé sur la table
                    'machine_type' => 'duplicopieur', // Pour le filtrage dans le template
                    'name' => $machine_name,
                    'master_counter' => $master_counter,
                    'passage_counter' => $passage_counter,
                    'tambours' => $dup['tambours'] ?? '["tambour_noir"]' // Tambours en JSON
                ];
            }
            
            // 2. PHOTOCOPIEURS : Récupérer UNIQUEMENT depuis la table photocopieurs
            $query = $db->query('SELECT id, marque, modele FROM photocopieurs WHERE actif = 1');
            $photocopieurs_table = $query->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($photocopieurs_table as $photocop) {
                $machine_name = $photocop['marque'];
                if (!empty($photocop['modele']) && $photocop['marque'] !== $photocop['modele']) {
                    $machine_name = $photocop['marque'] . ' ' . $photocop['modele'];
                }
                
                $machines[] = [
                    'id' => $photocop['id'], // Utiliser l'ID réel de la base de données
                    'type' => 'photocopieur', // Type simple basé sur la table
                    'machine_type' => 'photocopieur', // Pour le filtrage dans le template
                    'name' => $machine_name,
                    'master_counter' => 'N/A',
                    'passage_counter' => $this->getLastPassageCounter($db, $machine_name)
                ];
            }
            
            return $machines;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Récupérer le dernier compteur pour une machine
     */
    private function getLastCounter($db, $machine, $type) {
        // CORRECTION : Recherche insensible à la casse
        $query = $db->prepare('SELECT nb_p, nb_m FROM cons WHERE LOWER(machine) = LOWER(?) AND type = ? ORDER BY date DESC LIMIT 1');
        $query->execute([$machine, $type]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $type === 'master' ? $result['nb_m'] : $result['nb_p'];
        }
        
        return 0;
    }
    
    /**
     * Récupérer le dernier compteur de passage pour une machine
     */
    private function getLastPassageCounter($db, $machine) {
        // CORRECTION : Recherche insensible à la casse
        $query = $db->prepare('SELECT nb_p FROM cons WHERE LOWER(machine) = LOWER(?) ORDER BY date DESC LIMIT 1');
        $query->execute([$machine]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['nb_p'];
        }
        
        return 0;
    }
    
    /**
     * Déterminer le type de photocopieuse (encre ou toner)
     */
    public function determinePhotocopType($db, $machine_name) {
        // Déterminer le type et l'ID de machine selon le nom
        $machine_type = '';
        $machine_id = 0;
        
        if (strtolower($machine_name) === 'comcolor') {
            $machine_type = 'photocop';
            $machine_id = 1;
        } elseif (strtolower($machine_name) === 'konika') {
            $machine_type = 'photocop';
            $machine_id = 2;
        } else {
            // Pour les autres machines, essayer de les détecter
            $machine_type = 'photocop';
            $machine_id = 1; // Par défaut
        }
        
        // Vérifier si c'est une machine toner (a des tambours/dev)
        $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine_type = ? AND machine_id = ? AND type IN ("tambour", "dev")');
        $query->execute([$machine_type, $machine_id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return 'photocop_toner';
        }
        
        // Vérifier si c'est une machine encre (a des couleurs individuelles)
        $query = $db->prepare('SELECT COUNT(*) as count FROM prix WHERE machine_type = ? AND machine_id = ? AND type IN ("noire", "bleue", "rouge", "jaune")');
        $query->execute([$machine_type, $machine_id]);
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return 'photocop_encre';
        }
        
        return 'photocop_encre'; // Par défaut
    }
    
    /**
     * Supprimer une machine
     */
    public function deleteMachine($machine_id, $machine_type) {
        try {
            if (!class_exists('Pdotest')) {
                require_once __DIR__ . '/../../controler/func.php';
            }
            
            $con = pdo_connect();
            $db = pdo_connect();
            
            // Démarrer une transaction pour SQLite
            $db->beginTransaction();
            
            if ($machine_type === 'duplicopieur') {
                // Pour les duplicopieurs : supprimer de la table duplicopieurs
                $query = $db->prepare('SELECT marque, modele FROM duplicopieurs WHERE id = ?');
                $query->execute([$machine_id]);
                $duplicopieur = $query->fetch(PDO::FETCH_ASSOC);
                
                if ($duplicopieur) {
                    $machine_name = $duplicopieur['marque'] . ' ' . $duplicopieur['modele'];
                    if ($duplicopieur['marque'] === $duplicopieur['modele']) {
                        $machine_name = $duplicopieur['marque'];
                    }
                    
                    // Supprimer les enregistrements dans cons
                    $query = $db->prepare('DELETE FROM cons WHERE machine = ?');
                    $query->execute([strtolower($machine_name)]);
                    
                    // Supprimer les enregistrements dans dupli
                    $query = $db->prepare('DELETE FROM dupli WHERE nom_machine = ?');
                    $query->execute([$machine_name]);
                    
                    // Supprimer de la table duplicopieurs
                    $query = $db->prepare('DELETE FROM duplicopieurs WHERE id = ?');
                    $query->execute([$machine_id]);
                    
                    // Valider la transaction
                    $db->commit();
                    
                    return ['success' => "Duplicopieur $machine_name supprimé avec succès"];
                } else {
                    return ['error' => "Duplicopieur introuvable"];
                }
                
            } elseif ($machine_type === 'photocopieur') {
                // Pour les photocopieurs : supprimer de la table photocopieurs
                $query = $db->prepare('SELECT marque, modele FROM photocopieurs WHERE id = ?');
                $query->execute([$machine_id]);
                $photocopieur = $query->fetch(PDO::FETCH_ASSOC);
                
                if ($photocopieur) {
                    $machine_name = $photocopieur['marque'];
                    if (!empty($photocopieur['modele']) && $photocopieur['marque'] !== $photocopieur['modele']) {
                        $machine_name = $photocopieur['marque'] . ' ' . $photocopieur['modele'];
                    }
                    
                    // Supprimer les enregistrements dans cons
                    $query = $db->prepare('DELETE FROM cons WHERE machine = ?');
                    $query->execute([strtolower($machine_name)]);
                    
                    // Supprimer de la table photocopieurs
                    $query = $db->prepare('DELETE FROM photocopieurs WHERE id = ?');
                    $query->execute([$machine_id]);
                    
                    // Valider la transaction
                    $db->commit();
                    
                    return ['success' => "Photocopieur $machine_name supprimé avec succès"];
                } else {
                    return ['error' => "Photocopieur introuvable"];
                }
            } else {
                return ['error' => "Type de machine invalide"];
            }
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['error' => "Erreur lors de la suppression : " . $e->getMessage()];
        }
    }
    
    /**
     * Mettre à jour une machine
     */
    public function updateMachine($data) {
        try {
            if (!class_exists('Pdotest')) {
                require_once __DIR__ . '/../../controler/func.php';
            }
            
            $con = pdo_connect();
            $db = pdo_connect();
            
            $old_id = $data['edit_machine_id'];
            $new_id = $data['edit_machine_name'];
            $old_type = $data['edit_machine_type'];
            $new_type = $data['edit_machine_type_new'];
            
            // Si le nom a changé, mettre à jour toutes les références
            if ($old_id !== $new_id) {
                $query = $db->prepare('UPDATE cons SET machine = ? WHERE machine = ?');
                $query->execute([$new_id, $old_id]);
                
                $query = $db->prepare('UPDATE prix SET machine = ? WHERE machine = ?');
                $query->execute([$new_id, $old_id]);
                
                // Mettre à jour aussi la table photocop
                $query = $db->prepare('UPDATE photocop SET marque = ? WHERE marque = ?');
                $query->execute([$new_id, $old_id]);
                
                // Mettre à jour l'aide si elle existe
                $query = $db->prepare('UPDATE aide_machines SET machine = ? WHERE machine = ?');
                $query->execute([$new_id, $old_id]);
            }
            
            // Si le type a changé, gérer la conversion
            if ($old_type !== $new_type) {
                $this->handleTypeChange($db, $new_id, $old_type, $new_type, $data);
            }
            
            // Mettre à jour les compteurs
            if ($new_type === 'dupli' || $new_type === 'a4') {
                if (isset($data['edit_master_counter']) && is_numeric($data['edit_master_counter'])) {
                    $query = $db->prepare('UPDATE cons SET nb_m = ? WHERE machine = ? AND type = "master" ORDER BY date DESC LIMIT 1');
                    $query->execute([$data['edit_master_counter'], $new_id]);
                }
                if (isset($data['edit_passage_counter']) && is_numeric($data['edit_passage_counter'])) {
                    $query = $db->prepare('UPDATE cons SET nb_p = ? WHERE machine = ? AND type = "passage" ORDER BY date DESC LIMIT 1');
                    $query->execute([$data['edit_passage_counter'], $new_id]);
                }
            } else {
                $counter_field = 'edit_passage_counter_' . ($new_type === 'photocop_encre' ? 'encre' : 'toner');
                if (isset($data[$counter_field]) && is_numeric($data[$counter_field])) {
                    $query = $db->prepare('UPDATE cons SET nb_p = ? WHERE machine = ? AND type = "passage" ORDER BY date DESC LIMIT 1');
                    $query->execute([$data[$counter_field], $new_id]);
                }
            }
            
            return ['success' => "Machine $old_id mise à jour vers $new_id avec succès"];
            
        } catch (Exception $e) {
            return ['error' => "Erreur lors de la mise à jour : " . $e->getMessage()];
        }
    }
    
    /**
     * Renommer une machine et mettre à jour toutes les références
     */
    public function renameMachine($old_name, $new_name) {
        try {
            if (!class_exists('Pdotest')) {
                require_once __DIR__ . '/../../controler/func.php';
            }
            
            $con = pdo_connect();
            $db = pdo_connect();
            
            // Validation du nouveau nom
            if (empty($new_name) || trim($new_name) === '') {
                return ['error' => 'Le nouveau nom ne peut pas être vide'];
            }
            
            $new_name = trim($new_name);
            
            // Vérifier que le nouveau nom n'existe pas déjà
            $query = $db->prepare('SELECT COUNT(*) as count FROM duplicopieurs WHERE (marque = ? OR modele = ?) AND (marque != ? OR modele != ?)');
            $query->execute([$new_name, $new_name, $old_name, $old_name]);
            $result = $query->fetch(PDO::FETCH_OBJ);
            
            if ($result && $result->count > 0) {
                return ['error' => 'Une machine avec ce nom existe déjà'];
            }
            
            // Démarrer une transaction
            $db->beginTransaction();
            
            try {
                // 1. Mettre à jour la table duplicopieurs
                $query = $db->prepare('UPDATE duplicopieurs SET marque = ?, modele = ?, updated_at = CURRENT_TIMESTAMP WHERE marque = ? AND modele = ?');
                $query->execute([$new_name, $new_name, $old_name, $old_name]);
                
                // 2. Mettre à jour la table cons
                $query = $db->prepare('UPDATE cons SET machine = ? WHERE machine = ?');
                $query->execute([$new_name, $old_name]);
                
                // 3. Mettre à jour la table aide_machines
                $query = $db->prepare('UPDATE aide_machines SET machine = ?, date_modification = CURRENT_TIMESTAMP WHERE machine = ?');
                $query->execute([$new_name, $old_name]);
                
                // 4. Mettre à jour la table prix (si elle utilise des noms de machines)
                $query = $db->prepare('UPDATE prix SET machine_type = ? WHERE machine_type = ?');
                $query->execute([$new_name, $old_name]);
                
                // 5. Mettre à jour les tables de tirage si elles contiennent des références
                // Table dupli - a la colonne nom_machine
                try {
                    $query = $db->prepare('UPDATE dupli SET nom_machine = ? WHERE nom_machine = ?');
                    $query->execute([$new_name, $old_name]);
                } catch (PDOException $e) {
                    // Ignorer si la colonne n'existe pas
                }
                
                // Table a4 - n'a pas de colonne nom_machine, pas de mise à jour nécessaire
                
                // Table photocop - a la colonne marque
                try {
                    $query = $db->prepare('UPDATE photocop SET marque = ? WHERE marque = ?');
                    $query->execute([$new_name, $old_name]);
                } catch (PDOException $e) {
                    // Ignorer si la colonne n'existe pas
                }
                
                // Valider la transaction
                $db->commit();
                
                return ['success' => "Machine '$old_name' renommée en '$new_name' avec succès"];
                
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            return ['error' => "Erreur lors du renommage : " . $e->getMessage()];
        }
    }
    
    /**
     * Gérer le changement de type de machine
     */
    private function handleTypeChange($db, $machine_id, $old_type, $new_type, $data) {
        try {
            // Sauvegarder les anciens compteurs
            $old_passage_counter = 0;
            // CORRECTION : Recherche insensible à la casse
            $query = $db->prepare('SELECT nb_p FROM cons WHERE LOWER(machine) = LOWER(?) ORDER BY date DESC LIMIT 1');
            $query->execute([$machine_id]);
            $result = $query->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $old_passage_counter = $result['nb_p'] ?? 0;
            }
            
            // Supprimer les anciens prix
            $query = $db->prepare('DELETE FROM prix WHERE machine = ?');
            $query->execute([$machine_id]);
            
            // Supprimer les anciennes entrées cons avec recherche insensible à la casse
            $query = $db->prepare('DELETE FROM cons WHERE LOWER(machine) = LOWER(?)');
            $query->execute([$machine_id]);
            
            // Recréer selon le nouveau type
            if ($new_type === 'dupli' || $new_type === 'a4') {
                $master_counter = isset($data['edit_master_counter']) ? $data['edit_master_counter'] : 0;
                $passage_counter = isset($data['edit_passage_counter']) ? $data['edit_passage_counter'] : $old_passage_counter;
                $this->insertDuplicopieur($db, $new_type, $machine_id, $master_counter, $passage_counter);
            } else {
                $passage_counter = $old_passage_counter;
                if ($new_type === 'photocop_encre') {
                    $passage_counter = isset($data['edit_passage_counter_encre']) ? $data['edit_passage_counter_encre'] : $old_passage_counter;
                } else {
                    $passage_counter = isset($data['edit_passage_counter_toner']) ? $data['edit_passage_counter_toner'] : $old_passage_counter;
                }
                $this->insertPhotocopieur($db, $new_type, $machine_id, $passage_counter, $data);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors du changement de type: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Créer une aide par défaut pour une nouvelle machine
     */
    private function createDefaultAide($db, $machine_name) {
        try {
            // Vérifier si une aide existe déjà pour cette machine
            $query = $db->prepare('SELECT COUNT(*) FROM aide_machines WHERE machine = ?');
            $query->execute([$machine_name]);
            
            if ($query->fetchColumn() == 0) {
                // Créer une aide par défaut
                $default_content = 
                    '<div class="alert alert-info">' .
                    '  <p style="text-align: center;">Instructions pour ' . htmlspecialchars($machine_name) . '</p>' .
                    '  <p style="text-align: center;">Pour connaître le nombre à entrer, aller sur la machine :</p>' .
                    '  <p style="text-align: center;">Appuyer sur F1.</p>' .
                    '  <p style="text-align: center;">Et imprimer la liste, notez sur la feuille quelle cartouche vous avez changé.</p>' .
                    '  <p style="text-align: center;">Si c\'est une cartouche de couleur, entrez le chiffre total full color sinon total monochrome</p>' .
                    '  <p style="text-align: center;">Pour les tambours et unités de développement, entrez le nombre total de copies depuis le dernier changement</p>' .
                    '</div>' .
                    '<div style="text-align: center;">' .
                    '  <img src="img/compteur.png" style="width: 80%;">' .
                    '</div>';
                
                $query = $db->prepare('INSERT INTO aide_machines (machine, contenu_aide) VALUES (?, ?)');
                $query->execute([$machine_name, $default_content]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la création de l'aide par défaut: " . $e->getMessage());
            return false;
        }
    }
}
