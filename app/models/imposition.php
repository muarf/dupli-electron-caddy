<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/utilities.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/CropMarks.php');
require_once(__DIR__ . '/../controler/functions/ImpositionProcessor.php');
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


function reordering_pages_a6($number_of_pages) {
    $total_pages = $number_of_pages;
    
    if ($total_pages <= 0) {
        throw new Exception("Le nombre de pages doit être strictement positif.");
    }
    
    // Tout est maintenant calculé avec les formules mathématiques
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
        // L'ordre des 4 dernières pages dépend de l'offset
        // Pour offset=0: 7, 10, 11, 6
        // Pour offset>0: l'ordre est inversé
        $verso_base = [
            $N/4 - 1 - $offset,                    // N/4-1,N/4-3,N/4-5,...
            $N - ($N/4 - 1) + 1 + $offset,        // N-(N/4-1)+1,N-(N/4-1)+3,...
            $N - 1 - $offset,                      // N-1,N-3,N-5,...
            1 + 1 + $offset                        // 2,4,6,8,...
        ];
        
        // Les 4 dernières pages : ordre uniforme pour tous les cas
        $verso_last4 = [
            $N/2 - 1 - $offset,                    // N/2-1
            ($N/2 + 1) + 1 + $offset,             // N/2+2
            ($N/4) * 3 - 1 - $offset,             // (N/4)*3-1
            ($N/4 + 1) + 1 + $offset              // N/4+2
        ];
        
        // Inverser pour tous les cas sauf N=16 (qui a déjà le bon ordre)
        if ($N != 16) {
            $verso_last4 = array_reverse($verso_last4);
        }
        
        $verso = array_merge($verso_base, $verso_last4);
        
        // Filtrer les pages qui dépassent le nombre total de pages et réindexer
        $recto = array_values(array_filter($recto, function($page) use ($total_pages) {
            return $page <= $total_pages && $page > 0;
        }));
        $verso = array_values(array_filter($verso, function($page) use ($total_pages) {
            return $page <= $total_pages && $page > 0;
        }));
        
        // Combiner recto + verso
        $sheet_seq = array_merge($recto, $verso);
        $result = array_merge($result, $sheet_seq);
    }
    
    return $result;
}

/**
 * Génére un PDF temporaire complété de pages blanches afin d'obtenir
 * un nombre total de pages multiple de $multiple.
 *
 * @param string $pdfFilePath Chemin du PDF source
 * @param int    $multiple    Multiple souhaité (8 pour A5, 16 pour A6)
 * @return array{file:string,page_count:int,temp_file:?string}
 * @throws Exception
 */
function padPdfToMultiple($pdfFilePath, $multiple) {
    $pdf = new TCPDI();
    $pageCount = $pdf->setSourceFile($pdfFilePath);

    if ($multiple <= 0) {
        throw new Exception("Le multiple doit être strictement positif.");
    }

    if ($pageCount % $multiple === 0) {
        return [
            'file' => $pdfFilePath,
            'page_count' => $pageCount,
            'temp_file' => null
        ];
    }

    $pagesToAdd = $multiple - ($pageCount % $multiple);

    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
    if (!file_exists($tmp_dir)) {
        mkdir($tmp_dir, 0755, true);
    }

    $outputPath = $tmp_dir . 'padded_' . date('YmdHis') . '_' . uniqid() . '.pdf';

    $pdfPadded = new TCPDI();
    $pdfPadded->setPrintHeader(false);
    $pdfPadded->setPrintFooter(false);
    $pdfPadded->setSourceFile($pdfFilePath);

    $defaultWidth = 210;
    $defaultHeight = 297;
    $defaultOrientation = 'P';

    for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
        $templateId = $pdfPadded->importPage($pageNum);
        $size = $pdfPadded->getTemplateSize($templateId);
        if ($size && isset($size['width']) && isset($size['height'])) {
            $width = $size['width'];
            $height = $size['height'];
            $defaultWidth = $width;
            $defaultHeight = $height;
            $defaultOrientation = ($width > $height) ? 'L' : 'P';
        } else {
            $width = $defaultWidth;
            $height = $defaultHeight;
        }

        $orientation = ($width > $height) ? 'L' : 'P';
        $pdfPadded->AddPage($orientation, [$width, $height]);
        $pdfPadded->useTemplate($templateId, 0, 0, $width, $height);
    }

    for ($i = 0; $i < $pagesToAdd; $i++) {
        $pdfPadded->AddPage($defaultOrientation, [$defaultWidth, $defaultHeight]);
    }

    $pdfPadded->Output($outputPath, 'F');

    return [
        'file' => $outputPath,
        'page_count' => $pageCount + $pagesToAdd,
        'temp_file' => $outputPath
    ];
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

/**
 * Ajoute les numéros de pages au PDF original dans le coin bas-gauche
 * Texte petit, noir, sans fond, proche des bords mais dans la zone imprimable
 * 
 * @param string $pdfFilePath Chemin vers le fichier PDF source
 * @return string|null Chemin vers le PDF modifié ou null en cas d'erreur
 */
function addPageNumbersToPdf($pdfFilePath) {
    try {
        // Créer un nouveau PDF pour le résultat
        $pdf = new TCPDI();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setAutoPageBreak(false); // Désactiver l'ajout automatique de pages
        
        // Ouvrir le PDF source
        $pageCount = $pdf->setSourceFile($pdfFilePath);
        
        if ($pageCount <= 0) {
            throw new Exception("Impossible de lire le PDF ou PDF vide.");
        }
        
        // Parcourir toutes les pages
        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            try {
                // Importer la page (toutes les pages y compris celles avec rotation)
                $templateId = $pdf->importPage($pageNum);
                
                // Obtenir les dimensions de la page originale
                $size = $pdf->getTemplateSize($templateId);
                if (!$size || !isset($size['width']) || !isset($size['height'])) {
                    error_log("Page $pageNum : dimensions invalides");
                    continue; // Passer à la page suivante
                }
                
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];
                
                // Déterminer l'orientation
                $orientation = ($pageWidth > $pageHeight) ? 'L' : 'P';
                
                // Créer une nouvelle page avec les mêmes dimensions que l'originale
                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                
                // Utiliser le template - placer la page originale sur la nouvelle page
                // Les paramètres sont : templateId, x, y, width, height
                $pdf->useTemplate($templateId, 0, 0, $pageWidth, $pageHeight);
                
                // Position: bas à gauche, proche des bords mais dans la zone imprimable
                // Position en bas à gauche avec un petit offset (3mm) pour être proche mais imprimable
                // Décaler encore plus vers l'extérieur (≈1mm du bord gauche, 6mm du bas)
                $x = max(1, 3 - 2); // 3mm - 2mm = 1mm
                $y = $pageHeight - 6; // 6mm depuis le bas
                
                // Configurer la police : petit, noir
                $pdf->SetFont('helvetica', '', 8); // Taille 8 = petit
                $pdf->SetTextColor(0, 0, 0); // Noir
                
                // Ajouter le numéro de page
                $pdf->SetXY($x, $y);
                $pdf->Cell(10, 5, (string)$pageNum, 0, 0, 'L', false, '', 0, false, 'T', 'M');
                
            } catch (Exception $pageException) {
                error_log("Erreur lors du traitement de la page $pageNum : " . $pageException->getMessage());
                // Continuer avec les autres pages même si une page échoue
                continue;
            }
        }
        
        // Vérifier qu'au moins une page a été créée
        if ($pdf->getNumPages() == 0) {
            throw new Exception("Aucune page n'a pu être créée dans le PDF résultat.");
        }
        
        // Sauvegarder le PDF modifié dans un fichier temporaire
        $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
        if (!file_exists($tmp_dir)) {
            mkdir($tmp_dir, 0755, true);
        }
        
        $timestamp = date('YmdHis');
        $outputPath = $tmp_dir . 'numbered_' . $timestamp . '_' . uniqid() . '.pdf';
        $pdf->Output($outputPath, 'F');
        
        return $outputPath;
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'ajout des numéros de pages: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return null;
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

    if (isset($GLOBALS['last_error']) && is_array($GLOBALS['last_error'])) {
        $lastError = $GLOBALS['last_error'];
        $errorSummary = $lastError['type'] . ' : ' . $lastError['message'];
        if (!empty($lastError['file'])) {
            $errorSummary .= ' (fichier ' . $lastError['file'];
            if (!empty($lastError['line'])) {
                $errorSummary .= ', ligne ' . $lastError['line'];
            }
            $errorSummary .= ')';
        }
        $array['errors'][] = $errorSummary;
        $array['last_error_details'] = $lastError;
    }

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
        $paddedPdfFile = null;
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
            $requiredMultiple = ($imposition_type === 'a6') ? 16 : 8;

            // Ajouter des pages blanches si nécessaire avant toute autre transformation
            $paddingResult = padPdfToMultiple($pdfFile, $requiredMultiple);
            if ($paddingResult['temp_file'] !== null) {
                $paddedPdfFile = $paddingResult['temp_file'];
            }
            $pdfFile = $paddingResult['file'];
            $pageCount = $paddingResult['page_count'];
            $array['page_count'] = $pageCount;
            $pdf = new TCPDI();
            $pdf->setSourceFile($pdfFile);

            // Optionnel: Ajouter les numéros de pages une fois le PDF complété
            $add_page_numbers = isset($_POST['add_page_numbers']) && $_POST['add_page_numbers'] == '1';
            $numberedPdfFile = null;
            
            // Récupérer les options des traits de coupe
            $add_crop_marks = isset($_POST['add_crop_marks']);
            $crop_marks_type = isset($_POST['crop_marks_type']) ? $_POST['crop_marks_type'] : 'normal';
            $imposition_mode = isset($_POST['imposition_mode']) ? $_POST['imposition_mode'] : 'brochure';
            $bleed_mode = isset($_POST['bleed_mode']) ? $_POST['bleed_mode'] : 'fullsize';
            $bleed_size = isset($_POST['bleed_size']) ? floatval($_POST['bleed_size']) : 3;
            $render_trim_numbers = $add_page_numbers && $add_crop_marks;
            
            if ($add_page_numbers && !$render_trim_numbers) {
                $numberedPdfPath = addPageNumbersToPdf($pdfFile);
                if ($numberedPdfPath !== null && file_exists($numberedPdfPath)) {
                    if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                        @unlink($paddedPdfFile);
                    }
                    $paddedPdfFile = null;
                    $numberedPdfFile = $numberedPdfPath;
                    $pdfFile = $numberedPdfPath;
                    $pdf = new TCPDI();
                    $pageCount = $pdf->setSourceFile($pdfFile);
                    $array['page_count'] = $pageCount;
                } else {
                    $array['errors'][] = "Erreur lors de l'ajout des numéros de pages. Le PDF complété sera utilisé sans numérotation.";
                }
            }
            
            
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
                ImpositionProcessor::processA6Imposition(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
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
                );
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
                
                // Traitement de l'imposition A5
                ImpositionProcessor::processA5Imposition(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
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
                );
            }
            
            // Sauvegarde des fichiers résultants
            $timestamp = date('YmdHis');
            // Utiliser un répertoire temporaire système pour Ghostscript
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;
            
            // Nettoyer le nom de fichier pour éviter les problèmes
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);

            // Sauvegarder le PDF final
            $final_filename = $safe_filename . '_imposed.pdf';
            $output_pdf_path_final = $tmp_dir . $final_filename;
            $pdfFinal->Output($output_pdf_path_final, 'F');
            
            // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
            $array['download_url'] = '?download_pdf&file=' . $final_filename;
            
            if ($previewMode) {
                
                // Sauvegarder le preview (créé en même temps que le final)
                $preview_filename = $safe_filename . '_preview.pdf';
                $output_pdf_path_preview = $tmp_dir . $preview_filename;
                $pdfPreview->Output($output_pdf_path_preview, 'F');
                
                
                // Utiliser l'endpoint d'affichage pour la prévisualisation avec timestamp pour éviter le cache
                $array['preview_url'] = '?view_pdf&file=' . $preview_filename . '&t=' . time();
            }
            
            $array['success'] = true;
            $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages.";
            
            // Nettoyer le fichier temporaire avec numéros si créé
            if (isset($numberedPdfFile) && $numberedPdfFile !== null && file_exists($numberedPdfFile)) {
                @unlink($numberedPdfFile);
            }
            
            if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                @unlink($paddedPdfFile);
            }
            
            // Marquer que le traitement principal a réussi pour éviter le fallback
            $mainProcessingSuccess = true;
            error_log("DEBUG: Traitement principal réussi, flag mainProcessingSuccess = true");
            
        } catch (Exception $e) {
            // Première tentative échouée, essayer avec Ghostscript
            // Mais seulement si le traitement principal n'a pas réussi
            error_log("DEBUG: EXCEPTION CAPTURÉE - Dans le catch, mainProcessingSuccess = " . ($mainProcessingSuccess ? 'true' : 'false'));
            if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                @unlink($paddedPdfFile);
                $paddedPdfFile = null;
            }
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
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;
                
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
                
                // Récupérer le type d'imposition et compléter le PDF si besoin
                $imposition_type = isset($_POST['imposition_type']) ? $_POST['imposition_type'] : 'a5';
                $requiredMultiple = ($imposition_type === 'a6') ? 16 : 8;

                $paddingResult = padPdfToMultiple($pdfFile, $requiredMultiple);
                if ($paddingResult['temp_file'] !== null) {
                    $paddedPdfFile = $paddingResult['temp_file'];
                } else {
                    $paddedPdfFile = null;
                }
                $pdfFile = $paddingResult['file'];
                $pageCount = $paddingResult['page_count'];
                $array['page_count'] = $pageCount;
                $pdf = new TCPDI();
                $pdf->setSourceFile($pdfFile);

                // Optionnel: Ajouter les numéros de pages après padding
                $add_page_numbers = isset($_POST['add_page_numbers']) && $_POST['add_page_numbers'] == '1';
                $numberedPdfFile = null;
                
                if ($add_page_numbers) {
                    $numberedPdfPath = addPageNumbersToPdf($pdfFile);
                    if ($numberedPdfPath !== null && file_exists($numberedPdfPath)) {
                        if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                            @unlink($paddedPdfFile);
                        }
                        $paddedPdfFile = null;
                        // Utiliser le PDF avec numéros au lieu du PDF nettoyé
                        $numberedPdfFile = $numberedPdfPath;
                        $pdfFile = $numberedPdfPath;
                        // Réinitialiser avec le nouveau fichier
                        $pdf = new TCPDI();
                        $pageCount = $pdf->setSourceFile($pdfFile);
                        $array['page_count'] = $pageCount;
                    } else {
                        $array['errors'][] = "Erreur lors de l'ajout des numéros de pages. Le PDF complété sera utilisé sans numérotation.";
                    }
                }
                
                // Récupérer le nom du fichier original pour le nom de sortie
                $originalFileName = isset($_FILES["pdf"]["name"]) ? $_FILES["pdf"]["name"] : "document.pdf";
                $originalFileNameWithoutExt = pathinfo($originalFileName, PATHINFO_FILENAME);
                $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFileNameWithoutExt);

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
                    ImpositionProcessor::processA6Imposition(
                        $pdfFinal,
                        $pdfPreview,
                        $template_ids_preview,
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
                    );
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
                
                // Traitement de l'imposition A5
                ImpositionProcessor::processA5Imposition(
                    $pdfFinal,
                    $pdfPreview,
                    $template_ids_preview,
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
                );
                }
                
                // Sauvegarde des fichiers résultants
                $timestamp = date('YmdHis');
            // Utiliser le répertoire temporaire système cross-platform
    $tmp_dir = resolveTempDir() . DIRECTORY_SEPARATOR;

                if ($previewMode) {
                    $preview_filename = $safe_filename . '_preview.pdf';
                    $output_pdf_path_preview = $tmp_dir . $preview_filename;
                    $pdfPreview->Output($output_pdf_path_preview, 'F');
                    
                    // Utiliser l'endpoint d'affichage pour la prévisualisation avec timestamp pour éviter le cache
                    $array['preview_url'] = '?view_pdf&file=' . $preview_filename . '&t=' . time();
                }

                // Utiliser le nom du fichier original avec suffixe
                $final_filename = $safe_filename . '_imposed.pdf';
                $output_pdf_path_final = $tmp_dir . $final_filename;
                $pdfFinal->Output($output_pdf_path_final, 'F');
                
                // Utiliser l'endpoint de téléchargement pour les fichiers temporaires
                $array['download_url'] = '?download_pdf&file=' . $final_filename;
                
                $array['success'] = true;
                $array['result'] = "PDF imposé généré avec succès ! Le PDF contient $pageCount pages. (Nettoyé avec Ghostscript)";
                
                // Nettoyer le fichier temporaire avec numéros si créé
                if (isset($numberedPdfFile) && $numberedPdfFile !== null && file_exists($numberedPdfFile)) {
                    @unlink($numberedPdfFile);
                }
                
                if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                    @unlink($paddedPdfFile);
                }
                
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
                
                // Nettoyer le fichier temporaire avec numéros si créé
                if (isset($numberedPdfFile) && $numberedPdfFile !== null && file_exists($numberedPdfFile)) {
                    @unlink($numberedPdfFile);
                }
                
                if ($paddedPdfFile !== null && file_exists($paddedPdfFile)) {
                    @unlink($paddedPdfFile);
                }
                
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