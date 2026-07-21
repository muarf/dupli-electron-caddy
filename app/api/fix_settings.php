<?php
$file = '/home/ubuntu/dupli-electron-caddy/app/api/studio_process.php';
$c = file_get_contents($file);

// Livre: move $settings up
$c = preg_replace('/(\$paddingResult = padPdfToMultiple\(\$uploadedFile, \$multiple\);\s*\$pdfFile\s*=\s*\$paddingResult\[\'file\'\];\s*)(\$settings = \[.*?\];)/s', "$2\n\n$1", $c, 1);

// Brochure: move $settings up
$c = preg_replace('/(\$paddingResult = padPdfToMultiple\(\$uploadedFile, \$multiple\);\s*\$pdfFile\s*=\s*\$paddingResult\[\'file\'\];\s*\$pageCount\s*=\s*\$paddingResult\[\'page_count\'\];\s*)(\$settings = \[.*?\];)/s', "$2\n\n$1", $c, 1);

file_put_contents($file, $c);
echo "Settings order fixed.\n";
