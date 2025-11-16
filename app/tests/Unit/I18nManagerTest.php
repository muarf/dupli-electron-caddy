<?php

beforeEach(function () {
    reset_i18n_environment();
});

it('retourne les traductions françaises par défaut', function () {
    expect(__('header.brand'))->toBe('Duplicator');
    expect(getCurrentLanguage())->toBe('fr');
});

it('permet de changer de langue pour charger les traductions anglaises', function () {
    $manager = I18nManager::getInstance();
    expect($manager->setLanguage('en'))->toBeTrue();

    expect(__('header.previous'))->toBe('Previous');
    expect(getCurrentLanguage())->toBe('en');
});

it('retourne la clé brute lorsque la traduction est inexistante', function () {
    expect(__('header.cle_inexistante'))->toBe('header.cle_inexistante');
});

it('détecte la langue préférée issue des entêtes navigateur', function () {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-MX,fr;q=0.8';

    $manager = I18nManager::getInstance();

    expect($manager->getCurrentLanguage())->toBe('es');
    expect(__('header.previous'))->toBe('Anterior');
});


