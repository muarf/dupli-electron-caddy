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
    error_log("[PDF_MERGE] Appel de Action(), Méthode: " . ($_SERVER["REQUEST_METHOD"] ?? 'UNKNOWN'));
    $errors = array();
    $success = false;
    $result_file = '';
    $download_url = '';
    $preloaded_data = null;
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib']) && !empty($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $preloaded_data = $libManager->getFile($_GET['from_lib']);
    }

    try {
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            
            $uploaded_files_data = $_FILES["pdfs"] ?? null;
            $file_structure_json = $_POST['file_structure'] ?? '[]';
            $file_structure = json_decode($file_structure_json, true);
            
            error_log("[PDF_MERGE] Fichiers reçus: " . (is_array($uploaded_files_data['name'] ?? null) ? count($uploaded_files_data['name']) : 0));
            error_log("[PDF_MERGE] Structure reçue: " . $file_structure_json);
            
            $final_pdf_paths = array();
            
            // Créer le répertoire temporaire pour la session
            $baseTmpDir = getTmpDir();
            $tmpDir = $baseTmpDir . DIRECTORY_SEPARATOR . 'duplicator_pdf_merge' . DIRECTORY_SEPARATOR;
            $timestamp = date('YmdHis');
            $session_id = uniqid();
            $session_tmp_dir = $tmpDir . $session_id . DIRECTORY_SEPARATOR;
            if (!is_dir($session_tmp_dir)) {
                mkdir($session_tmp_dir, 0777, true);
            }

            // 1. Sauvegarder les fichiers uploadés d'abord pour avoir leurs chemins
            $uploaded_paths = array();
            if ($uploaded_files_data) {
                $num_uploads = count($uploaded_files_data['name']);
                for ($i = 0; $i < $num_uploads; $i++) {
                    if ($uploaded_files_data['error'][$i] === UPLOAD_ERR_OK) {
                        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($uploaded_files_data['name'][$i], PATHINFO_FILENAME)) . '.pdf';
                        $dest = $session_tmp_dir . 'upload_' . $i . '_' . $safe_name;
                        if (move_uploaded_file($uploaded_files_data['tmp_name'][$i], $dest)) {
                            $uploaded_paths[$i] = $dest;
                            error_log("[PDF_MERGE] Upload OK: " . $dest);
                        } else {
                            error_log("[PDF_MERGE] Upload FAILED: " . $uploaded_files_data['name'][$i] . " (Code: " . $uploaded_files_data['error'][$i] . ")");
                        }
                    }
                }
            }

            // 2. Construire la liste finale selon la structure ordonnée
            require_once __DIR__ . '/BibliothequeManager.php';
            $libManager = new BibliothequeManager();

            foreach ($file_structure as $item) {
                if ($item['type'] === 'lib') {
                    $libFile = $libManager->getFile($item['id']);
                    if ($libFile && file_exists($libFile['filepath'])) {
                        $final_pdf_paths[] = $libFile['filepath'];
                    }
                } elseif ($item['type'] === 'upload') {
                    $idx = $item['upload_index'];
                    if (isset($uploaded_paths[$idx])) {
                        $final_pdf_paths[] = $uploaded_paths[$idx];
                    }
                }
            }

            // Si aucune structure n'est passée (vieux mode), on prend juste les uploads
            if (empty($file_structure) && !empty($uploaded_paths)) {
                ksort($uploaded_paths);
                $final_pdf_paths = array_values($uploaded_paths);
            }

            if (count($final_pdf_paths) < 2) {
                $errors[] = "Veuillez sélectionner au moins deux fichiers pour la fusion.";
            } else {
                $output_filename = "merged_" . $timestamp . ".pdf";
                $output_path = $session_tmp_dir . $output_filename;
                
                $result = merge_pdfs($final_pdf_paths, $output_path);
                
                if (file_exists($result)) {
                    $success = true;
                    $result_file = $output_filename;
                    $download_url = "?download_merged&file=" . urlencode($output_filename) . "&session=" . urlencode($session_id);
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
        'download_url' => $download_url,
        'preloaded_data' => $preloaded_data
    ));
    }
}
?>
