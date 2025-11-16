<?php

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

function create_test_sqlite_database(): array
{
    $path = tempnam(sys_get_temp_dir(), 'dupli_test_db_');
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    return [$path, $pdo];
}

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

function require_pricing_dependencies(): void
{
    require_once dirname(__DIR__) . '/controler/conf.php';
    require_once dirname(__DIR__) . '/controler/functions/database.php';
    require_once dirname(__DIR__) . '/controler/functions/pricing.php';
}
