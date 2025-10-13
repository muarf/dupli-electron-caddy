<?php
require_once(__DIR__ . '/../vendor/autoload.php');

/**
 * Convertit une ou plusieurs images PNG en PDF avec le format choisi (A3/A4)
 */
function convert_png_to_pdf($image_files, $output_file, $format = 'A4', $orientation = 'P') {
    try {
        // Créer un nouveau PDF avec TCPDF
        $pdf = new TCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
        
        // Configurer le PDF
        $pdf->SetCreator('Duplicator');
        $pdf->SetAuthor('Duplicator');
        $pdf->SetTitle('Conversion PNG vers PDF');
        $pdf->SetSubject('Images converties en PDF');
        
        // Supprimer les en-têtes et pieds de page
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configurer les marges
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Ajouter chaque image sur une nouvelle page
        foreach ($image_files as $image_file) {
            if (!file_exists($image_file)) {
                throw new Exception("Le fichier image n'existe pas : " . $image_file);
            }
            
            // Ajouter une page
            $pdf->AddPage();
            
            // Obtenir les dimensions de la page
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            
            // Obtenir les dimensions de l'image
            list($imgWidth, $imgHeight) = getimagesize($image_file);
            
            // Calculer le ratio pour ajuster l'image à la page
            $widthRatio = $pageWidth / $imgWidth;
            $heightRatio = $pageHeight / $imgHeight;
            $ratio = min($widthRatio, $heightRatio);
            
            // Calculer les nouvelles dimensions
            $newWidth = $imgWidth * $ratio;
            $newHeight = $imgHeight * $ratio;
            
            // Centrer l'image
            $x = ($pageWidth - $newWidth) / 2;
            $y = ($pageHeight - $newHeight) / 2;
            
            // Insérer l'image
            $pdf->Image($image_file, $x, $y, $newWidth, $newHeight, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Sauvegarder le PDF
        $pdf->Output($output_file, 'F');
        
        return file_exists($output_file);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion PNG vers PDF : " . $e->getMessage());
        throw $e;
    }
}

function Action($conf) {
    $errors = array();
    $success = false;
    $result = '';
    $download_url = '';
    
    try {
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["images"]) && !empty($_FILES["images"]["name"][0])) {
            
            // Récupérer le format choisi
            $format = isset($_POST['format']) && $_POST['format'] === 'A3' ? 'A3' : 'A4';
            $orientation = isset($_POST['orientation']) && $_POST['orientation'] === 'L' ? 'L' : 'P';
            
            // Créer le dossier tmp s'il n'existe pas (dans public pour accès web)
            $tmpDir = __DIR__ . '/../public/tmp/';
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0777, true);
            }
            
            $uploadedFiles = [];
            $timestamp = date('YmdHis');
            
            // Traiter chaque fichier uploadé
            foreach ($_FILES["images"]["name"] as $key => $name) {
                if ($_FILES["images"]["error"][$key] == UPLOAD_ERR_OK) {
                    // Vérifier le type MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES["images"]["tmp_name"][$key]);
                    finfo_close($finfo);
                    
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                    if (!in_array($mimeType, $allowedTypes)) {
                        $errors[] = "Le fichier {$name} n'est pas une image valide (PNG/JPG uniquement).";
                        continue;
                    }
                    
                    if ($_FILES["images"]["size"][$key] == 0) {
                        $errors[] = "Le fichier {$name} est vide.";
                        continue;
                    }
                    
                    if ($_FILES["images"]["size"][$key] > 50 * 1024 * 1024) {
                        $errors[] = "Le fichier {$name} est trop volumineux (maximum 50MB).";
                        continue;
                    }
                    
                    // Sauvegarder le fichier uploadé
                    $uploadFile = $tmpDir . "img_upload_" . $timestamp . "_" . $key . "_" . basename($name);
                    
                    if (move_uploaded_file($_FILES["images"]["tmp_name"][$key], $uploadFile)) {
                        $uploadedFiles[] = $uploadFile;
                    } else {
                        $errors[] = "Erreur lors de l'upload du fichier {$name}.";
                    }
                }
            }
            
            // Si on a au moins un fichier uploadé et pas d'erreurs bloquantes
            if (!empty($uploadedFiles)) {
                // Utiliser le nom de la première image comme base
                $firstImageName = pathinfo($_FILES["images"]["name"][0], PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $firstImageName);
                
                // Générer le fichier de sortie
                $outputFile = $tmpDir . $safe_filename . ".pdf";
                
                // Exécuter la conversion
                $result_ok = convert_png_to_pdf($uploadedFiles, $outputFile, $format, $orientation);
                
                if ($result_ok && file_exists($outputFile)) {
                    $success = true;
                    $result = basename($outputFile);
                    $download_url = "tmp/" . basename($outputFile);
                    
                    // Nettoyer les fichiers temporaires uploadés
                    foreach ($uploadedFiles as $file) {
                        if (file_exists($file)) {
                            unlink($file);
                        }
                    }
                } else {
                    $errors[] = "Erreur lors de la génération du PDF.";
                }
            } else if (empty($errors)) {
                $errors[] = "Aucun fichier valide n'a été uploadé.";
            }
        }
    } catch (Exception $e) {
        error_log("Erreur détaillée dans Action : " . $e->getMessage());
        error_log("Trace complète : " . $e->getTraceAsString());
        $errors[] = "Erreur lors du traitement : " . $e->getMessage();
    }
    
    return template("../view/png_to_pdf.html.php", array(
        'errors' => $errors,
        'success' => $success,
        'result' => $result,
        'download_url' => $download_url
    ));
}

?>
