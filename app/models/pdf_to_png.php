<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Convertit un PDF en images PNG (une image par page)
 */
function convert_pdf_to_png($pdf_file, $output_dir, $dpi = 150, $base_filename = 'page') {
    try {
        // Vérifier que Ghostscript est disponible
        $gs_command = 'gs';
        if (PHP_OS_FAMILY === 'Windows') {
            $gs_command = __DIR__ . '/../ghostscript/gswin64c.exe';
            if (!file_exists($gs_command)) {
                throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
            }
        }
        
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
        $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($output_pattern) . " " . escapeshellarg($pdf_file) . " 2>&1";
        
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("Erreur lors de la conversion avec Ghostscript. Code: " . $return_var . " Output: " . implode("\n", $output));
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

function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    $download_urls = array();
    
    try {
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
                // Créer le dossier tmp s'il n'existe pas (dans public pour accès web)
                $tmpDir = __DIR__ . '/../public/tmp/';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0777, true);
                }
                
                $timestamp = date('YmdHis');
                $originalName = pathinfo($_FILES["pdf"]["name"], PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                $uploadFile = $tmpDir . "pdf_upload_" . $timestamp . ".pdf";
                
                if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadFile)) {
                    // Créer un sous-dossier pour les images
                    $outputDir = $tmpDir . 'pdf_to_png_' . $timestamp . '/';
                    
                    // Exécuter la conversion
                    $created_files = convert_pdf_to_png($uploadFile, $outputDir, $dpi, $safe_filename);
                    
                    if (!empty($created_files)) {
                        $success = true;
                        
                        // Préparer les URLs de téléchargement
                        foreach ($created_files as $file) {
                            $basename = basename($file);
                            $dirname = basename(dirname($file));
                            $result[] = $basename;
                            $download_urls[] = "tmp/" . $dirname . "/" . $basename;
                        }
                        
                        // Créer un fichier ZIP contenant toutes les images
                        $zip_filename = $safe_filename . '_pages.zip';
                        $zip_path = $tmpDir . $zip_filename;
                        
                        $zip = new ZipArchive();
                        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                            foreach ($created_files as $file) {
                                $zip->addFile($file, basename($file));
                            }
                            $zip->close();
                            $zip_url = 'tmp/' . $zip_filename;
                        } else {
                            $zip_url = '';
                        }
                        
                        // Nettoyer le fichier PDF uploadé
                        unlink($uploadFile);
                    } else {
                        $errors[] = "Erreur lors de la génération des images PNG.";
                    }
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier.";
                }
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
        'zip_url' => isset($zip_url) ? $zip_url : ''
    ));
}

?>
