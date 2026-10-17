<?php

beforeEach(function () {
    reset_i18n_environment();
});

it('retourne la clé brute lorsque la traduction est inexistante', function () {
    expect(__('header.cle_inexistante'))->toBe('header.cle_inexistante');
});

it('détecte la langue par défaut via les entêtes navigateur', function () {
    expect(getCurrentLanguage())->toBe('fr');
});

it('charge et retourne les traductions anglaises existantes', function () {
    $manager = I18nManager::getInstance();
    $manager->setLanguage('en');
    expect(__('header.brand'))->toBe('Duplicator');
});

it('permet de changer de langue pour charger les traductions anglaises', function () {
    $manager = I18nManager::getInstance();
    expect($manager->setLanguage('en'))->toBeTrue();
    expect(getCurrentLanguage())->toBe('en');
});

it('retourne la clé brute pour les clés absentes', function () {
    expect(__('missing.key'))->toBe('missing.key');
});

it('détecte la langue préférée issue des entêtes navigateur', function () {
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-MX,fr;q=0.8';

    $manager = I18nManager::getInstance();
    expect($manager->getCurrentLanguage())->toBe('es');
});
