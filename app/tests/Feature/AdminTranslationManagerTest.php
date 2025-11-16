<?php

require_once __DIR__ . '/../../models/admin/TranslationManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->translationsDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'translations_' . bin2hex(random_bytes(4));
    mkdir($this->translationsDir);

    $base = [
        'header' => [
            'title' => 'Accueil',
            'subtitle' => 'Bienvenue',
        ],
        'footer' => [
            'contact' => 'Contactez-nous',
        ],
    ];

    file_put_contents($this->translationsDir . '/fr.json', json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($this->translationsDir . '/en.json', json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($this->translationsDir . '/es.json', json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents($this->translationsDir . '/de.json', json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->manager = new TranslationManager($GLOBALS['conf']);
    $prop = new ReflectionProperty(TranslationManager::class, 'translationsPath');
    $prop->setAccessible(true);
    $prop->setValue($this->manager, $this->translationsDir . '/');
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
    if (isset($this->translationsDir) && is_dir($this->translationsDir)) {
        $files = glob($this->translationsDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->translationsDir);
    }
});

it('retourne les traductions aplaties pour une langue', function () {
    $translations = $this->manager->getTranslations('fr');
    expect($translations)->toHaveKey('header.title', 'Accueil');
    expect($translations)->toHaveKey('footer.contact', 'Contactez-nous');
});

it('met à jour une clé et persiste dans le fichier', function () {
    $updated = $this->manager->updateTranslation('fr', 'header.title', 'Bonjour');
    expect($updated)->toBeTrue();

    $content = json_decode(file_get_contents($this->translationsDir . '/fr.json'), true);
    expect($content['header']['title'])->toBe('Bonjour');
});

it('calcule les statistiques de traduction', function () {
    $stats = $this->manager->getTranslationStats();
    expect($stats['fr']['total_keys'])->toBe(3);
    expect($stats['fr']['translated_keys'])->toBe(3);
    expect($stats['fr']['percentage'])->toBe(100.0);
});

it('exporte et importe des traductions en CSV', function () {
    $csv = $this->manager->exportToCSV('fr');
    expect($csv)->toContain('header.title');

    $imported = $this->manager->importFromCSV('fr', "header.title,Salut\nfooter.contact,Bonjour");
    expect($imported)->toBeGreaterThan(0);
    expect($this->manager->getTranslationValue('fr', 'header.title'))->toBe('Salut');
});

