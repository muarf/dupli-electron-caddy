<?php

beforeEach(function () {
    $_GET = [];
    $_POST = [];
    
    // Créer un répertoire temporaire pour les uploads
    $this->uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_test_uploads' . DIRECTORY_SEPARATOR . 'aide_pdfs';
    if (!is_dir($this->uploadDir)) {
        mkdir($this->uploadDir, 0777, true);
    }
    
    // Créer un répertoire de test dans public/uploads/aide_pdfs
    $publicUploadDir = __DIR__ . '/../../public/uploads/aide_pdfs';
    if (!is_dir($publicUploadDir)) {
        mkdir($publicUploadDir, 0777, true);
    }
    
    [$this->dbPath, $this->pdo] = create_test_sqlite_database();
    configure_sqlite_conf($this->dbPath);
    
    $this->conf = $GLOBALS['conf'];
});

afterEach(function () {
    $_GET = [];
    $_POST = [];
    
    // Nettoyer les fichiers de test
    if (isset($this->uploadDir) && is_dir($this->uploadDir)) {
        $files = glob($this->uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    $publicUploadDir = __DIR__ . '/../../public/uploads/aide_pdfs';
    if (is_dir($publicUploadDir)) {
        $files = glob($publicUploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && strpos(basename($file), 'test_') === 0) {
                unlink($file);
            }
        }
    }
    
    if (isset($this->pdo)) {
        $this->pdo = null;
    }
    if (isset($this->dbPath) && file_exists($this->dbPath)) {
        unlink($this->dbPath);
    }
});

it('retourne une erreur 401 si non authentifié', function () {
    $output = run_upload_aide_pdf(['action' => 'list'], $this->conf, []);
    
    $json = json_decode($output, true);
    
    expect($json['success'])->toBeFalse();
    expect($json['error'])->toContain('Non autorisé');
});

it('retourne la liste des PDFs pour un admin authentifié', function () {
    $output = run_upload_aide_pdf(['action' => 'list'], $this->conf, ['user' => '1']);
    
    $json = json_decode($output, true);
    
    expect($json['success'])->toBeTrue();
    expect($json)->toHaveKey('pdfs');
    expect($json['pdfs'])->toBeArray();
});

it('retourne une erreur si action est manquante', function () {
    $output = run_upload_aide_pdf([], $this->conf, ['user' => '1']);
    
    $json = json_decode($output, true);
    
    expect($json['success'])->toBeFalse();
    expect($json)->toHaveKey('message');
    expect($json['message'])->toContain('Action non reconnue');
});

function run_upload_aide_pdf(array $get, array $conf, array $session): string
{
    return execute_routed_endpoint('upload_aide_pdf', [
        'method' => 'GET',
        'get' => $get,
        'session' => $session,
        'conf' => $conf,
        'env' => ['DUPLICATOR_DB_PATH' => $conf['db_path']],
    ]);
}



