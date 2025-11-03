<?php
/**
 * Test de la correction du timeout pour les tirages multi-machines
 * 
 * Ce script simule le processus d'enregistrement avec données complètes
 * pour vérifier que le timeout de 30 secondes est résolu.
 */

// Configuration pour éviter les timeouts
set_time_limit(120);
ini_set('max_execution_time', 120);

echo "=== TEST DE CORRECTION TIMEOUT MULTI-MACHINES ===\n\n";

// Simuler des données POST complètes
$_POST = [
    'contact' => 'Test User',
    'enregistrer' => '1',
    'machines' => [
        [
            'type' => 'duplicopieur',
            'contact' => 'Test User',
            'nb_masters' => 5,
            'nb_passages' => 100,
            'master_av' => 1000,
            'master_ap' => 1005,
            'passage_av' => 5000,
            'passage_ap' => 5100,
            'prix' => 15.50,
            'rv' => 'non',
            'feuilles_payees' => 'non',
            'A4' => 'non',
            'duplicopieur_id' => 1,
            'tambour' => 'tambour_noir'
        ],
        [
            'type' => 'photocopieur',
            'machine' => 'Test Photocopieur',
            'contact' => 'Test User',
            'brochures' => [
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
                ]
            ],
            'prix' => 12.75
        ]
    ],
    'paye' => 'non',
    'cb' => 0,
    'mot' => 'Test timeout fix'
];

echo "Données de test créées avec succès.\n";
echo "Machine 1 (duplicopieur): " . count($_POST['machines'][0]) . " champs\n";
echo "Machine 2 (photocopieur): " . count($_POST['machines'][1]['brochures']) . " brochures\n\n";

// Mesurer le temps d'exécution
$start_time = microtime(true);

try {
    // Inclure les dépendances nécessaires
    require_once __DIR__ . '/controler/functions/database.php';
    require_once __DIR__ . '/controler/functions/pricing.php';
    require_once __DIR__ . '/controler/functions/tirage.php';
    require_once __DIR__ . '/controler/functions/utilities.php';
    
    // Inclure le fichier principal
    require_once __DIR__ . '/models/tirage_multimachines.php';
    
    // Simuler l'appel de la fonction
    $result = tirage_multimachines();
    
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    echo "✅ Test réussi !\n";
    echo "⏱️  Temps d'exécution: " . round($execution_time, 2) . " secondes\n";
    
    if ($execution_time > 30) {
        echo "⚠️  ATTENTION: Le temps d'exécution dépasse 30 secondes\n";
    } else {
        echo "✅ Le timeout de 30 secondes est évité\n";
    }
    
    // Vérifier les résultats
    if (isset($result['success_message'])) {
        echo "✅ Message de succès trouvé: " . $result['success_message'] . "\n";
    } else if (isset($result['errors']) && !empty($result['errors'])) {
        echo "❌ Erreurs détectées:\n";
        foreach ($result['errors'] as $error) {
            echo "   - " . $error . "\n";
        }
    } else {
        echo "ℹ️  Aucun message de succès ou d'erreur trouvé\n";
    }
    
} catch (Exception $e) {
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    echo "❌ Erreur lors du test: " . $e->getMessage() . "\n";
    echo "⏱️  Temps d'exécution avant erreur: " . round($execution_time, 2) . " secondes\n";
}

echo "\n=== FIN DU TEST ===\n";
?>
