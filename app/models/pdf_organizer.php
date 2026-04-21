<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/binary_utilities.php');
require_once(__DIR__ . '/../controler/functions/paths.php');

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Modèle pour l'organisateur de pages PDF
 */

if (!function_exists('Action')) {
    function Action($conf) {
        $errors = array();
        $success = false;
        $download_url = '';
        
        // Gérer les requêtes AJAX
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            header('Content-Type: application/json');
            
            $action = $_POST['action'] ?? '';
            
            try {
                if ($action === 'upload') {
                    echo json_encode(handleUpload());
                    exit;
                } elseif ($action === 'generate') {
                    echo json_encode(handleGenerate());
                    exit;
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        // Affichage standard de la page
        return template("../view/pdf_organizer.html.php", array(
            'errors' => $errors,
            'success' => $success,
            'download_url' => $download_url
        ));
    }
}

/**
 * Gère l'upload de fichiers PDF et la génération de vignettes
 */
function handleUpload() {
    if (!isset($_FILES['pdfs'])) {
        throw new Exception("Aucun fichier reçu.");
    }

    $files = $_FILES['pdfs'];
    $session_id = $_POST['session_id'] ?? uniqid('org_');
    $tmp_base = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $session_id . DIRECTORY_SEPARATOR;
    
    $originals_dir = $tmp_base . 'originals' . DIRECTORY_SEPARATOR;
    $thumbs_dir = $tmp_base . 'thumbs' . DIRECTORY_SEPARATOR;

    if (!is_dir($originals_dir)) mkdir($originals_dir, 0777, true);
    if (!is_dir($thumbs_dir)) mkdir($thumbs_dir, 0777, true);

    $results = [];
    $num_files = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $num_files; $i++) {
        $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

        if ($error !== UPLOAD_ERR_OK) continue;

        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        $file_id = uniqid('file_');
        $dest_path = $originals_dir . $file_id . '.pdf';

        if (move_uploaded_file($tmp_name, $dest_path)) {
            // Extraire les vignettes via Ghostscript
            // On utilise une résolution faible (72 DPI) pour la rapidité
            $gs_path = get_ghostscript_path();
            $thumb_pattern = $thumbs_dir . $file_id . '_page_%03d.png';
            
            $cmd = escapeshellarg($gs_path) . " -dNOPAUSE -dBATCH -sDEVICE=png16m -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($thumb_pattern) . " " . escapeshellarg($dest_path) . " 2>&1";
            
            exec($cmd, $output, $return_var);
            
            $pages = glob($thumbs_dir . $file_id . '_page_*.png');
            sort($pages);

            foreach ($pages as $idx => $page_path) {
                $page_num = $idx + 1;
                $results[] = [
                    'file_id' => $file_id,
                    'file_name' => $name,
                    'page_num' => $page_num,
                    'thumb_url' => "?organizer_thumb&file=" . urlencode(basename($page_path)) . "&sess=" . urlencode($session_id)
                ];
            }
        }
    }

    return ['success' => true, 'pages' => $results, 'session_id' => $session_id];
}

/**
 * Gère la génération du PDF final basé sur l'ordre et les rotations
 */
function handleGenerate() {
    $session_id = $_POST['session_id'] ?? '';
    $structure = json_decode($_POST['structure'] ?? '[]', true);

    if (empty($session_id) || empty($structure)) {
        throw new Exception("Données de session ou structure manquantes.");
    }

    $tmp_base = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $session_id . DIRECTORY_SEPARATOR;
    $originals_dir = $tmp_base . 'originals' . DIRECTORY_SEPARATOR;
    
    // Créer le PDF final avec TCPDF/FPDI
    $pdf = new Fpdi();
    $pdf->SetAutoPageBreak(false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    foreach ($structure as $item) {
        if ($item['type'] === 'blank') {
            // Ajouter une page blanche (format A4 par défaut si rien d'autre n'est trouvé)
            $pdf->AddPage();
        } else {
            $file_path = $originals_dir . $item['file_id'] . '.pdf';
            if (!file_exists($file_path)) continue;

            $pdf->setSourceFile($file_path);
            $tplIdx = $pdf->importPage($item['page_num']);
            $size = $pdf->getTemplateSize($tplIdx);
            
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            
            // Gérer la rotation demandée par l'utilisateur
            $rotation = intval($item['rotation'] ?? 0);
            
            // Si on pivote de 90 ou 270, on inverse l'orientation
            if ($rotation == 90 || $rotation == 270) {
                $orientation = ($orientation === 'L') ? 'P' : 'L';
            }

            $pdf->AddPage($orientation, array($size['width'], $size['height']));
            
            // Appliquer la rotation
            // TCPDF::useTemplate(tplidx, x, y, w, h, adjustPageSize)
            // Note: FPDI handle rotation via third param of useTemplate in some versions, 
            // but TCPDF + FPDI often requires manual rotation via Rotate()
            
            if ($rotation != 0) {
                // Calculer le centre pour la rotation
                $cx = $pdf->GetPageWidth() / 2;
                $cy = $pdf->GetPageHeight() / 2;
                $pdf->StartTransform();
                $pdf->Rotate($rotation, $pdf->GetX(), $pdf->GetY());
                // Ajuster la position après rotation si nécessaire
                // Pour simplifier, on utilise la méthode d'insertion directe
                // mais attention aux offsets. FPDI supporte mieux :
            }
            
            $pdf->useTemplate($tplIdx, 0, 0, $size['width'], $size['height'], true);
            
            if ($rotation != 0) {
                $pdf->StopTransform();
            }
        }
    }

    $output_filename = "organized_" . date('YmdHis') . ".pdf";
    $output_path = $tmp_base . $output_filename;
    $pdf->Output($output_path, 'F');

    return [
        'success' => true, 
        'download_url' => "?download_organized&file=" . urlencode($output_filename) . "&sess=" . urlencode($session_id)
    ];
}
