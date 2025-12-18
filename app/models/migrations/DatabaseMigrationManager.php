<?php
/**
 * Gestionnaire unifié de migrations de base de données
 * Vérifie et applique automatiquement les migrations nécessaires
 */

require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../models/admin/BackupManager.php';

class DatabaseMigrationManager {
    private $conf;
    private $db;
    private $backupManager;
    
    public function __construct($conf) {
        $this->conf = $conf;
        $this->backupManager = new BackupManager($conf);
    }
    
    /**
     * Vérifier et appliquer toutes les migrations nécessaires
     */
    public function runMigrations() {
        try {
            $this->db = pdo_connect();
            
            // Liste des migrations à vérifier/appliquer
            require_once __DIR__ . '/add_multi_session_support.php';
            require_once __DIR__ . '/allow_multiple_active_sessions.php';
            
            $migrations = [
                'tirage_global_id' => [$this, 'migrateTirageGlobalId'],
                'bibliotheque_table' => [$this, 'createBibliothequeTable'],
                'bibliotheque_fts' => [$this, 'createBibliothequeFTS'],
                'multi_session_support' => function() {
                    migrate_add_multi_session_support($this->db);
                },
                'allow_multiple_active_sessions' => function() {
                    migrate_allow_multiple_active_sessions($this->db);
                },
                // Ajouter d'autres migrations ici à l'avenir
            ];
            
            foreach ($migrations as $migrationName => $migrationFunction) {
                try {
                    if (!$this->isMigrationApplied($migrationName)) {
                        error_log("[MIGRATION] Début migration: $migrationName");
                        $this->applyMigration($migrationName, $migrationFunction);
                        error_log("[MIGRATION] Migration $migrationName terminée");
                    } else {
                        error_log("[MIGRATION] Migration $migrationName déjà appliquée, ignorée");
                    }
                } catch (Exception $e) {
                    error_log("[MIGRATION] ERREUR migration $migrationName: " . $e->getMessage());
                    error_log("[MIGRATION] Continuer avec les migrations suivantes...");
                    // Ne pas bloquer les autres migrations
                }
            }
            
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur lors des migrations: " . $e->getMessage());
            // Ne pas bloquer l'application si les migrations échouent
        }
    }
    
    /**
     * Vérifier si une migration a déjà été appliquée
     */
    private function isMigrationApplied($migrationName) {
        try {
            // Vérifier si la table de suivi des migrations existe
            $this->db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration_name TEXT UNIQUE NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $query = $this->db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_name = ?');
            $query->execute([$migrationName]);
            return $query->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur vérification migration $migrationName: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marquer une migration comme appliquée
     */
    private function markMigrationApplied($migrationName) {
        try {
            $query = $this->db->prepare('INSERT OR IGNORE INTO schema_migrations (migration_name) VALUES (?)');
            $query->execute([$migrationName]);
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur marquage migration $migrationName: " . $e->getMessage());
        }
    }
    
    /**
     * Appliquer une migration avec backup automatique
     */
    private function applyMigration($migrationName, $migrationFunction) {
        // Créer un backup complet de la base avant la migration
        $backupResult = $this->createMigrationBackup($migrationName);
        
        if (isset($backupResult['error'])) {
            error_log("[MIGRATION] Erreur backup pour $migrationName: " . $backupResult['error']);
            // On continue quand même, mais c'est risqué
        } else {
            error_log("[MIGRATION] Backup créé: " . ($backupResult['filename'] ?? 'inconnu'));
        }
        
        // Appliquer la migration
        try {
            $this->db->beginTransaction();
            $migrationFunction();
            $this->markMigrationApplied($migrationName);
            $this->db->commit();
            error_log("[MIGRATION] Migration $migrationName appliquée avec succès");
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("[MIGRATION] Erreur lors de la migration $migrationName: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Créer un backup complet de la base SQLite avant migration
     */
    private function createMigrationBackup($migrationName) {
        try {
            $current_db_path = $this->conf['db_path'];
            
            if (!file_exists($current_db_path)) {
                return ['error' => "Fichier de base de données non trouvé : $current_db_path"];
            }
            
            // Utiliser BackupManager pour créer le backup
            $backup_name = 'before_' . $migrationName . '_' . date('Y-m-d_H-i-s');
            return $this->backupManager->createBackup($backup_name);
            
        } catch (Exception $e) {
            return ['error' => "Erreur lors de la création du backup: " . $e->getMessage()];
        }
    }
    
    /**
     * Migration: Créer la table bibliotheque_files
     */
    private function createBibliothequeTable() {
        error_log("[MIGRATION] Création table bibliotheque_files");
        
        $sql = "CREATE TABLE IF NOT EXISTS bibliotheque_files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            filepath TEXT NOT NULL,
            file_type TEXT NOT NULL,
            thumbnail_path TEXT,
            file_size INTEGER,
            page_count INTEGER DEFAULT 0,
            extracted_text TEXT,
            is_external INTEGER DEFAULT 0,
            source_directory TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->exec($sql);
        
        // Créer des index pour la recherche
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bibliotheque_filename ON bibliotheque_files(filename)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bibliotheque_type ON bibliotheque_files(file_type)");
    }

    /**
     * Migration: Créer la table FTS5 pour la recherche full-text
     */
    private function createBibliothequeFTS() {
        error_log("[MIGRATION] Création table FTS5 bibliotheque_files_fts");
        
        // Vérifier que la table bibliotheque_files existe
        $tableExists = false;
        try {
            $checkQuery = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bibliotheque_files'");
            $tableExists = $checkQuery->fetch() !== false;
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur vérification table bibliotheque_files: " . $e->getMessage());
        }
        
        if (!$tableExists) {
            error_log("[MIGRATION] Table bibliotheque_files n'existe pas, création...");
            $this->createBibliothequeTable();
        }
        
        // Supprimer la table FTS5 si elle existe déjà (pour réinitialisation)
        try {
            $this->db->exec("DROP TABLE IF EXISTS bibliotheque_files_fts");
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur suppression ancienne table FTS5: " . $e->getMessage());
        }
        
        // Créer la table virtuelle FTS5
        $this->db->exec("CREATE VIRTUAL TABLE bibliotheque_files_fts USING fts5(
            filename,
            extracted_text,
            content='bibliotheque_files',
            content_rowid='id'
        )");
        
        // Créer le trigger pour INSERT
        $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ai AFTER INSERT ON bibliotheque_files BEGIN
            INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
            VALUES (new.id, new.filename, new.extracted_text);
        END");
        
        // Créer le trigger pour DELETE
        $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ad AFTER DELETE ON bibliotheque_files BEGIN
            INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, extracted_text) 
            VALUES('delete', old.id, old.filename, old.extracted_text);
        END");
        
        // Créer le trigger pour UPDATE
        $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_au AFTER UPDATE ON bibliotheque_files BEGIN
            INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, extracted_text) 
            VALUES('delete', old.id, old.filename, old.extracted_text);
            INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
            VALUES (new.id, new.filename, new.extracted_text);
        END");
        
        // Remplir la table FTS5 avec les données existantes
        try {
            $this->db->exec("INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
                SELECT id, filename, extracted_text FROM bibliotheque_files");
            
            // Compter les enregistrements insérés
            $countQuery = $this->db->query("SELECT COUNT(*) FROM bibliotheque_files_fts");
            $count = $countQuery->fetchColumn();
            error_log("[MIGRATION] Table FTS5 remplie avec " . $count . " enregistrements");
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur remplissage FTS5: " . $e->getMessage());
            // Ne pas bloquer si le remplissage échoue, la table est créée
        }
    }

    /**
     * Migration: Ajouter tirage_global_id aux tables dupli et photocop
     */
    private function migrateTirageGlobalId() {
        error_log("[MIGRATION] Début migration tirage_global_id");
        
        // Vérifier si la colonne existe déjà dans dupli
        $dupliHasColumn = $this->columnExists('dupli', 'tirage_global_id');
        $photocopHasColumn = $this->columnExists('photocop', 'tirage_global_id');
        
        // Ajouter la colonne si elle n'existe pas
        if (!$dupliHasColumn) {
            error_log("[MIGRATION] Ajout colonne tirage_global_id à dupli");
            $this->db->exec('ALTER TABLE dupli ADD COLUMN tirage_global_id TEXT');
        }
        
        if (!$photocopHasColumn) {
            error_log("[MIGRATION] Ajout colonne tirage_global_id à photocop");
            $this->db->exec('ALTER TABLE photocop ADD COLUMN tirage_global_id TEXT');
        }
        
        // Mettre à jour les données existantes
        $this->updateExistingTirageGlobalIds();
    }
    
    /**
     * Vérifier si une colonne existe dans une table
     */
    private function columnExists($table, $column) {
        try {
            if (isset($this->conf['db_type']) && $this->conf['db_type'] === 'sqlite') {
                $query = $this->db->prepare("PRAGMA table_info($table)");
                $query->execute();
                $columns = $query->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($columns as $col) {
                    if ($col['name'] === $column) {
                        return true;
                    }
                }
                return false;
            } else {
                // MySQL
                $query = $this->db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                $query->execute([$column]);
                return $query->rowCount() > 0;
            }
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur vérification colonne $table.$column: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mettre à jour les tirage_global_id existants
     */
    private function updateExistingTirageGlobalIds() {
        // Mettre à jour dupli
        $this->updateTableTirageGlobalIds('dupli');
        
        // Mettre à jour photocop
        $this->updateTableTirageGlobalIds('photocop');
    }
    
    /**
     * Mettre à jour les tirage_global_id pour une table
     */
    private function updateTableTirageGlobalIds($table) {
        try {
            // Récupérer tous les tirages (y compris ceux qui ont déjà un tirage_global_id pour les régénérer avec la machine)
            // Pour dupli, récupérer nom_machine, pour photocop récupérer marque
            if ($table === 'dupli') {
                $query = $this->db->prepare("SELECT id, date, contact, nom_machine FROM $table");
            } else {
                // photocop
                $query = $this->db->prepare("SELECT id, date, contact, marque FROM $table");
            }
            $query->execute();
            $tirages = $query->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("[MIGRATION] Mise à jour de " . count($tirages) . " tirages dans $table");
            
            $updateQuery = $this->db->prepare("UPDATE $table SET tirage_global_id = ? WHERE id = ?");
            
            foreach ($tirages as $tirage) {
                // Récupérer la machine selon la table
                $machine = null;
                if ($table === 'dupli') {
                    $machine = isset($tirage['nom_machine']) ? $tirage['nom_machine'] : null;
                } else {
                    // photocop
                    $machine = isset($tirage['marque']) ? $tirage['marque'] : null;
                }
                
                $tirage_global_id = $this->generateTirageGlobalId($tirage['date'], $tirage['contact'], $machine);
                $updateQuery->execute([$tirage_global_id, $tirage['id']]);
            }
            
            error_log("[MIGRATION] Mise à jour terminée pour $table");
        } catch (Exception $e) {
            error_log("[MIGRATION] Erreur mise à jour $table: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Générer un identifiant lisible pour un tirage global
     * Format: YYYY-MM-DD_HH-MM-SS_ContactNormalise_MachineNormalisee
     */
    public static function generateTirageGlobalId($date, $contact, $machine = null) {
        // Normaliser le contact (trim, lowercase, remplacer caractères spéciaux par _)
        $normalizedContact = strtolower(trim($contact));
        $normalizedContact = preg_replace('/[^a-zA-Z0-9]/', '_', $normalizedContact);
        $normalizedContact = preg_replace('/_+/', '_', $normalizedContact); // Remplacer multiples _ par un seul
        $normalizedContact = trim($normalizedContact, '_'); // Enlever les _ en début/fin
        
        // Normaliser la machine si fournie
        $normalizedMachine = '';
        if ($machine !== null && $machine !== '') {
            $normalizedMachine = strtolower(trim($machine));
            $normalizedMachine = preg_replace('/[^a-zA-Z0-9]/', '_', $normalizedMachine);
            $normalizedMachine = preg_replace('/_+/', '_', $normalizedMachine); // Remplacer multiples _ par un seul
            $normalizedMachine = trim($normalizedMachine, '_'); // Enlever les _ en début/fin
        }
        
        // Formater la date
        // $date peut être un timestamp (int) ou une chaîne
        if (is_numeric($date)) {
            $dateFormatted = date('Y-m-d_H-i-s', intval($date));
        } else {
            // Si c'est une chaîne, essayer de la convertir
            $timestamp = is_numeric($date) ? intval($date) : strtotime($date);
            $dateFormatted = date('Y-m-d_H-i-s', $timestamp);
        }
        
        $result = $dateFormatted . '_' . $normalizedContact;
        if ($normalizedMachine !== '') {
            $result .= '_' . $normalizedMachine;
        }
        
        return $result;
    }
}
?>
