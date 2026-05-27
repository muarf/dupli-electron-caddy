<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../controler/functions/i18n.php');

use setasign\Fpdi\TcpdfFpdi as TCPDI;

/**
 * Convertit un PDF en images PNG en préservant les dimensions exactes
 * Utilise une résolution élevée (300 DPI) pour préserver la qualité
 */
function convert_pdf_to_images_preserve_size($pdf_file, $output_dir, $base_filename = 'page') {
    try {
        // D'abord, lire les dimensions du PDF avec FPDI
        $pdf = new TCPDI();
        $pageCount = $pdf->setSourceFile($pdf_file);
        
        if ($pageCount === 0) {
            throw new Exception("Le PDF est vide ou illisible.");
        }
        
        // Obtenir les dimensions de CHAQUE page (en mm)
        // Utiliser explicitement la CropBox pour correspondre à ce que Ghostscript va générer avec -dUseCropBox=true
        $page_dimensions = array();
        for ($page = 1; $page <= $pageCount; $page++) {
            // Essayer d'abord avec CropBox, puis MediaBox en fallback
            try {
                $templateId = $pdf->importPage($page, '/CropBox');
            } catch (Exception $e) {
                // Si CropBox n'existe pas, utiliser MediaBox (par défaut)
                $templateId = $pdf->importPage($page);
            }
            $size = $pdf->getTemplateSize($templateId);
            $page_dimensions[$page] = array(
                'width' => $size['width'],
                'height' => $size['height']
            );
            error_log("PDF Page $page : Dimensions détectées W={$size['width']}mm, H={$size['height']}mm");
        }
        
        // Utiliser 300 DPI pour préserver la qualité/résolution du PDF original
        // Le DPI détermine la résolution des images générées, pas la taille finale
        $dpi = 300;
        // Vérifier que le fichier PDF existe
        if (!file_exists($pdf_file)) {
            throw new Exception("Le fichier PDF n'existe pas : " . $pdf_file);
        }
        
        // Créer le dossier de sortie s'il n'existe pas
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0777, true);
        }
        
        // Générer un préfixe avec le nom du fichier original
        $prefix = $base_filename . '_page_%03d.png';
        $output_pattern = $output_dir . $prefix;

        $escaped_output_pattern = (PHP_OS_FAMILY === 'Windows') ? '"' . str_replace('"', '""', $output_pattern) . '"' : escapeshellarg($output_pattern);

        // Utiliser Ghostscript pour convertir le PDF en PNG à haute résolution
        // IMPORTANT: Utiliser -dUseCropBox=true pour que les images générées correspondent
        // exactement aux dimensions détectées par FPDI (qui lit aussi la CropBox)
        $gs_args = "-dNOPAUSE -dBATCH -dUseCropBox=true -sDEVICE=png16m -r" . intval($dpi) . " -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . $escaped_output_pattern . " " . escapeshellarg($pdf_file);
        
        $gs_result = run_ghostscript($gs_args);
        
        if (!$gs_result['success']) {
            throw new Exception("Erreur lors de la conversion avec Ghostscript. Code: " . $gs_result['error'] . " Output: " . $gs_result['output']);
        }
        
        // Lister les fichiers PNG créés
        $created_files = glob($output_dir . $base_filename . '_page_*.png');
        
        if (empty($created_files)) {
            throw new Exception("Aucune image n'a été créée. Le PDF est peut-être vide ou corrompu.");
        }
        
        // Trier les fichiers par nom
        sort($created_files);
        
        return array(
            'files' => $created_files,
            'page_dimensions' => $page_dimensions, // Tableau avec dimensions par page
            'dpi' => $dpi
        );
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion PDF vers PNG : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Convertit des images en PDF en préservant les dimensions exactes du PDF original
 * Les images sont insérées avec leurs dimensions exactes en mm, en préservant la résolution
 * @param array $image_files Tableau des chemins vers les fichiers images
 * @param string $output_file Chemin du fichier PDF de sortie
 * @param array $page_dimensions Tableau associatif [page_num => ['width' => mm, 'height' => mm]]
 * @param int $conversion_dpi DPI utilisé pour la conversion (par défaut 300)
 */
function convert_images_to_pdf_preserve_size($image_files, $output_file, $page_dimensions, $conversion_dpi = 300) {
    try {
        // Créer un nouveau PDF avec TCPDF (sans dimensions fixes au départ)
        // Format A4 par défaut, sera écrasé par AddPage avec les bonnes dimensions
        $pdf = new TCPDF('P', 'mm', array(210, 297), true, 'UTF-8', false);
        
        // Configurer le PDF
        $pdf->SetCreator('Duplicator');
        $pdf->SetAuthor('Duplicator');
        $pdf->SetTitle('Image traitée');
        
        // Supprimer les en-têtes et pieds de page
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configurer les marges
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Ajouter chaque image sur une nouvelle page avec ses dimensions spécifiques
        foreach ($image_files as $index => $image_file) {
            if (!file_exists($image_file)) {
                throw new Exception("Le fichier image n'existe pas : " . $image_file);
            }
            
            // Obtenir les dimensions de cette page (index + 1 car les pages commencent à 1)
            $page_num = $index + 1;
            if (isset($page_dimensions[$page_num])) {
                $width_mm = $page_dimensions[$page_num]['width'];
                $height_mm = $page_dimensions[$page_num]['height'];
                error_log("Page $page_num : Dimensions trouvées W=$width_mm, H=$height_mm");
            } else {
                // Fallback : utiliser les dimensions de la première page si non définies
                $width_mm = $page_dimensions[1]['width'];
                $height_mm = $page_dimensions[1]['height'];
                error_log("Page $page_num : Dimensions NON trouvées, fallback sur page 1 W=$width_mm, H=$height_mm. Keys disponibles: " . implode(',', array_keys($page_dimensions)));
            }
            
            // Obtenir les dimensions de l'image en pixels
            $image_info = getimagesize($image_file);
            $img_width_px = $image_info[0];
            $img_height_px = $image_info[1];
            
            // Calculer le DPI réel de l'image basé sur sa taille en pixels et les dimensions cibles en mm
            // DPI = (pixels / inches) = (pixels / (mm / 25.4))
            $width_inches = $width_mm / 25.4;
            $height_inches = $height_mm / 25.4;
            $dpi_x = $img_width_px / $width_inches;
            $dpi_y = $img_height_px / $height_inches;
            // Utiliser la moyenne pour le DPI d'insertion
            $insert_dpi = round(($dpi_x + $dpi_y) / 2);
            
            error_log("Page $page_num : Image PNG réelle {$img_width_px}x{$img_height_px}px, Dimensions attendues {$width_mm}x{$height_mm}mm, DPI calculé X={$dpi_x} Y={$dpi_y} Moyenne={$insert_dpi}");
            
            // Déterminer l'orientation explicitement pour éviter l'inversion automatique de TCPDF
            // Si width > height = Paysage (L), sinon Portrait (P)
            $orientation = ($width_mm > $height_mm) ? 'L' : 'P';
            
            // Ajouter une page avec les dimensions exactes de CETTE page spécifique
            // Utiliser l'orientation explicite pour éviter que TCPDF inverse automatiquement
            $pdf->AddPage($orientation, array($width_mm, $height_mm));
            
            // Insérer l'image avec les dimensions exactes en mm
            // Le paramètre DPI indique à TCPDF la résolution de l'image pour le calcul de la taille
            $pdf->Image($image_file, 0, 0, $width_mm, $height_mm, '', '', '', false, $insert_dpi, '', false, false, 0);
        }
        
        // Sauvegarder le PDF
        $pdf->Output($output_file, 'F');
        
        return file_exists($output_file);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion images vers PDF : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Charge une image depuis un fichier
 */
function load_image($image_path) {
    if (!file_exists($image_path)) {
        throw new Exception("Le fichier n'existe pas : " . $image_path);
    }
    
    if (!extension_loaded('gd')) {
        throw new Exception("L'extension PHP GD n'est pas disponible.");
    }
    
    $image_info = getimagesize($image_path);
    if (!$image_info) {
        throw new Exception("Impossible de lire l'image.");
    }
    
    $mime_type = $image_info['mime'];
    
    switch ($mime_type) {
        case 'image/jpeg':
            return imagecreatefromjpeg($image_path);
        case 'image/png':
            return imagecreatefrompng($image_path);
        case 'image/gif':
            return imagecreatefromgif($image_path);
        case 'image/webp':
            if (!function_exists('imagecreatefromwebp')) {
                throw new Exception("Le support WebP n'est pas disponible dans cette version de GD.");
            }
            return imagecreatefromwebp($image_path);
        default:
            throw new Exception("Format d'image non supporté : " . $mime_type);
    }
}

/**
 * Sauvegarde une image
 */
function save_image($image, $output_path, $mime_type) {
    switch ($mime_type) {
        case 'image/jpeg':
            return imagejpeg($image, $output_path, 95);
        case 'image/png':
            return imagepng($image, $output_path, 9);
        case 'image/gif':
            return imagegif($image, $output_path);
        case 'image/webp':
            if (!function_exists('imagewebp')) {
                throw new Exception("Le support WebP n'est pas disponible dans cette version de GD.");
            }
            return imagewebp($image, $output_path, 90);
        default:
            throw new Exception("Format d'image non supporté pour la sauvegarde : " . $mime_type);
    }
}

/**
 * Ajuste le contraste d'une image (-100 à +100)
 */
function adjust_contrast($image, $contrast) {
    // imagefilter attend -100 à +100
    $level = intval($contrast);
    if ($level < -100) $level = -100;
    if ($level > 100) $level = 100;
    
    return imagefilter($image, IMG_FILTER_CONTRAST, $level);
}

/**
 * Ajuste la luminosité d'une image (-100 à +100)
 */
function adjust_brightness($image, $brightness) {
    // imagefilter attend -255 à +255, on convertit de -100 à +100
    $level = intval(($brightness / 100) * 255);
    if ($level < -255) $level = -255;
    if ($level > 255) $level = 255;
    
    return imagefilter($image, IMG_FILTER_BRIGHTNESS, $level);
}

/**
 * Ajuste le gamma d'une image (0.1 à 3.0)
 */
function adjust_gamma($image, $gamma) {
    $gamma_value = floatval($gamma);
    if ($gamma_value < 0.1) $gamma_value = 0.1;
    if ($gamma_value > 3.0) $gamma_value = 3.0;
    
    return imagegammacorrect($image, 1.0, $gamma_value);
}

/**
 * Convertit RGB en HSL
 */
function rgb_to_hsl($r, $g, $b) {
    $r /= 255;
    $g /= 255;
    $b /= 255;
    
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    
    if ($max == $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        
        switch ($max) {
            case $r:
                $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6;
                break;
            case $g:
                $h = (($b - $r) / $d + 2) / 6;
                break;
            case $b:
                $h = (($r - $g) / $d + 4) / 6;
                break;
        }
    }
    
    return array($h * 360, $s, $l);
}

/**
 * Convertit HSL en RGB
 */
function hsl_to_rgb($h, $s, $l) {
    $h /= 360;
    
    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        
        $r = hue_to_rgb($p, $q, $h + 1/3);
        $g = hue_to_rgb($p, $q, $h);
        $b = hue_to_rgb($p, $q, $h - 1/3);
    }
    
    return array(round($r * 255), round($g * 255), round($b * 255));
}

function hue_to_rgb($p, $q, $t) {
    if ($t < 0) $t += 1;
    if ($t > 1) $t -= 1;
    if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
    if ($t < 1/2) return $q;
    if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
    return $p;
}

/**
 * Ajuste la saturation d'une image (-100 à +100)
 */
function adjust_saturation($image, $saturation) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    $saturation_factor = floatval($saturation) / 100.0;
    
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            list($h, $s, $l) = rgb_to_hsl($r, $g, $b);
            
            // Ajuster la saturation
            $s = max(0, min(1, $s + $saturation_factor));
            
            list($r, $g, $b) = hsl_to_rgb($h, $s, $l);
            
            $new_color = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x, $y, $new_color);
        }
    }
    
    return true;
}

/**
 * Convertit une image en bitmap avec seuil
 */
function convert_to_bitmap_threshold($image, $threshold) {
    $width = imagesx($image);
    $height = imagesy($image);
    
    $threshold_value = intval($threshold);
    if ($threshold_value < 0) $threshold_value = 0;
    if ($threshold_value > 255) $threshold_value = 255;
    
    // Créer une nouvelle image en niveaux de gris
    $bitmap = imagecreatetruecolor($width, $height);
    
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Convertir en niveaux de gris
            $gray = round(($r + $g + $b) / 3);
            
            // Appliquer le seuil
            $color = ($gray < $threshold_value) ? 0 : 255;
            
            $new_color = imagecolorallocate($bitmap, $color, $color, $color);
            imagesetpixel($bitmap, $x, $y, $new_color);
        }
    }
    
    return $bitmap;
}

/**
 * Convertit une image en bitmap avec dithering Floyd-Steinberg
 * Utilise Imagick si disponible pour les performances, sinon fallback sur PHP (lent)
 */
function convert_to_bitmap_dithering($image) {
    $width = imagesx($image);
    $height = imagesy($image);
    
        try {
            $tmp_in = resolveTempDir() . DIRECTORY_SEPARATOR . 'dither_in_' . uniqid() . '.png';
            $tmp_out = resolveTempDir() . DIRECTORY_SEPARATOR . 'dither_out_' . uniqid() . '.png';
            imagepng($image, $tmp_in);
            
            // Floyd-Steinberg dithering via CLI
            $magick_args = escapeshellarg($tmp_in) . " -colorspace gray -dither FloydSteinberg -colors 2 " . escapeshellarg($tmp_out);
            run_imagemagick($magick_args);
            
            if (file_exists($tmp_out)) {
                $new_image = imagecreatefrompng($tmp_out);
                @unlink($tmp_in);
                @unlink($tmp_out);
                if ($new_image !== false) return $new_image;
            }
            @unlink($tmp_in);
        } catch (Exception $e) {
            error_log("Erreur Magick CLI dithering: " . $e->getMessage());
        }

    // 2. Essayer d'utiliser Imagick (Extension PHP)
    if (extension_loaded('imagick')) {
        try {
            // Sauvegarder l'image GD dans un buffer pour la passer à Imagick
            ob_start();
            imagepng($image);
            $image_data = ob_get_contents();
            ob_end_clean();
            
            $imagick = new \Imagick();
            $imagick->readImageBlob($image_data);
            
            // Convertir en niveaux de gris
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
            
            // Appliquer le dithering Floyd-Steinberg
            // quantizeImage(nombre_couleurs, colorspace, treedepth, dither, measure_error)
            $imagick->quantizeImage(2, \Imagick::COLORSPACE_GRAY, 0, true, false);
            
            // Récupérer l'image traitée
            $imagick->setImageFormat('png');
            $processed_data = $imagick->getImageBlob();
            $imagick->clear();
            $imagick->destroy();
            
            $new_image = imagecreatefromstring($processed_data);
            if ($new_image !== false) {
                return $new_image;
            }
        } catch (Exception $e) {
            error_log("Erreur Imagick dithering: " . $e->getMessage() . " - Fallback sur PHP");
        }
    }
    
    // 2. Fallback : Algorithme PHP (Lent pour les grandes images)
    
    // Pour les très grandes images, limiter la taille ou utiliser un algorithme plus simple
    $max_dimension = 2000; // Limite pour éviter les timeouts en PHP pur (A4 300DPI = ~3500px)
    if ($width > $max_dimension || $height > $max_dimension) {
        error_log("Image trop grande pour dithering PHP ($width x $height) et Imagick non disponible/échoué, bascule sur seuil simple");
        return convert_to_bitmap_threshold($image, 128);
    }
    
    // Créer une copie en niveaux de gris
    $gray = imagecreatetruecolor($width, $height);
    imagecopy($gray, $image, 0, 0, 0, 0, $width, $height);
    imagefilter($gray, IMG_FILTER_GRAYSCALE);
    
    // Créer l'image bitmap finale (on va travailler directement dessus)
    $bitmap = imagecreatetruecolor($width, $height);
    
    // Créer un tableau 2D pour stocker les valeurs de gris avec erreurs
    // On va traiter toute l'image mais de manière optimisée
    $pixels = array();
    for ($y = 0; $y < $height; $y++) {
        $pixels[$y] = array();
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($gray, $x, $y);
            $pixels[$y][$x] = ($rgb >> 16) & 0xFF;
        }
    }
    
    // Appliquer le dithering Floyd-Steinberg
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $old_pixel = $pixels[$y][$x];
            $new_pixel = ($old_pixel < 128) ? 0 : 255;
            $error = $old_pixel - $new_pixel;
            
            // Appliquer le nouveau pixel
            $pixels[$y][$x] = $new_pixel;
            
            // Diffuser l'erreur (Floyd-Steinberg)
            if ($x + 1 < $width) {
                $pixels[$y][$x + 1] = max(0, min(255, $pixels[$y][$x + 1] + $error * 7 / 16));
            }
            if ($x - 1 >= 0 && $y + 1 < $height) {
                $pixels[$y + 1][$x - 1] = max(0, min(255, $pixels[$y + 1][$x - 1] + $error * 3 / 16));
            }
            if ($y + 1 < $height) {
                $pixels[$y + 1][$x] = max(0, min(255, $pixels[$y + 1][$x] + $error * 5 / 16));
            }
            if ($x + 1 < $width && $y + 1 < $height) {
                $pixels[$y + 1][$x + 1] = max(0, min(255, $pixels[$y + 1][$x + 1] + $error * 1 / 16));
            }
        }
    }
    
    // Appliquer les valeurs finales à l'image
    // Les pixels qui ont reçu de l'erreur peuvent avoir des valeurs entre 0 et 255
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $val = max(0, min(255, round($pixels[$y][$x])));
            $color = imagecolorallocate($bitmap, $val, $val, $val);
            imagesetpixel($bitmap, $x, $y, $color);
        }
    }
    
    imagedestroy($gray);
    
    // Retourner l'image avec le dithering appliqué
    // Les pixels peuvent avoir des valeurs entre 0 et 255 pour créer l'effet de tramage
    return $bitmap;
}

/**
 * Traite une image avec tous les ajustements
 */
function process_image($image_path, $output_path, $params) {
    try {
        // Charger l'image
        $image = load_image($image_path);
        $image_info = getimagesize($image_path);
        $mime_type = $image_info['mime'];
        
        // Appliquer les ajustements dans l'ordre
        if (isset($params['contrast']) && $params['contrast'] != 0) {
            adjust_contrast($image, $params['contrast']);
        }
        
        if (isset($params['brightness']) && $params['brightness'] != 0) {
            adjust_brightness($image, $params['brightness']);
        }
        
        if (isset($params['gamma']) && $params['gamma'] != 1.0) {
            adjust_gamma($image, $params['gamma']);
        }
        
        if (isset($params['saturation']) && $params['saturation'] != 0) {
            adjust_saturation($image, $params['saturation']);
        }
        
        // Conversion bitmap si demandée
        if (isset($params['bitmap_enabled']) && $params['bitmap_enabled']) {
            if (isset($params['bitmap_method']) && $params['bitmap_method'] === 'dithering') {
                $bitmap = convert_to_bitmap_dithering($image);
                imagedestroy($image);
                $image = $bitmap;
            } else {
                $threshold = isset($params['bitmap_threshold']) ? $params['bitmap_threshold'] : 128;
                $bitmap = convert_to_bitmap_threshold($image, $threshold);
                imagedestroy($image);
                $image = $bitmap;
            }
        }
        
        // Sauvegarder l'image traitée
        save_image($image, $output_path, $mime_type);
        imagedestroy($image);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur lors du traitement de l'image : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Fonction principale Action
 */
if (!function_exists('Action')) {
function Action($conf) {
    $errors = array();
    $success = false;
    $result = array();
    $download_url = '';
    $is_pdf = false;
    $progress_key = '';
    $from_lib_file = null;
    
    // Gestion de la pré-sélection bibliothèque (GET)
    if (isset($_GET['from_lib'])) {
        require_once __DIR__ . '/BibliothequeManager.php';
        $libManager = new BibliothequeManager();
        $file = $libManager->getFile($_GET['from_lib']);
        if ($file && in_array($file['file_type'], ['pdf', 'png']) && file_exists($file['filepath'])) {
            $from_lib_file = $file;
        }
    }
    
    // Gestion de la progression (pour modal) - AVANT les timeouts pour éviter les blocages
    if (isset($_GET['progress_key'])) {
        // Fermer la session pour éviter le verrouillage pendant le polling
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $progress_key = $_GET['progress_key'];
        $progress_file = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor_progress_' . $progress_key . '.json';
        
        // Cette requête doit être rapide, pas de timeout
        set_time_limit(5); // Maximum 5 secondes pour lire un fichier
        
        if (file_exists($progress_file)) {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            echo file_get_contents($progress_file);
            exit;
        } else {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, must-revalidate');
            echo json_encode(array('status' => 'not_found'));
            exit;
        }
    }
    
    // Augmenter les timeouts UNIQUEMENT pour les requêtes POST (traitement)
    if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
        set_time_limit(600); // 10 minutes
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '512M');
    }
    
    try {
        
        if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
            
            // Vérifier si on utilise une image déjà traitée depuis le canvas
            $use_canvas = isset($_POST['use_canvas']) && $_POST['use_canvas'] === '1';
            
            if ($use_canvas && isset($_FILES["processed_image"])) {
                // Cas : image déjà traitée depuis le canvas
                try {
                    // Vérifier le type MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES["processed_image"]["tmp_name"]);
                    finfo_close($finfo);
                    
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    
                    if (!in_array($mimeType, $allowed_types)) {
                        $errors[] = "Format d'image non supporté. Type détecté: " . $mimeType;
                    } elseif ($_FILES["processed_image"]["size"] == 0) {
                        $errors[] = "Le fichier est vide.";
                    } elseif ($_FILES["processed_image"]["size"] > 50 * 1024 * 1024) {
                        $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
                    } else {
                        // Créer le dossier temporaire
                        $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR;
                        if (!is_dir($tmpDir)) {
                            if (!mkdir($tmpDir, 0777, true)) {
                                throw new Exception("Impossible de créer le dossier temporaire.");
                            }
                            @chmod($tmpDir, 0777);
                        }
                        
                        $timestamp = date('YmdHis');
                        $originalName = isset($_POST['original_filename']) ? pathinfo($_POST['original_filename'], PATHINFO_FILENAME) : 'processed';
                        $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                        $extension = pathinfo($_POST['original_filename'] ?? 'image.png', PATHINFO_EXTENSION);
                        if (empty($extension)) {
                            // Déterminer l'extension depuis le MIME type
                            $extension_map = [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/gif' => 'gif',
                                'image/webp' => 'webp'
                            ];
                            $extension = $extension_map[$mimeType] ?? 'png';
                        }
                        
                        // Créer un sous-dossier pour l'image finale
                        $outputSubDir = $tmpDir . 'image_processor_' . $timestamp . DIRECTORY_SEPARATOR;
                        if (!is_dir($outputSubDir)) {
                            mkdir($outputSubDir, 0777, true);
                        }
                        
                        $outputFile = $outputSubDir . $safe_filename . "_processed." . $extension;
                        
                        if (move_uploaded_file($_FILES["processed_image"]["tmp_name"], $outputFile)) {
                            $success = true;
                            $result['filename'] = $safe_filename . "_processed." . $extension;
                            
                            // Encoder en base64 pour l'affichage
                            $imageData = base64_encode(file_get_contents($outputFile));
                            $result['preview_url'] = 'data:' . $mimeType . ';base64,' . $imageData;
                            
                            // URL de téléchargement
                            $download_url = "?download_processed&file=" . urlencode(basename($outputFile)) . "&dir=image_processor_" . $timestamp;
                            $result['download_url'] = $download_url;
                            $result['is_pdf'] = false;
                        } else {
                            $errors[] = "Erreur lors de l'enregistrement de l'image traitée.";
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erreur lors du traitement de l'image canvas : " . $e->getMessage());
                    $errors[] = "Erreur lors du traitement : " . $e->getMessage();
                }
            } else {
                // Cas classique : traitement serveur
                // Récupérer les paramètres de traitement
                $contrast = isset($_POST['contrast']) ? floatval($_POST['contrast']) : 0;
                $brightness = isset($_POST['brightness']) ? floatval($_POST['brightness']) : 0;
                $gamma = isset($_POST['gamma']) ? floatval($_POST['gamma']) : 1.0;
                $saturation = isset($_POST['saturation']) ? floatval($_POST['saturation']) : 0;
                $bitmap_enabled = isset($_POST['bitmap_enabled']) && $_POST['bitmap_enabled'] === '1';
                $bitmap_method = isset($_POST['bitmap_method']) ? $_POST['bitmap_method'] : 'threshold';
                $bitmap_threshold = isset($_POST['bitmap_threshold']) ? intval($_POST['bitmap_threshold']) : 128;
                
                $params = array(
                    'contrast' => $contrast,
                    'brightness' => $brightness,
                    'gamma' => $gamma,
                    'saturation' => $saturation,
                    'bitmap_enabled' => $bitmap_enabled,
                    'bitmap_method' => $bitmap_method,
                    'bitmap_threshold' => $bitmap_threshold
                );
                
                // Cas 1 : Fichier bibliothèque (POST avec lib_file_id)
                if (isset($_POST['lib_file_id']) && !empty($_POST['lib_file_id'])) {
                    require_once __DIR__ . '/BibliothequeManager.php';
                    $libManager = new BibliothequeManager();
                    $file = $libManager->getFile($_POST['lib_file_id']);
                    if ($file && in_array($file['file_type'], ['pdf', 'png']) && file_exists($file['filepath'])) {
                        $from_lib_file = $file;
                    } else {
                        $errors[] = "Fichier bibliothèque non trouvé ou invalide.";
                    }
                }
                
                // Si on a un fichier bibliothèque, créer un $_FILES simulé
                if ($from_lib_file) {
                    // Créer un fichier temporaire copié depuis la bibliothèque
                    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR;
                    if (!is_dir($tmpDir)) {
                        if (!mkdir($tmpDir, 0777, true)) {
                            throw new Exception("Impossible de créer le dossier temporaire.");
                        }
                        @chmod($tmpDir, 0777);
                    }
                    
                    $timestamp = date('YmdHis');
                    $originalName = $from_lib_file['filename'];
                    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                    $extension = $from_lib_file['file_type'];
                    $uploadFile = $tmpDir . "lib_file_" . $timestamp . "." . $extension;
                    
                    // Copier le fichier bibliothèque vers le dossier temporaire
                    if (copy($from_lib_file['filepath'], $uploadFile)) {
                        // Créer un $_FILES simulé
                        $_FILES["file"] = array(
                            "name" => $originalName,
                            "type" => $from_lib_file['file_type'] === 'pdf' ? 'application/pdf' : 'image/png',
                            "tmp_name" => $uploadFile,
                            "error" => UPLOAD_ERR_OK,
                            "size" => filesize($uploadFile)
                        );
                    } else {
                        $errors[] = "Erreur lors de la copie du fichier bibliothèque.";
                    }
                }
                
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
                $error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : "Erreur inconnue ($error_code)";
                $errors[] = "Erreur d'upload : " . $error_msg;
                } else {
                    // Vérifier le type MIME
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $_FILES["file"]["tmp_name"]);
                    finfo_close($finfo);
                    
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    
                    if (!in_array($mimeType, $allowed_types)) {
                        $errors[] = "Le fichier doit être un PDF ou une image (JPEG, PNG, GIF). Type détecté: " . $mimeType;
                    } elseif ($_FILES["file"]["size"] == 0) {
                        $errors[] = "Le fichier est vide.";
                    } elseif ($_FILES["file"]["size"] > 50 * 1024 * 1024) {
                        $errors[] = "Le fichier est trop volumineux (maximum 50MB).";
                    } else {
                    // Créer le dossier temporaire
                    $tmpDir = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor' . DIRECTORY_SEPARATOR;
                    if (!is_dir($tmpDir)) {
                        if (!mkdir($tmpDir, 0777, true)) {
                            throw new Exception("Impossible de créer le dossier temporaire.");
                        }
                        @chmod($tmpDir, 0777);
                    }
                    
                    $timestamp = date('YmdHis');
                    $extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
                    $originalName = pathinfo($_FILES["file"]["name"], PATHINFO_FILENAME);
                    $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                    
                    // Créer une clé de progression
                    $progress_key = uniqid('proc_', true);
                    $progress_file = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_image_processor_progress_' . $progress_key . '.json';
                    
                    // Initialiser la progression
                    file_put_contents($progress_file, json_encode(array(
                        'status' => 'processing',
                        'current' => 0,
                        'total' => 100,
                        'message' => 'Démarrage du traitement...'
                    )));
                    
                    $uploadFile = $tmpDir . "upload_" . $timestamp . "." . $extension;
                    
                    // Pour les fichiers de la bibliothèque, utiliser rename au lieu de move_uploaded_file
                    // car move_uploaded_file() ne fonctionne que pour les fichiers uploadés via HTTP POST
                    $move_result = false;
                    if (isset($from_lib_file) && $from_lib_file) {
                        // Fichier de la bibliothèque : déjà copié, juste renommer/déplacer
                        if (file_exists($_FILES["file"]["tmp_name"])) {
                            $move_result = rename($_FILES["file"]["tmp_name"], $uploadFile);
                        } else {
                            $move_result = false;
                        }
                    } else {
                        // Fichier uploadé normalement : utiliser move_uploaded_file
                        $move_result = move_uploaded_file($_FILES["file"]["tmp_name"], $uploadFile);
                    }
                    
                    if ($move_result) {
                        
                        // Préparer la réponse immédiate pour traitement asynchrone
                        $result['progress_key'] = $progress_key;
                        $result['status'] = 'processing';
                        $result['message'] = 'Traitement démarré...';
                        
                        // Fermer la session pour éviter le verrouillage pendant le traitement en arrière-plan
                        if (session_status() === PHP_SESSION_ACTIVE) {
                            session_write_close();
                        }
                        
                        // Envoyer la réponse HTTP immédiatement et continuer en arrière-plan
                        header('Content-Type: application/json');
                        echo json_encode($result);
                        
                        // Forcer l'envoi de la réponse
                        if (function_exists('fastcgi_finish_request')) {
                            fastcgi_finish_request();
                        } else {
                            // Fallback : forcer l'envoi avec flush et continuer en arrière-plan
                            if (ob_get_level()) {
                                ob_end_flush();
                            }
                            flush();
                            ignore_user_abort(true);
                            set_time_limit(600);
                        }
                        
                        if ($mimeType === 'application/pdf') {
                            // Traitement PDF
                            $is_pdf = true;
                            
                            // Convertir PDF en images (préserve les dimensions exactes)
                            $outputDir = $tmpDir . 'pdf_images_' . $timestamp . DIRECTORY_SEPARATOR;
                            
                            // Mettre à jour la progression : Conversion PDF
                            file_put_contents($progress_file, json_encode(array(
                                'status' => 'processing',
                                'current' => 0,
                                'total' => 100,
                                'message' => 'Conversion du PDF en images...'
                            )));
                            
                            $pdf_conversion_result = convert_pdf_to_images_preserve_size($uploadFile, $outputDir, $safe_filename);
                            $image_files = $pdf_conversion_result['files'];
                            $page_dimensions = $pdf_conversion_result['page_dimensions'];
                            
                            $total_pages = count($image_files);
                            
                            // Calculer la progression réelle : chaque page = 100 / total_pages
                            // Répartition : Conversion (5%), Pages (90%), Reconstitution (5%)
                            $percent_per_page = 90 / max(1, $total_pages); // 90% répartis sur les pages
                            $conversion_percent = 5; // 5% pour la conversion
                            $reconstitution_percent = 5; // 5% pour la reconstitution
                            
                            // Conversion terminée
                            file_put_contents($progress_file, json_encode(array(
                                'status' => 'processing',
                                'current' => $conversion_percent,
                                'total' => 100,
                                'message' => "Conversion terminée. Traitement de $total_pages page(s)..."
                            )));
                            
                            // Traiter chaque image
                            $processed_images = array();
                            foreach ($image_files as $index => $image_file) {
                                $page_num = $index + 1;
                                
                                // Calculer le pourcentage : conversion (5%) + pages traitées
                                $current_percent = $conversion_percent + ($page_num * $percent_per_page);
                                
                                // Mettre à jour la progression
                                file_put_contents($progress_file, json_encode(array(
                                    'status' => 'processing',
                                    'current' => min(95, round($current_percent)), // Max 95% avant reconstitution
                                    'total' => 100,
                                    'message' => "Traitement page $page_num/$total_pages..."
                                )));
                                
                                $processed_image = $outputDir . 'processed_' . basename($image_file);
                                process_image($image_file, $processed_image, $params);
                                $processed_images[] = $processed_image;
                            }
                            
                            // Reconstitution
                            file_put_contents($progress_file, json_encode(array(
                                'status' => 'processing',
                                'current' => 95,
                                'total' => 100,
                                'message' => 'Reconstitution du PDF...'
                            )));
                            
                            // Créer un sous-dossier pour le PDF final
                            $outputSubDir = $tmpDir . 'image_processor_' . $timestamp . DIRECTORY_SEPARATOR;
                            if (!is_dir($outputSubDir)) {
                                mkdir($outputSubDir, 0777, true);
                            }
                            
                            // Reconstituer le PDF avec les dimensions exactes et la même résolution
                            $outputFile = $outputSubDir . $safe_filename . "_processed.pdf";
                            convert_images_to_pdf_preserve_size($processed_images, $outputFile, $page_dimensions, $pdf_conversion_result['dpi']);
                            
                            if (file_exists($outputFile)) {
                                $success = true;
                                $result['filename'] = $safe_filename . "_processed.pdf";
                                $download_url = "?download_pdf&file=" . urlencode(basename($outputFile)) . "&dir=image_processor_" . $timestamp;
                                
                                // Nettoyer les images temporaires
                                foreach ($processed_images as $img) {
                                    if (file_exists($img)) unlink($img);
                                }
                                foreach ($image_files as $img) {
                                    if (file_exists($img)) unlink($img);
                                }
                                if (is_dir($outputDir)) rmdir($outputDir);
                            } else {
                                $errors[] = "Erreur lors de la génération du PDF.";
                            }
                            
                        } else {
                            // Traitement image
                            
                            // Préparer la réponse immédiate pour traitement asynchrone
                            $result['progress_key'] = $progress_key;
                            $result['status'] = 'processing';
                            $result['message'] = 'Traitement démarré...';
                            
                            // Envoyer la réponse HTTP immédiatement et continuer en arrière-plan
                            header('Content-Type: application/json');
                            echo json_encode($result);
                            
                            // Forcer l'envoi de la réponse
                            if (function_exists('fastcgi_finish_request')) {
                                fastcgi_finish_request();
                            } else {
                                // Fallback : forcer l'envoi avec flush et continuer en arrière-plan
                                if (ob_get_level()) {
                                    ob_end_flush();
                                }
                                flush();
                                ignore_user_abort(true);
                                set_time_limit(600);
                            }
                            
                            // Mettre à jour la progression
                            file_put_contents($progress_file, json_encode(array(
                                'status' => 'processing',
                                'current' => 50,
                                'total' => 100,
                                'message' => 'Traitement de l\'image...'
                            )));
                            
                            // Créer un sous-dossier pour l'image finale
                            $outputSubDir = $tmpDir . 'image_processor_' . $timestamp . DIRECTORY_SEPARATOR;
                            if (!is_dir($outputSubDir)) {
                                mkdir($outputSubDir, 0777, true);
                            }
                            
                            $outputFile = $outputSubDir . $safe_filename . "_processed." . $extension;
                            process_image($uploadFile, $outputFile, $params);
                            
                            // Mettre à jour la progression
                            file_put_contents($progress_file, json_encode(array(
                                'status' => 'processing',
                                'current' => 90,
                                'total' => 100,
                                'message' => 'Finalisation...'
                            )));
                            
                            if (file_exists($outputFile)) {
                                $success = true;
                                $result['filename'] = $safe_filename . "_processed." . $extension;
                                
                                // Encoder en base64 pour l'affichage
                                $imageData = base64_encode(file_get_contents($outputFile));
                                $mime = mime_content_type($outputFile);
                                $result['preview_url'] = 'data:' . $mime . ';base64,' . $imageData;
                                
                                // URL de téléchargement
                                $download_url = "?download_processed&file=" . urlencode(basename($outputFile)) . "&dir=image_processor_" . $timestamp;
                            } else {
                                $errors[] = "Erreur lors du traitement de l'image.";
                            }
                        }
                        
                        // Nettoyer le fichier uploadé
                        if (file_exists($uploadFile)) {
                            unlink($uploadFile);
                        }
                        
                        // Finaliser la progression
                        file_put_contents($progress_file, json_encode(array(
                            'status' => 'completed',
                            'current' => 100,
                            'total' => 100,
                            'message' => 'Traitement terminé avec succès !',
                            'download_url' => $download_url,
                            'is_pdf' => $is_pdf,
                            'filename' => isset($result['filename']) ? $result['filename'] : ''
                        )));
                        
                        // Si fastcgi_finish_request n'a pas été appelé, on met à jour le résultat
                        if (!function_exists('fastcgi_finish_request') || !fastcgi_finish_request()) {
                            $result['progress_key'] = $progress_key;
                            $result['download_url'] = $download_url;
                            $result['is_pdf'] = $is_pdf;
                            $result['filename'] = isset($result['filename']) ? $result['filename'] : '';
                        }
                        
                    } else {
                        $errors[] = "Erreur lors de l'upload du fichier.";
                    }
                }
            }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur dans image_processor Action : " . $e->getMessage());
        error_log("Trace : " . $e->getTraceAsString());
        $errors[] = "Erreur lors du traitement : " . $e->getMessage();
    }
    
    return template("../view/image_processor.html.php", array(
        'errors' => $errors,
        'success' => $success,
        'result' => $result,
        'from_lib_file' => $from_lib_file
    ));
}
}

?>

