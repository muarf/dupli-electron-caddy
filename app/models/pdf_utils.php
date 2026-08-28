<?php
require_once(__DIR__ . '/../vendor/autoload.php');
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

    $paddedPdf = new TCPDI();
    $paddedPdf->setPrintHeader(false);
    $paddedPdf->setPrintFooter(false);
    $paddedPdf->setSourceFile($pdfFilePath);

    $lastSize = null;
    for ($i = 1; $i <= $pageCount; $i++) {
        $templateId = $paddedPdf->importPage($i);
        $size = $paddedPdf->getTemplateSize($templateId);
        if ($size) {
            $lastSize = $size;
            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $paddedPdf->AddPage($orientation, [$size['width'], $size['height']]);
            $paddedPdf->useTemplate($templateId);
        } else {
            $paddedPdf->AddPage();
        }
    }

    for ($j = 0; $j < $pagesToAdd; $j++) {
        if ($lastSize) {
            $orientation = ($lastSize['width'] > $lastSize['height']) ? 'L' : 'P';
            $paddedPdf->AddPage($orientation, [$lastSize['width'], $lastSize['height']]);
        } else {
            $paddedPdf->AddPage();
        }
    }

    $tempDir = resolveTempDir() . DIRECTORY_SEPARATOR;
    $tempFile = $tempDir . 'padded_' . uniqid() . '_' . basename($pdfFilePath);
    $paddedPdf->Output($tempFile, 'F');

    return [
        'file' => $tempFile,
        'page_count' => $pageCount + $pagesToAdd,
        'temp_file' => $tempFile
    ];
}
}

/**
 * Ajoute le numéro de page sur le PDF
 */
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

/**
 * Ajoute un texte (avec ou sans fond blanc) à des coordonnées précises.
 */
if (!function_exists('addTextAndBox')) {
function addTextAndBox($pdf, $x, $y, $w, $h, $text, $font, $fontSize, $bg) {
    $pdf->setAutoPageBreak(false);
    $pdf->SetFont($font, '', $fontSize);
    
    if ($bg) {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $w, $h, 'F');
    }
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($w, $h, $text, 0, 'L', false, 1, '', '', true, 0, false, true, 0, 'T', false);
}
}

/**
 * Ajoute un numéro de page libre.
 */
if (!function_exists('addCustomPageNumber')) {
function addCustomPageNumber($pdf, $x, $y, $text, $font, $fontSize) {
    $pdf->setAutoPageBreak(false);
    $pdf->SetFont($font, '', $fontSize);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($x, $y);
    // On dessine le texte (pas de fond, largeur dynamique simple)
    $pdf->Cell(0, 0, $text, 0, 0, 'L', false, '', 0, false, 'T', 'M');
}
}

/**
 * Ajoute une biffure (caviardage).
 */
if (!function_exists('addRedaction')) {
function addRedaction($pdf, $x, $y, $w, $h, $color) {
    $pdf->setAutoPageBreak(false);
    
    if ($color === 'black') {
        $pdf->SetFillColor(0, 0, 0);
    } elseif ($color === 'white') {
        $pdf->SetFillColor(255, 255, 255);
    } elseif ($color === 'red') {
        $pdf->SetFillColor(255, 0, 0);
    } elseif (preg_match('/^#([a-f0-9]{3}){1,2}$/i', $color)) {
        $hex = ltrim($color, '#');
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $pdf->SetFillColor($r, $g, $b);
    } else {
        $pdf->SetFillColor(0, 0, 0);
    }
    
    $pdf->Rect($x, $y, $w, $h, 'F');
}
}

/**
 * Ajoute une zone négative (inverse les couleurs).
 */
if (!function_exists('addNegativeBox')) {
function addNegativeBox($pdf, $x, $y, $w, $h) {
    $pdf->setAutoPageBreak(false);
    $pdf->SetAlpha(1, 'Difference');
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($x, $y, $w, $h, 'F');
    $pdf->SetAlpha(1, 'Normal');
}
}

/**
 * Downgrade un PDF vers la version 1.4 en utilisant Ghostscript.
 * Utile pour contourner l'erreur de compression de FPDI.
 */
if (!function_exists('downgradePdfTo14')) {
function downgradePdfTo14($inputFile) {
    require_once __DIR__ . '/../controler/functions/binary_utilities.php';
    $gsPath = escapeshellarg(get_ghostscript_path());
    
    $tempDir = resolveTempDir() . DIRECTORY_SEPARATOR;
    $outputFile = $tempDir . 'downgraded_' . uniqid() . '_' . basename($inputFile);
    
    $input = escapeshellarg($inputFile);
    $output = escapeshellarg($outputFile);
    $cmd = "$gsPath -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=$output $input 2>&1";
    exec($cmd, $out, $ret);
    
    return ($ret === 0 && file_exists($outputFile)) ? $outputFile : false;
}
}
