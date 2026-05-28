<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Convertit un PDF en images PNG (une image par page)
 */
function convert_pdf_to_png($pdf_file, $output_dir, $dpi = 150, $base_filename = 'page') {
    try {
        // Vérifier que le fichier PDF existe
        if (!file_exists($pdf_file)) {
            throw new Exception("Le fichier PDF n'existe pas : " . $pdf_file);
        }
        
        // Créer le dossier de sortie s'il n'existe pas
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        
        // Générer un préfixe avec le nom du fichier original
        $prefix = $base_filename . '_page_%03d.png';
        $output_pattern = $output_dir . $prefix;

        // Utiliser Ghostscript pour convertir le PDF en PNG
        $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($output_pattern) . " " . escapeshellarg($pdf_file);
        
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            throw new Exception("Erreur lors de la conversion avec Ghostscript. Code: " . $gs_result['error'] . " Output: " . $gs_result['output']);
        }
        
        // Lister les fichiers PNG créés
        $created_files = glob($output_dir . $base_filename . '_page_*.png');
        
        if (empty($created_files)) {
            throw new Exception("Aucune image n'a été créée. Le PDF est peut-être vide ou corrompu.");
        }
        
        // Trier les fichiers par nom
        sort($created_files);
        
        return $created_files;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion PDF vers PNG : " . $e->getMessage());
        throw $e;
    }
}

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    $download_urls = array();
    $from_lib_file = null;
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $file = $libManager->getFile($_GET['from_lib']);
        if ($file && $file['file_type'] === 'pdf' && file_exists($file['filepath'])) {
            $from_lib_file = $file;
        }
    }
    
    try {
        // Cas 1 : Fichier bibliothèque (POST avec lib_file_id)
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
            require_once __DIR__ . '/BibliothequeManager.php';
            $libManager = new BibliothequeManager();
            $file = $libManager->getFile($_POST['lib_file_id']);
            if ($file && $file['file_type'] === 'pdf' && file_exists($file['filepath'])) {
                $from_lib_file = $file;
            } else {
                $errors[] = "Fichier bibliothèque non trouvé ou invalide.";
            }
        }
        
        // Cas 2 : Upload classique
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdf"]) && $_FILES["pdf"]["error"] == UPLOAD_ERR_OK) {
            
            // Récupérer la qualité choisie (DPI)
            $dpi = isset($_POST['dpi']) ? intval($_POST['dpi']) : 150;
            if ($dpi < 72) $dpi = 72;
            if ($dpi > 300) $dpi = 300;
            
            // Vérifier le type MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES["pdf"]["tmp_name"]);
            finfo_close($finfo);
            
            if ($mimeType !== 'application/pdf') {
                $errors[] = "Le fichier doit être un PDF.";
            } elseif ($_FILES["pdf"]["size"] == 0) {
                $errors[] = "Le fichier est vide.";
            } elseif ($_FILES["pdf"]["size"] > 50 * 1024 * 1024) {
                $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
            } else {
                // Utiliser sys_get_temp_dir() pour être compatible AppImage
                $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_pdf_to_png' . DIRECTORY_SEPARATOR;
                if (!is_dir($tmpDir)) {
                    if (!mkdir($tmpDir, 0777, true)) {
                        throw new Exception("Impossible de créer le répertoire temporaire : " . $tmpDir);
                    }
                }
                
                $timestamp = date('YmdHis');
                $originalName = pathinfo($_FILES["pdf"]["name"], PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                $uploadFile = $tmpDir . "pdf_upload_" . $timestamp . ".pdf";
                
                if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadFile)) {
                    // Créer un sous-dossier pour les images
                    $outputDir = $tmpDir . 'pdf_to_png_' . $timestamp . DIRECTORY_SEPARATOR;
                    
                    // Exécuter la conversion
                    $created_files = convert_pdf_to_png($uploadFile, $outputDir, $dpi, $safe_filename);
                    
                    if (!empty($created_files)) {
                        $success = true;
                        
                        // Préparer les URLs de téléchargement (via route API)
                        foreach ($created_files as $file) {
                            $basename = basename($file);
                            $result[] = $basename;
                            // Utiliser une route API pour servir les fichiers depuis le dossier temporaire
                            $download_urls[] = "?download_png&file=" . urlencode($basename) . "&dir=" . urlencode('pdf_to_png_' . $timestamp);
                        }
                        
                        // Créer un fichier ZIP contenant toutes les images
                        $zip_filename = $safe_filename . '_pages.zip';
                        $zip_path = $tmpDir . $zip_filename;
                        
                        if (class_exists('ZipArchive')) {
                            $zip = new ZipArchive();
                            if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                                foreach ($created_files as $file) {
                                    $zip->addFile($file, basename($file));
                                }
                                $zip->close();
                                $zip_url = '?download_png&file=' . urlencode($zip_filename) . '&dir=';
                            } else {
                                $zip_url = '';
                            }
                        } else {
                            // Extension zip non disponible, on continue sans créer le ZIP
                            $zip_url = '';
                            error_log("Extension ZipArchive non disponible. Le fichier ZIP ne sera pas créé.");
                        }
                        
                        // Nettoyer le fichier PDF uploadé
                        unlink($uploadFile);
                    } else {
                        $errors[] = "Erreur lors de la génération des images PNG.";
                    }
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier vers : " . $uploadFile;
                }
            }
        }
        
        // Traitement du fichier bibliothèque
        if ($from_lib_file) {
            // Récupérer la qualité choisie (DPI)
            $dpi = isset($_POST['dpi']) ? intval($_POST['dpi']) : 150;
            if ($dpi < 72) $dpi = 72;
            if ($dpi > 300) $dpi = 300;
            
            // Utiliser sys_get_temp_dir() pour être compatible AppImage
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_pdf_to_png' . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpDir)) {
                if (!mkdir($tmpDir, 0777, true)) {
                    throw new Exception("Impossible de créer le répertoire temporaire : " . $tmpDir);
                }
            }
            
            $timestamp = date('YmdHis');
            $originalName = pathinfo($from_lib_file['filename'], PATHINFO_FILENAME);
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
            
            // Créer un sous-dossier pour les images
            $outputDir = $tmpDir . 'pdf_to_png_' . $timestamp . DIRECTORY_SEPARATOR;
            
            // Exécuter la conversion directement depuis le fichier bibliothèque
            $created_files = convert_pdf_to_png($from_lib_file['filepath'], $outputDir, $dpi, $safe_filename);
            
            if (!empty($created_files)) {
                $success = true;
                
                // Préparer les URLs de téléchargement (via route API)
                foreach ($created_files as $file) {
                    $basename = basename($file);
                    $result[] = $basename;
                    // Utiliser une route API pour servir les fichiers depuis le dossier temporaire
                    $download_urls[] = "?download_png&file=" . urlencode($basename) . "&dir=" . urlencode('pdf_to_png_' . $timestamp);
                }
                
                // Créer un fichier ZIP contenant toutes les images
                $zip_filename = $safe_filename . '_pages.zip';
                $zip_path = $tmpDir . $zip_filename;
                
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive();
                    if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                        foreach ($created_files as $file) {
                            $zip->addFile($file, basename($file));
                        }
                        $zip->close();
                        $zip_url = '?download_png&file=' . urlencode($zip_filename) . '&dir=';
                    } else {
                        $zip_url = '';
                    }
                } else {
                    // Extension zip non disponible, on continue sans créer le ZIP
                    $zip_url = '';
                    error_log("Extension ZipArchive non disponible. Le fichier ZIP ne sera pas créé.");
                }
            } else {
                $errors[] = "Erreur lors de la génération des images PNG.";
            }
        }
    } catch (Exception $e) {
        error_log("Erreur détaillée dans Action : " . $e->getMessage());
        error_log("Trace complète : " . $e->getTraceAsString());
        $errors[] = "Erreur lors du traitement : " . $e->getMessage();
    }
    
    return template("../view/pdf_to_png.html.php", array(
        'errors' => $errors,
        'success' => $success,
        'result' => $result,
        'download_urls' => $download_urls,
        'zip_url' => isset($zip_url) ? $zip_url : '',
        'from_lib_file' => $from_lib_file
    ));
}
}

?>
