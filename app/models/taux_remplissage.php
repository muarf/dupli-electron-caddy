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

function convert_pdf_to_images_for_analysis($pdf_file, $output_dir, $dpi = 150) {
    try {
        if (!file_exists($pdf_file)) {
            throw new Exception("Le fichier PDF n'existe pas : " . $pdf_file);
        }
        
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0777, true)) {
                throw new Exception("Impossible de créer le dossier de sortie : " . $output_dir);
            }
        }
        
        $output_pattern = $output_dir . 'page_%d.png';

        // Convertir TOUTES les pages du PDF en PNG
        $gs_args = "-dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . 
                   " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . 
                   escapeshellarg($output_pattern) . " " . escapeshellarg($pdf_file);
        
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            throw new Exception("Erreur lors de la conversion avec Ghostscript. Code: " . $gs_result['error'] . " Output: " . $gs_result['output']);
        }
        
        // Récupérer la liste des fichiers créés
        $files = glob($output_dir . 'page_*.png');
        if (empty($files)) {
            throw new Exception("Aucune image n'a été créée.");
        }
        
        // Trier les fichiers par numéro de page (tri naturel)
        natsort($files);
        return array_values($files);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion PDF vers images : " . $e->getMessage());
        throw $e;
    }
}

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    
    try {
        error_log("=== TAUX_REMPLISSAGE - Début Action() ===");
        error_log("REQUEST_METHOD: " . ($_SERVER["REQUEST_METHOD"] ?? 'N/A'));
        error_log("POST data: " . print_r($_POST, true));
        error_log("FILES data: " . print_r($_FILES, true));
        
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            
            // Récupérer la tolérance
            $tolerance = isset($_POST['tolerance']) ? intval($_POST['tolerance']) : 245;
            if ($tolerance < 0) $tolerance = 0;
            if ($tolerance > 255) $tolerance = 255;
            
            error_log("Tolérance: " . $tolerance);
            
            // Vérifier si un fichier a été uploadé
            if (!isset($_FILES["file"])) {
                $errors[] = "Aucun fichier n'a été uploadé.";
                error_log("ERREUR: Aucun fichier dans \$_FILES");
            } elseif ($_FILES["file"]["error"] != UPLOAD_ERR_OK) {
                $error_messages = array(
                    UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la limite upload_max_filesize du php.ini.',
                    UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la limite MAX_FILE_SIZE du formulaire.',
                    UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé.',
                    UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
                    UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
                    UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier.'
                );
                $error_code = $_FILES["file"]["error"];
                $error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : "Erreur inconnue ($error_code)";
                $errors[] = "Erreur d'upload : " . $error_msg;
                error_log("ERREUR UPLOAD: " . $error_msg);
            } else {
                error_log("Fichier uploadé avec succès, vérification du type MIME...");
                
                // Vérifier le type MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
                finfo_close($finfo);
                
                error_log("Type MIME détecté: " . $mimeType);
                error_log("Taille du fichier: " . $_FILES["file"]["size"]);
                
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($mimeType, $allowed_types)) {
                    $errors[] = "Le fichier doit être un PDF ou une image (JPEG, PNG, GIF). Type détecté: " . $mimeType;
                    error_log("Type MIME non autorisé: " . $mimeType);
                } elseif ($_FILES["file"]["size"] == 0) {
                    $errors[] = "Le fichier est vide.";
                    error_log("Fichier vide");
                } elseif ($_FILES["file"]["size"] > 50 * 1024 * 1024) {
                    $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
                    error_log("Fichier trop volumineux: " . $_FILES["file"]["size"]);
                } else {
                    error_log("Validation OK, début du traitement...");
                    
                    // Créer le dossier tmp système pour l'AppImage
                    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_fillrate' . DIRECTORY_SEPARATOR;
                    if (!is_dir($tmpDir)) {
                        error_log("Création du dossier tmp: " . $tmpDir);
                        if (!mkdir($tmpDir, 0777, true)) {
                            $errors[] = "Impossible de créer le dossier temporaire.";
                            error_log("ERREUR: mkdir a échoué pour " . $tmpDir);
                            // Fallback vers le dossier public/tmp si le dossier système échoue
                            $tmpDir = __DIR__ . '/../public/tmp/';
                            if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
                        }
                    }
                    
                    $timestamp = date('YmdHis');
                    $extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
                    $uploadFile = $tmpDir . "upload_" . $timestamp . "." . $extension;
                    
                    error_log("Déplacement du fichier vers: " . $uploadFile);
                    
                    if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile)) {
                        error_log("Fichier déplacé avec succès");
                        
                        $images_to_analyze = array();
                        
                        // Si c'est un PDF, le convertir en plusieurs images
                        if ($mimeType === 'application/pdf') {
                            error_log("Conversion PDF en images (toutes les pages)...");
                            $outputDir = $tmpDir . 'analysis_' . $timestamp . '/';
                            $images_to_analyze = convert_pdf_to_images_for_analysis($uploadFile, $outputDir);
                            error_log(count($images_to_analyze) . " images créées");
                        } else {
                            $images_to_analyze = array($uploadFile);
                        }
                        
                        // Calculer le taux de remplissage moyen
                        error_log("Calcul du taux de remplissage sur " . count($images_to_analyze) . " page(s)...");
                        $total_fill = 0;
                        $page_count = count($images_to_analyze);
                        $last_result = null;
                        
                        foreach ($images_to_analyze as $img) {
                            $last_result = calculate_fill_rate($img, $tolerance);
                            $total_fill += $last_result['fill_rate'];
                        }
                        
                        $average_fill = $total_fill / $page_count;
                        $result = $last_result; // Garder les infos de la dernière page (dimensions etc)
                        $result['fill_rate'] = round($average_fill, 2);
                        $result['empty_rate'] = round(100 - $average_fill, 2);
                        $result['page_count'] = $page_count;
                        
                        error_log("Calcul terminé: " . $result['fill_rate'] . "% sur $page_count page(s)");
                        $success = true;
                        
                        // GESTION DE L'IMAGE DE PREVIEW (Première page)
                        $preview_img = $images_to_analyze[0];
                        $imageData = base64_encode(file_get_contents($preview_img));
                        $mime = mime_content_type($preview_img);
                        $preview_url = 'data:' . $mime . ';base64,' . $imageData;
                        
                        $result['preview_url'] = $preview_url;
                        $result['filename'] = $_FILES["file"]["name"];
                        $result['tolerance'] = $tolerance;
                        
                        error_log("Preview générée en base64 (page 1)");
                        
                        // Nettoyer les fichiers temporaires
                        foreach ($images_to_analyze as $img) {
                            if ($img !== $uploadFile && file_exists($img)) {
                                unlink($img);
                            }
                        }
                        // 2. Le dossier d'analyse si créé
                        if (isset($outputDir) && is_dir($outputDir)) {
                            rmdir($outputDir);
                        }
                        // 3. Le fichier uploadé original
                        if (file_exists($uploadFile)) {
                            unlink($uploadFile);
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
            'result' => $result
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


