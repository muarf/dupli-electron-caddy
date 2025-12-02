<?php
error_log("[IMPOSITION_TRACTS] Fichier imposition_tracts.php chargé");
require_once(__DIR__ . '/../controler/functions/utilities.php');
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');

use setasign\Fpdi\TcpdfFpdi as TCPDI;
error_log("[IMPOSITION_TRACTS] Imports terminés, fonction Action() va être définie");

function Action($conf = null)
{
    error_log("[IMPOSITION_TRACTS] Action() appelée - GET: " . print_r($_GET, true));
    error_log("[IMPOSITION_TRACTS] Action() appelée - POST: " . print_r($_POST, true));
    error_log("[IMPOSITION_TRACTS] Action() appelée - FILES: " . print_r($_FILES, true));
    $array = array();
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $file = $libManager->getFile($_GET['from_lib']);
        if ($file) {
            $array['from_lib_file'] = $file;
        }
    }
    
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
    // Vérifier si le formulaire est soumis : soit via submit, soit via FILES (le fichier est présent)
    error_log("[IMPOSITION_TRACTS] Vérification formulaire - POST submit: " . (isset($_POST['submit']) ? 'OUI' : 'NON'));
    error_log("[IMPOSITION_TRACTS] Vérification formulaire - FILES pdf_file: " . (isset($_FILES['pdf_file']) ? 'OUI' : 'NON'));
    error_log("[IMPOSITION_TRACTS] Méthode HTTP: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
    
    // Le formulaire est soumis si on a un fichier ET que c'est une requête POST
    // (pas besoin de vérifier submit car le bouton peut ne pas être dans POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ((isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) || (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])))) {
        error_log("[IMPOSITION_TRACTS] Début traitement formulaire");
        error_log("[IMPOSITION_TRACTS] POST submit: " . (isset($_POST['submit']) ? 'OUI' : 'NON'));
        error_log("[IMPOSITION_TRACTS] FILES pdf_file: " . (isset($_FILES['pdf_file']) ? 'OUI' : 'NON'));
        if (isset($_FILES['pdf_file'])) {
            error_log("[IMPOSITION_TRACTS] Détails upload - name: " . ($_FILES['pdf_file']['name'] ?? 'N/A'));
            error_log("[IMPOSITION_TRACTS] Détails upload - error: " . ($_FILES['pdf_file']['error'] ?? 'N/A'));
            error_log("[IMPOSITION_TRACTS] Détails upload - tmp_name: " . ($_FILES['pdf_file']['tmp_name'] ?? 'N/A'));
            error_log("[IMPOSITION_TRACTS] Détails upload - size: " . ($_FILES['pdf_file']['size'] ?? 'N/A'));
        }
        try {
            // Si fichier bibliothèque, créer un $_FILES temporaire
            if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
                require_once __DIR__ . '/BibliothequeManager.php';
                $libManager = new BibliothequeManager();
                $file = $libManager->getFile($_POST['lib_file_id']);
                if ($file && file_exists($file['filepath'])) {
                    // Créer un fichier temporaire copié depuis la bibliothèque
                    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
                    if (!file_exists($tmp_dir)) {
                        mkdir($tmp_dir, 0755, true);
                    }
                    $tmpFile = $tmp_dir . 'lib_' . uniqid() . '_' . basename($file['filepath']);
                    copy($file['filepath'], $tmpFile);
                    
                    // Simuler $_FILES pour processImpositionTracts
                    $_FILES['pdf_file'] = [
                        'name' => $file['filename'],
                        'type' => 'application/pdf',
                        'tmp_name' => $tmpFile,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($tmpFile)
                    ];
                } else {
                    throw new Exception("Erreur : Fichier de bibliothèque introuvable.");
                }
            }
            
            $array = processImpositionTracts();
        } catch (Exception $e) {
            error_log("[IMPOSITION_TRACTS] Exception capturée: " . $e->getMessage());
            error_log("[IMPOSITION_TRACTS] Stack trace: " . $e->getTraceAsString());
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
            // Utiliser sys_get_temp_dir() pour être compatible AppImage
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
    error_log("[IMPOSITION_TRACTS] processImpositionTracts() appelée");
    $array = array();
    
    // Vérifier qu'un fichier a été uploadé
    error_log("[IMPOSITION_TRACTS] Vérification upload - isset FILES: " . (isset($_FILES['pdf_file']) ? 'OUI' : 'NON'));
    if (!isset($_FILES['pdf_file'])) {
        error_log("[IMPOSITION_TRACTS] ERREUR: \$_FILES['pdf_file'] n'est pas défini");
        throw new Exception("Erreur lors de l'upload du fichier PDF - fichier non reçu.");
    }
    
    error_log("[IMPOSITION_TRACTS] Upload error code: " . $_FILES['pdf_file']['error']);
    if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'Taille du fichier dépasse upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Taille du fichier dépasse MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'Upload partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec écriture sur disque',
            UPLOAD_ERR_EXTENSION => 'Upload arrêté par extension PHP'
        ];
        $errorMsg = $errorMessages[$_FILES['pdf_file']['error']] ?? 'Erreur inconnue: ' . $_FILES['pdf_file']['error'];
        error_log("[IMPOSITION_TRACTS] ERREUR upload: " . $errorMsg);
        throw new Exception("Erreur lors de l'upload du fichier PDF: " . $errorMsg);
    }
    
    // Créer un nom de fichier unique
    $uniqueId = uniqid();
    $originalName = $_FILES['pdf_file']['name'];
    $originalNameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
    $tempFile = $_FILES['pdf_file']['tmp_name'];
    
    error_log("[IMPOSITION_TRACTS] Fichier original: " . $originalName);
    error_log("[IMPOSITION_TRACTS] Fichier temporaire upload: " . $tempFile);
    error_log("[IMPOSITION_TRACTS] Fichier temporaire existe: " . (file_exists($tempFile) ? 'OUI' : 'NON'));
    if (file_exists($tempFile)) {
        error_log("[IMPOSITION_TRACTS] Taille fichier temporaire: " . filesize($tempFile) . " bytes");
        error_log("[IMPOSITION_TRACTS] Fichier temporaire lisible: " . (is_readable($tempFile) ? 'OUI' : 'NON'));
    }
    
    // Utiliser sys_get_temp_dir() pour être compatible AppImage
    $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
    error_log("[IMPOSITION_TRACTS] Répertoire temporaire: " . $tmp_dir);
    if (!file_exists($tmp_dir)) {
        error_log("[IMPOSITION_TRACTS] Création répertoire temporaire");
        $mkdirResult = mkdir($tmp_dir, 0755, true);
        error_log("[IMPOSITION_TRACTS] Résultat mkdir: " . ($mkdirResult ? 'SUCCÈS' : 'ÉCHEC'));
    }
    error_log("[IMPOSITION_TRACTS] Répertoire existe: " . (file_exists($tmp_dir) ? 'OUI' : 'NON'));
    error_log("[IMPOSITION_TRACTS] Répertoire accessible en écriture: " . (is_writable($tmp_dir) ? 'OUI' : 'NON'));
    
    $inputFile = $tmp_dir . 'tracts_input_' . $uniqueId . '.pdf';
    error_log("[IMPOSITION_TRACTS] Fichier destination: " . $inputFile);
    
    // Déplacer le fichier uploadé
    error_log("[IMPOSITION_TRACTS] Tentative move_uploaded_file de " . $tempFile . " vers " . $inputFile);
    $moveResult = move_uploaded_file($tempFile, $inputFile);
    error_log("[IMPOSITION_TRACTS] Résultat move_uploaded_file: " . ($moveResult ? 'SUCCÈS' : 'ÉCHEC'));
    
    if (!$moveResult) {
        error_log("[IMPOSITION_TRACTS] ERREUR: move_uploaded_file a échoué");
        error_log("[IMPOSITION_TRACTS] tempFile existe: " . (file_exists($tempFile) ? 'OUI' : 'NON'));
        error_log("[IMPOSITION_TRACTS] inputFile existe après move: " . (file_exists($inputFile) ? 'OUI' : 'NON'));
        $lastError = error_get_last();
        error_log("[IMPOSITION_TRACTS] Dernière erreur PHP: " . ($lastError ? $lastError['message'] : 'N/A'));
        throw new Exception("Impossible de déplacer le fichier uploadé.");
    }
    
    // Vérifier les permissions du fichier déplacé
    error_log("[IMPOSITION_TRACTS] Vérification fichier déplacé");
    if (!file_exists($inputFile)) {
        error_log("[IMPOSITION_TRACTS] ERREUR: Le fichier déplacé n'existe pas");
        throw new Exception("Le fichier déplacé n'existe pas.");
    }
    
    error_log("[IMPOSITION_TRACTS] Fichier déplacé existe - taille: " . filesize($inputFile) . " bytes");
    
    if (!is_readable($inputFile)) {
        error_log("[IMPOSITION_TRACTS] ERREUR: Le fichier déplacé n'est pas lisible");
        throw new Exception("Le fichier déplacé n'est pas lisible.");
    }
    
    // S'assurer que le fichier a les bonnes permissions
    chmod($inputFile, 0644);
    error_log("[IMPOSITION_TRACTS] Permissions fichier après chmod: " . substr(sprintf('%o', fileperms($inputFile)), -4));
    
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
        $orientation = $_POST['orientation'] ?? 'auto';
        
        // Appliquer le format manuel si spécifié
        if ($manualFormat !== 'auto') {
            $pdfInfo['format'] = $manualFormat;
            $pdfInfo['forced_format'] = true;
        }
        
        // Déterminer les paramètres d'imposition automatiquement
        $impositionParams = determineAutomaticParams($pdfInfo);
        
        // Ajouter les options de redimensionnement et orientation
        $impositionParams['force_resize'] = $forceResize;
        $impositionParams['manual_format'] = $manualFormat;
        $impositionParams['orientation'] = $orientation;
        
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
        
        // Nettoyer le nom de fichier
        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalNameWithoutExt);
        $finalFileName = $safe_filename . '_imposed.pdf';
        $finalFilePath = $tmp_dir . $finalFileName;
        
        if (!copy($resultFile, $finalFilePath)) {
            throw new Exception("Impossible de sauvegarder le fichier final.");
        }
        
        // Nettoyer les fichiers temporaires
        unlink($inputFile);
        if (file_exists($resultFile)) unlink($resultFile);
        
        // Utiliser le même système de téléchargement et prévisualisation que impose/unimpose
        $array['download_url'] = '?download_pdf&file=' . $finalFileName;
        $array['preview_url'] = '?view_pdf&file=' . $finalFileName;
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
        $orientation = $params['orientation'] ?? 'auto';
        
        // Dimensions A3 selon l'orientation choisie ou automatique
        if ($orientation === 'portrait') {
            // Orientation portrait forcée
            $a3_width = 297;  // Largeur A3 en portrait (mm)
            $a3_height = 420; // Hauteur A3 en portrait (mm)
            $a3_orientation = 'P'; // Portrait
        } elseif ($orientation === 'landscape') {
            // Orientation paysage forcée
            $a3_width = 420;  // Largeur A3 en paysage (mm)
            $a3_height = 297; // Hauteur A3 en paysage (mm)
            $a3_orientation = 'L'; // Paysage
        } else {
            // Orientation automatique selon le format
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
        
        // Calculer la disposition sur A3 selon l'orientation et le nombre de copies
        // La disposition doit s'adapter à l'orientation choisie
        if ($copiesPerSheet == 2) {
            // 2 copies (A4 sur A3)
            if ($a3_orientation === 'P') {
                // Portrait : 1 colonne, 2 lignes
                $cols = 1;
                $rows = 2;
            } else {
                // Paysage : 2 colonnes, 1 ligne
                $cols = 2;
                $rows = 1;
            }
        } elseif ($copiesPerSheet == 4) {
            // 4 copies (A5 sur A3) - toujours 2×2
            $cols = 2;
            $rows = 2;
        } elseif ($copiesPerSheet == 8) {
            // 8 copies (A6 sur A3)
            if ($a3_orientation === 'P') {
                // Portrait : 2 colonnes, 4 lignes
                $cols = 2;
                $rows = 4;
            } else {
                // Paysage : 4 colonnes, 2 lignes
                $cols = 4;
                $rows = 2;
            }
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
        // Utiliser sys_get_temp_dir() pour être compatible AppImage
        $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator' . DIRECTORY_SEPARATOR;
        if (!file_exists($tmp_dir)) {
            mkdir($tmp_dir, 0755, true);
        }
        
        $tempFile = $tmp_dir . 'tracts_temp_' . uniqid() . '.pdf';
        $pdf->Output($tempFile, 'F');
        
        return $tempFile;
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de l'imposition : " . $e->getMessage());
    }
}
?>
