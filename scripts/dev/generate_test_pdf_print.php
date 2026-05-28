<?php
/**
 * Script pour générer un PDF de test pour les impressions
 */

// Définir les constantes curl manuellement si l'extension n'est pas chargée
if (!extension_loaded('curl')) {
    if (!defined('CURLOPT_CONNECTTIMEOUT')) {
        define('CURLOPT_CONNECTTIMEOUT', 78);
        define('CURLOPT_TIMEOUT', 13);
        define('CURLOPT_RETURNTRANSFER', 19913);
        define('CURLOPT_FOLLOWLOCATION', 52);
        define('CURLOPT_SSL_VERIFYPEER', 64);
        define('CURLOPT_SSL_VERIFYHOST', 81);
        define('CURLOPT_USERAGENT', 10018);
    }
}

require_once(__DIR__ . '/app/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

$format = $argv[1] ?? 'A4';
$pages = (int)($argv[2] ?? 1);
$output = $argv[3] ?? '';
$testName = $argv[4] ?? 'Test';

if (empty($output)) {
    die("Usage: php generate_test_pdf_print.php <format> <pages> <output> <testName>\n");
}

// Créer un nouveau PDF - utiliser le format directement comme chaîne comme dans generate_test_pdf.php
$pdf = new TCPDI('P', 'mm', $format);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(150, 150, 150);

for ($i = 1; $i <= $pages; $i++) {
    $pdf->AddPage();
    $pdf->SetXY(20, 20);
    $pdf->Cell(0, 0, $testName . ' - Page ' . $i, 0, 0);
}

$pdf->Output($output, 'F');
echo $output;

