<?php
// Activer l'affichage des erreurs pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');
require_once(__DIR__ . '/../controler/functions/binary_utilities.php');

/**
 * Calcule le taux de remplissage d'une image
 * @param string $image_path Chemin vers l'image
 * @param int $tolerance Tolérance pour considérer un pixel comme blanc (0-255)
 * @return array Tableau avec les statistiques de remplissage
 */
function calculate_fill_rate($image_path, $tolerance = 245) {
    try {
        // Vérifier que GD est disponible
        if (!extension_loaded('gd')) {
            throw new Exception("L'extension PHP GD n'est pas disponible. Veuillez l'installer.");
        }
        
        // Vérifier que le fichier existe
        if (!file_exists($image_path)) {
            throw new Exception("Le fichier n'existe pas : " . $image_path);
        }
        
        // Charger l'image
        $image_info = getimagesize($image_path);
        if (!$image_info) {
            throw new Exception("Impossible de lire l'image.");
        }
        
        $mime_type = $image_info['mime'];
        
        // Créer la ressource image selon le type
        switch ($mime_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($image_path);
                break;
            default:
                throw new Exception("Format d'image non supporté : " . $mime_type);
        }
        
        if (!$image) {
            throw new Exception("Erreur lors du chargement de l'image.");
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        $total_pixels = $width * $height;
        
        $filled_pixels = 0;
        $color_histogram = array();
        
        // Parcourir tous les pixels
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $rgb);
                
                $r = $colors['red'];
                $g = $colors['green'];
                $b = $colors['blue'];
                
                // Calculer la luminosité moyenne
                $luminosity = ($r + $g + $b) / 3;
                
                // Si le pixel n'est pas blanc (selon la tolérance)
                if ($luminosity < $tolerance) {
                    $filled_pixels++;
                }
                
                // Histogramme de couleurs simplifié
                $color_key = sprintf("%02x%02x%02x", round($r/16)*16, round($g/16)*16, round($b/16)*16);
                if (!isset($color_histogram[$color_key])) {
                    $color_histogram[$color_key] = 0;
                }
                $color_histogram[$color_key]++;
            }
        }
        
        // Libérer la mémoire
        imagedestroy($image);
        
        // Calculer le pourcentage de remplissage
        $fill_rate = ($filled_pixels / $total_pixels) * 100;
        
        // Trier les couleurs par fréquence
        arsort($color_histogram);
        $top_colors = array_slice($color_histogram, 0, 10, true);
        
        return array(
            'width' => $width,
            'height' => $height,
            'total_pixels' => $total_pixels,
            'filled_pixels' => $filled_pixels,
            'empty_pixels' => $total_pixels - $filled_pixels,
            'fill_rate' => round($fill_rate, 2),
            'empty_rate' => round(100 - $fill_rate, 2),
            'top_colors' => $top_colors,
            'success' => true
        );
        
    } catch (Exception $e) {
        error_log("Erreur lors du calcul du taux de remplissage : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Convertit un PDF en image PNG pour analyse
 */
function convert_pdf_to_image_for_analysis($pdf_file, $output_dir, $page_number = 1, $dpi = 150) {
    try {
        // Vérifier que le fichier PDF existe
        if (!file_exists($pdf_file)) {
            throw new Exception("Le fichier PDF n'existe pas : " . $pdf_file);
        }
        
        // Créer le dossier de sortie s'il n'existe pas
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0777, true)) {
                throw new Exception("Impossible de créer le dossier de sortie : " . $output_dir);
            }
        }
        
        // Générer un nom de fichier unique pour l'image
        $timestamp = date('YmdHis');
        $output_file = $output_dir . 'page_' . $timestamp . '.png';

        // Convertir la première page du PDF en PNG
        $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . 
                   " -dFirstPage=" . intval($page_number) . " -dLastPage=" . intval($page_number) .
                   " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . 
                   escapeshellarg($output_file) . " " . escapeshellarg($pdf_file);
        
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            throw new Exception("Erreur lors de la conversion avec Ghostscript. Code: " . $gs_result['error'] . " Output: " . $gs_result['output']);
        }
        
        if (!file_exists($output_file)) {
            throw new Exception("L'image n'a pas été créée. Le PDF est peut-être vide ou corrompu.");
        }
        
        return $output_file;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion PDF vers image : " . $e->getMessage());
        throw $e;
    }
}

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    $from_lib_file = null;
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib']) && !empty($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $from_lib_file = $libManager->getFile($_GET['from_lib']);
    }

    try {
        error_log("=== TAUX_REMPLISSAGE - Début Action() ===");
        error_log("REQUEST_METHOD: " . ($_SERVER["REQUEST_METHOD"] ?? 'N/A'));
        
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            
            // Récupérer la tolérance
            $tolerance = isset($_POST['tolerance']) ? intval($_POST['tolerance']) : 245;
            if ($tolerance < 0) $tolerance = 0;
            if ($tolerance > 255) $tolerance = 255;
            
            $pdfFile = null;
            $originalName = null;
            $mimeType = null;
            $fileSize = 0;

            // Cas 1 : Fichier bibliothèque
            if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
                require_once __DIR__ . '/BibliothequeManager.php';
                $libManager = new BibliothequeManager();
                $file = $libManager->getFile($_POST['lib_file_id']);
                if ($file && file_exists($file['filepath'])) {
                    $pdfFile = $file['filepath'];
                    $originalName = $file['filename'];
                    $fileSize = filesize($pdfFile);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $pdfFile);
                    finfo_close($finfo);
                    $is_lib = true;
                } else {
                    $errors[] = "Fichier de bibliothèque introuvable.";
                }
            } 
            // Cas 2 : Fichier uploadé
            elseif (isset($_FILES["file"]) && $_FILES["file"]["error"] == UPLOAD_ERR_OK) {
                // Vérifier le type MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
                finfo_close($finfo);
                
                $pdfFile = $_FILES["file"]["tmp_name"];
                $originalName = $_FILES["file"]["name"];
                $fileSize = $_FILES["file"]["size"];
                $is_lib = false;
            } else {
                $errors[] = "Aucun fichier n'a été sélectionné.";
            }

            if (empty($errors)) {
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($mimeType, $allowed_types)) {
                    $errors[] = "Le fichier doit être un PDF ou une image (JPEG, PNG, GIF).";
                } elseif ($fileSize == 0) {
                    $errors[] = "Le fichier est vide.";
                } elseif ($fileSize > 50 * 1024 * 1024) {
                    $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
                } else {
                    error_log("Validation OK, début du traitement...");
                    
                    // Utiliser resolveTempDir() pour plus de compatibilité (centralisé)
                    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_fillrate' . DIRECTORY_SEPARATOR;
                    if (!is_dir($tmpDir)) {
                        error_log("Création du dossier tmp: " . $tmpDir);
                        mkdir($tmpDir, 0777, true);
                        @chmod($tmpDir, 0777);
                    }
                    
                    $timestamp = date('YmdHis');
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $workingFile = $is_lib ? $pdfFile : $tmpDir . "upload_" . $timestamp . "." . $extension;
                    
                    if (!$is_lib) {
                        if (!move_uploaded_file($pdfFile, $workingFile)) {
                            throw new Exception("Erreur lors de l'upload du fichier.");
                        }
                    }

                    $image_to_analyze = $workingFile;
                    
                    // Si c'est un PDF, le convertir en image
                    if ($mimeType === 'application/pdf') {
                        error_log("Conversion PDF en image...");
                        $page_to_analyze = isset($_POST['page_number']) ? intval($_POST['page_number']) : 1;
                        if ($page_to_analyze < 1) $page_to_analyze = 1;
                        
                        $outputDir = $tmpDir . 'analysis_' . $timestamp . '/';
                        $image_to_analyze = convert_pdf_to_image_for_analysis($workingFile, $outputDir, $page_to_analyze);
                        error_log("Image créée: " . $image_to_analyze);
                    }
                    
                    // Calculer le taux de remplissage
                    error_log("Calcul du taux de remplissage...");
                    $result = calculate_fill_rate($image_to_analyze, $tolerance);
                    error_log("Calcul terminé: " . $result['fill_rate'] . "%");
                    $success = true;
                    
                    // GESTION DE L'IMAGE DE PREVIEW
                    $imageData = base64_encode(file_get_contents($image_to_analyze));
                    $mime = mime_content_type($image_to_analyze);
                    $preview_url = 'data:' . $mime . ';base64,' . $imageData;
                    
                    $result['preview_url'] = $preview_url;
                    $result['filename'] = $originalName;
                    $result['tolerance'] = $tolerance;
                    
                    error_log("Preview générée en base64");
                    
                    // Nettoyer les fichiers temporaires
                    if ($image_to_analyze !== $workingFile && file_exists($image_to_analyze)) {
                        unlink($image_to_analyze);
                    }
                    if (isset($outputDir) && is_dir($outputDir)) {
                        rmdir($outputDir);
                    }
                    if (!$is_lib && file_exists($workingFile)) {
                        unlink($workingFile);
                    }
                        
                        error_log("Nettoyage effectué");
                        error_log("Traitement terminé avec succès");
                    } else {
                        $errors[] = "Erreur lors de l'upload du fichier (move_uploaded_file a échoué).";
                        error_log("ERREUR: move_uploaded_file a échoué");
                    }
                }
            }
        } else {
            error_log("Pas de requête POST, affichage du formulaire");
        }
    } catch (Exception $e) {
        error_log("=== EXCEPTION CAPTURÉE ===");
        error_log("Message: " . $e->getMessage());
        error_log("Fichier: " . $e->getFile());
        error_log("Ligne: " . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());
        $errors[] = "Erreur lors du traitement : " . $e->getMessage();
    } catch (Error $e) {
        error_log("=== ERREUR FATALE CAPTURÉE ===");
        error_log("Message: " . $e->getMessage());
        error_log("Fichier: " . $e->getFile());
        error_log("Ligne: " . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());
        $errors[] = "Erreur fatale : " . $e->getMessage();
    }
    
    error_log("=== Fin Action() - Erreurs: " . count($errors) . ", Succès: " . ($success ? 'OUI' : 'NON') . " ===");
    
    try {
        $template_result = template("../view/taux_remplissage.html.php", array(
            'errors' => $errors,
            'success' => $success,
            'result' => $result,
            'from_lib_file' => $from_lib_file
        ));
        error_log("Template généré avec succès");
        return $template_result;
    } catch (Exception $e) {
        error_log("=== ERREUR LORS DU TEMPLATE ===");
        error_log("Message: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        // En cas d'erreur de template, afficher quelque chose
        return '<div class="container"><div class="alert alert-danger"><h1>Erreur</h1><p>' . 
               htmlspecialchars($e->getMessage()) . '</p><pre>' . 
               htmlspecialchars($e->getTraceAsString()) . '</pre></div></div>';
    }
}
}

?>


