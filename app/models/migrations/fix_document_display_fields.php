<?php
/**
 * Script de correction pour finaliser la migration add_document_display_fields
 * À exécuter une seule fois pour corriger les données existantes
 */

require_once __DIR__ . '/../../controler/functions/database.php';

echo "=== Correction migration add_document_display_fields ===\n\n";

try {
    $db = pdo_connect();
    
    // Vérifier si les colonnes existent
    $pragma = $db->query("PRAGMA table_info(print_jobs)");
    $columns = $pragma->fetchAll(PDO::FETCH_ASSOC);
    $hasFullPath = false;
    $hasDisplayName = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'document_full_path') $hasFullPath = true;
        if ($col['name'] === 'document_display_name') $hasDisplayName = true;
    }
    
    if (!$hasFullPath || !$hasDisplayName) {
        echo "❌ Les colonnes n'existent pas encore. La migration principale doit d'abord être exécutée.\n";
        exit(1);
    }
    
    echo "✓ Les colonnes existent\n\n";
    
    // Migrer les données
    echo "Recherche des enregistrements à migrer...\n";
    $jobs = $db->query("SELECT id, document FROM print_jobs WHERE document_full_path IS NULL OR document_display_name IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($jobs) === 0) {
        echo "✓ Aucun enregistrement à migrer. Tout est déjà à jour.\n";
        exit(0);
    }
    
    echo "Trouvé " . count($jobs) . " enregistrement(s) à migrer\n\n";
    
    $stmt = $db->prepare("UPDATE print_jobs SET document_full_path = ?, document_display_name = ? WHERE id = ?");
    $updated = 0;
    
    foreach ($jobs as $job) {
        $fullPath = $job['document'];
        $displayName = basename($fullPath); // Utilise PHP basename() qui gère à la fois \ et /
        
        $stmt->execute([$fullPath, $displayName, $job['id']]);
        $updated++;
        
        // Afficher un point tous les 10 jobs pour montrer la progression
        if ($updated % 10 === 0) {
            echo ".";
        }
    }
    
    echo "\n\n✅ Migration terminée avec succès : $updated enregistrement(s) mis à jour\n";
    
    // Afficher quelques exemples
    echo "\nExemples de migration :\n";
    $examples = $db->query("SELECT document, document_display_name FROM print_jobs LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($examples as $ex) {
        echo "  • {$ex['document']} → {$ex['document_display_name']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
