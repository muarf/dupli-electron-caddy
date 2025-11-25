<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

class ImpositionLeaflet
{
    private $pdf;
    private $previewPdf;
    private $sourceFile;
    private $settings;
    private $pageCount;

    public function __construct($sourceFile, $settings = [])
    {
        $this->sourceFile = $sourceFile;
        $this->settings = array_merge([
            'scale' => 100,
            'gutter_x' => 0,
            'gutter_y' => 0,
            'crop_marks' => false,
            'crop_mark_len' => 5,
            'crop_mark_width' => 0.1,
            'orientation' => 'L',
            'n_up' => 2, // 2, 4, 8
            'crop_style' => 'standard', // standard, spreads, booklet
            'gutter_strategy' => 'reduce', // reduce, crop
            'preview_mode' => false,
            'addPageNumberCallback' => null // Callback pour ajouter les numéros de pages
        ], $settings);

        $this->pdf = new FpdiRotated();
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        if ($this->settings['preview_mode']) {
            $this->previewPdf = new FpdiRotated();
            $this->previewPdf->setPrintHeader(false);
            $this->previewPdf->setPrintFooter(false);
        }
    }

    public function process($outputFile, $previewOutputFile = null)
    {
        // Normaliser les chemins de sortie pour éviter les problèmes de casse avec TCPDF
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }
        $realOutputDir = realpath($outputDir);
        if ($realOutputDir !== false) {
            $outputFile = $realOutputDir . DIRECTORY_SEPARATOR . basename($outputFile);
        }
        
        if ($previewOutputFile !== null) {
            $previewDir = dirname($previewOutputFile);
            if (!is_dir($previewDir)) {
                @mkdir($previewDir, 0755, true);
            }
            $realPreviewDir = realpath($previewDir);
            if ($realPreviewDir !== false) {
                $previewOutputFile = $realPreviewDir . DIRECTORY_SEPARATOR . basename($previewOutputFile);
            }
        }
        
        $this->pageCount = $this->pdf->setSourceFile($this->sourceFile);
        if ($this->previewPdf) {
            $this->previewPdf->setSourceFile($this->sourceFile);
        }
        
        $nUp = intval($this->settings['n_up']);
        
        // Determine padding requirement
        $multiple = 4; // Default for 2-up
        if ($nUp == 4) $multiple = 8;
        if ($nUp == 8) $multiple = 16;

        $totalPages = ceil($this->pageCount / $multiple) * $multiple;

        // Configuration
        if ($nUp == 4) {
            $this->settings['orientation'] = 'P'; 
        } else {
            $this->settings['orientation'] = 'L';
        }

        // Grid config
        if ($nUp == 2) {
            $cols = 2; $rows = 1;
        } elseif ($nUp == 4) {
            $cols = 2; $rows = 2;
        } elseif ($nUp == 8) {
            $cols = 4; $rows = 2;
        } else {
            $cols = 2; $rows = 1;
        }

        $a3Width = 420;
        $a3Height = 297;
        if ($this->settings['orientation'] == 'P') {
            $a3Width = 297;
            $a3Height = 420;
        }

        // Pre-calculate logical sheets (Leaflet Spreads)
        $sheetsToPrint = $this->calculateImposition($totalPages, $nUp);

        foreach ($sheetsToPrint as $sheetIdx => $sheetData) {
            // Front Side
            $this->pdf->AddPage($this->settings['orientation'], 'A3');
            if ($this->previewPdf) {
                $this->previewPdf->AddPage($this->settings['orientation'], 'A3');
            }
            $this->renderSheetSide($sheetData['front'], $cols, $rows, $a3Width, $a3Height);

            // Back Side
            $this->pdf->AddPage($this->settings['orientation'], 'A3');
            if ($this->previewPdf) {
                $this->previewPdf->AddPage($this->settings['orientation'], 'A3');
            }
            $this->renderSheetSide($sheetData['back'], $cols, $rows, $a3Width, $a3Height);
        }

        // S'assurer que le répertoire existe et normaliser le chemin
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }
        // Utiliser realpath() pour obtenir le chemin réel (résout la casse)
        $realOutputDir = realpath($outputDir);
        if ($realOutputDir !== false) {
            $outputFile = $realOutputDir . DIRECTORY_SEPARATOR . basename($outputFile);
        }
        $this->pdf->Output($outputFile, 'F');
        
        if ($this->previewPdf && $previewOutputFile) {
            $previewDir = dirname($previewOutputFile);
            if (!is_dir($previewDir)) {
                @mkdir($previewDir, 0755, true);
            }
            $realPreviewDir = realpath($previewDir);
            if ($realPreviewDir !== false) {
                $previewOutputFile = $realPreviewDir . DIRECTORY_SEPARATOR . basename($previewOutputFile);
            }
            $this->previewPdf->Output($previewOutputFile, 'F');
        }
    }

    public function getPreviewPdf()
    {
        return $this->previewPdf;
    }

    private function renderSheetSide($rowsData, $cols, $rows, $sheetWidth, $sheetHeight)
    {
        foreach ($rowsData as $rIndex => $rowData) {
            $pages = $rowData['pages'];
            $rotated = $rowData['rotated'];

            foreach ($pages as $cIndex => $pageNo) {
                if ($pageNo > $this->pageCount) continue; // Blank page

                $this->placePage($pageNo, $cIndex, $rIndex, $cols, $rows, $sheetWidth, $sheetHeight, $rotated);
            }
            
            // Draw crop marks based on style
            if ($this->settings['crop_marks']) {
                if ($this->settings['crop_style'] === 'booklet') {
                    // Whole row
                    $this->drawRowCropMarks($rIndex, $cols, $rows, $sheetWidth, $sheetHeight);
                } elseif ($this->settings['crop_style'] === 'spreads') {
                    // Spreads (Double Poses)
                    $this->drawSpreadCropMarks($rIndex, $cols, $rows, $sheetWidth, $sheetHeight);
                }
                // 'standard' is handled inside placePage
            }
        }
    }

    private function placePage($pageNo, $colIndex, $rowIndex, $totalCols, $totalRows, $sheetWidth, $sheetHeight, $rotated)
    {
        try {
            $tplIdx = $this->pdf->importPage($pageNo);
            $previewTplIdx = null;
            if ($this->previewPdf) {
                $previewTplIdx = $this->previewPdf->importPage($pageNo);
            }
        } catch (\Exception $e) {
            return;
        }
        
        $size = $this->pdf->getTemplateSize($tplIdx);
        
        // --- CALCUL DES MÉTRIQUES ---
        $scaleFactor = 1;
        if (!empty($this->settings['target_width']) && $this->settings['target_width'] > 0) {
             $scaleFactor = $this->settings['target_width'] / $size['width'];
        } elseif (!empty($this->settings['target_height']) && $this->settings['target_height'] > 0) {
             $scaleFactor = $this->settings['target_height'] / $size['height'];
        } else {
            $scaleFactor = $this->settings['scale'] / 100;
        }

        $rawW = $size['width'] * $scaleFactor;
        $rawH = $size['height'] * $scaleFactor;

        $cutGx = floatval($this->settings['gutter_x']);
        $cutGy = floatval($this->settings['gutter_y']);

        // Appliquer la stratégie de gouttière
        if ($this->settings['gutter_strategy'] === 'reduce') {
            // Mode RÉDUIRE
            $reqWidth = ($totalCols * $rawW) + (($totalCols - 1) * $cutGx);
            $reqHeight = ($totalRows * $rawH) + (($totalRows - 1) * $cutGy);
            
            $scaleW = 1.0;
            $scaleH = 1.0;
            
            if ($reqWidth > $sheetWidth) {
                $availW = $sheetWidth - (($totalCols - 1) * $cutGx);
                $scaleW = $availW / ($totalCols * $rawW);
            }
            
            if ($reqHeight > $sheetHeight) {
                $availH = $sheetHeight - (($totalRows - 1) * $cutGy);
                $scaleH = $availH / ($totalRows * $rawH);
            }
            
            $reductionFactor = min($scaleW, $scaleH);
            
            $finalW = $rawW * $reductionFactor;
            $finalH = $rawH * $reductionFactor;
            $posGx = $cutGx;
            $posGy = $cutGy;
            
        } else {
            // Mode ROGNER (Crop)
            $finalW = $rawW;
            $finalH = $rawH;
            
            // Calcul espacement X
            if ($totalCols > 1) {
                $availW = $sheetWidth - ($totalCols * $finalW);
                $posGx = $availW / ($totalCols - 1);
                if ($posGx > $cutGx) $posGx = $cutGx;
            } else {
                $posGx = 0;
            }
            
            // Calcul espacement Y
            if ($totalRows > 1) {
                $availH = $sheetHeight - ($totalRows * $finalH);
                $posGy = $availH / ($totalRows - 1);
                if ($posGy > $cutGy) $posGy = $cutGy;
            } else {
                $posGy = 0;
            }
        }

        // --- PLACEMENT ---
        $totalContentWidth = ($totalCols * $finalW) + (($totalCols - 1) * $posGx);
        $totalContentHeight = ($totalRows * $finalH) + (($totalRows - 1) * $posGy);

        $globalStartX = ($sheetWidth - $totalContentWidth) / 2;
        $globalStartY = ($sheetHeight - $totalContentHeight) / 2;

        $x = $globalStartX + ($colIndex * ($finalW + $posGx));
        $y = $globalStartY + ($rowIndex * ($finalH + $posGy));

        $rotation = $rotated ? 180 : 0;

        // Place page in final PDF
        if ($rotated) {
            $centerX = $x + ($finalW / 2);
            $centerY = $y + ($finalH / 2);
            
            $this->pdf->StartTransform();
            $this->pdf->Rotate(180, $centerX, $centerY);
            $this->pdf->useTemplate($tplIdx, $x, $y, $finalW, $finalH);
            $this->pdf->StopTransform();
        } else {
            $this->pdf->useTemplate($tplIdx, $x, $y, $finalW, $finalH);
        }

        // Place page in preview PDF
        if ($this->previewPdf && $previewTplIdx) {
            if ($rotated) {
                $centerX = $x + ($finalW / 2);
                $centerY = $y + ($finalH / 2);
                
                $this->previewPdf->StartTransform();
                $this->previewPdf->Rotate(180, $centerX, $centerY);
                $this->previewPdf->useTemplate($previewTplIdx, $x, $y, $finalW, $finalH);
                $this->previewPdf->StopTransform();
            } else {
                $this->previewPdf->useTemplate($previewTplIdx, $x, $y, $finalW, $finalH);
            }

            // Add page number to preview if callback is provided
            if ($this->settings['addPageNumberCallback'] && is_callable($this->settings['addPageNumberCallback'])) {
                call_user_func($this->settings['addPageNumberCallback'], $this->previewPdf, $pageNo, $x, $y, $finalW, $finalH, $rotation);
            }
        }

        // Standard individual crop marks ONLY if crop_style is standard
        if ($this->settings['crop_marks'] && ($this->settings['crop_style'] === 'standard' || empty($this->settings['crop_style']))) {
            // Calculer le bleed (pour dessiner les traits au bon endroit)
            $bleedX = ($cutGx - $posGx) / 2;
            $bleedY = ($cutGy - $posGy) / 2;
            
            $this->drawIndividualCropMarks($x, $y, $finalW, $finalH, $bleedX, $bleedY);
        }
    }
    
    private function drawIndividualCropMarks($x, $y, $w, $h)
    {
        $len = $this->settings['crop_mark_len'];
        $this->pdf->SetLineWidth($this->settings['crop_mark_width']);
        $this->pdf->SetDrawColor(0, 0, 0);
        $offset = 1;

        // TL
        $this->pdf->Line($x - $offset - $len, $y, $x - $offset, $y);
        $this->pdf->Line($x, $y - $offset - $len, $x, $y - $offset);
        // TR
        $this->pdf->Line($x + $w + $offset, $y, $x + $w + $offset + $len, $y);
        $this->pdf->Line($x + $w, $y - $offset - $len, $x + $w, $y - $offset);
        // BL
        $this->pdf->Line($x - $offset - $len, $y + $h, $x - $offset, $y + $h);
        $this->pdf->Line($x, $y + $h + $offset, $x, $y + $h + $offset + $len);
        // BR
        $this->pdf->Line($x + $w + $offset, $y + $h, $x + $w + $offset + $len, $y + $h);
        $this->pdf->Line($x + $w, $y + $h + $offset, $x + $w, $y + $h + $offset + $len);
    }

    private function drawRowCropMarks($rowIndex, $cols, $rows, $sheetWidth, $sheetHeight)
    {
        $this->drawRectCropMarks($rowIndex, 0, $cols, $cols, $rows, $sheetWidth, $sheetHeight);
    }

    private function drawSpreadCropMarks($rowIndex, $cols, $rows, $sheetWidth, $sheetHeight)
    {
        // Iterate through columns in pairs (0,1), (2,3), etc.
        for ($c = 0; $c < $cols; $c += 2) {
            $colsInBlock = 2;
            if ($c + 2 > $cols) $colsInBlock = 1; 
            
            $this->drawRectCropMarks($rowIndex, $c, $colsInBlock, $cols, $rows, $sheetWidth, $sheetHeight);
        }
    }

    private function drawRectCropMarks($rowIndex, $colStartIndex, $colsInBlock, $totalCols, $totalRows, $sheetWidth, $sheetHeight)
    {
        // Get size reference
        $tplIdx = $this->pdf->importPage(1);
        $size = $this->pdf->getTemplateSize($tplIdx);
        
        $scaleFactor = 1;
        if (!empty($this->settings['target_width']) && $this->settings['target_width'] > 0) {
             $scaleFactor = $this->settings['target_width'] / $size['width'];
        } elseif (!empty($this->settings['target_height']) && $this->settings['target_height'] > 0) {
             $scaleFactor = $this->settings['target_height'] / $size['height'];
        } else {
            $scaleFactor = $this->settings['scale'] / 100;
        }

        $finalW = $size['width'] * $scaleFactor;
        $finalH = $size['height'] * $scaleFactor;
        
        $gutterX = floatval($this->settings['gutter_x']);
        $gutterY = floatval($this->settings['gutter_y']);

        $totalContentWidth = ($totalCols * $finalW) + (($totalCols - 1) * $gutterX);
        $totalContentHeight = ($totalRows * $finalH) + (($totalRows - 1) * $gutterY);
        
        $globalStartX = ($sheetWidth - $totalContentWidth) / 2;
        $globalStartY = ($sheetHeight - $totalContentHeight) / 2;
        
        // Block Y Position
        $blockY = $globalStartY + ($rowIndex * ($finalH + $gutterY));
        $blockH = $finalH;
        
        // Block X Start (Relative to global start, based on start col)
        $blockStartX = $globalStartX + ($colStartIndex * ($finalW + $gutterX));
        
        // Block Width: (cols * w) + (cols-1 * gutter)
        $blockW = ($colsInBlock * $finalW) + (($colsInBlock - 1) * $gutterX);

        $len = $this->settings['crop_mark_len'];
        $this->pdf->SetLineWidth($this->settings['crop_mark_width']);
        $this->pdf->SetDrawColor(0, 0, 0);
        $offset = 1;
        
        $bx = $blockStartX;
        $by = $blockY;
        $bw = $blockW;
        $bh = $blockH;

        // TL
        $this->pdf->Line($bx - $offset - $len, $by, $bx - $offset, $by);
        $this->pdf->Line($bx, $by - $offset - $len, $bx, $by - $offset);
        
        // TR
        $this->pdf->Line($bx + $bw + $offset, $by, $bx + $bw + $offset + $len, $by);
        $this->pdf->Line($bx + $bw, $by - $offset - $len, $bx + $bw, $by - $offset);
        
        // BL
        $this->pdf->Line($bx - $offset - $len, $by + $bh, $bx - $offset, $by + $bh);
        $this->pdf->Line($bx, $by + $bh + $offset, $bx, $by + $bh + $offset + $len);
        
        // BR
        $this->pdf->Line($bx + $bw + $offset, $by + $bh, $bx + $bw + $offset + $len, $by + $bh);
        $this->pdf->Line($bx + $bw, $by + $bh + $offset, $bx + $bw, $by + $bh + $offset + $len);
    }

    private function calculateImposition($N, $nUp)
    {
        // Logic from simulate_imposition.py
        $M = $N / 4; 
        // $lsheets structure: [index => [ 'front' => [p1, p2], 'back' => [p3, p4] ] ]
        $lsheets = [];
        for ($i = 1; $i <= $M; $i++) {
            // Front: (N - 2(i-1), 2(i-1) + 1)
            $f1 = $N - 2*($i-1);
            $f2 = 2*($i-1) + 1;
            // Back: (2(i-1) + 2, N - 2(i-1) - 1)
            $b1 = 2*($i-1) + 2;
            $b2 = $N - 2*($i-1) - 1;
            $lsheets[$i] = ['front' => [$f1, $f2], 'back' => [$b1, $b2]];
        }

        $result = [];

        if ($nUp == 2) {
            $numSheets = $M;
            for ($k = 1; $k <= $numSheets; $k++) {
                $result[] = [
                    'front' => [
                        ['pages' => $lsheets[$k]['front'], 'rotated' => false]
                    ],
                    'back' => [
                        ['pages' => $lsheets[$k]['back'], 'rotated' => false]
                    ]
                ];
            }
        } elseif ($nUp == 4) {
            $numSheets = $M / 2;
            for ($k = 1; $k <= $numSheets; $k++) {
                // Front
                // Row 1: lsheets[k][front]
                // Row 2: lsheets[M - k + 1][back] (Rotated -> Reversed)
                $r1_pages = $lsheets[$k]['front'];
                $r2_src = $lsheets[$M - $k + 1]['back'];
                $r2_pages = array_reverse($r2_src);
                
                // Back
                // Row 1: lsheets[k][back]
                // Row 2: lsheets[M - k + 1][front] (Rotated -> Reversed)
                $br1_pages = $lsheets[$k]['back'];
                $br2_src = $lsheets[$M - $k + 1]['front'];
                $br2_pages = array_reverse($br2_src);

                $result[] = [
                    'front' => [
                        ['pages' => $r1_pages, 'rotated' => false],
                        ['pages' => $r2_pages, 'rotated' => true]
                    ],
                    'back' => [
                        ['pages' => $br1_pages, 'rotated' => false],
                        ['pages' => $br2_pages, 'rotated' => true]
                    ]
                ];
            }
        } elseif ($nUp == 8) {
            $numSheets = $M / 4;
            for ($k = 1; $k <= $numSheets; $k++) {
                // Front
                // Row 1: lsheets[M/2 - k + 1][back] + lsheets[k][front]
                $pair1 = $lsheets[$M/2 - $k + 1]['back'];
                $pair2 = $lsheets[$k]['front'];
                $r1_pages = array_merge($pair1, $pair2);
                
                // Row 2: lsheets[M - k + 1][back] + lsheets[M/2 + k][front] (Rotated -> Reversed)
                $pair3 = $lsheets[$M - $k + 1]['back'];
                $pair4 = $lsheets[$M/2 + $k]['front'];
                $r2_src = array_merge($pair3, $pair4);
                $r2_pages = array_reverse($r2_src);
                
                // Back
                // Row 1: lsheets[k][back] + lsheets[M/2 - k + 1][front]
                $pair1b = $lsheets[$k]['back'];
                $pair2b = $lsheets[$M/2 - $k + 1]['front'];
                $br1_pages = array_merge($pair1b, $pair2b);
                
                // Row 2: lsheets[M/2 + k][back] + lsheets[M - k + 1][front] (Rotated -> Reversed)
                $pair3b = $lsheets[$M/2 + $k]['back'];
                $pair4b = $lsheets[$M - $k + 1]['front'];
                $br2_src = array_merge($pair3b, $pair4b);
                $br2_pages = array_reverse($br2_src);
                
                $result[] = [
                    'front' => [
                        ['pages' => $r1_pages, 'rotated' => false],
                        ['pages' => $r2_pages, 'rotated' => true]
                    ],
                    'back' => [
                        ['pages' => $br1_pages, 'rotated' => false],
                        ['pages' => $br2_pages, 'rotated' => true]
                    ]
                ];
            }
        }

        return $result;
    }
}

// Extension class to support rotation - TCPDI a déjà StartTransform/Rotate/StopTransform
// Cette classe est maintenue pour compatibilité mais utilise les méthodes natives de TCPDI
class FpdiRotated extends TCPDI {
    // TCPDI a déjà les méthodes StartTransform, Rotate et StopTransform
    // Cette classe hérite simplement de TCPDI
}

