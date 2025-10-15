<?php
/**
 * Script de test pour vérifier les corrections du comptage des duplicopieurs
 */

// Inclure les fonctions nécessaires
require_once __DIR__ . '/app/controler/functions/database.php';
require_once __DIR__ . '/app/controler/functions/machines.php';
require_once __DIR__ . '/app/controler/functions/consommation.php';

echo "=== Test des corrections du comptage des duplicopieurs ===\n\n";

try {
    // 1. Tester la connexion à la base de données
    echo "1. Test de connexion à la base de données...\n";
    $db = pdo_connect();
    echo "✓ Connexion réussie\n\n";
    
    // 2. Lister les duplicopieurs disponibles
    echo "2. Duplicopieurs disponibles :\n";
    $query = $db->query('SELECT id, marque, modele FROM duplicopieurs WHERE actif = 1 ORDER BY id');
    $duplicopieurs = $query->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicopieurs)) {
        echo "⚠ Aucun duplicopieur trouvé dans la base de données\n";
    } else {
        foreach ($duplicopieurs as $dup) {
            echo "  - ID: {$dup['id']}, Marque: {$dup['marque']}, Modèle: {$dup['modele']}\n";
        }
    }
    echo "\n";
    
    // 3. Tester la fonction get_duplicopieur_id_by_name
    echo "3. Test de get_duplicopieur_id_by_name :\n";
    if (!empty($duplicopieurs)) {
        $test_dup = $duplicopieurs[0];
        $test_name = $test_dup['marque'] . ' ' . $test_dup['modele'];
        $found_id = get_duplicopieur_id_by_name($test_name);
        echo "  - Recherche de '{$test_name}' : ID = " . ($found_id ?: 'null') . "\n";
        
        if ($found_id == $test_dup['id']) {
            echo "✓ Fonction get_duplicopieur_id_by_name fonctionne correctement\n";
        } else {
            echo "✗ Problème avec get_duplicopieur_id_by_name\n";
        }
    }
    echo "\n";
    
    // 4. Tester la fonction get_last_number avec ID
    echo "4. Test de get_last_number avec ID :\n";
    if (!empty($duplicopieurs)) {
        $test_dup = $duplicopieurs[0];
        $last_counters = get_last_number('dupli', $test_dup['id']);
        echo "  - Duplicopieur ID {$test_dup['id']} :\n";
        echo "    * Master AV: {$last_counters['master_av']}\n";
        echo "    * Passage AV: {$last_counters['passage_av']}\n";
        echo "✓ Fonction get_last_number avec ID fonctionne\n";
    }
    echo "\n";
    
    // 5. Tester la fonction get_last_number sans ID (fallback)
    echo "5. Test de get_last_number sans ID (fallback) :\n";
    $last_counters_fallback = get_last_number('dupli');
    echo "  - Fallback :\n";
    echo "    * Master AV: {$last_counters_fallback['master_av']}\n";
    echo "    * Passage AV: {$last_counters_fallback['passage_av']}\n";
    echo "✓ Fonction get_last_number sans ID fonctionne\n\n";
    
    // 6. Tester la fonction get_cons
    echo "6. Test de get_cons :\n";
    if (!empty($duplicopieurs)) {
        $test_dup = $duplicopieurs[0];
        $test_name = $test_dup['marque'] . ' ' . $test_dup['modele'];
        $cons_data = get_cons($test_name);
        echo "  - Consommables pour '{$test_name}' :\n";
        if (isset($cons_data['master'])) {
            echo "    * Master - nb_actuel: {$cons_data['master']['nb_actuel']}\n";
        }
        if (isset($cons_data['encre'])) {
            echo "    * Encre - nb_actuel: {$cons_data['encre']['nb_actuel']}\n";
        }
        echo "✓ Fonction get_cons fonctionne\n";
    }
    echo "\n";
    
    echo "=== Tests terminés avec succès ===\n";
    
} catch (Exception $e) {
    echo "✗ Erreur lors des tests : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
}
?>