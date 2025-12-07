<?php
// Désactiver l'affichage des erreurs pour éviter la pollution JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Fonction pour renvoyer une réponse JSON d'erreur
function sendJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    // Nettoyer tout buffer avant d'envoyer
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fonction pour renvoyer une réponse JSON de succès
function sendJsonSuccess($data) {
    header('Content-Type: application/json; charset=utf-8');
    // Nettoyer tout buffer avant d'envoyer
    while (ob_get_level()) {
        ob_end_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Nettoyer tout buffer de sortie au début
while (ob_get_level()) {
    ob_end_clean();
}

// Démarrer un nouveau buffer pour capturer les erreurs
ob_start();

try {
    require_once __DIR__ . '/../controler/conf.php';
    require_once __DIR__ . '/../controler/func.php';
    require_once __DIR__ . '/../models/BibliothequeManager.php';
} catch (Exception $e) {
    error_log("Erreur chargement fichiers search_bibliotheque: " . $e->getMessage());
    sendJsonError("Erreur de chargement: " . $e->getMessage());
} catch (Error $e) {
    error_log("Erreur fatale chargement fichiers search_bibliotheque: " . $e->getMessage());
    sendJsonError("Erreur fatale de chargement: " . $e->getMessage());
}

$search = $_GET['q'] ?? '';
$type = $_GET['type'] ?? '';

try {
    // Vérifier que la table existe, sinon la créer
    $db = pdo_connect();
    if (!$db) {
        sendJsonError("Impossible de se connecter à la base de données");
    }
    
    $tableExists = false;
    try {
        $checkQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bibliotheque_files'");
        $tableExists = $checkQuery->fetch() !== false;
    } catch (Exception $e) {
        error_log("Erreur vérification table bibliotheque_files: " . $e->getMessage());
    }
    
    if (!$tableExists) {
        // Créer la table si elle n'existe pas
        error_log("Table bibliotheque_files n'existe pas, création...");
        try {
            require_once __DIR__ . '/../models/migrations/DatabaseMigrationManager.php';
            $migrationManager = new DatabaseMigrationManager($conf);
            $migrationManager->runMigrations();
        } catch (Exception $e) {
            error_log("Erreur lors de la création de la table: " . $e->getMessage());
            // Créer la table manuellement si la migration échoue
            try {
                $createTableSQL = "CREATE TABLE IF NOT EXISTS bibliotheque_files (
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
                $db->exec($createTableSQL);
                $db->exec("CREATE INDEX IF NOT EXISTS idx_bibliotheque_filename ON bibliotheque_files(filename)");
                $db->exec("CREATE INDEX IF NOT EXISTS idx_bibliotheque_type ON bibliotheque_files(file_type)");
            } catch (Exception $e2) {
                error_log("Erreur création manuelle table: " . $e2->getMessage());
                sendJsonError("Impossible de créer la table bibliotheque_files: " . $e2->getMessage());
            }
        }
    }
    
    $manager = new BibliothequeManager();
    $files = $manager->getAllFiles($search, $type);
    
    // Nettoyer les données pour éviter les problèmes d'encodage UTF-8
    $cleanedFiles = array_map(function($file) {
        // Nettoyer chaque champ texte pour s'assurer qu'il est en UTF-8 valide
        foreach ($file as $key => $value) {
            if (is_string($value)) {
                // Supprimer les caractères UTF-8 invalides
                $file[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                // Supprimer les caractères de contrôle et les caractères invalides
                $file[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $file[$key]);
                // S'assurer que c'est bien de l'UTF-8 valide
                if (!mb_check_encoding($file[$key], 'UTF-8')) {
                    $file[$key] = mb_convert_encoding($file[$key], 'UTF-8', 'UTF-8');
                    // Si toujours invalide, remplacer par une chaîne vide
                    if (!mb_check_encoding($file[$key], 'UTF-8')) {
                        $file[$key] = '';
                    }
                }
            }
        }
        return $file;
    }, $files);
    
    sendJsonSuccess([
        'success' => true,
        'files' => $cleanedFiles
    ]);
    
} catch (Exception $e) {
    error_log("Erreur search_bibliotheque: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    sendJsonError($e->getMessage());
} catch (Error $e) {
    error_log("Erreur fatale search_bibliotheque: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    sendJsonError('Erreur fatale: ' . $e->getMessage());
}





