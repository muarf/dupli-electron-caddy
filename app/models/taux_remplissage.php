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
        $pages = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\s*(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)\s+(\d+\.\d+)/', $line, $matches)) {
                $c = (float)$matches[1];
                $m = (float)$matches[2];
                $y = (float)$matches[3];
                $k = (float)$matches[4];
                $totalC += $c;
                $totalM += $m;
                $totalY += $y;
                $totalK += $k;
                $pageCount++;
                
                $pages[] = [
                    'page' => $pageCount,
                    'fill_rate' => round($c + $m + $y + $k, 2),
                    'c' => $c, 'm' => $m, 'y' => $y, 'k' => $k
                ];
            }
        }

        if ($pageCount === 0) {
            error_log("Ghostscript n'a renvoyé aucune donnée de couverture. Output: " . $output);
            throw new Exception("Ghostscript n'a détecté aucune donnée de couverture.");
        }

        $avgC = $totalC / $pageCount;
        $avgM = $totalM / $pageCount;
        $avgY = $totalY / $pageCount;
        $avgK = $totalK / $pageCount;
        
        $maxDiff = max(abs($avgC - $avgM), abs($avgM - $avgY), abs($avgC - $avgY));
        $isColor = ($avgC + $avgM + $avgY > 0.01) && ($maxDiff > 0.005);

        // 2. Calcul du VRAI taux de couverture par pixels (Area Coverage)
        $temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_pages_' . uniqid();
        mkdir($temp_dir, 0777, true);
        $output_pattern = $temp_dir . DIRECTORY_SEPARATOR . 'page_%d.png';
        
        // Rendu en 36 DPI (basse résolution pour aller vite)
        // Note: On Windows escapeshellarg strips "%", so we use manual quotes for the safe output pattern.
        $gs_png_args = "-q -dNOPAUSE -dBATCH -sDEVICE=png16m -r36 -sOutputFile=\"" . $output_pattern . "\" " . escapeshellarg($pdf_file);
        $gs_png_result = run_ghostscript($gs_png_args);
        
        $total_true_fill_rate = 0;
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $png_file = $temp_dir . DIRECTORY_SEPARATOR . 'page_' . $i . '.png';
            $page_fill_rate = 0;
            
            if (file_exists($png_file)) {
                try {
                    $pixel_analysis = calculate_fill_rate($png_file, 245);
                    if ($pixel_analysis['success']) {
                        $page_fill_rate = $pixel_analysis['fill_rate'];
                    }
                } catch (Exception $e) {
                    error_log("Erreur analyse page $i : " . $e->getMessage());
                }
                unlink($png_file);
            }
            
            // On met à jour le vrai fill_rate dans le tableau des pages
            $pages[$i - 1]['fill_rate'] = round($page_fill_rate, 2);
            $total_true_fill_rate += $page_fill_rate;
        }
        
        if (is_dir($temp_dir)) {
            rmdir($temp_dir);
        }
        
        $avg_fill_rate = $total_true_fill_rate / $pageCount;

        return array(
            'fill_rate' => round($avg_fill_rate, 2),
            'empty_rate' => round(max(0, 100 - $avg_fill_rate), 2),
            'page_count' => $pageCount,
            'is_color' => $isColor,
            'pages' => $pages,
            'avg_c' => round($avgC, 2),
            'avg_m' => round($avgM, 2),
            'avg_y' => round($avgY, 2),
            'avg_k' => round($avgK, 2),
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



?>


