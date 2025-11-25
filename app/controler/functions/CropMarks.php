<?php
/**
 * Classe pour gérer le dessin des traits de coupe (crop marks) et la numérotation
 * dans les zones de coupe pour l'imposition PDF.
 * 
 * @package Duplicator
 * @subpackage Imposition
 */
class CropMarks {
    
    /**
     * Longueur par défaut des traits de coupe normaux (en mm)
     */
    const DEFAULT_MARK_LENGTH = 10;
    
    /**
     * Longueur par défaut des traits de coupe centraux (en mm)
     */
    const DEFAULT_CENTRAL_MARK_LENGTH = 8;
    
    /**
     * Épaisseur des traits de coupe (en mm)
     */
    const MARK_LINE_WIDTH = 0.5;
    
    /**
     * Position du trait central pour A3→A4 (en mm)
     */
    const CENTRAL_MARK_POSITION = 210; // 21cm = 210mm
    
    /**
     * Dessine les traits de coupe aux 4 coins d'une zone vers l'extérieur.
     * 
     * @param object $pdf Instance TCPDF/TCPDI
     * @param float $x Position X du coin supérieur gauche
     * @param float $y Position Y du coin supérieur gauche
     * @param float $width Largeur de la zone
     * @param float $height Hauteur de la zone
     * @param float $bleed_size Taille du fond perdu (non utilisé actuellement mais conservé pour compatibilité)
     * @return void
     */
    public static function drawCropMarks($pdf, $x, $y, $width, $height, $bleed_size = 3) {
        // Dessiner les traits de coupe aux 4 coins vers l'extérieur de la zone
        // Ligne noire plus épaisse pour les traits de coupe
        $pdf->SetLineWidth(self::MARK_LINE_WIDTH);
        $pdf->SetDrawColor(0, 0, 0); // Noir
        
        $mark_length = self::DEFAULT_MARK_LENGTH; // Longueur fixe de 10mm pour bien voir les marques
        
        // Coin supérieur gauche - lignes vers l'extérieur
        $pdf->Line($x, $y, $x - $mark_length, $y); // Horizontale vers la gauche
        $pdf->Line($x, $y, $x, $y - $mark_length); // Verticale vers le haut
        
        // Coin supérieur droit - lignes vers l'extérieur
        $pdf->Line($x + $width, $y, $x + $width + $mark_length, $y); // Horizontale vers la droite
        $pdf->Line($x + $width, $y, $x + $width, $y - $mark_length); // Verticale vers le haut
        
        // Coin inférieur gauche - lignes vers l'extérieur
        $pdf->Line($x, $y + $height, $x - $mark_length, $y + $height); // Horizontale vers la gauche
        $pdf->Line($x, $y + $height, $x, $y + $height + $mark_length); // Verticale vers le bas
        
        // Coin inférieur droit - lignes vers l'extérieur
        $pdf->Line($x + $width, $y + $height, $x + $width + $mark_length, $y + $height); // Horizontale vers la droite
        $pdf->Line($x + $width, $y + $height, $x + $width, $y + $height + $mark_length); // Verticale vers le bas
    }
    
    /**
     * Dessine les traits de coupe centraux pour A3→A4 selon l'orientation de la page.
     * 
     * @param object $pdf Instance TCPDF/TCPDI
     * @param float $x Position X (non utilisé mais conservé pour compatibilité)
     * @param float $y Position Y (non utilisé mais conservé pour compatibilité)
     * @param float $width Largeur (non utilisé mais conservé pour compatibilité)
     * @param float $height Hauteur (non utilisé mais conservé pour compatibilité)
     * @return void
     */
    public static function drawCentralCropMarks($pdf, $x, $y, $width, $height) {
        // Dessiner les traits de coupe centraux pour A3→A4 selon l'orientation
        $pdf->SetLineWidth(self::MARK_LINE_WIDTH);
        $pdf->SetDrawColor(0, 0, 0); // Noir
        
        // Détecter l'orientation de la page
        $page_width = $pdf->getPageWidth();
        $page_height = $pdf->getPageHeight();
        
        if ($page_width > $page_height) {
            // Paysage : trait vertical à 21cm (210mm) - haut et bas
            $center_x = self::CENTRAL_MARK_POSITION; // 21cm = 210mm
            $mark_length = self::DEFAULT_CENTRAL_MARK_LENGTH; // Plus court
            
            // Trait haut (vers l'extérieur)
            $pdf->Line($center_x, 5, $center_x, max(0, 5 - $mark_length));
            
            // Trait bas (vers l'extérieur) - utiliser la hauteur de la page
            $pdf->Line($center_x, $page_height - 5, $center_x, $page_height - 5 + $mark_length);
        } else {
            // Portrait : trait horizontal à 21cm (210mm) - gauche et droite
            $center_y = self::CENTRAL_MARK_POSITION; // 21cm = 210mm
            $mark_length = self::DEFAULT_CENTRAL_MARK_LENGTH; // Plus court
            
            // Trait gauche (vers l'extérieur)
            $pdf->Line(5, $center_y, max(0, 5 - $mark_length), $center_y);
            
            // Trait droite (vers l'extérieur) - utiliser la largeur de la page
            $pdf->Line($page_width - 5, $center_y, $page_width - 5 + $mark_length, $center_y);
        }
    }
    
    /**
     * Dessine tous les traits de coupe selon le type sélectionné.
     * 
     * @param object $pdf Instance TCPDF/TCPDI
     * @param float $x Position X du coin supérieur gauche
     * @param float $y Position Y du coin supérieur gauche
     * @param float $width Largeur de la zone
     * @param float $height Hauteur de la zone
     * @param float $bleed_size Taille du fond perdu
     * @param string $crop_marks_type Type de traits : 'normal', 'central', ou 'both'
     * @return void
     */
    public static function drawAllCropMarks($pdf, $x, $y, $width, $height, $bleed_size, $crop_marks_type) {
        // Dessiner selon le type sélectionné
        if ($crop_marks_type === 'normal' || $crop_marks_type === 'both') {
            self::drawCropMarks($pdf, $x, $y, $width, $height, $bleed_size);
        }
        
        if ($crop_marks_type === 'central' || $crop_marks_type === 'both') {
            self::drawCentralCropMarks($pdf, $x, $y, $width, $height);
        }
    }
    
    /**
     * Dessine le numéro de page dans la zone de coupe.
     * 
     * @param object $pdf Instance TCPDF/TCPDI
     * @param int $page_num Numéro de la page
     * @param float $label_x Position X du label
     * @param float $label_y Position Y du label
     * @return void
     */
    public static function drawTrimZonePageNumber($pdf, $page_num, $label_x, $label_y) {
        $previousAutoBreak = method_exists($pdf, 'getAutoPageBreak') ? $pdf->getAutoPageBreak() : true;
        $previousBreakMargin = method_exists($pdf, 'getBreakMargin') ? $pdf->getBreakMargin() : 0;
        $pdf->setAutoPageBreak(false, 0);
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($label_x, $label_y);
        $pdf->Cell(8, 4, (string)$page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
        
        $pdf->setAutoPageBreak($previousAutoBreak, $previousBreakMargin);
    }
    
    /**
     * Calcule la position du numéro de page dans la zone de coupe.
     * 
     * @param float $x Position X de la page
     * @param float $y Position Y de la page
     * @param float $page_width Largeur de la page
     * @param float $page_height Hauteur de la page
     * @param int $page_row Numéro de la rangée (0-indexed)
     * @param int $total_rows Nombre total de rangées
     * @param float $sheet_width Largeur de la feuille
     * @param float $sheet_height Hauteur de la feuille
     * @param float $offset Offset pour positionner le label (défaut: 4mm)
     * @return array [label_x, label_y] Position calculée du label
     */
    public static function computeTrimZonePosition($x, $y, $page_width, $page_height, $page_row, $total_rows, $sheet_width, $sheet_height, $offset = 4) {
        $label_x = $x + ($page_width / 2) - 4;
        $label_x = max(2, min($sheet_width - 10, $label_x));
        
        if ($page_row <= 0) {
            $label_y = max(2, $y - $offset - 4);
        } elseif ($page_row >= $total_rows - 1) {
            $label_y = min($sheet_height - 6, $y + $page_height + $offset);
        } else {
            $label_y = $y + ($page_height / 2) - 2;
        }
        
        return [$label_x, $label_y];
    }
}

