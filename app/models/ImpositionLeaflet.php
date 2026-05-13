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
            'add_page_numbers_in_gutters' => false,
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

        // Configuration format sortie selon le format choisi
        $outputFormat = $this->settings['output_format'] ?? 'A3';
        
        if ($outputFormat === 'A4') {
            // Format A4
            $sheetWidth = 210;
            $sheetHeight = 297;
            if ($this->settings['orientation'] == 'P') {
                $sheetWidth = 210;
                $sheetHeight = 297;
            } else {
                $sheetWidth = 297;
                $sheetHeight = 210;
            }
        } else {
            // Format A3 (par défaut)
            $sheetWidth = 420;
            $sheetHeight = 297;
            if ($this->settings['orientation'] == 'P') {
                $sheetWidth = 297;
                $sheetHeight = 420;
            }
        }

        // Pre-calculate logical sheets (Leaflet Spreads)
        $sheetsToPrint = $this->calculateImposition($totalPages, $nUp);

        foreach ($sheetsToPrint as $sheetIdx => $sheetData) {
            // Front Side
            $this->pdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            if ($this->previewPdf) {
                $this->previewPdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            }
            $this->renderSheetSide($sheetData['front'], $cols, $rows, $sheetWidth, $sheetHeight);

            // Back Side
            $this->pdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            if ($this->previewPdf) {
                $this->previewPdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            }
            $this->renderSheetSide($sheetData['back'], $cols, $rows, $sheetWidth, $sheetHeight);
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
        // Calculer les métriques une fois pour toutes les pages (nécessaire pour les crop marks de style)
        $metrics = $this->calculatePageMetrics($cols, $rows, $sheetWidth, $sheetHeight);
        
        foreach ($rowsData as $rIndex => $rowData) {
            $pages = $rowData['pages'];
            $rotated = $rowData['rotated'];

            foreach ($pages as $cIndex => $pageNo) {
                // We no longer skip blank pages so that crop marks and numbers are drawn.

                $this->placePage($pageNo, $cIndex, $rIndex, $cols, $rows, $sheetWidth, $sheetHeight, $rotated);
            }
            
            // Draw crop marks based on style
            if ($this->settings['crop_marks']) {
                if ($this->settings['crop_style'] === 'booklet') {
                    // Whole row
                    $this->drawRowCropMarks($rIndex, $cols, $rows, $sheetWidth, $sheetHeight, $metrics);
                } elseif ($this->settings['crop_style'] === 'spreads') {
                    // Spreads (Double Poses)
                    $this->drawSpreadCropMarks($rIndex, $cols, $rows, $sheetWidth, $sheetHeight, $metrics);
                }
                // 'standard' is handled inside placePage
            }
        }
    }
    
    /**
     * Calcule les métriques communes pour toutes les pages (utilisé pour les crop marks de style)
     */
    private function calculatePageMetrics($totalCols, $totalRows, $sheetWidth, $sheetHeight)
    {
        // Get size reference (même logique que dans placePage)
        $tplIdx = $this->pdf->importPage(1);
        $size = $this->pdf->getTemplateSize($tplIdx);
        
        // Calculer le scale factor (même logique que dans placePage)
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

        // Appliquer la stratégie de gouttière (même logique que dans placePage)
        if ($this->settings['gutter_strategy'] === 'reduce') {
            $reqWidth = ($totalCols * $rawW) + (($totalCols - 1) * $cutGx);
            $reqHeight = ($totalRows * $rawH) + (($totalRows - 1) * $cutGy);
            
            if ($reqWidth <= $sheetWidth && $reqHeight <= $sheetHeight) {
                $finalW = $rawW;
                $finalH = $rawH;
                $posGx = $cutGx;
                $posGy = $cutGy;
            } else {
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
            }
        } else {
            // Mode CROP
            $finalW = $rawW;
            $finalH = $rawH;
            
            if ($totalCols > 1) {
                $availW = $sheetWidth - ($totalCols * $finalW);
                $posGx = $availW / ($totalCols - 1);
                if ($posGx > $cutGx) $posGx = $cutGx;
            } else {
                $posGx = 0;
            }
            
            if ($totalRows > 1) {
                $availH = $sheetHeight - ($totalRows * $finalH);
                $posGy = $availH / ($totalRows - 1);
                if ($posGy > $cutGy) $posGy = $cutGy;
            } else {
                $posGy = 0;
            }
        }

        // Calculer les positions globales
        $totalContentWidth = ($totalCols * $finalW) + (($totalCols - 1) * $posGx);
        $totalContentHeight = ($totalRows * $finalH) + (($totalRows - 1) * $posGy);

        $globalStartX = ($sheetWidth - $totalContentWidth) / 2;
        $globalStartY = ($sheetHeight - $totalContentHeight) / 2;
        
        return [
            'finalW' => $finalW,
            'finalH' => $finalH,
            'posGx' => $posGx,
            'posGy' => $posGy,
            'globalStartX' => $globalStartX,
            'globalStartY' => $globalStartY
        ];
    }

    private function placePage($pageNo, $colIndex, $rowIndex, $totalCols, $totalRows, $sheetWidth, $sheetHeight, $rotated)
    {
        $isBlank = ($pageNo > $this->pageCount);
        $tplIdx = null;
        $previewTplIdx = null;

        if (!$isBlank) {
            try {
                $tplIdx = $this->pdf->importPage($pageNo);
                if ($this->previewPdf) {
                    $previewTplIdx = $this->previewPdf->importPage($pageNo);
                }
            } catch (\Exception $e) {
                return;
            }
            $size = $this->pdf->getTemplateSize($tplIdx);
        } else {
            // It's a blank page, use the dimensions of the first page as reference
            try {
                $refTplIdx = $this->pdf->importPage(1);
                $size = $this->pdf->getTemplateSize($refTplIdx);
            } catch (\Exception $e) {
                $size = ['width' => 210, 'height' => 297]; // Fallback to A4
            }
        }
        
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
            // CORRECTION : Vérifier d'abord si le scale + gouttières rentrent
            $reqWidth = ($totalCols * $rawW) + (($totalCols - 1) * $cutGx);
            $reqHeight = ($totalRows * $rawH) + (($totalRows - 1) * $cutGy);
            
            if ($reqWidth <= $sheetWidth && $reqHeight <= $sheetHeight) {
                // Assez de place, garder le scale exact
                $finalW = $rawW;
                $finalH = $rawH;
                $posGx = $cutGx;
                $posGy = $cutGy;
            } else {
                // Pas assez de place, réduire
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
            }
            
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
        if (!$isBlank) {
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
        }

        // Place page in preview PDF
        if ($this->previewPdf) {
            if (!$isBlank && $previewTplIdx) {
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
            }

            // Add page number to preview if callback is provided (even for blank pages)
            if ($this->settings['addPageNumberCallback'] && is_callable($this->settings['addPageNumberCallback'])) {
                call_user_func($this->settings['addPageNumberCallback'], $this->previewPdf, $pageNo, $x, $y, $finalW, $finalH, $rotation);
            }
        }

        // Numéros dans les gouttières (pour preview et final si activé)
        if ($this->settings['add_page_numbers_in_gutters']) {
            $this->addPageNumberInGutter($pageNo, $x, $y, $finalW, $finalH, $colIndex, $rowIndex, $totalCols, $totalRows, $cutGx, $cutGy, $globalStartX, $globalStartY, $rotated, $sheetWidth, $sheetHeight);
        }

        // CORRECTION : Dessiner les crop marks individuels pour tous les styles
        // (les crop marks de style sont dessinés dans renderSheetSide)
        if ($this->settings['crop_marks']) {
            // Dessiner les traits individuels SEULEMENT pour le style standard
            // Pour les styles spreads et booklet, seuls les traits de style sont dessinés
            if ($this->settings['crop_style'] === 'standard') {
                // Calculer le bleed selon la stratégie
                if ($this->settings['gutter_strategy'] === 'crop') {
                    // En mode CROP, les crop marks doivent être aux bords réels de la page
                    $bleedX = 0;
                    $bleedY = 0;
                } else {
                    // Mode REDUCE : calculer le bleed normal
                    $bleedX = ($cutGx - $posGx) / 2;
                    $bleedY = ($cutGy - $posGy) / 2;
                }
                
                // Dessiner les crop marks individuels pour chaque page
                $this->drawIndividualCropMarks($x, $y, $finalW, $finalH, $bleedX, $bleedY);
            }
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

    private function drawRowCropMarks($rowIndex, $cols, $rows, $sheetWidth, $sheetHeight, $metrics)
    {
        // Dessiner les traits de coupe aux coins extérieurs de la ligne
        $this->drawRectCropMarks($rowIndex, 0, $cols, $cols, $rows, $sheetWidth, $sheetHeight, $metrics);
        
        // Ajouter un trait séparateur horizontal au milieu vertical (entre les lignes) pour séparer les doubles faces
        // Ce trait est dessiné seulement pour la première ligne (rowIndex == 0)
        // car il sépare la ligne du haut de la ligne du bas
        if ($rowIndex == 0 && $rows > 1) {
            $finalW = $metrics['finalW'];
            $finalH = $metrics['finalH'];
            $posGx = $metrics['posGx'];
            $posGy = $metrics['posGy'];
            $globalStartX = $metrics['globalStartX'];
            $globalStartY = $metrics['globalStartY'];
            
            // Position Y du trait séparateur : exactement au milieu vertical entre les 2 lignes (dans la gouttière)
            // La ligne du haut se termine à : blockY + blockH
            // La ligne du bas commence à : blockY + blockH + posGy
            // Le milieu exact est à : blockY + blockH + (posGy / 2)
            $blockY = $globalStartY + ($rowIndex * ($finalH + $posGy));
            $blockH = $finalH;
            $separatorY = $blockY + $blockH + ($posGy / 2);
            
            // Position X : de gauche à droite de toute la ligne (à l'intérieur de la zone)
            $blockStartX = $globalStartX;
            $blockW = ($cols * $finalW) + (($cols - 1) * $posGx);
            
            // Dessiner des traits horizontaux aux extrémités (pas une ligne continue)
            // Les traits doivent être À L'INTÉRIEUR de la zone de la ligne, pas à l'extérieur
            $len = $this->settings['crop_mark_len'];
            $this->pdf->SetLineWidth($this->settings['crop_mark_width']);
            $this->pdf->SetDrawColor(0, 0, 0);
            
            // Trait horizontal à gauche : à l'intérieur du bord gauche (pas de marge)
            $this->pdf->Line($blockStartX, $separatorY, $blockStartX + $len, $separatorY);
            // Trait horizontal à droite : à l'intérieur du bord droit
            $this->pdf->Line($blockStartX + $blockW - $len, $separatorY, $blockStartX + $blockW, $separatorY);
        }
    }

    private function drawSpreadCropMarks($rowIndex, $cols, $rows, $sheetWidth, $sheetHeight, $metrics)
    {
        // Iterate through columns in pairs (0,1), (2,3), etc.
        for ($c = 0; $c < $cols; $c += 2) {
            $colsInBlock = 2;
            if ($c + 2 > $cols) $colsInBlock = 1; 
            
            $this->drawRectCropMarks($rowIndex, $c, $colsInBlock, $cols, $rows, $sheetWidth, $sheetHeight, $metrics);
        }
    }

    /**
     * Convertit le numéro de page source en numéro de page du livret final
     * Pour un livret, l'ordre des pages est :
     * - Page source 8 → Page livret 1
     * - Page source 1 → Page livret 2
     * - Page source 2 → Page livret 3
     * - Page source 7 → Page livret 4
     * - Page source 6 → Page livret 5
     * - Page source 3 → Page livret 6
     * - Page source 4 → Page livret 7
     * - Page source 5 → Page livret 8
     */
    private function getBookletPageNumber($sourcePageNo, $totalPages)
    {
        // Calculer M (nombre de spreads)
        $M = $totalPages / 4;
        
        // Parcourir tous les spreads pour trouver la correspondance
        for ($i = 1; $i <= $M; $i++) {
            // Front du spread i
            $f1 = $totalPages - 2*($i-1);
            $f2 = 2*($i-1) + 1;
            // Back du spread i
            $b1 = 2*($i-1) + 2;
            $b2 = $totalPages - 2*($i-1) - 1;
            
            // Vérifier si la page source correspond
            if ($sourcePageNo == $f1) {
                // Première page du front = première page du spread
                return 4*($i-1) + 1;
            } elseif ($sourcePageNo == $f2) {
                // Deuxième page du front = deuxième page du spread
                return 4*($i-1) + 2;
            } elseif ($sourcePageNo == $b1) {
                // Première page du back = troisième page du spread
                return 4*($i-1) + 3;
            } elseif ($sourcePageNo == $b2) {
                // Deuxième page du back = quatrième page du spread
                return 4*($i-1) + 4;
            }
        }
        
        // Si non trouvé, retourner le numéro source
        return $sourcePageNo;
    }

    private function addPageNumberInGutter($pageNo, $x, $y, $w, $h, $colIndex, $rowIndex, $totalCols, $totalRows, $gutterX, $gutterY, $globalStartX, $globalStartY, $rotated, $sheetWidth, $sheetHeight)
    {
        // Utiliser le PDF preview si disponible, sinon le PDF final
        $targetPdf = $this->previewPdf ? $this->previewPdf : $this->pdf;
        
        $targetPdf->setAutoPageBreak(false);
        $targetPdf->SetFont('helvetica', '', 6); // Police petite (taille 6, comme dans imposition.php)
        $targetPdf->SetTextColor(0, 0, 0); // Noir
        
        // Afficher le numéro de page ORIGINAL du PDF source (pas converti en numéro de livret)
        // Exemple : sur la feuille recto, ligne 1 : pages 8, 1 → afficher "8" et "1"
        //           ligne 2 : pages 4, 5 → afficher "4" et "5"
        $displayPageNo = (string)$pageNo;
        
        // Dimensions de la cellule de texte
        $cellWidth = 10; // mm
        $cellHeight = 4; // mm
        
        // Distance depuis le bord de la gouttière : 1mm (à l'intérieur de la gouttière)
        $offsetFromGutterEdge = 1; // mm
        
        // Calculer la position par rapport à la page individuelle et à la gouttière adjacente
        // La gouttière est entre les pages, donc :
        // - Pour une page de gauche : la gouttière est à droite, à x + w
        // - Pour une page de droite : la gouttière est à gauche, à x
        // - Pour une ligne du haut : la gouttière est en dessous, à y + h
        // - Pour une ligne du bas : la gouttière est au-dessus, à y
        
        // Position X : par rapport à la gouttière verticale
        if ($colIndex < $totalCols / 2) {
            // Page de gauche : numéro dans la gouttière à droite de la page
            // La gouttière commence à x + w, on place le numéro à 1mm à l'intérieur
            $posX = $x + $w + $offsetFromGutterEdge - ($cellWidth / 2);
        } else {
            // Page de droite : numéro dans la gouttière à gauche de la page
            // La gouttière se termine à x, on place le numéro à 1mm à l'intérieur (donc à x - 1mm)
            $posX = $x - $offsetFromGutterEdge - ($cellWidth / 2);
        }
        
        // Position Y : par rapport à la gouttière horizontale
        if ($rowIndex == 0) {
            // Ligne du haut : numéro dans la gouttière en dessous de la page
            // La gouttière commence à y + h, on place le numéro à 1mm à l'intérieur
            $posY = $y + $h + $offsetFromGutterEdge - ($cellHeight / 2);
        } else {
            // Ligne du bas : numéro dans la gouttière au-dessus de la page
            // La gouttière se termine à y, on place le numéro à 1mm à l'intérieur (donc à y - 1mm)
            $posY = $y - $offsetFromGutterEdge - ($cellHeight / 2);
        }
        
        // Alignement centré
        $align = 'C';
        
        // Pour les pages tournées (ligne 2), tourner le numéro à 180° (tête-bêche)
        if ($rotated) {
            // Calculer le centre de rotation (centre géométrique de la cellule de texte)
            $rotCenterX = $posX + ($cellWidth / 2);
            $rotCenterY = $posY + ($cellHeight / 2);
            
            $targetPdf->StartTransform();
            $targetPdf->Rotate(180, $rotCenterX, $rotCenterY);
            $targetPdf->SetXY($posX, $posY);
            $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, $align, false);
            $targetPdf->StopTransform();
        } else {
            $targetPdf->SetXY($posX, $posY);
            $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, $align, false);
        }
    }

    private function drawRectCropMarks($rowIndex, $colStartIndex, $colsInBlock, $totalCols, $totalRows, $sheetWidth, $sheetHeight, $metrics)
    {
        // Utiliser les métriques calculées (mêmes valeurs que placePage)
        $finalW = $metrics['finalW'];
        $finalH = $metrics['finalH'];
        $posGx = $metrics['posGx'];
        $posGy = $metrics['posGy'];
        $globalStartX = $metrics['globalStartX'];
        $globalStartY = $metrics['globalStartY'];
        
        // Block Y Position
        $blockY = $globalStartY + ($rowIndex * ($finalH + $posGy));
        $blockH = $finalH;
        
        // Block X Start (Relative to global start, based on start col)
        $blockStartX = $globalStartX + ($colStartIndex * ($finalW + $posGx));
        
        // Block Width: (cols * w) + (cols-1 * gutter)
        $blockW = ($colsInBlock * $finalW) + (($colsInBlock - 1) * $posGx);

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

