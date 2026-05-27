<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/unimpose_logic.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/utilities.php');

function unimpose_split_double_pages($input_file, $output_file) {
    /**Transforme un PDF avec couverture + doubles pages en pages individuelles - avec nettoyage Ghostscript forcé*/
    
    // Vérifier d'abord que le fichier existe et est lisible
    if (!file_exists($input_file) || !is_readable($input_file)) {
        throw new Exception("Le fichier PDF n'existe pas ou n'est pas lisible.");
    }
    
    // Utiliser le même dossier temporaire que pour l'upload
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
    
    if (!file_exists($tmp_dir)) {
        mkdir($tmp_dir, 0777, true);
        @chmod($tmp_dir, 0777);
    }
    
    $timestamp = date('YmdHis');
    $cleanedPdfFile = $tmp_dir . 'cleaned_unimpose_split_' . $timestamp . '.pdf';
    
    $gs_command = get_ghostscript_path();
    if (!$gs_command) {
        throw new Exception("Ghostscript n'a pas été trouvé sur ce système. Veuillez l'installer.");
    }
    
    $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($input_file);
    $gs_result = run_ghostscript($gs_args);
    $output = $gs_result['output'];
    $returnCode = $gs_result['success'] ? 0 : 1;
    
    if (!file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
        throw new Exception("Échec du nettoyage Ghostscript. Sortie: " . $output);
    }
    
    // Utiliser directement le fichier de sortie
    $finalOutputFile = $output_file;
    
    // Maintenant exécuter le découpage avec le PDF nettoyé
    try {
        // Utiliser la méthode splitDoublePages
        $unimpose = new UnimposeBooklet($cleanedPdfFile, $finalOutputFile);
        $resultFile = $unimpose->splitDoublePages();
    
        // Nettoyer le fichier temporaire
        if (file_exists($cleanedPdfFile)) {
            unlink($cleanedPdfFile);
        }
        
        if (!$resultFile) {
            throw new Exception("Échec du découpage des doubles pages du PDF");
        }
        
        return $resultFile;
        
    } catch (Exception $e) {
        // Nettoyer le fichier temporaire en cas d'erreur
        if (file_exists($cleanedPdfFile)) {
            unlink($cleanedPdfFile);
        }
        
        // Afficher l'erreur détaillée pour debug
        error_log("Erreur détaillée de découpage : " . $e->getMessage());
        error_log("Trace : " . $e->getTraceAsString());
        
        throw new Exception("Erreur lors du découpage : " . $e->getMessage());
    }
}

function unimpose_booklet($input_file, $output_file) {
    /**Transforme un livret en PDF page par page - avec nettoyage Ghostscript forcé*/
    
    // Vérifier d'abord que le fichier existe et est lisible
    if (!file_exists($input_file) || !is_readable($input_file)) {
        throw new Exception("Le fichier PDF n'existe pas ou n'est pas lisible.");
    }
    
    // FORCER le nettoyage Ghostscript dans tous les cas
    $timestamp = date('YmdHis');
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
    
    if (!file_exists($tmp_dir)) {
        mkdir($tmp_dir, 0777, true);
        @chmod($tmp_dir, 0777);
    }
    
    $cleanedPdfFile = $tmp_dir . 'cleaned_unimpose_' . $timestamp . '.pdf';
    
    $gs_command = get_ghostscript_path();
    if (!$gs_command) {
        throw new Exception("Ghostscript n'a pas été trouvé sur ce système. Veuillez l'installer.");
    }
    
    $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($input_file);
    $gs_result = run_ghostscript($gs_args);
    $output = $gs_result['output'];
    $returnCode = $gs_result['success'] ? 0 : 1;
    
    if (!file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
        throw new Exception("Échec du nettoyage Ghostscript. Sortie: " . $output);
    }
    
        // Utiliser directement le fichier de sortie (unimpose_logic.php ajoutera -ppp)
        $finalOutputFile = $output_file;
        
        // Maintenant exécuter la désimposition avec le PDF nettoyé
        try {
            // Utiliser la classe UnimposeBooklet existante
            $unimpose = new UnimposeBooklet($cleanedPdfFile, $finalOutputFile);
            $resultFile = $unimpose->unimposeBooklet();
        
        // Nettoyer le fichier temporaire
        if (file_exists($cleanedPdfFile)) {
            unlink($cleanedPdfFile);
        }
        
        if (!$resultFile) {
            throw new Exception("Échec de la désimposition du PDF");
        }
        
        return $resultFile;
        
    } catch (Exception $e) {
            // Nettoyer le fichier temporaire en cas d'erreur
            if (file_exists($cleanedPdfFile)) {
                unlink($cleanedPdfFile);
            }
            
            // Afficher l'erreur détaillée pour debug
            error_log("Erreur détaillée de désimposition : " . $e->getMessage());
            error_log("Trace : " . $e->getTraceAsString());
            
            throw new Exception("Erreur lors de la désimposition : " . $e->getMessage());
        }
}

if (!function_exists('Action')) {
function Action($conf) {
    // Initialiser le système de traduction
    I18nManager::getInstance();
    
    $errors = array();
    $success = false;
    $result = '';
    $download_url = '';
    $from_lib_file = null;
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib']) && !empty($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $from_lib_file = $libManager->getFile($_GET['from_lib']);
    }

    try {
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            $pdfFile = null;
            $originalName = null;
            $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;

            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0777, true);
                @chmod($tmpDir, 0777);
            }

            // Cas 1 : Fichier bibliothèque
            if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
                require_once __DIR__ . '/BibliothequeManager.php';
                $libManager = new BibliothequeManager();
                $file = $libManager->getFile($_POST['lib_file_id']);
                if ($file && file_exists($file['filepath'])) {
                    $pdfFile = $file['filepath'];
                    $originalName = pathinfo($file['filename'], PATHINFO_FILENAME);
                } else {
                    $errors[] = "Fichier de bibliothèque introuvable.";
                }
            } 
            // Cas 2 : Fichier uploadé
            elseif (isset($_FILES["pdf"]) && $_FILES["pdf"]["error"] == UPLOAD_ERR_OK) {
                // Vérifier le type MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES["pdf"]["tmp_name"]);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $errors[] = "Le fichier doit être un PDF.";
                } elseif ($_FILES["pdf"]["size"] == 0) {
                    $errors[] = "Le fichier est vide.";
                } elseif ($_FILES["pdf"]["size"] > 50 * 1024 * 1024) { // 50MB max
                    $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
                } else {
                    $timestamp = date('YmdHis');
                    $uploadFile = $tmpDir . "unimpose_upload_" . $timestamp . ".pdf";
                    if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadFile)) {
                        $pdfFile = $uploadFile;
                        $originalName = pathinfo($_FILES["pdf"]["name"], PATHINFO_FILENAME);
                    } else {
                        $errors[] = "Erreur lors de l'upload du fichier.";
                    }
                }
            }

            if ($pdfFile && empty($errors)) {
                // Déterminer le mode de désimposition
                $unimposeMode = isset($_POST['unimpose_mode']) ? $_POST['unimpose_mode'] : 'booklet';
                
                if ($unimposeMode === 'split_double_pages') {
                    $outputFile = $tmpDir . $originalName . '_split.pdf';
                    $resultFile = unimpose_split_double_pages($pdfFile, $outputFile);
                } else {
                    $outputFile = $tmpDir . $originalName . '_unimposed.pdf';
                    $resultFile = unimpose_booklet($pdfFile, $outputFile);
                }
                
                if (file_exists($resultFile)) {
                    $success = true;
                    $result = basename($resultFile);
                    $download_url = "?download_unimposed&file=" . urlencode(basename($resultFile));
                    
                    // Si c'était un upload temporaire, on le supprime
                    if (isset($uploadFile) && file_exists($uploadFile)) {
                        unlink($uploadFile);
                    }
                } else {
                    $errors[] = "Erreur lors de la génération du PDF désimposé.";
                }
            }
        }
    } catch (Exception $e) {
            // Afficher l'erreur détaillée pour debug
            error_log("Erreur détaillée dans Action : " . $e->getMessage());
            error_log("Trace complète : " . $e->getTraceAsString());
            
            $errors[] = "Erreur lors du traitement : " . $e->getMessage();
        }
    
    // Retourner le template avec les variables
    return template(__DIR__ . "/../view/unimpose.html.php", [
        'errors' => $errors,
        'success' => $success,
        'result' => $result,
        'download_url' => $download_url,
        'from_lib_file' => $from_lib_file
    ]);
}
}
?>