<?php
/**
 * Classe pour gérer le traitement de l'imposition PDF (A5 et A6).
 * Factorise la logique d'imposition pour éviter la duplication de code.
 * 
 * @package Duplicator
 * @subpackage Imposition
 */
class ImpositionProcessor {
    
    /**
     * Traite une page individuelle : placement, traits de coupe, numéros, preview
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI pour le PDF final
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview (peut être null)
     * @param array &$template_ids_preview Tableau des IDs de templates pour le preview
     * @param int $page_num Numéro de la page à traiter
     * @param int $pageCount Nombre total de pages
     * @param float $x Position X de la page
     * @param float $y Position Y de la page
     * @param float $new_width Largeur de la page redimensionnée
     * @param float $new_height Hauteur de la page redimensionnée
     * @param int $page_row Numéro de la rangée (0-indexed)
     * @param int $total_rows Nombre total de rangées
     * @param float $a3_width Largeur de la feuille A3
     * @param float $a3_height Hauteur de la feuille A3
     * @param bool $previewMode Mode preview activé
     * @param bool $add_crop_marks Traits de coupe activés
     * @param string $imposition_mode Mode d'imposition ('brochure' ou 'livre')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $render_trim_numbers Afficher les numéros dans les zones de coupe
     * @param int $rotation Rotation de la page (0 ou 180)
     * @return void
     */
    public static function processPage(
        $pdfFinal,
        $pdfPreview,
        &$template_ids_preview,
        $page_num,
        $pageCount,
        $x,
        $y,
        $new_width,
        $new_height,
        $page_row,
        $total_rows,
        $a3_width,
        $a3_height,
        $previewMode,
        $add_crop_marks,
        $imposition_mode,
        $bleed_size,
        $crop_marks_type,
        $render_trim_numbers,
        $rotation = 0
    ) {
        // Vérifier que la page est valide
        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) {
            return;
        }
        
        // Créer le preview en même temps
        if ($previewMode && $pdfPreview !== null) {
            // Importer la page au moment de l'utiliser pour éviter les pages supplémentaires
            if (!isset($template_ids_preview[$page_num])) {
                $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
            }
            $template_id_preview = $template_ids_preview[$page_num];
            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $rotation);
        }
        
        // Dessiner les traits de coupe si activées (mode livre)
        if ($add_crop_marks && $imposition_mode === 'livre') {
            CropMarks::drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
        }
        
        // Afficher les numéros dans les zones de coupe
        if ($render_trim_numbers) {
            list($label_x, $label_y) = CropMarks::computeTrimZonePosition(
                $x,
                $y,
                $new_width,
                $new_height,
                $page_row,
                $total_rows,
                $a3_width,
                $a3_height,
                max(4, $bleed_size + 1)
            );
            CropMarks::drawTrimZonePageNumber($pdfFinal, $page_num, $label_x, $label_y);
        }
    }
    
    /**
     * Dessine les traits de coupe en mode brochure pour A6
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param float $global_x_offset Offset X global
     * @param float $global_y_offset Offset Y global
     * @param float $page_width Largeur d'une page
     * @param float $page_height Hauteur d'une page
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $previewMode Mode preview activé
     * @return void
     */
    public static function drawBrochureCropMarksA6(
        $pdfFinal,
        $pdfPreview,
        $global_x_offset,
        $global_y_offset,
        $page_width,
        $page_height,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $previewMode
    ) {
        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
        
        // A4 paysage du HAUT (4 A6 côte à côte)
        $a4_top_x = $global_x_offset + $crop_offset;
        $a4_top_y = $global_y_offset + $crop_offset;
        $a4_top_width = (4 * $page_width) - $crop_width_reduction;
        $a4_top_height = $page_height - $crop_width_reduction;
        CropMarks::drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
        
        // A4 paysage du BAS (4 A6 côte à côte)
        $a4_bottom_x = $global_x_offset + $crop_offset;
        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
        $a4_bottom_width = (4 * $page_width) - $crop_width_reduction;
        $a4_bottom_height = $page_height - $crop_width_reduction;
        CropMarks::drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
        
        if ($previewMode && $pdfPreview !== null) {
            CropMarks::drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
            CropMarks::drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
        }
    }
    
    /**
     * Dessine les traits de coupe en mode brochure pour A5
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param float $global_x_offset Offset X global
     * @param float $global_y_offset Offset Y global
     * @param float $page_width Largeur d'une page
     * @param float $page_height Hauteur d'une page
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $previewMode Mode preview activé
     * @return void
     */
    public static function drawBrochureCropMarksA5(
        $pdfFinal,
        $pdfPreview,
        $global_x_offset,
        $global_y_offset,
        $page_width,
        $page_height,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $previewMode
    ) {
        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
        
        // A4 paysage du HAUT (2 A5 côte à côte)
        $a4_top_x = $global_x_offset + $crop_offset;
        $a4_top_y = $global_y_offset + $crop_offset;
        $a4_top_width = (2 * $page_width) - $crop_width_reduction;
        $a4_top_height = $page_height - $crop_width_reduction;
        CropMarks::drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
        
        // A4 paysage du BAS (2 A5 côte à côte)
        $a4_bottom_x = $global_x_offset + $crop_offset;
        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
        $a4_bottom_width = (2 * $page_width) - $crop_width_reduction;
        $a4_bottom_height = $page_height - $crop_width_reduction;
        CropMarks::drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
        
        if ($previewMode && $pdfPreview !== null) {
            CropMarks::drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
            CropMarks::drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
        }
    }
    
    /**
     * Traite un côté (recto ou verso) pour l'imposition A6
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param array &$template_ids_preview Tableau des IDs de templates
     * @param array $pages Liste des pages à placer (8 pages)
     * @param int $pageCount Nombre total de pages
     * @param float $a3_width Largeur A3
     * @param float $a3_height Hauteur A3
     * @param float $page_width Largeur d'une page A6
     * @param float $page_height Hauteur d'une page A6
     * @param float $gutter_width Largeur de la gouttière
     * @param bool $forceResize Forcer le redimensionnement
     * @param bool $previewMode Mode preview activé
     * @param bool $add_crop_marks Traits de coupe activés
     * @param string $imposition_mode Mode d'imposition
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $render_trim_numbers Afficher les numéros dans les zones de coupe
     * @return void
     */
    public static function processA6Side(
        $pdfFinal,
        $pdfPreview,
        &$template_ids_preview,
        $pages,
        $pageCount,
        $a3_width,
        $a3_height,
        $page_width,
        $page_height,
        $gutter_width,
        $forceResize,
        $previewMode,
        $add_crop_marks,
        $imposition_mode,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $render_trim_numbers
    ) {
        // Créer la page
        $pdfFinal->AddPage('L', [$a3_width, $a3_height]);
        if ($previewMode && $pdfPreview !== null) {
            $pdfPreview->AddPage('L', [$a3_width, $a3_height]);
        }
        
        // Calculer l'offset pour centrer la grille 2x4 sur la feuille A3
        $grid_width = 4 * $page_width + (3 * $gutter_width);
        $grid_height = 2 * $page_height + $gutter_width;
        $global_x_offset = ($a3_width - $grid_width) / 2;
        $global_y_offset = ($a3_height - $grid_height) / 2;
        
        // Placer les 8 pages
        for ($j = 0; $j < 8; $j++) {
            $page_num = $pages[$j];
            
            $template_id = $pdfFinal->importPage($page_num);
            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
            
            // Position en grille 2x4
            $page_row = intval($j / 4);  // 0, 1 (2 rangées)
            $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
            
            // Ajouter la gouttière dans le calcul
            $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
            $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
            
            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
            
            // Traiter la page (preview, traits de coupe, numéros)
            self::processPage(
                $pdfFinal,
                $pdfPreview,
                $template_ids_preview,
                $page_num,
                $pageCount,
                $x,
                $y,
                $new_width,
                $new_height,
                $page_row,
                2,
                $a3_width,
                $a3_height,
                $previewMode,
                $add_crop_marks,
                $imposition_mode,
                $bleed_size,
                $crop_marks_type,
                $render_trim_numbers,
                0
            );
        }
        
        // Hirondelles en mode brochure
        if ($add_crop_marks && $imposition_mode === 'brochure') {
            self::drawBrochureCropMarksA6(
                $pdfFinal,
                $pdfPreview,
                $global_x_offset,
                $global_y_offset,
                $page_width,
                $page_height,
                $bleed_mode,
                $bleed_size,
                $crop_marks_type,
                $previewMode
            );
        }
    }
    
    /**
     * Traite un côté (recto ou verso) pour l'imposition A5
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param array &$template_ids_preview Tableau des IDs de templates
     * @param array $pages Liste des pages à placer (4 pages)
     * @param int $pageCount Nombre total de pages
     * @param float $a3_width Largeur A3
     * @param float $a3_height Hauteur A3
     * @param float $page_width Largeur d'une page A5
     * @param float $page_height Hauteur d'une page A5
     * @param float $gutter_width Largeur de la gouttière
     * @param bool $forceResize Forcer le redimensionnement
     * @param bool $previewMode Mode preview activé
     * @param bool $add_crop_marks Traits de coupe activés
     * @param string $imposition_mode Mode d'imposition
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $render_trim_numbers Afficher les numéros dans les zones de coupe
     * @return void
     */
    public static function processA5Side(
        $pdfFinal,
        $pdfPreview,
        &$template_ids_preview,
        $pages,
        $pageCount,
        $a3_width,
        $a3_height,
        $page_width,
        $page_height,
        $gutter_width,
        $forceResize,
        $previewMode,
        $add_crop_marks,
        $imposition_mode,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $render_trim_numbers
    ) {
        // Créer la page
        $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
        if ($previewMode && $pdfPreview !== null) {
            $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
        }
        
        // Calculer l'offset pour centrer la grille 2x2 sur la feuille A3
        $grid_width = 2 * $page_width + $gutter_width;
        $grid_height = 2 * $page_height + $gutter_width;
        $global_x_offset = ($a3_width - $grid_width) / 2;
        $global_y_offset = ($a3_height - $grid_height) / 2;
        
        // Placer les 4 pages
        for ($j = 0; $j < 4; $j++) {
            $page_num = $pages[$j];
            
            $template_id = $pdfFinal->importPage($page_num);
            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
            
            // Position en grille 2x2
            $page_row = intval($j / 2);  // 0, 1 (2 rangées)
            $page_col = $j % 2;          // 0, 1 (2 colonnes)
            
            // Ajouter la gouttière dans le calcul
            $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
            $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
            
            // Rotation de 180° pour la deuxième ligne (tête-bêche)
            $rotation = ($page_row == 1) ? 180 : 0;
            if ($rotation == 180) {
                $pdfFinal->StartTransform();
                $pdfFinal->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
            }
            
            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
            
            if ($rotation == 180) {
                $pdfFinal->StopTransform();
            }
            
            // Traiter la page (preview, traits de coupe, numéros)
            // Pour A5, le preview est géré différemment car les templates sont pré-importés
            if ($previewMode && $pdfPreview !== null) {
                if ($rotation == 180) {
                    $pdfPreview->StartTransform();
                    $pdfPreview->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                }
                
                $template_id_preview = $template_ids_preview[$page_num];
                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                
                if ($rotation == 180) {
                    $pdfPreview->StopTransform();
                }
                
                if ($add_crop_marks && $imposition_mode === 'livre') {
                    CropMarks::drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                }
                
                addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $rotation);
            }
            
            // Dessiner les traits de coupe si activées (mode livre)
            if ($add_crop_marks && $imposition_mode === 'livre') {
                CropMarks::drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
            }
            
            // Afficher les numéros dans les zones de coupe
            if ($render_trim_numbers) {
                list($label_x, $label_y) = CropMarks::computeTrimZonePosition(
                    $x,
                    $y,
                    $new_width,
                    $new_height,
                    $page_row,
                    2,
                    $a3_width,
                    $a3_height,
                    max(4, $bleed_size + 1)
                );
                CropMarks::drawTrimZonePageNumber($pdfFinal, $page_num, $label_x, $label_y);
            }
        }
        
        // Hirondelles en mode brochure
        if ($add_crop_marks && $imposition_mode === 'brochure') {
            self::drawBrochureCropMarksA5(
                $pdfFinal,
                $pdfPreview,
                $global_x_offset,
                $global_y_offset,
                $page_width,
                $page_height,
                $bleed_mode,
                $bleed_size,
                $crop_marks_type,
                $previewMode
            );
        }
    }
    
    /**
     * Traite l'imposition complète pour A6
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param array &$template_ids_preview Tableau des IDs de templates
     * @param array $ordered_pages_array Tableau ordonné des pages
     * @param int $pageCount Nombre total de pages
     * @param int $pages_per_sheet Nombre de pages par feuille (16 pour A6)
     * @param float $a3_width Largeur A3
     * @param float $a3_height Hauteur A3
     * @param float $page_width Largeur d'une page A6
     * @param float $page_height Hauteur d'une page A6
     * @param float $gutter_width Largeur de la gouttière
     * @param bool $forceResize Forcer le redimensionnement
     * @param bool $previewMode Mode preview activé
     * @param bool $add_crop_marks Traits de coupe activés
     * @param string $imposition_mode Mode d'imposition
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $render_trim_numbers Afficher les numéros dans les zones de coupe
     * @return void
     */
    public static function processA6Imposition(
        $pdfFinal,
        $pdfPreview,
        &$template_ids_preview,
        $ordered_pages_array,
        $pageCount,
        $pages_per_sheet,
        $a3_width,
        $a3_height,
        $page_width,
        $page_height,
        $gutter_width,
        $forceResize,
        $previewMode,
        $add_crop_marks,
        $imposition_mode,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $render_trim_numbers
    ) {
        // Pour A6 : créer recto et verso séparés
        for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
            $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
            $recto_pages = array_slice($sheet_pages, 0, 8);
            $verso_pages = array_slice($sheet_pages, 8, 8);
            
            // Traiter le recto
                self::processA6Side(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
                    $recto_pages,
                    $pageCount,
                    $a3_width,
                    $a3_height,
                    $page_width,
                    $page_height,
                    $gutter_width,
                    $forceResize,
                    $previewMode,
                    $add_crop_marks,
                    $imposition_mode,
                    $bleed_mode,
                    $bleed_size,
                    $crop_marks_type,
                    $render_trim_numbers
                );
                
                // Traiter le verso
                self::processA6Side(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
                    $verso_pages,
                    $pageCount,
                    $a3_width,
                    $a3_height,
                    $page_width,
                    $page_height,
                    $gutter_width,
                    $forceResize,
                    $previewMode,
                    $add_crop_marks,
                    $imposition_mode,
                    $bleed_mode,
                    $bleed_size,
                    $crop_marks_type,
                    $render_trim_numbers
                );
        }
    }
    
    /**
     * Traite l'imposition complète pour A5
     * 
     * @param object $pdfFinal Instance TCPDF/TCPDI
     * @param object|null $pdfPreview Instance TCPDF/TCPDI pour le preview
     * @param array &$template_ids_preview Tableau des IDs de templates
     * @param array $ordered_pages_array Tableau ordonné des pages
     * @param int $pageCount Nombre total de pages
     * @param int $pages_per_sheet Nombre de pages par feuille (8 pour A5)
     * @param float $a3_width Largeur A3
     * @param float $a3_height Hauteur A3
     * @param float $page_width Largeur d'une page A5
     * @param float $page_height Hauteur d'une page A5
     * @param float $gutter_width Largeur de la gouttière
     * @param bool $forceResize Forcer le redimensionnement
     * @param bool $previewMode Mode preview activé
     * @param bool $add_crop_marks Traits de coupe activés
     * @param string $imposition_mode Mode d'imposition
     * @param string $bleed_mode Mode de fond perdu ('fullsize' ou 'resize')
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits de coupe
     * @param bool $render_trim_numbers Afficher les numéros dans les zones de coupe
     * @return void
     */
    public static function processA5Imposition(
        $pdfFinal,
        $pdfPreview,
        &$template_ids_preview,
        $ordered_pages_array,
        $pageCount,
        $pages_per_sheet,
        $a3_width,
        $a3_height,
        $page_width,
        $page_height,
        $gutter_width,
        $forceResize,
        $previewMode,
        $add_crop_marks,
        $imposition_mode,
        $bleed_mode,
        $bleed_size,
        $crop_marks_type,
        $render_trim_numbers
    ) {
        // Pour A5 : créer recto et verso séparés (4 pages par côté)
        for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
            $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
            $recto_pages = array_slice($sheet_pages, 0, 4); // 4 pages recto
            $verso_pages = array_slice($sheet_pages, 4, 4); // 4 pages verso
            
            // Traiter le recto
                self::processA5Side(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
                    $recto_pages,
                    $pageCount,
                    $a3_width,
                    $a3_height,
                    $page_width,
                    $page_height,
                    $gutter_width,
                    $forceResize,
                    $previewMode,
                    $add_crop_marks,
                    $imposition_mode,
                    $bleed_mode,
                    $bleed_size,
                    $crop_marks_type,
                    $render_trim_numbers
                );
                
                // Traiter le verso
                self::processA5Side(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
                    $verso_pages,
                    $pageCount,
                    $a3_width,
                    $a3_height,
                    $page_width,
                    $page_height,
                    $gutter_width,
                    $forceResize,
                    $previewMode,
                    $add_crop_marks,
                    $imposition_mode,
                    $bleed_mode,
                    $bleed_size,
                    $crop_marks_type,
                    $render_trim_numbers
                );
        }
    }
}

