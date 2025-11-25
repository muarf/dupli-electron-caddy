<?php
/**
 * Script pour générer un PDF de test avec 32 pages en format A6
 * Chaque page affiche son numéro de page en grand au centre
 */

require_once(__DIR__ . '/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

// Créer un nouveau PDF en format A6 (105 x 148 mm)
$pdf = new TCPDI('P', 'mm', 'A6');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Générer 32 pages
for ($page = 1; $page <= 32; $page++) {
    $pdf->AddPage();
    
    // Définir la police en grande taille (un peu plus petite que pour A5 car A6 est plus petit)
    $pdf->SetFont('helvetica', 'B', 60); // Taille 60 pour A6
    
    // Obtenir les dimensions de la page
    $pageWidth = $pdf->getPageWidth();
    $pageHeight = $pdf->getPageHeight();
    
    // Calculer la position pour centrer le texte
    $text = (string)$page;
    $textWidth = $pdf->GetStringWidth($text);
    $textHeight = 60 / 2.83465; // Conversion approximative de points en mm
    
    $x = ($pageWidth - $textWidth) / 2;
    $y = ($pageHeight - $textHeight) / 2;
    
    // Dessiner le numéro de page au centre
    $pdf->SetXY($x, $y);
    $pdf->Cell(0, 0, $text, 0, 0, 'C');
}

// Sauvegarder le PDF
$outputPath = __DIR__ . '/test_32_pages_a6.pdf';
$pdf->Output($outputPath, 'F');

echo "PDF généré avec succès : $outputPath\n";
echo "Le fichier contient 32 pages en format A6 avec les numéros de page affichés au centre.\n";

