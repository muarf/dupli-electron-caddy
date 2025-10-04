<?php
require_once(__DIR__ . '/../vendor/autoload.php');
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
            [1, 16, 13, 4, 8, 9, 12, 5, 3, 14, 15, 2, 6, 11, 10, 7]
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
    $base_pattern = [1, 16, 13, 4, 8, 9, 12, 5, 3, 14, 15, 2, 6, 11, 10, 7];
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
        // Pas de redimensionnement nécessaire
        return [0, 0, $orig_width, $orig_height];
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
        // Pas de redimensionnement nécessaire
        return [0, 0, $orig_width, $orig_height];
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
    // Ajouter le numéro de page en transparence
    $pdf->SetFont('Helvetica', '', 150);
    $pdf->SetTextColor(192, 192, 192); // Couleur gris clair
    $pdf->SetAlpha(0.7 ); // Transparence
    if ($rotation == 180) {
        $pdf->StartTransform();
        $pdf->Rotate(180, $x + ($new_width / 2), $y + ($new_height / 2)); // Rotation centrée
    }
    $pdf->SetXY($x, $y);
    $pdf->Cell($new_width, $new_height, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
    if ($rotation == 180) {
        $pdf->StopTransform();
    }
    $pdf->SetAlpha(1); // Réinitialiser la transparence
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
                    
                    // Placer les 8 pages recto
                    for ($j = 0; $j < 8; $j++) {
                        $page_num = $recto_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x4 pour le recto
                        $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                        $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                        
                        $x = $page_col * $page_width + $x_offset;
                        $y = $page_row * $page_height + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($previewMode) {
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
                    
                    // Placer les 8 pages verso
                    for ($j = 0; $j < 8; $j++) {
                        $page_num = $verso_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x4 pour le verso
                        $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                        $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                        
                        $x = $page_col * $page_width + $x_offset;
                        $y = $page_row * $page_height + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($previewMode) {
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                        }
                    }
                }
            } else {
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
                    
                    // Placer les 4 pages recto
                    for ($j = 0; $j < 4; $j++) {
                        $page_num = $recto_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x2 pour le recto
                        $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                        $page_col = $j % 2;          // 0, 1 (2 colonnes)
                        
                        $x = $page_col * $page_width + $x_offset;
                        $y = $page_row * $page_height + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($previewMode) {
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            // Ajouter le numéro de page en surbrillance
                            $pdfPreview->SetFont('helvetica', 'B', 20);
                            $pdfPreview->SetTextColor(255, 0, 0); // Rouge
                            $pdfPreview->SetFillColor(255, 255, 0); // Jaune
                            $pdfPreview->Rect($x + 2, $y + 2, 20, 15, 'F'); // Fond jaune plus grand
                            $pdfPreview->SetXY($x + 6, $y + 6);
                            $pdfPreview->Cell(15, 8, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
                        }
                    }
                    
                    // Créer la page verso
                    $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                    if ($previewMode) {
                        $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                    }
                    
                    // Placer les 4 pages verso
                    for ($j = 0; $j < 4; $j++) {
                        $page_num = $verso_pages[$j];
                        if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                        
                        $template_id = $pdfFinal->importPage($page_num);
                        list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                        
                        // Position en grille 2x2 pour le verso
                        $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                        $page_col = $j % 2;          // 0, 1 (2 colonnes)
                        
                        $x = $page_col * $page_width + $x_offset;
                        $y = $page_row * $page_height + $y_offset;
                        
                        $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                        
                        if ($previewMode) {
                            $template_id_preview = $template_ids_preview[$page_num];
                            $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                            // Ajouter le numéro de page en surbrillance
                            $pdfPreview->SetFont('helvetica', 'B', 20);
                            $pdfPreview->SetTextColor(255, 0, 0); // Rouge
                            $pdfPreview->SetFillColor(255, 255, 0); // Jaune
                            $pdfPreview->Rect($x + 2, $y + 2, 20, 15, 'F'); // Fond jaune plus grand
                            $pdfPreview->SetXY($x + 6, $y + 6);
                            $pdfPreview->Cell(15, 8, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
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

            // Sauvegarder le PDF final
            $output_pdf_path_final = $tmp_dir . 'imposition_final_' . $timestamp . '.pdf';
            $pdfFinal->Output($output_pdf_path_final, 'F');
            
            // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
            $array['download_url'] = 'download_pdf.php?file=imposition_final_' . $timestamp . '.pdf';
            
            if ($previewMode) {
                // Sauvegarder la prévisualisation avec numéros
                $output_pdf_path_preview = $tmp_dir . 'imposition_preview_' . $timestamp . '.pdf';
                $pdfPreview->Output($output_pdf_path_preview, 'F');
                
                // Utiliser l'endpoint d'affichage pour la prévisualisation
                $array['preview_url'] = 'view_pdf.php?file=imposition_preview_' . $timestamp . '.pdf';
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
                    $gs_command = __DIR__ . '/../../ghostscript/gs10060w64.exe';
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
                
                // Récupérer le type d'imposition
                $imposition_type = isset($_POST['imposition_type']) ? $_POST['imposition_type'] : 'a5';
                
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

                // Vérifier si la case à cocher "Preview" est cochée
                $previewMode = isset($_POST['preview']);
                $forceResize = isset($_POST['force_resize']);

                // Créer deux objets PDF
                $pdfFinal = new TCPDI();
                $pdfPreview = null;
                $template_ids_preview = [];

                $pdfFinal->setSourceFile($pdfFile);
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

                $pdfFinal->setPrintHeader(false);
                $pdfFinal->setPrintFooter(false);

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
                        
                        // Placer les 8 pages recto
                        for ($j = 0; $j < 8; $j++) {
                            $page_num = $recto_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x4 pour le recto
                            $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                            $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                            
                            $x = $page_col * $page_width + $x_offset;
                            $y = $page_row * $page_height + $y_offset;
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            if ($previewMode) {
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
                        
                        // Placer les 8 pages verso
                        for ($j = 0; $j < 8; $j++) {
                            $page_num = $verso_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA6($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x4 pour le verso
                            $page_row = intval($j / 4);  // 0, 1 (2 rangées)
                            $page_col = $j % 4;          // 0, 1, 2, 3 (4 colonnes)
                            
                            $x = $page_col * $page_width + $x_offset;
                            $y = $page_row * $page_height + $y_offset;
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            if ($previewMode) {
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                addPageNumber($pdfPreview, $page_num, $x, $y, $new_width, $new_height, 0);
                            }
                        }
                    }
                } else {
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
                        
                        // Placer les 4 pages recto
                        for ($j = 0; $j < 4; $j++) {
                            $page_num = $recto_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x2 pour le recto
                            $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                            $page_col = $j % 2;          // 0, 1 (2 colonnes)
                            
                            $x = $page_col * $page_width + $x_offset;
                            $y = $page_row * $page_height + $y_offset;
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            
                            if ($previewMode) {
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                // Ajouter le numéro de page en surbrillance
                                $pdfPreview->SetFont('helvetica', 'B', 20);
                                $pdfPreview->SetTextColor(255, 0, 0); // Rouge
                                $pdfPreview->SetFillColor(255, 255, 0); // Jaune
                                $pdfPreview->Rect($x + 2, $y + 2, 20, 15, 'F'); // Fond jaune plus grand
                                $pdfPreview->SetXY($x + 6, $y + 6);
                                $pdfPreview->Cell(15, 8, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
                            }
                        }
                        
                        // Créer la page verso
                        $pdfFinal->AddPage('P', [$a3_width, $a3_height]);
                        if ($previewMode) {
                            $pdfPreview->AddPage('P', [$a3_width, $a3_height]);
                        }
                        
                        // Placer les 4 pages verso
                        for ($j = 0; $j < 4; $j++) {
                            $page_num = $verso_pages[$j];
                            if ($page_num === "blank_page" || $page_num <= 0 || $page_num > $pageCount) continue;
                            
                            $template_id = $pdfFinal->importPage($page_num);
                            list($x_offset, $y_offset, $new_width, $new_height) = resizeToA5($pdfFinal, $template_id, $page_width, $page_height, $forceResize);
                            
                            // Position en grille 2x2 pour le verso
                            $page_row = intval($j / 2);  // 0, 1 (2 rangées)
                            $page_col = $j % 2;          // 0, 1 (2 colonnes)
                            
                            $x = $page_col * $page_width + $x_offset;
                            $y = $page_row * $page_height + $y_offset;
                            
                            $pdfFinal->useTemplate($template_id, $x, $y, $new_width, $new_height);
                            
                            if ($previewMode) {
                                $template_id_preview = $template_ids_preview[$page_num];
                                $pdfPreview->useTemplate($template_id_preview, $x, $y, $new_width, $new_height);
                                // Ajouter le numéro de page en surbrillance
                                $pdfPreview->SetFont('helvetica', 'B', 20);
                                $pdfPreview->SetTextColor(255, 0, 0); // Rouge
                                $pdfPreview->SetFillColor(255, 255, 0); // Jaune
                                $pdfPreview->Rect($x + 2, $y + 2, 20, 15, 'F'); // Fond jaune plus grand
                                $pdfPreview->SetXY($x + 6, $y + 6);
                                $pdfPreview->Cell(15, 8, $page_num, 0, 0, 'C', false, '', 0, false, 'T', 'M');
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
                    $output_pdf_path_preview = $tmp_dir . 'imposition_preview_' . $timestamp . '.pdf';
                    $pdfPreview->Output($output_pdf_path_preview, 'F');
                    
                // Utiliser l'endpoint d'affichage pour la prévisualisation
                $array['preview_url'] = 'view_pdf.php?file=imposition_preview_' . $timestamp . '.pdf';
                }

                $output_pdf_path_final = $tmp_dir . 'imposition_final_' . $timestamp . '.pdf';
                $pdfFinal->Output($output_pdf_path_final, 'F');
                
                // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
                $array['download_url'] = 'download_pdf.php?file=imposition_final_' . $timestamp . '.pdf';
                
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