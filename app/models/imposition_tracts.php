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
    
    // Traitement du formulaire d'imposition
    if (isset($_POST['submit']) && isset($_FILES['pdf_file'])) {
        try {
            $array = processImpositionTracts();
        } catch (Exception $e) {
            $array['error'] = $e->getMessage();
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
        
        // TCPDF retourne déjà les dimensions en mm
        $widthMm = (int)round($widthPt);
        $heightMm = (int)round($heightPt);
        
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
    
    // Vérifier qu'un fichier a été uploadé
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erreur lors de l'upload du fichier PDF.");
    }
    
    // Créer un nom de fichier unique
    $uniqueId = uniqid();
    $originalName = $_FILES['pdf_file']['name'];
    $tempFile = $_FILES['pdf_file']['tmp_name'];
    $inputFile = sys_get_temp_dir() . '/tracts_input_' . $uniqueId . '.pdf';
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($tempFile, $inputFile)) {
        throw new Exception("Impossible de déplacer le fichier uploadé.");
    }
    
    // Vérifier les permissions du fichier déplacé
    if (!file_exists($inputFile)) {
        throw new Exception("Le fichier déplacé n'existe pas.");
    }
    
    if (!is_readable($inputFile)) {
        throw new Exception("Le fichier déplacé n'est pas lisible.");
    }
    
    // S'assurer que le fichier a les bonnes permissions
    chmod($inputFile, 0644);
    
    try {
        // NETTOYER LE PDF AVEC GHOSTSCRIPT FORCÉ (comme unimpose et impose)
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
        
        $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -sOutputFile=" . escapeshellarg($cleanedPdfFile) . " " . escapeshellarg($inputFile) . " 2>&1";
        $output = shell_exec($command);
        
        if (!file_exists($cleanedPdfFile) || filesize($cleanedPdfFile) == 0) {
            throw new Exception("Échec du nettoyage Ghostscript. Sortie: " . $output);
        }
        
        // Utiliser le fichier nettoyé pour l'analyse
        $pdfFileArray = [
            'tmp_name' => $cleanedPdfFile,
            'type' => 'application/pdf'
        ];
        
        // Créer une instance de FPDI pour analyser
        $pdf = new TCPDI();
        $pageCount = $pdf->setSourceFile($cleanedPdfFile);
        
        if ($pageCount === 0) {
            throw new Exception('Impossible de lire le PDF même après nettoyage Ghostscript');
        }
        
        // Analyser la première page pour déterminer le format
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);
        
        $widthMm = (int)round($size['width']);
        $heightMm = (int)round($size['height']);
        
        // Déterminer le format
        $format = determineFormat($widthMm, $heightMm);
        
        $pdfInfo = [
            'format' => $format,
            'page_count' => $pageCount,
            'dimensions' => [
                'width' => $widthMm,
                'height' => $heightMm
            ],
            'ghostscript_used' => true
        ];
        $array['pdf_info'] = $pdfInfo;
        
        // Remplacer le fichier original par le fichier nettoyé
        unlink($inputFile);
        $inputFile = $cleanedPdfFile;
        
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
            if (!mkdir($tmp_dir, 0755, true)) {
                throw new Exception("Impossible de créer le répertoire temporaire: $tmp_dir");
            }
        }
        
        if (!is_writable($tmp_dir)) {
            throw new Exception("Le répertoire temporaire n'est pas accessible en écriture: $tmp_dir");
        }
        
        $finalFileName = 'tracts_final_' . $timestamp . '.pdf';
        $finalFilePath = $tmp_dir . $finalFileName;
        
        if (!copy($resultFile, $finalFilePath)) {
            throw new Exception("Impossible de sauvegarder le fichier final.");
        }
        
        // Nettoyer les fichiers temporaires
        unlink($inputFile);
        if (file_exists($resultFile)) unlink($resultFile);
        
        // Utiliser le même système de téléchargement et prévisualisation que impose/unimpose
        $array['download_url'] = 'download_pdf.php?file=' . $finalFileName;
        $array['preview_url'] = 'view_pdf.php?file=' . $finalFileName;
        $array['success'] = true;
        $array['result'] = "PDF imposé généré avec succès ! Le PDF contient {$pdfInfo['page_count']} page(s).";
        
        if ($pdfInfo['ghostscript_used']) {
            $array['result'] .= " (Nettoyé avec Ghostscript)";
        }
        
    } catch (Exception $e) {
        // Nettoyer en cas d'erreur
        if (file_exists($inputFile)) unlink($inputFile);
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
        $pageCount = $params['page_count'];
        $copiesPerSheet = $params['copies_per_sheet'];
        $format = $params['format'];
        
        // Dimensions A3 selon le format
        if ($format === 'A5') {
            // A5 : A3 en portrait pour optimiser l'espace
            $a3_width = 297;  // Largeur A3 en portrait (mm)
            $a3_height = 420; // Hauteur A3 en portrait (mm)
            $a3_orientation = 'P'; // Portrait
        } else {
            // A4 et A6 : A3 en paysage
            $a3_width = 420;  // Largeur A3 en paysage (mm)
            $a3_height = 297; // Hauteur A3 en paysage (mm)
            $a3_orientation = 'L'; // Paysage
        }
        
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
        
        // Calculer la disposition sur A3 selon l'orientation
        if ($copiesPerSheet == 2) {
            // 2 copies (A4 sur A3 paysage)
            $cols = 2;
            $rows = 1;
        } elseif ($copiesPerSheet == 4) {
            // 4 copies (A5 sur A3 portrait)
            if ($format === 'A5') {
                $cols = 2;
                $rows = 2; // 2×2 en portrait
            } else {
                $cols = 2;
                $rows = 2; // 2×2 en paysage
            }
        } elseif ($copiesPerSheet == 8) {
            // 8 copies (A6 sur A3 paysage)
            $cols = 4;
            $rows = 2;
        }
        
        // Espacement entre les copies
        $spacingX = ($a3_width - ($cols * $page_width)) / ($cols + 1);
        $spacingY = ($a3_height - ($rows * $page_height)) / ($rows + 1);
        
        // Créer une seule instance TCPDI pour tout le processus
        $pdf = new TCPDI();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setSourceFile($inputFile);
        
        // Logique simplifiée : traiter chaque page séparément
        for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
            // Nouvelle feuille A3 avec la bonne orientation
            $pdf->AddPage($a3_orientation, array($a3_width, $a3_height));
            
            // Importer la page une seule fois
            $templateId = $pdf->importPage($pageNum);
            
            // Dupliquer cette page le nombre de fois nécessaire
            $copiesPlaced = 0;
            for ($row = 0; $row < $rows && $copiesPlaced < $copiesPerSheet; $row++) {
                for ($col = 0; $col < $cols && $copiesPlaced < $copiesPerSheet; $col++) {
                    // Calculer la position
                    $x = $spacingX + $col * ($page_width + $spacingX);
                    $y = $spacingY + $row * ($page_height + $spacingY);
                    
                    // Placer la même page à cette position
                    $pdf->useTemplate($templateId, $x, $y, $page_width, $page_height);
                    
                    $copiesPlaced++;
                }
            }
        }
        
        // Sauvegarder le fichier temporaire
        $tempFile = sys_get_temp_dir() . '/tracts_temp_' . uniqid() . '.pdf';
        $pdf->Output($tempFile, 'F');
        
        return $tempFile;
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de l'imposition : " . $e->getMessage());
    }
}
?>
