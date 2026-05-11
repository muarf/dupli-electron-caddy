<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/paths.php');
require_once(__DIR__ . '/../controler/functions/binary_utilities.php');

use setasign\Fpdi\TcpdfFpdi as PDF;

/**
 * Logique de redimensionnement de documents vers A4/A3
 */
function Action($conf) {
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        header('Content-Type: application/json');
        
        $errors = array();
        $success = false;
        $result = array();

        try {
            $pdfFile = null;
            $originalName = null;
            $is_pdf = false;

            // Cas 1 : Fichier bibliothèque
            if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
                require_once __DIR__ . '/BibliothequeManager.php';
                $libManager = new BibliothequeManager();
                $file = $libManager->getFile($_POST['lib_file_id']);
                if ($file && file_exists($file['filepath'])) {
                    $pdfFile = $file['filepath'];
                    $originalName = $file['filename'];
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $is_pdf = ($extension === 'pdf');
                } else {
                    throw new Exception("Fichier de bibliothèque introuvable.");
                }
            } 
            // Cas 2 : Fichier uploadé
            elseif (isset($_FILES["file"]) && $_FILES["file"]["error"] == UPLOAD_ERR_OK) {
                $pdfFile = $_FILES["file"]["tmp_name"];
                $originalName = $_FILES["file"]["name"];
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $is_pdf = ($extension === 'pdf');
            } else {
                throw new Exception("Aucun fichier n'a été sélectionné.");
            }

            $target_format = $_POST['target_format'] ?? 'A4';
            $mode = $_POST['mode'] ?? 'fit';
            $orientation_pref = $_POST['orientation'] ?? 'auto';
            $alignment = $_POST['alignment'] ?? 'centered';

            $formats = [
                'A4' => ['w' => 210, 'h' => 297],
                'A5' => ['w' => 148, 'h' => 210],
                'A3' => ['w' => 297, 'h' => 420]
            ];
            
            $target_w = $formats[$target_format]['w'];
            $target_h = $formats[$target_format]['h'];

            $tmpBase = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_resizer' . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpBase)) mkdir($tmpBase, 0777, true);
            
            $session_id = session_id() ?: 'no_session';
            $tmpDir = $tmpBase . $session_id . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

            $outputFilename = pathinfo($originalName, PATHINFO_FILENAME) . "_resized_" . $target_format . ".pdf";
            $outputPath = $tmpDir . $outputFilename;

            $pdf = new PDF();
            $pdf->SetCreator('Duplicator');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            if ($is_pdf) {
                $pageCount = $pdf->setSourceFile($pdfFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $tplIdx = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($tplIdx);
                    $src_w = $size['width'];
                    $src_h = $size['height'];

                    $final_target_w = $target_w;
                    $final_target_h = $target_h;
                    $orientation = 'P';
                    if ($orientation_pref === 'auto') {
                        if ($src_w > $src_h) {
                            $orientation = 'L';
                            $final_target_w = $target_h;
                            $final_target_h = $target_w;
                        }
                    } elseif ($orientation_pref === 'landscape') {
                        $orientation = 'L';
                        $final_target_w = $target_h;
                        $final_target_h = $target_w;
                    }

                    $pdf->AddPage($orientation, array($final_target_w, $final_target_h));

                    $scale = 1;
                    $posX = 0;
                    $posY = 0;

                    if ($mode === 'fit' || $mode === 'fill') {
                        $ratioW = $final_target_w / $src_w;
                        $ratioH = $final_target_h / $src_h;
                        if ($mode === 'fit') {
                            $scale = min($ratioW, $ratioH);
                        } else {
                            $scale = max($ratioW, $ratioH);
                        }
                    }

                    $finalW = $src_w * $scale;
                    $finalH = $src_h * $scale;

                    if ($alignment === 'centered') {
                        $posX = ($final_target_w - $finalW) / 2;
                        $posY = ($final_target_h - $finalH) / 2;
                    }

                    $pdf->useTemplate($tplIdx, $posX, $posY, $finalW, $finalH);
                }
            } else {
                $size = @getimagesize($pdfFile);
                if (!$size) throw new Exception("Impossible de lire les dimensions de l'image.");
                $src_w_px = $size[0];
                $src_h_px = $size[1];
                $src_w = $src_w_px * 25.4 / 300;
                $src_h = $src_h_px * 25.4 / 300;

                $final_target_w = $target_w;
                $final_target_h = $target_h;
                $orientation = 'P';
                if ($orientation_pref === 'auto') {
                    if ($src_w > $src_h) {
                        $orientation = 'L';
                        $final_target_w = $target_h;
                        $final_target_h = $target_w;
                    }
                } elseif ($orientation_pref === 'landscape') {
                    $orientation = 'L';
                    $final_target_w = $target_h;
                    $final_target_h = $target_w;
                }

                $pdf->AddPage($orientation, array($final_target_w, $final_target_h));
                $scale = 1; $posX = 0; $posY = 0;
                if ($mode === 'fit' || $mode === 'fill') {
                    $ratioW = $final_target_w / $src_w;
                    $ratioH = $final_target_h / $src_h;
                    if ($mode === 'fit') $scale = min($ratioW, $ratioH);
                    else $scale = max($ratioW, $ratioH);
                }
                $finalW = $src_w * $scale; $finalH = $src_h * $scale;
                if ($alignment === 'centered') {
                    $posX = ($final_target_w - $finalW) / 2;
                    $posY = ($final_target_h - $finalH) / 2;
                }
                $pdf->Image($pdfFile, $posX, $posY, $finalW, $finalH);
            }

            $pdf->Output($outputPath, 'F');
            $success = true;
            $result['download_url'] = "?download_resized&file=" . urlencode($outputFilename) . "&session=" . $session_id;

        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        echo json_encode(array(
            'success' => $success,
            'errors' => $errors,
            'result' => $result
        ));
        exit;
    }

    $preloaded_data = null;
    if (isset($_GET['from_lib']) && !empty($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $file = $libManager->getFile($_GET['from_lib']);
        if ($file) {
            $preloaded_data = $file;
        }
    }

    return template("../view/resizer.html.php", array(
        'preloaded_data' => $preloaded_data
    ));
}
