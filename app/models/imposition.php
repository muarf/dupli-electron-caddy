<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/utilities.php');
require_once(__DIR__ . '/../controler/functions/simple_i18n.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

function reordering_pages_a5($number_of_pages) {
    $original_num_pages = $number_of_pages;
    $new_list_pages = [];
    
    // Calculer le nombre de feuilles A3 (8 pages A5 par feuille)
    $num_sheets = ceil($original_num_pages / 8);
    
    // Pour chaque feuille A3 (8 pages par feuille)
    for ($sheet = 0; $sheet < $num_sheets; $sheet++) {
        // Pattern général d'imposition recto/verso adapté pour N pages :
        // Pour 40 pages: 40, 21, 20, 39, 19, 22 sont les valeurs de référence
        // Pour N pages: adapter ces valeurs proportionnellement
        
        $last_page = $original_num_pages;                    // Dernière page (40 → N)
        $first_page = 1;                                     // Première page (toujours 1)
        $mid_page = ceil($original_num_pages / 2);           // Moitié (20 → N/2)
        $before_last = $original_num_pages - 1;              // Avant-dernière (39 → N-1)
        
        // Pattern adapté pour N pages (basé sur le pattern de 40 pages)
        $sheet_pages = [
            $last_page - $sheet * 2,                         // Position 1: N, N-2, N-4...
            $first_page + $sheet * 2,                        // Position 2: 1, 3, 5...
            $mid_page + 1 + $sheet * 2,                      // Position 3: N/2+1, N/2+3, N/2+5...
            $mid_page - $sheet * 2,                          // Position 4: N/2, N/2-2, N/2-4...
            $first_page + 1 + $sheet * 2,                    // Position 5: 2, 4, 6...
            $before_last - $sheet * 2,                       // Position 6: N-1, N-3, N-5...
            $mid_page - 1 - $sheet * 2,                      // Position 7: N/2-1, N/2-3, N/2-5...
            $mid_page + 2 + $sheet * 2                       // Position 8: N/2+2, N/2+4, N/2+6...
        ];
        
        // Ajouter les pages de cette feuille
        foreach ($sheet_pages as $page) {
            if ($page >= 1 && $page <= $original_num_pages) {
                $new_list_pages[] = $page;
            } else {
                $new_list_pages[] = "blank_page";
            }
        }
    }
    
    // S'assurer qu'on a un multiple de 8 (pour les feuilles A3 complètes)
    while (count($new_list_pages) % 8 != 0) {
        $new_list_pages[] = "blank_page";
    }

    return $new_list_pages;
}

function imposition_for_sheet($group_index, $total_pages) {
    // Séquences exactes fournies par l'utilisateur
    $sequences = [
        16 => [
            [1, 16, 13, 4, 8, 9, 12, 5, 3, 14, 15, 2, 7, 10, 11, 6]
        ],
        32 => [
            [1, 32, 25, 8, 16, 17, 24, 9, 7, 26, 31, 2, 10, 23, 18, 15],
            [3, 30, 27, 6, 14, 19, 22, 11, 5, 28, 29, 4, 12, 21, 20, 13]
        ],
        48 => [
            [1, 48, 37, 12, 24, 25, 36, 13, 11, 38, 47, 2, 14, 35, 26, 23],
            [3, 46, 39, 10, 22, 27, 34, 15, 9, 40, 45, 4, 16, 33, 28, 21],
            [5, 44, 41, 8, 20, 29, 32, 17, 7, 42, 43, 6, 18, 31, 30, 19]
        ],
        64 => [
            [1, 64, 49, 16, 32, 33, 48, 17, 15, 50, 63, 2, 18, 47, 34, 31],
            [3, 62, 51, 14, 30, 35, 46, 19, 13, 52, 61, 4, 20, 45, 36, 29],
            [5, 60, 53, 12, 28, 37, 44, 21, 11, 54, 59, 6, 22, 43, 38, 27],
            [7, 58, 55, 10, 26, 39, 42, 23, 9, 56, 57, 8, 24, 41, 40, 25]
        ]
    ];
    
    // Si on a une séquence exacte, l'utiliser
    if (isset($sequences[$total_pages]) && isset($sequences[$total_pages][$group_index])) {
        return $sequences[$total_pages][$group_index];
    }
    
    // Sinon, utiliser une logique générique basée sur le pattern de base
    $base_pattern = [1, 16, 13, 4, 8, 9, 12, 5, 3, 14, 15, 2, 7, 10, 11, 6];
    $seq = [];
    
    foreach ($base_pattern as $pos => $value) {
        if ($group_index == 0) {
            // Premier groupe : ajuster selon le nombre total de pages
            $N = $total_pages;
            switch ($pos) {
                case 0: $seq[] = 1; break;
                case 1: $seq[] = $N; break;
                case 2: $seq[] = $N - 11; break;
                case 3: $seq[] = 12; break;
                case 4: $seq[] = 24; break;
                case 5: $seq[] = 25; break;
                case 6: $seq[] = $N - 12; break;
                case 7: $seq[] = 13; break;
                case 8: $seq[] = 11; break;
                case 9: $seq[] = $N - 10; break;
                case 10: $seq[] = $N - 1; break;
                case 11: $seq[] = 2; break;
                case 12: $seq[] = 14; break;
                case 13: $seq[] = $N - 13; break;
                case 14: $seq[] = $N - 22; break;
                case 15: $seq[] = 23; break;
            }
        } else {
            // Groupes suivants : utiliser le pattern de base avec offset
            $seq[] = $value + ($group_index * 16);
        }
    }
    
    return $seq;
}

function reordering_pages_a6($number_of_pages) {
    $total_pages = $number_of_pages;
    
    if ($total_pages <= 0) {
        throw new Exception("Le nombre de pages doit être strictement positif.");
    }
    
    // Si on a une séquence exacte codée, l'utiliser
    $exact_sequences = [16, 32, 48, 64];
    if (in_array($total_pages, $exact_sequences)) {
        $result = [];
        $num_groups = ceil($total_pages / 16);
        
        for ($group = 0; $group < $num_groups; $group++) {
            $sheet_seq = imposition_for_sheet($group, $total_pages);
            $result = array_merge($result, $sheet_seq);
        }
        return $result;
    }
    
    // Logique générique pour les autres cas
    $result = [];
    
    // Trouver le multiple de 16 le plus proche
    $nearest_multiple = ceil($total_pages / 16) * 16;
    $N = $nearest_multiple;
    
    $num_sheets = ceil($total_pages / 16);
    
    for ($sheet = 0; $sheet < $num_sheets; $sheet++) {
        // Utiliser la formule mathématique exacte
        $offset = $sheet * 2;
        
        // Première suite recto avec offset
        $recto = [
            1 + $offset,                           // 1,3,5,7...
            $N - $offset,                          // N,N-2,N-4,N-6...
            $N - ($N/4 - 1) + $offset,            // N-(N/4-1),N-(N/4-1)+2,...
            $N/4 - $offset,                        // N/4,N/4-2,N/4-4,...
            $N/2 - $offset,                        // N/2,N/2-2,N/2-4,...
            $N/2 + 1 + $offset,                    // N/2+1,N/2+3,N/2+5,...
            ($N/4) * 3 - $offset,                 // (N/4)*3,(N/4)*3-2,...
            $N/4 + 1 + $offset                     // N/4+1,N/4+3,N/4+5,...
        ];
        
        // Première suite verso avec offset
        $verso = [
            $N/4 - 1 - $offset,                    // N/4-1,N/4-3,N/4-5,...
            $N - ($N/4 - 1) + 1 + $offset,        // N-(N/4-1)+1,N-(N/4-1)+3,...
            $N - 1 - $offset,                      // N-1,N-3,N-5,...
            1 + 1 + $offset,                       // 2,4,6,8,...
            ($N/4 + 1) + 1 + $offset,             // N/4+2,N/4+4,N/4+6,...
            ($N/4) * 3 - 1 - $offset,             // (N/4)*3-1,(N/4)*3-3,...
            ($N/2 + 1) + 1 + $offset,             // N/2+2,N/2+4,N/2+6,...
            $N/2 - 1 - $offset                     // N/2-1,N/2-3,N/2-5,...
        ];
        
        // Filtrer les pages qui dépassent le nombre total de pages
        $recto = array_filter($recto, function($page) use ($total_pages) {
            return $page <= $total_pages && $page > 0;
        });
        $verso = array_filter($verso, function($page) use ($total_pages) {
            return $page <= $total_pages && $page > 0;
        });
        
        // Combiner recto + verso
        $sheet_seq = array_merge($recto, $verso);
        $result = array_merge($result, $sheet_seq);
    }
    
    return $result;
}

function resizeToA5($pdf, $template_id, $a5_width, $a5_height, $forceResize = false) {
    $size = $pdf->getTemplateSize($template_id);
    $orig_width = $size["width"];
    $orig_height = $size["height"];

    // Vérifier si le redimensionnement est nécessaire
    if ($orig_width <= $a5_width && $orig_height <= $a5_height && !$forceResize) {
        // Pas de redimensionnement nécessaire, mais centrer quand même
        $x_offset = ($a5_width - $orig_width) / 2;
        $y_offset = ($a5_height - $orig_height) / 2;
        return [$x_offset, $y_offset, $orig_width, $orig_height];
    }

    // Calcul de l'échelle pour adapter l'image sans déformation
    $scale = min($a5_width / $orig_width, $a5_height / $orig_height);

    // Nouvelles dimensions proportionnelles
    $new_width = $orig_width * $scale;
    $new_height = $orig_height * $scale;

    // Calcul du centrage dans A5
    $x_offset = ($a5_width - $new_width) / 2;
    $y_offset = ($a5_height - $new_height) / 2;

    return [$x_offset, $y_offset, $new_width, $new_height];
}

function resizeToA6($pdf, $template_id, $a6_width, $a6_height, $forceResize = false) {
    $size = $pdf->getTemplateSize($template_id);
    $orig_width = $size["width"];
    $orig_height = $size["height"];

    // Vérifier si le redimensionnement est nécessaire
    if ($orig_width <= $a6_width && $orig_height <= $a6_height && !$forceResize) {
        // Pas de redimensionnement nécessaire, mais centrer quand même
        $x_offset = ($a6_width - $orig_width) / 2;
        $y_offset = ($a6_height - $orig_height) / 2;
        return [$x_offset, $y_offset, $orig_width, $orig_height];
    }

    // Calcul de l'échelle pour adapter l'image sans déformation
    $scale = min($a6_width / $orig_width, $a6_height / $orig_height);

    // Nouvelles dimensions proportionnelles
    $new_width = $orig_width * $scale;
    $new_height = $orig_height * $scale;

    // Calcul du centrage dans A6
    $x_offset = ($a6_width - $new_width) / 2;
    $y_offset = ($a6_height - $new_height) / 2;

    return [$x_offset, $y_offset, $new_width, $new_height];
}

function addPageNumber($pdf, $page_num, $x, $y, $new_width, $new_height, $rotation) {
    // Désactiver l'ajout automatique de pages
    $pdf->setAutoPageBreak(false);
    
    // Ajouter le numéro de page en surbrillance (rouge sur fond jaune)
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(255, 0, 0); // Rouge
    $pdf->SetFillColor(255, 255, 0); // Jaune
    
    if ($rotation == 180) {
        $pdf->StartTransform();
        $pdf->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2)); // Rotation centrée
    }
    
    // Dessiner le fond jaune
    $pdf->Rect($x + 2, $y + 2, 20, 15, 'F');
    
    // Ajouter le numéro en rouge avec Cell (qui n'ajoutera pas de page grâce à setAutoPageBreak)
    $pdf->SetXY($x + 6, $y + 6);
    $pdf->Cell(15, 8, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    
    if ($rotation == 180) {
        $pdf->StopTransform();
    }
}

function drawCropMarks($pdf, $x, $y, $width, $height, $bleed_size = 3) {
    // Dessiner les traits de coupe aux 4 coins À L'INTÉRIEUR de la zone
    // Ligne noire plus épaisse pour les traits de coupe (0.5mm)
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(0, 0, 0); // Noir
    
    $mark_length = 10; // Longueur fixe de 10mm pour bien voir les marques
    
    // Coin supérieur gauche - lignes À L'INTÉRIEUR
    $pdf->Line($x, $y, $x + $mark_length, $y); // Horizontale vers la droite
    $pdf->Line($x, $y, $x, $y + $mark_length); // Verticale vers le bas
    
    // Coin supérieur droit - lignes À L'INTÉRIEUR
    $pdf->Line($x + $width, $y, $x + $width - $mark_length, $y); // Horizontale vers la gauche
    $pdf->Line($x + $width, $y, $x + $width, $y + $mark_length); // Verticale vers le bas
    
    // Coin inférieur gauche - lignes À L'INTÉRIEUR
    $pdf->Line($x, $y + $height, $x + $mark_length, $y + $height); // Horizontale vers la droite
    $pdf->Line($x, $y + $height, $x, $y + $height - $mark_length); // Verticale vers le haut
    
    // Coin inférieur droit - lignes À L'INTÉRIEUR
    $pdf->Line($x + $width, $y + $height, $x + $width - $mark_length, $y + $height); // Horizontale vers la gauche
    $pdf->Line($x + $width, $y + $height, $x + $width, $y + $height - $mark_length); // Verticale vers le haut
}

function drawCentralCropMarks($pdf, $x, $y, $width, $height) {
    // Dessiner les traits de coupe centraux pour A3→A4 selon l'orientation
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(0, 0, 0); // Noir
    
    // Détecter l'orientation de la page
    $page_width = $pdf->getPageWidth();
    $page_height = $pdf->getPageHeight();
    
    if ($page_width > $page_height) {
        // Paysage : trait vertical à 21cm (210mm) - haut et bas
        $center_x = 210; // 21cm = 210mm
        $mark_length = 8; // Plus court
        
        // Trait haut
        $pdf->Line($center_x, 5, $center_x, 5 + $mark_length);
        
        // Trait bas - utiliser la hauteur de la page
        $pdf->Line($center_x, $page_height - 5 - $mark_length, $center_x, $page_height - 5);
    } else {
        // Portrait : trait horizontal à 21cm (210mm) - gauche et droite
        $center_y = 210; // 21cm = 210mm
        $mark_length = 8; // Plus court
        
        // Trait gauche
        $pdf->Line(5, $center_y, 5 + $mark_length, $center_y);
        
        // Trait droite - utiliser la largeur de la page
        $pdf->Line($page_width - 5 - $mark_length, $center_y, $page_width - 5, $center_y);
    }
}

function drawAllCropMarks($pdf, $x, $y, $width, $height, $bleed_size, $crop_marks_type) {
    // Dessiner selon le type sélectionné
    if ($crop_marks_type === 'normal' || $crop_marks_type === 'both') {
        drawCropMarks($pdf, $x, $y, $width, $height, $bleed_size);
    }
    
    if ($crop_marks_type === 'central' || $crop_marks_type === 'both') {
        drawCentralCropMarks($pdf, $x, $y, $width, $height);
    }
}

function Action($conf)
{
    $array = array();
    $array['errors'] = array();
    $array['success'] = false;
    $array['result'] = '';
    $array['preview_url'] = '';
    $array['download_url'] = '';
    $array['page_count'] = 0;
    $array['ordered_pages'] = '';

    // Traitement du fichier PDF uploadé
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdf"])) {
        $pdfFile = $_FILES["pdf"]["tmp_name"];
        $originalFileName = $_FILES["pdf"]["name"];
        
        // Extraire le nom sans extension
        $originalFileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
        
        if ($_FILES["pdf"]["error"] !== UPLOAD_ERR_OK) {
            $array['errors'][] = "Erreur d'upload : " . $_FILES["pdf"]["error"];
            return template(__DIR__ . "/../view/imposition.html.php", $array);
        }
        
        if (!file_exists($pdfFile)) {
            $array['errors'][] = "Erreur : Fichier introuvable.";
            return template(__DIR__ . "/../view/imposition.html.php", $array);
        }

        // Vérifier que le fichier est bien un PDF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $pdfFile);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            $array['errors'][] = "Erreur : Le fichier n'est pas un PDF valide (type détecté: $mimeType).";
            return template(__DIR__ . "/../view/imposition.html.php", $array);
        }

        // Traitement principal avec gestion d'erreur globale et fallback Ghostscript
        $cleanedPdfFile = null;
        $usedGhostscript = false;
        $mainProcessingSuccess = false; // Flag pour éviter l'exécution du bloc de fallback
        
        try {
            // Essayer d'abord avec le PDF original
            $pdf = new TCPDI();
            $pageCount = $pdf->setSourceFile($pdfFile);
            $array['page_count'] = $pageCount;
            
            if ($pageCount <= 0) {
                throw new Exception("Impossible de lire le PDF ou PDF vide.");
            }

            // Récupérer le type d'imposition
            $imposition_type = isset($_POST['imposition_type']) ? $_POST['imposition_type'] : 'a5';
            
            // Récupérer les options des traits de coupe
            $add_crop_marks = isset($_POST['add_crop_marks']);
            $crop_marks_type = isset($_POST['crop_marks_type']) ? $_POST['crop_marks_type'] : 'normal';
            $imposition_mode = isset($_POST['imposition_mode']) ? $_POST['imposition_mode'] : 'brochure';
            $bleed_mode = isset($_POST['bleed_mode']) ? $_POST['bleed_mode'] : 'fullsize';
            $bleed_size = isset($_POST['bleed_size']) ? floatval($_POST['bleed_size']) : 3;
            
            
            // Réorganiser les pages selon le type d'imposition
            if ($imposition_type === 'a6') {
                $ordered_pages = reordering_pages_a6($pageCount);
            } else {
                $ordered_pages = reordering_pages_a5($pageCount);
            }
            $array['ordered_pages'] = implode(", ", $ordered_pages);
            
            // Convertir en tableau pour le traitement PDF
            $ordered_pages_array = $ordered_pages;

            // Dimensions selon le type d'imposition
            if ($imposition_type === 'a6') {
                // A3 en paysage pour contenir 16 pages A6
                $a3_width = 420;   // Largeur A3 en paysage (mm)
                $a3_height = 297;  // Hauteur A3 en paysage (mm)
                // Dimensions A6
                $page_width = 105;   // Largeur d'une page A6 (mm)
                $page_height = 148;  // Hauteur d'une page A6 (mm)
                $pages_per_side = 8; // 8 pages A6 par côté
                $pages_per_sheet = 16; // 16 pages A6 par feuille recto-verso
            } else {
                // A3 en portrait pour contenir 8 pages A5
                $a3_width = 297;   // Largeur A3 en portrait (mm)
                $a3_height = 420;  // Hauteur A3 en portrait (mm)
                // Dimensions A5
                $page_width = 148;   // Largeur d'une page A5 (mm)
                $page_height = 210;  // Hauteur d'une page A5 (mm)
                $pages_per_side = 4; // 4 pages A5 par côté
                $pages_per_sheet = 8; // 8 pages A5 par feuille recto-verso
            }
            
            // Ajuster les dimensions si mode de coupe avec redimensionnement
            $gutter_width = 0; // Gouttière (espace entre les pages)
            if ($add_crop_marks) {
                if ($bleed_mode === 'resize') {
                    // Réduire les dimensions des pages pour laisser place aux marges de coupe
                    $page_width -= ($bleed_size * 2);
                    $page_height -= ($bleed_size * 2);
                }
                if ($imposition_mode === 'livre') {
                    // Ajouter une gouttière entre les pages
                    $gutter_width = $bleed_size;
                }
            }

            // Vérifier si la case à cocher "Preview" est cochée
            $previewMode = isset($_POST['preview']);
            $forceResize = isset($_POST['force_resize']);

            // Créer les objets PDF
            $pdfFinal = new TCPDI();
            $pdfFinal->setSourceFile($pdfFile);
            $pdfFinal->setPrintHeader(false);
            $pdfFinal->setPrintFooter(false);
            
            $pdfPreview = null;
            $template_ids_preview = [];
            
            // Initialiser le preview pour A6 et A5
            if ($previewMode) {
                $pdfPreview = new TCPDI();
                $pdfPreview->setSourceFile($pdfFile);
                $pdfPreview->setPrintHeader(false);
                $pdfPreview->setPrintFooter(false);
                
                // NE PAS pré-importer pour A6, le faire au fur et à mesure
                // Pour A5, on pré-importe dans le bloc else plus bas
            }

            // Traitement de l'imposition
            if ($imposition_type === 'a6') {
                // Pour A6 : créer recto et verso séparés
                for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
                    $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
                    $recto_pages = array_slice($sheet_pages, 0, 8);
                    $verso_pages = array_slice($sheet_pages, 8, 8);
                    
                    // Créer la page recto
                    $pdfFinal->AddPage('L', [$a3_width, $a3_height]);
                    if ($previewMode) {
                        $pdfPreview->AddPage('L', [$a3_width, $a3_height]);
                    }
                    
                    // Calculer l'offset pour centrer la grille 2x4 sur la feuille A3
                    $grid_width = 4 * $page_width + (3 * $gutter_width);   // Largeur totale + 3 gouttières
                    $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale + 1 gouttière
                    $global_x_offset = ($a3_width - $grid_width) / 2;
                    $global_y_offset = ($a3_height - $grid_height) / 2;
                    
                    // Placer les 8 pages recto
                    for ($j = 0; $j < 8; $j++) {
                        $page_num = $recto_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x4 pour le recto
                        $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                        $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                        
                        // Ajouter la gouttière dans le calcul
                        $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                        $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        // Créer le preview en même temps
                        if ($previewMode) {
                            // Importer la page au moment de l'utiliser pour éviter les pages supplémentaires
                            if (!isset($template_ids_preview[$page_num])) {
                                $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                            }
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            $pages_before = $pdfPreview->getNumPages();
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                            $pages_after = $pdfPreview->getNumPages();
                            if ($pages_after != $pages_before) {
                            }
                        }
                        
                        // Dessiner les traits de coupe si activées (mode livre)
                        if ($add_crop_marks && $imposition_mode === 'livre') {
                            drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                        }
                    }
                    
                    // Hirondelles en mode brochure sur le RECTO A6 - 1 par A4 paysage (par ligne)
                    if ($add_crop_marks && $imposition_mode === 'brochure') {
                        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                        
                        // A4 paysage du HAUT (4 A6 côte à côte)
                        $a4_top_x = $global_x_offset + $crop_offset;
                        $a4_top_y = $global_y_offset + $crop_offset;
                        $a4_top_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_top_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                        
                        // A4 paysage du BAS (4 A6 côte à côte)
                        $a4_bottom_x = $global_x_offset + $crop_offset;
                        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                        $a4_bottom_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_bottom_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        
                    }
                    
                    // Créer la page verso
                    $pdfFinal->AddPage('L', [$a3_width, $a3_height]);
                    if ($previewMode) {
                        $pdfPreview->AddPage('L', [$a3_width, $a3_height]);
                    }
                    
                    // Calculer l'offset pour centrer la grille 2x4 sur la feuille A3
                    $grid_width = 4 * $page_width + (3 * $gutter_width);   // Largeur totale + 3 gouttières
                    $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale + 1 gouttière
                    $global_x_offset = ($a3_width - $grid_width) / 2;
                    $global_y_offset = ($a3_height - $grid_height) / 2;
                    
                    // Placer les 8 pages verso
                    for ($j = 0; $j < 8; $j++) {
                        $page_num = $verso_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x4 pour le verso
                        $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                        $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                        
                        // Ajouter la gouttière dans le calcul
                        $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                        $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        // Créer le preview en même temps
                        if ($previewMode) {
                            // Importer la page au moment de l'utiliser pour éviter les pages supplémentaires
                            if (!isset($template_ids_preview[$page_num])) {
                                $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                            }
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            $pages_before = $pdfPreview->getNumPages();
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                            $pages_after = $pdfPreview->getNumPages();
                            if ($pages_after != $pages_before) {
                            }
                        }
                        
                        // Dessiner les traits de coupe si activées (mode livre)
                        if ($add_crop_marks && $imposition_mode === 'livre') {
                            drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                        }
                    }
                    
                    // Hirondelles en mode brochure sur le VERSO A6 - 1 par A4 paysage (par ligne)
                    if ($add_crop_marks && $imposition_mode === 'brochure') {
                        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                        
                        // A4 paysage du HAUT (4 A6 côte à côte)
                        $a4_top_x = $global_x_offset + $crop_offset;
                        $a4_top_y = $global_y_offset + $crop_offset;
                        $a4_top_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_top_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                        
                        // A4 paysage du BAS (4 A6 côte à côte)
                        $a4_bottom_x = $global_x_offset + $crop_offset;
                        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                        $a4_bottom_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_bottom_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        
                    }
                }
            } else {
                // Initialiser le preview pour A5 uniquement
                if ($previewMode) {
                    $pdfPreview = new TCPDI();
                    $pdfPreview->setSourceFile($pdfFile);
                    $pdfPreview->setPrintHeader(false);
                    $pdfPreview->setPrintFooter(false);
                    
                    // Pré-importer tous les templates pour éviter les pages supplémentaires
                    for ($page_num = 1; $page_num <= $pageCount; $page_num++) {
                        $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                    }
                }
                
                // Pour A5 : créer recto et verso séparés (4 pages par côté)
                for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
                    $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
                    $recto_pages = array_slice($sheet_pages, 0, 4); // 4 pages recto
                    $verso_pages = array_slice($sheet_pages, 4, 4); // 4 pages verso
                    
                    // Créer la page recto
                    $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                    if ($previewMode) {
                        $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                    }
                    
                    // Calculer l'offset pour centrer la grille 2x2 sur la feuille A3
                    $grid_width = 2 * $page_width + $gutter_width;   // Largeur totale de la grille + gouttière
                    $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale de la grille + gouttière
                    $global_x_offset = ($a3_width - $grid_width) / 2;
                    $global_y_offset = ($a3_height - $grid_height) / 2;
                    
                    // Placer les 4 pages recto
                    for ($j = 0; $j < 4; $j++) {
                        $page_num = $recto_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x2 pour le recto
                        $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                        $page_col = $j % 2;          // 0, 1 (2 colonnes)
                        
                        // DEBUG: Log pour la première page seulement
                        if ($j == 0 && $i == 0) {
                        }
                        
                        // Ajouter la gouttière dans le calcul
                        $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                        $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                        
                        // Rotation de 180° pour la deuxième ligne (tête-bêche)
                        if ($page_row == 1) {
                            $pdfFinal->StartTransform();
                            $pdfFinal->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                        }
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($page_row == 1) {
                            $pdfFinal->StopTransform();
                        }
                        
                        // Dessiner les traits de coupe si activées (mode livre)
                        if ($add_crop_marks && $imposition_mode === 'livre') {
                            drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                        }
                        
                        if ($previewMode) {
                            // Rotation de 180° pour la deuxième ligne (tête-bêche)
                            if ($page_row == 1) {
                                $pdfPreview->StartTransform();
                                $pdfPreview->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                            }
                            
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            
                            if ($page_row == 1) {
                                $pdfPreview->StopTransform();
                            }
                            
                            // Dessiner les traits de coupe dans le preview aussi
                            if ($add_crop_marks && $imposition_mode === 'livre') {
                                drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                            }
                            
                            // Ajouter le numéro de page (avec rotation si nécessaire)
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $page_row == 1 ? 180 : 0);
                        }
                    }
                    
                    // Hirondelles en mode brochure sur le RECTO - 1 par A4 paysage (par ligne)
                    if ($add_crop_marks && $imposition_mode === 'brochure') {
                        // Ajuster le décalage selon le mode bleed
                        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                        
                        // A4 paysage du HAUT (2 A5 côte à côte)
                        $a4_top_x = $global_x_offset + $crop_offset;
                        $a4_top_y = $global_y_offset + $crop_offset;
                        $a4_top_width = (2 * $page_width) - $crop_width_reduction;
                        $a4_top_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                        
                        // A4 paysage du BAS (2 A5 côte à côte)
                        $a4_bottom_x = $global_x_offset + $crop_offset;
                        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                        $a4_bottom_width = (2 * $page_width) - $crop_width_reduction;
                        $a4_bottom_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        
                        if ($previewMode) {
                            drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                            drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        }
                    }
                    
                    // Créer la page verso
                    $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                    if ($previewMode) {
                        $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                    }
                    
                    // Calculer l'offset pour centrer la grille 2x2 sur la feuille A3
                    $grid_width = 2 * $page_width + $gutter_width;   // Largeur totale de la grille + gouttière
                    $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale de la grille + gouttière
                    $global_x_offset = ($a3_width - $grid_width) / 2;
                    $global_y_offset = ($a3_height - $grid_height) / 2;
                    
                    // Placer les 4 pages verso
                    for ($j = 0; $j < 4; $j++) {
                        $page_num = $verso_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x2 pour le verso
                        $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                        $page_col = $j % 2;          // 0, 1 (2 colonnes)
                        
                        // Ajouter la gouttière dans le calcul
                        $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                        $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                        
                        // Rotation de 180° pour la deuxième ligne (tête-bêche)
                        if ($page_row == 1) {
                            $pdfFinal->StartTransform();
                            $pdfFinal->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                        }
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($page_row == 1) {
                            $pdfFinal->StopTransform();
                        }
                        
                        // Dessiner les traits de coupe si activées (mode livre)
                        if ($add_crop_marks && $imposition_mode === 'livre') {
                            drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                        }
                        
                        if ($previewMode) {
                            // Rotation de 180° pour la deuxième ligne (tête-bêche)
                            if ($page_row == 1) {
                                $pdfPreview->StartTransform();
                                $pdfPreview->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                            }
                            
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            
                            if ($page_row == 1) {
                                $pdfPreview->StopTransform();
                            }
                            
                            // Dessiner les traits de coupe dans le preview aussi
                            if ($add_crop_marks && $imposition_mode === 'livre') {
                                drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                            }
                            
                            // Ajouter le numéro de page (avec rotation si nécessaire)
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $page_row == 1 ? 180 : 0);
                        }
                    }
                    
                    // Hirondelles en mode brochure sur le VERSO - 1 par A4 paysage (par ligne)
                    if ($add_crop_marks && $imposition_mode === 'brochure') {
                        // Ajuster le décalage selon le mode bleed
                        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                        
                        // A4 paysage du HAUT (2 A5 côte à côte)
                        $a4_top_x = $global_x_offset + $crop_offset;
                        $a4_top_y = $global_y_offset + $crop_offset;
                        $a4_top_width = (2 * $page_width) - $crop_width_reduction;
                        $a4_top_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                        
                        // A4 paysage du BAS (2 A5 côte à côte)
                        $a4_bottom_x = $global_x_offset + $crop_offset;
                        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                        $a4_bottom_width = (2 * $page_width) - $crop_width_reduction;
                        $a4_bottom_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        
                        if ($previewMode) {
                            drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                            drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        }
                    }
                }
            }
            
            // Sauvegarde des fichiers résultants
            $timestamp = date('YmdHis');
            // Utiliser un répertoire temporaire système pour Ghostscript
            $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
            
            if (!file_exists($tmp_dir)) {
                mkdir($tmp_dir, 0755, true);
            }
            
            // Nettoyer le nom de fichier pour éviter les problèmes
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);

            // Sauvegarder le PDF final
            $final_filename = $safe_filename . '_imposed.pdf';
            $output_pdf_path_final = $tmp_dir . $final_filename;
            $pdfFinal->Output($output_pdf_path_final, 'F');
            
            // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
            $array['download_url'] = 'download_pdf.php?file=' . $final_filename;
            
            if ($previewMode) {
                
                // Sauvegarder le preview (créé en même temps que le final)
                $preview_filename = $safe_filename . '_preview.pdf';
                $output_pdf_path_preview = $tmp_dir . $preview_filename;
                $pdfPreview->Output($output_pdf_path_preview, 'F');
                
                
                // Utiliser l'endpoint d'affichage pour la prévisualisation avec timestamp pour éviter le cache
                $array['preview_url'] = 'view_pdf.php?file=' . $preview_filename . '&t=' . time();
            }
            
            $array['success'] = true;
            $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages.";
            
            // Marquer que le traitement principal a réussi pour éviter le fallback
            $mainProcessingSuccess = true;
            error_log("DEBUG: Traitement principal réussi, flag mainProcessingSuccess = true");
            
        } catch (Exception $e) {
            // Première tentative échouée, essayer avec Ghostscript
            // Mais seulement si le traitement principal n'a pas réussi
            error_log("DEBUG: EXCEPTION CAPTURÉE - Dans le catch, mainProcessingSuccess = " . ($mainProcessingSuccess ? 'true' : 'false'));
            if (isset($mainProcessingSuccess) && $mainProcessingSuccess) {
                // Le traitement principal a réussi, ne pas exécuter le fallback
                error_log("DEBUG: Traitement principal réussi, sortie du bloc de fallback");
                return $array;
            }
            
            try {
                error_log("DEBUG: BLOC DE FALLBACK EXÉCUTÉ - Première tentative échouée, nettoyage avec Ghostscript: " . $e->getMessage());
                error_log("DEBUG: mainProcessingSuccess dans le bloc de fallback = " . ($mainProcessingSuccess ? 'true' : 'false'));
                
                // Créer un fichier temporaire nettoyé
                $timestamp = date('YmdHis');
            // Utiliser le répertoire temporaire système cross-platform
            $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
                
                if (!file_exists($tmp_dir)) {
                    mkdir($tmp_dir, 0755, true);
                }
                
                $cleanedPdfFile = $tmp_dir . 'cleaned_' . $timestamp . '.pdf';
                
                // Nettoyer le PDF avec Ghostscript - détection automatique de la plateforme
                if (PHP_OS_FAMILY === 'Windows') {
                    // Chemin complet vers Ghostscript Windows
                    $gs_command = __DIR__ . '/../../ghostscript/gswin64c.exe';
                    if (!file_exists($gs_command)) {
                        throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
                    }
                } else {
                    $gs_command = 'gs';
                }
                $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($pdfFile) . " 2>&1";
                $output = shell_exec($command);
                
                if (!file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
                    throw new Exception("Échec du nettoyage Ghostscript. Sortie: " . $output);
                }
                
                // Réessayer avec le PDF nettoyé
                $pdf = new TCPDI();
                $pageCount = $pdf->setSourceFile($cleanedPdfFile);
                $array['page_count'] = $pageCount;
                
                if ($pageCount <= 0) {
                    throw new Exception("Impossible de lire le PDF nettoyé ou PDF vide.");
                }
                
                $usedGhostscript = true;
                $pdfFile = $cleanedPdfFile; // Utiliser le fichier nettoyé
                
                // Récupérer le nom du fichier original pour le nom de sortie
                $originalFileName = isset($_FILES["pdf"]["name"]) ? $_FILES["pdf"]["name"] : "document.pdf";
                $originalFileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);
                
                // Récupérer le type d'imposition
                $imposition_type = isset($_POST['imposition_type']) ? $_POST['imposition_type'] : 'a5';
                
                // Récupérer les options des traits de coupe
                $add_crop_marks = isset($_POST['add_crop_marks']);
                $crop_marks_type = isset($_POST['crop_marks_type']) ? $_POST['crop_marks_type'] : 'normal';
                $imposition_mode = isset($_POST['imposition_mode']) ? $_POST['imposition_mode'] : 'brochure';
                $bleed_mode = isset($_POST['bleed_mode']) ? $_POST['bleed_mode'] : 'fullsize';
                $bleed_size = isset($_POST['bleed_size']) ? floatval($_POST['bleed_size']) : 3;
                
                // Réorganiser les pages selon le type d'imposition
                if ($imposition_type === 'a6') {
                    $ordered_pages = reordering_pages_a6($pageCount);
                } else {
                    $ordered_pages = reordering_pages_a5($pageCount);
                }
                $array['ordered_pages'] = implode(", ", $ordered_pages);
                
                // Convertir en tableau pour le traitement PDF
                $ordered_pages_array = $ordered_pages;

                // Dimensions selon le type d'imposition
                if ($imposition_type === 'a6') {
                    // A3 en paysage pour contenir 16 pages A6
                    $a3_width = 420;   // Largeur A3 en paysage (mm)
                    $a3_height = 297;  // Hauteur A3 en paysage (mm)
                    // Dimensions A6
                    $page_width = 105;   // Largeur d'une page A6 (mm)
                    $page_height = 148;  // Hauteur d'une page A6 (mm)
                    $pages_per_side = 8; // 8 pages A6 par côté
                    $pages_per_sheet = 16; // 16 pages A6 par feuille recto-verso
                } else {
                    // A3 en portrait pour contenir 8 pages A5
                    $a3_width = 297;   // Largeur A3 en portrait (mm)
                    $a3_height = 420;  // Hauteur A3 en portrait (mm)
                    // Dimensions A5
                    $page_width = 148;   // Largeur d'une page A5 (mm)
                    $page_height = 210;  // Hauteur d'une page A5 (mm)
                    $pages_per_side = 4; // 4 pages A5 par côté
                    $pages_per_sheet = 8; // 8 pages A5 par feuille recto-verso
                }
                
                // Ajuster les dimensions si mode de coupe avec redimensionnement
                $gutter_width = 0; // Gouttière (espace entre les pages)
                if ($add_crop_marks) {
                    if ($bleed_mode === 'resize') {
                        // Réduire les dimensions des pages pour laisser place aux marges de coupe
                        $page_width -= ($bleed_size * 2);
                        $page_height -= ($bleed_size * 2);
                    }
                    if ($imposition_mode === 'livre') {
                        // Ajouter une gouttière entre les pages
                        $gutter_width = $bleed_size;
                    }
                }

                // Vérifier si la case à cocher "Preview" est cochée
                $previewMode = isset($_POST['preview']);
                $forceResize = isset($_POST['force_resize']);

                // Créer deux objets PDF
                $pdfFinal = new TCPDI();
                $pdfFinal->setSourceFile($pdfFile);
                $pdfFinal->setPrintHeader(false);
                $pdfFinal->setPrintFooter(false);
                
                // Initialiser le preview pour le bloc Ghostscript
                $pdfPreview = null;
                $template_ids_preview = [];
                if ($previewMode) {
                    $pdfPreview = new TCPDI();
                    $pdfPreview->setSourceFile($pdfFile);
                    $pdfPreview->setPrintHeader(false);
                    $pdfPreview->setPrintFooter(false);
                    // NE PAS pré-importer pour A6, le faire au fur et à mesure
                }

                // Traitement de l'imposition
                if ($imposition_type === 'a6') {
                    // Pour A6 : créer recto et verso séparés (même logique que le bloc principal)
                    for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
                        $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
                        $recto_pages = array_slice($sheet_pages, 0, 8);
                        $verso_pages = array_slice($sheet_pages, 8, 8);
                        
                        // Créer la page recto
                        $pdfFinal->AddPage('L', [$a3_width, $a3_height]);
                        if ($previewMode) {
                            $pdfPreview->AddPage('L', [$a3_width, $a3_height]);
                        }
                        
                        // Calculer l'offset pour centrer la grille 2x4 sur la feuille A3
                        $grid_width = 4 * $page_width + (3 * $gutter_width);   // Largeur totale + 3 gouttières
                        $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale + 1 gouttière
                        $global_x_offset = ($a3_width - $grid_width) / 2;
                        $global_y_offset = ($a3_height - $grid_height) / 2;
                        
                        // Placer les 8 pages recto
                        for ($j = 0; $j < 8; $j++) {
                            $page_num = $recto_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x4 pour le recto
                            $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                            $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                            
                            // Ajouter la gouttière dans le calcul
                            $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                            $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            if ($previewMode) {
                                // Importer la page au moment de l'utiliser
                                if (!isset($template_ids_preview[$page_num])) {
                                    $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                                }
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                            }
                        }
                        
                        // Créer la page verso
                        $pdfFinal->AddPage('L', [$a3_width, $a3_height]);
                        if ($previewMode) {
                            $pdfPreview->AddPage('L', [$a3_width, $a3_height]);
                        }
                        
                    // Calculer l'offset pour centrer la grille 2x4 sur la feuille A3
                    $grid_width = 4 * $page_width + (3 * $gutter_width);   // Largeur totale + 3 gouttières
                    $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale + 1 gouttière
                    $global_x_offset = ($a3_width - $grid_width) / 2;
                    $global_y_offset = ($a3_height - $grid_height) / 2;
                    
                    // Placer les 8 pages verso
                    for ($j = 0; $j < 8; $j++) {
                        $page_num = $verso_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x4 pour le verso
                        $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                        $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                        
                        // Ajouter la gouttière dans le calcul
                        $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                        $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        // Dessiner les traits de coupe si activées (mode livre)
                        if ($add_crop_marks && $imposition_mode === 'livre') {
                            drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                        }
                        
                        if ($previewMode) {
                            // Importer la page au moment de l'utiliser
                            if (!isset($template_ids_preview[$page_num])) {
                                $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                            }
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            
                            // Dessiner les traits de coupe dans le preview aussi
                            if ($add_crop_marks && $imposition_mode === 'livre') {
                                drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                            }
                            
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                        }
                    }
                    
                    // Hirondelles en mode brochure sur le VERSO A6 - 1 par A4 paysage (par ligne)
                    if ($add_crop_marks && $imposition_mode === 'brochure') {
                        $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                        $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                        
                        // A4 paysage du HAUT (4 A6 côte à côte)
                        $a4_top_x = $global_x_offset + $crop_offset;
                        $a4_top_y = $global_y_offset + $crop_offset;
                        $a4_top_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_top_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                        
                        // A4 paysage du BAS (4 A6 côte à côte)
                        $a4_bottom_x = $global_x_offset + $crop_offset;
                        $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                        $a4_bottom_width = (4 * $page_width) - $crop_width_reduction;
                        $a4_bottom_height = $page_height - $crop_width_reduction;
                        drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        
                        if ($previewMode) {
                            drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                            drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                        }
                    }
                }
            } else {
                    // Initialiser le preview pour A5 dans le bloc Ghostscript
                    if ($previewMode) {
                        $pdfPreview = new TCPDI();
                        $pdfPreview->setSourceFile($pdfFile);
                        $pdfPreview->setPrintHeader(false);
                        $pdfPreview->setPrintFooter(false);
                        
                        // Pré-importer tous les templates pour A5
                        for ($page_num = 1; $page_num <= $pageCount; $page_num++) {
                            $template_ids_preview[$page_num] = $pdfPreview->importPage($page_num);
                        }
                    }
                    
                    // Pour A5 : créer recto et verso séparés (4 pages par côté)
                    for ($i = 0; $i < count($ordered_pages_array); $i += $pages_per_sheet) {
                        $sheet_pages = array_slice($ordered_pages_array, $i, $pages_per_sheet);
                        $recto_pages = array_slice($sheet_pages, 0, 4); // 4 pages recto
                        $verso_pages = array_slice($sheet_pages, 4, 4); // 4 pages verso
                        
                        // Créer la page recto
                        $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                        if ($previewMode) {
                            $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                        }
                        
                        // Calculer l'offset pour centrer la grille 2x2 sur la feuille A3
                        $grid_width = 2 * $page_width + $gutter_width;   // Largeur totale de la grille + gouttière
                        $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale de la grille + gouttière
                        $global_x_offset = ($a3_width - $grid_width) / 2;
                        $global_y_offset = ($a3_height - $grid_height) / 2;
                        
                        // Placer les 4 pages recto
                        for ($j = 0; $j < 4; $j++) {
                            $page_num = $recto_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x2 pour le recto
                            $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                            $page_col = $j % 2;          // 0, 1 (2 colonnes)
                            
                            // Ajouter la gouttière dans le calcul
                            $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                            $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                            
                            // Rotation de 180° pour la deuxième ligne (tête-bêche)
                            if ($page_row == 1) {
                                $pdfFinal->StartTransform();
                                $pdfFinal->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                            }
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            
                            if ($page_row == 1) {
                                $pdfFinal->StopTransform();
                            }
                            
                            // Dessiner les traits de coupe si activées (mode livre)
                            if ($add_crop_marks && $imposition_mode === 'livre') {
                                drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                            }
                            
                            if ($previewMode) {
                                // Rotation de 180° pour la deuxième ligne (tête-bêche)
                                if ($page_row == 1) {
                                    $pdfPreview->StartTransform();
                                    $pdfPreview->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                                }
                                
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                
                                if ($page_row == 1) {
                                    $pdfPreview->StopTransform();
                                }
                                
                                // Dessiner les traits de coupe dans le preview aussi
                                if ($add_crop_marks && $imposition_mode === 'livre') {
                                    drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                                }
                                
                                // Ajouter le numéro de page (avec rotation si nécessaire)
                                addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $page_row == 1 ? 180 : 0);
                            }
                        }
                        
                        // Hirondelles en mode brochure sur le RECTO - 1 par A4 paysage (par ligne)
                        if ($add_crop_marks && $imposition_mode === 'brochure') {
                            // Ajuster le décalage selon le mode bleed
                            $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                            $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                            
                            // A4 paysage du HAUT (2 A5 côte à côte)
                            $a4_top_x = $global_x_offset + $crop_offset;
                            $a4_top_y = $global_y_offset + $crop_offset;
                            $a4_top_width = (2 * $page_width) - $crop_width_reduction;
                            $a4_top_height = $page_height - $crop_width_reduction;
                            drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                            
                            // A4 paysage du BAS (2 A5 côte à côte)
                            $a4_bottom_x = $global_x_offset + $crop_offset;
                            $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                            $a4_bottom_width = (2 * $page_width) - $crop_width_reduction;
                            $a4_bottom_height = $page_height - $crop_width_reduction;
                            drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                            
                            if ($previewMode) {
                                drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                                drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                            }
                        }
                        
                        // Créer la page verso
                        $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                        if ($previewMode) {
                            $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                        }
                        
                        // Calculer l'offset pour centrer la grille 2x2 sur la feuille A3
                        $grid_width = 2 * $page_width + $gutter_width;   // Largeur totale de la grille + gouttière
                        $grid_height = 2 * $page_height + $gutter_width; // Hauteur totale de la grille + gouttière
                        $global_x_offset = ($a3_width - $grid_width) / 2;
                        $global_y_offset = ($a3_height - $grid_height) / 2;
                        
                        // Placer les 4 pages verso
                        for ($j = 0; $j < 4; $j++) {
                            $page_num = $verso_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x2 pour le verso
                            $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                            $page_col = $j % 2;          // 0, 1 (2 colonnes)
                            
                            // Ajouter la gouttière dans le calcul
                            $x = $global_x_offset + $page_col * ($page_width + $gutter_width) + $x_offset;
                            $y = $global_y_offset + $page_row * ($page_height + $gutter_width) + $y_offset;
                            
                            // Rotation de 180° pour la deuxième ligne (tête-bêche)
                            if ($page_row == 1) {
                                $pdfFinal->StartTransform();
                                $pdfFinal->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                            }
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            
                            if ($page_row == 1) {
                                $pdfFinal->StopTransform();
                            }
                            
                            // Dessiner les traits de coupe si activées (mode livre)
                            if ($add_crop_marks && $imposition_mode === 'livre') {
                                drawAllCropMarks($pdfFinal, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                            }
                            
                            if ($previewMode) {
                                // Rotation de 180° pour la deuxième ligne (tête-bêche)
                                if ($page_row == 1) {
                                    $pdfPreview->StartTransform();
                                    $pdfPreview->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2));
                                }
                                
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                
                                if ($page_row == 1) {
                                    $pdfPreview->StopTransform();
                                }
                                
                                // Dessiner les traits de coupe dans le preview aussi
                                if ($add_crop_marks && $imposition_mode === 'livre') {
                                    drawAllCropMarks($pdfPreview, $x, $y, $new_width, $new_height, $bleed_size, $crop_marks_type);
                                }
                                
                                // Ajouter le numéro de page (avec rotation si nécessaire)
                                addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, $page_row == 1 ? 180 : 0);
                            }
                        }
                        
                        // Hirondelles en mode brochure sur le VERSO - 1 par A4 paysage (par ligne)
                        if ($add_crop_marks && $imposition_mode === 'brochure') {
                            // Ajuster le décalage selon le mode bleed
                            $crop_offset = ($bleed_mode === 'resize') ? 0 : $bleed_size;
                            $crop_width_reduction = ($bleed_mode === 'resize') ? 0 : (2 * $bleed_size);
                            
                            // A4 paysage du HAUT (2 A5 côte à côte)
                            $a4_top_x = $global_x_offset + $crop_offset;
                            $a4_top_y = $global_y_offset + $crop_offset;
                            $a4_top_width = (2 * $page_width) - $crop_width_reduction;
                            $a4_top_height = $page_height - $crop_width_reduction;
                            drawAllCropMarks($pdfFinal, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                            
                            // A4 paysage du BAS (2 A5 côte à côte)
                            $a4_bottom_x = $global_x_offset + $crop_offset;
                            $a4_bottom_y = $global_y_offset + $page_height + $crop_offset;
                            $a4_bottom_width = (2 * $page_width) - $crop_width_reduction;
                            $a4_bottom_height = $page_height - $crop_width_reduction;
                            drawAllCropMarks($pdfFinal, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                            
                            if ($previewMode) {
                                drawAllCropMarks($pdfPreview, $a4_top_x, $a4_top_y, $a4_top_width, $a4_top_height, $bleed_size, $crop_marks_type);
                                drawAllCropMarks($pdfPreview, $a4_bottom_x, $a4_bottom_y, $a4_bottom_width, $a4_bottom_height, $bleed_size, $crop_marks_type);
                            }
                        }
                    }
                }
                
                // Sauvegarde des fichiers résultants
                $timestamp = date('YmdHis');
            // Utiliser le répertoire temporaire système cross-platform
            $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
                
                if (!file_exists($tmp_dir)) {
                    mkdir($tmp_dir, 0755, true);
                }

                if ($previewMode) {
                    $preview_filename = $safe_filename . '_preview.pdf';
                    $output_pdf_path_preview = $tmp_dir . $preview_filename;
                    $pdfPreview->Output($output_pdf_path_preview, 'F');
                    
                    // Utiliser l'endpoint d'affichage pour la prévisualisation avec timestamp pour éviter le cache
                    $array['preview_url'] = 'view_pdf.php?file=' . $preview_filename . '&t=' . time();
                }

                // Utiliser le nom du fichier original avec suffixe
                $final_filename = $safe_filename . '_imposed.pdf';
                $output_pdf_path_final = $tmp_dir . $final_filename;
                $pdfFinal->Output($output_pdf_path_final, 'F');
                
                // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
                $array['download_url'] = 'download_pdf.php?file=' . $final_filename;
                
                $array['success'] = true;
                $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages. (Nettoyé avec Ghostscript)";
                
                // Nettoyer le fichier temporaire nettoyé
                if (file_exists($cleanedPdfFile)) {
                    unlink($cleanedPdfFile);
                }
                
            } catch (Exception $e2) {
                // Gestion d'erreur globale avec proposition de fallback
                $errorMessage = "Erreur lors du traitement du PDF : " . $e->getMessage();
                $array['errors'][] = $errorMessage;
                $array['errors'][] = "Tentative de nettoyage avec Ghostscript échouée : " . $e2->getMessage();
                
                // Message d'erreur final
                $array['errors'][] = "Impossible de traiter ce PDF avec les outils disponibles.";
                $array['fallback_url'] = null;
                
                // Nettoyer le fichier temporaire en cas d'erreur
                if ($cleanedPdfFile && file_exists($cleanedPdfFile)) {
                    unlink($cleanedPdfFile);
                }
                
                error_log("Erreur imposition PDF: " . $e->getMessage() . " - Erreur Ghostscript: " . $e2->getMessage() . " - Fichier: " . ($_FILES["pdf"]["name"] ?? "inconnu"));
            }
        }
    }
    
    return template(__DIR__ . "/../view/imposition.html.php", $array);
}

?>