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
