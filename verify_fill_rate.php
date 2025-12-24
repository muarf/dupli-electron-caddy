<?php
// Script to independently verify fill rate of a PDF
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdfPath = "temp.pdf";
$gsPath = "C:\\Users\\Dupli\\AppData\\Local\\Programs\\dupli-electron-caddy\\ghostscript\\gswin64c.exe";
$outputDir = __DIR__ . "\\verification_temp\\";

if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

if (!file_exists($pdfPath)) {
    die("PDF not found: $pdfPath\n");
}
if (!file_exists($gsPath)) {
    die("Ghostscript not found: $gsPath\n");
}

echo "Analyzing PDF: $pdfPath\n";

// 1. Get Page Count - SKIPPED due to GS errors
// $gsPdfPath = str_replace('\\', '/', $pdfPath);
// $cmdCount = "\"$gsPath\" -q -dNODISPLAY -c \"($gsPdfPath) (r) file runpdfbegin pdfpagecount = quit\"";
// $pageCount = intval(shell_exec($cmdCount));
echo "Analyzing Page 1 only for quick check...\n";
$pageCount = 1; 

// Initial cleanup
$cleanupFiles = glob($outputDir . "*.png");
foreach ($cleanupFiles as $f) unlink($f);

$totalFillRate200 = 0;
$totalFillRate245 = 0;
$pagesAnalyzed = 0;

for ($i = 1; $i <= $pageCount; $i++) {
    // echo "Processing Page $i...\n";
    
    $pngFile = $outputDir . "page_" . $i . ".png";
    
    // 2. Convert to PNG
    $pdfPathSanitized = str_replace('\\', '/', $pdfPath);
    $cmdConvert = "\"$gsPath\" -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -dFirstPage=$i -dLastPage=$i -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=\"$pngFile\" \"$pdfPathSanitized\" 2>&1";
    
    echo "  Running: $cmdConvert\n";
    
    $output = [];
    $returnVar = 0;
    exec($cmdConvert, $output, $returnVar);
    
    // If output file doesn't exist or is empty, we probably reached end of PDF
    if (!file_exists($pngFile) || filesize($pngFile) == 0) {
        if ($i == 1) {
             echo "Failed to convert Page 1. GS Output:\n" . implode("\n", $output) . "\n";
        }
        break;
    }
    
    // 3. Analyze Pixels
    $fill200 = calculate_fill_rate_v($pngFile, 200);
    $fill245 = calculate_fill_rate_v($pngFile, 245);
    
    echo "  Fill Rate (Threshold 200 - C++): $fill200%\n";
    echo "  Fill Rate (Threshold 245 - PHP): $fill245%\n";
    
    $totalFillRate200 += $fill200;
    $totalFillRate245 += $fill245;
    $pagesAnalyzed++;
    
    // Cleanup
    unlink($pngFile);
}

if ($pagesAnalyzed > 0) {
    $avg200 = $totalFillRate200 / $pagesAnalyzed;
    $avg245 = $totalFillRate245 / $pagesAnalyzed;
    
    echo "\n=== RESULTS ===\n";
    echo "Average Fill Rate (Threshold 200): " . round($avg200, 2) . "%\n";
    echo "Average Fill Rate (Threshold 245): " . round($avg245, 2) . "%\n";
} else {
    echo "No pages analyzed.\n";
}

// Simplified function from model
function calculate_fill_rate_v($image_path, $tolerance) {
    if (!file_exists($image_path)) return 0;
    
    $image = imagecreatefrompng($image_path);
    if (!$image) return 0;
    
    $width = imagesx($image);
    $height = imagesy($image);
    $total_pixels = $width * $height;
    $filled_pixels = 0;
    
    // Sample every pixel
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            $luminosity = ($r + $g + $b) / 3;
            
            if ($luminosity < $tolerance) {
                $filled_pixels++;
            }
        }
    }
    
    imagedestroy($image);
    
    return ($filled_pixels / $total_pixels) * 100;
}
?>
