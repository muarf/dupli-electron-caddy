<?php

require_once __DIR__ . '/../../models/admin/SiteManager.php';
require_once __DIR__ . '/../../models/admin/NewsManager.php';
require_once __DIR__ . '/../../models/admin/AideManager.php';
require_once __DIR__ . '/../../models/admin/StatsManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (setting_name TEXT PRIMARY KEY, setting_value TEXT, updated_at TEXT)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS news (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, titre TEXT, news TEXT)');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS aide_machines_qa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT,
        question TEXT,
        reponse TEXT,
        ordre INTEGER DEFAULT 0,
        categorie TEXT DEFAULT "general",
        date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS aide_machines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT UNIQUE,
        contenu_aide TEXT,
        date_modification DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('SiteManager: retourne les settings actuels', function () {
    $site = new SiteManager($GLOBALS['conf']);
    $settings = $site->getCurrentSettings();
    expect($settings)->toBeArray();
});

it('SiteManager: met à jour et récupère un paramètre', function () {
    $site = new SiteManager($GLOBALS['conf']);
    $site->updateSiteSetting('test_site_key', 'test_site_val');

    $val = $site->getSiteSetting('test_site_key', 'default');
    expect($val)->toBe('test_site_val');
});

it('SiteManager: gère la liste des emails', function () {
    $site = new SiteManager($GLOBALS['conf']);
    $emails = $site->getEmails();
    expect($emails)->toBeArray();
});

it('NewsManager: crée, récupère, met à jour et supprime une news', function () {
    $news = new NewsManager($GLOBALS['conf']);

    $news->insertNews('Titre Test', '<p>Contenu</p>');
    $all = $news->getAllNews();
    expect(count($all))->toBeGreaterThan(0);

    $first = $all[0];
    $news->updateNews('Titre Modifié', '<p>Modifié</p>', $first['id']);

    $updated = $news->getNews($first['id']);
    expect($updated['titre'])->toBe('Titre Modifié');

    $news->deleteNews($first['id']);
    $afterDelete = $news->getAllNews();
    expect(count($afterDelete))->toBe(0);
});

it('AideManager: ajoute et récupère des Q&R', function () {
    $aide = new AideManager($GLOBALS['conf']);

    $result = $aide->addQA('Riso', 'Comment nettoyer ?', 'Utiliser un chiffon', 1, 'maintenance');
    expect($result)->toHaveKey('success');

    $qaList = $aide->getAllQA();
    expect(count($qaList))->toBeGreaterThan(0);

    $first = $qaList[0];
    expect($first['machine'])->toBe('Riso');

    $machines = $aide->getMachinesWithAide();
    expect($machines)->toBeArray();
});

it('AideManager: filtre les Q&R par machine et catégorie', function () {
    $aide = new AideManager($GLOBALS['conf']);

    $aide->addQA('Riso', 'Question 1', 'Réponse 1', 1, 'maintenance');
    $aide->addQA('Riso', 'Question 2', 'Réponse 2', 2, 'utilisation');

    $qaMaintenance = $aide->getQAByMachineAndCategory('Riso', 'maintenance');
    expect($qaMaintenance)->toBeArray();
    expect(count($qaMaintenance))->toBeGreaterThanOrEqual(1);

    $first = $qaMaintenance[0];
    expect($first['categorie'])->toBe('maintenance');
});

it('StatsManager: retourne et met à jour le texte d\'introduction', function () {
    $stats = new StatsManager($GLOBALS['conf']);

    $stats->updateStatsIntroText('<p>Intro test</p>');
    $text = $stats->getStatsIntroText();
    expect($text)->toContain('Intro test');

    $data = $stats->getAllStatsData();
    expect($data)->toBeArray();
    expect($data)->toHaveKey('stats_intro_text');
});
