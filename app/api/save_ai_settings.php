<?php
/**
 * API : Sauvegarde des réglages IA
 * POST - Accessible admin seulement
 */
ini_set('display_errors', 0);
while (ob_get_level()) ob_end_clean();

require_once __DIR__ . '/../controler/conf.php';
require_once __DIR__ . '/../controler/func.php';
require_once __DIR__ . '/../models/SettingsManager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $db = pdo_connect();
    $settings = new SettingsManager($db);

    $fields = [
        'ai_enabled',
        'ai_llm_url',
        'ai_llm_url_pro',
        'ai_embedding_url',
        'ai_embedding_model',
        'ai_reranker_url',
        'ai_token',
        'ai_system_prompt',
        'bibliotheque_password',
        // Studio IA — endpoints VPS
        'studio_api_fonts_url',
        'studio_api_docling_url',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $settings->set($field, trim($_POST[$field]));
        }
    }

    // ai_enabled est un checkbox — absent du POST si décoché
    if (!isset($_POST['ai_enabled'])) {
        $settings->set('ai_enabled', '0');
    }

    echo json_encode(['success' => true, 'message' => 'Réglages IA sauvegardés.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
