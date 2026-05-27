<?php

require_once __DIR__ . '/../../models/admin/NewsManager.php';
require_once __DIR__ . '/../../controler/functions/utilities.php';

beforeEach(function () {
    require_pricing_dependencies();
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    seed_news_schema($this->pdo);

    $this->manager = new NewsManager($GLOBALS['conf']);
});

afterEach(function () {
    unset($this->manager);
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('cree puis recupere une news', function () {
    $this->manager->insertNews('Titre', 'Contenu');

    $news = $this->manager->getAllNews();
    expect($news)->toHaveCount(1);
    expect($news[0]['titre'])->toBe('Titre');
});

it('met a jour puis supprime une news', function () {
    $this->manager->insertNews('Ancien', 'Texte');
    $id = $this->pdo->query('SELECT id FROM news LIMIT 1')->fetchColumn();

    $_POST['id2'] = $id;
    $this->manager->updateNews('Nouveau', 'Texte maj', $id);
    $updated = $this->manager->getNews($id);
    $updatedArray = is_array($updated) ? $updated : (array) $updated;
    expect($updatedArray['titre'])->toBe('Nouveau');

    $_POST['id'] = $id;
    $this->manager->deleteNews($id);
    $rest = $this->manager->getAllNews();
    expect($rest)->toHaveCount(0);
    unset($_POST['id'], $_POST['id2']);
});

function seed_news_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS news (id INTEGER PRIMARY KEY AUTOINCREMENT, time INTEGER, titre TEXT, news TEXT)');
}

