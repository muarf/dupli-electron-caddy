<?php
// Test de la prévisualisation
echo "=== Test de la prévisualisation ===\n\n";

require_once(__DIR__ . '/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

// Créer un PDF de test
$pdf = new TCPDI('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Test PDF A4 pour prévisualisation', 0, 1, 'C');
$pdf->Output(__DIR__ . '/test_preview_source.pdf', 'F');

echo "PDF de test créé\n\n";

// Test de soumission du formulaire
echo "Test de l'imposition avec prévisualisation...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://13.48.138.100/?imposition_tracts');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'pdf_file' => new CURLFile(__DIR__ . '/test_preview_source.pdf', 'application/pdf', 'test_preview_source.pdf'),
    'manual_format' => 'auto',
    'force_resize' => '0',
    'cut_margin' => '2',
    'submit' => '1'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP: $httpCode\n";

// Vérifier la prévisualisation
if (strpos($response, 'Prévisualisation') !== false) {
    echo "✅ Section prévisualisation trouvée\n";
} else {
    echo "❌ Pas de section prévisualisation\n";
}

if (strpos($response, 'view_pdf.php') !== false) {
    echo "✅ URL de prévisualisation trouvée\n";
} else {
    echo "❌ Pas d'URL de prévisualisation\n";
}

if (strpos($response, 'iframe') !== false) {
    echo "✅ Iframe de prévisualisation trouvée\n";
} else {
    echo "❌ Pas d'iframe de prévisualisation\n";
}

// Nettoyer
unlink(__DIR__ . '/test_preview_source.pdf');

echo "\n=== Résumé ===\n";
echo "Test de prévisualisation effectué.\n";
?>




