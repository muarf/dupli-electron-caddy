<?php
/**
 * Script pour générer un PDF de test avec 16 pages
 * Chaque page affiche son numéro de page en grand au centre
 */

require_once(__DIR__ . '/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

// Créer un nouveau PDF
$pdf = new TCPDI('P', 'mm', 'A5'); // Format A5 (148 x 210 mm)
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Générer 16 pages
for ($page = 1; $page <= 16; $page++) {
    $pdf->AddPage();
    
    // Définir la police en grande taille
    $pdf->SetFont('helvetica', 'B', 72); // Taille 72 pour un gros numéro
    
    // Obtenir les dimensions de la page
    $pageWidth = $pdf->getPageWidth();
    $pageHeight = $pdf->getPageHeight();
    
    // Calculer la position pour centrer le texte
    $text = (string)$page;
    $textWidth = $pdf->GetStringWidth($text);
    $textHeight = 72 / 2.83465; // Conversion approximative de points en mm
    
    $x = ($pageWidth - $textWidth) / 2;
    $y = ($pageHeight - $textHeight) / 2;
    
    // Dessiner le numéro de page au centre
    $pdf->SetXY($x, $y);
    $pdf->Cell(0, 0, $text, 0, 0, 'C');
}

// Sauvegarder le PDF
$outputPath = __DIR__ . '/test_16_pages.pdf';
$pdf->Output($outputPath, 'F');

echo "PDF généré avec succès : $outputPath\n";
echo "Le fichier contient 16 pages avec les numéros de page affichés au centre.\n";

