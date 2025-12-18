<?php
/**
 * Migration: Permettre plusieurs sessions actives par utilisateur
 */

function migrate_allow_multiple_active_sessions($db) {
    echo "➡️  Autorisation sessions multiples...\n";
    
    try {
        // Supprimer l'index unique restrictif
        $db->exec("DROP INDEX IF EXISTS idx_sessions_active_contact");
        echo "   ✓ Index unique restrictif supprimé\n";
        
        // On garde un index simple pour les performances de recherche
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sessions_contact_status ON print_sessions(contact, status)");
        echo "   ✓ Index de recherche optimisé créé\n";
        
    } catch (Exception $e) {
        echo "   ⚠️  Erreur migration sessions multiples: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Migration sessions multiples terminée\n\n";
}
