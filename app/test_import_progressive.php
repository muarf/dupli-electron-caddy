<?php
require_once 'vendor/autoload.php';
use setasign\Fpdi\TcpdfFpdi as TCPDI;

$pdfFile = '/tmp/duplicator/uploaded_20251013033915.pdf';
$pdfPreview = new TCPDI();
$pageCount = $pdfPreview->setSourceFile($pdfFile);
$pdfPreview->setPrintHeader(false);
$pdfPreview->setPrintFooter(false);

echo "Nombre total de pages source: $pageCount\n";
echo "Pages dans preview après setSourceFile: " . $pdfPreview->getNumPages() . "\n";

// Créer 10 pages A3 paysage
for ($i = 1; $i <= 10; $i++) {
    $pdfPreview->AddPage('L', [420, 297]);
    echo "Après AddPage $i: " . $pdfPreview->getNumPages() . " pages\n";
}

echo "\nTotal final: " . $pdfPreview->getNumPages() . " pages\n";
