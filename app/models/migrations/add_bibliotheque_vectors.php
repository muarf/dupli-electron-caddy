<?php
/**
 * Migration pour ajouter le stockage des vecteurs (embeddings)
 */

require_once __DIR__ . '/../../controler/func.php';
$db = pdo_connect();

try {
    echo "Démarrage de la migration pour les vecteurs...\n";

    // Table pour les vecteurs
    // On stocke le vecteur sous forme de BLOB (Float32 binary) pour la performance
    $db->exec("CREATE TABLE IF NOT EXISTS bibliotheque_vectors (
        chunk_id INTEGER PRIMARY KEY,
        vector BLOB NOT NULL,
        FOREIGN KEY (chunk_id) REFERENCES bibliotheque_chunks(id) ON DELETE CASCADE
    )");
    
    echo "Table 'bibliotheque_vectors' créée.\n";
    echo "Migration terminée avec succès !\n";

} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
