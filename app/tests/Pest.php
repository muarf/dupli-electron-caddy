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
require_once __DIR__ . '/helpers/test_db_helpers.php';

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

// Les fonctions create_test_sqlite_database, create_essential_tables et configure_sqlite_conf
// ont été déplacées dans helpers/test_db_helpers.php pour être partagées avec les sous-processus.

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
    
    // Prepare env variables for the child process - use the $env array of proc_open for cross-platform support
    $process_env = array_merge($_ENV, getenv(), $env_vars);
    
    // We'll use the CLI to run the script. 
    $wrapper = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'endpoint_wrapper_' . uniqid() . '.php';
    
    $wrapper_code = "<?php
\$_GET = " . var_export($get_params, true) . ";
\$_POST = " . var_export($post_data, true) . ";
\$_REQUEST = array_merge(\$_GET, \$_POST);
\$_SERVER['REQUEST_METHOD'] = '" . (empty($post_data) ? 'GET' : 'POST') . "';

include '" . addslashes($abs_path) . "';
";
    file_put_contents($wrapper, $wrapper_code);

    $php_binary = PHP_BINARY;
    $command = escapeshellarg($php_binary) . " " . escapeshellarg($wrapper);
    
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes, null, $process_env);

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
        // On utilise une Exception pour que Pest capture et affiche l'erreur proprement
        $errorMsg = "run_endpoint failed for $file_path.\n";
        $errorMsg .= "Raw snippet (first 3000 chars):\n";
        $errorMsg .= substr($output, 0, 3000) . "\n";
        $errorMsg .= "END RAW SNIPPET";
        throw new \Exception($errorMsg);
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


