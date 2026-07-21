<?php
/**
 * process_markdown_chunks.php — Phase 3 du pipeline RAG Markdown
 *
 * Orchestre la conversion PDF → Markdown (via Docling) → chunks sémantiques
 * avec atomic swap (anciens chunks préservés jusqu'au COMMIT), fallback
 * silencieux et vectorisation enchaînée après chaque fichier réussi.
 *
 * Usage CLI :
 *   php process_markdown_chunks.php [options]
 *
 * Options :
 *   --file_id=N      Traiter un seul fichier
 *   --all            Traiter tous les fichiers 'raw'
 *   --retry-errors   Relancer les fichiers en 'error'
 *   --force          Retraiter même les fichiers 'done' (avec --all)
 */

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/binary_utilities.php';
require_once __DIR__ . '/../models/SettingsManager.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

// ─── Paramètres CLI ───────────────────────────────────────────────────────────
$opts        = getopt('', ['file_id::', 'all', 'retry-errors', 'force']);
$singleId    = isset($opts['file_id']) ? (int)$opts['file_id'] : null;
$processAll  = isset($opts['all']);
$retryErrors = isset($opts['retry-errors']);
$forceRedo   = isset($opts['force']);

if (!$singleId && !$processAll && !$retryErrors) {
    echo "Usage:\n";
    echo "  php process_markdown_chunks.php --file_id=N       # un seul fichier\n";
    echo "  php process_markdown_chunks.php --all              # tous les fichiers 'raw'\n";
    echo "  php process_markdown_chunks.php --retry-errors     # relancer les 'error'\n";
    echo "  php process_markdown_chunks.php --all --force      # tout retraiter (y compris 'done')\n";
    exit(1);
}

// ─── Init DB ──────────────────────────────────────────────────────────────────
$db = pdo_connect();

// PRAGMA foreign_keys = ON une fois pour toute la session
// → garantit que DELETE sur bibliotheque_chunks déclenche CASCADE sur bibliotheque_vectors
$db->exec("PRAGMA foreign_keys = ON;");

$settingsManager = new SettingsManager($db);

// ─── Chemins ──────────────────────────────────────────────────────────────────
$scriptDir   = __DIR__;
$logsDir     = $scriptDir . '/../logs';
$scriptPy    = $scriptDir . '/../api/scripts/pdf_to_semantic_chunks.py';
$logFile     = $logsDir . '/markdown_migration.log';
$statusFile  = $logsDir . '/markdown_status.json';
$pythonBin   = get_python_path();

if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function logMsg(string $msg, string $logFile): void
{
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function writeStatus(array $data, string $statusFile): void
{
    @file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ─── Requête de sélection des fichiers ───────────────────────────────────────
if ($singleId) {
    $stmt = $db->prepare("SELECT id, filename, filepath FROM bibliotheque_files WHERE id = ? AND file_type = 'pdf'");
    $stmt->execute([$singleId]);
} else {
    $statuses = ["'raw'"];
    if ($retryErrors) $statuses[] = "'error'";
    if ($forceRedo)   $statuses[] = "'done'";
    $inClause = implode(',', $statuses);
    $stmt = $db->query("SELECT id, filename, filepath FROM bibliotheque_files
                        WHERE file_type = 'pdf'
                          AND (markdown_status IS NULL OR markdown_status IN ($inClause))
                        ORDER BY id ASC");
}

$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($files);

if ($total === 0) {
    logMsg("Aucun fichier à traiter.", $logFile);
    exit(0);
}

logMsg("=== Démarrage process_markdown_chunks.php === ($total fichiers)", $logFile);
logMsg("Python: $pythonBin", $logFile);

$processed = 0;
$errors    = 0;
$skipped   = 0;

// ─── Boucle principale (séquentielle — jamais en parallèle) ──────────────────
foreach ($files as $idx => $file) {
    $fileId   = (int)$file['id'];
    $filename = $file['filename'];
    $filepath = $file['filepath'];

    logMsg("[$idx/$total] Traitement : $filename (id=$fileId)", $logFile);

    // Mise à jour du fichier de statut pour l'UI
    writeStatus([
        'running'    => true,
        'current'    => ['id' => $fileId, 'name' => $filename],
        'progress'   => $idx,
        'total'      => $total,
        'processed'  => $processed,
        'errors'     => $errors,
        'started_at' => date('Y-m-d H:i:s'),
    ], $statusFile);

    // Vérifications préalables
    if (!file_exists($filepath)) {
        logMsg("  SKIP — fichier introuvable : $filepath", $logFile);
        $skipped++;
        continue;
    }

    // Fichier temporaire de sortie JSON
    $tmpJson = sys_get_temp_dir() . '/rag_chunks_' . $fileId . '_' . uniqid() . '.json';

    // ── Marquer en 'processing' ────────────────────────────────────────────
    $db->prepare("UPDATE bibliotheque_files SET markdown_status = 'processing' WHERE id = ?")
       ->execute([$fileId]);

    // ── Appel du script Python (mode local) ───────────────────────────────
    $envPrefix = function_exists('get_hf_home_env') ? get_hf_home_env() : '';
    $cmd = $envPrefix 
         . escapeshellarg($pythonBin)
         . ' ' . escapeshellarg($scriptPy)
         . ' ' . escapeshellarg($filepath)
         . ' ' . escapeshellarg($tmpJson)
         . ' 2>&1';

    $timeoutSeconds = 300; // 5 min max par fichier

    // proc_open pour pouvoir lire stdout+stderr et gérer le timeout
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($proc)) {
        logMsg("  ERREUR — impossible de lancer Python", $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        @unlink($tmpJson);
        continue;
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $t0     = time();
    $timedOut = false;

    while (true) {
        $stdout .= (string)fread($pipes[1], 4096);
        $stderr .= (string)fread($pipes[2], 4096);

        $status = proc_get_status($proc);
        if (!$status['running']) {
            // Lire le reste
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            break;
        }

        if (time() - $t0 > $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($proc, 9);
            break;
        }

        usleep(200000); // 200ms
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);

    if ($timedOut) {
        logMsg("  ERREUR — timeout après {$timeoutSeconds}s", $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        @unlink($tmpJson);
        continue;
    }

    if ($exitCode !== 0) {
        logMsg("  ERREUR Python (exit $exitCode) : " . trim($stderr ?: $stdout), $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        @unlink($tmpJson);
        continue;
    }

    // ── Parser le JSON de sortie ───────────────────────────────────────────
    if (!file_exists($tmpJson)) {
        logMsg("  ERREUR — fichier JSON de sortie introuvable", $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        continue;
    }

    $jsonContent = file_get_contents($tmpJson);
    @unlink($tmpJson);

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($data['chunks'])) {
        logMsg("  ERREUR — JSON invalide ou chunks vides : " . json_last_error_msg(), $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        continue;
    }

    $chunks       = $data['chunks'];
    $markdownFull = $data['markdown_full'] ?? '';
    $stats        = $data['stats'] ?? [];

    logMsg(
        sprintf(
            "  Python OK : %d chunks, %d mots, %.1fs",
            $stats['total_chunks'] ?? count($chunks),
            $stats['total_words']  ?? 0,
            $stats['processing_time_s'] ?? 0
        ),
        $logFile
    );

    // ── Atomic swap dans une transaction ──────────────────────────────────
    try {
        $db->beginTransaction();

        // a. Supprimer les anciens chunks (CASCADE → vecteurs orphelins supprimés automatiquement)
        $db->prepare("DELETE FROM bibliotheque_chunks WHERE file_id = ?")
           ->execute([$fileId]);

        // b. Insérer les nouveaux chunks avec section_title + heading_level
        $insChunk = $db->prepare("
            INSERT INTO bibliotheque_chunks (file_id, chunk_index, content, word_count, section_title, heading_level)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($chunks as $i => $chunk) {
            $content      = mb_substr(trim($chunk['content'] ?? ''), 0, 5000, 'UTF-8'); // sécurité OVH
            $wordCount    = (int)($chunk['word_count'] ?? str_word_count($content));
            $sectionTitle = mb_substr(trim($chunk['section_title'] ?? ''), 0, 500, 'UTF-8');
            $headingLevel = (int)($chunk['heading_level'] ?? 0);

            if (empty($content)) continue;

            $insChunk->execute([$fileId, $i, $content, $wordCount, $sectionTitle, $headingLevel]);
        }

        // c. Mettre à jour extracted_text avec le Markdown complet (pour FTS et consultation)
        //    Note : FTS5 est mis à jour via trigger AFTER UPDATE sur bibliotheque_files
        if (!empty($markdownFull)) {
            // Ne pas dépasser la limite SQLite TEXT (peu probable, mais prudence)
            $markdownTruncated = mb_substr($markdownFull, 0, 10000000, 'UTF-8');
            $db->prepare("UPDATE bibliotheque_files SET extracted_text = ?, markdown_status = 'done', updated_at = datetime('now') WHERE id = ?")
               ->execute([$markdownTruncated, $fileId]);
        } else {
            $db->prepare("UPDATE bibliotheque_files SET markdown_status = 'done', updated_at = datetime('now') WHERE id = ?")
               ->execute([$fileId]);
        }

        $db->commit();
        logMsg("  SWAP OK — " . count($chunks) . " chunks en base", $logFile);

    } catch (Exception $e) {
        $db->rollBack();
        logMsg("  ERREUR transaction : " . $e->getMessage(), $logFile);
        markError($db, $fileId, $logFile);
        $errors++;
        continue;
    }

    // ── Vectorisation immédiate des nouveaux chunks ────────────────────────
    $vectorizeResult = vectorizeNewChunks($db, $fileId, $settingsManager, $logFile);
    if (!$vectorizeResult) {
        logMsg("  WARN — vectorisation échouée (sera reprise par vectorize_chunks.php)", $logFile);
    }

    $processed++;
}

// ─── Statut final ─────────────────────────────────────────────────────────────
writeStatus([
    'running'     => false,
    'progress'    => $total,
    'total'       => $total,
    'processed'   => $processed,
    'errors'      => $errors,
    'skipped'     => $skipped,
    'finished_at' => date('Y-m-d H:i:s'),
], $statusFile);

logMsg("=== TERMINÉ : $processed traités, $errors erreurs, $skipped ignorés ===", $logFile);
exit($errors > 0 ? 1 : 0);


// ─── Fonctions utilitaires ────────────────────────────────────────────────────

/**
 * Marque un fichier en 'error' tout en préservant ses anciens chunks (fallback silencieux).
 */
function markError(PDO $db, int $fileId, string $logFile): void
{
    try {
        $db->prepare("UPDATE bibliotheque_files SET markdown_status = 'error', updated_at = datetime('now') WHERE id = ?")
           ->execute([$fileId]);
    } catch (Exception $e) {
        logMsg("  WARN markError : " . $e->getMessage(), $logFile);
    }
}

/**
 * Vectorise les chunks du fichier qui n'ont pas encore de vecteur.
 * Pattern extrait de vectorize_chunks.php (même API OVH).
 *
 * @return bool True si tous les chunks ont été vectorisés avec succès
 */
function vectorizeNewChunks(PDO $db, int $fileId, SettingsManager $settingsManager, string $logFile): bool
{
    $apiUrl = $settingsManager->get('ai_embedding_url', 'http://localhost:11434/api/embeddings');
    $token  = $settingsManager->get('ai_token', '');
    $model  = $settingsManager->get('ai_embedding_model', 'bge-m3');

    // Chunks du fichier sans vecteur
    $stmt = $db->prepare("
        SELECT c.id, c.content
        FROM bibliotheque_chunks c
        LEFT JOIN bibliotheque_vectors v ON c.id = v.chunk_id
        WHERE c.file_id = ? AND v.chunk_id IS NULL
    ");
    $stmt->execute([$fileId]);
    $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($chunks)) {
        return true; // Déjà vectorisé
    }

    $allOk = true;
    foreach ($chunks as $chunk) {
        $text = mb_substr(trim($chunk['content']), 0, 5000, 'UTF-8');
        if (empty($text)) continue;

        // Nettoyage UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = trim((string)$text);

        $success = false;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            
            $isOllamaNative = (strpos($apiUrl, '/api/embeddings') !== false);
            if ($isOllamaNative) {
                $payload = [
                    'model' => $model,
                    'prompt' => $text
                ];
            } else {
                $payload = [
                    'model' => $model,
                    'input' => [$text]
                ];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $headers = ['Content-Type: application/json'];
            if (!empty($token)) $headers[] = 'Authorization: Bearer ' . $token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['data'][0]['embedding'])) {
                    $vector = $data['data'][0]['embedding'];
                    $blob   = pack('f*', ...$vector);
                    $ins    = $db->prepare("INSERT OR REPLACE INTO bibliotheque_vectors (chunk_id, vector) VALUES (?, ?)");
                    $ins->execute([$chunk['id'], $blob]);
                    $success = true;
                    break;
                }
            }
            sleep(1);
        }

        if (!$success) {
            $allOk = false;
            logMsg("    WARN — vectorisation échouée pour chunk #" . $chunk['id'], $logFile);
        }
    }

    return $allOk;
}
