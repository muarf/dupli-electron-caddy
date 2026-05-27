<?php
/**
 * Migration pour ajouter le support du chunking (découpage en morceaux) 
 * pour le système RAG.
 */

function migrate_add_bibliotheque_chunks(PDO $db) {
    echo "➡️  Migration pour le chunking...\n";

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
    echo "   ✓ Table 'bibliotheque_chunks' créée\n";

    // 2. Index pour accélérer les recherches par file_id
    $db->exec("CREATE INDEX IF NOT EXISTS idx_chunks_file_id ON bibliotheque_chunks(file_id)");
    echo "   ✓ Index sur 'file_id' créé\n";

    // 3. Mise à jour du schéma FTS pour inclure les chunks
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS bibliotheque_chunks_fts USING fts5(
        content,
        content='bibliotheque_chunks',
        content_rowid='id'
    )");
    echo "   ✓ Table FTS pour les chunks prête\n";

    echo "✅ Migration chunking terminée\n\n";
}
