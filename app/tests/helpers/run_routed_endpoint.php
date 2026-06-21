<?php
define('DEVELOPMENT_MODE', true);
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($argc) || php_sapi_name() !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

$payload = $argv[1] ?? '{}';

$decoded = base64_decode($payload, true);
if ($decoded !== false && json_decode($decoded) !== null) {
    $payload = $decoded;
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    fwrite(STDERR, "Payload invalide\n");
    exit(1);
}

$endpoint = $data['endpoint'] ?? null;
$config = $data['config'] ?? [];

if ($endpoint === null) {
    fwrite(STDERR, "Endpoint manquant dans le payload\n");
    exit(1);
}

// Configurer $_GET avec l'endpoint comme première clé (pour key($_GET))
$_GET = [];
if (!empty($config['get'])) {
    $_GET = $config['get'];
}
// L'endpoint doit être la première clé pour que key($_GET) fonctionne
$_GET = [$endpoint => ''] + $_GET;

$_POST = $config['post'] ?? [];
$_FILES = $config['files'] ?? [];
$_COOKIE = $config['cookie'] ?? [];

$serverDefaults = [
    'REQUEST_METHOD' => $config['method'] ?? 'GET',
    'HTTP_HOST' => 'localhost',
    'REQUEST_URI' => '/?'.$endpoint,
    'CONTENT_TYPE' => $config['content_type'] ?? 'application/x-www-form-urlencoded',
];

$_SERVER = array_merge($serverDefaults, $config['server'] ?? []);

if (!empty($config['conf'])) {
    $GLOBALS['conf'] = $config['conf'];
}

if (!empty($config['env']) && is_array($config['env'])) {
    foreach ($config['env'] as $key => $value) {
        putenv($key . '=' . $value);
        $_SERVER[$key] = $value;
    }
}

// Configuration des sessions pour les tests
// Utiliser le même chemin que index.php pour éviter les conflits
$sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duplicator_sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}

// Configurer le chemin de session AVANT que index.php ne le fasse
ini_set('session.save_path', $sessionPath);

if (!empty($config['session'])) {
    $sessionId = $config['session_id'] ?? ('test' . bin2hex(random_bytes(8)));
    $_COOKIE[session_name()] = $sessionId;
    session_id($sessionId);
    
    // Démarrer la session maintenant et sauvegarder les données
    // pour qu'elles soient disponibles quand index.php démarre la session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = $config['session'];
    session_write_close();
    
    // Réinitialiser session_id pour que index.php puisse le réutiliser
    session_id($sessionId);
}

// Désactiver l'affichage direct pour capturer la sortie
define('DUPLICATOR_DISABLE_DIRECT_OUTPUT', true);

// Capturer la sortie
ob_start();

// Inclure le fichier de routage principal
// index.php va démarrer la session et charger les données depuis le fichier de session
require __DIR__ . '/../../public/index.php';

$output = ob_get_clean();
echo $output;

