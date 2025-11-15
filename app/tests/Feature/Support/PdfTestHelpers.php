<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

if (!function_exists('createSamplePdf')) {
    function createSamplePdf(int $pages = 1, string $format = 'A4'): string
    {
        $pdf = new TCPDI('P', 'mm', $format, true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);

        for ($i = 1; $i <= $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 10, "Page {$i}", 0, 1);
        }

        $path = tempnam(sys_get_temp_dir(), 'dupli_pdf_') . '.pdf';
        $pdf->Output($path, 'F');
        return $path;
    }
}

if (!function_exists('createSamplePng')) {
    /**
     * Génère un PNG où une portion définie est remplie.
     *
     * @return array{0:string,1:float}
     */
    function createSamplePng(int $width, int $height, float $fillRatio): array
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        $black = imagecolorallocate($image, 0, 0, 0);
        $filledColumns = (int) round($width * $fillRatio);
        for ($x = 0; $x < $filledColumns; $x++) {
            imagefilledrectangle($image, $x, 0, $x, $height - 1, $black);
        }

        $path = tempnam(sys_get_temp_dir(), 'dupli_png_') . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return [$path, $fillRatio];
    }
}

if (!function_exists('cleanupPath')) {
    function cleanupPath(string $path): void
    {
        if (is_dir($path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            @rmdir($path);
        } elseif (file_exists($path)) {
            @unlink($path);
        }
    }
}

