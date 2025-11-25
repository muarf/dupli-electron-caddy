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
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
    
    if (!file_exists($tmp_dir)) {
        mkdir($tmp_dir, 0755, true);
    }
    
    $timestamp = date('YmdHis');
    $cleanedPdfFile = $tmp_dir . 'cleaned_unimpose_split_' . $timestamp . '.pdf';
    
    // Nettoyer le PDF avec Ghostscript - détection automatique de la plateforme
    if (PHP_OS_FAMILY === 'Windows') {
        // Chemin complet vers Ghostscript Windows
        $gs_command = __DIR__ . '/../../ghostscript/gswin64c.exe';
        if (!file_exists($gs_command)) {
            throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
        }
    } else {
        $gs_command = 'gs';
    }
    
    $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($input_file) . " 2>&1";
    $output = shell_exec($command);
    
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
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
    
    if (!file_exists($tmp_dir)) {
        mkdir($tmp_dir, 0755, true);
    }
    
    $cleanedPdfFile = $tmp_dir . 'cleaned_unimpose_' . $timestamp . '.pdf';
    
    // Nettoyer le PDF avec Ghostscript - détection automatique de la plateforme
    if (PHP_OS_FAMILY === 'Windows') {
        // Chemin complet vers Ghostscript Windows
        $gs_command = __DIR__ . '/../../ghostscript/gswin64c.exe';
        if (!file_exists($gs_command)) {
            throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
        }
    } else {
        $gs_command = 'gs';
    }
    
    $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($input_file) . " 2>&1";
    $output = shell_exec($command);
    
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

function Action($conf) {
    // Initialiser le système de traduction
    I18nManager::getInstance();
    
    $errors = array();
    $success = false;
    $result = '';
    $download_url = '';
    
    try {
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdf"]) && $_FILES["pdf"]["error"] == UPLOAD_ERR_OK) {
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
                // Utiliser le répertoire temporaire système pour être compatible AppImage (ReadOnly FS)
                $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_unimpose' . DIRECTORY_SEPARATOR;
                if (!is_dir($tmpDir)) {
                    if (!mkdir($tmpDir, 0777, true)) {
                        throw new Exception("Impossible de créer le répertoire temporaire : " . $tmpDir);
                    }
                }
                
                // Sauvegarder le fichier uploadé
                $timestamp = date('YmdHis');
                $uploadFile = $tmpDir . "unimpose_upload_" . $timestamp . ".pdf";
                
                if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadFile)) {
                    // Générer le fichier de sortie avec le nom original + _unimposed
                    $originalName = pathinfo($_FILES["pdf"]["name"], PATHINFO_FILENAME);
                    
                    // Déterminer le mode de désimposition
                    $unimposeMode = isset($_POST['unimpose_mode']) ? $_POST['unimpose_mode'] : 'booklet';
                    
                    if ($unimposeMode === 'split_double_pages') {
                        // Mode : couverture + doubles pages
                        $outputFile = $tmpDir . $originalName . '_split.pdf';
                        $resultFile = unimpose_split_double_pages($uploadFile, $outputFile);
                    } else {
                        // Mode : livret classique (par défaut)
                        $outputFile = $tmpDir . $originalName . '_unimposed.pdf';
                        $resultFile = unimpose_booklet($uploadFile, $outputFile);
                    }
                    
                    if (file_exists($resultFile)) {
                        $success = true;
                        $result = basename($resultFile);
                        // URL spéciale pour télécharger depuis le dossier temporaire système
                        $download_url = "?download_unimposed&file=" . urlencode(basename($resultFile));
                        
                        // Nettoyer le fichier d'upload temporaire
                        unlink($uploadFile);
                    } else {
                        $errors[] = "Erreur lors de la génération du PDF désimposé.";
                    }
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier vers : " . $uploadFile;
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
        'download_url' => $download_url
    ]);
}
?>