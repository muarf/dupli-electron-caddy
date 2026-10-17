<?php

require_once __DIR__ . '/../../models/admin/TranslationManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('TranslationManager: getAvailableLanguages() retourne les langues', function () {
    $manager = new TranslationManager($GLOBALS['conf']);
    $langs = $manager->getAvailableLanguages();
    expect($langs)->toBeArray();
});

it('TranslationManager: getAllTranslationKeys() retourne les clés', function () {
    $manager = new TranslationManager($GLOBALS['conf']);
    $keys = $manager->getAllTranslationKeys();
    expect($keys)->toBeArray();
});

it('TranslationManager: getTranslationStats() retourne les statistiques', function () {
    $manager = new TranslationManager($GLOBALS['conf']);
    $stats = $manager->getTranslationStats();
    expect($stats)->toBeArray();
});

it('TranslationManager: saveTranslations() et getTranslations()', function () {
    $manager = new TranslationManager($GLOBALS['conf']);

    $manager->saveTranslations('fr', ['test.key' => 'Valeur test']);
    $translations = $manager->getTranslations('fr');
    expect($translations)->toBeArray();
});
