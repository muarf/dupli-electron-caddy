<?php

beforeAll(function () {
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $_GET = [];
    require_once __DIR__ . '/../../controler/functions/paths.php';
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

it('réduit le coût en A4 en divisant le prix page par deux', function () {
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
    // nb_p = nb_exemplaires * nb_feuilles * 2 (recto/verso) = 800
    expect($total)->toEqualWithDelta(34.8, 0.0001);
});

