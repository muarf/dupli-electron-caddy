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
        $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escape_shell_arg_with_percent($output_pattern) . " " . escapeshellarg($pdf_file);
        
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



?>
