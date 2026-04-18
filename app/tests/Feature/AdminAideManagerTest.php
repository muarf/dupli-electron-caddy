<?php

require_once __DIR__ . '/../../models/admin/AideManager.php';

beforeEach(function () {
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS aide_machines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT UNIQUE,
        contenu_aide TEXT,
        date_modification TEXT
    )');

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS aide_machines_qa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        machine TEXT,
        question TEXT,
        reponse TEXT,
        ordre INTEGER,
        categorie TEXT,
        date_modification TEXT
    )');

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS duplicopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER
    )');

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS photocopieurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        marque TEXT,
        modele TEXT,
        actif INTEGER
    )');

    $this->manager = new AideManager($GLOBALS['conf']);
});

afterEach(function () {
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('ajoute et met à jour une Q&A', function () {
    $create = $this->manager->addQA('Riso', 'Comment ?','En appuyant sur Go', 1, 'usage');
    expect($create)->toHaveKey('success');

    $qa = $this->manager->getQA(1);
    expect($qa['question'])->toBe('Comment ?');

    $update = $this->manager->updateQA(1, 'Riso', 'Pourquoi ?', 'Parce que', 2, 'usage');
    expect($update)->toHaveKey('success');

    $qaUpdated = $this->manager->getQA(1);
    expect($qaUpdated['question'])->toBe('Pourquoi ?');
    expect((int) $qaUpdated['ordre'])->toBe(2);
});

it('ajoute une aide unique et la récupère', function () {
    $this->manager->addAide('Ricoh', 'Tutoriel complet');
    $aide = $this->manager->getAideByMachine('Ricoh');
    expect($aide['contenu_aide'])->toBe('Tutoriel complet');

    $update = $this->manager->updateAide($aide['id'], 'Ricoh', 'Tutoriel v2');
    expect($update)->toHaveKey('success');

    $updated = $this->manager->getAide($aide['id']);
    expect($updated['contenu_aide'])->toBe('Tutoriel v2');
});

it('retourne les machines disponibles avec aides', function () {
    $this->manager->addAide('Riso SF', 'Doc');
    $machines = $this->manager->getMachinesWithAide();
    expect($machines)->toContain('Riso SF');
});

it('filtre les Q&A par machine et categorie', function () {
    $this->manager->addQA('Riso', 'Q1', 'R1', 1, 'usage');
    $this->manager->addQA('Riso', 'Q2', 'R2', 2, 'maintenance');
    $list = $this->manager->getQAByMachine('Riso', 'usage');
    expect($list)->toHaveCount(1);
    expect($list[0]['question'])->toBe('Q1');
});

