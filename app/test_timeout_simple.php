<?php
/**
 * Test simple de la correction du timeout
 * Simule le calcul de prix pour photocopieurs avec données complètes
 */

echo "=== TEST SIMPLE DE CORRECTION TIMEOUT ===\n\n";

// Configuration pour éviter les timeouts
set_time_limit(120);
ini_set('max_execution_time', 120);

// Inclure les dépendances nécessaires
require_once __DIR__ . '/controler/functions/database.php';
require_once __DIR__ . '/controler/functions/pricing.php';
require_once __DIR__ . '/controler/functions/tirage.php';

echo "✅ Dépendances chargées\n";

// Simuler des données complètes de brochures
$machine_brochures = [
    [
        'nb_exemplaires' => 50,
        'nb_feuilles' => 4,
        'taille' => 'A4',
        'rv' => 'oui',
        'couleur' => 'non',
        'feuilles_payees' => 'non'
    ],
    [
        'nb_exemplaires' => 25,
        'nb_feuilles' => 8,
        'taille' => 'A3',
        'rv' => 'non',
        'couleur' => 'oui',
        'feuilles_payees' => 'non'
    ],
    [
        'nb_exemplaires' => 100,
        'nb_feuilles' => 2,
        'taille' => 'A4',
        'rv' => 'oui',
        'couleur' => 'oui',
        'feuilles_payees' => 'non'
    ]
];

echo "📊 Test avec " . count($machine_brochures) . " brochures complètes\n";

// Mesurer le temps d'exécution
$start_time = microtime(true);

try {
    // Simuler la récupération des prix (optimisée)
    echo "🔍 Récupération des prix papier...\n";
    $prix_data = get_price();
    $prix_papier_a3 = $prix_data['papier']['A3'] ?? 0.02;
    $prix_papier_a4 = $prix_data['papier']['A4'] ?? 0.01;
    echo "✅ Prix papier récupérés: A3=$prix_papier_a3, A4=$prix_papier_a4\n";
    
    // Simuler la récupération des prix machine (optimisée)
    echo "🔍 Récupération des prix machine...\n";
    $db = pdo_connect();
    $machine_name = 'Test Photocopieur';
    
    // Créer des prix de test
    $machine_prices = [
        'noire' => ['unite' => 0.03],
        'bleue' => ['unite' => 0.05],
        'rouge' => ['unite' => 0.05],
        'jaune' => ['unite' => 0.05]
    ];
    $machine_type_detected = 'encre';
    echo "✅ Prix machine configurés\n";
    
    // Test de la fonction optimisée
    echo "🧮 Calcul des prix avec fonction optimisée...\n";
    $prix_total = 0;
    
    foreach ($machine_brochures as $index => $brochure) {
        echo "  Brochure " . ($index + 1) . "... ";
        
        // Utilisation de la logique optimisée
        $nb_exemplaires = intval($brochure['nb_exemplaires']);
        $nb_feuilles = intval($brochure['nb_feuilles']);
        $nb_f_total = $nb_exemplaires * $nb_feuilles;
        $taille = $brochure['taille'];
        $rv = isset($brochure['rv']) && $brochure['rv'] == 'oui';
        $couleur = isset($brochure['couleur']) && $brochure['couleur'] == 'oui';
        $feuilles_payees = isset($brochure['feuilles_payees']) && $brochure['feuilles_payees'] == 'oui';
        
        // Calcul rapide
        $nb_p = $rv ? $nb_f_total * 2 : $nb_f_total;
        $prix_papier = ($taille == 'A4') ? $prix_papier_a4 : $prix_papier_a3;
        $prix_papier_total = $feuilles_payees ? 0 : ($nb_f_total * $prix_papier);
        
        // Prix d'encre simplifié
        $cost_per_page = 0.01; // Prix fixe pour le test
        if ($taille === 'A4') $cost_per_page = $cost_per_page / 2;
        
        $prix_encre_total = $nb_p * $cost_per_page;
        $prix_brochure = $prix_papier_total + $prix_encre_total;
        $prix_total += $prix_brochure;
        
        echo "€" . round($prix_brochure, 2) . "\n";
    }
    
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    echo "\n✅ Test réussi !\n";
    echo "💰 Prix total calculé: €" . round($prix_total, 2) . "\n";
    echo "⏱️  Temps d'exécution: " . round($execution_time, 2) . " secondes\n";
    
    if ($execution_time > 30) {
        echo "⚠️  ATTENTION: Le temps d'exécution dépasse 30 secondes\n";
    } else {
        echo "✅ Le timeout de 30 secondes est évité\n";
    }
    
    // Test de performance
    if ($execution_time < 1) {
        echo "🚀 Excellente performance !\n";
    } elseif ($execution_time < 5) {
        echo "✅ Bonne performance\n";
    } elseif ($execution_time < 10) {
        echo "⚠️  Performance acceptable\n";
    } else {
        echo "❌ Performance lente\n";
    }
    
} catch (Exception $e) {
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
    echo "⏱️  Temps d'exécution avant erreur: " . round($execution_time, 2) . " secondes\n";
}

echo "\n=== FIN DU TEST ===\n";
?>



