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
?>
