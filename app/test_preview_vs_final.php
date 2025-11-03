<?php
// Test pour comparer preview vs final A6

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
require_once 'models/imposition.php';

use setasign\Fpdi\Tcpdf\Fpdi;

echo "=== COMPARAISON PREVIEW VS FINAL A6 ===\n\n";

// Utiliser le PDF de test
$testPdfFile = '/root/dupli-php-dev/downloads/test_16_pages_fixed.pdf';

// Créer les objets PDF
$pdfFinal = new Fpdi();
$pdfPreview = new Fpdi();

$pageCount = $pdfFinal->setSourceFile($testPdfFile);
$pdfPreview->setSourceFile($testPdfFile);

echo "1. Nombre de pages : $pageCount\n";

// Pré-importer tous les templates pour le preview
$template_ids_preview = [];
for ($page_num = 1; $page_num <= $pageCount; $page_num++) {
    $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
}

// Tester l'ordre des pages A6
$ordered_pages = reordering_pages_a6($pageCount);
echo "2. Ordre des pages : " . implode(', ', $ordered_pages) . "\n";

// Dimensions A6
$page_width = 105;
$page_height = 148;
$a3_width = 420;
$a3_height = 297;
$gutter_width = 5;

// Créer la page recto FINAL
$pdfFinal->AddPage('L', [$a3_width, $a3_height]);

// Calculer l'offset pour centrer la grille 2x4
$grid_width = 4 * $page_width + (3 * $gutter_width);
$grid_height = 2 * $page_height + $gutter_width;
$global_x_offset = ($a3_width - $grid_width) / 2;
$global_y_offset = ($a3_height - $grid_height) / 2;

echo "3. Grille : {$grid_width}x{$grid_height}, offset : ({$global_x_offset}, {$global_y_offset})\n";

// Placer les 8 pages recto FINAL
$recto_pages = array_slice($ordered_pages, 0, 8);
echo "4. Pages recto : " . implode(', ', $recto_pages) . "\n";

for ($j = 0; $j < 8; $j++) {
    $page_num = $recto_pages[$j];
    
    $template_id = $pdfFinal->importPage($page_num);
    list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, false);
    
    $page_row = intval($j / 4);
    $page_col = $j % 4;
    
    $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
    $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
    
    echo "   FINAL Page $page_num : position ($x, $y), dimensions ($new_width x $new_height)\n";
    
    $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
}

// Créer la page recto PREVIEW
$pdfPreview->AddPage('L', [$a3_width, $a3_height]);

for ($j = 0; $j < 8; $j++) {
    $page_num = $recto_pages[$j];
    
    $template_id = $pdfFinal->importPage($page_num);
    list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, false);
    
    $page_row = intval($j / 4);
    $page_col = $j % 4;
    
    $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
    $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
    
    echo "   PREVIEW Page $page_num : position ($x, $y), dimensions ($new_width x $new_height)\n";
    
    $template_id_preview = $template_ids_preview[$page_num];
    $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
    
    // Ajouter le numéro de page
    addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
}

// Sauvegarder les fichiers
$finalFile = '/tmp/comparison_final.pdf';
$previewFile = '/tmp/comparison_preview.pdf';

$pdfFinal->Output($finalFile, 'F');
$pdfPreview->Output($previewFile, 'F');

echo "\n=== RÉSULTATS ===\n";
echo "✅ Fichier final : $finalFile (" . filesize($finalFile) . " bytes)\n";
echo "✅ Fichier preview : $previewFile (" . filesize($previewFile) . " bytes)\n";

// Copier dans downloads pour comparaison
copy($finalFile, '/root/dupli-php-dev/downloads/comparison_final.pdf');
copy($previewFile, '/root/dupli-php-dev/downloads/comparison_preview.pdf');

echo "📁 Fichiers copiés dans downloads/\n";
echo "\n=== FIN ===\n";
?>
