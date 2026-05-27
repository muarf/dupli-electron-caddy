<?php

require_once __DIR__ . '/../../models/admin/SiteManager.php';
require_once __DIR__ . '/../../controler/functions/utilities.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    seed_site_manager_schema($this->pdo);

    $this->manager = new SiteManager($GLOBALS['conf']);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('met à jour un paramètre et le récupère', function () {
    $this->manager->updateSiteSetting('show_mailing_list', '0');
    $value = $this->manager->getSiteSetting('show_mailing_list', '1');

    expect($value)->toBe('0');
});

it('met à jour plusieurs paramètres et retourne les réglages courants', function () {
    $settings = [
        'show_mailing_list' => '1',
        'enable_counter_mode' => '1',
        'enable_manual_mode' => '0',
        'stats_intro_text' => 'Bienvenue',
        'formphoto_attention_message' => 'Attention au toner',
    ];

    $this->manager->updateSiteSettings($settings);
    $current = $this->manager->getCurrentSettings();

    expect($current)->toMatchArray($settings);
});

it('calcule les statistiques globales', function () {
    $this->pdo->exec("INSERT INTO a3 (type) VALUES ('initialisation'), ('tirage')");
    $this->pdo->exec("INSERT INTO a4 (type) VALUES ('initialisation')");
    $this->pdo->exec("INSERT INTO photocop (type) VALUES ('tirage'), ('initialisation')");
    $this->pdo->exec("INSERT INTO news (time, titre, news) VALUES (strftime('%s','now'), 'Actu', 'Texte')");
    $this->pdo->exec("INSERT INTO email (email) VALUES ('alice@example.com'), ('bob@example.com')");

    $stats = $this->manager->getStats();

    expect($stats['total_tirages'])->toBe(5);
    expect($stats['machines_count'])->toBe(3);
    expect((int) $stats['news_count'])->toBe(1);
    expect((int) $stats['emails_count'])->toBe(2);
});

it('gère la suppression d emails', function () {
    $this->pdo->exec("INSERT INTO email (email) VALUES ('alice@example.com'), ('bob@example.com')");

    $deleteOne = $this->manager->deleteEmail('alice@example.com');
    expect($deleteOne['success'])->toContain('succès');
    expect(count_emails())->toEqual(['bob@example.com']);

    $deleteAll = $this->manager->deleteAllEmails();
    expect($deleteAll['success'])->toContain('Tous les emails');
    expect(count_emails())->toBe([]);
});

function seed_site_manager_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
        setting_name TEXT PRIMARY KEY,
        setting_value TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS a3 (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS a4 (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS photocop (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS news (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, titre TEXT, news TEXT)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS email (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT)');
}

