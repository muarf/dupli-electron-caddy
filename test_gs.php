<?php
require_once __DIR__ . '/app/controler/functions/binary_utilities.php';
$gs_path = get_ghostscript_path();
echo "Ghostscript path: " . ($gs_path ?? "NOT FOUND") . "\n";
// Test actual conversion
// Test actual conversion with !
if ($gs_path) {
    $pdf_orig = __DIR__ . '/blank_A4_4pages.pdf';
    $pdf = __DIR__ . '/test_!_path.pdf';
    copy($pdf_orig, $pdf);
    $out = __DIR__ . '/test_thumb_!.png';
    @unlink($out);
    
    $command = "\"$gs_path\" -dNOPAUSE -dBATCH -sDEVICE=png16m -dFirstPage=1 -dLastPage=1 -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=\"$out\" \"$pdf\" 2>&1";
    echo "Running command: $command\n";
    exec($command, $output, $returnVar);
    echo "Return code: $returnVar\n";
    echo "Output: " . implode("\n", $output) . "\n";
    echo "Result file exists: " . (file_exists($out) ? "YES" : "NO") . "\n";
    @unlink($pdf);
}
