<?php

use setasign\Fpdi\TcpdfFpdi as TCPDI;

class Imposition
{
    private $pdf;
    private $previewPdf;
    private $sourceFile;
    private $pageCount;
    private $settings;

    // Propriétés explicites pour l'IDE/cache
    private $scale;
    private $targetWidth;
    private $targetHeight;
    private $gutterX;
    private $gutterY;
    private $gutterStrategy;
    private $cropMarks;
    private $cropMarkLen;
    private $cropMarkWidth;
    private $orientation;
    private $nUp;
    private $duplex;
    private $previewMode;
    private $addPageNumbersInGutters;
    private $teteBeche;
    private $outputFormat;
    private $addPageNumberCallback;
    private $gutterNumOffsetX;
    private $gutterNumOffsetY;

    public function __construct($sourceFile, $settings = [])
    {
        $this->sourceFile = $sourceFile;
        // Paramètres par défaut
        $this->settings = array_merge([
            'scale' => 100, // Pourcentage
            'gutter_x' => 0, // mm
            'gutter_y' => 0, // mm
            'gutter_strategy' => 'reduce', // reduce, crop
            'crop_marks' => false,
            'crop_mark_len' => 2, // mm (Court pour style discret)
            'crop_mark_width' => 0.1, // mm
            'orientation' => 'L', // L = Landscape (A3 paysage)
            'n_up' => 2, // Nombre de poses (2, 4, 8)
            'duplex' => true, // Mode Recto-Verso par défaut
            'preview_mode' => false,
            'add_page_numbers_in_gutters' => false,
            'gutter_num_offset_x' => 0.0,
            'gutter_num_offset_y' => -2.0,
            'tete_beche' => false,
            'addPageNumberCallback' => null // Callback pour ajouter les numéros de pages
        ], $settings);

        // Hydrater les propriétés
        $this->scale = $this->settings['scale'];
        $this->targetWidth = isset($this->settings['target_width']) ? $this->settings['target_width'] : 0;
        $this->targetHeight = isset($this->settings['target_height']) ? $this->settings['target_height'] : 0;
        $this->gutterX = $this->settings['gutter_x'];
        $this->gutterY = $this->settings['gutter_y'];
        $this->gutterStrategy = $this->settings['gutter_strategy'];
        $this->cropMarks = $this->settings['crop_marks'];
        $this->cropMarkLen = $this->settings['crop_mark_len'];
        $this->cropMarkWidth = $this->settings['crop_mark_width'];
        $this->orientation = $this->settings['orientation'];
        $this->nUp = $this->settings['n_up'];
        $this->duplex = $this->settings['duplex'];
        $this->previewMode = $this->settings['preview_mode'];
        $this->addPageNumbersInGutters = $this->settings['add_page_numbers_in_gutters'];
        $this->teteBeche = $this->settings['tete_beche'];
        $this->addPageNumberCallback = $this->settings['addPageNumberCallback'];
        $this->outputFormat = isset($this->settings['output_format']) ? $this->settings['output_format'] : 'SRA3';
        $this->gutterNumOffsetX = isset($this->settings['gutter_num_offset_x']) ? $this->settings['gutter_num_offset_x'] : 0.0;
        $this->gutterNumOffsetY = isset($this->settings['gutter_num_offset_y']) ? $this->settings['gutter_num_offset_y'] : -2.0;

        $this->pdf = new TCPDI();
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        if ($this->settings['preview_mode']) {
            $this->previewPdf = new TCPDI();
            $this->previewPdf->setPrintHeader(false);
            $this->previewPdf->setPrintFooter(false);
        }
    }

    public function process($outputFile, $previewOutputFile = null)
    {
        // 1. Initialisation et import
        $this->pageCount = $this->pdf->setSourceFile($this->sourceFile);
        $pageCount = $this->pageCount;
        if ($this->previewPdf) {
            $this->previewPdf->setSourceFile($this->sourceFile);
        }

        $nUp = intval($this->settings['n_up']);
        if ($nUp < 1)
            $nUp = 2;

        $duplex = !empty($this->settings['duplex']);

        // Orientation automatique
        if ($nUp == 4) {
            $this->settings['orientation'] = 'P'; // Portrait pour 4 poses
        } else {
            $this->settings['orientation'] = 'L'; // Paysage pour 2 et 8 poses
        }

        // Définition de la grille (cols x rows)
        if ($nUp == 2) {
            $cols = 2;
            $rows = 1;
        } elseif ($nUp == 4) {
            $cols = 2;
            $rows = 2;
        } elseif ($nUp == 8) {
            $cols = 4;
            $rows = 2;
        } else {
            $cols = ceil(sqrt($nUp));
            $rows = ceil($nUp / $cols);
        }

        // Cut & Stack : Calcul de la profondeur de la pile
        // Si Duplex, on consomme 2 pages source pour 1 feuille physique (Recto+Verso)
        // Donc la pile monte moins vite en nombre de feuilles.
        $pagesPerSheet = $nUp * ($duplex ? 2 : 1);
        // On utilise ceil, mais attention si pageCount n'est pas un multiple parfait.
        // Le nombre de feuilles PHYSIQUES dans la pile :
        $stackDepth = ceil($pageCount / $pagesPerSheet);

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

        // Boucle sur les feuilles à générer
        for ($i = 1; $i <= $stackDepth; $i++) {

            // --- RECTO ---
            $this->pdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            if ($this->previewPdf) {
                $this->previewPdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
            }

            for ($pos = 0; $pos < $nUp; $pos++) {
                // Calcul de la page source pour cette position
                if ($duplex) {
                    // Formule Recto-Verso :
                    // Pile n° 'pos'. Chaque pile a 'stackDepth' feuilles.
                    // Chaque feuille consomme 2 pages (Recto, Verso).
                    // Page de début de la pile 'pos' = pos * (stackDepth * 2)
                    // Dans la pile, on est à la feuille 'i'.
                    // Page Recto = DebutPile + (i * 2) - 1 (car page 1 est sur feuille 1)
                    // Page Verso = DebutPile + (i * 2)

                    // Exemple : pos=0, i=1 -> (0) + 2 - 1 = 1. Correct.
                    $pageNo = ($pos * $stackDepth * 2) + ($i * 2) - 1;
                } else {
                    // Formule Simplex
                    $pageNo = $i + ($pos * $stackDepth);
                }

                $currentRow = floor($pos / $cols);
                $currentCol = $pos % $cols;

                $this->placePage($pageNo, $currentCol, $currentRow, $cols, $rows, $sheetWidth, $sheetHeight);
            }

            // --- VERSO (Seulement si Duplex) ---
            if ($duplex) {
                $this->pdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
                if ($this->previewPdf) {
                    $this->previewPdf->AddPage($this->settings['orientation'], array($sheetWidth, $sheetHeight));
                }

                for ($pos = 0; $pos < $nUp; $pos++) {
                    // Page Verso correspondant à la même position "logique" dans la pile
                    $pageNo = ($pos * $stackDepth * 2) + ($i * 2);

                    // Logique Miroir pour le Verso
                    // On prend la position 'pos' (qui correspond à une case de la grille)
                    // et on cherche où l'imprimer physiquement pour qu'elle soit au dos.

                    $origRow = floor($pos / $cols);
                    $origCol = $pos % $cols;

                    // Inversion des colonnes (Miroir axe vertical)
                    // Col 0 devient Col Max, Col 1 devient Col Max-1...
                    $mirrorCol = ($cols - 1) - $origCol;
                    $mirrorRow = $origRow; // La ligne ne change pas si on tourne la page comme un livre

                    $this->placePage($pageNo, $mirrorCol, $mirrorRow, $cols, $rows, $sheetWidth, $sheetHeight);
                }
            }
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

    private function placePage($pageNo, $colIndex, $rowIndex, $totalCols, $totalRows, $sheetWidth, $sheetHeight)
    {
        $isBlank = ($pageNo > $this->pageCount);
        $tplIdx = null;
        $previewTplIdx = null;

        if (!$isBlank) {
            // Import de la page
            try {
                $tplIdx = $this->pdf->importPage($pageNo);
                if ($this->previewPdf) {
                    $previewTplIdx = $this->previewPdf->importPage($pageNo);
                }
            } catch (\Exception $e) {
                return; // Page invalide ou erreur
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

        if (!$size) {
            $size = ['width' => 210, 'height' => 297]; // Fallback to A4
        }

        // --- CALCUL DES MÉTRIQUES ---
        // 1. Déterminer l'échelle de base
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

        // 2. Appliquer la stratégie de gouttière
        if ($this->settings['gutter_strategy'] === 'reduce') {
            // Mode RÉDUIRE : On réduit l'échelle si ça ne rentre pas
            // Espace nécessaire = (Cols * rawW) + ((Cols-1) * cutGx)
            // Doit être <= sheetWidth

            $reqWidth = ($totalCols * $rawW) + (($totalCols - 1) * $cutGx);
            $reqHeight = ($totalRows * $rawH) + (($totalRows - 1) * $cutGy);

            $scaleW = 1.0;
            $scaleH = 1.0;

            if ($reqWidth > $sheetWidth) {
                // Espace disponible pour le contenu (hors gouttières)
                $availW = $sheetWidth - (($totalCols - 1) * $cutGx);
                $scaleW = $availW / ($totalCols * $rawW);
            }

            if ($reqHeight > $sheetHeight) {
                $availH = $sheetHeight - (($totalRows - 1) * $cutGy);
                $scaleH = $availH / ($totalRows * $rawH);
            }

            $reductionFactor = min($scaleW, $scaleH);

            // Appliquer la réduction
            $finalW = $rawW * $reductionFactor;
            $finalH = $rawH * $reductionFactor;
            $posGx = $cutGx; // La gouttière physique reste celle demandée
            $posGy = $cutGy;

        } else {
            // Mode ROGNER (Crop) : On garde l'échelle, on réduit l'espacement physique
            $finalW = $rawW;
            $finalH = $rawH;

            // Calcul espacement X
            if ($totalCols > 1) {
                $availW = $sheetWidth - ($totalCols * $finalW);
                // Répartir l'espace disponible (qui peut être négatif)
                $posGx = $availW / ($totalCols - 1);
                // En mode crop, on ne veut pas écarter les pages physiquement si on a de la place,
                // car cela diminuerait le bleed (la coupe dans la page).
                // On force max 0 pour que le bleed soit TOUJOURS de cutGx / 2.
                if ($posGx > 0)
                    $posGx = 0;
            } else {
                $posGx = 0;
            }

            // Calcul espacement Y
            if ($totalRows > 1) {
                $availH = $sheetHeight - ($totalRows * $finalH);
                $posGy = $availH / ($totalRows - 1);
                if ($posGy > 0)
                    $posGy = 0;
            } else {
                $posGy = 0;
            }
        }

        // --- PLACEMENT ---
        // Calcul du bloc total de contenu (avec espacement physique calculé)
        $totalContentWidth = ($totalCols * $finalW) + (($totalCols - 1) * $posGx);
        $totalContentHeight = ($totalRows * $finalH) + (($totalRows - 1) * $posGy);

        // Centrage global
        $globalStartX = ($sheetWidth - $totalContentWidth) / 2;
        $globalStartY = ($sheetHeight - $totalContentHeight) / 2;

        // Position de la page
        $x = $globalStartX + ($colIndex * ($finalW + $posGx));
        $y = $globalStartY + ($rowIndex * ($finalH + $posGy));

        // Rotation Tête-bêche (180°)
        $applyRotation = false;
        if (!empty($this->settings['tete_beche']) && $rowIndex == 0 && $totalRows > 1) {
            $applyRotation = true;
        }

        // Ajout de la page dans le PDF final
        if (!$isBlank) {
            if ($applyRotation) {
                $this->pdf->StartTransform();
                $this->pdf->Rotate(180, $x + ($finalW / 2), $y + ($finalH / 2));
            }
            $this->pdf->useTemplate($tplIdx, $x, $y, $finalW, $finalH);
            if ($applyRotation) {
                $this->pdf->StopTransform();
            }
        }

        // Ajout de la page dans le PDF preview
        if ($this->previewPdf) {
            if (!$isBlank && $previewTplIdx) {
                if ($applyRotation) {
                    $this->previewPdf->StartTransform();
                    $this->previewPdf->Rotate(180, $x + ($finalW / 2), $y + ($finalH / 2));
                }
                $this->previewPdf->useTemplate($previewTplIdx, $x, $y, $finalW, $finalH);
                if ($applyRotation) {
                    $this->previewPdf->StopTransform();
                }
            }

            // Add page number to preview if callback is provided (even for blank pages)
            if ($this->settings['addPageNumberCallback'] && is_callable($this->settings['addPageNumberCallback'])) {
                $previewRotation = $applyRotation ? 180 : 0;
                call_user_func($this->settings['addPageNumberCallback'], $this->previewPdf, $pageNo, $x, $y, $finalW, $finalH, $previewRotation);
            }
        }

        // Numéros dans les gouttières (pour preview et final si activé)
        if ($this->settings['add_page_numbers_in_gutters']) {
            // On passe posGx/posGy pour savoir où est le "milieu" physique de la gouttière
            // Mais le numéro doit-il être positionné par rapport à la coupe ou à la page ?
            // L'utilisateur veut "a coté des crop mark". Donc par rapport à la coupe théorique.
            // Le milieu de la gouttière est : x + finalW + posGx/2
            // La coupe théorique est à : milieu - cutGx/2
            // C'est complexe. Utilisons la position de la page pour l'instant.
            $this->addPageNumberInGutter($pageNo, $x, $y, $finalW, $finalH, $colIndex, $rowIndex, $totalCols, $totalRows, $cutGx, $cutGy, $globalStartX, $globalStartY, $applyRotation);
        }

        // Traits de coupe
        if ($this->settings['crop_marks']) {
            // On passe les dimensions réelles et les gouttières de COUPE (théoriques)
            // Pour dessiner les traits au bon endroit
            // Le centre de la gouttière physique est : x + finalW + posGx/2
            // Le centre de la gouttière de coupe est le même (car on a centré le tout)

            // On doit calculer le décalage (bleed effectif) pour la fonction de dessin
            // Bleed = (cutGx - posGx) / 2
            // Si posGx < cutGx (mode rogner), bleed est positif (on coupe dans la page)

            $bleedX = ($cutGx - $posGx) / 2;
            $bleedY = ($cutGy - $posGy) / 2;

            // On passe ces "bleeds" à une fonction de dessin adaptée
            $this->drawSmartCropMarks($x, $y, $finalW, $finalH, $bleedX, $bleedY);
        }
    }

    private function drawSmartCropMarks($x, $y, $w, $h, $bleedX, $bleedY)
    {
        $len = $this->settings['crop_mark_len'];
        $this->pdf->SetLineWidth($this->settings['crop_mark_width']);
        $this->pdf->SetDrawColor(0, 0, 0);

        $visualOffset = 1;

        // Coordonnées de coupe (en rentrant dans la page de bleedX/Y)
        // Si bleed est négatif (mode réduire avec marges), on sort de la page
        // Mais ici bleedX = (cut - pos) / 2.
        // Si cut > pos (rogner), bleed > 0 -> On coupe DANS la page.
        // Si cut == pos (réduire), bleed = 0 -> On coupe au bord.

        $cutX1 = $x + $bleedX;
        $cutX2 = $x + $w - $bleedX;
        $cutY1 = $y + $bleedY;
        $cutY2 = $y + $h - $bleedY;

        // Haut-Gauche
        $this->pdf->Line($cutX1 - $visualOffset - $len, $cutY1, $cutX1 - $visualOffset, $cutY1);
        $this->pdf->Line($cutX1, $cutY1 - $visualOffset - $len, $cutX1, $cutY1 - $visualOffset);

        // Haut-Droite
        $this->pdf->Line($cutX2 + $visualOffset, $cutY1, $cutX2 + $visualOffset + $len, $cutY1);
        $this->pdf->Line($cutX2, $cutY1 - $visualOffset - $len, $cutX2, $cutY1 - $visualOffset);

        // Bas-Gauche
        $this->pdf->Line($cutX1 - $visualOffset - $len, $cutY2, $cutX1 - $visualOffset, $cutY2);
        $this->pdf->Line($cutX1, $cutY2 + $visualOffset, $cutX1, $cutY2 + $visualOffset + $len);

        // Bas-Droite
        $this->pdf->Line($cutX2 + $visualOffset, $cutY2, $cutX2 + $visualOffset + $len, $cutY2);
        $this->pdf->Line($cutX2, $cutY2 + $visualOffset, $cutX2, $cutY2 + $visualOffset + $len);
    }

    private function addPageNumberInGutter($pageNo, $x, $y, $w, $h, $colIndex, $rowIndex, $totalCols, $totalRows, $gutterX, $gutterY, $globalStartX, $globalStartY, $rotated = false)
    {
        // On écrit sur le PDF final ET sur le PDF preview s'il existe
        $targetPdfs = [$this->pdf];
        if ($this->previewPdf) {
            $targetPdfs[] = $this->previewPdf;
        }

        foreach ($targetPdfs as $targetPdf) {
            $targetPdf->setAutoPageBreak(false);
            $targetPdf->SetFont('helvetica', '', 6); // Police petite (taille 6)
            $targetPdf->SetTextColor(0, 0, 0); // Noir

            $cellWidth = 10;
            $cellHeight = 4;
            $offsetX = $this->gutterNumOffsetX;
            $offsetY = $this->gutterNumOffsetY;
            $displayPageNo = (string)$pageNo;

        $useManualOffset = isset($this->settings['add_page_numbers_manual_offset']) && $this->settings['add_page_numbers_manual_offset'];

        if ($useManualOffset) {
            $targetPdf->StartTransform();
            if ($rotated) {
                $targetPdf->Rotate(180, $x + ($w / 2), $y + ($h / 2));
            }
            $posX = $x + $offsetX;
            $posY = $y + $offsetY;
            $targetPdf->SetXY($posX, $posY);
            $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, 'L', false);
            $targetPdf->StopTransform();
        } else {
            $hasVerticalGutter = ($gutterX > 0);
            $hasHorizontalGutter = ($gutterY > 0);
            $isCrop = ($this->settings['gutter_strategy'] === 'crop');
            
            // On tourne l'espace pour que le texte soit "dans le sens de la page"
            $targetPdf->StartTransform();
            if ($rotated) {
                $targetPdf->Rotate(180, $x + ($w / 2), $y + ($h / 2));
            }
            
            if ($hasVerticalGutter || $hasHorizontalGutter) {
                if ($hasVerticalGutter) {
                    $isOdd = ($pageNo % 2 != 0);
                    $position = isset($this->settings['add_page_numbers_position']) ? $this->settings['add_page_numbers_position'] : 'margins';
                    if ($position === 'gutters') {
                        $wantVisualRight = !$isOdd; // Tranches (Intérieur) : Impaires à gauche, Paires à droite
                    } else {
                        $wantVisualRight = $isOdd; // Marges (Extérieur) : Impaires à droite, Paires à gauche
                    }
                    
                    if ($wantVisualRight) {
                        $unrotatedSide = 'RIGHT';
                    } else {
                        $unrotatedSide = 'LEFT';
                    }
                    
                    if ($unrotatedSide === 'LEFT') {
                        $centerX = $isCrop ? ($x + ($gutterX / 4)) : ($x - ($gutterX / 4));
                    } else { // 'RIGHT'
                        $centerX = $isCrop ? ($x + $w - ($gutterX / 4)) : ($x + $w + ($gutterX / 4));
                    }
                    
                    $posY = $y + ($h / 2) - ($cellHeight / 2);
                    $posX = $centerX - ($cellWidth / 2);
                    $targetPdf->SetXY($posX, $posY);
                    $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, 'C', false);
                }

                if ($hasHorizontalGutter) {
                    $gutterOnLocalBottom = ($rowIndex % 2 == 0);
                    
                    if ($gutterOnLocalBottom) {
                        $centerY = $isCrop ? ($y + $h - ($gutterY / 4)) : ($y + $h + ($gutterY / 4));
                    } else {
                        $centerY = $isCrop ? ($y + ($gutterY / 4)) : ($y - ($gutterY / 4));
                    }
                    
                    $posX = $x + ($w / 2) - ($cellWidth / 2);
                    $posY = $centerY - ($cellHeight / 2);
                    $targetPdf->SetXY($posX, $posY);
                    $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, 'C', false);
                }
            } else {
                // Auto-center standard pour les livrets normaux sans gouttière 4-poses
                $centerX = $x + ($w / 2);
                $centerY = $y + $h - 5; // 5mm du bas
                $posX = $centerX - ($cellWidth / 2);
                $posY = $centerY - ($cellHeight / 2);
                $targetPdf->SetXY($posX, $posY);
                $targetPdf->Cell($cellWidth, $cellHeight, $displayPageNo, 0, 0, 'C', false);
            }

            $targetPdf->StopTransform();
        }
        }
    }




}

