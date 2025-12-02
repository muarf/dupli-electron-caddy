<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';

use Smalot\PdfParser\Parser;

class BibliothequeManager {
    private $db;
    private $baseDir;
    private $thumbnailsDir;
    
    public function __construct() {
        $this->db = pdo_connect();
        $this->baseDir = getBibliothequeDir();
        
        // Créer la structure de dossiers si nécessaire
        $this->createDirectoryStructure();
    }
    
    private function createDirectoryStructure() {
        if (!is_dir($this->baseDir)) {
            $mkdir = @mkdir($this->baseDir, 0777, true);
            if (!$mkdir && !is_dir($this->baseDir)) {
                error_log("Impossible de créer le dossier bibliothèque: " . $this->baseDir);
            }
        }
        
        $dirs = [
            'files/pdf',
            'files/png',
            'thumbnails/pdf',
            'thumbnails/png'
        ];
        
        foreach ($dirs as $dir) {
            $path = $this->baseDir . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($path)) {
                $mkdir = @mkdir($path, 0777, true);
                if (!$mkdir && !is_dir($path)) {
                    error_log("Impossible de créer le sous-dossier: " . $path);
                }
            }
        }
        
        $this->thumbnailsDir = $this->baseDir . DIRECTORY_SEPARATOR . 'thumbnails';
    }
    
    /**
     * Ajoute un fichier uploadé à la bibliothèque
     */
    public function addUploadedFile($fileInfo) {
        $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'png'])) {
            throw new Exception("Type de fichier non supporté");
        }
        
        $filename = $fileInfo['name'];
        // Nettoyer le nom de fichier
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Générer un nom unique pour le stockage
        $uniqueName = uniqid() . '_' . $filename;
        $targetSubDir = 'files/' . $ext;
        $targetPath = $this->baseDir . DIRECTORY_SEPARATOR . $targetSubDir . DIRECTORY_SEPARATOR . $uniqueName;
        
        if (!move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
            throw new Exception("Erreur lors du déplacement du fichier");
        }
        
        return $this->registerFile($targetPath, $filename, $ext, false);
    }
    
    /**
     * Ajoute un fichier externe (indexation sans copie)
     */
    public function addExternalFile($path) {
        if (!file_exists($path)) {
            throw new Exception("Le fichier n'existe pas : $path");
        }
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = basename($path);
        
        // Vérifier si le fichier est déjà indexé
        $stmt = $this->db->prepare("SELECT id FROM bibliotheque_files WHERE filepath = ?");
        $stmt->execute([$path]);
        if ($stmt->fetch()) {
            return ['status' => 'exists', 'message' => 'Fichier déjà indexé'];
        }
        
        return $this->registerFile($path, $filename, $ext, true);
    }
    
    /**
     * Enregistre le fichier en base et génère les métadonnées
     */
    private function registerFile($path, $originalName, $type, $isExternal) {
        try {
            // 1. Générer la miniature
            $thumbnailPath = $this->generateThumbnail($path, $type);
            
            // 2. Extraire les infos (pages, texte)
            $pageCount = 0;
            $extractedText = '';
            
            if ($type === 'pdf') {
                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($path);
                    $pages = $pdf->getPages();
                    $pageCount = count($pages);
                    $extractedText = $pdf->getText();
                } catch (Exception $e) {
                    error_log("Erreur extraction texte PDF $path: " . $e->getMessage());
                }
            }
            
            // 3. Enregistrer en base
            $stmt = $this->db->prepare("
                INSERT INTO bibliotheque_files 
                (filename, filepath, file_type, thumbnail_path, file_size, page_count, extracted_text, is_external, source_directory, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
            ");
            
            $sourceDir = $isExternal ? dirname($path) : null;
            
            $stmt->execute([
                $originalName,
                $path,
                $type,
                $thumbnailPath,
                filesize($path),
                $pageCount,
                $extractedText,
                $isExternal ? 1 : 0,
                $sourceDir
            ]);
            
            return [
                'status' => 'success',
                'id' => $this->db->lastInsertId(),
                'filename' => $originalName
            ];
            
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement du fichier $path: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Génère une miniature pour le fichier
     */
    private function generateThumbnail($path, $type) {
        $thumbName = md5($path . filemtime($path)) . '.png';
        $thumbSubDir = 'thumbnails/' . $type;
        $thumbPath = $this->baseDir . DIRECTORY_SEPARATOR . $thumbSubDir . DIRECTORY_SEPARATOR . $thumbName;
        $relativePath = $thumbSubDir . '/' . $thumbName; // Pour stockage en DB
        
        if (file_exists($thumbPath)) {
            return $relativePath;
        }
        
        if ($type === 'pdf') {
            $this->generatePdfThumbnail($path, $thumbPath);
        } else {
            $this->generatePngThumbnail($path, $thumbPath);
        }
        
        return $relativePath;
    }
    
    private function generatePdfThumbnail($pdfPath, $outPath) {
        // Détection Ghostscript (comme dans pdf_to_png.php)
        if (PHP_OS_FAMILY === 'Windows') {
            $gs_command = __DIR__ . '/../ghostscript/gswin64c.exe';
            if (!file_exists($gs_command)) $gs_command = 'gswin64c';
        } else {
            $gs_command = 'gs';
        }
        
        // Commande pour extraire la première page en PNG 200x200 (redimensionné ensuite ou direct)
        // On utilise -r72 pour une basse résolution suffisante pour une miniature
        $command = "$gs_command -dNOPAUSE -dBATCH -sDEVICE=png16m -dFirstPage=1 -dLastPage=1 -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($outPath) . " " . escapeshellarg($pdfPath) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($outPath)) {
            error_log("Erreur génération miniature PDF: " . implode("\n", $output));
            // Créer une image vide ou erreur si échec
            return false;
        }
        
        // Redimensionner l'image générée à 200px max
        $this->resizeImage($outPath, 200, 200);
    }
    
    private function generatePngThumbnail($pngPath, $outPath) {
        if (!copy($pngPath, $outPath)) {
            return false;
        }
        $this->resizeImage($outPath, 200, 200);
    }
    
    private function resizeImage($file, $w, $h) {
        list($width, $height) = getimagesize($file);
        $r = $width / $height;
        
        if ($w/$h > $r) {
            $newwidth = $h*$r;
            $newheight = $h;
        } else {
            $newheight = $w/$r;
            $newwidth = $w;
        }
        
        $src = imagecreatefrompng($file);
        $dst = imagecreatetruecolor($newwidth, $newheight);
        
        // Transparence
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        
        imagepng($dst, $file);
        
        imagedestroy($src);
        imagedestroy($dst);
    }
    
    public function getAllFiles($search = '', $type = '') {
        $sql = "SELECT * FROM bibliotheque_files WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (filename LIKE ? OR extracted_text LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($type)) {
            $sql .= " AND file_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getFile($id) {
        $stmt = $this->db->prepare("SELECT * FROM bibliotheque_files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function deleteFile($id) {
        $file = $this->getFile($id);
        if (!$file) return false;
        
        // Supprimer la miniature
        $thumbPath = $this->baseDir . DIRECTORY_SEPARATOR . $file['thumbnail_path'];
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
        
        // Supprimer le fichier physique SI c'est un fichier interne (is_external = 0)
        if ($file['is_external'] == 0) {
            if (file_exists($file['filepath'])) {
                unlink($file['filepath']);
            }
        }
        
        // Supprimer de la base
        $stmt = $this->db->prepare("DELETE FROM bibliotheque_files WHERE id = ?");
        return $stmt->execute([$id]);
    }
}


