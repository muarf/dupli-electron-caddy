<?php
/**
 * Migration pour ajouter le support du chunking (découpage en morceaux) 
 * pour le système RAG.
 */

require_once __DIR__ . '/../../controler/func.php';
$db = pdo_connect();

try {
    echo "Démarrage de la migration pour le chunking...\n";

    // 1. Création de la table des chunks
    $db->exec("CREATE TABLE IF NOT EXISTS bibliotheque_chunks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id INTEGER NOT NULL,
        chunk_index INTEGER NOT NULL,
        content TEXT NOT NULL,
        word_count INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES bibliotheque_files(id) ON DELETE CASCADE
    )");
    echo "Table 'bibliotheque_chunks' créée ou déjà présente.\n";

    // 2. Index pour accélérer les recherches par file_id
    $db->exec("CREATE INDEX IF NOT EXISTS idx_chunks_file_id ON bibliotheque_chunks(file_id)");
    echo "Index sur 'file_id' créé.\n";

    // 3. Mise à jour du schéma FTS pour inclure les chunks si nécessaire
    // On pourra créer une table FTS dédiée aux chunks pour une recherche ultra-précise par morceau
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS bibliotheque_chunks_fts USING fts5(
        content,
        content='bibliotheque_chunks',
        content_rowid='id'
    )");
    echo "Table FTS pour les chunks prête.\n";

    echo "Migration terminée avec succès !\n";

} catch (Exception $e) {
    echo "ERREUR lors de la migration : " . $e->getMessage() . "\n";
    exit(1);
}
