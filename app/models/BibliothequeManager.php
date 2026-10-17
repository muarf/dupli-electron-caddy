<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/SettingsManager.php';

use Smalot\PdfParser\Parser;
use voku\helper\StopWords;

class BibliothequeManager {
    private $db;
    private $baseDir;
    private $thumbnailsDir;

    // Plus besoin de la liste statique manuelle
    
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

        // Initialiser les réglages IA par défaut (idempotent)
        try {
            $settingsManager = new SettingsManager($this->db);
            $settingsManager->initAiSettings();
        } catch (Exception $e) {
            error_log("Erreur initAiSettings: " . $e->getMessage());
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
                tags,
                extracted_text,
                content='bibliotheque_files',
                content_rowid='id'
            )");
            
            // Triggers
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ai AFTER INSERT ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(rowid, filename, tags, extracted_text) 
                VALUES (new.id, new.filename, new.tags, new.extracted_text);
            END");
            
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_ad AFTER DELETE ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, tags, extracted_text) 
                VALUES('delete', old.id, old.filename, old.tags, old.extracted_text);
            END");
            
            $this->db->exec("CREATE TRIGGER IF NOT EXISTS bibliotheque_files_au AFTER UPDATE ON bibliotheque_files BEGIN
                INSERT INTO bibliotheque_files_fts(bibliotheque_files_fts, rowid, filename, tags, extracted_text) 
                VALUES('delete', old.id, old.filename, old.tags, old.extracted_text);
                INSERT INTO bibliotheque_files_fts(rowid, filename, tags, extracted_text) 
                VALUES (new.id, new.filename, new.tags, new.extracted_text);
            END");
            
            // Remplir si vide
            $count = $this->db->query("SELECT COUNT(*) FROM bibliotheque_files_fts")->fetchColumn();
            if ($count == 0) {
                $this->db->exec("INSERT INTO bibliotheque_files_fts(rowid, filename, tags, extracted_text) 
                    SELECT id, filename, tags, extracted_text FROM bibliotheque_files");
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
        // Nettoyer le nom de fichier (autoriser les espaces, lettres, chiffres, tirets, points)
        $filename = preg_replace('/[^\p{L}\p{N} \._-]/u', '_', $filename);
        
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
    public function addExternalFile($path, $forceUpdate = false) {
        if (!file_exists($path)) {
            throw new Exception("Le fichier n'existe pas : $path");
        }
        
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = basename($path);
        
        // Vérifier si le fichier est déjà indexé
        $stmt = $this->db->prepare("SELECT id, thumbnail_path FROM bibliotheque_files WHERE filepath = ?");
        $stmt->execute([$path]);
        $row = $stmt->fetch();
        
        if ($row && !$forceUpdate) {
            // Le fichier existe et on ne force pas. On vérifie juste la miniature.
            if (empty($row['thumbnail_path']) || !file_exists($this->baseDir . '/' . $row['thumbnail_path'])) {
                $thumbPath = $this->generateThumbnail($path, $ext);
                if ($thumbPath) {
                    $upd = $this->db->prepare("UPDATE bibliotheque_files SET thumbnail_path = ? WHERE id = ?");
                    $upd->execute([$thumbPath, $row['id']]);
                }
            }
            return ['status' => 'exists', 'message' => 'Fichier déjà indexé'];
        }
        
        $updateId = $row ? $row['id'] : null;
        return $this->registerFile($path, $filename, $ext, true, $updateId);
    }
    
    /**
     * Analyse technique approfondie du PDF (Format, Couleur, Imposition)
     */
    private function analyzePdf($path, $originalName, $pageCount) {
        $gs_command = get_ghostscript_path();
        
        $metadata = [
            'format' => 'Inconnu',
            'dimensions' => '0x0',
            'is_color' => false,
            'imposition' => 'inconnu',
            'pages' => $pageCount, // Valeur par défaut
            'analysis_date' => date('Y-m-d H:i')
        ];

        // 1. Détection Imposition par nom de fichier (Regex)
        $lowerName = strtolower($originalName);
        if (preg_match('/(ppp|page_par_page|original)/', $lowerName)) {
            $metadata['imposition'] = 'ppp';
        } elseif (preg_match('/(imposed|imp|conv|2up|4up|montage|tirage|cahier|livret)/', $lowerName)) {
            $metadata['imposition'] = 'imposé';
        }

        // 2. Analyse physique via Ghostscript
        $tmpPdf = resolveTempDir() . '/gs_analyze_' . uniqid() . '.pdf';
        if (copy($path, $tmpPdf)) {
            // A. Nombre de pages et Format/Dimensions
            // On récupère le nombre de pages d'abord
            $cmdPages = $gs_command . " -q -dNODISPLAY -c \"(" . addslashes($tmpPdf) . ") (r) file runpdfbegin pdfpagecount = quit\"";
            $outputPages = [];
            exec($cmdPages, $outputPages, $returnVar);
            if ($returnVar === 0 && !empty($outputPages)) {
                $metadata['pages'] = intval($outputPages[0]);
            }

            $cmdFormat = $gs_command . " -q -dNODISPLAY -dNOSAFER -c \"(" . addslashes($tmpPdf) . ") (r) file runpdfbegin 1 pdfgetpage /MediaBox get == quit\"";
            $output = [];
            exec($cmdFormat, $output, $returnVar);
            
            if ($returnVar === 0 && !empty($output)) {
                $bbox = trim($output[0] ?? '', "[] ");
                $parts = preg_split('/\s+/', $bbox);
                if (count($parts) >= 4) {
                    $widthPts = floatval($parts[2]) - floatval($parts[0]);
                    $heightPts = floatval($parts[3]) - floatval($parts[1]);
                    
                    $wMm = round($widthPts * 0.352778);
                    $hMm = round($heightPts * 0.352778);
                    $metadata['dimensions'] = "{$wMm}x{$hMm}";
                    
                    $maxDim = max($wMm, $hMm);
                    $minDim = min($wMm, $hMm);
                    
                    if ($maxDim >= 410 && $maxDim <= 460 && $minDim >= 280 && $minDim <= 330) {
                        $metadata['format'] = 'A3/SRA3';
                    } elseif ($maxDim >= 280 && $maxDim <= 310 && $minDim >= 190 && $minDim <= 220) {
                        $metadata['format'] = 'A4';
                    } elseif ($maxDim >= 190 && $maxDim <= 220 && $minDim >= 130 && $minDim <= 160) {
                        $metadata['format'] = 'A5';
                    } else {
                        $metadata['format'] = 'Spécifique';
                    }
                }
            }

            // B. Couleur (inkcov) - Analyse intégrale du document
            $cmdColor = $gs_command . " -q -o - -sDEVICE=inkcov " . escapeshellarg($tmpPdf);
            $output = [];
            exec($cmdColor, $output, $returnVar);
            foreach ($output as $line) {
                if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)\s+CMYK\s+OK/', $line, $matches)) {
                    if (floatval($matches[1]) > 0.001 || floatval($matches[2]) > 0.001 || floatval($matches[3]) > 0.001) {
                        $metadata['is_color'] = true;
                        break;
                    }
                }
            }
            @unlink($tmpPdf);
        }

        // 3. Heuristique finale pour l'imposition (Règle A3 + 2 pages)
        if ($metadata['imposition'] === 'inconnu') {
            if ($metadata['format'] === 'A3/SRA3' && $pageCount >= 2) {
                $metadata['imposition'] = 'imposé';
            } else {
                $metadata['imposition'] = 'ppp';
            }
        }

        return $metadata;
    }

    /**
     * Extrait les mots-clés les plus fréquents d'un texte avec Racinisation (Stemming)
     */
    public function extractKeywords($text, $title = '', $limit = 20) {
        if (empty($text)) return [];

        $text = mb_strtolower($text, 'UTF-8');
        $titleClean = mb_strtolower($title, 'UTF-8');
        
        // Découpage en mots (lettres uniquement, min 4 caractères)
        preg_match_all('/[a-zàâçéèêëîïôûù]{4,}/u', $text, $matches);
        $words = $matches[0];
        
        // StopWords
        $stopWordsHelper = new StopWords();
        $stopWords = array_merge($stopWordsHelper->getStopWordsFromLanguage('fr'), [
            'plus', 'fait', 'faire', 'c\'est', 'qu\'est-ce', 'peu', 'tous', 'tout', 'être', 'donner', 
            'pourtant', 'point', 'page', 'cette', 'ceux', 'elles', 'ainsi', 'alors', 'entre', 'sous',
            'être', 'avoir', 'donc', 'avec', 'mais', 'pour', 'dans', 'nous', 'vous', 'leur', 'leurs',
            'quelque', 'certains', 'comme'
        ]);
        
        // Stemmer Snowball
        $stemmer = new \Wamania\Snowball\Stemmer\French();
        
        $stemGroups = [];
        foreach ($words as $word) {
            if (in_array($word, $stopWords)) continue;
            
            try {
                $stem = $stemmer->stem($word);
            } catch (Exception $e) {
                $stem = $word;
            }
            
            if (!isset($stemGroups[$stem])) { 
                $stemGroups[$stem] = ['word' => $word, 'score' => 0]; 
            }
            
            $score = 1;
            // Bonus énorme si le mot est dans le titre (exact ou stem)
            if (mb_strpos($titleClean, $word) !== false) {
                $score += 500;
            }
            
            $stemGroups[$stem]['score'] += $score;
        }
        
        // Trier par score décroissant
        uasort($stemGroups, function($a, $b) { 
            return $b['score'] <=> $a['score']; 
        });
        
        // Récupérer les mots originaux les plus représentatifs
        $res = [];
        foreach($stemGroups as $g) { 
            $res[] = $g['word']; 
            if (count($res) >= $limit) break; 
        }
        return $res;
    }

    /**
     * Affine les mots-clés en utilisant Ollama pour en faire des tags intelligents
     */
    private function refineTagsWithAI($keywords, $filename) {
        // Tentative d'appel à Ollama (API locale par défaut)
        $ollamaUrl = "http://localhost:11434/api/generate";
        $prompt = "Voici les 20 mots les plus cités dans ce pdf [$filename] : " . implode(', ', $keywords) . ".\n";
        $prompt .= "Analyse aussi s'il y a des noms propres ou concepts dans le titre et ajoute-les à ta réflexion.\n";
        $prompt .= "CONSIGNE : Parmi ces mots uniquement, choisis les 5 tags les plus pertinents pour caractériser le document.\n";
        $prompt .= "N'invente rien. Réponds uniquement par les 5 tags séparés par des virgules sans aucune phrase.";

        $data = [
            "model" => "qwen2.5:0.5b", 
            "prompt" => $prompt,
            "stream" => false
        ];
        
        // Supprimer le message système car Ministral préfère les instructions dans le prompt

        $ch = curl_init($ollamaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes : on privilégie la qualité à la vitesse
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $json = json_decode($response, true);
            $tags = $json['response'] ?? implode(', ', array_slice($keywords, 0, 4));
            // Nettoyage : remplacer les sauts de ligne par des virgules et enlever les doubles virgules/espaces
            $tags = str_replace(["\n", "\r"], ', ', $tags);
            $tags = preg_replace('/,\s*,/', ',', $tags);
            return trim($tags, " \t\n\r\0\x0B,");
        }

        // Si Ollama échoue, on renvoie les 4 premiers mots-clés PHP
        return implode(', ', array_slice($keywords, 0, 4));
    }

    /**
     * Met à jour uniquement les métadonnées techniques d'un fichier (sans toucher aux chunks/vecteurs)
     */
    public function reanalyzeMetadata($id) {
        $file = $this->getFile($id);
        if (!$file) return false;

        $path = $file['filepath'];
        if (!file_exists($path)) return false;

        // Analyse physique (GS)
        $techMetadata = $this->analyzePdf($path, $file['filename'], $file['page_count']);
        $pageCount = $techMetadata['pages'] ?? $file['page_count'];

        // Extraction de texte pour les tags uniquement
        $text = "";
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
        } catch (Exception $e) {}

        // Tags simples (5 mots)
        $keywords = $this->extractKeywords($text, $file['filename'], 5);
        $tags = implode(', ', $keywords);

        // Update DB
        $stmt = $this->db->prepare("UPDATE bibliotheque_files SET page_count = ?, tags = ?, metadata_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([
            $pageCount,
            $tags,
            json_encode($techMetadata),
            $id
        ]);
    }

    /**
     * Enregistre le fichier en base et génère les métadonnées
     */
    private function registerFile($path, $originalName, $type, $isExternal, $updateId = null) {
        try {
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '1024M');
            
            // 1. Miniature
            $thumbnailPath = $this->generateThumbnail($path, $type);
            
            // 2. Extraction Infos
            $pageCount = 0;
            $extractedText = '';
            $tags = '';
            $techMetadata = [];
            
            if ($type === 'pdf') {
                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($path);
                    $pageCount = count($pdf->getPages());
                    $extractedText = $pdf->getText();
                    
                    // Nettoyage des artefacts de PDF (ex: <>)
                    $extractedText = str_replace('<>', ' ', $extractedText);
                    
                    if (strlen($extractedText) > 500000) {
                        $extractedText = substr($extractedText, 0, 500000) . '...';
                    }

                    // Métadonnées (Format, Couleur)
                    $techMetadata = $this->analyzePdf($path, $originalName, $pageCount);
                    $pageCount = $techMetadata['pages'] ?? $pageCount;
                    
                    // Tags (5 mots-clés PHP les plus fréquents)
                    $keywords = $this->extractKeywords($extractedText, $originalName, 5);
                    $tags = implode(', ', $keywords);

                } catch (Exception $e) {
                    error_log("Erreur extraction PDF $path: " . $e->getMessage());
                }
            }
            
            $metadataJson = json_encode($techMetadata);
            $sourceDir = $isExternal ? dirname($path) : null;
            $fileSize = file_exists($path) ? filesize($path) : 0;

            if ($updateId) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE bibliotheque_files 
                    SET filename = ?, filepath = ?, file_type = ?, thumbnail_path = ?, file_size = ?, 
                        page_count = ?, extracted_text = ?, metadata_json = ?, tags = ?, 
                        is_external = ?, source_directory = ?, updated_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([
                    $originalName, $path, $type, $thumbnailPath, $fileSize,
                    $pageCount, $extractedText, $metadataJson, $tags,
                    $isExternal ? 1 : 0, $sourceDir, $updateId
                ]);
                $finalId = $updateId;
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO bibliotheque_files 
                    (filename, filepath, file_type, thumbnail_path, file_size, page_count, extracted_text, metadata_json, tags, is_external, source_directory, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                ");
                $stmt->execute([
                    $originalName, $path, $type, $thumbnailPath, $fileSize,
                    $pageCount, $extractedText, $metadataJson, $tags,
                    $isExternal ? 1 : 0, $sourceDir
                ]);
                $finalId = $this->db->lastInsertId();
            }
            
            // Sync FTS5
            try {
                $this->db->exec("INSERT INTO bibliotheque_files_fts(rowid, filename, extracted_text) 
                                 SELECT id, filename, extracted_text FROM bibliotheque_files WHERE id = $finalId
                                 ON CONFLICT(rowid) DO UPDATE SET filename=excluded.filename, extracted_text=excluded.extracted_text");
            } catch (Exception $e) { /* FTS fail non-bloquant */ }

            // 4. Génération des chunks pour le RAG (fallback PdfParser immédiat)
            if (!empty($extractedText)) {
                $this->generateChunksForFile($finalId, $extractedText);
            }

            // 5. Marquer pour traitement Markdown (Docling) en arrière-plan
            //    markdown_status = 'raw' → sera traité par process_markdown_chunks.php
            try {
                $this->db->prepare("UPDATE bibliotheque_files SET markdown_status = 'raw' WHERE id = ? AND file_type = 'pdf'")
                         ->execute([$finalId]);
                $this->queueMarkdownProcessing($finalId);
            } catch (Exception $e) {
                // Non-bloquant — le chunk PdfParser reste utilisé
                error_log("queueMarkdownProcessing failed for id=$finalId: " . $e->getMessage());
            }

            ini_set('memory_limit', $originalMemoryLimit);
            return [
                'status' => $updateId ? 'updated' : 'success',
                'id' => $finalId,
                'filename' => $originalName
            ];
            
        } catch (Exception $e) {
            if (isset($originalMemoryLimit)) ini_set('memory_limit', $originalMemoryLimit);
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
            $gs_command = get_ghostscript_path();
            if (!file_exists($gs_command)) {
                return false;
            }
        }
        
        // WORKAROUND: Copier le fichier vers un chemin temporaire ASCII simple
        // Ghostscript sur Windows via cmd.exe gère très mal les caractères spéciaux (accents, !, etc.)
        // dans les chemins passés en argument via exec().
        $tmpPdf = resolveTempDir() . '/gs_temp_' . uniqid() . '.pdf';
        if (!copy($pdfPath, $tmpPdf)) {
            error_log("Impossible de copier le PDF vers temp pour thumbnail: $pdfPath");
            return false;
        }
        
        // Commande avec le chemin temporaire safe
        $command = $gs_command . " -dNOPAUSE -dBATCH -sDEVICE=png16m -dFirstPage=1 -dLastPage=1 -r72 -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -sOutputFile=" . escapeshellarg($outPath) . " " . escapeshellarg($tmpPdf) . " 2>&1";
        
        exec($command, $output, $returnVar);
        
        // Nettoyage immédiat
        @unlink($tmpPdf);
        
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
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'et', 'ou', 'de', 'du', 'en', 'pour', 'dans', 'sur', 'qui', 'que', 'quoi', 'dont', 'où', 'quelles', 'quel', 'quelle', 'quels', 'ce', 'cet', 'cette', 'ces', 'au', 'aux', 'est', 'sont', 'a', 'ont', 'avec', 'par'];

        // Découper la recherche en mots
        $words = preg_split('/\s+/', trim($search));
        
        // Filtrer les mots de moins de 2 caractères et les stopwords
        $words = array_filter($words, function($w) use ($stopWords) { 
            return strlen($w) >= 2 && !in_array(mb_strtolower($w, 'UTF-8'), $stopWords); 
        });
        
        if (empty($words)) {
            return '';
        }
        
        // Mettre chaque mot entre guillemets et ajouter une astérisque
        $quotedWords = array_map(function($w) {
            $w = str_replace('"', '""', $w); // Échapper les guillemets existants
            return '"' . $w . '"*';
        }, $words);
        
        // Comportement intelligent : AND pour 2-3 mots, OR pour 4+ mots
        $wordCount = count($quotedWords);
        if ($wordCount >= 2 && $wordCount <= 3) {
            return implode(' AND ', $quotedWords);
        } else {
            return implode(' OR ', $quotedWords);
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
            
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            }
            
            $words = preg_split('/\s+/', trim($search));
            $words = array_filter($words, function($w) { return strlen($w) >= 2; });
            
            if (empty($words)) {
                return [];
            }
            
            $contexts = [];
            $contextLength = 100;
            $maxContexts = 3;
            
            foreach ($words as $word) {
                if (count($contexts) >= $maxContexts) {
                    break;
                }
                
                if (!mb_check_encoding($word, 'UTF-8')) {
                    $word = mb_convert_encoding($word, 'UTF-8', 'UTF-8');
                }
                
                $wordLower = mb_strtolower($word, 'UTF-8');
                $textLower = mb_strtolower($text, 'UTF-8');
                $wordLength = mb_strlen($word, 'UTF-8');
                
                $offset = 0;
                $maxIterations = 50;
                $iterationCount = 0;
                
                while ($iterationCount < $maxIterations && ($pos = mb_strpos($textLower, $wordLower, $offset, 'UTF-8')) !== false && count($contexts) < $maxContexts) {
                    $iterationCount++;
                    
                    $formatted = $this->formatSingleContext($text, $pos, $wordLength, $contextLength);
                    $contextKey = md5($formatted);
                    if (!isset($contexts[$contextKey]) && !empty($formatted)) {
                        $contexts[$contextKey] = $formatted;
                    }
                    
                    $offset = $pos + 1;
                    if ($offset >= mb_strlen($text, 'UTF-8')) {
                        break;
                    }
                }
            }
            
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
     * Formate un extrait de contexte unique avec mise en valeur <mark>
     */
    private function formatSingleContext(string $text, int $pos, int $wordLength, int $contextLength): string {
        $textLen = mb_strlen($text, 'UTF-8');
        $start = max(0, $pos - $contextLength);
        $end = min($textLen, $pos + $wordLength + $contextLength);
        
        $textBefore = mb_substr($text, 0, $pos, 'UTF-8');
        $sentenceStart = mb_strrpos($textBefore, '. ', 0, 'UTF-8');
        if ($sentenceStart !== false && $sentenceStart > $start - 50) {
            $start = $sentenceStart + 2;
        }
        
        $sentenceEnd = mb_strpos($text, '. ', $pos, 'UTF-8');
        if ($sentenceEnd !== false && $sentenceEnd < $end + 50) {
            $end = $sentenceEnd + 1;
        }
        
        $context = mb_substr($text, $start, $end - $start, 'UTF-8');
        $context = preg_replace('/\s+/', ' ', $context);
        
        if ($start > 0) {
            $context = '...' . ltrim($context);
        }
        if ($end < $textLen) {
            $context = rtrim($context) . '...';
        }
        
        $contextLower = mb_strtolower($context, 'UTF-8');
        $wordLower = mb_strtolower(mb_substr($text, $pos, $wordLength, 'UTF-8'), 'UTF-8');
        $wordPosInContext = mb_strpos($contextLower, $wordLower, 0, 'UTF-8');
        if ($wordPosInContext !== false) {
            $before = mb_substr($context, 0, $wordPosInContext, 'UTF-8');
            $match = mb_substr($context, $wordPosInContext, $wordLength, 'UTF-8');
            $after = mb_substr($context, $wordPosInContext + $wordLength, null, 'UTF-8');
            $context = $before . '<mark>' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</mark>' . $after;
        }
        
        return trim($context);
    }
    
    /**
     * Recherche Full-Text avec FTS5
     */
    private function getAllFilesWithFTS($search, $type = '', $filters = []) {
        $ftsQuery = $this->prepareFTSQuery($search);
        
        if (empty($ftsQuery)) {
            return $this->getAllFilesWithLike($search, $type, $filters);
        }
        
        $sql = "SELECT b.id, b.filename, b.file_type, b.thumbnail_path, b.file_size, b.page_count, b.metadata_json, b.tags, b.is_external, b.source_directory, b.created_at, b.updated_at, 
                       bm25(bibliotheque_files_fts, 10.0, 5.0) as rank,
                       snippet(bibliotheque_files_fts, -1, '<mark>', '</mark>', '...', 30) as fts_snippet
                FROM bibliotheque_files b
                JOIN bibliotheque_files_fts ON bibliotheque_files_fts.rowid = b.id
                WHERE bibliotheque_files_fts MATCH ?";
        $params = [$ftsQuery];
        
        if (!empty($type)) {
            $sql .= " AND b.file_type = ?";
            $params[] = $type;
        }

        // Appliquer les filtres techniques
        if (!empty($filters)) {
            if (!empty($filters['format'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.format') = ?";
                $params[] = $filters['format'];
            }
            if (!empty($filters['color'])) {
                $isColor = $filters['color'] === 'color' ? 1 : 0;
                $sql .= " AND json_extract(b.metadata_json, '$.is_color') = ?";
                $params[] = $isColor;
            }
            if (!empty($filters['imposition'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.imposition') = ?";
                $params[] = $filters['imposition'];
            }
            if (!empty($filters['tag'])) {
                $this->applyTagFilters($sql, $params, $filters['tag'], 'b');
            }
        }
        
        // Gestion du tri
        $sortBy = $filters['sort_by'] ?? 'rank';
        $sortOrder = $filters['sort_order'] ?? 'ASC';
        
        // Sécurisation du tri pour éviter les injections
        $allowedSortFields = ['id', 'filename', 'file_size', 'created_at', 'updated_at', 'page_count', 'rank'];
        if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'rank';
        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'ASC';

        $sql .= " ORDER BY $sortBy $sortOrder";
        
        // Gestion de la pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$filters['limit'];
            $params[] = (int)$filters['offset'];
        } else {
            $sql .= " LIMIT 100";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Extraire les contextes pour chaque résultat
        foreach ($results as &$file) {
            if (!empty($file['fts_snippet'])) {
                $file['match_contexts'] = [$file['fts_snippet']];
            } else {
                $file['match_contexts'] = [];
            }
            unset($file['fts_snippet']);
        }
        unset($file);
        
        return $results;
    }
    
    /**
     * Recherche multi-mots avec LIKE (fallback)
     */
    private function getAllFilesWithLike($search, $type = '', $filters = []) {
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
            $conditions[] = "(b.filename LIKE ? OR b.extracted_text LIKE ? OR b.tags LIKE ?)";
            $params[] = "%$word%";
            $params[] = "%$word%";
            $params[] = "%$word%";
        }
        
        $sql = "SELECT b.id, b.filename, b.file_type, b.thumbnail_path, b.file_size, b.page_count, b.metadata_json, b.tags, b.is_external, b.source_directory, b.created_at, b.updated_at FROM bibliotheque_files b WHERE (" . implode(" AND ", $conditions) . ")";
        
        if (!empty($type)) {
            $sql .= " AND b.file_type = ?";
            $params[] = $type;
        }
        
        // Appliquer les filtres techniques
        if (!empty($filters)) {
            if (!empty($filters['format'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.format') = ?";
                $params[] = $filters['format'];
            }
            if (!empty($filters['color'])) {
                $isColor = $filters['color'] === 'color' ? 1 : 0;
                $sql .= " AND json_extract(b.metadata_json, '$.is_color') = ?";
                $params[] = $isColor;
            }
            if (!empty($filters['imposition'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.imposition') = ?";
                $params[] = $filters['imposition'];
            }
            if (!empty($filters['tag'])) {
                $this->applyTagFilters($sql, $params, $filters['tag'], 'b');
            }
        }
        
        // Gestion du tri
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        
        $allowedSortFields = ['id', 'filename', 'file_size', 'created_at', 'updated_at', 'page_count'];
        if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'created_at';
        if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';

        $sql .= " ORDER BY $sortBy $sortOrder";
        
        // Gestion de la pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$filters['limit'];
            $params[] = (int)$filters['offset'];
        } else {
            $sql .= " LIMIT 100";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sans FTS, pas d'extraction rapide du texte, contextes vides
        foreach ($results as &$file) {
            $file['match_contexts'] = [];
        }
        unset($file);
        
        return $results;
    }
    
    /**
     * Recherche avec correction de fautes de frappe (fuzzy search)
     * Optimisé : limite à 50 fichiers et utilise seulement le nom de fichier
     */
    private function getAllFilesWithFuzzy($search, $type = '', $filters = []) {
        // Seulement si la recherche fait au moins 3 caractères
        if (strlen($search) < 3) {
            return [];
        }
        
        // Gestion du tri pour la recherche fuzzy
        $allowedSort = ['filename', 'page_count', 'created_at', 'file_size'];
        $sort = isset($filters['sort']) && in_array($filters['sort'], $allowedSort) ? $filters['sort'] : 'created_at';
        $order = isset($filters['order']) && strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT id, filename, file_type, thumbnail_path, file_size, page_count, metadata_json, tags, is_external, source_directory, created_at, updated_at FROM bibliotheque_files WHERE 1=1";
        $params = [];
        
        if (!empty($type)) {
            $sql .= " AND file_type = ?";
            $params[] = $type;
        }

        // Appliquer les filtres techniques
        if (!empty($filters)) {
            if (!empty($filters['format'])) {
                $sql .= " AND json_extract(metadata_json, '$.format') = ?";
                $params[] = $filters['format'];
            }
            if (!empty($filters['color'])) {
                $isColor = $filters['color'] === 'color' ? 1 : 0;
                $sql .= " AND json_extract(metadata_json, '$.is_color') = ?";
                $params[] = $isColor;
            }
            if (!empty($filters['imposition'])) {
                $sql .= " AND json_extract(metadata_json, '$.imposition') = ?";
                $params[] = $filters['imposition'];
            }
            if (!empty($filters['tag'])) {
                $this->applyTagFilters($sql, $params, $filters['tag']);
            }
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
    public function getAllFiles($search = '', $type = '', $filters = []) {
        // Si pas de recherche, retourner tous les fichiers (avec filtres éventuels)
        if (empty($search)) {
            $sql = "SELECT id, filename, file_type, thumbnail_path, file_size, page_count, metadata_json, tags, is_external, source_directory, created_at, updated_at FROM bibliotheque_files b WHERE 1=1";
            $params = [];
            
            if (!empty($type)) {
                $sql .= " AND b.file_type = ?";
                $params[] = $type;
            }

            // Appliquer les filtres techniques
            if (!empty($filters)) {
                if (!empty($filters['format'])) {
                    $sql .= " AND json_extract(b.metadata_json, '$.format') = ?";
                    $params[] = $filters['format'];
                }
                if (!empty($filters['color'])) {
                    $isColor = $filters['color'] === 'color' ? 1 : 0;
                    $sql .= " AND json_extract(b.metadata_json, '$.is_color') = ?";
                    $params[] = $isColor;
                }
                if (!empty($filters['imposition'])) {
                    $sql .= " AND json_extract(b.metadata_json, '$.imposition') = ?";
                    $params[] = $filters['imposition'];
                }
                if (!empty($filters['tag'])) {
                    $this->applyTagFilters($sql, $params, $filters['tag'], 'b');
                }
            }
            
            // Gestion du tri
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = $filters['sort_order'] ?? 'DESC';
            
            $allowedSortFields = ['id', 'filename', 'file_size', 'created_at', 'updated_at', 'page_count'];
            if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'created_at';
            if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'DESC';

            $sql .= " ORDER BY $sortBy $sortOrder";
            
            // Gestion de la pagination
            if (isset($filters['limit']) && isset($filters['offset'])) {
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = (int)$filters['limit'];
                $params[] = (int)$filters['offset'];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // ... (FTS, LIKE, Fuzzy logic)
        // Note: Les méthodes appelées ci-dessous doivent aussi gérer sort et limit
        
        // Vérifier si FTS5 est disponible
        if ($this->hasFTS5Support()) {
            // Utiliser FTS5
            try {
                $results = $this->getAllFilesWithFTS($search, $type, $filters);
            } catch (Exception $e) {
                error_log("FTS5 Search failed: " . $e->getMessage() . " - Falling back to LIKE");
                $results = $this->getAllFilesWithLike($search, $type, $filters);
            }
            
            // Si aucun résultat, essayer LIKE puis fuzzy search
            if (empty($results)) {
                $results = $this->getAllFilesWithLike($search, $type, $filters);
                
                // Si toujours aucun résultat, essayer fuzzy search
                if (empty($results)) {
                    $results = $this->getAllFilesWithFuzzy($search, $type, $filters);
                }
            }
        } else {
            $results = $this->getAllFilesWithLike($search, $type, $filters);
            if (empty($results)) {
                $results = $this->getAllFilesWithFuzzy($search, $type, $filters);
            }
        }
        
        return $results;
    }

    /**
     * Compte le nombre total de fichiers correspondants aux critères (pour la pagination)
     */
    public function countAllFiles($search = '', $type = '', $filters = []) {
        $sql = "SELECT COUNT(*) FROM bibliotheque_files b";
        $params = [];
        
        if (!empty($search)) {
            if ($this->hasFTS5Support()) {
                $ftsQuery = $this->prepareFTSQuery($search);
                if (empty($ftsQuery)) {
                    // Fallback sur LIKE si pas de mots valides
                    $sql = "SELECT COUNT(*) FROM bibliotheque_files b";
                    $params = [];
                    // ... sera géré par la suite de la fonction
                } else {
                    $sql = "SELECT COUNT(*) FROM bibliotheque_files b JOIN bibliotheque_files_fts ON bibliotheque_files_fts.rowid = b.id WHERE bibliotheque_files_fts MATCH ?";
                    $params = [$ftsQuery];
                }
            } else {
                $words = preg_split('/\s+/', trim($search));
                $words = array_filter($words, function($w) { return strlen($w) >= 2; });
                if (!empty($words)) {
                    $conditions = [];
                    foreach ($words as $word) {
                        $conditions[] = "(b.filename LIKE ? OR b.extracted_text LIKE ? OR b.tags LIKE ?)";
                        $params[] = "%$word%";
                        $params[] = "%$word%";
                        $params[] = "%$word%";
                    }
                    $sql .= " WHERE (" . implode(" AND ", $conditions) . ")";
                } else {
                    $sql .= " WHERE 1=1";
                }
            }
        } else {
            $sql .= " WHERE 1=1";
        }
        
        if (!empty($type)) {
            $sql .= " AND b.file_type = ?";
            $params[] = $type;
        }

        if (!empty($filters)) {
            if (!empty($filters['format'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.format') = ?";
                $params[] = $filters['format'];
            }
            if (!empty($filters['color'])) {
                $isColor = $filters['color'] === 'color' ? 1 : 0;
                $sql .= " AND json_extract(b.metadata_json, '$.is_color') = ?";
                $params[] = $isColor;
            }
            if (!empty($filters['imposition'])) {
                $sql .= " AND json_extract(b.metadata_json, '$.imposition') = ?";
                $params[] = $filters['imposition'];
            }
            if (!empty($filters['tag'])) {
                $this->applyTagFilters($sql, $params, $filters['tag'], 'b');
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Met à jour les métadonnées et les tags d'un fichier
     */
    public function updateMetadata($id, $data) {
        $allowedFields = ['filename', 'tags', 'page_count'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (isset($data['format']) || isset($data['is_color']) || isset($data['imposition'])) {
            // Récupérer le JSON actuel
            $file = $this->getFile($id);
            $meta = $file['metadata_json'] ? JSON_decode($file['metadata_json'], true) : [];
            
            if (isset($data['format'])) $meta['format'] = $data['format'];
            if (isset($data['is_color'])) $meta['is_color'] = (bool)$data['is_color'];
            if (isset($data['imposition'])) $meta['imposition'] = $data['imposition'];
            
            $updates[] = "metadata_json = ?";
            $params[] = json_encode($meta);
        }
        
        if (empty($updates)) return false;
        
        $updates[] = "updated_at = datetime('now')";
        $params[] = $id;
        
        $sql = "UPDATE bibliotheque_files SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function getFile($id) {
        $stmt = $this->db->prepare("SELECT * FROM bibliotheque_files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function deleteFile($id, $deleteFromDisk = true) {
        $file = $this->getFile($id);
        if (!$file) return false;
        
        $this->db->beginTransaction();
        try {
            // 1. Récupérer les IDs des chunks pour nettoyer les vecteurs
            $stmt = $this->db->prepare("SELECT id FROM bibliotheque_chunks WHERE file_id = ?");
            $stmt->execute([$id]);
            $chunkIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($chunkIds)) {
                $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
                
                // 2. Supprimer les vecteurs
                $stmt = $this->db->prepare("DELETE FROM bibliotheque_vectors WHERE chunk_id IN ($placeholders)");
                $stmt->execute($chunkIds);
                
                // 3. Supprimer les chunks
                $stmt = $this->db->prepare("DELETE FROM bibliotheque_chunks WHERE id IN ($placeholders)");
                $stmt->execute($chunkIds);
            }
            
            // 4. Supprimer la miniature
            if (!empty($file['thumbnail_path'])) {
                $thumbPath = $this->baseDir . DIRECTORY_SEPARATOR . $file['thumbnail_path'];
                if (file_exists($thumbPath)) {
                    @unlink($thumbPath);
                }
            }
            
            // 5. Supprimer le fichier physique (si demandé)
            if ($deleteFromDisk && $file['is_external'] == 0) {
                if (!empty($file['filepath']) && file_exists($file['filepath'])) {
                    @unlink($file['filepath']);
                }
            }
            
            // 6. Supprimer de la base principale
            $stmt = $this->db->prepare("DELETE FROM bibliotheque_files WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la suppression du fichier $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Renomme un fichier dans la base de données
     */
    public function renameFile($id, $newName) {
        $stmt = $this->db->prepare("SELECT id FROM bibliotheque_files WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new Exception("Fichier non trouvé");
        }
        
        $stmt = $this->db->prepare("UPDATE bibliotheque_files SET filename = ?, updated_at = datetime('now') WHERE id = ?");
        return $stmt->execute([$newName, $id]);
    }

    /**
     * MAINTENANCE: Vérifie l'intégrité de la bibliothèque
     */
    public function checkIntegrity() {
        $stmt = $this->db->query("SELECT id, filename, filepath, thumbnail_path FROM bibliotheque_files");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [
            'total' => count($files),
            'ok' => 0,
            'missing_file' => [],
            'missing_thumb' => []
        ];
        
        foreach ($files as $file) {
            $fileMissing = !file_exists($file['filepath']);
            $thumbMissing = empty($file['thumbnail_path']) || !file_exists($this->baseDir . DIRECTORY_SEPARATOR . $file['thumbnail_path']);
            
            if ($fileMissing) {
                $results['missing_file'][] = [
                    'id' => $file['id'],
                    'filename' => $file['filename'],
                    'path' => $file['filepath']
                ];
            }
            
            if ($thumbMissing) {
                $results['missing_thumb'][] = [
                    'id' => $file['id'],
                    'filename' => $file['filename']
                ];
            }
            
            if (!$fileMissing && !$thumbMissing) {
                $results['ok']++;
            }
        }
        
        return $results;
    }

    /**
     * MAINTENANCE: Supprime les entrées BDD dont le fichier physique est manquant
     */
    public function cleanOrphans() {
        $integrity = $this->checkIntegrity();
        $count = 0;
        
        if (!empty($integrity['missing_file'])) {
            $ids = array_column($integrity['missing_file'], 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare("DELETE FROM bibliotheque_files WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
        }
        
        return $count;
    }

    /**
     * MAINTENANCE: Prépare la régénération des miniatures
     */
    public function prepareRegenerateThumbnails() {
        // 1. Vider les chemins en base
        $this->db->exec("UPDATE bibliotheque_files SET thumbnail_path = NULL");
        
        // 2. Supprimer physiquement les dossiers de miniatures
        $this->recursiveDelete($this->baseDir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'pdf');
        $this->recursiveDelete($this->baseDir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'png');
        
        // Recréer la structure
        $this->createDirectoryStructure();
        
        return true;
    }

    private function recursiveDelete($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            (is_dir($path)) ? $this->recursiveDelete($path) : unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * MAINTENANCE: Répare l'index FTS5
     */
    public function repairFTS() {
        try {
            $this->db->beginTransaction();
            // Supprimer l'ancienne table FTS
            $this->db->exec("DROP TABLE IF EXISTS bibliotheque_files_fts");
            // La recréer avec le nouveau schéma (via createFTS5Table ou manuellement ici)
            $this->db->exec("CREATE VIRTUAL TABLE bibliotheque_files_fts USING fts5(
                filename,
                tags,
                extracted_text,
                content='bibliotheque_files',
                content_rowid='id'
            )");
            // La remplir
            $this->db->exec("INSERT INTO bibliotheque_files_fts(rowid, filename, tags, extracted_text) 
                SELECT id, filename, tags, extracted_text FROM bibliotheque_files");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Erreur repairFTS: " . $e->getMessage());
            // Tenter de recréer les triggers au cas où
            $this->createFTS5Table();
            return false;
        }
    }

    /**
     * MAINTENANCE: Vide entièrement la bibliothèque
     */
    public function resetLibrary($deleteFiles = false) {
        if ($deleteFiles) {
            $stmt = $this->db->query("SELECT filepath FROM bibliotheque_files WHERE is_external = 0");
            while ($path = $stmt->fetchColumn()) {
                if (file_exists($path)) @unlink($path);
            }
        }
        
        // Supprimer toutes les miniatures
        $this->recursiveDelete($this->baseDir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'pdf');
        $this->recursiveDelete($this->baseDir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'png');
        
        // Vider les tables
        $this->db->exec("DELETE FROM bibliotheque_files");
        // Les triggers s'occupent de FTS5 mais on peut forcer le rebuild si besoin
        try {
            $this->db->exec("DELETE FROM bibliotheque_files_fts");
        } catch (Exception $e) {}
        
        return true;
    }

    /**
     * MAINTENANCE: Récupère les dossiers externes connus
     */
    public function getKnownExternalDirectories() {
        $stmt = $this->db->query("SELECT DISTINCT source_directory FROM bibliotheque_files WHERE is_external = 1 AND source_directory IS NOT NULL");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère tous les tags uniques utilisés dans la bibliothèque
     */
    public function getAllUniqueTags() {
        try {
            $stmt = $this->db->query("SELECT tags FROM bibliotheque_files WHERE tags IS NOT NULL AND tags != ''");
            $allTags = [];
            while ($row = $stmt->fetchColumn()) {
                $tags = explode(',', $row);
                foreach ($tags as $tag) {
                    $trimmed = trim($tag);
                    if ($trimmed !== '') {
                        $allTags[$trimmed] = true;
                    }
                }
            }
            $result = array_map('strval', array_keys($allTags));
            sort($result);
            return $result;
        } catch (Exception $e) {
            error_log("Erreur getAllUniqueTags: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Découpe un texte en morceaux de taille fixe avec un chevauchement
     */
    public function chunkText($text, $chunkSize = 300, $overlap = 50) {
        if (empty($text)) return [];
        
        // Nettoyage basique (espaces multiples, et artefacts <>)
        $text = str_replace('<>', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $words = explode(' ', $text);
        $totalWords = count($words);
        
        $chunks = [];
        $start = 0;
        
        while ($start < $totalWords) {
            $end = min($start + $chunkSize, $totalWords);
            $chunkWords = array_slice($words, $start, $end - $start);
            
            if (!empty($chunkWords)) {
                $chunks[] = [
                    'content' => implode(' ', $chunkWords),
                    'word_count' => count($chunkWords)
                ];
            }
            
            // Si on a atteint la fin, on s'arrête
            if ($end === $totalWords) break;
            
            // Sinon on avance en reculant de l'overlap
            $start += ($chunkSize - $overlap);
            
            // Sécurité pour éviter les boucles infinies si overlap >= chunkSize
            if ($overlap >= $chunkSize) $start += $chunkSize;
        }
        
        return $chunks;
    }

    /**
     * Génère et enregistre les chunks pour un fichier donné
     */
    public function generateChunksForFile($fileId, $text) {
        // 1. Supprimer les anciens chunks
        $this->db->prepare("DELETE FROM bibliotheque_chunks WHERE file_id = ?")->execute([$fileId]);
        
        // 2. Découper le texte
        $chunks = $this->chunkText($text, 300, 50);
        
        // 3. Insérer les nouveaux chunks
        $stmt = $this->db->prepare("INSERT INTO bibliotheque_chunks (file_id, chunk_index, content, word_count) VALUES (?, ?, ?, ?)");
        $stmtFts = $this->db->prepare("INSERT INTO bibliotheque_chunks_fts (rowid, content) VALUES (?, ?)");
        
        foreach ($chunks as $index => $chunk) {
            $stmt->execute([$fileId, $index, $chunk['content'], $chunk['word_count']]);
            $chunkId = $this->db->lastInsertId();
            
            try {
                $stmtFts->execute([$chunkId, $chunk['content']]);
            } catch (Exception $e) { /* FTS fail non-bloquant */ }
        }
        
        return count($chunks);
    }

    /**
     * Ajoute un file_id dans la queue Markdown pour traitement Docling en background.
     * Utilise un fichier texte simple (logs/markdown_queue.txt) — un ID par ligne.
     * Un daemon/cron/worker lit ce fichier séquentiellement.
     */
    public function queueMarkdownProcessing(int $fileId): void
    {
        $logsDir   = __DIR__ . '/../../logs';
        $queueFile = $logsDir . '/markdown_queue.txt';

        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
        }

        // Éviter les doublons : ne pas rajouter si déjà dans la queue
        if (file_exists($queueFile)) {
            $existing = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (in_array((string)$fileId, $existing, true)) {
                return;
            }
        }

        file_put_contents($queueFile, $fileId . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Applique les filtres de tags (Inclusion/Exclusion) à une requête SQL
     * @param string &$sql La requête SQL en cours de construction
     * @param array &$params Les paramètres de la requête
     * @param string $tagFilter Chaîne de tags séparés par des virgules (ex: "histoire,-secret")
     * @param string $alias Alias de la table bibliotheque_files dans la requête
     */
    private function applyTagFilters(&$sql, &$params, $tagFilter, $alias = 'b') {
        if (empty($tagFilter)) return;
        
        $tags = explode(',', $tagFilter);
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (empty($tag)) continue;
            
            $isExclusion = false;
            if (strpos($tag, '-') === 0) {
                $isExclusion = true;
                $tag = ltrim($tag, '-');
            }
            
            $cleanTag = strtolower(str_replace(' ', '', $tag));
            $tagPattern = "%," . $cleanTag . ",%";
            
            // On normalise les tags pour la recherche : on entoure de virgules et on enlève les espaces
            $normalizedTags = "',' || LOWER(REPLACE(COALESCE(" . $alias . ".tags, ''), ' ', '')) || ','";
            
            if ($isExclusion) {
                // Exclusion : le tag ne doit PAS être présent
                $sql .= " AND $normalizedTags NOT LIKE ?";
            } else {
                // Inclusion : le tag DOIT être présent
                $sql .= " AND $normalizedTags LIKE ?";
            }
            $params[] = $tagPattern;
        }
    }

    /**
     * Recherche vectorielle filtrée par tags
     */
    public function searchVector($qVector, $limit = 20, $tagFilter = '', $fileIds = []) {
        $sql = "SELECT v.chunk_id, v.vector 
                FROM bibliotheque_vectors v
                JOIN bibliotheque_chunks c ON v.chunk_id = c.id
                JOIN bibliotheque_files b ON c.file_id = b.id
                WHERE 1=1";
        $params = [];
        
        $this->applyTagFilters($sql, $params, $tagFilter, 'b');

        if (!empty($fileIds) && is_array($fileIds)) {
            $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
            $sql .= " AND b.id IN ($placeholders)";
            $params = array_merge($params, $fileIds);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $vectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $scores = [];
        foreach ($vectors as $vData) {
            $vector = array_values(unpack('f*', $vData['vector']));
            $dotProduct = 0;
            foreach ($qVector as $i => $val) { 
                $dotProduct += $val * ($vector[$i] ?? 0); 
            }
            $scores[$vData['chunk_id']] = $dotProduct;
        }
        
        arsort($scores);
        return array_slice(array_keys($scores), 0, $limit);
    }
    
    /**
     * Recherche hybride (Vector + Full-Text Search) filtrée par tags
     */
    public function searchHybrid($question, $qVector, $limit = 20, $tagFilter = '', $fileIds = []) {
        $ftsLimit = ceil($limit / 2);
        $vectorLimit = $limit; // Demander plus de vecteurs pour compenser les doublons potentiels
        
        $hybridIds = [];
        
        // 1. Recherche Plein-Texte (FTS) sur les mots-clés de la question
        $ftsQuery = $this->prepareFTSQuery($question);
        if (!empty($ftsQuery)) {
            $sqlFts = "SELECT c.id 
                       FROM bibliotheque_chunks c
                       JOIN bibliotheque_chunks_fts fts ON c.id = fts.rowid
                       JOIN bibliotheque_files b ON c.file_id = b.id
                       WHERE bibliotheque_chunks_fts MATCH ?";
            $paramsFts = [$ftsQuery];
            
            $this->applyTagFilters($sqlFts, $paramsFts, $tagFilter, 'b');

            if (!empty($fileIds) && is_array($fileIds)) {
                $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                $sqlFts .= " AND b.id IN ($placeholders)";
                $paramsFts = array_merge($paramsFts, $fileIds);
            }
            
            $sqlFts .= " ORDER BY rank LIMIT " . (int)$ftsLimit;
            
            $stmt = $this->db->prepare($sqlFts);
            $stmt->execute($paramsFts);
            $ftsResults = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($ftsResults as $id) {
                if (!in_array($id, $hybridIds)) {
                    $hybridIds[] = $id;
                }
            }
        }
        
        // 2. Recherche Vectorielle pour le sémantique global
        // On demande un peu plus de résultats au cas où les FTS prennent déjà des places
        $vectorResults = $this->searchVector($qVector, $vectorLimit, $tagFilter, $fileIds);
        
        foreach ($vectorResults as $id) {
            if (!in_array($id, $hybridIds)) {
                $hybridIds[] = $id;
            }
            if (count($hybridIds) >= $limit) {
                break;
            }
        }
        
        return array_slice($hybridIds, 0, $limit);
    }
}


