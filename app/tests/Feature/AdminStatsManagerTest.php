<?php

require_once __DIR__ . '/../../models/admin/StatsManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    $this->manager = new StatsManager($GLOBALS['conf']);
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (setting_name TEXT PRIMARY KEY, setting_value TEXT, updated_at TEXT)');
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('met à jour et lit le texte dintroduction des stats', function () {
    $saved = $this->manager->updateStatsIntroText('Bonjour stats');
    expect($saved)->toBeTrue();

    $text = $this->manager->getStatsIntroText();
    expect($text)->toBe('Bonjour stats');
});

it('renvoie lensemble des données de stats', function () {
    update_site_setting('stats_intro_text', 'Texte global');

    $data = $this->manager->getAllStatsData();
    expect($data['stats_intro_text'])->toBe('Texte global');
});

