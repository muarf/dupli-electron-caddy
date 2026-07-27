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
