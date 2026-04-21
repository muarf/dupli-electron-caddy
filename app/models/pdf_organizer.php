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
        
        $action = $_POST['action'] ?? '';
        
        // Gérer les requêtes AJAX (POST avec action)
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && !empty($action)) {
            header('Content-Type: application/json');
            try {
                if ($action === 'upload') {
                    echo json_encode(handleUpload());
                } elseif ($action === 'generate') {
                    echo json_encode(handleGenerate());
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
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
            // Extraire les vignettes via Imagick (cross-platform: Linux + Windows)
            try {
                $imagick = new Imagick();
                $imagick->setResolution(72, 72);
                $imagick->readImage($dest_path);
                
                $num_pages = $imagick->getNumberImages();
                for ($i = 0; $i < $num_pages; $i++) {
                    $page = new Imagick();
                    $page->setResolution(72, 72);
                    $page->readImage($dest_path . '[' . $i . ']');
                    $page->setImageFormat('png');
                    $page->writeImage($thumbs_dir . $file_id . '_page_' . sprintf('%03d', $i + 1) . '.png');
                    $page->clear();
                }
                $imagick->clear();
            } catch (Exception $e) {
                // Ignorer les erreurs de thumbnail
            }
            
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
    $log_path = __DIR__ . '/../api/debug_log.txt';
    file_put_contents($log_path, "[" . date('Y-m-d H:i:s') . "] [pdf_organizer] handleGenerate called\n", FILE_APPEND);
    
    $session_id = $_POST['session_id'] ?? '';
    $structure_json = $_POST['structure'] ?? '[]';
    $structure = json_decode($structure_json, true);
    
    file_put_contents($log_path, "[" . date('Y-m-d H:i:s') . "] [pdf_organizer] Generate: session=$session_id, structure=$structure_json\n", FILE_APPEND);

    if (empty($session_id) || empty($structure)) {
        throw new Exception("Données de session ou structure manquantes.");
    }

    $tmp_base = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_organizer' . DIRECTORY_SEPARATOR . $session_id . DIRECTORY_SEPARATOR;
    $originals_dir = $tmp_base . 'originals' . DIRECTORY_SEPARATOR;
    
    // Log pour débuguer
    file_put_contents($log_path, "[" . date('Y-m-d H:i:s') . "] [pdf_organizer] Generate started, session: $session_id\n", FILE_APPEND);
    
    // Fallback: générer le PDF avec Imagick + Ghostscript si TCPDF échoue
    try {
        // Test TCPDF
        if (!defined('CURLOPT_CONNECTTIMEOUT')) {
            define('CURLOPT_CONNECTTIMEOUT', 0);
        }
    } catch (Exception $e) {
        // Ignore
    }
    
    // Get first page size for blank pages (PDF uses points - convert to pixels at 150 DPI)
    $first_pdf_path = '';
    foreach ($structure as $item) {
        if ($item['type'] !== 'blank') {
            $first_pdf_path = $originals_dir . $item['file_id'] . '.pdf';
            if (file_exists($first_pdf_path)) break;
            $first_pdf_path = '';
        }
    }
    
    // Default A4 at 150 DPI: 595 points = 595 * 150/72 = 1239 pixels
    $blank_width = 1240;
    $blank_height = 1754;
    if ($first_pdf_path) {
        try {
            $tmp_im = new Imagick();
            $tmp_im->setResolution(150, 150);
            $tmp_im->readImage($first_pdf_path . '[0]');
            $blank_width = $tmp_im->getImageWidth();
            $blank_height = $tmp_im->getImageHeight();
            $tmp_im->clear();
        } catch (Exception $e) {}
    }
    
    $output_pdf = $tmp_base . 'output.pdf';
    $images = [];
    
    foreach ($structure as $item) {
        if ($item['type'] === 'blank') {
            // Page blanche with correct size
            $blank_path = $tmp_base . 'blank_' . uniqid() . '.png';
            $im = new Imagick();
            $im->setResolution(150, 150);
            $im->newImage($blank_width, $blank_height, 'white', 'png');
            $im->writeImage($blank_path);
            $im->clear();
            $images[] = $blank_path;
        } else {
            $file_path = $originals_dir . $item['file_id'] . '.pdf';
            if (!file_exists($file_path)) continue;
            
            try {
                $page = new Imagick();
                $page->setResolution(150, 150);
                $page->readImage($file_path . '[' . (intval($item['page_num'] ?? 1) - 1) . ']');
                
                $rotation = intval($item['rotation'] ?? 0);
                if ($rotation != 0) {
                    $page->rotateImage(new ImagickPixel('none'), $rotation);
                }
                
                $page->setImageFormat('png');
                $out_path = $tmp_base . 'page_' . uniqid() . '.png';
                $page->writeImage($out_path);
                $page->clear();
                $images[] = $out_path;
            } catch (Exception $e) {
                file_put_contents($log_path, "[" . date('Y-m-d H:i:s') . "] [pdf_organizer] Imagick error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    
    if (empty($images)) {
        throw new Exception("Aucune page à inclure");
    }
    
    // With Imagick: convert images to PDF
    $magick = new Imagick();
    foreach ($images as $img) {
        $magick->readImage($img);
    }
    $magick->setImageFormat('pdf');
    $magick->writeImages($output_pdf, true);
    $magick->clear();
    
    // Nettoyer les images temp
    foreach ($images as $img) {
        @unlink($img);
    }
    
    file_put_contents($log_path, "[" . date('Y-m-d H:i:s') . "] [pdf_organizer] PDF generated: $output_pdf\n", FILE_APPEND);
    
    if (!file_exists($output_pdf)) {
        throw new Exception("Erreur génération PDF");
    }
    
    return ['success' => true, 'download_url' => '?download_organized&session=' . urlencode($session_id)];
}
