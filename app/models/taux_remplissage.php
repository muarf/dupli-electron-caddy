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
 * Analyse le taux de remplissage d'un PDF via Ghostscript (INK_COV)
 * Beaucoup plus rapide et précis que l'analyse pixel par pixel en PHP.
 * Calcule la moyenne de couverture sur l'ensemble des pages.
 */
function analyze_pdf_ink_coverage_gs($pdf_file) {
    try {
        error_log("analyze_pdf_ink_coverage_gs: Début de l'analyse pour " . $pdf_file);
        
        // -q pour mode silencieux, -sDEVICE=ink_cov pour la couverture d'encre
        $gs_args = "-q -dNOPAUSE -dBATCH -sDEVICE=ink_cov -o - " . escapeshellarg($pdf_file);
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            error_log("Erreur Ghostscript dans analyze_pdf_ink_coverage_gs: " . $gs_result['output']);
            throw new Exception("Erreur Ghostscript: " . $gs_result['output']);
        }

        $output = $gs_result['output'];
        $lines = explode("\n", $output);
        $totalC = 0; $totalM = 0; $totalY = 0; $totalK = 0;
        $pageCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            // Le format ink_cov est : C M Y K CMYK OK
            // Exemple : 0.07498  0.07050  0.06968  0.09209 CMYK OK
            if (preg_match('/^\s*(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)/', $line, $matches)) {
                $totalC += (float)$matches[1];
                $totalM += (float)$matches[2];
                $totalY += (float)$matches[3];
                $totalK += (float)$matches[4];
                $pageCount++;
                error_log("Page $pageCount détectée: C=" . $matches[1] . " M=" . $matches[2] . " Y=" . $matches[3] . " K=" . $matches[4]);
            }
        }

        if ($pageCount === 0) {
            error_log("Ghostscript n'a renvoyé aucune donnée de couverture. Output: " . $output);
            throw new Exception("Ghostscript n'a détecté aucune donnée de couverture.");
        }

        // Calculer les moyennes par page
        $avgC = $totalC / $pageCount;
        $avgM = $totalM / $pageCount;
        $avgY = $totalY / $pageCount;
        $avgK = $totalK / $pageCount;

        // Formule demandée par l'utilisateur : Somme des canaux (C+M+J+N)
        // Les valeurs renvoyées par ink_cov sont déjà en pourcentages (0-100).
        $fillRate = ($avgC + $avgM + $avgY + $avgK);
        
        // Détection couleur heuristique
        $maxDiff = max(abs($avgC - $avgM), abs($avgM - $avgY), abs($avgC - $avgY));
        $isColor = ($avgC + $avgM + $avgY > 0.01) && ($maxDiff > 0.005);

        error_log("Analyse PDF terminée: $pageCount pages, Taux moyen=" . round($fillRate, 2) . "%");

        return array(
            'fill_rate' => round($fillRate, 2),
            'empty_rate' => round(max(0, 100 - $fillRate), 2),
            'page_count' => $pageCount,
            'is_color' => $isColor,
            'avg_c' => $avgC,
            'avg_m' => $avgM,
            'avg_y' => $avgY,
            'avg_k' => $avgK,
            'success' => true
        );

    } catch (Exception $e) {
        error_log("Erreur analyse GS : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Convertit un PDF en image PNG (première page seulement) pour la miniature
 */
function convert_pdf_to_thumbnail($pdf_file, $output_dir, $dpi = 72) {
    try {
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        $output_file = $output_dir . DIRECTORY_SEPARATOR . 'thumb_' . time() . '_' . uniqid() . '.png';
        
        // -q pour le silence
        $gs_args = "-q -dNOPAUSE -dBATCH -sDEVICE=png16m -r" . intval($dpi) . 
                   " -dFirstPage=1 -dLastPage=1 -sOutputFile=" . 
                   escapeshellarg($output_file) . " " . escapeshellarg($pdf_file);
        
        $res = run_ghostscript($gs_args);
        if (!$res['success']) {
            error_log("Échec création miniature PDF: " . $res['output']);
            return null;
        }
        
        return file_exists($output_file) ? $output_file : null;
    } catch (Exception $e) { 
        error_log("Exception convert_pdf_to_thumbnail: " . $e->getMessage());
        return null; 
    }
}

if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    
    try {
        error_log("=== TAUX_REMPLISSAGE - Début Action() ===");
        
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            
            // Récupérer la tolérance (uniquement pour les images simples)
            $tolerance = isset($_POST['tolerance']) ? intval($_POST['tolerance']) : 245;
            if ($tolerance < 0) $tolerance = 0;
            if ($tolerance > 255) $tolerance = 255;
            
            // Vérifier si un fichier a été uploadé
            if (!isset($_FILES["file"])) {
                $errors[] = "Aucun fichier n'a été uploadé.";
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
                $errors[] = "Erreur d'upload : " . ($error_messages[$error_code] ?? "Erreur inconnue ($error_code)");
            } else {
                // Vérifier le type MIME
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
                finfo_close($finfo);
                
                error_log("Fichier: " . $_FILES["file"]["name"] . " (MIME: $mimeType, Taille: " . $_FILES["file"]["size"] . ")");
                
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($mimeType, $allowed_types)) {
                    $errors[] = "Le fichier doit être un PDF ou une image (JPEG, PNG, GIF).";
                } elseif ($_FILES["file"]["size"] == 0) {
                    $errors[] = "Le fichier est vide.";
                } elseif ($_FILES["file"]["size"] > 100 * 1024 * 1024) { // Augmenté à 100MB pour les gros PDF
                    $errors[] = "Le fichier est trop volumineux (maximum 100MB).";
                } else {
                    // Créer le dossier tmp
                    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_fillrate' . DIRECTORY_SEPARATOR;
                    if (!is_dir($tmpDir)) {
                        @mkdir($tmpDir, 0777, true);
                    }
                    
                    $extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
                    $uploadFile = $tmpDir . "anal_" . time() . "_" . uniqid() . "." . $extension;
                    
                    if (move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile)) {
                        
                        if ($mimeType === 'application/pdf') {
                            // ANALYSE PDF VIA GHOSTSCRIPT (Rapide et Multi-pages)
                            $result = analyze_pdf_ink_coverage_gs($uploadFile);
                            
                            // Générer une miniature
                            $outputDir = $tmpDir . 'thumb_' . time();
                            $thumbPath = convert_pdf_to_thumbnail($uploadFile, $outputDir);
                            
                            if ($thumbPath) {
                                $thumbInfo = getimagesize($thumbPath);
                                $result['width'] = $thumbInfo[0] ?? 0;
                                $result['height'] = $thumbInfo[1] ?? 0;
                                $result['total_pixels'] = $result['width'] * $result['height'];
                                // Estimation factice des pixels remplis basée sur le taux
                                $result['filled_pixels'] = round($result['total_pixels'] * ($result['fill_rate'] / 100));
                                $result['empty_pixels'] = $result['total_pixels'] - $result['filled_pixels'];

                                $imageData = base64_encode(file_get_contents($thumbPath));
                                $result['preview_url'] = 'data:image/png;base64,' . $imageData;
                                
                                // Nettoyage miniature
                                @unlink($thumbPath);
                                @rmdir($outputDir);
                            } else {
                                $result['preview_url'] = ''; // Pas de miniature
                                $result['width'] = 0;
                                $result['height'] = 0;
                                $result['total_pixels'] = 0;
                                $result['filled_pixels'] = 0;
                                $result['empty_pixels'] = 0;
                            }
                        } else {
                            // ANALYSE IMAGE PIXEL PAR PIXEL (Ancien mode)
                            $result = calculate_fill_rate($uploadFile, $tolerance);
                            $imageData = base64_encode(file_get_contents($uploadFile));
                            $result['preview_url'] = 'data:' . $mimeType . ';base64,' . $imageData;
                            $result['page_count'] = 1;
                        }
                        
                        $result['filename'] = $_FILES["file"]["name"];
                        $result['tolerance'] = $tolerance;
                        $success = true;
                        
                        // Nettoyage du fichier uploadé
                        @unlink($uploadFile);
                    } else {
                        $errors[] = "Erreur interne lors du déplacement du fichier temporaire.";
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Exception dans Action: " . $e->getMessage());
        $errors[] = "Erreur : " . $e->getMessage();
    } catch (Error $e) {
        error_log("Erreur fatale dans Action: " . $e->getMessage());
        $errors[] = "Erreur système : " . $e->getMessage();
    }
    
    return template("../view/taux_remplissage.html.php", array(
        'errors' => $errors,
        'success' => $success,
        'result' => $result
    ));
}
}

?>


