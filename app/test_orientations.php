<?php
// Test des orientations A3 selon le format
echo "=== Test des orientations A3 ===\n\n";

require_once(__DIR__ . '/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

function testFormat($format, $description) {
    echo "--- Test $format ($description) ---\n";
    
    // Créer un PDF source
    $sourcePdf = new TCPDI('P', 'mm', $format);
    $sourcePdf->AddPage();
    $sourcePdf->SetFont('helvetica', 'B', 16);
    $sourcePdf->Cell(0, 10, "TEST $format SOURCE", 0, 1, 'C');
    $sourceFile = __DIR__ . "/test_source_$format.pdf";
    $sourcePdf->Output($sourceFile, 'F');
    
    // Test de soumission
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://13.48.138.100/?imposition_tracts');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'pdf_file' => new CURLFile($sourceFile, 'application/pdf', "test_$format.pdf"),
        'manual_format' => $format,
        'force_resize' => '0',
        'cut_margin' => '2',
        'submit' => '1'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Code HTTP: $httpCode\n";
    
    if (strpos($response, 'alert-success') !== false) {
        echo "✅ Succès\n";
    } else {
        echo "❌ Échec\n";
    }
    
    if (strpos($response, 'Télécharger le PDF optimisé') !== false) {
        echo "✅ Bouton téléchargement\n";
    } else {
        echo "❌ Pas de bouton téléchargement\n";
    }
    
    if (strpos($response, 'Prévisualisation') !== false) {
        echo "✅ Prévisualisation\n";
    } else {
        echo "❌ Pas de prévisualisation\n";
    }
    
    // Nettoyer
    unlink($sourceFile);
    echo "\n";
}

// Tester les différents formats
testFormat('A4', 'A3 en paysage');
testFormat('A5', 'A3 en portrait');
testFormat('A6', 'A3 en paysage');

echo "=== Résumé ===\n";
echo "Tests d'orientation terminés.\n";
?>




