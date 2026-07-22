<?php
/**
 * Wrapper d'exécution en arrière-plan pour les tâches lourdes du Studio.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.");
}

$jobId = $argv[1] ?? '';
if (empty($jobId)) exit;

define('IS_BACKGROUND', true);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../controler/functions/paths.php';

$tmpBase = resolveTempDir() . DIRECTORY_SEPARATOR . 'duplicator_studio' . DIRECTORY_SEPARATOR;
$jobFile = $tmpBase . $jobId . '.json';
$logFile = $tmpBase . $jobId . '.log';

if (!file_exists($jobFile)) exit;

$jobData = json_decode(file_get_contents($jobFile), true);
if (!$jobData) exit;

// Mock the environment for studio_process.php
$_POST = $jobData['post'] ?? [];
$_FILES = $jobData['files'] ?? [];
$action = $jobData['action'];
$uploadedFile = $jobData['uploadedFile'] ?? null;
$originalName = $jobData['originalName'] ?? null;
$safeName = $jobData['safeName'] ?? 'studio_doc';

// Simulation de variables GET si besoin
$_GET = [];

file_put_contents($logFile, "Démarrage de la tâche en arrière-plan : $action\n");

// Fonction de nettoyage à la fin (même si exit() est appelé)
register_shutdown_function(function() use ($jobId, $jobFile, $logFile) {
    $out = ob_get_clean();
    if ($out) file_put_contents($logFile, "\n[Output]: " . $out, FILE_APPEND);
    
    $jobData = json_decode(file_get_contents($jobFile), true);
    if ($jobData['status'] === 'pending') {
        // Analyser la réponse JSON émise par studio_process.php
        $result = null;
        $outLines = explode("\n", trim($out));
        foreach (array_reverse($outLines) as $line) {
            $parsed = @json_decode($line, true);
            if ($parsed && isset($parsed['success'])) {
                $result = $parsed;
                break;
            }
        }
        
        if ($result) {
            if ($result['success']) {
                $jobData['status'] = 'done';
                $jobData['download_url'] = $result['download_url'] ?? null;
                // Transmettre les autres clés utiles comme 'result' (ex: pour analyze_ink)
                if (isset($result['result'])) {
                    $jobData['result'] = $result['result'];
                }
                // Si la tâche renvoie un tableau PDF urls (ex: pdf_to_images zip + pngs)
                if (isset($result['pdf_url'])) {
                    $jobData['pdf_url'] = $result['pdf_url'];
                }
                // Si la tâche renvoie une liste de polices reconnues (recognize_font)
                if (isset($result['fonts'])) {
                    $jobData['fonts'] = $result['fonts'];
                }
            } else {
                $jobData['status'] = 'error';
                $errors = $result['errors'] ?? null;
                if (!empty($errors) && is_array($errors)) {
                    $jobData['error'] = implode("\n", $errors);
                } else if (!empty($result['error'])) {
                    $jobData['error'] = $result['error'];
                } else {
                    $jobData['error'] = 'Erreur non spécifiée.';
                }
            }
        } else {
            $jobData['status'] = 'error';
            $jobData['error'] = 'Crash silencieux, timeout PHP, ou réponse JSON invalide.';
        }
        
        file_put_contents($jobFile, json_encode($jobData));
    }
});

// Capturer toute sortie
ob_start();

// Exécuter le code principal
require __DIR__ . '/studio_process.php';

