<?php

require_once __DIR__ . '/../../models/ImpositionLeaflet.php';
require_once __DIR__ . '/../../controler/functions/ImpositionProcessor.php';

it('valide l existence et l instanciation des classes du moteur d imposition', function () {
    expect(class_exists('ImpositionLeaflet'))->toBeTrue();
    expect(class_exists('ImpositionProcessor'))->toBeTrue();
});

it('calcule correctement les séquences de pages pour une imposition livret A5', function () {
    $nbPages = 16;
    $sheets = intval(ceil($nbPages / 4));
    expect($sheets)->toBe(4);
});

it('découpe un document en cahiers de 16 pages avec numérotation décalée', function () {
    $leaflet = new ImpositionLeaflet(__FILE__, []);
    $sheets = $leaflet->calculateSignatureSheets(48, 2, 16);

    // 48 pages / 4 par feuille = 12 feuilles, en 3 cahiers
    expect($sheets)->toHaveCount(12);

    // Cahier 1 : pages 1..16, première feuille recto (16,1), verso (2,15)
    expect($sheets[0]['signature'])->toBe(1);
    expect($sheets[0]['front'][0]['pages'])->toBe([16, 1]);
    expect($sheets[0]['back'][0]['pages'])->toBe([2, 15]);

    // Cahier 2 : pages 17..32, première feuille recto (32,17)
    expect($sheets[4]['signature'])->toBe(2);
    expect($sheets[4]['front'][0]['pages'])->toBe([32, 17]);

    // Cahier 3 : pages 33..48
    expect($sheets[8]['signature'])->toBe(3);
    expect($sheets[8]['front'][0]['pages'])->toBe([48, 33]);

    // Dernière feuille du cahier 3 : verso (40, 41)
    expect($sheets[11]['back'][0]['pages'])->toBe([40, 41]);
});

it('avec signature_size = nombre total de pages, on obtient un seul cahier', function () {
    $leaflet = new ImpositionLeaflet(__FILE__, []);
    $sheets = $leaflet->calculateSignatureSheets(16, 2, 16);

    expect($sheets)->toHaveCount(4);
    expect($sheets[0]['signature'])->toBe(1);
    expect($sheets[3]['signature'])->toBe(1);
});

it('découpe correctement les cahiers pour une imposition 4-up', function () {
    $leaflet = new ImpositionLeaflet(__FILE__, []);
    // 16 pages en cahiers de 16, nUp=4 → 8 pages par feuille → 2 feuilles par cahier
    $sheets = $leaflet->calculateSignatureSheets(32, 4, 16);

    expect($sheets)->toHaveCount(4); // 32 pages / 8 par feuille = 4 feuilles, 2 cahiers
    expect($sheets[0]['signature'])->toBe(1);
    expect($sheets[2]['signature'])->toBe(2);
});
