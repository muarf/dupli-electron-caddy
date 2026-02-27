<?php
/**
 * Gestionnaire d'upload de PDFs pour les aides
 * Ce fichier gère l'upload, la suppression et la récupération des PDFs d'aide
 */

// Inclure la configuration AVANT les fonctions pour qu'elles soient disponibles
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

// La session est déjà démarrée par public/index.php
// Ne pas redémarrer la session si elle est déjà active
if (session_status() === PHP_SESSION_NONE) {
    // Configuration cross-platform des chemins temporaires
    $temp_dir = sys_get_temp_dir();
    $session_path = $temp_dir . DIRECTORY_SEPARATOR . 'duplicator_sessions';
    
    // Créer le répertoire de sessions s'il n'existe pas
    if (!is_dir($session_path)) {
        mkdir($session_path, 0777, true);
    }
    
    // Configurer les chemins temporaires
    session_save_path($session_path);
    
    // Démarrer la session
    session_start();
}

// Initialiser le système de traduction
require_once __DIR__ . '/../controler/functions/i18n.php';
I18nManager::getInstance();

// Vérifier l'authentification admin
// Accepter soit $_SESSION['admin'] === true soit $_SESSION['user'] === "1" (admin)
if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && 
    (!isset($_SESSION['user']) || $_SESSION['user'] !== "1")) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Non autorisé', 'debug' => $_SESSION]);
    exit;
}

// Définir les headers pour JSON
header('Content-Type: application/json; charset=utf-8');

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Fonction pour nettoyer le nom de fichier
function sanitizeFileName($filename) {
    // Supprimer l'extension
    $name = pathinfo($filename, PATHINFO_FILENAME);
    // Remplacer les caractères spéciaux par des underscores
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    // Limiter la longueur
    $name = substr($name, 0, 50);
    return $name;
}

// Fonction pour résoudre le répertoire de stockage des PDFs d'aide
function resolveAidePdfDir() {
    if (function_exists('getAidePdfDir')) {
        return getAidePdfDir();
    }
    
    // Fallback si paths.php n'est pas chargé (ne devrait pas arriver)
    return normalizePath(__DIR__ . '/../public/uploads/aide_pdfs');
}

// Fonction pour normaliser un chemin
function normalizePath($path) {
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $path = trim($path);
    return rtrim($path, DIRECTORY_SEPARATOR);
}

// Fonction pour récupérer la liste des PDFs
function getUploadedPdfs() {
    $pdfDir = resolveAidePdfDir() . DIRECTORY_SEPARATOR;
    $pdfs = [];
    
    if (is_dir($pdfDir)) {
        $files = scandir($pdfDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                $filePath = $pdfDir . $file;
                $pdfs[] = [
                    'filename' => $file,
                    'name' => sanitizeFileName($file),
                    'size' => formatFileSize(filesize($filePath)),
                    'upload_date' => date('d/m/Y H:i', filemtime($filePath)),
                    'url' => '?view_aide_pdf&file=' . urlencode($file)
                ];
            }
        }
        // Trier par date de modification (plus récent en premier)
        usort($pdfs, function($a, $b) {
            return strcmp($b['upload_date'], $a['upload_date']);
        });
    }
    
    return $pdfs;
}

// Gestion des requêtes AJAX
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload':
            // Gestion de l'upload
            if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors de l\'upload du fichier.');
            }
            
            $file = $_FILES['pdf_file'];
            
            // Vérifier le type MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mimeType !== 'application/pdf') {
                throw new Exception('Le fichier doit être un PDF.');
            }
            
            // Pas de limite de taille pour les PDFs d'aide
            
            // Créer un nom de fichier unique
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            $sanitizedName = sanitizeFileName($originalName);
            $timestamp = date('Y-m-d_H-i-s');
            $uniqueFilename = $sanitizedName . '_' . $timestamp . '.pdf';
            
            // Chemin de destination (utiliser le répertoire résolu)
            $uploadDir = resolveAidePdfDir() . DIRECTORY_SEPARATOR;
            $uploadPath = $uploadDir . $uniqueFilename;
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Déplacer le fichier
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Impossible de sauvegarder le fichier.');
            }
            
            // Définir les permissions
            chmod($uploadPath, 0644);
            
            echo json_encode([
                'success' => true,
                'message' => _e('admin_aide.upload_success'),
                'filename' => $uniqueFilename,
                'url' => '?view_aide_pdf&file=' . urlencode($uniqueFilename)
            ]);
            break;
            
        case 'list':
            // Récupérer la liste des PDFs
            $pdfs = getUploadedPdfs();
            echo json_encode([
                'success' => true,
                'pdfs' => $pdfs
            ]);
            break;
            
        case 'delete':
            // Supprimer un PDF
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception('Nom de fichier manquant.');
            }
            
            // Sécuriser le nom de fichier
            $filename = basename($filename);
            $filePath = resolveAidePdfDir() . DIRECTORY_SEPARATOR . $filename;
            
            if (!file_exists($filePath)) {
                throw new Exception('Fichier non trouvé.');
            }
            
            if (!unlink($filePath)) {
                throw new Exception('Impossible de supprimer le fichier.');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'PDF supprimé avec succès.'
            ]);
            break;
            
        default:
            throw new Exception('Action non reconnue.');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
