<?php
/**
 * Mise à jour de la base de données pour le RAG
 * Crée les tables nécessaires pour le chunking et les vecteurs
 */
require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';

try {
    $db = pdo_connect();
    
    echo "Initialisation des tables RAG...\n";

    // 1. Table des morceaux de texte (chunks)
    $db->exec("CREATE TABLE IF NOT EXISTS bibliotheque_chunks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id INTEGER NOT NULL,
        chunk_index INTEGER NOT NULL,
        content TEXT NOT NULL,
        word_count INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES bibliotheque_files(id) ON DELETE CASCADE
    )");
    echo "OK: bibliotheque_chunks\n";

    // 2. Table FTS pour les chunks (recherche hybride)
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS bibliotheque_chunks_fts USING fts5(content, content='bibliotheque_chunks', content_rowid='id')");
    echo "OK: bibliotheque_chunks_fts\n";

    // 3. Table des vecteurs (VEC)
    // Note: SQLite n'a pas de type Vector natif sans extension, on stocke en BLOB (float32)
    $db->exec("CREATE TABLE IF NOT EXISTS bibliotheque_vectors (
        chunk_id INTEGER PRIMARY KEY,
        vector BLOB NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chunk_id) REFERENCES bibliotheque_chunks(id) ON DELETE CASCADE
    )");
    echo "OK: bibliotheque_vectors\n";

    // 4. Indexation initiale si nécessaire (triguer par l'utilisateur plus tard)
    
    echo "Mise à jour terminée avec succès.\n";

} catch (Exception $e) {
    die("ERREUR: " . $e->getMessage() . "\n");
}
