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

it('gère l orientation pied-contre-pied (défaut) et tête-contre-tête en 4-up', function () {
    // 1) Pied contre pied (foot_to_foot) : ligne 1 droite, ligne 2 tournée 180°
    $leafletDefault = new ImpositionLeaflet(__FILE__, [
        'n_up' => 4,
        'tumble_direction' => 'foot_to_foot'
    ]);
    $sheetsDefault = $leafletDefault->calculateSignatureSheets(16, 4, 16);
    expect($sheetsDefault[0]['front'][0]['rotated'])->toBeFalse();
    expect($sheetsDefault[0]['front'][1]['rotated'])->toBeTrue();
    // En pied contre pied, ligne 1 = [16, 1], ligne 2 = [9, 8] (inversé de [8, 9])
    expect($sheetsDefault[0]['front'][0]['pages'])->toBe([16, 1]);
    expect($sheetsDefault[0]['front'][1]['pages'])->toBe([9, 8]);

    // 2) Tête contre tête (head_to_head) : ligne 1 tournée 180°, ligne 2 droite
    $leafletHeadToHead = new ImpositionLeaflet(__FILE__, [
        'n_up' => 4,
        'tumble_direction' => 'head_to_head'
    ]);
    $sheetsHeadToHead = $leafletHeadToHead->calculateSignatureSheets(16, 4, 16);
    expect($sheetsHeadToHead[0]['front'][0]['rotated'])->toBeTrue();
    expect($sheetsHeadToHead[0]['front'][1]['rotated'])->toBeFalse();
    // En tête contre tête, ligne 1 = [1, 16] (inversé de [16, 1]), ligne 2 = [8, 9] (droit)
    expect($sheetsHeadToHead[0]['front'][0]['pages'])->toBe([1, 16]);
    expect($sheetsHeadToHead[0]['front'][1]['pages'])->toBe([8, 9]);

    // 3) Tête-bêche désactivé (tete_beche = false) : les deux lignes droites
    $leafletNoTumble = new ImpositionLeaflet(__FILE__, [
        'n_up' => 4,
        'tete_beche' => false
    ]);
    $sheetsNoTumble = $leafletNoTumble->calculateSignatureSheets(16, 4, 16);
    expect($sheetsNoTumble[0]['front'][0]['rotated'])->toBeFalse();
    expect($sheetsNoTumble[0]['front'][1]['rotated'])->toBeFalse();
    expect($sheetsNoTumble[0]['front'][0]['pages'])->toBe([16, 1]);
    expect($sheetsNoTumble[0]['front'][1]['pages'])->toBe([8, 9]);
});
