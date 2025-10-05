<?php
require_once(__DIR__ . '/../controler/functions/utilities.php');
require_once(__DIR__ . '/../vendor/autoload.php');
use setasign\Fpdi\TcpdfFpdi as TCPDI;

function Action($conf = null)
{
    $array = array();
    
    // Gestion AJAX pour l'analyse du PDF
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'analyze_pdf' && isset($_FILES['pdf_file'])) {
        try {
            $result = analyzePDFFormat($_FILES['pdf_file']);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Traitement du formulaire
    if (isset($_POST['pdf_file']) && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        try {
            $array = processImpositionTracts();
        } catch (Exception $e) {
            $array['errors'] = ['Erreur lors du traitement : ' . $e->getMessage()];
        }
    }
    
    return template(__DIR__ . "/../view/imposition_tracts.html.php", $array);
}

function analyzePDFFormat($pdfFile)
{
    try {
        // Vérifier que le fichier est bien un PDF
        if ($pdfFile['type'] !== 'application/pdf') {
            throw new Exception('Le fichier doit être un PDF');
        }
        
        $originalFile = $pdfFile['tmp_name'];
        $cleanedPdfFile = null;
        $usedGhostscript = false;
        
        // Créer une instance de FPDI
        $pdf = new TCPDI();
        
        try {
            // Essayer de lire le fichier PDF directement
            $pageCount = $pdf->setSourceFile($originalFile);
            
            if ($pageCount === 0) {
                throw new Exception('PDF vide ou illisible');
            }
            
        } catch (Exception $e) {
            // Si TCPDF échoue, essayer de nettoyer avec Ghostscript
            $timestamp = date('YmdHis');
            $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
            
            if (!file_exists($tmp_dir)) {
                mkdir($tmp_dir, 0755, true);
            }
            
            $cleanedPdfFile = $tmp_dir . 'cleaned_tracts_' . $timestamp . '.pdf';
            
            // Nettoyer le PDF avec Ghostscript - détection automatique de la plateforme
            if (PHP_OS_FAMILY === 'Windows') {
                $gs_command = __DIR__ . '/../../ghostscript/gswin64c.exe';
                if (!file_exists($gs_command)) {
                    throw new Exception("Ghostscript Windows non trouvé : " . $gs_command);
                }
            } else {
                $gs_command = 'gs';
            }
            
            $cmd = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($originalFile) . " 2>&1";
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0 || !file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
                throw new Exception("Impossible de nettoyer le PDF avec Ghostscript. Erreur: " . implode("\n", $output));
            }
            
            // Réessayer avec le PDF nettoyé
            $pdf = new TCPDI();
            $pageCount = $pdf->setSourceFile($cleanedPdfFile);
            
            if ($pageCount === 0) {
                throw new Exception('Impossible de lire le PDF même après nettoyage Ghostscript');
            }
            
            $usedGhostscript = true;
            $originalFile = $cleanedPdfFile; // Utiliser le fichier nettoyé
        }
        
        // Analyser la première page pour déterminer le format
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);
        
        // Dimensions en points (72 points = 1 pouce)
        $widthPt = $size['width'];
        $heightPt = $size['height'];
        
        // Convertir en mm (1 point = 0.352778 mm, mais TCPDF utilise déjà mm)
        // Les dimensions sont déjà en mm dans TCPDF
        $widthMm = $widthPt;
        $heightMm = $heightPt;
        
        // Arrondir et convertir en int pour la comparaison
        $widthMm = (int)round($widthMm);
        $heightMm = (int)round($heightMm);
        
        // Déterminer le format
        $format = determineFormat($widthMm, $heightMm);
        
        $result = [
            'success' => true,
            'format' => $format,
            'page_count' => $pageCount,
            'dimensions' => [
                'width' => $widthMm,
                'height' => $heightMm
            ],
            'is_portrait' => $heightMm > $widthMm,
            'ghostscript_used' => $usedGhostscript
        ];
        
        // Nettoyer le fichier temporaire Ghostscript s'il existe
        if ($usedGhostscript && $cleanedPdfFile && file_exists($cleanedPdfFile)) {
            unlink($cleanedPdfFile);
        }
        
        return $result;
        
    } catch (Exception $e) {
        // Nettoyer le fichier temporaire Ghostscript en cas d'erreur
        if (isset($cleanedPdfFile) && $cleanedPdfFile && file_exists($cleanedPdfFile)) {
            unlink($cleanedPdfFile);
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function determineFormat($widthMm, $heightMm)
{
    // Formats A4, A5, A6 (en mm)
    $formats = [
        'A4' => [210, 297],
        'A5' => [148, 210],
        'A6' => [105, 148]
    ];
    
    // Vérifier chaque format (portrait et paysage)
    foreach ($formats as $format => $dimensions) {
        if (($widthMm === $dimensions[0] && $heightMm === $dimensions[1]) ||
            ($widthMm === $dimensions[1] && $heightMm === $dimensions[0])) {
            return $format;
        }
    }
    
    // Si aucun format standard n'est détecté
    return 'unknown';
}

function processImpositionTracts()
{
    $array = array();
    
    // Vérifier l'upload
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors de l'upload du fichier.");
    }
    
    $uploadedFile = $_FILES['pdf_file'];
    $originalName = $uploadedFile['name'];
    $tempFile = $uploadedFile['tmp_name'];
    
    // Vérifier le type de fichier
    if (!preg_match('/\.pdf$/i', $originalName)) {
        throw new Exception("Le fichier doit être un PDF.");
    }
    
    // Créer un nom de fichier unique
    $uniqueId = uniqid();
    $inputFile = sys_get_temp_dir() . '/tract_input_' . $uniqueId . '.pdf';
    $outputFile = sys_get_temp_dir() . '/tract_output_' . $uniqueId . '.pdf';
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($tempFile, $inputFile)) {
        throw new Exception("Impossible de déplacer le fichier uploadé.");
    }
    
    try {
        // Analyser le PDF avec la fonction existante
        $pdfFileArray = [
            'tmp_name' => $inputFile,
            'type' => 'application/pdf'
        ];
        $analysisResult = analyzePDFFormat($pdfFileArray);
        
        if (!$analysisResult['success']) {
            throw new Exception($analysisResult['error']);
        }
        
        $pdfInfo = [
            'format' => $analysisResult['format'],
            'page_count' => $analysisResult['page_count'],
            'dimensions' => $analysisResult['dimensions'],
            'ghostscript_used' => $analysisResult['ghostscript_used'] ?? false
        ];
        $array['pdf_info'] = $pdfInfo;
        
        // Récupérer les options d'imposition
        $manualFormat = $_POST['manual_format'] ?? 'auto';
        $forceResize = isset($_POST['force_resize']) && $_POST['force_resize'] == '1';
        $cutMargin = intval($_POST['cut_margin'] ?? 2);
        
        // Appliquer le format manuel si spécifié
        if ($manualFormat !== 'auto') {
            $pdfInfo['format'] = $manualFormat;
            $pdfInfo['forced_format'] = true;
        }
        
        // Déterminer les paramètres d'imposition automatiquement
        $impositionParams = determineAutomaticParams($pdfInfo);
        
        // Ajouter les options de redimensionnement
        $impositionParams['force_resize'] = $forceResize;
        $impositionParams['manual_format'] = $manualFormat;
        
        // Traiter l'imposition
        $resultFile = performImposition($inputFile, $impositionParams, $cutMargin);
        
        // Utiliser le répertoire temporaire système comme impose/unimpose
        $timestamp = date('YmdHis');
        $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
        
        if (!file_exists($tmp_dir)) {
            mkdir($tmp_dir, 0755, true);
        }
        
        $finalFileName = 'tracts_final_' . $timestamp . '.pdf';
        $finalFilePath = $tmp_dir . $finalFileName;
        
        if (!copy($resultFile, $finalFilePath)) {
            throw new Exception("Impossible de sauvegarder le fichier final.");
        }
        
        // Nettoyer les fichiers temporaires
        unlink($inputFile);
        if (file_exists($resultFile)) unlink($resultFile);
        
        // Utiliser le même système de téléchargement que impose/unimpose
        $array['download_url'] = 'download_pdf.php?file=' . $finalFileName;
        $array['success'] = true;
        $array['result'] = "PDF imposé généré avec succès ! Le PDF contient {$pdfInfo['page_count']} page(s).";
        
        if ($pdfInfo['ghostscript_used']) {
            $array['result'] .= " (Nettoyé avec Ghostscript)";
        }
        
    } catch (Exception $e) {
        // Nettoyer en cas d'erreur
        if (file_exists($inputFile)) unlink($inputFile);
        if (file_exists($outputFile)) unlink($outputFile);
        throw $e;
    }
    
    return $array;
}

function determineAutomaticParams($pdfInfo)
{
    $format = $pdfInfo['format'];
    $pageCount = $pdfInfo['page_count'];
    
    // Déterminer le nombre de copies selon le format
    $copiesPerSheet = 2; // Par défaut
    
    switch ($format) {
        case 'A4':
            $copiesPerSheet = 2; // 2 copies A4 sur A3
            break;
        case 'A5':
            $copiesPerSheet = 4; // 4 copies A5 sur A3
            break;
        case 'A6':
            $copiesPerSheet = 8; // 8 copies A6 sur A3
            break;
    }
    
    return [
        'copies_per_sheet' => $copiesPerSheet,
        'paper_format' => 'A3',
        'page_count' => $pageCount,
        'format' => $format
    ];
}

function performImposition($inputFile, $params, $cutMargin = 2)
{
    try {
        // Créer une instance de FPDI
        $pdf = new TCPDI();
        $pdf->setSourceFile($inputFile);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pageCount = $params['page_count'];
        $copiesPerSheet = $params['copies_per_sheet'];
        $format = $params['format'];
        
        // Dimensions A3
        $a3_width = 420;  // Largeur A3 en paysage (mm)
        $a3_height = 297; // Hauteur A3 en paysage (mm)
        
        // Dimensions des pages selon le format
        $page_width = 210;  // A4
        $page_height = 297;
        
        switch ($format) {
            case 'A5':
                $page_width = 148;
                $page_height = 210;
                break;
            case 'A6':
                $page_width = 105;
                $page_height = 148;
                break;
        }
        
        // Calculer la disposition sur A3
        if ($copiesPerSheet == 2) {
            // 2 copies (A4 sur A3)
            $cols = 2;
            $rows = 1;
        } elseif ($copiesPerSheet == 4) {
            // 4 copies (A5 sur A3)
            $cols = 2;
            $rows = 2;
        } elseif ($copiesPerSheet == 8) {
            // 8 copies (A6 sur A3)
            $cols = 4;
            $rows = 2;
        }
        
        // Espacement entre les copies
        $spacingX = ($a3_width - ($cols * $page_width)) / ($cols + 1);
        $spacingY = ($a3_height - ($rows * $page_height)) / ($rows + 1);
        
        // Créer le PDF final
        $pdfFinal = new TCPDI();
        $pdfFinal->setPrintHeader(false);
        $pdfFinal->setPrintFooter(false);
        
        $currentSheet = 0;
        $pagesProcessed = 0;
        
        while ($pagesProcessed < $pageCount) {
            // Nouvelle feuille A3
            $pdfFinal->AddPage('L', array($a3_width, $a3_height)); // Paysage
            $currentSheet++;
            
            // Placer les copies sur cette feuille
            $copiesPlaced = 0;
            for ($row = 0; $row < $rows && $copiesPlaced < $copiesPerSheet; $row++) {
                for ($col = 0; $col < $cols && $copiesPlaced < $copiesPerSheet; $col++) {
                    if ($pagesProcessed >= $pageCount) break;
                    
                    // Calculer la position
                    $x = $spacingX + $col * ($page_width + $spacingX);
                    $y = $spacingY + $row * ($page_height + $spacingY);
                    
                    // Importer et placer la page
                    $pageNum = ($pagesProcessed % $pageCount) + 1;
                    $templateId = $pdf->importPage($pageNum);
                    $pdfFinal->useTemplate($templateId, $x, $y, $page_width, $page_height);
                    
                    $copiesPlaced++;
                    
                    // Pour les PDF recto/verso (2 pages), placer aussi la page verso
                    if ($pageCount == 2 && $pagesProcessed == 0) {
                        // C'est un PDF recto/verso, placer la page 2 (verso) sur la même feuille
                        if ($copiesPlaced < $copiesPerSheet) {
                            $col++;
                            if ($col >= $cols) {
                                $col = 0;
                                $row++;
                                if ($row >= $rows) break;
                            }
                            
                            $x = $spacingX + $col * ($page_width + $spacingX);
                            $y = $spacingY + $row * ($page_height + $spacingY);
                            
                            $templateId2 = $pdf->importPage(2);
                            $pdfFinal->useTemplate($templateId2, $x, $y, $page_width, $page_height);
                            
                            $copiesPlaced++;
                        }
                    }
                    
                    $pagesProcessed++;
                }
            }
        }
        
        // Sauvegarder le fichier temporaire
        $tempFile = sys_get_temp_dir() . '/tracts_temp_' . uniqid() . '.pdf';
        $pdfFinal->Output($tempFile, 'F');
        
        return $tempFile;
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de l'imposition : " . $e->getMessage());
    }
}

function analyzePDF($pdfFile)
{
    // Utiliser pdfinfo pour analyser le PDF
    $cmd = "pdfinfo " . escapeshellarg($pdfFile) . " 2>/dev/null";
    exec($cmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        // Si pdfinfo échoue, essayer avec Ghostscript pour convertir
        $convertedFile = preg_replace('/\.pdf$/', '_converted.pdf', $pdfFile);
        $gsCmd = "gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=" . escapeshellarg($convertedFile) . " " . escapeshellarg($pdfFile) . " 2>/dev/null";
        exec($gsCmd, $gsOutput, $gsReturnCode);
        
        if ($gsReturnCode === 0 && file_exists($convertedFile)) {
            // Réessayer avec le fichier converti
            $cmd = "pdfinfo " . escapeshellarg($convertedFile) . " 2>/dev/null";
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0) {
                // Remplacer le fichier original par le fichier converti
                unlink($pdfFile);
                rename($convertedFile, $pdfFile);
            } else {
                if (file_exists($convertedFile)) unlink($convertedFile);
                throw new Exception("Impossible d'analyser le PDF même après conversion Ghostscript.");
            }
        } else {
            if (file_exists($convertedFile)) unlink($convertedFile);
            throw new Exception("Impossible d'analyser le PDF et conversion Ghostscript échouée.");
        }
    }
    
    // Parser les informations
    $info = [
        'page_count' => 0,
        'format' => 'A4', // Par défaut
        'width' => 210,
        'height' => 297
    ];
    
    foreach ($output as $line) {
        if (preg_match('/^Pages:\s+(\d+)/', $line, $matches)) {
            $info['page_count'] = (int)$matches[1];
        } elseif (preg_match('/^Page size:\s+([\d.]+)\s+x\s+([\d.]+)\s+pts/', $line, $matches)) {
            $width = floatval($matches[1]) * 0.352778; // Convertir pts en mm
            $height = floatval($matches[2]) * 0.352778;
            
            $info['width'] = $width;
            $info['height'] = $height;
            
            // Déterminer le format
            if (abs($width - 210) < 5 && abs($height - 297) < 5) {
                $info['format'] = 'A4';
            } elseif (abs($width - 148) < 5 && abs($height - 210) < 5) {
                $info['format'] = 'A5';
            } elseif (abs($width - 105) < 5 && abs($height - 148) < 5) {
                $info['format'] = 'A6';
            } else {
                // Déterminer le format le plus proche
                $a4Diff = abs($width - 210) + abs($height - 297);
                $a5Diff = abs($width - 148) + abs($height - 210);
                $a6Diff = abs($width - 105) + abs($height - 148);
                
                if ($a4Diff <= $a5Diff && $a4Diff <= $a6Diff) {
                    $info['format'] = 'A4';
                } elseif ($a5Diff <= $a6Diff) {
                    $info['format'] = 'A5';
                } else {
                    $info['format'] = 'A6';
                }
            }
        }
    }
    
    if ($info['page_count'] === 0) {
        throw new Exception("Impossible de déterminer le nombre de pages du PDF.");
    }
    
    return $info;
}


function performImposition($inputFile, $outputFile, $params, $cutMargin)
{
    $copiesPerSheet = $params['copies_per_sheet'];
    $format = $params['paper_format'];
    $pageCount = $params['page_count'];
    
    // Créer le PDF final
    $pdf = new TCPDI('P', 'mm', 'A3');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Dimensions A3
    $a3Width = 297;
    $a3Height = 420;
    
    // Dimensions selon le format
    $pageDimensions = getPageDimensions($format);
    $pageWidth = $pageDimensions['width'];
    $pageHeight = $pageDimensions['height'];
    
    // Calculer le nombre de colonnes et lignes
    $cols = getColumnsForFormat($copiesPerSheet);
    $rows = ceil($copiesPerSheet / $cols);
    
    // Calculer les marges et espacements
    $margin = 10; // Marge générale
    $cutMargin_mm = $cutMargin; // Marge de coupe
    
    $availableWidth = $a3Width - (2 * $margin);
    $availableHeight = $a3Height - (2 * $margin);
    
    $spacingX = ($availableWidth - ($cols * $pageWidth)) / ($cols - 1);
    $spacingY = ($availableHeight - ($rows * $pageHeight)) / ($rows - 1);
    
    // Importer les pages du PDF original
    $templateIds = [];
    for ($i = 1; $i <= $pageCount; $i++) {
        try {
            $templateIds[$i] = $pdf->importPage($i);
        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'import de la page $i : " . $e->getMessage());
        }
    }
    
    // Redimensionner si nécessaire
    if ($params['force_resize'] && isset($params['manual_format']) && $params['manual_format'] !== 'auto') {
        $targetDimensions = getPageDimensions($params['manual_format']);
        $originalDimensions = getPageDimensions($format);
        
        // Calculer le facteur de redimensionnement
        $scaleX = $targetDimensions['width'] / $originalDimensions['width'];
        $scaleY = $targetDimensions['height'] / $originalDimensions['height'];
        $scale = min($scaleX, $scaleY); // Garder les proportions
        
        // Mettre à jour les dimensions de page
        $pageWidth = $targetDimensions['width'];
        $pageHeight = $targetDimensions['height'];
        
        // Recalculer les espacements
        $spacingX = ($availableWidth - ($cols * $pageWidth)) / ($cols - 1);
        $spacingY = ($availableHeight - ($rows * $pageHeight)) / ($rows - 1);
    }
    
    // Créer les feuilles A3
    $currentPage = 0;
    $currentSheet = 0;
    
    foreach ($templateIds as $pageNum => $templateId) {
        // Ajouter une nouvelle page A3 si nécessaire
        if ($currentPage === 0) {
            $pdf->AddPage('P', 'A3');
            $currentSheet++;
        }
        
        // Calculer la position pour chaque copie de cette page
        for ($copy = 0; $copy < $copiesPerSheet; $copy++) {
            if ($currentPage >= $copiesPerSheet) {
                // Nouvelle feuille A3
                $pdf->AddPage('P', 'A3');
                $currentSheet++;
                $currentPage = 0;
            }
            
            // Calculer la position (col, row)
            $col = $currentPage % $cols;
            $row = floor($currentPage / $cols);
            
            // Calculer les coordonnées
            $x = $margin + ($col * ($pageWidth + $spacingX));
            $y = $margin + ($row * ($pageHeight + $spacingY));
            
            // Ajouter la page à la position calculée
            $pdf->useTemplate($templateId, $x, $y, $pageWidth, $pageHeight);
            
            // Ajouter les marques de coupe si demandées
            if ($cutMargin_mm > 0) {
                addCutMarks($pdf, $x, $y, $pageWidth, $pageHeight, $cutMargin_mm);
            }
            
            $currentPage++;
        }
    }
    
    // Sauvegarder le PDF
    $pdf->Output($outputFile, 'F');
    
    return $outputFile;
}

function getPageDimensions($format)
{
    switch ($format) {
        case 'A4':
            return ['width' => 210, 'height' => 297];
        case 'A5':
            return ['width' => 148, 'height' => 210];
        case 'A6':
            return ['width' => 105, 'height' => 148];
        default:
            return ['width' => 210, 'height' => 297]; // A4 par défaut
    }
}

function getColumnsForFormat($copiesPerSheet)
{
    switch ($copiesPerSheet) {
        case 2:
            return 2; // 2x1
        case 4:
            return 2; // 2x2
        case 8:
            return 4; // 4x2
        default:
            return 2;
    }
}

function addCutMarks($pdf, $x, $y, $width, $height, $margin)
{
    $lineWidth = 0.1;
    $markLength = 5;
    
    $pdf->SetLineWidth($lineWidth);
    $pdf->SetDrawColor(0, 0, 0);
    
    // Marques de coupe aux coins
    // Coin supérieur gauche
    $pdf->Line($x - $margin, $y - $margin, $x - $margin, $y - $margin + $markLength);
    $pdf->Line($x - $margin, $y - $margin, $x - $margin + $markLength, $y - $margin);
    
    // Coin supérieur droit
    $pdf->Line($x + $width + $margin, $y - $margin, $x + $width + $margin, $y - $margin + $markLength);
    $pdf->Line($x + $width + $margin - $markLength, $y - $margin, $x + $width + $margin, $y - $margin);
    
    // Coin inférieur gauche
    $pdf->Line($x - $margin, $y + $height + $margin - $markLength, $x - $margin, $y + $height + $margin);
    $pdf->Line($x - $margin, $y + $height + $margin, $x - $margin + $markLength, $y + $height + $margin);
    
    // Coin inférieur droit
    $pdf->Line($x + $width + $margin, $y + $height + $margin - $markLength, $x + $width + $margin, $y + $height + $margin);
    $pdf->Line($x + $width + $margin - $markLength, $y + $height + $margin, $x + $width + $margin, $y + $height + $margin);
}
?>
