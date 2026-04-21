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
            if (!isset($_FILES["file"])) {
                throw new Exception("Aucun fichier n'a été uploadé.");
            }

            if ($_FILES["file"]["error"] != UPLOAD_ERR_OK) {
                throw new Exception("Erreur d'upload code: " . $_FILES["file"]["error"]);
            }

            $target_format = $_POST['target_format'] ?? 'A4';
            $mode = $_POST['mode'] ?? 'fit';
            $orientation_pref = $_POST['orientation'] ?? 'auto';
            $alignment = $_POST['alignment'] ?? 'centered';

            $formats = [
                'A4' => ['w' => 210, 'h' => 297],
                'A3' => ['w' => 297, 'h' => 420]
            ];
            
            $target_w = $formats[$target_format]['w'];
            $target_h = $formats[$target_format]['h'];

            $tmpBase = getTmpDir() . DIRECTORY_SEPARATOR . 'duplicator_resizer' . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpBase)) mkdir($tmpBase, 0777, true);
            
            $session_id = session_id() ?: 'no_session';
            $tmpDir = $tmpBase . $session_id . DIRECTORY_SEPARATOR;
            if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

            $filename = $_FILES["file"]["name"];
            $tmpPath = $_FILES["file"]["tmp_name"];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $is_pdf = ($extension === 'pdf');

            $outputFilename = pathinfo($filename, PATHINFO_FILENAME) . "_resized_" . $target_format . ".pdf";
            $outputPath = $tmpDir . $outputFilename;

            $pdf = new PDF();
            $pdf->SetCreator('Duplicator');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0);

            if ($is_pdf) {
                $pageCount = $pdf->setSourceFile($tmpPath);
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
                $size = @getimagesize($tmpPath);
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
                $pdf->Image($tmpPath, $posX, $posY, $finalW, $finalH);
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

    return template("../view/resizer.html.php", array());
}
