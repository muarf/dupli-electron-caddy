<?php
// Test de soumission du formulaire avec un vrai PDF
echo "=== Test de soumission du formulaire ===\n\n";

// Créer un PDF de test
require_once(__DIR__ . '/vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

$pdf = new TCPDI('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Test PDF A4 pour imposition tracts', 0, 1, 'C');
$pdf->Output(__DIR__ . '/test_form.pdf', 'F');

echo "PDF de test créé\n\n";

// Test de soumission du formulaire
echo "Test de soumission du formulaire...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://13.48.138.100/?imposition_tracts');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'pdf_file' => new CURLFile(__DIR__ . '/test_form.pdf', 'application/pdf', 'test_form.pdf'),
    'manual_format' => 'auto',
    'force_resize' => '0',
    'cut_margin' => '2',
    'submit' => '1'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP: $httpCode\n";

// Vérifier si on a les éléments de succès
if (strpos($response, 'alert-success') !== false) {
    echo "✅ Zone de succès trouvée\n";
} else {
    echo "❌ Pas de zone de succès\n";
}

if (strpos($response, 'Télécharger le PDF optimisé') !== false) {
    echo "✅ Bouton de téléchargement trouvé\n";
} else {
    echo "❌ Pas de bouton de téléchargement\n";
}

if (strpos($response, 'alert-danger') !== false) {
    echo "⚠️  Zone d'erreur trouvée\n";
}

// Vérifier les variables PHP
if (strpos($response, '$download_url') !== false) {
    echo "⚠️  Variable \$download_url non résolue\n";
}

if (strpos($response, '$success') !== false) {
    echo "⚠️  Variable \$success non résolue\n";
}

// Vérifier le debug
if (strpos($response, 'alert-info') !== false) {
    echo "🔍 Zone de debug trouvée\n";
    // Extraire le message de debug
    preg_match('/<div class="alert alert-info".*?<p>(.*?)<\/p>/s', $response, $matches);
    if (isset($matches[1])) {
        echo "Debug: " . strip_tags($matches[1]) . "\n";
    }
}

// Nettoyer
unlink(__DIR__ . '/test_form.pdf');

echo "\n=== Résumé ===\n";
echo "Test de soumission du formulaire effectué.\n";
?>
