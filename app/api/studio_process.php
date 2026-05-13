<?php
/**
 * Studio Process API - Point d'entrée AJAX unifié pour Dupli Studio
 * 
 * Accepte : POST multipart/form-data
 *   - action   : 'impose' | 'resize'
 *   - file     : fichier PDF/image uploadé
 *   - (params spécifiques selon l'action)
 * 
 * Retourne : JSON { success, download_url, errors[] }
 */

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: application/json');

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/paths.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\TcpdfFpdi as TCPDI;

$action = $_POST['action'] ?? '';
$errors = [];
$result = [];

$uploadedFile = null;
$originalName = null;
$safeName = 'studio_doc';

// --- Récupérer le fichier uploadé (sauf pour certaines actions) ---
if (!in_array($action, ['organize_pages', 'merge', 'riso_pdf'])) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'errors' => ['Aucun fichier valide reçu.']]);
        exit;
    }
    $uploadedFile  = $_FILES['file']['tmp_name'];
    $originalName  = $_FILES['file']['name'];
    $safeName      = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
} else {
    // S'il y a quand même un fichier 'file' (ex: merge avec fichier principal), on le récupère
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile  = $_FILES['file']['tmp_name'];
        $originalName  = $_FILES['file']['name'];
        $safeName      = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    }
}

$tmpBase       = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
if (!is_dir($tmpBase)) mkdir($tmpBase, 0777, true);

/**
 * Génère un aperçu PNG de la première page d'un PDF via Ghostscript.
 * Retourne le nom du fichier PNG créé, ou null si Ghostscript échoue.
 */
function generateImpositionPreview(string $pdfPath, string $tmpBase, string $safeName): ?string {
    $previewFile = $tmpBase . $safeName . '_preview.png';
    // Ghostscript : première page uniquement, 150 dpi, fond blanc
    $gs_args = implode(' ', [
        '-dNOPAUSE', '-dBATCH', '-dSAFER',
        '-sDEVICE=png16m',
        '-r150',
        '-dFirstPage=1', '-dLastPage=1',
        '-dFitPage',
        '-sOutputFile=' . escapeshellarg($previewFile),
        escapeshellarg($pdfPath),
    ]);
    $result = run_ghostscript($gs_args);
    if ($result['success'] && file_exists($previewFile) && filesize($previewFile) > 0) {
        return basename($previewFile);
    }
    return null;
}

// === ACTION : IMPOSE ===
if ($action === 'impose') {
    $impose_type = $_POST['impose_type'] ?? 'brochure'; // brochure | livre | tracts

    // Validate + auto-convert image → PDF si besoin
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile);
    finfo_close($finfo);

    $imposeTempPdf = null; // fichier temporaire à nettoyer après
    $imageTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    if (in_array($mimeType, $imageTypes)) {
        // Convertir l'image en PDF avant imposition
        $sz = getimagesize($uploadedFile);
        if (!$sz) {
            echo json_encode(['success' => false, 'errors' => ["Impossible de lire les dimensions de l'image."]]);
            exit;
        }
        $dpi  = 96;
        $w_mm = round($sz[0] * 25.4 / $dpi, 2);
        $h_mm = round($sz[1] * 25.4 / $dpi, 2);
        $orientation = ($w_mm > $h_mm) ? 'L' : 'P';

        $pdfConv = new TCPDI();
        $pdfConv->SetCreator('Dupli Studio');
        $pdfConv->setPrintHeader(false);
        $pdfConv->setPrintFooter(false);
        $pdfConv->SetAutoPageBreak(false, 0);
        $pdfConv->SetMargins(0, 0, 0);
        $pdfConv->AddPage($orientation, [$w_mm, $h_mm]);
        $pdfConv->Image($uploadedFile, 0, 0, $w_mm, $h_mm);

        $imposeTempPdf = $tmpBase . 'img2pdf_' . time() . '_' . uniqid() . '.pdf';
        $pdfConv->Output($imposeTempPdf, 'F');

        if (!file_exists($imposeTempPdf)) {
            echo json_encode(['success' => false, 'errors' => ["Échec de la conversion image → PDF."]]);
            exit;
        }
        $uploadedFile = $imposeTempPdf; // remplacer le fichier source pour la suite
        $mimeType     = 'application/pdf';

    } elseif ($mimeType !== 'application/pdf') {
        echo json_encode(['success' => false, 'errors' => ["Format non supporté : $mimeType. Veuillez fournir un PDF ou une image (PNG, JPG, WebP)."]]);
        exit;
    }

    // === TRACTS (N-up copies identiques) ===
    if ($impose_type === 'tracts') {
        try {
            require_once __DIR__ . '/../models/imposition_tracts.php';
            // Copier le fichier uploadé en temp pour utiliser le mode "bibliothèque"
            // (évite les contraintes de move_uploaded_file sur l'alias $_FILES)
            $tempCopy = $tmpBase . 'studio_tracts_' . time() . '_' . uniqid() . '.pdf';
            if (!copy($uploadedFile, $tempCopy)) throw new Exception("Impossible de copier le fichier.");
            $_FILES['pdf_file'] = [
                'name'     => $originalName,
                'type'     => 'application/pdf',
                'tmp_name' => $tempCopy,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempCopy),
            ];
            // Transmettre les paramètres studio → processImpositionTracts lit $_POST directement
            $_POST['output_format']      = $_POST['output_format']      ?? 'A3';
            $_POST['manual_format']      = $_POST['manual_format']       ?? 'auto';
            $_POST['orientation']        = $_POST['orientation']         ?? 'auto';
            $_POST['draw_crop_marks']    = ($_POST['draw_crop_marks'] ?? '0') === '1' ? '1' : '0';
            $_POST['crop_marks_length']  = $_POST['crop_marks_length']  ?? 10;
            $_POST['crop_marks_width']   = $_POST['crop_marks_width']   ?? 0.5;
            $_POST['cut_margin']         = $_POST['cut_margin']         ?? 2;
            $_POST['keep_original_size'] = ($_POST['keep_original_size'] ?? '0') === '1' ? '1' : '0';
            $_POST['force_resize']       = ($_POST['force_resize'] ?? '0') === '1' ? '1' : '0';
            $_POST['duplex_mode']        = $_POST['duplex_mode']        ?? 'none';

            $result = processImpositionTracts(true); // true = from_lib → utilise rename

            if ($imposeTempPdf && file_exists($imposeTempPdf)) @unlink($imposeTempPdf);
            // Tracts sauvegarde dans resolveTempDir() (racine, pas sous-dossier studio)
            $tractsDlUrl = $result['download_url'] ?? '';
            preg_match('/file=([^&]+)/', $tractsDlUrl, $m);
            $tractsFile = $m[1] ?? '';
            $tractsPath = $tractsFile ? resolveTempDir() . DIRECTORY_SEPARATOR . $tractsFile : '';
            $previewPng = ($tractsPath && file_exists($tractsPath))
                ? generateImpositionPreview($tractsPath, $tmpBase, $safeName)
                : null;
            echo json_encode([
                'success'      => $result['success'] ?? false,
                'download_url' => $tractsDlUrl,
                'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
                'errors'       => isset($result['error']) ? [$result['error']] : [],
            ]);
        } catch (Exception $e) {
            if (isset($tempCopy) && file_exists($tempCopy)) @unlink($tempCopy);
            if ($imposeTempPdf && file_exists($imposeTempPdf)) @unlink($imposeTempPdf);
            echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        }
        exit;
    }

    // === LIVRE (Cut & Stack, classe Imposition) ===
    if ($impose_type === 'livre') {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../models/imposition.php';
            require_once __DIR__ . '/../models/imposition_livre.php'; // fournit padPdfToMultiple

            $n_up        = intval($_POST['n_up'] ?? 2);
            $multiple    = 2 * $n_up;
            $resize_mode = $_POST['resize_mode'] ?? 'percent';
            $scale       = ($resize_mode === 'mm') ? 0 : floatval($_POST['scale'] ?? 100);
            $target_w    = ($resize_mode === 'mm') ? floatval($_POST['target_width']  ?? 0) : 0;
            $target_h    = ($resize_mode === 'mm') ? floatval($_POST['target_height'] ?? 0) : 0;

            $paddingResult = padPdfToMultiple($uploadedFile, $multiple);
            $pdfFile       = $paddingResult['file'];

            $settings = [
                'n_up'                        => $n_up,
                'duplex'                      => ($_POST['duplex']    ?? '0') === '1',
                'tete_beche'                  => ($_POST['tete_beche'] ?? '0') === '1',
                'scale'                       => $scale,
                'target_width'                => $target_w,
                'target_height'               => $target_h,
                'gutter_x'                    => floatval($_POST['gutter_x']       ?? 0),
                'gutter_y'                    => floatval($_POST['gutter_y']       ?? 0),
                'gutter_strategy'             => $_POST['gutter_strategy']          ?? 'reduce',
                'crop_marks'                  => ($_POST['crop_marks'] ?? '0') === '1',
                'crop_style'                  => $_POST['crop_style']               ?? 'spreads',
                'crop_mark_len'               => floatval($_POST['crop_mark_len']  ?? 2),
                'crop_mark_width'             => floatval($_POST['crop_mark_width'] ?? 0.1),
                'preview_mode'                => false,
                'add_page_numbers_in_gutters' => ($_POST['add_page_numbers_in_gutters'] ?? '0') === '1',
                'gutter_num_offset_x'         => floatval($_POST['gutter_num_offset_x'] ?? 0.0),
                'gutter_num_offset_y'         => floatval($_POST['gutter_num_offset_y'] ?? -2.0),
                'output_format'               => $_POST['output_format'] ?? 'A3',
                'addPageNumberCallback'        => null,
            ];

            $outFilename = $safeName . '_studio_livre.pdf';
            $outPath     = $tmpBase . $outFilename;

            $imposition = new Imposition($pdfFile, $settings);
            $imposition->process($outPath, null);

            if ($paddingResult['temp_file'] && file_exists($paddingResult['temp_file'])) @unlink($paddingResult['temp_file']);
            if (!file_exists($outPath)) throw new Exception("Le fichier livre n'a pas été créé.");

            if ($imposeTempPdf && file_exists($imposeTempPdf)) @unlink($imposeTempPdf);

            $previewPng = generateImpositionPreview($outPath, $tmpBase, $safeName);
            echo json_encode([
                'success'      => true,
                'download_url' => '?download_studio&file=' . urlencode($outFilename),
                'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
                'errors'       => [],
            ]);
        } catch (Exception $e) {
            // Fallback GS pour livre
            try {
                $cleanedPath = $tmpBase . 'cleaned_livre_' . time() . '.pdf';
                $gs = run_ghostscript("-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=" . escapeshellarg($cleanedPath) . " " . escapeshellarg($uploadedFile));
                if (!$gs['success'] || !file_exists($cleanedPath)) throw new Exception("GS: " . $gs['output']);
                $pad2 = padPdfToMultiple($cleanedPath, 2 * intval($_POST['n_up'] ?? 2));
                $imp2 = new Imposition($pad2['file'], $settings ?? []);
                $out2 = $tmpBase . $safeName . '_studio_livre.pdf';
                $imp2->process($out2, null);
                if ($pad2['temp_file']) @unlink($pad2['temp_file']);
                @unlink($cleanedPath);
                $previewPng = generateImpositionPreview($out2, $tmpBase, $safeName);
                echo json_encode([
                    'success'      => true, 
                    'download_url' => '?download_studio&file=' . urlencode(basename($out2)), 
                    'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
                    'errors'       => []
                ]);
            } catch (Exception $e2) {
                echo json_encode(['success' => false, 'errors' => [$e->getMessage(), $e2->getMessage()]]);
            }
            if ($imposeTempPdf && file_exists($imposeTempPdf)) @unlink($imposeTempPdf);
        }
        exit;
    }

    // === BROCHURE (ImpositionLeaflet — défaut) ===
    try {
        require_once __DIR__ . '/../models/ImpositionLeaflet.php';
        require_once __DIR__ . '/../models/imposition_brochure.php'; // fournit padPdfToMultiple

        $n_up        = intval($_POST['n_up']  ?? 2);
        $multiple    = ($n_up == 2) ? 4 : (($n_up == 4) ? 8 : 16);
        $resize_mode = $_POST['resize_mode'] ?? 'percent';
        $scale       = ($resize_mode === 'mm') ? 0 : floatval($_POST['scale'] ?? 100);
        $target_w    = ($resize_mode === 'mm') ? floatval($_POST['target_width']  ?? 0) : 0;
        $target_h    = ($resize_mode === 'mm') ? floatval($_POST['target_height'] ?? 0) : 0;

        $paddingResult = padPdfToMultiple($uploadedFile, $multiple);
        $pdfFile       = $paddingResult['file'];
        $pageCount     = $paddingResult['page_count'];

        $settings = [
            'n_up'                        => $n_up,
            'scale'                       => $scale,
            'target_width'                => $target_w,
            'target_height'               => $target_h,
            'gutter_x'                    => floatval($_POST['gutter_x']       ?? 0),
            'gutter_y'                    => floatval($_POST['gutter_y']       ?? 0),
            'gutter_strategy'             => $_POST['gutter_strategy']          ?? 'reduce',
            'crop_marks'                  => ($_POST['crop_marks'] ?? '0') === '1',
            'crop_style'                  => $_POST['crop_style']               ?? 'standard',
            'crop_mark_len'               => floatval($_POST['crop_mark_len']  ?? 5),
            'crop_mark_width'             => floatval($_POST['crop_mark_width'] ?? 0.1),
            'preview_mode'                => false,
            'add_page_numbers_in_gutters' => ($_POST['add_page_numbers_in_gutters'] ?? '0') === '1',
            'output_format'               => $_POST['output_format'] ?? 'A3',
            'addPageNumberCallback'        => null,
        ];

        $outFilename = $safeName . '_studio_brochure.pdf';
        $outPath     = $tmpBase . $outFilename;

        $imposition = new ImpositionLeaflet($pdfFile, $settings);
        $imposition->process($outPath, null);

        if ($paddingResult['temp_file'] && file_exists($paddingResult['temp_file'])) @unlink($paddingResult['temp_file']);
        if (!file_exists($outPath)) throw new Exception("Le fichier brochure n'a pas été créé.");

        if ($imposeTempPdf && file_exists($imposeTempPdf)) @unlink($imposeTempPdf);

        $previewPng = generateImpositionPreview($outPath, $tmpBase, $safeName);
        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
            'page_count'   => $pageCount,
            'errors'       => [],
        ]);
        exit;

    } catch (Exception $e) {
        // Fallback GS pour brochure
        try {
            $cleanedPath = $tmpBase . 'cleaned_brochure_' . time() . '.pdf';
            $gs = run_ghostscript("-dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=" . escapeshellarg($cleanedPath) . " " . escapeshellarg($uploadedFile));
            if (!$gs['success'] || !file_exists($cleanedPath)) throw new Exception("GS: " . $gs['output']);
            $pad2 = padPdfToMultiple($cleanedPath, $multiple);
            $imp2 = new ImpositionLeaflet($pad2['file'], $settings ?? []);
            $out2 = $tmpBase . $safeName . '_studio_brochure.pdf';
            $imp2->process($out2, null);
            if ($pad2['temp_file']) @unlink($pad2['temp_file']);
            @unlink($cleanedPath);
            echo json_encode(['success' => true, 'download_url' => '?download_studio&file=' . urlencode(basename($out2)), 'errors' => []]);
        } catch (Exception $e2) {
            echo json_encode(['success' => false, 'errors' => [$e->getMessage(), $e2->getMessage()]]);
        }
        exit;
    }
}

// === ACTION : RESIZE ===
if ($action === 'resize') {
    try {
        $format  = $_POST['resize_format'] ?? 'A4';
        $mode    = 'fit';
        $alignment = 'centered';

        $formats = [
            'A4' => ['w' => 210, 'h' => 297],
            'A3' => ['w' => 297, 'h' => 420],
            'A5' => ['w' => 148, 'h' => 210],
        ];
        if (!isset($formats[$format])) throw new Exception("Format inconnu : $format");
        $target_w = $formats[$format]['w'];
        $target_h = $formats[$format]['h'];

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);
        $is_pdf   = ($mimeType === 'application/pdf');

        $pdf = new TCPDI();
        $pdf->SetCreator('Dupli Studio');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);

        if ($is_pdf) {
            $pageCount = $pdf->setSourceFile($uploadedFile);
            for ($p = 1; $p <= $pageCount; $p++) {
                $tpl  = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tpl);
                $sw   = $size['width'];  $sh = $size['height'];
                $orientation = ($sw > $sh) ? 'L' : 'P';
                $tw = ($orientation === 'L') ? $target_h : $target_w;
                $th = ($orientation === 'L') ? $target_w : $target_h;
                $pdf->AddPage($orientation, [$tw, $th]);
                $scale = min($tw / $sw, $th / $sh);
                $fw = $sw * $scale; $fh = $sh * $scale;
                $px = ($tw - $fw) / 2; $py = ($th - $fh) / 2;
                $pdf->useTemplate($tpl, $px, $py, $fw, $fh);
            }
        } else {
            $sz = getimagesize($uploadedFile);
            if (!$sz) throw new Exception("Impossible de lire les dimensions de l'image.");
            $sw = $sz[0] * 25.4 / 300; $sh = $sz[1] * 25.4 / 300;
            $orientation = ($sw > $sh) ? 'L' : 'P';
            $tw = ($orientation === 'L') ? $target_h : $target_w;
            $th = ($orientation === 'L') ? $target_w : $target_h;
            $pdf->AddPage($orientation, [$tw, $th]);
            $scale = min($tw / $sw, $th / $sh);
            $fw = $sw * $scale; $fh = $sh * $scale;
            $pdf->Image($uploadedFile, ($tw - $fw) / 2, ($th - $fh) / 2, $fw, $fh);
        }

        $outFilename = $safeName . '_studio_resized_' . $format . '.pdf';
        $outPath     = $tmpBase . $outFilename;
        $pdf->Output($outPath, 'F');

        if (!file_exists($outPath)) throw new Exception("Le fichier redimensionné n'a pas été créé.");

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'filename'     => $outFilename,
            'errors'       => []
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        exit;
    }
}

// === ACTION : TO_PDF (Canvas PNG → PDF) ===
if ($action === 'to_pdf') {
    try {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);

        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowed)) {
            throw new Exception("Format d'image non supporté : $mimeType");
        }

        $sz = getimagesize($uploadedFile);
        if (!$sz) throw new Exception("Impossible de lire les dimensions de l'image.");

        // Dimensions en mm (on suppose 96 dpi pour le canvas navigateur)
        $dpi = floatval($_POST['dpi'] ?? 96);
        $w_mm = round($sz[0] * 25.4 / $dpi, 2);
        $h_mm = round($sz[1] * 25.4 / $dpi, 2);
        $orientation = ($w_mm > $h_mm) ? 'L' : 'P';

        $pdf = new TCPDI();
        $pdf->SetCreator('Dupli Studio');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage($orientation, [$w_mm, $h_mm]);
        $pdf->Image($uploadedFile, 0, 0, $w_mm, $h_mm);

        $outFilename = $safeName . '_studio_export.pdf';
        $outPath     = $tmpBase . $outFilename;
        $pdf->Output($outPath, 'F');

        if (!file_exists($outPath)) throw new Exception("Le PDF n'a pas pu être créé.");

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'filename'     => $outFilename,
            'errors'       => []
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        exit;
    }
}

// === ACTION : RISO_PDF (Multiple layers → Multi-page PDF) ===
if ($action === "riso_pdf") {
    try {
        $layers = $_FILES["layers"] ?? null;
        $colors = $_POST["colors"] ?? [];
        if (!$layers || !is_array($layers["tmp_name"])) {
            throw new Exception("Aucun calque reçu.");
        }

        $pdf = new TCPDI();
        $pdf->SetCreator("Dupli Studio");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);

        foreach ($layers["tmp_name"] as $i => $tmpPath) {
            if ($layers["error"][$i] !== UPLOAD_ERR_OK) continue;
            $sz = getimagesize($tmpPath);
            if (!$sz) continue;
            $dpi = floatval($_POST["dpi"] ?? 96);
            $w_mm = round($sz[0] * 25.4 / $dpi, 2);
            $h_mm = round($sz[1] * 25.4 / $dpi, 2);
            $orientation = ($w_mm > $h_mm) ? "L" : "P";
            $pdf->AddPage($orientation, [$w_mm, $h_mm]);
            $pdf->Image($tmpPath, 0, 0, $w_mm, $h_mm);
            $colorName = $colors[$i] ?? "Inconnu";
            $pdf->SetFont("helvetica", "B", 10);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Text(10, 10, "TAMBOUR RISO : " . strtoupper($colorName));
        }

        $outFilename = $safeName . "_riso_planches.pdf";
        $outPath     = $tmpBase . $outFilename;
        $pdf->Output($outPath, "F");
        echo json_encode([
            "success"      => true,
            "download_url" => "?download_studio&file=" . urlencode($outFilename),
            "filename"     => $outFilename,
            "errors"       => []
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(["success" => false, "errors" => [$e->getMessage()]]);
        exit;
    }
}

// === ACTION : ANALYZE_INK (Calculate fill rate) ===
if ($action === 'analyze_ink') {
    try {
        require_once __DIR__ . '/../models/taux_remplissage.php';
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);

        if ($mime === 'application/pdf') {
            $result = analyze_pdf_ink_coverage_gs($uploadedFile);
        } else {
            // For images, use the simple fill rate calculation
            $result = calculate_fill_rate($uploadedFile);
        }

        echo json_encode([
            'success' => true,
            'result'  => $result
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        exit;
    }
}

// === ACTION : PDF_TO_IMAGES ===
if ($action === 'pdf_to_images') {
    try {
        require_once __DIR__ . '/../models/pdf_to_png.php';

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') throw new Exception("Le fichier doit être un PDF.");

        $dpi     = max(72, min(300, intval($_POST['dpi'] ?? 150)));
        $outDir  = $tmpBase . $safeName . '_pages_' . time() . DIRECTORY_SEPARATOR;
        if (!is_dir($outDir)) mkdir($outDir, 0777, true);

        $created = convert_pdf_to_png($uploadedFile, $outDir, $dpi, $safeName);

        if (empty($created)) throw new Exception("Aucune image générée.");

        // Créer un ZIP
        $zipName = $safeName . '_pages.zip';
        $zipPath = $tmpBase . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($created as $f) $zip->addFile($f, basename($f));
            $zip->close();
        }

        // Copier les PNG dans tmpBase pour les servir via download_studio
        $dlUrls = [];
        foreach ($created as $f) {
            $dest = $tmpBase . basename($f);
            copy($f, $dest);
            $dlUrls[] = '?download_studio&file=' . urlencode(basename($f));
        }

        // Preview = première page
        $previewPng = null;
        if (!empty($dlUrls)) {
            $firstPng = $tmpBase . basename($created[0]);
            if (file_exists($firstPng)) $previewPng = '?preview_studio&file=' . urlencode(basename($firstPng));
        }

        echo json_encode([
            'success'       => true,
            'download_url'  => file_exists($zipPath) ? '?download_studio&file=' . urlencode($zipName) : $dlUrls[0],
            'preview_url'   => $previewPng,
            'page_urls'     => $dlUrls,
            'page_count'    => count($created),
            'errors'        => [],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
    }
    exit;
}

// === ACTION : MERGE ===
// Fusionne plusieurs fichiers PDF (reçus dans $_FILES['files'][])
if ($action === 'merge') {
    try {
        require_once __DIR__ . '/../models/pdf_merge.php';

        // Récupérer tous les fichiers de la requête (multi-upload clé 'files[]')
        $filesData = $_FILES['files'] ?? null;
        $pdfPaths = [];

        // Si un fichier unique a été chargé via la dropzone normale, l'inclure aussi
        if (file_exists($uploadedFile)) {
            $pdfPaths[] = $uploadedFile;
        }

        if ($filesData && is_array($filesData['tmp_name'])) {
            foreach ($filesData['tmp_name'] as $i => $tmp) {
                if ($filesData['error'][$i] === UPLOAD_ERR_OK && is_uploaded_file($tmp)) {
                    $dest = $tmpBase . 'merge_' . $i . '_' . time() . '.pdf';
                    move_uploaded_file($tmp, $dest);
                    $pdfPaths[] = $dest;
                }
            }
        }

        // Dédoublonner et valider que ce sont des PDFs
        $pdfPaths = array_unique($pdfPaths);
        $pdfPaths = array_filter($pdfPaths, function($p) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $m = finfo_file($finfo, $p);
            finfo_close($finfo);
            return $m === 'application/pdf';
        });
        $pdfPaths = array_values($pdfPaths);

        if (count($pdfPaths) < 2) throw new Exception("Au moins 2 PDFs valides sont requis pour la fusion.");

        $outFilename = $safeName . '_merged.pdf';
        $outPath     = $tmpBase . $outFilename;

        merge_pdfs($pdfPaths, $outPath);

        if (!file_exists($outPath)) throw new Exception("Le fichier fusionné n'a pas été créé.");

        $previewPng = generateImpositionPreview($outPath, $tmpBase, $safeName . '_merged');

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
            'errors'       => [],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
    }
    exit;
}

// === ACTION : UNIMPOSE ===
if ($action === 'unimpose') {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../models/unimpose_logic.php';

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') throw new Exception("Le fichier doit être un PDF.");

        $mode        = $_POST['unimpose_mode'] ?? 'booklet'; // booklet | doubles
        $outFilename = $safeName . '_unimposed.pdf';
        $outPath     = $tmpBase . $outFilename;

        $un = new UnimposeBooklet($uploadedFile, $outPath);

        if ($mode === 'doubles') {
            $result = $un->splitDoublePages();
        } else {
            $result = $un->unimposeBooklet();
        }

        if (!$result || !file_exists($outPath)) throw new Exception("La dés-imposition a échoué. Vérifiez que le PDF est bien un livret imposé.");

        $previewPng = generateImpositionPreview($outPath, $tmpBase, $safeName . '_unimposed');

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'preview_url'  => $previewPng ? '?preview_studio&file=' . urlencode($previewPng) : null,
            'errors'       => [],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
    }
    exit;
}

// === ACTION : ORGANIZE_PAGES ===
if ($action === 'organize_pages') {
    try {
        $structure = json_decode($_POST['structure'] ?? '[]', true);
        if (empty($structure)) throw new Exception("Structure de pages invalide.");

        $files = []; // Map file_idx => temp path
        
        // Save uploaded files to temp
        foreach ($_FILES as $key => $fileData) {
            if (strpos($key, 'file_') === 0 && $fileData['error'] === UPLOAD_ERR_OK) {
                $idx = str_replace('file_', '', $key);
                $dest = $tmpBase . 'org_' . $idx . '_' . time() . '.pdf';
                move_uploaded_file($fileData['tmp_name'], $dest);
                $files[$idx] = $dest;
            }
        }

        // Generate images for each page, then combine
        $images = [];
        $blank_width = 1240;
        $blank_height = 1754;

        foreach ($structure as $item) {
            if ($item['type'] === 'blank') {
                $blank_path = $tmpBase . 'blank_' . uniqid() . '.png';
                $magick_args = "-size {$blank_width}x{$blank_height} xc:white " . escapeshellarg($blank_path);
                run_imagemagick($magick_args);
                $images[] = $blank_path;
            } else {
                $idx = $item['file_idx'];
                if (!isset($files[$idx]) || !file_exists($files[$idx])) continue;
                
                $rotation = intval($item['rotation'] ?? 0);
                $page_idx = intval($item['page_num'] ?? 1) - 1;
                $out_path = $tmpBase . 'page_' . uniqid() . '.png';
                
                $rot_cmd = $rotation != 0 ? "-rotate $rotation" : "";
                $magick_args = "-density 150 " . escapeshellarg($files[$idx] . "[$page_idx]") . " $rot_cmd " . escapeshellarg($out_path);
                run_imagemagick($magick_args);
                
                if (file_exists($out_path)) {
                    $images[] = $out_path;
                }
            }
        }

        if (empty($images)) throw new Exception("Aucune page à inclure");

        $outFilename = 'organized_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;

        $img_list = implode(' ', array_map('escapeshellarg', $images));
        run_imagemagick("$img_list " . escapeshellarg($outPath));

        foreach ($images as $img) @unlink($img);

        if (!file_exists($outPath)) throw new Exception("Erreur génération PDF");

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'errors'       => [],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
    }
    exit;
}

echo json_encode(['success' => false, 'errors' => ['Action inconnue : ' . htmlspecialchars($action)]]);

