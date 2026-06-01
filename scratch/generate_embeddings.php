<?php
/**
 * Script de vectorisation de masse
 * Envoie les morceaux de texte à Ollama (bge-m3) et stocke les vecteurs dans SQLite.
 */

require_once __DIR__ . '/../app/controler/func.php';
require_once __DIR__ . '/../app/models/BibliothequeManager.php';

$db = pdo_connect();
$ollamaUrl = "http://127.0.0.1:11434/api/embeddings";
$model = "bge-m3";

// On récupère les chunks qui n'ont pas encore de vecteur
$sql = "
    SELECT c.id, c.content 
    FROM bibliotheque_chunks c
    LEFT JOIN bibliotheque_vectors v ON v.chunk_id = c.id
    WHERE v.chunk_id IS NULL
    ORDER BY c.id ASC
";

try {
    $stmt = $db->query($sql);
    $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($chunks);

    if ($total === 0) {
        echo "Tous les morceaux sont déjà vectorisés.\n";
        exit;
    }

    echo "Démarrage de la vectorisation de $total morceaux avec $model...\n";
    echo "Cela peut prendre du temps (plusieurs heures sur CPU).\n\n";

    $count = 0;
    foreach ($chunks as $chunk) {
        $count++;
        
        // Appel Ollama
        $ch = curl_init($ollamaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'prompt' => $chunk['content']
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['embedding'])) {
                // Conversion en binaire (Float32) pour économiser de la place et gagner en vitesse
                $vectorBinary = pack('f*', ...$data['embedding']);
                
                $ins = $db->prepare("INSERT INTO bibliotheque_vectors (chunk_id, vector) VALUES (?, ?)");
                $ins->execute([$chunk['id'], $vectorBinary]);
                
                if ($count % 10 === 0 || $count === $total) {
                    $percent = round(($count / $total) * 100, 1);
                    echo "[$count/$total] $percent% terminés...\r";
                }
            }
        } else {
            echo "\nERREUR sur le morceau {$chunk['id']} (Code: $httpCode) : $response\n";
            // On s'arrête si Ollama ne répond plus
            if ($httpCode === 0) exit(1);
        }
        
        // Petite pause pour libérer du CPU pour le Chat et éviter de saturer le tunnel
        usleep(500000); // 0.5 seconde de pause
    }

    echo "\n\nTerminé ! Tous les vecteurs ont été générés.\n";

} catch (Exception $e) {
    echo "\nERREUR FATALE : " . $e->getMessage() . "\n";
    exit(1);
}
