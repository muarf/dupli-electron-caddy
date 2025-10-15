<?php
/**
 * Fonctions de gestion des machines et des tirages pour l'application Duplicator
 * 
 * Ce fichier contient toutes les fonctions liées à la gestion des photocopieurs,
 * duplicopieurs, et des opérations de tirage.
 */

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/database.php';

/**
 * Classe pour la gestion des machines et des tirages
 */
class MachineManager
{
    /**
     * Instance de DatabaseManager
     * @var DatabaseManager
     */
    private $db;
    
    /**
     * Constructeur
     * 
     * @param DatabaseManager $db Instance de DatabaseManager
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }
    
    /**
     * Vérifier si les machines existent dans la base de données
     * 
     * @return bool True si au moins une machine existe
     */
    public function checkMachinesExist(): bool
    {
        try {
            // Vérifier les photocopieurs
            $photocop_count = $this->db->count('photocop');
            
            // Vérifier les duplicopieurs
            $duplicopieurs_count = $this->db->count('duplicopieurs');
            
            return ($photocop_count > 0 || $duplicopieurs_count > 0);
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des machines: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir la liste des photocopieurs actifs
     * 
     * @return array Liste des photocopieurs
     */
    public function getActivePhotocopiers(): array
    {
        try {
            return $this->db->select(
                "SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != '' ORDER BY marque"
            );
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des photocopieurs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtenir la liste des duplicopieurs actifs
     * 
     * @return array Liste des duplicopieurs
     */
    public function getActiveDuplicators(): array
    {
        try {
            return $this->db->select(
                "SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele"
            );
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des duplicopieurs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtenir le premier duplicopieur disponible
     * 
     * @return array|null Premier duplicopieur ou null
     */
    public function getFirstDuplicator(): ?array
    {
        try {
            $result = $this->db->selectOne(
                "SELECT * FROM duplicopieurs WHERE actif = 1 ORDER BY id LIMIT 1"
            );
            return $result;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du premier duplicopieur: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculer le nombre de feuilles depuis un intervalle de temps
     * 
     * @param string $machine Nom de la machine (table)
     * @param int $now Timestamp actuel
     * @param int $ago Timestamp de début de l'intervalle
     * @return array Statistiques des feuilles
     */
    public function calculateSheetsFromInterval(string $machine, int $now, int $ago): array
    {
        try {
            $nbf_total = [];
            
            if (!empty($now)) {
                $nbf_total['ago'] = $now - $ago;
                
                // Calculer le nombre de feuilles recto-verso
                $rv_result = $this->db->selectOne(
                    "SELECT SUM(rv) as nbr FROM `$machine` WHERE date < ? AND date > ?",
                    [$now, $nbf_total['ago']]
                );
                $nbf_total['nbf'] = $rv_result['nbr'] ?? 0;
                
                // Calculer le montant total
                $prix_result = $this->db->selectOne(
                    "SELECT SUM(prix) as nbr FROM `$machine` WHERE date < ? AND date > ?",
                    [$now, $nbf_total['ago']]
                );
                $nbf_total['montant'] = $prix_result['nbr'] ?? 0;
            }
            
            return $nbf_total;
        } catch (PDOException $e) {
            error_log("Erreur lors du calcul des feuilles: " . $e->getMessage());
            return ['ago' => 0, 'nbf' => 0, 'montant' => 0];
        }
    }
    
    /**
     * Obtenir les dernières nouvelles
     * 
     * @param int $limit Nombre de nouvelles à récupérer
     * @return array Liste des nouvelles
     */
    public function getLatestNews(int $limit = 3): array
    {
        try {
            $news = $this->db->select(
                "SELECT * FROM news ORDER BY id DESC LIMIT ?",
                [$limit]
            );
            
            $formatted_news = [];
            foreach ($news as $i => $result) {
                $formatted_news[$i] = [
                    'time' => format_date_french($result['time']),
                    'titre' => $result['titre'],
                    'news' => $result['news']
                ];
            }
            
            return $formatted_news;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des nouvelles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Insérer un email dans la liste de diffusion
     * 
     * @param string $email Adresse email à ajouter
     * @return string Message de résultat
     */
    public function insertEmail(string $email): string
    {
        try {
            // Envoyer l'email de souscription (optionnel)
            $this->sendSubscriptionEmail($email);
            
            // Insérer dans la base de données
            $this->db->insert(
                "INSERT INTO email (email) VALUES (?)",
                [$email]
            );
            
            return '<div class="alert alert-success">
                <strong>Succès!</strong> Votre email a bien été ajouté
            </div>';
        } catch (PDOException $e) {
            error_log("Erreur lors de l'insertion de l'email: " . $e->getMessage());
            return '<div class="alert alert-danger">
                <strong>Danger!</strong> Une erreur s\'est produite.
                <a href="javascript:" onclick="history.go(-1); return false;">Retour</a>
            </div>';
        }
    }
    
    /**
     * Envoyer un email de souscription (fonction optionnelle)
     * 
     * @param string $email Adresse email
     */
    private function sendSubscriptionEmail(string $email): void
    {
        // Cette fonction peut être activée si nécessaire
        // Pour l'instant, elle est désactivée pour éviter les erreurs
        /*
        $to = "duplicator-subscribe@lists.riseup.net";
        $subject = "subscribe";
        $message = "not_bb";
        $headers = "From: <$email>\r\n";
        
        mail($to, $subject, $message, $headers);
        */
    }
    
    /**
     * Obtenir les statistiques des tirages par mois
     * 
     * @param int $year Année (par défaut: année actuelle)
     * @return array Statistiques par mois
     */
    public function getMonthlyStats(?int $year = null): array
    {
        if ($year === null) {
            $year = (int) date('Y');
        }
        
        try {
            $stats = $this->db->select(
                "SELECT mois, SUM(nb_tirages) as total_tirages, SUM(montant_total) as total_montant 
                 FROM stats 
                 WHERE annee = ? 
                 GROUP BY mois 
                 ORDER BY mois",
                [$year]
            );
            
            // Formater les résultats
            $formatted_stats = [];
            foreach ($stats as $stat) {
                $month_name = $this->getMonthName($stat['mois']);
                $formatted_stats[] = [
                    'mois' => $stat['mois'],
                    'mois_nom' => $month_name,
                    'total_tirages' => (int) $stat['total_tirages'],
                    'total_montant' => (float) $stat['total_montant']
                ];
            }
            
            return $formatted_stats;
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtenir le nom du mois en français
     * 
     * @param int $month Numéro du mois (1-12)
     * @return string Nom du mois en français
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        return $months[$month] ?? 'Mois inconnu';
    }
}

/**
 * Récupère le dernier numéro de compteur pour une machine
 * 
 * @param string $machine Nom de la machine
 * @param int $duplicopieur_id ID du duplicopieur (optionnel)
 * @return array Derniers compteurs (master_av, passage_av)
 */
function get_last_number($machine, $duplicopieur_id = null)
{
    $db = pdo_connect();
    
    // Convertir le nom de la machine en minuscules pour correspondre aux noms de tables
    $table_name = strtolower($machine);
    
    // Gérer le cas spécial des duplicopieurs qui utilisent tous la table 'dupli'
    if ($table_name === 'dupli' || $table_name === 'dx4545' || $table_name === 'a3') {
        $table_name = 'dupli';
        
        // Si on a un duplicopieur_id, l'utiliser pour filtrer
        if ($duplicopieur_id) {
            $query = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE duplicopieur_id = ? ORDER BY id DESC LIMIT 1');
            $query->execute([$duplicopieur_id]);
        } else {
            // Essayer de trouver l'ID du duplicopieur par son nom
            $duplicopieur_id = get_duplicopieur_id_by_name($machine);
            if ($duplicopieur_id) {
                $query = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE duplicopieur_id = ? ORDER BY id DESC LIMIT 1');
                $query->execute([$duplicopieur_id]);
            } else {
                // Fallback : utiliser l'ancien système avec nom_machine
                $query = $db->prepare('SELECT master_ap, passage_ap FROM dupli WHERE nom_machine = ? ORDER BY id DESC LIMIT 1');
                $query->execute([$machine]);
            }
        }
    } else {
        // Pour les autres machines (photocopieurs), utiliser l'ancien système
        $query = $db->query('SELECT master_ap, passage_ap FROM ' . $table_name . ' ORDER BY id DESC LIMIT 1');
    }
    
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if (!$result) {
        // Si la table est vide, retourner des valeurs par défaut
        $last['master_av'] = 0;
        $last['passage_av'] = 0;
    } else {
        $last['master_av'] = ceil($result->master_ap);
        $last['passage_av'] = ceil($result->passage_ap);
    }
    return $last;
}

/**
 * Récupère l'ID d'un duplicopieur par son nom
 * 
 * @param string $machine_name Nom de la machine
 * @return int|null ID du duplicopieur ou null si non trouvé
 */
function get_duplicopieur_id_by_name($machine_name)
{
    $db = pdo_connect();
    
    // Essayer plusieurs formats de nom
    $query = $db->prepare('SELECT id FROM duplicopieurs WHERE (marque || " " || modele = ? OR marque = ? OR modele = ?) AND actif = 1 LIMIT 1');
    $query->execute([$machine_name, $machine_name, $machine_name]);
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    return $result ? intval($result['id']) : null;
}

/**
 * Récupère les derniers compteurs pour un photocopieur
 * 
 * @param string $machine Nom de la machine
 * @return array Derniers compteurs (master_av, passage_av)
 */
function get_last_counters_photocop($machine)
{
    $db = pdo_connect();
    
    // Pour les photocopieurs, chercher dans la table cons le dernier changement pour cette machine
    $query = $db->prepare('SELECT nb_p FROM cons WHERE machine = ? ORDER BY date DESC LIMIT 1');
    $query->execute([$machine]);
    $result = $query->fetch(PDO::FETCH_OBJ);
    
    if (!$result) {
        // Si aucun changement trouvé, retourner 0
        $last['passage_av'] = 0;
    } else {
        $last['passage_av'] = $result->nb_p;
    }
    
    // Les photocopieurs n'ont pas de masters
    $last['master_av'] = 0;
    
    return $last;
}

/**
 * Récupère la liste de toutes les machines disponibles
 * 
 * @return array Liste des machines
 */
function get_machines()
{
    $db = pdo_connect();
    
    // Récupérer les duplicopieurs actifs
    $query = $db->query('SELECT id, marque, modele FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele');
    $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les photocopieurs installés
    $query = $db->query('SELECT DISTINCT marque FROM photocop WHERE marque IS NOT NULL AND marque != "" ORDER BY marque');
    $photocopiers = $query->fetchAll(PDO::FETCH_COLUMN);
    
    // Construire la liste des machines
    $machines = array();
    
    // Ajouter chaque duplicopieur avec son nom complet
    foreach ($duplicopieurs as $dup) {
        $nom_complet = $dup['marque'] . ' ' . $dup['modele'];
        if ($dup['marque'] === $dup['modele']) {
            $nom_complet = $dup['marque'];
        }
        $machines[] = $nom_complet;
    }
    
    // Ajouter chaque photocopieur individuellement
    foreach ($photocopiers as $photocop_name) {
        $machines[] = $photocop_name;
    }
    
    return $machines;
}

/**
 * Vérifie si des machines existent dans la base de données
 * 
 * @return bool True si au moins une machine existe
 */
function check_machines_exist()
{
    $db = pdo_connect();
    
    // Vérifier s'il y a des machines dans les nouvelles tables
    $has_machines = false;
    
    try {
        // Vérifier les duplicopieurs
        $query = $db->query('SELECT COUNT(*) as count FROM duplicopieurs WHERE actif = 1');
        $result = $query->fetch(PDO::FETCH_OBJ);
        if ($result && $result->count > 0) {
            $has_machines = true;
        }
    } catch (PDOException $e) {
        // Table n'existe pas encore, continuer
    }
    
    // Vérifier les photocopieurs
    if (!$has_machines) {
        try {
            $query = $db->query('SELECT COUNT(*) as count FROM photocopieurs WHERE actif = 1');
            $result = $query->fetch(PDO::FETCH_OBJ);
            if ($result && $result->count > 0) {
                $has_machines = true;
            }
        } catch (PDOException $e) {
            // Table n'existe pas encore, continuer
        }
    }
    
    // Vérifier les anciennes tables pour compatibilité
    if (!$has_machines) {
        $old_machines = array('dupli', 'photocop');
        foreach ($old_machines as $machine) {
            try {
                $query = $db->query('SELECT COUNT(*) as count FROM ' . $machine);
                $result = $query->fetch(PDO::FETCH_OBJ);
                if ($result && $result->count > 0) {
                    $has_machines = true;
                    break;
                }
            } catch (Exception $e) {
                // Table n'existe pas, continuer
            }
        }
    }
    
    return $has_machines;
}

?>
