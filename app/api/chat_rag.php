<?php
require_once __DIR__ . '/../controler/functions/bibliotheque.php';
requireBibliothequeAuth();
/**
 * API Chat RAG (Retrieval-Augmented Generation) - STREAMING MODE (ROBUST & INSTRUMENTED)
 */

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');
ini_set('memory_limit', '2G');
set_time_limit(0); // Empêche PHP de tuer le script (max_execution_time)
ignore_user_abort(false); // Arrête le script si le client se déconnecte / clique sur Stop


function sendStreamEvent($data) {
    global $debugFile;
    $json = json_encode($data);
    if ($json === false) {
        ragDebug("sendStreamEvent: json_encode error");
        return;
    }
    echo "data: " . $json . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
    ragDebug("sendStreamEvent: flush OK");
}

// Libérer le verrou de session PHP pour éviter de bloquer les autres requêtes du navigateur
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// --- INSTRUMENTATION DEBUG ---
$startTime = microtime(true);
$debugFile = __DIR__ . '/../../logs/rag_debug.log';
function ragDebug($msg) {
    global $startTime, $debugFile;
    $elapsed = round(microtime(true) - $startTime, 3);
    $logMsg = sprintf("[%s] [%.3fs] %s\n", date('H:i:s'), $elapsed, $msg);
    file_put_contents($debugFile, $logMsg, FILE_APPEND);
}
// On ajoute une ligne de séparation pour chaque nouvelle requête
ragDebug("--- NOUVELLE REQUÊTE ---");

// Désactiver tout buffering de sortie
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/BibliothequeManager.php';
require_once __DIR__ . '/../models/SettingsManager.php';

// On envoie un premier message pour débloquer l'UI
sendStreamEvent(['type' => 'status', 'message' => '🚀 Initialisation...']);

// 1. RÉCUPÉRATION DU JSON
$input = json_decode(file_get_contents('php://input'), true);
$question = $input['question'] ?? '';
$modelMode = $input['mode'] ?? 'fast'; // 'fast' = Luth, 'pro' = Gemma
$tagFilter = $input['tags'] ?? ''; // Filtrage par tags (inclusion/exclusion)

if (empty($question)) {
    ragDebug("ERREUR: Question vide");
    sendStreamEvent(['type' => 'error', 'message' => 'Question vide']);
    exit;
}

ragDebug("Question reçue: " . $question);

try {
    $db = pdo_connect();
    $libManager = new BibliothequeManager($db);
    $settingsManager = new SettingsManager($db);
    
    // 2. EMBEDDING DE LA QUESTION

    $aiEnabled = (int)$settingsManager->get('ai_enabled', 0);
    if (!$aiEnabled) {
    ragDebug("ERREUR: IA désactivée dans les réglages.");
    sendStreamEvent(['type' => 'error', 'message' => 'L\'IA est actuellement désactivée par l\'administrateur.']);
    exit;
}

    $embeddingUrl = $settingsManager->get('ai_embedding_url', 'http://localhost:11434/api/embeddings');
    $embeddingModel = $settingsManager->get('ai_embedding_model', 'bge-m3');
    $token = $settingsManager->get('ai_token', '');

    // 2. EMBEDDINGS
    ragDebug("Demande d'embedding ($embeddingModel) via $embeddingUrl...");
    $ch = curl_init($embeddingUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $isOllamaNative = (strpos($embeddingUrl, '/api/embeddings') !== false);
    if ($isOllamaNative) {
        $payload = [
            'model' => $embeddingModel,
            'prompt' => $question
        ];
    } else {
        $payload = [
            'model' => $embeddingModel,
            'input' => $question
        ];
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $headers = ['Content-Type: application/json'];
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        $errorMsg = curl_error($ch);
        throw new Exception("Erreur Embeddings (HTTP $httpCode) - URL: $embeddingUrl - Error: $errorMsg");
    }
    
    $qEmbeddingData = json_decode($response, true);
    $qVector = null;
    if ($isOllamaNative && isset($qEmbeddingData['embedding'])) {
        $qVector = $qEmbeddingData['embedding'];
    } elseif (!$isOllamaNative && isset($qEmbeddingData['data'][0]['embedding'])) {
        $qVector = $qEmbeddingData['data'][0]['embedding'];
    }
    
    if (!$qVector || !is_array($qVector)) {
        throw new Exception("Format de réponse d'embedding invalide ou vide.");
    }
    
    ragDebug("Embedding reçu (" . count($qVector) . " dimensions)");

    sendStreamEvent(['type' => 'status', 'message' => '🔍 Recherche des documents...']);

    $selectedFiles = $input['selected_files'] ?? [];
    if (!is_array($selectedFiles)) {
        $selectedFiles = [];
    }
    ragDebug("Recherche hybride. Fichiers sélectionnés: " . count($selectedFiles));

    // 3. RECHERCHE HYBRIDE (Vectorielle + FTS, Top 20)
    $top20Ids = $libManager->searchHybrid($question, $qVector, 20, $tagFilter, $selectedFiles);
    ragDebug("Recherche hybride terminée (Filtrée). Trouvé: " . count($top20Ids));

    // 4. RÉCUPÉRATION DES CHUNKS ET RERANKING
    $uniqueSources = [];
    $context = "";
    
    if (!empty($top20Ids)) {
        $placeholders = implode(',', array_fill(0, count($top20Ids), '?'));
        $stmt = $db->prepare("SELECT c.id, c.content, c.section_title, b.id as file_id, b.filename as title 
                             FROM bibliotheque_chunks c 
                             JOIN bibliotheque_files b ON c.file_id = b.id 
                             WHERE c.id IN ($placeholders)");
        $stmt->execute($top20Ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $idToRow = [];
        foreach($rows as $row) { $idToRow[$row['id']] = $row; }
        $orderedRows = [];
        foreach($top20Ids as $id) { if(isset($idToRow[$id])) $orderedRows[] = $idToRow[$id]; }

        $documents = array_map(function($r) { return $r['content']; }, $orderedRows);

        $totalCharCount = array_sum(array_map('strlen', $documents));
        ragDebug("Préparation Payload Reranker: " . count($documents) . " docs, " . $totalCharCount . " caractères au total.");

        $payload = json_encode([
            'query' => $question,
            'documents' => $documents,
            'top_n' => 20
        ]);
        
        $rerankerUrl = $settingsManager->get('ai_reranker_url', 'http://localhost:11437/rerank');
        if (!empty($rerankerUrl)) {
            sendStreamEvent(['type' => 'status', 'message' => '🧠 Tri intelligent des sources...']);
            ragDebug("Appel au Re-ranker ($rerankerUrl) pour " . count($documents) . " documents...");
            $chR = curl_init($rerankerUrl);
            curl_setopt($chR, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chR, CURLOPT_POST, true);
            curl_setopt($chR, CURLOPT_POSTFIELDS, $payload);
            
            $headersR = ['Content-Type: application/json'];
            if (!empty($token)) {
                $headersR[] = 'Authorization: Bearer ' . $token;
            }
            curl_setopt($chR, CURLOPT_HTTPHEADER, $headersR);
            curl_setopt($chR, CURLOPT_TIMEOUT, 90); // Porté à 90s pour le traitement des gros documents
            
            // Heartbeat progressif pendant le tri (toutes les 10s) - COMMENTAIRE SSE
            curl_setopt($chR, CURLOPT_NOPROGRESS, false);
            curl_setopt($chR, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) {
                if (connection_aborted()) {
                    ragDebug("Requête interrompue par l'utilisateur pendant le tri. Arrêt cURL Reranker.");
                    return 1; // Arrête cURL Reranker immédiatement
                }
                static $lastH = 0;
                if (time() - $lastH >= 10) {
                    $lastH = time();
                    ragDebug("Heartbeat progressif (tri en cours...)");
                    echo ": heartbeat\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                    ragDebug("Heartbeat flush OK");
                }
                return 0;
            });
        
            $t1 = microtime(true);
            $resR = curl_exec($chR);
            $t2 = microtime(true);
            $httpCodeR = curl_getinfo($chR, CURLINFO_HTTP_CODE);
            $curlError = curl_error($chR);
            curl_close($chR);

            ragDebug("Réponse Re-ranker reçue en " . round($t2 - $t1, 3) . "s (HTTP $httpCodeR)");
        } else {
            $httpCodeR = 0;
            $resR = false;
            $curlError = "Reranker URL is empty";
            ragDebug("Re-ranker désactivé (URL vide)");
        }

        if ($httpCodeR === 200 && $resR) {
            $rankData = json_decode($resR, true);
            if (isset($rankData['results'])) {
                ragDebug("Re-ranker OK (" . count($rankData['results']) . " résultats)");
                
                $rankedSources = [];
                foreach ($rankData['results'] as $res) {
                    $idx = $res['index'];
                    $source = $orderedRows[$idx];
                    $source['relevance'] = $res['relevance_score'];
                    $rankedSources[] = $source;
                }
                
                // --- DÉDOUBLONNAGE INTELLIGENT PAR NOM DE FICHIER ---
                $groupedSources = [];
                foreach ($rankedSources as $i => $src) {
                    $isTop = ($i < 5); // Les 5 meilleurs pour le contexte IA
                    
                    if ($isTop) {
                        $sectionLabel = !empty($src['section_title']) ? ' — ' . $src['section_title'] : '';
                        $context .= "[Source: " . $src['title'] . $sectionLabel . "]\n" . $src['content'] . "\n\n";
                    }
                    
                    // Normalisation du nom pour le dédoublonnage
                    $cleanName = strtolower($src['title']);
                    $cleanName = preg_replace('/\.(pdf|epub|txt)$/i', '', $cleanName); // Enlever extension
                    $cleanName = preg_replace('/[ \-_](ppp|cahier|imposition|v[0-9]+|copie)$/i', '', $cleanName); // Enlever suffixes
                    $cleanName = trim($cleanName);
                    
                    // Création d'une clé unique sans accents
                    $key = iconv('UTF-8', 'ASCII//TRANSLIT', $cleanName);
                    $key = preg_replace('/[^a-z0-9]/', '', strtolower($key));

                    if (!isset($groupedSources[$key])) {
                        $groupedSources[$key] = [
                            'id' => $src['file_id'],
                            'title' => $src['title'],
                            'section_title' => $src['section_title'] ?? '',
                            'is_top' => $isTop,
                            'score' => round($src['relevance'], 2),
                            'chunks' => 1,
                            'contents' => [$src['content']]
                        ];
                    } else {
                        $groupedSources[$key]['chunks']++;
                        if (count($groupedSources[$key]['contents']) < 3) {
                            $groupedSources[$key]['contents'][] = $src['content'];
                        }
                        if ($isTop) $groupedSources[$key]['is_top'] = true;
                        // Garder la section_title du chunk le plus pertinent (premier top)
                        if ($isTop && empty($groupedSources[$key]['section_title'])) {
                            $groupedSources[$key]['section_title'] = $src['section_title'] ?? '';
                        }
                        $groupedSources[$key]['score'] = max($groupedSources[$key]['score'], round($src['relevance'], 2));
                    }
                }
                $uniqueSources = array_values($groupedSources);
            }
        } else {
            ragDebug("ERREUR Re-ranker: " . ($curlError ?: "HTTP $httpCodeR") . ". Mode dégradé.");
            // Mode dégradé : on prend les 5 meilleurs vecteurs bruts
            $groupedSources = [];
            foreach (array_slice($orderedRows, 0, 5) as $row) {
                $sectionLabel = !empty($row['section_title']) ? ' — ' . $row['section_title'] : '';
                $context .= "[Source: " . $row['title'] . $sectionLabel . "]\n" . $row['content'] . "\n\n";
                if (!isset($groupedSources[$row['file_id']])) {
                    $groupedSources[$row['file_id']] = [
                        'id' => $row['file_id'], 
                        'title' => $row['title'],
                        'section_title' => $row['section_title'] ?? '',
                        'is_top' => true, 
                        'score' => 0, 
                        'chunks' => 1,
                        'content' => $row['content']
                    ];
                }
            }
            $uniqueSources = array_values($groupedSources);
        }
    }

    ragDebug("Sources identifiées: " . count($uniqueSources));
    
    $sourcesData = ['type' => 'status', 'message' => '📚 Sources identifiées (' . count($uniqueSources) . ')', 'sources' => $uniqueSources];
    $jsonTest = json_encode($sourcesData);
    
    if ($jsonTest === false) {
        ragDebug("ERREUR FATALE JSON: " . json_last_error_msg());
        foreach ($uniqueSources as $idx => $src) {
            if (json_encode($src) === false) {
                ragDebug("Culprit trouvé ! Source #$idx - Titre: " . $src['title'] . " - Erreur: " . json_last_error_msg());
            }
        }
    } else {
        ragDebug("JSON encode OK (taille: " . strlen($jsonTest) . " octets)");
    }

    sendStreamEvent($sourcesData);
    ragDebug("Stream event sources envoyé.");

    sendStreamEvent(['type' => 'status', 'message' => '📖 Rédaction de la réponse...']);
    ragDebug("Stream event redaction envoyé.");

    // 5. PROMPT ET GÉNÉRATION
    ragDebug("Récupération du system prompt...");
    $systemPrompt = $settingsManager->get('ai_system_prompt', "Tu es un assistant expert et rigoureux. Tu adhères aux idées du contexte. Tu es anarchiste. Utilise UNIQUEMENT le contexte suivant pour répondre. NE FAIS PAS appel à tes connaissances personnelles. Si la réponse n'est pas dans le texte, dis-le simplement.");
    ragDebug("System prompt récupéré (len: " . strlen($systemPrompt) . ")");
    
    // Remplacement des placeholders optionnels ({context}) si l'utilisateur les a utilisés,
    // sinon on rajoute le contexte à la fin du prompt.
    if (strpos($systemPrompt, '{context}') !== false) {
        $systemPromptText = str_replace('{context}', $context, $systemPrompt);
    } else {
        $systemPromptText = $systemPrompt . "\n\nCONTEXTE :\n" . $context;
    }

    if ($modelMode === 'fast') {
        $prompt = "<|im_start|>system\n" . $systemPromptText . "<|im_end|>\n<|im_start|>user\n$question<|im_end|>\n<|im_start|>assistant\n";
        $llmUrl = $settingsManager->get('ai_llm_url', 'http://localhost:11436/completion');
    } elseif ($modelMode === 'nemotron') {
        $prompt = "<|im_start|>system\n" . $systemPromptText . "<|im_end|>\n<|im_start|>user\n$question<|im_end|>\n<|im_start|>assistant\n";
        $llmUrl = $settingsManager->get('ai_llm_url_nemotron', 'http://localhost:11438/completion');
    } else {
        $prompt = "<|turn|>system\n" . $systemPromptText . "<|turn|>\n<|turn|>user\n$question<|turn|>\n<|turn|>model\n<|channel|>thought\n";
        $llmUrl = $settingsManager->get('ai_llm_url_pro', 'http://localhost:11435/completion');
    }

    ragDebug("Appel à l'IA ($modelMode) via $llmUrl...");
    $ch = curl_init($llmUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // Aucune limite de temps pour cURL

    
    // Prise en charge des endpoints type OpenAI (/v1/chat/completions) vs llama.cpp (/completion)
    $isChatCompletions = strpos($llmUrl, '/chat/completions') !== false;
    
    if ($isChatCompletions) {
        $postData = [
            'model' => $modelMode, // ou un modèle spécifique si configuré
            'messages' => [
                ['role' => 'system', 'content' => $systemPromptText],
                ['role' => 'user', 'content' => $question]
            ],
            'stream' => true,
            'max_tokens' => 1000,
            'temperature' => 0.1
        ];
    } else {
        $postData = [
            'prompt' => $prompt,
            'stream' => true,
            'n_predict' => 1000,
            'temperature' => 0.1,
            'stop' => ["<|im_end|>", "<|turn|>", "</s>"]
        ];
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    
    $headersLlm = ['Content-Type: application/json'];
    if (!empty($token)) {
        $headersLlm[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headersLlm);

    $fullResponse = "";
    
    // Heartbeat mechanism to prevent UI timeout during long prompt processing
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    $lastHeartbeat = microtime(true);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $download_size, $downloaded, $upload_size, $uploaded) use (&$lastHeartbeat) {
        if (connection_aborted()) {
            ragDebug("Requête interrompue par l'utilisateur (AbortController). Arrêt cURL.");
            return 1; // Arrête cURL immédiatement
        }
        $now = microtime(true);
        if ($now - $lastHeartbeat > 10.0) { // Send heartbeat every 10 seconds
            ragDebug("Heartbeat LLM progressif (réflexion en cours...)");
            sendStreamEvent(['type' => 'status', 'message' => 'L\'IA réfléchit...']);
            $lastHeartbeat = $now;
        }
        return 0; // Return 0 to continue
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullResponse) {
        if (connection_aborted()) {
            ragDebug("Requête interrompue par l'utilisateur (AbortController). Arrêt cURL.");
            return 0; // Stoppe la réception cURL
        }
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $payload = substr($line, 6);
                if (trim($payload) === '[DONE]') continue;
                
                $json = json_decode($payload, true);
                if ($json) {
                    $content = '';
                    // Format llama.cpp
                    if (isset($json['content'])) {
                        $content = $json['content'];
                    } 
                    // Format OpenAI / DeepSeek reasoning
                    elseif (isset($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];
                    }
                    elseif (isset($json['choices'][0]['delta']['reasoning_content'])) {
                        $content = $json['choices'][0]['delta']['reasoning_content'];
                    }
                    elseif (isset($json['reasoning_content'])) {
                        $content = $json['reasoning_content'];
                    }
                    if ($content !== '') {
                        $fullResponse .= $content;
                        ragDebug("IA Content: " . $content);
                        sendStreamEvent(['type' => 'content', 'content' => $content]);
                    }
                }
            }
        }
        return strlen($data);
    });

    $resLlm = curl_exec($ch);
    $httpCodeLlm = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrorLlm = curl_error($ch);
    curl_close($ch);

    if ($httpCodeLlm !== 200) {
        ragDebug("ERREUR IA (HTTP $httpCodeLlm): " . $curlErrorLlm);
        sendStreamEvent(['type' => 'error', 'message' => "L'IA a rencontré un problème (Code $httpCodeLlm)"]);
    } else {
        ragDebug("Génération terminée (total chars: " . strlen($fullResponse) . ")");
    }
    
    ragDebug("TERMINÉ avec succès.");
    sendStreamEvent(['type' => 'done']);

    // Log historique structuré pour le debug
    $historyFile = __DIR__ . '/../../logs/chat_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    if (!is_array($history)) $history = [];
    
    // Extraire la pensée si présente et la retirer de la réponse
    $thoughtMatch = "";
    $cleanResponse = $fullResponse;
    if (preg_match('/<think>(.*?)<\/think>/s', $fullResponse, $matches)) {
        $thoughtMatch = $matches[1];
        $cleanResponse = str_replace($matches[0], '', $fullResponse);
    }

    array_unshift($history, [
        'timestamp' => date('Y-m-d H:i:s'),
        'question' => $question,
        'model' => $modelMode,
        'prompt' => $prompt,
        'thought' => trim($thoughtMatch),
        'response' => trim($cleanResponse),
        'sources' => $uniqueSources,
        'elapsed' => round(microtime(true) - $startTime, 1)
    ]);
    
    // Garder les 50 derniers
    $history = array_slice($history, 0, 50);
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

    ragDebug("TERMINÉ avec succès.");

} catch (Throwable $e) {
    ragDebug("ERREUR CRITIQUE: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendStreamEvent(['type' => 'error', 'message' => $e->getMessage()]);
}
