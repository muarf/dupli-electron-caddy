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
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/paths.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/../models/SettingsManager.php';
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Retourne tous les réglages de l'application (cached).
 * Utilisé pour lire les URLs VPS des services IA Studio.
 */
function getStudioSettings(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $db = pdo_connect();
            $sm = new SettingsManager($db);
            $cache = $sm->getAll();
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

use setasign\Fpdi\TcpdfFpdi as TCPDI;

/**
 * Déplace un fichier uploadé ou copié localement (compatible HTTP et CLI/test).
 */
function studio_move_uploaded_file(string $tmp, string $dest): bool {
    if (is_uploaded_file($tmp)) {
        return move_uploaded_file($tmp, $dest);
    }
    if (file_exists($tmp)) {
        if (rename($tmp, $dest)) {
            return true;
        }
        return copy($tmp, $dest);
    }
    return false;
}

$action = $_POST['action'] ?? '';
$errors = [];
$result = [];

$uploadedFile = null;
$originalName = null;
$safeName = 'studio_doc';

// --- Récupérer le fichier uploadé (sauf pour certaines actions) ---
if (!in_array($action, ['organize_pages', 'merge', 'riso_pdf', 'montage_libre', 'crop_pdf', 'modification', 'upload_font', 'list_fonts', 'recognize_font', 'passthrough_pdf', 'download_google_font'])) {
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
                'add_page_numbers_position' => $_POST['add_page_numbers_position'] ?? 'margins',
                'add_page_numbers_manual_offset' => ($_POST['add_page_numbers_manual_offset'] ?? '0') === '1',
                'gutter_num_offset_x'         => (float)($_POST['gutter_num_offset_x'] ?? 0),
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
            'add_page_numbers_position' => $_POST['add_page_numbers_position'] ?? 'margins',
            'add_page_numbers_manual_offset' => ($_POST['add_page_numbers_manual_offset'] ?? '0') === '1',
            'gutter_num_offset_x'         => (float)($_POST['gutter_num_offset_x'] ?? 0),
            'gutter_num_offset_y'         => floatval($_POST['gutter_num_offset_y'] ?? -2.0),
            'tete_beche'                  => ($_POST['tete_beche'] ?? '0') === '1',
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

// === ACTION : PASSTHROUGH_PDF (PDF → PDF sans modification) ===
// Permet de télécharger un PDF brut en préservant la couche OCR/texte
if ($action === 'passthrough_pdf') {
    try {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $uploadedFile);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            throw new Exception("Le fichier doit être un PDF pour le passthrough.");
        }

        $outFilename = $safeName . '_studio.pdf';
        $outPath     = $tmpBase . $outFilename;
        if (!copy($uploadedFile, $outPath)) {
            throw new Exception("Impossible de copier le fichier PDF.");
        }

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
                if ($filesData['error'][$i] === UPLOAD_ERR_OK && (is_uploaded_file($tmp) || file_exists($tmp))) {
                    $dest = $tmpBase . 'merge_' . $i . '_' . time() . '.pdf';
                    studio_move_uploaded_file($tmp, $dest);
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

        $mode        = $_POST['unimpose_mode'] ?? 'booklet'; // booklet | doubles | sequential
        $outFilename = $safeName . '_unimposed.pdf';
        $outPath     = $tmpBase . $outFilename;

        $un = new UnimposeBooklet($uploadedFile, $outPath);

        if ($mode === 'doubles') {
            $result = $un->splitDoublePages();
        } elseif ($mode === 'sequential') {
            $result = $un->splitSequential();
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
                studio_move_uploaded_file($fileData['tmp_name'], $dest);
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
                $flipH = !empty($item['flipH']);
                $flipV = !empty($item['flipV']);
                $page_idx = intval($item['page_num'] ?? 1) - 1;
                $out_path = $tmpBase . 'page_' . uniqid() . '.png';
                
                $rot_cmd = $rotation != 0 ? "-rotate $rotation" : "";
                $flop_cmd = $flipH ? "-flop" : "";
                $flip_cmd = $flipV ? "-flip" : "";
                $magick_args = "-density 150 " . escapeshellarg($files[$idx] . "[$page_idx]") . " $rot_cmd $flop_cmd $flip_cmd " . escapeshellarg($out_path);
                run_imagemagick($magick_args);
                
                // Appliquer le crop si défini
                $crop = isset($_POST['crop']) ? json_decode($_POST['crop'], true) : null;
                if (file_exists($out_path) && $crop) {
                    // 150 DPI : 1 mm = 150/25.4 px ≈ 5.906 px
                    $dpi = 150;
                    $pxPerMm = $dpi / 25.4;
                    list($imgW, $imgH) = getimagesize($out_path);
                    $cropL = max(0, round(floatval($crop['left'])   * $pxPerMm));
                    $cropT = max(0, round(floatval($crop['top'])    * $pxPerMm));
                    $cropR = max(0, round(floatval($crop['right'])  * $pxPerMm));
                    $cropB = max(0, round(floatval($crop['bottom']) * $pxPerMm));
                    $newW = $imgW - $cropL - $cropR;
                    $newH = $imgH - $cropT - $cropB;
                    if ($newW > 0 && $newH > 0) {
                        $cropped = $tmpBase . 'cropped_' . uniqid() . '.png';
                        $crop_args = escapeshellarg($out_path) . " -crop {$newW}x{$newH}+{$cropL}+{$cropT} +repage " . escapeshellarg($cropped);
                        run_imagemagick($crop_args);
                        if (file_exists($cropped)) {
                            @unlink($out_path);
                            $out_path = $cropped;
                        }
                    }
                }
                
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

// === ACTION : MONTAGE_LIBRE ===
if ($action === 'montage_libre') {
    header('Content-Type: application/json');
    try {
        $payloadRaw = $_POST['payload'] ?? '';
        $payload = json_decode($payloadRaw, true);
        if (!$payload || !isset($payload['planches'])) throw new Exception("Payload invalide");

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Save uploaded files to temp
        $tempSourcePdfs = [];
        foreach ($_FILES as $key => $fileInfo) {
            if (strpos($key, 'file_') === 0 && $fileInfo['error'] === UPLOAD_ERR_OK) {
                $id = str_replace('file_', '', $key);
                $tmpPath = $tmpBase . 'source_' . $id . '_' . time() . '.pdf';
                studio_move_uploaded_file($fileInfo['tmp_name'], $tmpPath);
                $tempSourcePdfs[$id] = $tmpPath;
            }
        }

        foreach ($payload['planches'] as $planche) {
            $fmt = [$planche['width_mm'], $planche['height_mm']];
            $orientation = ($planche['width_mm'] > $planche['height_mm']) ? 'L' : 'P';
            $pdf->AddPage($orientation, $fmt);

            if (isset($planche['objects']) && is_array($planche['objects'])) {
                foreach ($planche['objects'] as $obj) {
                    $fileId = $obj['source_fileId'];
                    if (!isset($tempSourcePdfs[$fileId])) continue;
                    
                    $original_w = $obj['original_width_mm'];
                    $original_h = $obj['original_height_mm'];
                    $scale_x = $obj['scale_x'];
                    $scale_y = $obj['scale_y'];
                    $angle = $obj['angle'];
                    $mm_to_px = $obj['mm_to_px'];
                    
                    $cx = $obj['x_px'] / $mm_to_px;
                    $cy = $obj['y_px'] / $mm_to_px;
                    
                    $isImage = isset($obj['is_image']) && $obj['is_image'];
                    
                    if ($isImage) {
                        $pdf->StartTransform();
                        $pdf->Translate($cx, $cy);
                        if ($angle != 0) {
                            $pdf->Rotate(-$angle);
                        }
                        if ($scale_x != 1 || $scale_y != 1) {
                            $pdf->ScaleX($scale_x * 100, 0, 0);
                            $pdf->ScaleY($scale_y * 100, 0, 0);
                        }
                        
                        $pdf->Image($tempSourcePdfs[$fileId], -$original_w / 2, -$original_h / 2, $original_w, $original_h);
                        $pdf->StopTransform();
                    } else {
                        $pageNum = $obj['page_num'];
                        $pdf->setSourceFile($tempSourcePdfs[$fileId]);
                        $tplId = $pdf->importPage($pageNum);
                        
                        $pdf->StartTransform();
                        $pdf->Translate($cx, $cy);
                        if ($angle != 0) {
                            $pdf->Rotate(-$angle);
                        }
                        if ($scale_x != 1 || $scale_y != 1) {
                            $pdf->ScaleX($scale_x * 100, 0, 0);
                            $pdf->ScaleY($scale_y * 100, 0, 0);
                        }
                        
                        $pdf->useTemplate($tplId, -$original_w / 2, -$original_h / 2, $original_w, $original_h);
                        $pdf->StopTransform();
                    }
                }
            }
        }

        $outFilename = 'montage_libre_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;
        $pdf->Output($outPath, 'F');
        
        $previewPng = generateImpositionPreview($outPath, $tmpBase, 'montage_libre_preview');

        foreach ($tempSourcePdfs as $tp) { @unlink($tp); }

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

// === ACTION : CROP_PDF ===
// Rogne toutes les pages d'un PDF ou image selon les marges en mm
if ($action === 'crop_pdf') {
    header('Content-Type: application/json');
    try {
        $crop = json_decode($_POST['crop'] ?? 'null', true);
        if (!$crop) throw new Exception('Paramètres de crop invalides.');
        if (!$uploadedFile || !file_exists($uploadedFile)) throw new Exception('Fichier invalide.');

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $isPdf = ($ext === 'pdf');

        // 150 DPI : 1 mm = 150/25.4 px ≈ 5.906 px
        $dpi = 150;
        $pxPerMm = $dpi / 25.4;
        $cropL = max(0, round(floatval($crop['left'])   * $pxPerMm));
        $cropT = max(0, round(floatval($crop['top'])    * $pxPerMm));
        $cropR = max(0, round(floatval($crop['right'])  * $pxPerMm));
        $cropB = max(0, round(floatval($crop['bottom']) * $pxPerMm));

        $images = [];

        if ($isPdf) {
            // Compter les pages via ImageMagick identify
            $page_count_raw = shell_exec('identify ' . escapeshellarg($uploadedFile) . ' 2>/dev/null | wc -l');
            $numPages = max(1, intval(trim($page_count_raw)));

            for ($p = 0; $p < $numPages; $p++) {
                $page_png = $tmpBase . 'crop_page_' . $p . '_' . uniqid() . '.png';
                $magick_args = "-density {$dpi} " . escapeshellarg($uploadedFile . "[$p]") . ' ' . escapeshellarg($page_png);
                run_imagemagick($magick_args);
                if (!file_exists($page_png)) continue;

                list($imgW, $imgH) = getimagesize($page_png);
                $newW = $imgW - $cropL - $cropR;
                $newH = $imgH - $cropT - $cropB;
                if ($newW <= 0 || $newH <= 0) throw new Exception("Marge de crop trop grande pour la page " . ($p + 1));

                $cropped = $tmpBase . 'crop_result_' . $p . '_' . uniqid() . '.png';
                run_imagemagick(escapeshellarg($page_png) . " -crop {$newW}x{$newH}+{$cropL}+{$cropT} +repage " . escapeshellarg($cropped));
                @unlink($page_png);
                if (file_exists($cropped)) $images[] = $cropped;
            }
        } else {
            // Image simple (PNG/JPG/etc.)
            $page_png = $tmpBase . 'crop_img_' . uniqid() . '.png';
            run_imagemagick(escapeshellarg($uploadedFile) . ' ' . escapeshellarg($page_png));
            list($imgW, $imgH) = getimagesize($page_png);
            $newW = $imgW - $cropL - $cropR;
            $newH = $imgH - $cropT - $cropB;
            if ($newW <= 0 || $newH <= 0) throw new Exception('Marge de crop trop grande.');
            $cropped = $tmpBase . 'crop_result_' . uniqid() . '.png';
            run_imagemagick(escapeshellarg($page_png) . " -crop {$newW}x{$newH}+{$cropL}+{$cropT} +repage " . escapeshellarg($cropped));
            @unlink($page_png);
            if (file_exists($cropped)) $images[] = $cropped;
        }

        if (empty($images)) throw new Exception('Aucune page rognée générée.');

        $outFilename = $safeName . '_cropped_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;
        $img_list = implode(' ', array_map('escapeshellarg', $images));
        run_imagemagick("$img_list " . escapeshellarg($outPath));

        foreach ($images as $img) @unlink($img);

        if (!file_exists($outPath)) throw new Exception('Erreur génération PDF rogné.');

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

if ($action === 'ocr_cleanup') {
    header('Content-Type: application/json');
    try {
        if (!$uploadedFile || !file_exists($uploadedFile)) throw new Exception('Fichier invalide ou manquant.');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Si c'est une image, on la convertit d'abord en PDF
        if ($ext !== 'pdf') {
            $newPdf = $tmpBase . 'temp_ocr_input_' . uniqid() . '.pdf';
            run_imagemagick(escapeshellarg($uploadedFile) . ' ' . escapeshellarg($newPdf));
            if (!file_exists($newPdf)) throw new Exception("Impossible de préparer l'image pour l'OCR.");
            $uploadedFile = $newPdf;
        }

        $lang = $_POST['lang'] ?? 'fra';
        $type = $_POST['type'] ?? 'skip_text'; // skip_text ou force_ocr
        $deskew = ($_POST['deskew'] ?? '0') === '1';
        $clean = ($_POST['clean'] ?? '0') === '1';
        $optimize = ($_POST['optimize'] ?? '0') === '1';

        $outFilename = $safeName . '_ocr_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;

        $toDocxFlow = ($_POST['to_docx_flow'] ?? '0') === '1';
        $sidecarPath = $tmpBase . 'sidecar_' . uniqid() . '.txt';

        // Construction de la commande ocrmypdf
        // Sur Windows : on appelle ocrmypdf via le Python embarqué (python -m ocrmypdf)
        // Sur Linux/macOS : on utilise la commande système ocrmypdf
        if (PHP_OS_FAMILY === 'Windows') {
            $pythonExe = get_python_path();
            $cmd = [escapeshellarg($pythonExe), '-m', 'ocrmypdf'];
            // Indiquer à ocrmypdf où trouver tesseract.exe
            $tesseractExe = get_tesseract_path();
            if ($tesseractExe !== 'tesseract') {
                $tessDir = dirname(realpath($tesseractExe) ?: $tesseractExe);
                // Ajouter le dossier de tesseract.exe au PATH pour qu'OCRmyPDF le trouve
                putenv('PATH=' . $tessDir . PATH_SEPARATOR . getenv('PATH'));
                // Définir TESSDATA_PREFIX si un dossier tessdata est adjacent
                $tessdata = $tessDir . DIRECTORY_SEPARATOR . 'tessdata';
                if (is_dir($tessdata)) {
                    putenv('TESSDATA_PREFIX=' . $tessdata);
                }
            }
        } else {
            $cmd = ['ocrmypdf'];
        }

        $ocrType = $_POST['type'] ?? 'skip_text';
        if ($ocrType === 'force_ocr') {
            $cmd[] = '--force-ocr';
        } else {
            $cmd[] = '--skip-text';
        }

        $cmd[] = '--output-type pdf';
        $cmd[] = '--pdf-renderer hocr';

        // Tesseract permet plusieurs langues (ex: fra+eng), ici on en passe une ou plusieurs
        $cleanLang = preg_replace('/[^a-z\+]/', '', strtolower($lang));
        if (empty($cleanLang)) $cleanLang = 'fra';
        $cmd[] = '--language ' . escapeshellarg($cleanLang);

        if ($deskew) $cmd[] = '--deskew';
        if ($clean) $cmd[] = '--clean';
        if ($optimize) $cmd[] = '--optimize 1';

        $cmd[] = escapeshellarg($uploadedFile);
        $cmd[] = escapeshellarg($outPath);

        $fullCmd = implode(' ', $cmd) . ' 2>&1';
        $output = shell_exec($fullCmd);

        if (!file_exists($outPath) || filesize($outPath) === 0) {
            throw new Exception("Erreur lors du traitement OCR. Logs : " . htmlspecialchars((string)$output, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        $toOdt = ($_POST['to_odt'] ?? '0') === '1';
        $toDocx = ($_POST['to_docx'] ?? '0') === '1';
        $toDocxDocling = ($_POST['to_docx_docling'] ?? '0') === '1';

        if ($toDocxDocling) {
            $outFilenameDocx = str_replace('.pdf', '_docling.docx', $outFilename);
            $docxPath = $tmpBase . $outFilenameDocx;

            $studioSettings = getStudioSettings();
            $doclingApiUrl = trim($studioSettings['studio_api_docling_url'] ?? '');

            if (!empty($doclingApiUrl)) {
                // Mode VPS : envoyer le PDF en Base64 au serveur Docling distant
                $pdfData = base64_encode((string)file_get_contents($outPath));
                $ch = curl_init($doclingApiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['pdf' => $pdfData, 'filename' => basename($outPath)]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                $vpsResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200 || !$vpsResponse) {
                    throw new Exception("L'API Docling VPS est inaccessible (HTTP $httpCode). Vérifiez studio_api_docling_url dans les réglages.");
                }
                $vpsData = json_decode($vpsResponse, true);
                if ($vpsData && isset($vpsData['docx'])) {
                    file_put_contents($docxPath, base64_decode($vpsData['docx']));
                } else {
                    // Réponse binaire directe
                    file_put_contents($docxPath, $vpsResponse);
                }
            } else {
                // Mode local (Fallback sans API)
                $doclingScript = __DIR__ . '/scripts/docling_copyfit.py';
                $pythonEnv = get_python_path();
                $envPrefix = get_hf_home_env();
                $doclingCmd = $envPrefix . escapeshellarg($pythonEnv) . ' ' . escapeshellarg($doclingScript)
                    . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
                $doclingOutput = shell_exec($doclingCmd);

                if (!file_exists($docxPath) || filesize($docxPath) === 0) {
                    throw new Exception("Aucune URL Docling VPS configurée et l'environnement local est indisponible. Configurez studio_api_docling_url dans les réglages IA.");
                }
            }

            if (!file_exists($docxPath) || filesize($docxPath) === 0) {
                throw new Exception("Erreur lors de l'extraction Copyfit IA (Docling). Vérifiez les logs serveur.");
            }

            $outFilename = $outFilenameDocx;
            $outPath = $docxPath;
        } else if ($toDocxFlow) {
            // Extraction du texte depuis le PDF de sortie (qui contient l'original + l'OCR)
            $pdftotextExe = get_pdftotext_path();
            shell_exec(escapeshellarg($pdftotextExe) . ' -enc UTF-8 ' . escapeshellarg($outPath) . ' ' . escapeshellarg($sidecarPath));
            
            if (!file_exists($sidecarPath) || filesize($sidecarPath) === 0) {
                throw new Exception("Aucun texte n'a pu être extrait du document.");
            }
            
            $outFilenameDocx = str_replace('.pdf', '_fluide.docx', $outFilename);
            $docxPath = $tmpBase . $outFilenameDocx;
            
            $pythonEnv = get_python_path();
            $textToDocxScript = __DIR__ . '/scripts/text_to_docx.py';

            $docxCmd = escapeshellarg($pythonEnv) . ' ' . escapeshellarg($textToDocxScript) . ' ' . escapeshellarg($sidecarPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
            $docxOutput = shell_exec($docxCmd);
            
            @unlink($sidecarPath);
            
            if (file_exists($docxPath) && filesize($docxPath) > 0) {
                $outFilename = $outFilenameDocx;
                $outPath = $docxPath;
            } else {
                throw new Exception("Erreur lors de la conversion du texte fluide. Logs : " . htmlspecialchars($docxOutput));
            }
            
        } else if ($toDocx) {
            // Conversion en DOCX via pdf2docx
            $outFilenameDocx = str_replace('.pdf', '.docx', $outFilename);
            $docxPath = $tmpBase . $outFilenameDocx;
            
            $pythonEnv = get_python_path();
            $pdfToDocxScript = __DIR__ . '/scripts/pdf_to_docx.py';

            $docxCmd = escapeshellarg($pythonEnv) . ' ' . escapeshellarg($pdfToDocxScript) . ' ' . escapeshellarg($outPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
            $docxOutput = shell_exec($docxCmd);
            
            if (file_exists($docxPath) && filesize($docxPath) > 0) {
                $outFilename = $outFilenameDocx;
                $outPath = $docxPath;
            } else {
                throw new Exception("Erreur lors de la conversion DOCX par pdf2docx. Logs : " . htmlspecialchars($docxOutput));
            }
        }

        echo json_encode([
            'success'      => true,
            'download_url' => '?download_studio&file=' . urlencode($outFilename),
            'filename'     => $outFilename
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : MODIFICATION ===
if ($action === 'modification') {
    try {
        require_once __DIR__ . '/../models/pdf_utils.php';

        $dataRaw = $_POST['data'] ?? '{}';
        $data = json_decode($dataRaw, true);
        if (!$data) throw new Exception("Données de modification invalides.");
        
        $scope = $data['scope'] ?? 'current';
        $currentPage = intval($data['currentPage'] ?? 1);
        $ops = $data['operations'] ?? [];

        // Gestion du fichier source (via file_id bibliothèque ou upload)
        $sourcePdf = null;
        if (isset($_POST['file_id']) && !empty($_POST['file_id'])) {
            require_once __DIR__ . '/../models/bibliotheque.php';
            $fileInfo = get_bibliotheque_file($_POST['file_id']);
            if ($fileInfo && isset($fileInfo['path']) && file_exists($fileInfo['path'])) {
                $sourcePdf = $fileInfo['path'];
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileInfo['filename'], PATHINFO_FILENAME));
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $sourcePdf = $_FILES['file']['tmp_name'];
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
        }

        if (!$sourcePdf || !file_exists($sourcePdf)) {
            throw new Exception("Fichier source introuvable.");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $sourcePdf);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') throw new Exception("Le fichier doit être un PDF.");

        $pdf = new TCPDI();
        $pdf->SetCreator('Dupli Studio');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        
        $pageCount = $pdf->setSourceFile($sourcePdf);
        
        for ($p = 1; $p <= $pageCount; $p++) {
            $tpl = $pdf->importPage($p);
            $size = $pdf->getTemplateSize($tpl);
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
            
            // Vérifier si la portée s'applique à cette page
            $apply = false;
            if ($scope === 'all') $apply = true;
            elseif ($scope === 'even' && $p % 2 === 0) $apply = true;
            elseif ($scope === 'odd' && $p % 2 !== 0) $apply = true;
            elseif ($scope === 'current' && $p === $currentPage) $apply = true;
            
            if ($apply) {
                foreach ($ops as $op) {
                    $type = $op['type'] ?? '';
                    if ($type === 'redact_text' || $type === 'strikeout') {
                        $x = ($op['relX'] ?? 0) * $size['width'];
                        $y = ($op['relY'] ?? 0) * $size['height'];
                        $w = ($op['relW'] ?? 0) * $size['width'];
                        $h = ($op['relH'] ?? 0) * $size['height'];
                        
                        if ($type === 'redact_text') {
                            addTextAndBox($pdf, $x, $y, $w, $h, 
                                          $op['text'], $op['font'] ?? 'helvetica', intval($op['size'] ?? 12), $op['bg']);
                        } else {
                            addRedaction($pdf, $x, $y, $w, $h, $op['color'] ?? 'black');
                        }
                    } elseif ($type === 'page_number') {
                        $rangeStart = isset($op['rangeStart']) && $op['rangeStart'] !== null ? intval($op['rangeStart']) : 1;
                        $rangeEnd = isset($op['rangeEnd']) && $op['rangeEnd'] !== null ? intval($op['rangeEnd']) : $pageCount;
                        $firstVal = isset($op['firstVal']) && $op['firstVal'] !== null ? intval($op['firstVal']) : 1;
                        
                        if ($p >= $rangeStart && $p <= $rangeEnd) {
                            $currentNum = $firstVal + ($p - $rangeStart);
                            $text = str_replace('{p}', $currentNum, $op['format']);
                            $text = str_replace('{t}', $pageCount, $text);
                            $pos = $op['position'] ?? 'bottom_center';
                            $margin = floatval($op['margin'] ?? 10);
                            
                            // Default size estimation to center roughly
                            $pdf->SetFont($op['font'] ?? 'helvetica', '', intval($op['size'] ?? 12));
                            $textWidth = $pdf->GetStringWidth($text);
                            
                            $x = 0; $y = 0;
                            if ($pos === 'custom') {
                                $x = ($op['relX'] ?? 0) * $size['width'];
                                $y = ($op['relY'] ?? 0) * $size['height'];
                            } else {
                                if (strpos($pos, 'bottom') !== false) {
                                    $y = $size['height'] - $margin;
                                } else {
                                    $y = $margin; // top
                                }
                                
                                if (strpos($pos, 'left') !== false) {
                                    $x = $margin;
                                } elseif (strpos($pos, 'right') !== false) {
                                    $x = $size['width'] - $margin - $textWidth;
                                } else { // center
                                    $x = ($size['width'] - $textWidth) / 2;
                                }
                            }
                            
                            addCustomPageNumber($pdf, $x, $y, $text, $op['font'] ?? 'helvetica', intval($op['size'] ?? 12));
                        }
                    }
                }
            }
        }
        
        $outFilename = $safeName . '_modif_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;
        $pdf->Output($outPath, 'F');
        
        if (!file_exists($outPath)) throw new Exception("Erreur lors de la génération du PDF modifié.");
        
        echo json_encode([
            'success'      => true,
            'result'       => ['pdf_url' => '?download_studio&file=' . urlencode($outFilename)],
            'download_url' => '?download_studio&file=' . urlencode($outFilename)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : LIST_FONTS ===
if ($action === 'list_fonts') {
    $fontsDir = __DIR__ . '/../public/custom_fonts';
    if (!is_dir($fontsDir)) @mkdir($fontsDir, 0777, true);
    
    $fonts = [];
    foreach (glob($fontsDir . '/*.{ttf,otf}', GLOB_BRACE) as $file) {
        $basename = basename($file);
        $name = pathinfo($basename, PATHINFO_FILENAME);
        $fonts[] = [
            'name' => $name,
            'url' => 'custom_fonts/' . $basename
        ];
    }
    echo json_encode(['success' => true, 'fonts' => $fonts]);
    exit;
}

// === ACTION : UPLOAD_FONT ===
if ($action === 'upload_font') {
    try {
        if (!isset($_FILES['font']) || $_FILES['font']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erreur lors de l'upload de la police.");
        }
        
        $tmpName = $_FILES['font']['tmp_name'];
        $originalName = $_FILES['font']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['ttf', 'otf'])) {
            throw new Exception("Seuls les formats .ttf et .otf sont supportés.");
        }
        
        $fontsDir = __DIR__ . '/../public/custom_fonts';
        if (!is_dir($fontsDir)) @mkdir($fontsDir, 0777, true);
        
        // Clean filename
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $destPath = $fontsDir . '/' . $safeName . '.' . $ext;
        
        if (!move_uploaded_file($tmpName, $destPath)) {
            throw new Exception("Impossible de sauvegarder la police.");
        }
        
        // Convert for TCPDF
        require_once __DIR__ . '/../vendor/autoload.php';
        $fontname = TCPDF_FONTS::addTTFfont($destPath, 'TrueTypeUnicode', '', 96);
        if (!$fontname) {
            // Rollback
            @unlink($destPath);
            throw new Exception("Le moteur interne a rejeté cette police.");
        }
        
        echo json_encode(['success' => true, 'font_name' => $fontname, 'url' => 'custom_fonts/' . basename($destPath)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : READ_METADATA ===
if ($action === 'read_metadata') {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Fichier introuvable.");
        }
        $file = escapeshellarg($_FILES['file']['tmp_name']);
        $exiftoolExe = escapeshellarg(get_exiftool_path());
        $cmd = "$exiftoolExe -json $file 2>&1";
        $output = shell_exec($cmd);
        $data = json_decode($output, true);
        if ($data && is_array($data) && count($data) > 0) {
            echo json_encode(['success' => true, 'metadata' => $data[0]]);
        } else {
            throw new Exception("Impossible de lire les métadonnées.");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : UPDATE_METADATA ===
if ($action === 'update_metadata') {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Fichier introuvable.");
        }
        
        $fields = ['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreateDate', 'ModifyDate'];
        $args = [];
        
        if (!empty($_POST['clear_all'])) {
            $args[] = "-all=";
        } else {
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    $val = $_POST[$f];
                    $args[] = "-" . $f . "=" . escapeshellarg($val);
                }
            }
        }
        
        if (empty($args)) {
            echo json_encode(['success' => true]);
            exit;
        }
        
        global $tmpBase;
        
        $tmpCopy = $tmpBase . 'meta_' . uniqid() . '.pdf';
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpCopy) && !copy($_FILES['file']['tmp_name'], $tmpCopy)) {
            throw new Exception("Erreur copie temporaire.");
        }
        
        $exiftoolExe = escapeshellarg(get_exiftool_path());
        $cmd = "$exiftoolExe -overwrite_original " . implode(" ", $args) . " " . escapeshellarg($tmpCopy) . " 2>&1";
        $output = shell_exec($cmd);
        
        // Output file to download directory
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
        if (empty($safeName)) $safeName = 'document';
        $outFilename = $safeName . '_meta_' . time() . '.pdf';
        $outPath = $tmpBase . $outFilename;
        
        rename($tmpCopy, $outPath);
        
        if (!file_exists($outPath)) {
            throw new Exception("Erreur d'écriture : " . $output);
        }
        
        echo json_encode([
            'success' => true, 
            'download_url' => '?download_studio&file=' . urlencode($outFilename)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : RECOGNIZE_FONT ===
if ($action === 'recognize_font') {
    try {
        if (!isset($_POST['image'])) {
            throw new Exception("Aucune image (Base64) reçue.");
        }
        $imgData = $_POST['image'];
        if (strpos($imgData, ',') !== false) {
            $imgData = explode(',', $imgData)[1];
        }
        $imgDecoded = base64_decode($imgData);
        if ($imgDecoded === false) {
            throw new Exception("L'image n'a pas pu être décodée.");
        }

        $studioSettings = getStudioSettings();
        $fontsApiUrl = trim($studioSettings['studio_api_fonts_url'] ?? '');

        if (!empty($fontsApiUrl)) {
            // Mode VPS : envoyer l'image en Base64 au serveur distant
            $ch = curl_init($fontsApiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['image' => base64_encode($imgDecoded)]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $vpsResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$vpsResponse) {
                throw new Exception("L'API de reconnaissance de police est inaccessible (HTTP $httpCode). Vérifiez studio_api_fonts_url dans les réglages.");
            }
            $resultData = json_decode($vpsResponse, true);
            if ($resultData === null) {
                throw new Exception("Réponse invalide du serveur VPS.");
            }
            echo json_encode(['success' => true, 'fonts' => $resultData]);
        } else {
            // Mode local : appel Python (venv local ou Python embarqué Windows)
            global $tmpBase;
            if (empty($tmpBase)) {
                $tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
            }
            $tmpImagePath = $tmpBase . 'font_crop_' . uniqid() . '.png';
            file_put_contents($tmpImagePath, $imgDecoded);

            $pythonScript = __DIR__ . '/scripts/font_recognizer.py';
            $pythonEnv = get_python_path();
            $envPrefix = get_hf_home_env();
            $cmd = $envPrefix . escapeshellarg($pythonEnv) . ' ' . escapeshellarg($pythonScript) . ' ' . escapeshellarg($tmpImagePath) . ' 2>&1';

            $output = shell_exec($cmd);
            @unlink($tmpImagePath);

            $resultData = json_decode($output, true);
            if ($resultData === null) {
                throw new Exception("Erreur de l'IA (JSON invalide): " . $output);
            }
            if (isset($resultData['error'])) {
                throw new Exception("Erreur Python: " . $resultData['error']);
            }
            echo json_encode(['success' => true, 'fonts' => $resultData]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// === ACTION : DOWNLOAD_GOOGLE_FONT ===
if ($action === 'download_google_font') {
    try {
        $fontNameRaw = $_POST['font_name'] ?? '';
        if (empty($fontNameRaw)) throw new Exception("Nom de police manquant.");
        
        $url = 'https://fonts.googleapis.com/css?family=' . urlencode($fontNameRaw);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // User-Agent ancien pour forcer Google Fonts à renvoyer du TTF au lieu de WOFF2
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0');
        $css = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($css)) {
            throw new Exception("offline"); // Mot-clé pour déclencher l'alerte spécifique JS
        }
        
        if (!preg_match('/url\((https:\/\/[^)]+\.ttf)\)/i', $css, $matches)) {
             throw new Exception("offline"); // On considère comme erreur de téléchargement
        }
        $ttfUrl = $matches[1];
        
        // Timeout de 15s max pour le téléchargement
        $ctx = stream_context_create(['http' => ['timeout' => 15]]);
        $ttfData = @file_get_contents($ttfUrl, false, $ctx);
        if (empty($ttfData)) {
            throw new Exception("offline");
        }
        
        $fontsDir = __DIR__ . '/../public/custom_fonts';
        if (!is_dir($fontsDir)) @mkdir($fontsDir, 0777, true);
        
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fontNameRaw);
        $destPath = $fontsDir . '/' . $safeName . '.ttf';
        
        if (file_put_contents($destPath, $ttfData) === false) {
             throw new Exception("Impossible de sauvegarder le fichier TTF.");
        }
        
        require_once __DIR__ . '/../vendor/autoload.php';
        $fontname = TCPDF_FONTS::addTTFfont($destPath, 'TrueTypeUnicode', '', 96);
        if (!$fontname) {
             @unlink($destPath);
             throw new Exception("Erreur de conversion TCPDF.");
        }
        
        echo json_encode(['success' => true, 'font_name' => $fontname, 'url' => 'custom_fonts/' . basename($destPath)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'errors' => ['Action inconnue : ' . htmlspecialchars($action)]]);
