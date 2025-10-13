<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/unimpose_logic.php');

function unimpose_booklet($input_file, $output_file) {
    /**Transforme un livret en PDF page par page - avec nettoyage Ghostscript forcé*/
    
    // Vérifier d'abord que le fichier existe et est lisible
    if (!file_exists($input_file) || !is_readable($input_file)) {
        throw new Exception("Le fichier PDF n'existe pas ou n'est pas lisible.");
    }
    
    // FORCER le nettoyage Ghostscript dans tous les cas
    $timestamp = date('YmdHis');
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
    
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
                // Créer le dossier tmp s'il n'existe pas
                $tmpDir = __DIR__ . '/../public/tmp/';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0777, true);
                }
                
                // Sauvegarder le fichier uploadé
                $timestamp = date('YmdHis');
                $uploadFile = $tmpDir . "unimpose_upload_" . $timestamp . ".pdf";
                
                if (move_uploaded_file($_FILES["pdf"]["tmp_name"], $uploadFile)) {
                    // Générer le fichier de sortie avec le nom original + _unimposed
                    $originalName = pathinfo($_FILES["pdf"]["name"], PATHINFO_FILENAME);
                    $outputFile = $tmpDir . $originalName . '_unimposed.pdf';
                    
                    // Exécuter la désimposition
                    $resultFile = unimpose_booklet($uploadFile, $outputFile);
                    
                    if (file_exists($resultFile)) {
                        $success = true;
                        $result = basename($resultFile);
                        $download_url = "tmp/" . basename($resultFile);
                        
                        // Nettoyer le fichier d'upload temporaire
                        unlink($uploadFile);
                    } else {
                        $errors[] = "Erreur lors de la génération du PDF désimposé.";
                    }
                } else {
                    $errors[] = "Erreur lors de l'upload du fichier.";
                }
            }
        }
    } catch (Exception $e) {
            // Afficher l'erreur détaillée pour debug
            error_log("Erreur détaillée dans Action : " . $e->getMessage());
            error_log("Trace complète : " . $e->getTraceAsString());
            
            $errors[] = "Erreur lors du traitement : " . $e->getMessage();
        }
    
    // Version simplifiée avec formulaire d'upload
    $html = '<div class="container">';
    $html .= '<div class="row"><div class="col-md-8 col-md-offset-2">';
    
    // En-tête
    $html .= '<div class="page-header text-center" style="background: linear-gradient(135deg, #ffb3ba 0%, #ffdfba 100%); padding: 30px; border-radius: 10px; margin-bottom: 30px;">';
    $html .= '<h1 style="color: #333; margin: 0;"><i class="fa fa-undo" style="margin-right: 15px;"></i>Désimposer un PDF</h1>';
    $html .= '<p class="lead" style="color: #666; margin: 10px 0 0 0;">Transforme un livret imposé en pages normales</p>';
    $html .= '</div>';
    
    // Messages d'erreur
    if (!empty($errors)) {
        $html .= '<div class="alert alert-danger">';
        $html .= '<h4><i class="fa fa-exclamation-triangle"></i> Erreurs détectées :</h4><ul class="mb-0">';
        foreach ($errors as $error) {
            $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul></div>';
    }
    
    // Formulaire d'upload avec drag & drop
    $html .= '<div class="panel panel-default"><div class="panel-body">';
    $html .= '<form method="POST" enctype="multipart/form-data" id="unimposeForm">';
    $html .= '<div id="dropZone" style="border: 3px dashed #ffb3ba; border-radius: 15px; padding: 40px; text-align: center; background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%); transition: all 0.3s ease; cursor: pointer;">';
    $html .= '<div style="font-size: 48px; color: #ffb3ba; margin-bottom: 20px;"><i class="fa fa-file-pdf-o"></i></div>';
    $html .= '<h3 style="color: #333; margin-bottom: 10px;">Glissez votre PDF ici</h3>';
    $html .= '<p style="color: #666; margin-bottom: 20px;">ou cliquez pour sélectionner un fichier</p>';
    $html .= '<input type="file" name="pdf" id="pdfInput" accept=".pdf" required style="display: none;">';
    $html .= '<button type="button" id="selectFileBtn" class="btn btn-lg" style="background: #ffb3ba; border: none; color: white; padding: 12px 30px; border-radius: 25px; margin-bottom: 20px;">';
    $html .= '<i class="fa fa-upload"></i> Sélectionner un PDF</button>';
    $html .= '<div id="fileInfo" style="display: none; margin-top: 20px;">';
    $html .= '<div class="alert alert-info"><i class="fa fa-file-pdf-o"></i> <span id="fileName"></span></div>';
    $html .= '<button type="submit" class="btn btn-success btn-lg" style="padding: 12px 30px; border-radius: 25px;">';
    $html .= '<i class="fa fa-magic"></i> Désimposer le PDF</button>';
    $html .= '<button type="button" id="resetBtn" class="btn btn-default btn-lg" style="margin-left: 10px; padding: 12px 30px; border-radius: 25px;">';
    $html .= '<i class="fa fa-times"></i> Annuler</button>';
    $html .= '</div>';
    $html .= '</div></form></div></div>';
    
    // JavaScript simplifié pour éviter les conflits
    $html .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("unimposeForm");
        const pdfInput = document.getElementById("pdfInput");
        const selectFileBtn = document.getElementById("selectFileBtn");
        const fileInfo = document.getElementById("fileInfo");
        const fileName = document.getElementById("fileName");
        const resetBtn = document.getElementById("resetBtn");
        
        // Clic sur le bouton
        selectFileBtn.addEventListener("click", function(e) {
            e.preventDefault();
            pdfInput.click();
        });
        
        // Sélection de fichier
        pdfInput.addEventListener("change", function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (file.type !== "application/pdf") {
                    alert("Veuillez sélectionner un fichier PDF valide.");
                    this.value = "";
                    return;
                }
                fileName.textContent = file.name + " (" + (file.size / 1024 / 1024).toFixed(2) + " MB)";
                fileInfo.style.display = "block";
            }
        });
        
        // Reset
        resetBtn.addEventListener("click", function(e) {
            e.preventDefault();
            pdfInput.value = "";
            fileInfo.style.display = "none";
        });
        
        // Soumission du formulaire - protection simple
        form.addEventListener("submit", function(e) {
            const submitBtn = this.querySelector("button[type=submit]");
            if (submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = "Traitement en cours...";
        });
    });
    </script>';
    
    // Informations
    $html .= '<div class="panel panel-info"><div class="panel-heading">';
    $html .= '<h3 class="panel-title"><i class="fa fa-info-circle"></i> Comment ça marche ?</h3>';
    $html .= '</div><div class="panel-body">';
    $html .= '<p>Cette fonction permet de transformer un PDF imposé (livret) en pages normales :</p>';
    $html .= '<ul><li><strong>Pages A3 imposées</strong> → <strong>Pages A4 normales</strong></li>';
    $html .= '<li><strong>Ordre de livret</strong> → <strong>Ordre séquentiel</strong></li>';
    $html .= '<li><strong>2 pages par feuille</strong> → <strong>1 page par feuille</strong></li></ul>';
    $html .= '</div></div>';
    
    // Résultat
    if ($success && !empty($result)) {
        $html .= '<div class="panel panel-success"><div class="panel-heading">';
        $html .= '<h3 class="panel-title"><i class="fa fa-check-circle"></i> Désimposition réussie !</h3>';
        $html .= '</div><div class="panel-body text-center">';
        $html .= '<h4 style="color: #333; margin-bottom: 20px;">Votre PDF a été désimposé avec succès</h4>';
        $html .= '<a href="' . htmlspecialchars($download_url) . '" class="btn btn-success btn-lg" download>';
        $html .= '<i class="fa fa-download"></i> Télécharger le PDF désimposé</a>';
        $html .= '</div></div>';
    }
    
    $html .= '</div></div></div>';
    return $html;
}

?>