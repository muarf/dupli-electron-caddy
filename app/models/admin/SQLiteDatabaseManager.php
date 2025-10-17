<?php
/**
 * Module de gestion des bases de données SQLite
 * Gère la création, suppression, changement et renommage des fichiers SQLite
 */

class SQLiteDatabaseManager {
    private $conf;
    private $databases_dir;
    private $current_db_file;
    
    public function __construct($conf) {
        $this->conf = $conf;
        $this->databases_dir = dirname($conf['db_path']);
        $this->current_db_file = basename($conf['db_path']);
    }
    
    /**
     * Créer une nouvelle base de données SQLite
     */
    public function createDatabase($db_name, $db_type, $db_template) {
        $result = array();
        
        // Validation du nom (pas de préfixe automatique pour SQLite)
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $db_name)) {
            $result['error'] = "Le nom de la base doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores.";
            return $result;
        }
        
        // Ajouter l'extension .sqlite si pas présente
        if(substr($db_name, -7) !== '.sqlite') {
            $db_name .= '.sqlite';
        }
        
        $new_db_path = $this->databases_dir . '/' . $db_name;
        
        // Vérifier si le fichier existe déjà
        if(file_exists($new_db_path)) {
            $result['error'] = "Un fichier de base de données avec ce nom existe déjà.";
            return $result;
        }
        
        try {
            // Créer le fichier SQLite vide
            $db = new PDO('sqlite:' . $new_db_path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Créer les tables essentielles
            $this->createEssentialTables($db);
            
            $result['success'] = "Base de données SQLite '$db_name' créée avec succès.";
            
            // Si un template est spécifié, copier les données
            if(!empty($db_template)) {
                $result['success'] .= $this->applyTemplate($db, $new_db_path, $db_template);
            }
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors de la création de la base : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Appliquer un template à une base de données SQLite
     */
    private function applyTemplate($db, $new_db_path, $template) {
        if($template === 'structure_complete') {
            return " Structure complète créée (tables sans données).";
        } elseif($template === 'duplinew') {
            return $this->copyFromDatabase($new_db_path, $this->conf['db_path']);
        } elseif($template === 'duplinew_dev') {
            // Chercher un fichier duplinew_dev.sqlite
            $dev_path = $this->databases_dir . '/duplinew_dev.sqlite';
            if(file_exists($dev_path)) {
                return $this->copyFromDatabase($new_db_path, $dev_path);
            } else {
                return " Fichier duplinew_dev.sqlite non trouvé.";
            }
        }
        return "";
    }
    
    /**
     * Copier la structure et les données depuis une base SQLite existante
     */
    private function copyFromDatabase($target_path, $source_path) {
        try {
            if(!file_exists($source_path)) {
                return " Fichier source non trouvé.";
            }
            
            // Copier le fichier SQLite complet
            if(copy($source_path, $target_path)) {
                return " Données copiées depuis le fichier source.";
            } else {
                return " Erreur lors de la copie du fichier.";
            }
            
        } catch(Exception $e) {
            return " Erreur lors de la copie : " . $e->getMessage();
        }
    }
    
    /**
     * Changer de base de données active
     */
    public function switchDatabase($new_db) {
        $result = array();
        
        // Ajouter l'extension .sqlite si pas présente
        if(substr($new_db, -7) !== '.sqlite') {
            $new_db .= '.sqlite';
        }
        
        $new_db_path = $this->databases_dir . '/' . $new_db;
        
        // Vérifier que le fichier existe
        if(!file_exists($new_db_path)) {
            $result['error'] = "Fichier de base de données '$new_db' non trouvé.";
            return $result;
        }
        
        try {
            // Tester la connexion à la nouvelle base
            $db = new PDO('sqlite:' . $new_db_path);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Mettre à jour le fichier de configuration
            $this->updateConfigFile($new_db_path);
            
            $result['success'] = "Basculement vers '$new_db' effectué avec succès. La page va se recharger automatiquement.";
            $result['current_db'] = $new_db;
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors du changement de base : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Mettre à jour le fichier de configuration avec le nouveau chemin
     */
    private function updateConfigFile($new_db_path) {
        $conf_file = dirname(dirname(__DIR__)) . '/controler/conf.php';
        
        echo "DEBUG: Chemin conf.php: $conf_file\n";
        echo "DEBUG: Fichier existe: " . (file_exists($conf_file) ? 'OUI' : 'NON') . "\n";
        
        if(file_exists($conf_file)) {
            $content = file_get_contents($conf_file);
            echo "DEBUG: Contenu lu: " . strlen($content) . " caractères\n";
            
            $new_content = preg_replace(
                '/\$sqlite_db_path = .*?;/',
                '$sqlite_db_path = \'' . $new_db_path . '\';',
                $content
            );
            
            echo "DEBUG: Contenu modifié: " . ($content !== $new_content ? 'OUI' : 'NON') . "\n";
            
            $result = file_put_contents($conf_file, $new_content);
            echo "DEBUG: Écriture: " . ($result !== false ? 'SUCCÈS' : 'ÉCHEC') . "\n";
        }
    }
    
    /**
     * Supprimer une base de données SQLite
     */
    public function deleteDatabase($db_to_delete) {
        $result = array();
        
        // Ajouter l'extension .sqlite si pas présente
        if(substr($db_to_delete, -7) !== '.sqlite') {
            $db_to_delete .= '.sqlite';
        }
        
        $db_path = $this->databases_dir . '/' . $db_to_delete;
        
        // Empêcher la suppression de la base actuelle
        if($db_path === $this->conf['db_path']) {
            $result['error'] = "Impossible de supprimer la base de données actuellement active.";
            return $result;
        }
        
        if(!file_exists($db_path)) {
            $result['error'] = "Fichier de base de données '$db_to_delete' non trouvé.";
            return $result;
        }
        
        try {
            if(unlink($db_path)) {
                $result['success'] = "Base de données '$db_to_delete' supprimée avec succès.";
            } else {
                $result['error'] = "Erreur lors de la suppression du fichier.";
            }
            
        } catch(Exception $e) {
            $result['error'] = "Erreur lors de la suppression : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Renommer une base de données SQLite
     */
    public function renameDatabase($old_db_name, $new_db_name) {
        $result = array();
        
        // Validation du nouveau nom
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $new_db_name)) {
            $result['error'] = "Le nouveau nom doit commencer par une lettre et ne contenir que des lettres, chiffres et underscores.";
            return $result;
        }
        
        // Ajouter l'extension .sqlite si pas présente
        if(substr($old_db_name, -7) !== '.sqlite') {
            $old_db_name .= '.sqlite';
        }
        if(substr($new_db_name, -7) !== '.sqlite') {
            $new_db_name .= '.sqlite';
        }
        
        if($old_db_name === $new_db_name) {
            $result['error'] = "Le nouveau nom doit être différent de l'ancien.";
            return $result;
        }
        
        $old_path = $this->databases_dir . '/' . $old_db_name;
        $new_path = $this->databases_dir . '/' . $new_db_name;
        
        if(!file_exists($old_path)) {
            $result['error'] = "Fichier de base de données '$old_db_name' non trouvé.";
            return $result;
        }
        
        if(file_exists($new_path)) {
            $result['error'] = "Un fichier avec le nom '$new_db_name' existe déjà.";
            return $result;
        }
        
        try {
            if(rename($old_path, $new_path)) {
                $result['success'] = "Base de données '$old_db_name' renommée en '$new_db_name' avec succès.";
                
                // Si c'était la base active, mettre à jour la configuration
                if($old_path === $this->conf['db_path']) {
                    $this->updateConfigFile($new_path);
                }
            } else {
                $result['error'] = "Erreur lors du renommage du fichier.";
            }
            
        } catch(Exception $e) {
            $result['error'] = "Erreur lors du renommage : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Obtenir la liste des bases de données SQLite disponibles
     */
    public function getDatabasesList() {
        $databases = array();
        
        try {
            // Lister tous les fichiers .sqlite dans le répertoire
            $files = glob($this->databases_dir . '/*.sqlite');
            
            foreach($files as $file) {
                $filename = basename($file);
                $db_name = str_replace('.sqlite', '', $filename);
                
                // Déterminer le type
                $type = 'dev';
                if($filename === 'duplinew.sqlite') {
                    $type = 'production';
                } elseif(strpos($filename, '_dev') !== false) {
                    $type = 'dev';
                } elseif(strpos($filename, '_test') !== false) {
                    $type = 'test';
                } elseif(strpos($filename, '_staging') !== false) {
                    $type = 'staging';
                } elseif(strpos($filename, 'dupli_') === 0) {
                    $type = 'production';
                } elseif(strpos($filename, 'fond_') === 0) {
                    $type = 'production';
                }
                
                $databases[] = array(
                    'name' => $db_name,
                    'type' => $type,
                    'file' => $filename,
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                );
            }
            
        } catch(Exception $e) {
            // En cas d'erreur, retourner une liste vide
        }
        
        return $databases;
    }
    
    /**
     * Obtenir la base de données active actuelle
     */
    public function getCurrentDatabase() {
        return str_replace('.sqlite', '', $this->current_db_file);
    }
    
    /**
     * Vider la base de données (supprimer toutes les données mais garder la structure)
     */
    public function emptyDatabase() {
        $result = array();
        
        try {
            $db = new PDO('sqlite:' . $this->conf['db_path']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Obtenir la liste des tables
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Supprimer toutes les données de chaque table
            foreach ($tables as $table) {
                $db->exec("DELETE FROM `$table`");
            }
            
            $result['success'] = "Base de données vidée avec succès. Toutes les données ont été supprimées.";
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors du vidage de la base : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Réinitialiser complètement la base de données (supprimer tout et recréer la structure)
     */
    public function resetDatabase() {
        $result = array();
        
        try {
            $db = new PDO('sqlite:' . $this->conf['db_path']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Obtenir la liste des tables
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Supprimer toutes les tables
            foreach ($tables as $table) {
                $db->exec("DROP TABLE IF EXISTS `$table`");
            }
            
            // Recréer la structure
            $this->createEssentialTables($db);
            
            $result['success'] = "Base de données réinitialisée avec succès. Structure recréée.";
            
        } catch(PDOException $e) {
            $result['error'] = "Erreur lors de la réinitialisation : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Créer les tables essentielles pour une nouvelle base de données SQLite
     */
    public function createEssentialTables($db) {
        // Tables essentielles adaptées pour SQLite
        $tables = array(
            'active_database' => "CREATE TABLE IF NOT EXISTS active_database (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                database_name TEXT NOT NULL DEFAULT 'duplinew',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            'admin_passwords' => "CREATE TABLE IF NOT EXISTS admin_passwords (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_active INTEGER DEFAULT 1
            )",
            
            'duplicopieurs' => "CREATE TABLE IF NOT EXISTS duplicopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL,
                supporte_a3 INTEGER DEFAULT 1,
                supporte_a4 INTEGER DEFAULT 1,
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                tambours TEXT
            )",
            
            'photocopieurs' => "CREATE TABLE IF NOT EXISTS photocopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL,
                type_encre TEXT NOT NULL CHECK(type_encre IN ('encre','toner')),
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(marque, modele)
            )",
            
            'prix' => "CREATE TABLE IF NOT EXISTS prix (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                machine_type TEXT NOT NULL,
                machine_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                unite REAL NOT NULL,
                pack INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            'papier' => "CREATE TABLE IF NOT EXISTS papier (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                prix REAL NOT NULL
            )",
            
            'news' => "CREATE TABLE IF NOT EXISTS news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                time TEXT NOT NULL,
                titre TEXT NOT NULL,
                news TEXT NOT NULL
            )",
            
            'email' => "CREATE TABLE IF NOT EXISTS email (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL
            )",
            
            'cons' => "CREATE TABLE IF NOT EXISTS cons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                machine TEXT NOT NULL,
                type TEXT NOT NULL,
                nb_p INTEGER NOT NULL,
                nb_m INTEGER NOT NULL,
                tambour TEXT DEFAULT NULL
            )",
            
            'site_settings' => "CREATE TABLE IF NOT EXISTS site_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT NOT NULL UNIQUE,
                setting_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            'aide_machines' => "CREATE TABLE IF NOT EXISTS aide_machines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                machine TEXT NOT NULL UNIQUE,
                contenu_aide TEXT NOT NULL,
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            'aide_machines_qa' => "CREATE TABLE IF NOT EXISTS aide_machines_qa (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                machine TEXT NOT NULL,
                question TEXT NOT NULL,
                reponse TEXT NOT NULL,
                ordre INTEGER DEFAULT 0,
                date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                date_modification DATETIME DEFAULT CURRENT_TIMESTAMP,
                categorie TEXT DEFAULT 'general'
            )",
            
            'dupli' => "CREATE TABLE IF NOT EXISTS dupli (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                contact TEXT NOT NULL,
                master_av TEXT NOT NULL,
                master_ap TEXT NOT NULL,
                passage_av TEXT NOT NULL,
                passage_ap TEXT NOT NULL,
                rv TEXT NOT NULL,
                prix TEXT NOT NULL,
                paye TEXT NOT NULL,
                cb TEXT NOT NULL,
                mot TEXT NOT NULL,
                date TEXT NOT NULL,
                nom_machine TEXT DEFAULT 'Duplicopieur',
                duplicopieur_id INTEGER DEFAULT 1,
                tambour TEXT DEFAULT NULL
            )",
            
            'a4' => "CREATE TABLE IF NOT EXISTS a4 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                contact TEXT NOT NULL,
                master_av TEXT NOT NULL,
                master_ap TEXT NOT NULL,
                passage_av TEXT NOT NULL,
                passage_ap TEXT NOT NULL,
                rv TEXT NOT NULL,
                prix TEXT NOT NULL,
                paye TEXT NOT NULL,
                cb TEXT NOT NULL,
                mot TEXT NOT NULL,
                date TEXT NOT NULL
            )",
            
            'photocop' => "CREATE TABLE IF NOT EXISTS photocop (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                marque TEXT DEFAULT NULL,
                contact TEXT NOT NULL,
                nb_f TEXT NOT NULL,
                rv TEXT NOT NULL,
                paye TEXT NOT NULL,
                prix TEXT NOT NULL,
                cb TEXT NOT NULL,
                mot TEXT NOT NULL,
                date TEXT NOT NULL
            )"
        );
        
        foreach ($tables as $table_name => $create_sql) {
            try {
                $db->exec($create_sql);
            } catch(PDOException $e) {
                // Ignorer les erreurs si la table existe déjà
            }
        }
        
        // Insérer les données initiales
        $this->insertInitialData($db);
    }
    
    /**
     * Insérer les données initiales
     */
    public function insertInitialData($db) {
        try {
            // Insérer les prix de papier par défaut
            $stmt = $db->prepare("INSERT OR IGNORE INTO papier (prix) VALUES (?)");
            $stmt->execute([0.05]); // Prix par défaut
            
            // Insérer l'aide ComColor par défaut
            $stmt = $db->prepare("INSERT OR IGNORE INTO aide_machines (machine, contenu_aide) VALUES (?, ?)");
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
            $stmt->execute(['ComColor', $aide_comcolor]);
            
        } catch(PDOException $e) {
            // Ignorer les erreurs d'insertion
        }
    }
}
?>
