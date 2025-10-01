<?php
/**
 * Fonctions de base de données pour l'application Duplicator
 * 
 * Ce fichier contient toutes les fonctions liées à la gestion des bases de données,
 * des connexions PDO et des opérations CRUD de base.
 */

/**
 * Classe principale pour la gestion des bases de données
 */
class DatabaseManager
{
    /**
     * Configuration de la base de données
     * @var array
     */
    private $config;
    
    /**
     * Connexion PDO active
     * @var PDO|null
     */
    private $connection = null;
    
    /**
     * Constructeur
     * 
     * @param array $config Configuration de la base de données
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * Établir une connexion PDO à la base de données
     * 
     * @return PDO Connexion PDO
     * @throws PDOException En cas d'erreur de connexion
     */
    public function connect(): PDO
    {
        if ($this->connection === null) {
            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ];
                
                // Configuration spécifique selon le type de base de données
                if (isset($this->config['db_type']) && $this->config['db_type'] === 'sqlite') {
                    // Configuration SQLite
                    $options[PDO::ATTR_TIMEOUT] = 30;
                } else {
                    // Configuration MySQL (pour compatibilité)
                    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
                }
                
                $this->connection = new PDO(
                    $this->config['dsn'],
                    $this->config['login'],
                    $this->config['pass'],
                    $options
                );
            } catch (PDOException $e) {
                throw new PDOException('Erreur de connexion à la base de données: ' . $e->getMessage());
            }
        }
        
        return $this->connection;
    }
    
    /**
     * Fermer la connexion à la base de données
     */
    public function disconnect(): void
    {
        $this->connection = null;
    }
    
    /**
     * Exécuter une requête SELECT et retourner tous les résultats
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @return array Résultats de la requête
     */
    public function select(string $sql, array $params = []): array
    {
        $db = $this->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Exécuter une requête SELECT et retourner un seul résultat
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @return array|null Résultat unique ou null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $db = $this->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Exécuter une requête INSERT, UPDATE ou DELETE
     * 
     * @param string $sql Requête SQL
     * @param array $params Paramètres de la requête
     * @return int Nombre de lignes affectées
     */
    public function execute(string $sql, array $params = []): int
    {
        $db = $this->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Insérer des données et retourner l'ID de la dernière insertion
     * 
     * @param string $sql Requête SQL INSERT
     * @param array $params Paramètres de la requête
     * @return int ID de la dernière insertion
     */
    public function insert(string $sql, array $params = []): int
    {
        $db = $this->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $db->lastInsertId();
    }
    
    /**
     * Compter le nombre de lignes dans une table
     * 
     * @param string $table Nom de la table
     * @param string $where Clause WHERE optionnelle
     * @param array $params Paramètres de la clause WHERE
     * @return int Nombre de lignes
     */
    public function count(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM `$table`";
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        $db = $this->connect();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Vérifier si une table existe
     * 
     * @param string $table Nom de la table
     * @return bool True si la table existe
     */
    public function tableExists(string $table): bool
    {
        try {
            $db = $this->connect();
            
            if (isset($this->config['db_type']) && $this->config['db_type'] === 'sqlite') {
                // Requête SQLite
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$table]);
                $result = $stmt->fetch();
            } else {
                // Requête MySQL
                $result = $db->query("SHOW TABLES LIKE '$table'")->fetch();
            }
            
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Obtenir la liste des tables de la base de données
     * 
     * @return array Liste des tables
     */
    public function getTables(): array
    {
        $db = $this->connect();
        
        if (isset($this->config['db_type']) && $this->config['db_type'] === 'sqlite') {
            // Requête SQLite
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Requête MySQL
            $stmt = $db->query("SHOW TABLES");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    /**
     * Obtenir la structure d'une table
     * 
     * @param string $table Nom de la table
     * @return array Structure de la table
     */
    public function getTableStructure(string $table): array
    {
        $db = $this->connect();
        
        if (isset($this->config['db_type']) && $this->config['db_type'] === 'sqlite') {
            // Requête SQLite
            $stmt = $db->query("PRAGMA table_info(`$table`)");
            return $stmt->fetchAll();
        } else {
            // Requête MySQL
            $stmt = $db->query("DESCRIBE `$table`");
            return $stmt->fetchAll();
        }
    }
    
    /**
     * Commencer une transaction
     */
    public function beginTransaction(): void
    {
        $this->connect()->beginTransaction();
    }
    
    /**
     * Valider une transaction
     */
    public function commit(): void
    {
        $this->connect()->commit();
    }
    
    /**
     * Annuler une transaction
     */
    public function rollback(): void
    {
        $this->connect()->rollback();
    }
}

/**
 * Fonction utilitaire pour créer une instance de DatabaseManager
 * 
 * @return DatabaseManager Instance de DatabaseManager
 */
function create_database_manager(): DatabaseManager
{
    global $conf;
    return new DatabaseManager($conf);
}

/**
 * Fonction de connexion PDO principale
 * 
 * @return PDO Instance de connexion à la base de données
 * @throws Exception En cas d'erreur de connexion
 */
function pdo_connect()
{
    // Éviter l'inclusion répétée de conf.php
    if (!isset($GLOBALS['conf'])) {
        include(__DIR__ . '/../conf.php');
        $GLOBALS['conf'] = $conf;
    } else {
        $conf = $GLOBALS['conf'];
    }
    
    // Vérifier si la base de données existe pour SQLite
    if ($conf['db_type'] === 'sqlite' && !file_exists($conf['db_path'])) {
        throw new PDOException("Base de données non trouvée: " . $conf['db_path']);
    }
    
    try {
        $db = new PDO($conf['dsn'], $conf['login'], $conf['pass']);
        
        // Options spécifiques pour SQLite
        if ($conf['db_type'] === 'sqlite') {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
            // S'assurer que SQLite peut écrire
            $db->exec("PRAGMA journal_mode=WAL");
            $db->exec("PRAGMA synchronous=NORMAL");
        }
        
        return $db;
    }
    catch (PDOException $e) {
        error_log('Connexion échouée : ' . $e->getMessage());
        throw new Exception('Connexion échouée : ' . $e->getMessage());
    }
}

