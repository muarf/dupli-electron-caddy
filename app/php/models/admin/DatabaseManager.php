<?php
/**
 * Module de gestion des bases de données
 * Gère la création, suppression, changement et renommage des bases de données
 */

class AdminDatabaseManager {
    private $conf;
    private $known_databases;
    
    public function __construct($conf) {
        $this->conf = $conf;
        $this->known_databases = array('duplinew', 'duplinew_dev', 'duplinew_test', 'duplinew_staging', 'dupli_montreuil', 'fond_de_la_casse');
    }
    
    /**
     * Créer une nouvelle base de données
     */
    public function createDatabase($db_name, $db_type, $db_template) {
        $result = array();
        
        // Ajouter automatiquement "d_" si le nom ne commence pas par "d"
        if(strpos($db_name, 'd') !== 0) {
            $db_name = 'd_' . $db_name;
        }
        
        // Validation du nom
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $db_name)) {
            $result['error'] = "Le nom de la base doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores.";
            return $result;
        }
        
        try {
            // Connexion à MySQL (sans spécifier de base)
            $mysql_dsn = preg_replace('/dbname=[^;]+;?/', '', $this->conf['dsn']);
            $db = new PDO($mysql_dsn, $this->conf['login'], $this->conf['pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Créer la base de données
            $db->exec("CREATE DATABASE `$db_name`");
            $result['success'] = "Base de données '$db_name' créée avec succès.";
            
            // Ajouter à la liste des bases connues
            if(!in_array($db_name, $this->known_databases)) {
                $this->known_databases[] = $db_name;
            }
            
            // Créer les tables essentielles même pour une base vide
            $result['success'] .= $this->createEssentialTables($db, $db_name);
            
            // Si un template est spécifié, copier la structure complète
            if(!empty($db_template)) {
                $result['success'] .= $this->applyTemplate($db, $db_name, $db_template);
            }
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors de la création de la base : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Appliquer un template à une base de données
     */
    private function applyTemplate($db, $db_name, $template) {
        if($template === 'structure_complete') {
            return $this->createCompleteStructure($db, $db_name);
        } elseif($template === 'duplinew') {
            return $this->copyFromDatabase($db, $db_name, 'duplinew');
        } elseif($template === 'duplinew_dev') {
            return $this->copyFromDatabase($db, $db_name, 'duplinew_dev');
        }
        return "";
    }
    
    /**
     * Copier la structure et les données depuis une base existante
     */
    private function copyFromDatabase($db, $target_db_name, $source_db_name) {
        try {
            // Vérifier que la base source existe
            $db->exec("USE `$source_db_name`");
            
            // Créer d'abord la structure
            $this->createEssentialTables($db, $target_db_name);
            
            // Copier les données de chaque table
            $tables_to_copy = array(
                'duplicopieurs', 'photocopieurs', 'prix', 'papier', 
                'site_settings', 'aide_machines', 'news', 'email', 'config'
            );
            
            $copied_tables = 0;
            foreach ($tables_to_copy as $table_name) {
                try {
                    // Vérifier si la table existe dans la source
                    $stmt = $db->query("SHOW TABLES LIKE '$table_name'");
                    if ($stmt->rowCount() > 0) {
                        // Copier les données
                        $db->exec("INSERT INTO `$target_db_name`.`$table_name` SELECT * FROM `$source_db_name`.`$table_name`");
                        $copied_tables++;
                    }
                } catch(PDOException $e) {
                    // Ignorer les erreurs de copie pour certaines tables
                    continue;
                }
            }
            
            return " Structure et données copiées depuis '$source_db_name' ($copied_tables tables copiées).";
            
        } catch(PDOException $e) {
            return " Erreur lors de la copie depuis '$source_db_name': " . $e->getMessage();
        }
    }
    
    /**
     * Créer la structure complète sans données
     */
    private function createCompleteStructure($db, $db_name) {
        $db->exec("USE `$db_name`");
        
        // Utiliser la même structure que createEssentialTables
        $this->createEssentialTables($db, $db_name);
        
        return " Structure complète créée (tables sans données).";
    }
    
    /**
     * Changer de base de données active
     */
    public function switchDatabase($new_db) {
        $result = array();
        
        // Vérifier que la base de données existe et commence par "d"
        if(strpos($new_db, 'd') === 0) {
            try {
                // Vérifier que la base existe réellement dans MySQL
                $db_check = new PDO("mysql:host=127.0.0.1", $this->conf['login'], $this->conf['pass']);
                $db_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db_check->exec("USE `{$new_db}`");
                
                // Mettre à jour la base de données active dans la table
                $pdo = new PDO("mysql:host=127.0.0.1;dbname=duplinew", $this->conf['login'], $this->conf['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Insérer une nouvelle entrée avec la nouvelle base
                $stmt = $pdo->prepare("INSERT INTO active_database (database_name) VALUES (?)");
                $stmt->execute([$new_db]);
                
                $result['success'] = "Basculement vers '$new_db' effectué avec succès. La page va se recharger automatiquement.";
                $result['current_db'] = $new_db;
                
            } catch (PDOException $e) {
                $result['error'] = "Erreur lors du changement de base : " . $e->getMessage();
            }
        } else {
            $result['error'] = "Base de données '$new_db' non trouvée dans la liste des bases disponibles.";
        }
        
        return $result;
    }
    
    /**
     * Supprimer une base de données
     */
    public function deleteDatabase($db_to_delete) {
        $result = array();
        
        if($db_to_delete === 'duplinew') {
            $result['error'] = "Impossible de supprimer la base de données de production.";
            return $result;
        }
        
        try {
            $mysql_dsn = preg_replace('/dbname=[^;]+;?/', '', $this->conf['dsn']);
            $db = new PDO($mysql_dsn, $this->conf['login'], $this->conf['pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $db->exec("DROP DATABASE `$db_to_delete`");
            $result['success'] = "Base de données '$db_to_delete' supprimée avec succès.";
            
            // Mettre à jour la liste des bases connues
            $this->known_databases = array_filter($this->known_databases, function($db) use ($db_to_delete) {
                return $db !== $db_to_delete;
            });
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Renommer une base de données
     */
    public function renameDatabase($old_db_name, $new_db_name) {
        $result = array();
        
        // Validation du nouveau nom
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $new_db_name)) {
            $result['error'] = "Le nouveau nom doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores.";
            return $result;
        }
        
        if($old_db_name === $new_db_name) {
            $result['error'] = "Le nouveau nom doit être différent de l'ancien.";
            return $result;
        }
        
        try {
            // Connexion à MySQL (sans spécifier de base)
            $mysql_dsn = preg_replace('/dbname=[^;]+;?/', '', $this->conf['dsn']);
            $db = new PDO($mysql_dsn, $this->conf['login'], $this->conf['pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Méthode simplifiée : créer la nouvelle base avec la structure uniquement
            $db->exec("CREATE DATABASE `$new_db_name`");
            
            // Copier la structure des tables (sans les données)
            $db->exec("USE `$old_db_name`");
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $create_stmt = $db->query("SHOW CREATE TABLE `$table`");
                $create_result = $create_stmt->fetch(PDO::FETCH_ASSOC);
                $create_sql = $create_result['Create Table'];
                
                // Modifier le SQL pour la nouvelle base
                $create_sql = str_replace("CREATE TABLE `$table`", "CREATE TABLE `$new_db_name`.`$table`", $create_sql);
                $db->exec($create_sql);
            }
            
            // Supprimer l'ancienne base
            $db->exec("DROP DATABASE `$old_db_name`");
            
            $result['success'] = "Base de données '$old_db_name' renommée en '$new_db_name' avec succès. Seule la structure a été copiée, les données doivent être restaurées depuis une sauvegarde.";
            
            // Mettre à jour la liste des bases connues
            $this->known_databases = array_map(function($db) use ($old_db_name, $new_db_name) {
                return $db === $old_db_name ? $new_db_name : $db;
            }, $this->known_databases);
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors du renommage : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Obtenir la liste des bases de données disponibles
     */
    public function getDatabasesList() {
        $databases = array();
        
        // Récupérer toutes les bases de données depuis MySQL
        try {
            $db_check = new PDO("mysql:host=127.0.0.1", $this->conf['login'], $this->conf['pass']);
            $db_check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $db_check->query("SHOW DATABASES");
            $all_databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filtrer les bases pertinentes (exclure les bases système et ne garder que celles commençant par "d")
            $system_dbs = array('information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin');
            $relevant_dbs = array_filter($all_databases, function($db_name) use ($system_dbs) {
                return !in_array($db_name, $system_dbs) && strpos($db_name, 'd') === 0;
            });
            
            foreach($relevant_dbs as $db_name) {
                try {
                    // Vérifier que la base est accessible
                    $db_check->exec("USE `{$db_name}`");
                    
                    $type = 'dev';
                    if($db_name == 'duplinew') {
                        $type = 'production';
                    } elseif(strpos($db_name, '_dev') !== false) {
                        $type = 'dev';
                    } elseif(strpos($db_name, '_test') !== false) {
                        $type = 'test';
                    } elseif(strpos($db_name, '_staging') !== false) {
                        $type = 'staging';
                    } elseif(strpos($db_name, 'dupli_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'fond_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'd_le_fond_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'd_') === 0) {
                        $type = 'production';
                    }
                    
                    $databases[] = array(
                        'name' => $db_name,
                        'type' => $type
                    );
                } catch(PDOException $e) {
                    // La base n'est pas accessible, l'ignorer
                    continue;
                }
            }
            
        } catch(PDOException $e) {
            // En cas d'erreur, utiliser la méthode de fallback avec known_databases
            foreach($this->known_databases as $db_name) {
                try {
                    $db_check = new PDO("mysql:host=127.0.0.1", $this->conf['login'], $this->conf['pass']);
                    $db_check->exec("USE `{$db_name}`");
                    
                    $type = 'dev';
                    if($db_name == 'duplinew') {
                        $type = 'production';
                    } elseif(strpos($db_name, '_dev') !== false) {
                        $type = 'dev';
                    } elseif(strpos($db_name, '_test') !== false) {
                        $type = 'test';
                    } elseif(strpos($db_name, '_staging') !== false) {
                        $type = 'staging';
                    } elseif(strpos($db_name, 'dupli_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'fond_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'd_le_fond_') === 0) {
                        $type = 'production';
                    } elseif(strpos($db_name, 'd_') === 0) {
                        $type = 'production';
                    }
                    
                    $databases[] = array(
                        'name' => $db_name,
                        'type' => $type
                    );
                } catch(PDOException $e) {
                    continue;
                }
            }
        }
        
        return $databases;
    }
    
    /**
     * Créer les tables essentielles pour une nouvelle base de données
     */
    private function createEssentialTables($db, $db_name) {
        $db->exec("USE `$db_name`");
        
        // Tables essentielles (structure exacte de duplinew)
        $tables = array(
            'active_database' => "CREATE TABLE IF NOT EXISTS `active_database` (
                `id` int NOT NULL AUTO_INCREMENT,
                `database_name` varchar(255) NOT NULL DEFAULT 'duplinew',
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'admin_passwords' => "CREATE TABLE IF NOT EXISTS `admin_passwords` (
                `id` int NOT NULL AUTO_INCREMENT,
                `password_hash` varchar(255) NOT NULL,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `is_active` tinyint(1) DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'dupli' => "CREATE TABLE IF NOT EXISTS `dupli` (
                `id` int NOT NULL AUTO_INCREMENT,
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
                `nom_machine` varchar(255) DEFAULT 'Duplicopieur',
                `duplicopieur_id` int DEFAULT '1',
                `tambour` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'a4' => "CREATE TABLE IF NOT EXISTS `a4` (
                `id` int NOT NULL AUTO_INCREMENT,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'changement' => "CREATE TABLE IF NOT EXISTS `changement` (
                `id` int NOT NULL AUTO_INCREMENT,
                `date` varchar(255) NOT NULL,
                `dupli` varchar(255) NOT NULL,
                `changement` varchar(255) NOT NULL,
                `nombre` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'compta' => "CREATE TABLE IF NOT EXISTS `compta` (
                `id` int NOT NULL AUTO_INCREMENT,
                `date` varchar(255) NOT NULL,
                `contact` varchar(255) NOT NULL,
                `mavant` varchar(255) NOT NULL,
                `mapres` varchar(255) NOT NULL,
                `pavant` varchar(255) NOT NULL,
                `papres` varchar(255) NOT NULL,
                `nbfeuilles` varchar(255) NOT NULL,
                `paye` varchar(255) NOT NULL,
                `prix` varchar(255) NOT NULL,
                `prixpaye` varchar(255) NOT NULL,
                `commentaires` text NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'comptaa3' => "CREATE TABLE IF NOT EXISTS `comptaa3` (
                `id` int NOT NULL AUTO_INCREMENT,
                `date` varchar(255) NOT NULL,
                `contact` varchar(255) NOT NULL,
                `mavant` varchar(255) NOT NULL,
                `mapres` varchar(255) NOT NULL,
                `pavant` varchar(255) NOT NULL,
                `papres` varchar(255) NOT NULL,
                `nbfeuilles` varchar(255) NOT NULL,
                `paye` varchar(255) NOT NULL,
                `prix` varchar(255) NOT NULL,
                `prixpaye` varchar(255) NOT NULL,
                `commentaires` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'comptap' => "CREATE TABLE IF NOT EXISTS `comptap` (
                `id` int NOT NULL AUTO_INCREMENT,
                `heure` varchar(255) NOT NULL,
                `qui` varchar(255) NOT NULL,
                `nbf` varchar(255) NOT NULL,
                `a3` varchar(255) NOT NULL,
                `rv` varchar(255) NOT NULL,
                `couleur` text NOT NULL,
                `prix` varchar(255) NOT NULL,
                `paye` varchar(255) NOT NULL,
                `prixpaye` text NOT NULL,
                `commentaire` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `id` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'config' => "CREATE TABLE IF NOT EXISTS `config` (
                `id` int NOT NULL AUTO_INCREMENT,
                `prix_m_A4` varchar(11) NOT NULL,
                `prix_m_A3` varchar(11) NOT NULL,
                `prix_f_A4` varchar(11) NOT NULL,
                `prix_f_A3` varchar(11) NOT NULL,
                `prix_p_A4` varchar(11) NOT NULL,
                `prix_p_A3` varchar(11) NOT NULL,
                `prix_p_pA4` varchar(255) NOT NULL,
                `prix_p_pA3` varchar(255) NOT NULL,
                `prix_r_m_A3` int NOT NULL,
                `prix_r_m_A4` int NOT NULL,
                `prix_r_e_A3` int NOT NULL,
                `prix_r_e_A4` int NOT NULL,
                `prix_p_pr` int NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'cons' => "CREATE TABLE IF NOT EXISTS `cons` (
                `id` int NOT NULL AUTO_INCREMENT,
                `date` varchar(255) NOT NULL,
                `machine` varchar(255) NOT NULL,
                `type` varchar(255) NOT NULL,
                `nb_p` int NOT NULL,
                `nb_m` int NOT NULL,
                `tambour` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'duplicopieurs' => "CREATE TABLE IF NOT EXISTS `duplicopieurs` (
                `id` int NOT NULL AUTO_INCREMENT,
                `marque` varchar(255) NOT NULL,
                `modele` varchar(255) NOT NULL,
                `supporte_a3` tinyint(1) DEFAULT '1',
                `supporte_a4` tinyint(1) DEFAULT '1',
                `actif` tinyint(1) DEFAULT '1',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `tambours` text,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'email' => "CREATE TABLE IF NOT EXISTS `email` (
                `id` int NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'news' => "CREATE TABLE IF NOT EXISTS `news` (
                `id` int NOT NULL AUTO_INCREMENT,
                `time` varchar(255) NOT NULL,
                `titre` varchar(255) NOT NULL,
                `news` varchar(25555) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'papier' => "CREATE TABLE IF NOT EXISTS `papier` (
                `id` int NOT NULL AUTO_INCREMENT,
                `prix` float NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id` (`id`),
                KEY `id_2` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'photocop' => "CREATE TABLE IF NOT EXISTS `photocop` (
                `id` int NOT NULL AUTO_INCREMENT,
                `type` varchar(255) NOT NULL,
                `marque` varchar(255) DEFAULT NULL,
                `contact` varchar(255) NOT NULL,
                `nb_f` varchar(255) NOT NULL,
                `rv` varchar(255) NOT NULL,
                `paye` varchar(255) NOT NULL,
                `prix` varchar(255) NOT NULL,
                `cb` varchar(255) NOT NULL,
                `mot` varchar(255) NOT NULL,
                `date` varchar(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1",
            
            'photocopieurs' => "CREATE TABLE IF NOT EXISTS `photocopieurs` (
                `id` int NOT NULL AUTO_INCREMENT,
                `marque` varchar(100) NOT NULL,
                `modele` varchar(100) NOT NULL,
                `type_encre` enum('encre','toner') NOT NULL,
                `actif` tinyint(1) DEFAULT '1',
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `marque_modele` (`marque`,`modele`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'prix' => "CREATE TABLE IF NOT EXISTS `prix` (
                `id` int NOT NULL AUTO_INCREMENT,
                `machine_type` varchar(50) NOT NULL,
                `machine_id` int NOT NULL,
                `type` varchar(255) NOT NULL,
                `unite` float NOT NULL,
                `pack` int NOT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `machine_type_id_type` (`machine_type`,`machine_id`,`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'prix_backup' => "CREATE TABLE IF NOT EXISTS `prix_backup` (
                `id` int NOT NULL DEFAULT '0',
                `machine` varchar(255) CHARACTER SET latin1 NOT NULL,
                `type` varchar(255) CHARACTER SET latin1 NOT NULL,
                `unite` float NOT NULL,
                `pack` int NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'site_settings' => "CREATE TABLE IF NOT EXISTS `site_settings` (
                `id` int NOT NULL AUTO_INCREMENT,
                `setting_name` varchar(255) NOT NULL,
                `setting_value` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `setting_name` (`setting_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci",
            
            'aide_machines' => "CREATE TABLE IF NOT EXISTS `aide_machines` (
                `id` int NOT NULL AUTO_INCREMENT,
                `machine` varchar(100) NOT NULL,
                `contenu_aide` text NOT NULL,
                `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `date_modification` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `machine` (`machine`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        );
        
        foreach ($tables as $table_name => $create_sql) {
            try {
                $db->exec($create_sql);
            } catch(PDOException $e) {
                // Ignorer les erreurs si la table existe déjà
            }
        }
        
        // Insérer les données initiales
        $this->insertInitialAideData($db);
        
        return " Tables essentielles créées.";
    }
    
    /**
     * Obtenir la base de données active actuelle
     */
    public function getCurrentDatabase() {
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;dbname=duplinew", $this->conf['login'], $this->conf['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("SELECT database_name FROM active_database ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['database_name'])) {
                return $result['database_name'];
            }
        } catch (PDOException $e) {
            // En cas d'erreur, utiliser la base par défaut
        }
        
        return 'duplinew';
    }
    
    /**
     * Insérer les données initiales pour l'aide des machines
     */
    public function insertInitialAideData($db) {
        try {
            // Vérifier si l'aide ComColor existe déjà
            $stmt = $db->prepare("SELECT COUNT(*) FROM aide_machines WHERE machine = ?");
            $stmt->execute(['ComColor']);
            $exists = $stmt->fetchColumn();
            
            if (!$exists) {
                $aide_comcolor = '<div class="alert alert-info">
                    <p align="center">Pour connaitre le nombre à entrer, aller sur la machine :</p>
                    <p align="center">Appuyer sur F1.</p>
                    <p align="center">et imprimer la liste, notez sur la feuille quelle cartouche vous avez changé.</p>
                    <p align="center">si c\'est une cartouche de couleur, entrez le chiffre total full color sinon total monochrome</p>
                    <p align="center">Pour les tambours et unités de développement, entrez le nombre total de copies depuis le dernier changement</p>
                </div>
                <div align="center">
                    <img src="img/compteur.png" width="80%">
                </div>';
                
                $stmt = $db->prepare("INSERT INTO aide_machines (machine, contenu_aide) VALUES (?, ?)");
                $stmt->execute(['ComColor', $aide_comcolor]);
                
                return "Données initiales d'aide insérées pour ComColor.";
            }
            
            return "L'aide ComColor existe déjà.";
        } catch (PDOException $e) {
            return "Erreur lors de l'insertion des données d'aide : " . $e->getMessage();
        }
    }
}
?>

