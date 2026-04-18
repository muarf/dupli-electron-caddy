<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/utilities.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/imposition.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

/**
 * Génére un PDF temporaire complété de pages blanches afin d'obtenir
 * un nombre total de pages multiple de $multiple.
 *
 * @param string $pdfFilePath Chemin du PDF source
 * @param int    $multiple    Multiple souhaité
 * @return array{file:string,page_count:int,temp_file:?string}
 * @throws Exception
 */
if (!function_exists('padPdfToMultiple')) {
function padPdfToMultiple($pdfFilePath, $multiple) {
    $pdf = new TCPDI();
    $pageCount = $pdf->setSourceFile($pdfFilePath);

    if ($multiple <= 0) {
        throw new Exception("Le multiple doit être strictement positif.");
    }

    if ($pageCount % $multiple === 0) {
        return [
            'file' => $pdfFilePath,
            'page_count' => $pageCount,
            'temp_file' => null
        ];
    }

    $pagesToAdd = $multiple - ($pageCount % $multiple);

    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;

    $outputPath = $tmp_dir . 'padded_' . date('YmdHis') . '_' . uniqid() . '.pdf';

    $pdfPadded = new TCPDI();
    $pdfPadded->setPrintHeader(false);
    $pdfPadded->setPrintFooter(false);
    $pdfPadded->setSourceFile($pdfFilePath);

    $defaultWidth = 210;
    $defaultHeight = 297;
    $defaultOrientation = 'P';

    for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
        $templateId = $pdfPadded->importPage($pageNum);
        $size = $pdfPadded->getTemplateSize($templateId);
        if ($size && isset($size['width']) && isset($size['height'])) {
            $width = $size['width'];
            $height = $size['height'];
            $defaultWidth = $width;
            $defaultHeight = $height;
            $defaultOrientation = ($width > $height) ? 'L' : 'P';
        } else {
            $width = $defaultWidth;
            $height = $defaultHeight;
        }

        $orientation = ($width > $height) ? 'L' : 'P';
        $pdfPadded->AddPage($orientation, [$width, $height]);
        $pdfPadded->useTemplate($templateId, 0, 0, $width, $height);
    }

    for ($i = 0; $i < $pagesToAdd; $i++) {
        $pdfPadded->AddPage($defaultOrientation, [$defaultWidth, $defaultHeight]);
    }

    $pdfPadded->Output($outputPath, 'F');

    return [
        'file' => $outputPath,
        'page_count' => $pageCount + $pagesToAdd,
        'temp_file' => $outputPath
    ];
}
}
if (!function_exists('addPageNumber')) {
function addPageNumber($pdf, $page_num, $x, $y, $new_width, $new_height, $rotation) {
    // Désactiver l'ajout automatique de pages
    $pdf->setAutoPageBreak(false);
    
    // Ajouter le numéro de page en surbrillance (rouge sur fond jaune)
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(255, 0, 0); // Rouge
    $pdf->SetFillColor(255, 255, 0); // Jaune
    
    if ($rotation == 180) {
        $pdf->StartTransform();
        $pdf->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2)); // Rotation centrée
    }
    
    // Dessiner le fond jaune
    $pdf->Rect($x + 2, $y + 2, 20, 15, 'F');
    
    // Ajouter le numéro en rouge avec Cell (qui n'ajoutera pas de page grâce à setAutoPageBreak)
    $pdf->SetXY($x + 6, $y + 6);
    $pdf->Cell(15, 8, (string)$page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    
    if ($rotation == 180) {
        $pdf->StopTransform();
    }
}
}

if (!function_exists('Action')) {
function Action($conf)
{
    $array = array();
    $array['errors'] = array();
    $array['success'] = false;
    $array['result'] = '';
    $array['preview_url'] = '';
    $array['download_url'] = '';
    $array['page_count'] = 0;

    if (isset($GLOBALS['last_error']) && is_array($GLOBALS['last_error'])) {
        $lastError = $GLOBALS['last_error'];
        $errorSummary = $lastError['type'] . ' : ' . $lastError['message'];
        if (!empty($lastError['file'])) {
            $errorSummary .= ' (fichier ' . $lastError['file'];
            if (!empty($lastError['line'])) {
                $errorSummary .= ', ligne ' . $lastError['line'];
            }
            $errorSummary .= ')';
        }
        $array['errors'][] = $errorSummary;
        $array['last_error_details'] = $lastError;
    }

    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $file = $libManager->getFile($_GET['from_lib']);
        if ($file) {
            $array['from_lib_file'] = $file;
        }
    }

    // Traitement du formulaire (POST)
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
        $pdfFile = null;
        $originalFileName = null;
        
        // Cas 1 : Fichier bibliothèque
        if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
            require_once __DIR__ . '/BibliothequeManager.php';
            $libManager = new BibliothequeManager();
            $file = $libManager->getFile($_POST['lib_file_id']);
            if ($file && file_exists($file['filepath'])) {
                $pdfFile = $file['filepath'];
                $originalFileName = $file['filename'];
            } else {
                $array['errors'][] = "Erreur : Fichier de bibliothèque introuvable.";
                return template(__DIR__ . "/../view/imposition_livre.html.php", $array);
            }
        }
        // Cas 2 : Fichier uploadé
        elseif (isset($_FILES["pdf"]) && $_FILES["pdf"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $pdfFile = $_FILES["pdf"]["tmp_name"];
        $originalFileName = $_FILES["pdf"]["name"];
        
        if ($_FILES["pdf"]["error"] !== UPLOAD_ERR_OK) {
            $array['errors'][] = "Erreur d'upload : " . $_FILES["pdf"]["error"];
            return template(__DIR__ . "/../view/imposition_livre.html.php", $array);
            }
        }
        
        // Si on a un fichier à traiter
        if ($pdfFile) {
            // Extraire le nom sans extension
            $originalFileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
        
        if (!file_exists($pdfFile)) {
            $array['errors'][] = "Erreur : Fichier introuvable.";
            return template(__DIR__ . "/../view/imposition_livre.html.php", $array);
        }

        // Vérifier que le fichier est bien un PDF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $pdfFile);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            $array['errors'][] = "Erreur : Le fichier n'est pas un PDF valide (type détecté: $mimeType).";
            return template(__DIR__ . "/../view/imposition_livre.html.php", $array);
        }

        // Traitement principal avec gestion d'erreur globale et fallback Ghostscript
        $cleanedPdfFile = null;
        $paddedPdfFile = null;
        $usedGhostscript = false;
        $mainProcessingSuccess = false;
        
        try {
            // Essayer d'abord avec le PDF original
            $pdf = new TCPDI();
            $pageCount = $pdf->setSourceFile($pdfFile);
            $array['page_count'] = $pageCount;
            
            if ($pageCount <= 0) {
                throw new Exception("Impossible de lire le PDF ou PDF vide.");
            }

            // Récupérer les paramètres
            $n_up = intval($_POST['n_up'] ?? 2);
            $multiple = 2 * $n_up; // Pour cut & stack : 2 * n_up

            // Ajouter des pages blanches si nécessaire
            $paddingResult = padPdfToMultiple($pdfFile, $multiple);
            if ($paddingResult['temp_file'] !== null) {
                $paddedPdfFile = $paddingResult['temp_file'];
            }
            $pdfFile = $paddingResult['file'];
            $pageCount = $paddingResult['page_count'];
            $array['page_count'] = $pageCount;

            // Récupérer les options
            $previewMode = isset($_POST['preview']);
            $duplex = isset($_POST['duplex']);
            $scale = floatval($_POST['scale'] ?? 100);
            $target_width = floatval($_POST['target_width'] ?? 0);
            $target_height = floatval($_POST['target_height'] ?? 0);
            $gutter_x = floatval($_POST['gutter_x'] ?? 0);
            $gutter_y = floatval($_POST['gutter_y'] ?? 0);
            $gutter_strategy = $_POST['gutter_strategy'] ?? 'reduce'; // 'reduce' ou 'crop'
            $crop_marks = isset($_POST['crop_marks']);
            $crop_mark_len = floatval($_POST['crop_mark_len'] ?? 2);
            $crop_mark_width = floatval($_POST['crop_mark_width'] ?? 0.1);
            $resize_mode = $_POST['resize_mode'] ?? 'percent';
            $add_page_numbers_in_gutters = isset($_POST['add_page_numbers_in_gutters']);
            $gutterNumOffsetX = isset($_POST['gutter_num_offset_x']) ? floatval($_POST['gutter_num_offset_x']) : 0.0;
            $gutterNumOffsetY = isset($_POST['gutter_num_offset_y']) ? floatval($_POST['gutter_num_offset_y']) : -2.0;

            $tete_beche = isset($_POST['tete_beche']);
            $outputFormat = $_POST['output_format'] ?? 'A3'; // Format de sortie (A3 ou A4)

            // Si on est en mode dimension cible, on ignore l'échelle %
            if ($resize_mode === 'mm') {
                $scale = 0;
            } else {
                $target_width = 0;
                $target_height = 0;
            }

            // Préparer les settings pour Imposition
            $settings = [
                'n_up' => $n_up,
                'duplex' => $duplex,
                'scale' => $scale,
                'target_width' => $target_width,
                'target_height' => $target_height,
                'gutter_x' => $gutter_x,
                'gutter_y' => $gutter_y,
                'gutter_strategy' => $gutter_strategy,
                'crop_marks' => $crop_marks,
                'crop_mark_len' => $crop_mark_len,
                'crop_mark_width' => $crop_mark_width,
                'preview_mode' => $previewMode,
                'add_page_numbers_in_gutters' => $add_page_numbers_in_gutters,
                'gutter_num_offset_x' => $gutterNumOffsetX,
                'gutter_num_offset_y' => $gutterNumOffsetY,
                'tete_beche' => $tete_beche,
                'output_format' => $outputFormat,
                'addPageNumberCallback' => $previewMode ? function($pdf, $pageNo, $x, $y, $w, $h, $rotation) {
                    return addPageNumber($pdf, $pageNo, $x, $y, $w, $h, $rotation);
                } : null
            ];

            // Créer les fichiers de sortie
            $timestamp = date('YmdHis');
            $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;

            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);
            $final_filename = $safe_filename . '_imposed.pdf';
            $output_pdf_path_final = $tmp_dir . $final_filename;
            $preview_output_path = null;

            if ($previewMode) {
                $preview_filename = $safe_filename . '_preview.pdf';
                $preview_output_path = $tmp_dir . $preview_filename;
            }

            // Traiter l'imposition
            $imposition = new Imposition($pdfFile, $settings);
            $imposition->process($output_pdf_path_final, $preview_output_path);

            // Utiliser l'endpoint de téléchargement
            $array['download_url'] = '?download_pdf&file=' . $final_filename;
            
            if ($previewMode && $preview_output_path && file_exists($preview_output_path)) {
                $array['preview_url'] = '?view_pdf&file=' . $preview_filename . '&t=' . time();
            }
            
            $array['success'] = true;
            $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages.";
            
            // Nettoyer le fichier temporaire
            if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                @unlink($paddedPdfFile);
            }
            
            $mainProcessingSuccess = true;
            
        } catch (Exception $e) {
            // Première tentative échouée, essayer avec Ghostscript
            if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                @unlink($paddedPdfFile);
                $paddedPdfFile = null;
            }
            if (isset($mainProcessingSuccess) && $mainProcessingSuccess) {
                return $array;
            }
            
            try {
                error_log("BLOC DE FALLBACK EXÉCUTÉ - Première tentative échouée, nettoyage avec Ghostscript: " . $e->getMessage());
                
                // Créer un fichier temporaire nettoyé
                $timestamp = date('YmdHis');
                $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;
                
                $cleanedPdfFile = $tmp_dir . 'cleaned_' . $timestamp . '.pdf';
                
                // Nettoyer le PDF avec Ghostscript
                if (PHP_OS_FAMILY === 'Windows') {
                    $gs_command = __DIR__ . '/../../ghostscript/gswin64c.exe';
                    if (!file_exists($gs_command)) {
                        throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
                    }
                } else {
                    $gs_command = 'gs';
                }
                $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($pdfFile) . " 2>&1";
                $output = shell_exec($command);
                
                if (!file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
                    throw new Exception("Échec du nettoyage Ghostscript. Sortie: " . $output);
                }
                
                // Réessayer avec le PDF nettoyé
                $pdf = new TCPDI();
                $pageCount = $pdf->setSourceFile($cleanedPdfFile);
                $array['page_count'] = $pageCount;
                
                if ($pageCount <= 0) {
                    throw new Exception("Impossible de lire le PDF nettoyé ou PDF vide.");
                }
                
                $usedGhostscript = true;
                $pdfFile = $cleanedPdfFile;

                // Récupérer les paramètres
                $n_up = intval($_POST['n_up'] ?? 2);
                $multiple = 2 * $n_up;

                $paddingResult = padPdfToMultiple($pdfFile, $multiple);
                if ($paddingResult['temp_file'] !== null) {
                    $paddedPdfFile = $paddingResult['temp_file'];
                } else {
                    $paddedPdfFile = null;
                }
                $pdfFile = $paddingResult['file'];
                $pageCount = $paddingResult['page_count'];
                $array['page_count'] = $pageCount;

                // Récupérer les options
                $previewMode = isset($_POST['preview']);
                $duplex = isset($_POST['duplex']);
                $scale = floatval($_POST['scale'] ?? 100);
                $target_width = floatval($_POST['target_width'] ?? 0);
                $target_height = floatval($_POST['target_height'] ?? 0);
                $gutter_x = floatval($_POST['gutter_x'] ?? 0);
                $gutter_y = floatval($_POST['gutter_y'] ?? 0);
                $gutter_strategy = $_POST['gutter_strategy'] ?? 'reduce'; // 'reduce' ou 'crop'
                $crop_marks = isset($_POST['crop_marks']);
                $crop_mark_len = floatval($_POST['crop_mark_len'] ?? 2);
                $crop_mark_width = floatval($_POST['crop_mark_width'] ?? 0.1);
                $resize_mode = $_POST['resize_mode'] ?? 'percent';
                $add_page_numbers_in_gutters = isset($_POST['add_page_numbers_in_gutters']);
                $gutterNumOffsetX = isset($_POST['gutter_num_offset_x']) ? floatval($_POST['gutter_num_offset_x']) : 0.0;
                $gutterNumOffsetY = isset($_POST['gutter_num_offset_y']) ? floatval($_POST['gutter_num_offset_y']) : -2.0;

                $tete_beche = isset($_POST['tete_beche']);
                $outputFormat = $_POST['output_format'] ?? 'A3'; // Format de sortie (A3 ou A4)

                if ($resize_mode === 'mm') {
                    $scale = 0;
                } else {
                    $target_width = 0;
                    $target_height = 0;
                }

                // Préparer les settings
                $settings = [
                    'n_up' => $n_up,
                    'duplex' => $duplex,
                    'scale' => $scale,
                    'target_width' => $target_width,
                    'target_height' => $target_height,
                    'gutter_x' => $gutter_x,
                    'gutter_y' => $gutter_y,
                    'gutter_strategy' => $gutter_strategy,
                    'crop_marks' => $crop_marks,
                    'crop_mark_len' => $crop_mark_len,
                    'crop_mark_width' => $crop_mark_width,
                    'preview_mode' => $previewMode,
                    'add_page_numbers_in_gutters' => $add_page_numbers_in_gutters,
                    'gutter_num_offset_x' => $gutterNumOffsetX,
                    'gutter_num_offset_y' => $gutterNumOffsetY,
                    'tete_beche' => $tete_beche,
                    'output_format' => $outputFormat,
                    'addPageNumberCallback' => $previewMode ? function($pdf, $pageNo, $x, $y, $w, $h, $rotation) {
                        return addPageNumber($pdf, $pageNo, $x, $y, $w, $h, $rotation);
                    } : null
                ];

                // Créer les fichiers de sortie
                $timestamp = date('YmdHis');
                $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;

                $originalFileName = isset($originalFileName) ? $originalFileName : "document.pdf";
                $originalFileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);
                $final_filename = $safe_filename . '_imposed.pdf';
                $output_pdf_path_final = $tmp_dir . $final_filename;
                $preview_output_path = null;

                if ($previewMode) {
                    $preview_filename = $safe_filename . '_preview.pdf';
                    $preview_output_path = $tmp_dir . $preview_filename;
                }

                // Traiter l'imposition
                $imposition = new Imposition($pdfFile, $settings);
                $imposition->process($output_pdf_path_final, $preview_output_path);

                // Utiliser l'endpoint de téléchargement
                $array['download_url'] = '?download_pdf&file=' . $final_filename;
                
                if ($previewMode && $preview_output_path && file_exists($preview_output_path)) {
                    $array['preview_url'] = '?view_pdf&file=' . $preview_filename . '&t=' . time();
                }
                
                $array['success'] = true;
                $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages. (Nettoyé avec Ghostscript)";
                
                // Nettoyer les fichiers temporaires
                if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                    @unlink($paddedPdfFile);
                }
                
                if (file_exists($cleanedPdfFile)) {
                    unlink($cleanedPdfFile);
                }
                
            } catch (Exception $e2) {
                $errorMessage = "Erreur lors du traitement du PDF : " . $e->getMessage();
                $array['errors'][] = $errorMessage;
                $array['errors'][] = "Tentative de nettoyage avec Ghostscript échouée : " . $e2->getMessage();
                $array['errors'][] = "Impossible de traiter ce PDF avec les outils disponibles.";
                
                // Nettoyer les fichiers temporaires
                if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                    @unlink($paddedPdfFile);
                }
                
                if ($cleanedPdfFile && file_exists($cleanedPdfFile)) {
                    unlink($cleanedPdfFile);
                }
                
                error_log("Erreur imposition PDF: " . $e->getMessage() . " - Erreur Ghostscript: " . $e2->getMessage() . " - Fichier: " . ($originalFileName ?? "inconnu"));
            }
            }
        }
    }
    
    return template(__DIR__ . "/../view/imposition_livre.html.php", $array);
}
}

?>

