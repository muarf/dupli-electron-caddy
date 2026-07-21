<?php
/**
 * Migration : Ajouter les colonnes pour le pipeline Markdown RAG (Docling)
 *
 * - bibliotheque_files.markdown_status : suivi de l'extraction Docling
 *   Valeurs : 'raw' (PdfParser seulement), 'processing', 'done', 'error'
 * - bibliotheque_chunks.section_title  : titre de section hiérarchique issu du Markdown
 * - bibliotheque_chunks.heading_level  : niveau de heading (0 = aucun, 1 = #, 2 = ##, etc.)
 */

function migrate_update_rag_markdown(PDO $db) {
    echo "➡️  Migration update_rag_markdown...\n";

    // 1. markdown_status sur bibliotheque_files
    try {
        $db->exec("ALTER TABLE bibliotheque_files ADD COLUMN markdown_status TEXT DEFAULT 'raw'");
        echo "   ✓ Colonne 'markdown_status' ajoutée à bibliotheque_files\n";
    } catch (Exception $e) {
        // Colonne déjà présente — pas bloquant
        echo "   ~ Colonne 'markdown_status' déjà présente (ignoré)\n";
    }

    // 2. section_title sur bibliotheque_chunks
    try {
        $db->exec("ALTER TABLE bibliotheque_chunks ADD COLUMN section_title TEXT");
        echo "   ✓ Colonne 'section_title' ajoutée à bibliotheque_chunks\n";
    } catch (Exception $e) {
        echo "   ~ Colonne 'section_title' déjà présente (ignoré)\n";
    }

    // 3. heading_level sur bibliotheque_chunks
    try {
        $db->exec("ALTER TABLE bibliotheque_chunks ADD COLUMN heading_level INTEGER DEFAULT 0");
        echo "   ✓ Colonne 'heading_level' ajoutée à bibliotheque_chunks\n";
    } catch (Exception $e) {
        echo "   ~ Colonne 'heading_level' déjà présente (ignoré)\n";
    }

    // Marquer tous les fichiers existants comme 'raw' (à traiter par Docling)
    try {
        $db->exec("UPDATE bibliotheque_files SET markdown_status = 'raw' WHERE markdown_status IS NULL");
        echo "   ✓ Fichiers existants marqués 'raw'\n";
    } catch (Exception $e) {
        echo "   ~ Mise à jour markdown_status ignorée : " . $e->getMessage() . "\n";
    }

    echo "✅ Migration update_rag_markdown terminée\n\n";
}
