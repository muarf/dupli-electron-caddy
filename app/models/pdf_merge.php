<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/binary_utilities.php');

/**
 * Fusionne plusieurs PDF en un seul
 */
function merge_pdfs($pdf_files, $output_file) {
    try {
        // Vérifier que tous les fichiers existent
        foreach ($pdf_files as $file) {
            if (!file_exists($file)) {
                throw new Exception("Le fichier PDF n'existe pas : " . $file);
            }
        }
        
        // Créer le dossier de sortie s'il n'existe pas
        $output_dir = dirname($output_file);
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }

        // Préparer la commande Ghostscript
        $files_escaped = array_map('escapeshellarg', $pdf_files);
        $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($output_file) . " " . implode(' ', $files_escaped);
        
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            throw new Exception("Erreur lors de la fusion avec Ghostscript. Code: " . $gs_result['error'] . " Output: " . $gs_result['output']);
        }
        
        if (!file_exists($output_file) || filesize($output_file) === 0) {
            throw new Exception("Le fichier fusionné n'a pas été généré correctement.");
        }
        
        return $output_file;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la fusion PDF : " . $e->getMessage());
        throw $e;
    }
}

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result_file = '';
    $download_url = '';
    
    try {
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfs"])) {
            
            $files = $_FILES["pdfs"];
            $pdf_paths = array();
            
            // Récupérer l'ordre des fichiers si spécifié
            $order = isset($_POST['file_order']) ? explode(',', $_POST['file_order']) : array();
            
            // Organiser les fichiers par index original
            $uploaded_files = array();
            $num_files = count($files['name']);
            
            if ($num_files < 2) {
                $errors[] = "Veuillez sélectionner au moins deux fichiers pour la fusion.";
            } else {
                // Créer le répertoire temporaire
                $baseTmpDir = getTmpDir();
                $tmpDir = $baseTmpDir . DIRECTORY_SEPARATOR . 'duplicator_pdf_merge' . DIRECTORY_SEPARATOR;
                if (!is_dir($tmpDir)) {
                    if (!mkdir($tmpDir, 0777, true)) {
                        throw new Exception("Impossible de créer le répertoire temporaire : " . $tmpDir);
                    }
                }
                
                $timestamp = date('YmdHis');
                $session_id = uniqid();
                $session_tmp_dir = $tmpDir . $session_id . DIRECTORY_SEPARATOR;
                mkdir($session_tmp_dir, 0777, true);
                
                for ($i = 0; $i < $num_files; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        // Vérifier le type MIME
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $files["tmp_name"][$i]);
                        finfo_close($finfo);
                        
                        if ($mimeType !== 'application/pdf') {
                            $errors[] = "Le fichier " . $files['name'][$i] . " n'est pas un PDF valide.";
                            continue;
                        }
                        
                        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($files['name'][$i], PATHINFO_FILENAME)) . '.pdf';
                        $dest = $session_tmp_dir . $i . '_' . $safe_name;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                            $uploaded_files[$i] = $dest;
                        }
                    }
                }
                
                if (empty($errors)) {
                    // Trier les fichiers selon l'ordre spécifié (ou par index si non spécifié)
                    $sorted_paths = array();
                    if (!empty($order)) {
                        foreach ($order as $idx) {
                            if (isset($uploaded_files[$idx])) {
                                $sorted_paths[] = $uploaded_files[$idx];
                            }
                        }
                    } else {
                        ksort($uploaded_files);
                        $sorted_paths = array_values($uploaded_files);
                    }
                    
                    if (count($sorted_paths) < 2) {
                        $errors[] = "Au moins deux fichiers valides sont requis pour la fusion.";
                    } else {
                        $output_filename = "merged_" . $timestamp . ".pdf";
                        $output_path = $session_tmp_dir . $output_filename;
                        
                        $result = merge_pdfs($sorted_paths, $output_path);
                        
                        if (file_exists($result)) {
                            $success = true;
                            $result_file = $output_filename;
                            $download_url = "?download_merged&file=" . urlencode($output_filename) . "&session=" . urlencode($session_id);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur dans Action pdf_merge : " . $e->getMessage());
        $errors[] = "Erreur lors du traitement : " . $e->getMessage();
    }
    
    return template("../view/pdf_merge.html.php", array(
        'errors' => $errors,
        'success' => $success,
        'result_file' => $result_file,
        'download_url' => $download_url
    ));
}
}
?>
