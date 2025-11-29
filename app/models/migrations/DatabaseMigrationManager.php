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
            $migrations = [
                'tirage_global_id' => [$this, 'migrateTirageGlobalId'],
                // Ajouter d'autres migrations ici à l'avenir
            ];
            
            foreach ($migrations as $migrationName => $migrationFunction) {
                if (!$this->isMigrationApplied($migrationName)) {
                    error_log("[MIGRATION] Début migration: $migrationName");
                    $this->applyMigration($migrationName, $migrationFunction);
                    error_log("[MIGRATION] Migration $migrationName terminée");
                } else {
                    error_log("[MIGRATION] Migration $migrationName déjà appliquée, ignorée");
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
            // Récupérer tous les tirages sans tirage_global_id
            $query = $this->db->prepare("SELECT id, date, contact FROM $table WHERE tirage_global_id IS NULL OR tirage_global_id = ''");
            $query->execute();
            $tirages = $query->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("[MIGRATION] Mise à jour de " . count($tirages) . " tirages dans $table");
            
            $updateQuery = $this->db->prepare("UPDATE $table SET tirage_global_id = ? WHERE id = ?");
            
            foreach ($tirages as $tirage) {
                $tirage_global_id = $this->generateTirageGlobalId($tirage['date'], $tirage['contact']);
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
     * Format: YYYY-MM-DD_HH-MM-SS_ContactNormalise
     */
    public static function generateTirageGlobalId($date, $contact) {
        // Normaliser le contact (trim, lowercase, remplacer caractères spéciaux par _)
        $normalizedContact = strtolower(trim($contact));
        $normalizedContact = preg_replace('/[^a-zA-Z0-9]/', '_', $normalizedContact);
        $normalizedContact = preg_replace('/_+/', '_', $normalizedContact); // Remplacer multiples _ par un seul
        $normalizedContact = trim($normalizedContact, '_'); // Enlever les _ en début/fin
        
        // Formater la date
        // $date peut être un timestamp (int) ou une chaîne
        if (is_numeric($date)) {
            $dateFormatted = date('Y-m-d_H-i-s', intval($date));
        } else {
            // Si c'est une chaîne, essayer de la convertir
            $timestamp = is_numeric($date) ? intval($date) : strtotime($date);
            $dateFormatted = date('Y-m-d_H-i-s', $timestamp);
        }
        
        return $dateFormatted . '_' . $normalizedContact;
    }
}
?>
