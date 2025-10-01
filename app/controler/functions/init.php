<?php
/**
 * Fichier d'initialisation des fonctions pour l'application Duplicator
 * 
 * Ce fichier inclut tous les modules de fonctions et initialise
 * les classes principales de l'application.
 */

// Inclure les fichiers de fonctions
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/machines.php';

// Initialiser les gestionnaires principaux
if (!function_exists('init_application')) {
    /**
     * Initialiser l'application avec les gestionnaires principaux
     * 
     * @return array Tableau contenant les gestionnaires initialisés
     */
    function init_application(): array
    {
        global $conf;
        
        // Créer le gestionnaire de base de données
        $database_manager = create_database_manager();
        
        // Créer le gestionnaire de machines
        $machine_manager = new MachineManager($database_manager);
        
        return [
            'database' => $database_manager,
            'machines' => $machine_manager
        ];
    }
}

// Vérifier que toutes les fonctions essentielles sont disponibles
if (!function_exists('template')) {
    die('Erreur: La fonction template() est manquante');
}

if (!function_exists('create_database_manager')) {
    die('Erreur: La fonction create_database_manager() est manquante');
}

if (!class_exists('MachineManager')) {
    die('Erreur: La classe MachineManager est manquante');
}

// Définir des constantes utiles
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Duplicator');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '0.2.2');
}

if (!defined('APP_AUTHOR')) {
    define('APP_AUTHOR', 'Collectif Duplicator');
}

// Configuration des erreurs en mode développement
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

// Configuration des logs
if (!function_exists('log_error')) {
    /**
     * Logger une erreur
     * 
     * @param string $message Message d'erreur
     * @param string $context Contexte de l'erreur
     */
    function log_error(string $message, string $context = ''): void
    {
        $log_message = date('Y-m-d H:i:s') . " [ERROR] " . $message;
        if (!empty($context)) {
            $log_message .= " [Context: $context]";
        }
        
        error_log($log_message);
    }
}

if (!function_exists('log_info')) {
    /**
     * Logger une information
     * 
     * @param string $message Message d'information
     * @param string $context Contexte de l'information
     */
    function log_info(string $message, string $context = ''): void
    {
        $log_message = date('Y-m-d H:i:s') . " [INFO] " . $message;
        if (!empty($context)) {
            $log_message .= " [Context: $context]";
        }
        
        error_log($log_message);
    }
}

// Fonction de validation des données
if (!function_exists('validate_input')) {
    /**
     * Valider et nettoyer les données d'entrée
     * 
     * @param mixed $input Données à valider
     * @param string $type Type de validation
     * @param mixed $default Valeur par défaut si validation échoue
     * @return mixed Données validées ou valeur par défaut
     */
    function validate_input($input, string $type = 'string', $default = null)
    {
        if ($input === null || $input === '') {
            return $default;
        }
        
        switch ($type) {
            case 'int':
                return is_numeric($input) ? (int) $input : $default;
                
            case 'float':
                return is_numeric($input) ? (float) $input : $default;
                
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) ?: $default;
                
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) ?: $default;
                
            case 'boolean':
                if (is_bool($input)) return $input;
                if (is_string($input)) {
                    $input = strtolower($input);
                    return in_array($input, ['1', 'true', 'yes', 'on']) ? true : false;
                }
                return (bool) $input;
                
            case 'string':
            default:
                return is_string($input) ? sanitize_string($input) : $default;
        }
    }
}

// Fonction de sécurité pour les tokens CSRF
if (!function_exists('generate_csrf_token')) {
    /**
     * Générer un token CSRF
     * 
     * @return string Token CSRF
     */
    function generate_csrf_token(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Vérifier un token CSRF
     * 
     * @param string $token Token à vérifier
     * @return bool True si le token est valide
     */
    function verify_csrf_token(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Ne pas démarrer automatiquement la session - laisser index.php le faire
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Log de l'initialisation
log_info('Application Duplicator initialisée avec succès', 'init.php');
?>
