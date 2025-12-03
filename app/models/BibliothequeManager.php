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
        
        // Vérifier et créer FTS5 si nécessaire (auto-réparation)
        if (!$this->hasFTS5Support()) {
            try {
                require_once __DIR__ . '/migrations/DatabaseMigrationManager.php';
                // Pour inclure DatabaseMigrationManager, on a besoin de conf
                global $conf;
                $migrationManager = new DatabaseMigrationManager($conf);
                // On ne peut pas appeler createBibliothequeFTS directement car elle est privée
                // Mais on peut l'appeler via runMigrations si on l'a ajoutée à la liste
                // Ou réimplémenter la logique ici pour être sûr
                $this->createFTS5Table();
            } catch (Exception $e) {
                error_log("Erreur création auto FTS5: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Créer la table FTS5 si elle n'existe pas (méthode de secours)
     */
    private function createFTS5Table() {
        try {
            // Créer la table virtuelle FTS5
            $this->db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS bibliotheque_files_fts USING fts5(
                filename,
                extracted_text,
                content='bibliotheque_files',
                content_rowid='id'
            )");
            
            // Triggers
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ai AFTER INSERT ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
                VALUES (new.id, new.filename, new.extracted_text);
            END");
            
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ad AFTER DELETE ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, extracted_text) 
                VALUES('delete', old.id, old.filename, old.extracted_text);
            END");
            
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_au AFTER UPDATE ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, extracted_text) 
                VALUES('delete', old.id, old.filename, old.extracted_text);
                INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
                VALUES (new.id, new.filename, new.extracted_text);
            END");
            
            // Remplir si vide
            $count = $this->db->query("SELECT COUNT(*) FROM bibliotheque_files_fts")->fetchColumn();
            if ($count == 0) {
                $this->db->exec("INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
                    SELECT id, filename, extracted_text FROM bibliotheque_files");
            }
        } catch (Exception $e) {
            error_log("Erreur createFTS5Table: " . $e->getMessage());
        }
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
            // Augmenter la limite de mémoire pour l'indexation (1 GB pour gérer les gros PDFs)
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '1024M');
            
            // 1. Générer la miniature
            $thumbnailPath = $this->generateThumbnail($path, $type);
            // Si null, continuer sans thumbnail (ne pas bloquer l'upload)
            
            // 2. Extraire les infos (pages, texte)
            $pageCount = 0;
            $extractedText = '';
            
            if ($type === 'pdf') {
                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($path);
                    $pages = $pdf->getPages();
                    $pageCount = count($pages);
                    
                    // Extraire le texte de TOUTES les pages
                    // Traitement par batch pour éviter de tout charger en mémoire d'un coup
                    try {
                        $textParts = [];
                        $maxTextLength = 500000; // Limite de 500KB de texte pour la recherche
                        $currentLength = 0;
                        
                        // Essayer d'abord la méthode rapide (getText() sur tout le PDF)
                        try {
                            $extractedText = $pdf->getText();
                            // Si le texte est trop long, on le tronque mais on garde le maximum
                            if (strlen($extractedText) > $maxTextLength) {
                                $extractedText = substr($extractedText, 0, $maxTextLength) . '...';
                            }
                        } catch (Exception $e) {
                            // Si getText() échoue, extraire page par page
                            error_log("getText() échoué, extraction page par page pour $path: " . $e->getMessage());
                            
                            // Traiter les pages par batch de 50 pour libérer la mémoire
                            $batchSize = 50;
                            for ($i = 0; $i < $pageCount; $i++) {
                                try {
                                    $pageText = $pages[$i]->getText();
                                    $textParts[] = $pageText;
                                    $currentLength += strlen($pageText);
                                    
                                    // Libérer la mémoire périodiquement
                                    if (($i + 1) % $batchSize === 0) {
                                        if (function_exists('gc_collect_cycles')) {
                                            gc_collect_cycles();
                                        }
                                    }
                                    
                                    // Arrêter si on atteint la limite de texte
                                    if ($currentLength >= $maxTextLength) {
                                        break;
                                    }
                                } catch (Exception $pageError) {
                                    // Continuer avec la page suivante si une page échoue
                                    error_log("Erreur extraction page $i du PDF $path: " . $pageError->getMessage());
                                    continue;
                                }
                            }
                            
                            $extractedText = implode(' ', $textParts);
                            // Tronquer si nécessaire
                            if (strlen($extractedText) > $maxTextLength) {
                                $extractedText = substr($extractedText, 0, $maxTextLength) . '...';
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Erreur extraction texte PDF $path: " . $e->getMessage());
                        $extractedText = '';
                    }
                    
                    // Libérer la mémoire explicitement
                    unset($pdf);
                    unset($pages);
                    unset($parser);
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                } catch (\Error $e) {
                    // Gérer les erreurs fatales (mémoire, etc.)
                    error_log("Erreur fatale extraction PDF $path: " . $e->getMessage());
                    $extractedText = '';
                    // Essayer quand même de compter les pages avec une méthode alternative
                    try {
                        // Utiliser pdftk ou pdfinfo si disponible, sinon on garde pageCount = 0
                        $pageCount = 0;
                    } catch (Exception $e2) {
                        // Ignorer
                    }
                } catch (Exception $e) {
                    error_log("Erreur extraction texte PDF $path: " . $e->getMessage());
                    $extractedText = '';
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
            
            // Restaurer la limite de mémoire originale
            ini_set('memory_limit', $originalMemoryLimit);
            
            // Forcer le garbage collection pour libérer la mémoire
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            return [
                'status' => 'success',
                'id' => $this->db->lastInsertId(),
                'filename' => $originalName
            ];
            
        } catch (\Error $e) {
            // Gérer les erreurs fatales (mémoire, etc.)
            error_log("Erreur fatale lors de l'enregistrement du fichier $path: " . $e->getMessage());
            // Restaurer la limite de mémoire
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            throw new Exception("Erreur lors de l'indexation du fichier (mémoire insuffisante ou fichier corrompu): " . basename($path));
        } catch (Exception $e) {
            error_log("Erreur lors de l'enregistrement du fichier $path: " . $e->getMessage());
            // Restaurer la limite de mémoire
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
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
        
        // S'assurer que le dossier existe
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir)) {
            @mkdir($thumbDir, 0777, true);
        }
        
        $success = false;
        if ($type === 'pdf') {
            $success = $this->generatePdfThumbnail($path, $thumbPath);
        } else {
            $success = $this->generatePngThumbnail($path, $thumbPath);
        }
        
        // Vérifier que le fichier a bien été créé
        if (!$success || !file_exists($thumbPath)) {
            return null;
        }
        
        return $relativePath;
    }
    
    private function generatePdfThumbnail($pdfPath, $outPath) {
        // Détection Ghostscript (EXACTEMENT comme dans pdf_to_png.php)
        $gs_command = 'gs';
        if (PHP_OS_FAMILY === 'Windows') {
            $gs_command = __DIR__ . '/../ghostscript/gswin64c.exe';
            if (!file_exists($gs_command)) {
                return false;
            }
        }
        
        // Commande EXACTEMENT comme dans pdf_to_png.php (concaténation, pas interpolation)
        $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=png16m -dFirstPage=1 -dLastPage=1 -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($outPath) . " " . escapeshellarg($pdfPath) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($outPath)) {
            error_log("Erreur génération miniature PDF (code=$returnVar): " . implode("\n", $output));
            return false;
        }
        
        // Redimensionner l'image générée à 200px max
        $this->resizeImage($outPath, 200, 200);
        return true;
    }
    
    private function generatePngThumbnail($pngPath, $outPath) {
        if (!copy($pngPath, $outPath)) {
            return false;
        }
        $this->resizeImage($outPath, 200, 200);
        return true;
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
    
    /**
     * Vérifier si FTS5 est disponible
     */
    private function hasFTS5Support() {
        try {
            $checkQuery = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bibliotheque_files_fts'");
            return $checkQuery->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Préparer la requête FTS5 avec comportement intelligent
     * AND pour 2-3 mots, OR pour 4+ mots
     */
    private function prepareFTSQuery($search) {
        // Découper la recherche en mots
        $words = preg_split('/\s+/', trim($search));
        // Filtrer les mots de moins de 2 caractères
        $words = array_filter($words, function($w) { return strlen($w) >= 2; });
        
        if (empty($words)) {
            return '';
        }
        
        // Comportement intelligent : AND pour 2-3 mots, OR pour 4+ mots
        $wordCount = count($words);
        if ($wordCount >= 2 && $wordCount <= 3) {
            // Tous les mots requis
            return implode(' AND ', $words);
        } else {
            // Au moins un mot
            return implode(' OR ', $words);
        }
    }
    
    /**
     * Extraire les contextes de recherche (phrases trouvées) dans le texte
     * @param string $text Le texte dans lequel chercher
     * @param string $search La recherche (peut contenir plusieurs mots)
     * @return array Tableau de contextes formatés avec balises <mark>
     */
    private function extractMatchContexts($text, $search) {
        try {
            if (empty($text) || empty($search)) {
                return [];
            }
            
            // S'assurer que le texte est en UTF-8 valide
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
            
            // Découper la recherche en mots (filtrer < 2 caractères)
            $words = preg_split('/\s+/', trim($search));
            $words = array_filter($words, function($w) { return strlen($w) >= 2; });
            
            if (empty($words)) {
                return [];
            }
            
            $contexts = [];
            $contextLength = 100; // ~100 caractères avant et après
            $maxContexts = 3; // Limiter à 3 contextes maximum
            
            // Pour chaque mot, trouver toutes les occurrences
            foreach ($words as $word) {
                if (count($contexts) >= $maxContexts) {
                    break;
                }
                
                // S'assurer que le mot est en UTF-8 valide
                if (!mb_check_encoding($word, 'UTF-8')) {
                    $word = mb_convert_encoding($word, 'UTF-8', 'UTF-8');
                }
                
                $wordLower = mb_strtolower($word, 'UTF-8');
                $textLower = mb_strtolower($text, 'UTF-8');
                $wordLength = mb_strlen($word, 'UTF-8');
                
                // Trouver toutes les occurrences du mot (insensible à la casse)
                $offset = 0;
                $maxIterations = 100; // Limiter les itérations pour éviter les boucles infinies
                $iterationCount = 0;
                
                while ($iterationCount < $maxIterations && ($pos = mb_strpos($textLower, $wordLower, $offset, 'UTF-8')) !== false && count($contexts) < $maxContexts) {
                    $iterationCount++;
                    
                    // Extraire ~100 caractères avant et après
                    $start = max(0, $pos - $contextLength);
                    $end = min(mb_strlen($text, 'UTF-8'), $pos + $wordLength + $contextLength);
                    
                    $context = mb_substr($text, $start, $end - $start, 'UTF-8');
                    
                    // Nettoyer : normaliser les espaces
                    $context = preg_replace('/\s+/', ' ', $context);
                    
                    // Tronquer intelligemment aux limites de phrase si possible
                    if ($start > 0) {
                        // Chercher le début de phrase le plus proche
                        $textBefore = mb_substr($text, 0, $pos, 'UTF-8');
                        $sentenceStart = mb_strrpos($textBefore, '. ', 0, 'UTF-8');
                        if ($sentenceStart !== false && $sentenceStart > $start - 50) {
                            $start = $sentenceStart + 2; // Après ". "
                            $context = mb_substr($text, $start, $end - $start, 'UTF-8');
                            $context = preg_replace('/\s+/', ' ', $context);
                        } else {
                            // Sinon, tronquer au début si nécessaire
                            if ($start > 0) {
                                $context = '...' . ltrim($context);
                            }
                        }
                    }
                    
                    if ($end < mb_strlen($text, 'UTF-8')) {
                        // Chercher la fin de phrase la plus proche
                        $sentenceEnd = mb_strpos($text, '. ', $pos, 'UTF-8');
                        if ($sentenceEnd !== false && $sentenceEnd < $end + 50) {
                            $end = $sentenceEnd + 1;
                            $context = mb_substr($text, $start, $end - $start, 'UTF-8');
                            $context = preg_replace('/\s+/', ' ', $context);
                        } else {
                            // Sinon, tronquer à la fin si nécessaire
                            $context = rtrim($context) . '...';
                        }
                    }
                    
                    // Mettre en évidence le mot trouvé (avec balises HTML <mark>)
                    // Trouver la position du mot dans le contexte (insensible à la casse)
                    $contextLower = mb_strtolower($context, 'UTF-8');
                    $wordPosInContext = mb_strpos($contextLower, $wordLower, 0, 'UTF-8');
                    if ($wordPosInContext !== false) {
                        $before = mb_substr($context, 0, $wordPosInContext, 'UTF-8');
                        $match = mb_substr($context, $wordPosInContext, $wordLength, 'UTF-8');
                        $after = mb_substr($context, $wordPosInContext + $wordLength, null, 'UTF-8');
                        $context = $before . '<mark>' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</mark>' . $after;
                    }
                    
                    // Éviter les doublons
                    $contextKey = md5($context);
                    if (!isset($contexts[$contextKey])) {
                        $contexts[$contextKey] = trim($context);
                    }
                    
                    $offset = $pos + 1;
                    if ($offset >= mb_strlen($text, 'UTF-8')) {
                        break;
                    }
                }
            }
            
            // Retourner les contextes (limiter à 3)
            return array_slice(array_values($contexts), 0, $maxContexts);
            
        } catch (Exception $e) {
            error_log("Erreur dans extractMatchContexts: " . $e->getMessage());
            return [];
        } catch (Error $e) {
            error_log("Erreur fatale dans extractMatchContexts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recherche avec FTS5 et ranking
     */
    private function getAllFilesWithFTS($search, $type = '') {
        $ftsQuery = $this->prepareFTSQuery($search);
        
        if (empty($ftsQuery)) {
            return [];
        }
        
        $sql = "SELECT b.*, 
                bm25(bibliotheque_files_fts) as rank
                FROM bibliotheque_files b
                JOIN bibliotheque_files_fts ON bibliotheque_files_fts.rowid = b.id
                WHERE bibliotheque_files_fts MATCH ?";
        $params = [$ftsQuery];
        
        if (!empty($type)) {
            $sql .= " AND b.file_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY rank, b.created_at DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Extraire les contextes pour chaque résultat
        foreach ($results as &$file) {
            try {
                $contexts = $this->extractMatchContexts($file['extracted_text'] ?? '', $search);
                $file['match_contexts'] = $contexts;
            } catch (Exception $e) {
                error_log("Erreur extraction contextes pour fichier " . ($file['id'] ?? 'inconnu') . ": " . $e->getMessage());
                $file['match_contexts'] = [];
            } catch (Error $e) {
                error_log("Erreur fatale extraction contextes pour fichier " . ($file['id'] ?? 'inconnu') . ": " . $e->getMessage());
                $file['match_contexts'] = [];
            }
        }
        unset($file);
        
        return $results;
    }
    
    /**
     * Recherche multi-mots avec LIKE (fallback)
     */
    private function getAllFilesWithLike($search, $type = '') {
        // Découper la recherche en mots
        $words = preg_split('/\s+/', trim($search));
        // Filtrer les mots de moins de 2 caractères
        $words = array_filter($words, function($w) { return strlen($w) >= 2; });
        
        if (empty($words)) {
            return [];
        }
        
        // Construire la requête avec AND (tous les mots requis)
        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $conditions[] = "(filename LIKE ? OR extracted_text LIKE ?)";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
        
        $sql = "SELECT * FROM bibliotheque_files WHERE (" . implode(" AND ", $conditions) . ")";
        
        if (!empty($type)) {
            $sql .= " AND file_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Extraire les contextes pour chaque résultat
        foreach ($results as &$file) {
            try {
                $contexts = $this->extractMatchContexts($file['extracted_text'] ?? '', $search);
                $file['match_contexts'] = $contexts;
            } catch (Exception $e) {
                error_log("Erreur extraction contextes pour fichier " . ($file['id'] ?? 'inconnu') . ": " . $e->getMessage());
                $file['match_contexts'] = [];
            } catch (Error $e) {
                error_log("Erreur fatale extraction contextes pour fichier " . ($file['id'] ?? 'inconnu') . ": " . $e->getMessage());
                $file['match_contexts'] = [];
            }
        }
        unset($file);
        
        return $results;
    }
    
    /**
     * Recherche avec correction de fautes de frappe (fuzzy search)
     * Optimisé : limite à 50 fichiers et utilise seulement le nom de fichier
     */
    private function getAllFilesWithFuzzy($search, $type = '') {
        // Seulement si la recherche fait au moins 3 caractères
        if (strlen($search) < 3) {
            return [];
        }
        
        // Limiter à 50 fichiers pour la performance (fuzzy search coûteux)
        // On récupère les 50 fichiers les plus récents pour limiter le calcul Levenshtein
        $sql = "SELECT * FROM bibliotheque_files WHERE 1=1";
        $params = [];
        
        if (!empty($type)) {
            $sql .= " AND file_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $threshold = 2; // Distance de Levenshtein max
        $filtered = [];
        $searchLower = strtolower($search);
        
        // Utiliser seulement le nom de fichier pour gagner en performance
        foreach ($allFiles as $file) {
            $filenameDist = levenshtein($searchLower, strtolower($file['filename']));
            
            // Si le nom de fichier correspond
            if ($filenameDist <= $threshold) {
                $file['relevance_score'] = $filenameDist;
                $filtered[] = $file;
            }
        }
        
        // Trier par score de pertinence
        usort($filtered, function($a, $b) {
            return $a['relevance_score'] <=> $b['relevance_score'];
        });
        
        // Limiter à 50 résultats
        return array_slice($filtered, 0, 50);
    }
    
    /**
     * Recherche hybride : FTS5 → LIKE → Fuzzy
     */
    public function getAllFiles($search = '', $type = '') {
        // Si pas de recherche, retourner tous les fichiers
        if (empty($search)) {
            $sql = "SELECT * FROM bibliotheque_files WHERE 1=1";
            $params = [];
            
            if (!empty($type)) {
                $sql .= " AND file_type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Vérifier si FTS5 est disponible
        if ($this->hasFTS5Support()) {
            // Utiliser FTS5
            $results = $this->getAllFilesWithFTS($search, $type);
            
            // Si aucun résultat, essayer LIKE puis fuzzy search
            if (empty($results)) {
                $results = $this->getAllFilesWithLike($search, $type);
                
                // Si toujours aucun résultat, essayer fuzzy search (limité et optimisé)
                if (empty($results)) {
                    $results = $this->getAllFilesWithFuzzy($search, $type);
                }
            }
        } else {
            // Fallback : utiliser LIKE
            $results = $this->getAllFilesWithLike($search, $type);
            
            // Si aucun résultat, essayer fuzzy search (limité et optimisé)
            if (empty($results)) {
                $results = $this->getAllFilesWithFuzzy($search, $type);
            }
        }
        
        return $results;
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


