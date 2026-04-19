<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

require_once __DIR__ . '/helpers/test_helpers.php';

/*
|--------------------------------------------------------------------------
| Helpers globaux
|--------------------------------------------------------------------------
*/

function reset_i18n_environment(): void
{
    require_once dirname(__DIR__) . '/controler/functions/i18n.php';

    $_SESSION = [];
    $_GET = [];
    $_POST = [];
    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['QUERY_STRING'] = '';

    $reflection = new ReflectionClass(I18nManager::class);
    $instanceProperty = $reflection->getProperty('instance');
    $instanceProperty->setAccessible(true);
    $instanceProperty->setValue(null, null);
}

if (!function_exists('create_test_sqlite_database')) {
    function create_test_sqlite_database(): array
    {
        // Créer un dossier temporaire unique pour ce test
        $dataDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dupli_test_data_' . uniqid();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        
        $path = $dataDir . DIRECTORY_SEPARATOR . 'duplinew.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        create_essential_tables($pdo);
        
        return [$path, $pdo, $dataDir];
    }
}

if (!function_exists('create_essential_tables')) {
    function create_essential_tables(PDO $db): void
    {
        $tables = [
            'print_sessions' => "CREATE TABLE IF NOT EXISTS print_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contact TEXT NOT NULL,
                session_name TEXT,
                opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                closed_at DATETIME NULL,
                status TEXT DEFAULT 'active' CHECK(status IN ('active', 'closed')),
                total_price REAL DEFAULT 0.0,
                notes TEXT
            )",
            'duplicopieurs' => "CREATE TABLE IF NOT EXISTS duplicopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL,
                supporte_a3 INTEGER DEFAULT 1,
                supporte_a4 INTEGER DEFAULT 1,
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                tambours TEXT
            )",
            'photocopieurs' => "CREATE TABLE IF NOT EXISTS photocopieurs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                marque TEXT NOT NULL,
                modele TEXT NOT NULL DEFAULT '',
                type_encre TEXT NOT NULL DEFAULT 'toner' CHECK(type_encre IN ('encre','toner')),
                actif INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(marque, modele)
            )",
            'dupli' => "CREATE TABLE IF NOT EXISTS dupli (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT '',
                document_name TEXT NOT NULL DEFAULT '',
                nb_exemplaires INTEGER NOT NULL DEFAULT 1,
                thumbnail_url TEXT DEFAULT NULL,
                type TEXT NOT NULL DEFAULT 'dupli',
                contact TEXT NOT NULL DEFAULT '',
                master_av TEXT NOT NULL DEFAULT '0',
                master_ap TEXT NOT NULL DEFAULT '0',
                passage_av TEXT NOT NULL DEFAULT '0',
                passage_ap TEXT NOT NULL DEFAULT '0',
                rv TEXT NOT NULL DEFAULT 'non',
                prix TEXT NOT NULL DEFAULT '0',
                paye TEXT NOT NULL DEFAULT 'non',
                cb TEXT NOT NULL DEFAULT 'non',
                mot TEXT NOT NULL DEFAULT '',
                date TEXT NOT NULL DEFAULT '',
                nom_machine TEXT DEFAULT 'Duplicopieur',
                duplicopieur_id INTEGER DEFAULT 1,
                tambour TEXT DEFAULT NULL,
                tirage_global_id TEXT DEFAULT NULL,
                session_id INTEGER DEFAULT NULL
            )",
            'photocop' => "CREATE TABLE IF NOT EXISTS photocop (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL DEFAULT 'photocop',
                marque TEXT DEFAULT NULL,
                contact TEXT NOT NULL DEFAULT '',
                nb_f TEXT NOT NULL DEFAULT '0',
                rv TEXT NOT NULL DEFAULT 'non',
                paye TEXT NOT NULL DEFAULT 'non',
                prix TEXT NOT NULL DEFAULT '0',
                cb TEXT NOT NULL DEFAULT 'non',
                mot TEXT NOT NULL DEFAULT '',
                date TEXT NOT NULL DEFAULT '',
                document_name TEXT DEFAULT '',
                thumbnail_url TEXT DEFAULT NULL,
                nb_exemplaires INTEGER DEFAULT 1,
                tirage_global_id TEXT DEFAULT NULL,
                session_id INTEGER DEFAULT NULL
            )",
            'print_jobs' => "CREATE TABLE IF NOT EXISTS print_jobs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    job_id TEXT,
                    document TEXT NOT NULL,
                    printer_name TEXT,
                    status TEXT,
                    pages_printed INTEGER DEFAULT 0,
                    total_pages INTEGER DEFAULT 0,
                    timestamp TEXT,
                    thumbnail_url TEXT,
                    session_id INTEGER,
                    staged INTEGER DEFAULT 0,
                    calculated_price REAL DEFAULT 0
                )"
        ];
        
        foreach ($tables as $sql) {
            $db->exec($sql);
        }
    }
}


if (!function_exists('configure_sqlite_conf')) {
    function configure_sqlite_conf(string $path): void
    {
        $GLOBALS['conf'] = [
            'db_type' => 'sqlite',
            'db_path' => $path,
            'dsn' => 'sqlite:' . $path,
            'login' => '',
            'pass' => '',
            'uploaddir' => sys_get_temp_dir() . '/',
        ];
    }
}

if (!function_exists('require_pricing_dependencies')) {
    function require_pricing_dependencies(): void
    {
        error_log("DEBUG PEST: Step require_pricing_dependencies START");
        require_once dirname(__DIR__) . '/controler/func.php';
        error_log("DEBUG PEST: Step require_pricing_dependencies - func.php loaded");
        require_once dirname(__DIR__) . '/controler/conf.php';
        require_once dirname(__DIR__) . '/controler/functions/database.php';
        require_once dirname(__DIR__) . '/controler/functions/pricing.php';
        error_log("DEBUG PEST: Step require_pricing_dependencies END");
    }
}

/**
 * Simule l'appel d'un endpoint PHP API et capture la réponse JSON
 */
function run_endpoint(string $file_path, array $get_params = [], array $env_vars = [], array $post_data = []): array
{
    // Fix absolute path
    $abs_path = realpath(dirname(__DIR__) . '/' . $file_path);
    if (!$abs_path) {
        throw new Exception("Endpoint not found: $file_path");
    }

    // Prepare query string
    $query = http_build_query($get_params);
    
    // Prepare env variables for the child process
    $env_cmd = "";
    foreach ($env_vars as $key => $val) {
        $env_cmd .= "export $key=" . escapeshellarg($val) . " && ";
    }

    // We'll use the CLI to run the script. 
    // To simulate $_GET and $_POST, we can use a wrapper or the fact that some of our APIs check $_REQUEST which we can't easily set from CLI.
    // HOWEVER, we can use the `-d` flag of PHP to set them if the script was a web server, but here it's CLI.
    
    // Most of our APIs use $_REQUEST or $_GET. For CLI, we'll need to "spoof" them.
    // We can create a temporary wrapper script that sets the globals then includes the API.
    
    $wrapper = sys_get_temp_dir() . '/endpoint_wrapper_' . uniqid() . '.php';
    $post_json = json_encode($post_data);
    
    $wrapper_code = "<?php
\$_GET = " . var_export($get_params, true) . ";
\$_POST = " . var_export($post_data, true) . ";
\$_REQUEST = array_merge(\$_GET, \$_POST);
\$_SERVER['REQUEST_METHOD'] = '" . (empty($post_data) ? 'GET' : 'POST') . "';

// Mock php://input
// We can't easily mock php://input, but we can override it if the API uses a helper
// For now, let's just include the file.
include '" . addslashes($abs_path) . "';
";
    file_put_contents($wrapper, $wrapper_code);

    $command = "{$env_cmd} php " . escapeshellarg($wrapper) . " 2>&1";
    
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes);

    if (is_resource($process)) {
        if (!empty($post_data)) {
            fwrite($pipes[0], json_encode($post_data));
        }
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        
        if (!empty($stderr)) {
            $output .= "\nSTDERR: " . $stderr;
        }
    } else {
        $output = "Failed to start process";
    }

    unlink($wrapper);

    $data = json_decode($output, true);
    if ($data === null) {
        // Try searching for JSON in case of debug logs before it
        if (preg_match('/\{.*\}/s', $output, $matches)) {
            $data = json_decode($matches[0], true);
        }
    }
    
    if ($data === null || !isset($data['success']) || $data['success'] === false) {
        $logFile = sys_get_temp_dir() . '/run_endpoint_last_raw.log';
        file_put_contents($logFile, $output);
        // On utilise var_dump pour que Pest capture et affiche la sortie brute en cas d'échec
        echo "\nDEBUG Pest: run_endpoint failed for $file_path. Raw output saved to $logFile\n";
        echo "DEBUG Pest: Raw snippet (first 2000 chars):\n";
        var_dump(substr($output, 0, 2000));
    }
    
    return $data ?? ['success' => false, 'error' => 'Invalid JSON', 'raw_output' => $output];
}

if (!function_exists('run_migrations')) {
    function run_migrations(): void
    {
        global $conf;
        require_once dirname(__DIR__) . '/models/migrations/DatabaseMigrationManager.php';
        $manager = new DatabaseMigrationManager($conf);
        $manager->runMigrations();
    }
}


