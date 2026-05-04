<?php
/**
 * Script de vectorisation Turbo via OVH AI Endpoints
 * Modèle : Qwen3-Embedding-8B (Haute Précision)
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$db = pdo_connect();
require_once __DIR__ . '/../models/SettingsManager.php';
$settingsManager = new SettingsManager($db);

$apiUrl = $settingsManager->get('ai_embedding_url', 'http://localhost:11434/api/embeddings');
$token = $settingsManager->get('ai_token', '');
$model = $settingsManager->get('ai_embedding_model', 'bge-m3');
$batchSize = 1; // Un seul par un pour éviter de dépasser les limites de tokens

echo "[" . date('H:i:s') . "] DÉMARRAGE VECTORISATION (Endpoint : $apiUrl - Modèle : $model)\n";

// Récupération des chunks à traiter
$sql = "SELECT c.id, c.content 
        FROM bibliotheque_chunks c 
        LEFT JOIN bibliotheque_vectors v ON c.id = v.chunk_id 
        WHERE v.chunk_id IS NULL";

$stmt = $db->query($sql);
$chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($chunks);
echo "[" . date('H:i:s') . "] Trouvé $total chunks à vectoriser.\n";

if ($total === 0) {
    echo "Tout est déjà vectorisé ! 🎉\n";
    exit;
}

function sanitizeText($text) {
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    $text = str_replace("\xEF\xBF\xBD", " ", $text); 
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    $text = trim(mb_convert_encoding($text, 'UTF-8', 'UTF-8'));
    
    // SÉCURITÉ : On tronque à 5000 caractères pour ne JAMAIS dépasser la limite de tokens d'OVH (8192)
    if (mb_strlen($text, 'UTF-8') > 5000) {
        $text = mb_substr($text, 0, 5000, 'UTF-8');
    }
    return $text;
}

$processed = 0;
$errors = 0;

// Traitement par batchs
for ($i = 0; $i < $total; $i += $batchSize) {
    $batch = array_slice($chunks, $i, $batchSize);
    $batchTexts = [];
    $batchIds = [];

    foreach ($batch as $c) {
        $clean = sanitizeText($c['content']);
        if (!empty($clean)) {
            $batchTexts[] = $clean;
            $batchIds[] = $c['id'];
        }
    }

    if (empty($batchTexts)) continue;

    $maxRetries = 3;
    $success = false;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'input' => $batchTexts
        ]));
        $headers = ['Content-Type: application/json'];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['data']) && is_array($data['data'])) {
                $db->beginTransaction();
                foreach ($data['data'] as $item) {
                    $idx = $item['index'];
                    $chunkId = $batchIds[$idx];
                    $vector = $item['embedding'];
                    $blob = pack('f*', ...$vector);
                    
                    $ins = $db->prepare("INSERT OR REPLACE INTO bibliotheque_vectors (chunk_id, vector) VALUES (?, ?)");
                    $ins->execute([$chunkId, $blob]);
                }
                $db->commit();
                $success = true;
                break;
            }
        }

        echo "[" . date('H:i:s') . "] Échec batch à $i (HTTP $httpCode). Tentative $attempt/$maxRetries. Réponse: " . substr($response, 0, 200) . "...\n";
        sleep(2);
    }

    if ($success) {
        $processed += count($batchTexts);
    } else {
        $errors += count($batchTexts);
        echo "[" . date('H:i:s') . "] ÉCHEC DÉFINITIF pour le batch démarrant à $i\n";
    }

    echo "[" . date('H:i:s') . "] Progression : $processed / $total (" . round(($processed/$total)*100) . "%) | Erreurs: $errors\n";
}

echo "TERMINE ! $processed chunks traités et vectorisés.\n";
