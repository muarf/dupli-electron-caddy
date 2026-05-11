<?php
require_once __DIR__ . '/../app/controler/functions/binary_utilities.php';

$pdfFile = __DIR__ . '/../app/bibliotheque/files/pdf/Blanqui ou l_insurrection d_Etat.pdf';

if (!file_exists($pdfFile)) {
    die("Fichier non trouvé : $pdfFile\n");
}

$gsPath = get_ghostscript_path();
echo "Binaire GS : $gsPath\n";

// Commande pour détecter la couleur sur les 5 premières pages (pour aller vite)
$cmd = $gsPath . " -q -o - -sDEVICE=inkcov -dFirstPage=1 -dLastPage=5 " . escapeshellarg($pdfFile);

echo "Exécution de la détection de couleur (5 premières pages)...\n";
exec($cmd, $output, $returnVar);

$isColor = false;
foreach ($output as $line) {
    if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+CMYK\s+OK/', $line, $matches)) {
        $c = floatval($matches[1]);
        $m = floatval($matches[2]);
        $y = floatval($matches[3]);
        
        echo "Page : C=$c M=$m Y=$y\n";
        
        if ($c > 0 || $m > 0 || $y > 0) {
            $isColor = true;
        }
    }
}

if ($isColor) {
    echo "Résultat : DOCUMENT COULEUR\n";
} else {
    echo "Résultat : DOCUMENT NOIR & BLANC\n";
}
