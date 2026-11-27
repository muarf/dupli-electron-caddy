<?php

beforeAll(function () {
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_GET = [];
    require_once __DIR__ . '/../../controler/functions/paths.php';
    
    [$dbPath, $pdo] = create_test_sqlite_database();
    configure_sqlite_conf($dbPath);
    
    $pdo->exec('CREATE TABLE IF NOT EXISTS photocopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        type_encre TEXT,
        actif INTEGER
    )');
    
    require_once __DIR__ . '/../../models/tirage_multimachines.php';
});

it('calcule le coût par page toner couleur en tenant compte du taux de remplissage', function () {
    $prices = [
        'cyan' => ['unite' => 0.01],
        'magenta' => ['unite' => 0.01],
        'yellow' => ['unite' => 0.01],
        'noir' => ['unite' => 0.01],
        'tambour' => ['unite' => 0.002],
        'dev' => ['unite' => 0.003],
    ];

    $cost = calculatePageCost('Ricoh Pro', 'toner', $prices, true, false, 0.75);

    // 4 couleurs à 0.01 avec multiplicateur 1.5 -> 0.06 + tambour/dev = 0.06
    expect($cost)->toEqual(0.06);
});

it('calcule le coût par page encre noir et blanc sans multiplicateur', function () {
    $prices = [
        'noire' => ['unite' => 0.012],
    ];

    $cost = calculatePageCost('Riso EZ', 'encre', $prices, false, false, 0.2);

    expect($cost)->toEqual(0.012);
});

it('calcule un devis A3 en ajoutant papier et encre', function () {
    $brochure = [
        'nb_exemplaires' => 100,
        'nb_feuilles' => 10,
        'taille' => 'A3',
        'rv' => 'non',
        'couleur' => 'non',
        'feuilles_payees' => 'non',
    ];

    $prices = [
        'noir' => ['unite' => 0.01],
        'tambour' => ['unite' => 0.005],
        'dev' => ['unite' => 0.004],
    ];

    $total = calculateBrochurePriceOptimized(
        $brochure,
        0.03,
        0.015,
        $prices,
        'toner',
        'Ricoh Pro',
        0.5
    );

    // Papier : 100*10*0.03 = 30, encre : 0.019*1000 = 19
    expect($total)->toEqual(49.0);
});

it('réduit le coût en A4 en divisant le prix page par deux et calcule correctement le recto-verso', function () {
    $brochure = [
        'nb_exemplaires' => 50,
        'nb_feuilles' => 8,
        'taille' => 'A4',
        'rv' => 'oui',
        'couleur' => 'oui',
        'feuilles_payees' => 'oui',
    ];

    $prices = [
        'cyan' => ['unite' => 0.02],
        'magenta' => ['unite' => 0.02],
        'yellow' => ['unite' => 0.02],
        'noir' => ['unite' => 0.02],
        'tambour' => ['unite' => 0.004],
        'dev' => ['unite' => 0.003],
    ];

    $total = calculateBrochurePriceOptimized(
        $brochure,
        0.03,
        0.015,
        $prices,
        'toner',
        'Ricoh Pro',
        0.5
    );

    // Cost per page = (4*0.02) + 0.004 + 0.003 = 0.087 -> divisé par 2 car A4 = 0.0435
    // nb_p = nb_exemplaires * nb_pages (recto/verso : 8 pages d'encre par exemplaire) = 400 pages d'encre
    // total = 400 * 0.0435 = 17.40
    expect($total)->toEqualWithDelta(17.4, 0.0001);
});

it('divise le papier par deux en recto-verso tout en conservant le nombre de pages d encre', function () {
    $prices = [
        'noir' => ['unite' => 0.01],
        'tambour' => ['unite' => 0.005],
        'dev' => ['unite' => 0.004],
    ];

    // Document de 10 pages en Recto seul (100 exemplaires)
    $brochureSimplex = [
        'nb_exemplaires' => 100,
        'nb_pages' => 10,
        'taille' => 'A4',
        'rv' => 'non',
        'couleur' => 'non',
        'feuilles_payees' => 'non',
    ];

    // Document de 10 pages en Recto-Verso (100 exemplaires)
    $brochureDuplex = [
        'nb_exemplaires' => 100,
        'nb_pages' => 10,
        'taille' => 'A4',
        'rv' => 'oui',
        'couleur' => 'non',
        'feuilles_payees' => 'non',
    ];

    $totalSimplex = calculateBrochurePriceOptimized($brochureSimplex, 0.03, 0.015, $prices, 'toner', 'Ricoh Pro', 0.5);
    $totalDuplex = calculateBrochurePriceOptimized($brochureDuplex, 0.03, 0.015, $prices, 'toner', 'Ricoh Pro', 0.5);

    // En A4 : coût encre par page = (0.01 + 0.005 + 0.004) / 2 = 0.0095
    // Simplex : 100 * 10 = 1000 feuilles papier (1000 * 0.015 = 15€) + 1000 encre (1000 * 0.0095 = 9.5€) = 24.5€
    // Duplex  : 100 * 5 = 500 feuilles papier (500 * 0.015 = 7.5€) + 1000 encre (1000 * 0.0095 = 9.5€) = 17.0€
    expect($totalSimplex)->toEqual(24.5);
    expect($totalDuplex)->toEqual(17.0);
    // L'économie en recto-verso correspond exactement aux 500 feuilles de papier économisées (7.5€)
    expect($totalSimplex - $totalDuplex)->toEqual(7.5);
});

