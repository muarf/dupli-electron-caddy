<?php
require_once __DIR__ . '/../app/controler/functions/binary_utilities.php';

$pdfFile = __DIR__ . '/../app/bibliotheque/files/pdf/Blanqui ou l_insurrection d_Etat.pdf';

if (!file_exists($pdfFile)) {
    die("Fichier non trouvé : $pdfFile\n");
}

$gsPath = get_ghostscript_path();
echo "Binaire GS : $gsPath\n";

// Commande pour extraire la MediaBox de la page 1
// Le format de sortie sera [x_min y_min x_max y_max]
$cmd = $gsPath . " -q -dNODISPLAY -dNOSAFER -c \"(" . addslashes($pdfFile) . ") (r) file runpdfbegin 1 pdfgetpage /MediaBox get == quit\"";

echo "Exécution de la commande...\n";
exec($cmd, $output, $returnVar);

if ($returnVar === 0 && !empty($output)) {
    $bbox = trim($output[0], "[] ");
    $parts = preg_split('/\s+/', $bbox);
    
    if (count($parts) >= 4) {
        $widthPts = floatval($parts[2]) - floatval($parts[0]);
        $heightPts = floatval($parts[3]) - floatval($parts[1]);
        
        $widthMm = round($widthPts * 0.352778);
        $heightMm = round($heightPts * 0.352778);
        
        echo "Dimensions détectées : {$widthPts}x{$heightPts} pts\n";
        echo "Dimensions en mm : {$widthMm}x{$heightMm} mm\n";
        
        // Déduction du format
        if (($widthMm == 210 && $heightMm == 297) || ($widthMm == 297 && $heightMm == 210)) {
            echo "Format détecté : A4\n";
        } elseif (($widthMm == 297 && $heightMm == 420) || ($widthMm == 420 && $heightMm == 297)) {
            echo "Format détecté : A3\n";
        } else {
            echo "Format détecté : Spécifique ({$widthMm}x{$heightMm}mm)\n";
        }
    }
} else {
    echo "Erreur lors de l'analyse : " . implode("\n", $output) . "\n";
}
