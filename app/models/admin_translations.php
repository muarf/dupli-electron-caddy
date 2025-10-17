<?php
require_once __DIR__ . '/admin/TranslationManager.php';
require_once __DIR__ . '/../controler/functions/i18n.php';

function Action($conf) {
    // Vérification de l'authentification admin
    if(!isset($_SESSION['user'])) {
        // Pour les requêtes AJAX, retourner une erreur JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        } else {
            header('Location: ?admin');
            exit;
        }
    }
    
    $array = array();
    $translationManager = new TranslationManager($conf);
    
    // Gestion des actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_translation':
                $language = $_POST['language'];
                $key = $_POST['key'];
                $value = $_POST['value'];
                
                // Debug: les données sont reçues correctement
                
                // Vérifier si c'est une requête AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    
                    $result = $translationManager->updateTranslation($language, $key, $value);
                    
                    if ($result) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Traduction mise à jour avec succès !']);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la traduction.']);
                    }
                    exit;
                } else {
                    // Requête normale (non-AJAX)
                    if ($translationManager->updateTranslation($language, $key, $value)) {
                        $array['success_message'] = "Traduction mise à jour avec succès !";
                    } else {
                        $array['error_message'] = "Erreur lors de la mise à jour de la traduction.";
                    }
                }
                break;
                
            case 'export_csv':
                $language = $_POST['language'];
                $csv = $translationManager->exportToCSV($language);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="translations_' . $language . '.csv"');
                echo $csv;
                exit;
                break;
                
            case 'import_csv':
                $language = $_POST['language'];
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $csvContent = file_get_contents($_FILES['csv_file']['tmp_name']);
                    $imported = $translationManager->importFromCSV($language, $csvContent);
                    $array['success_message'] = "$imported traductions importées avec succès !";
                } else {
                    $array['error_message'] = "Erreur lors de l'import du fichier CSV.";
                }
                break;
        }
    }
    
    // Obtenir les données pour l'affichage
    $array['available_languages'] = $translationManager->getAvailableLanguages();
    $array['translation_keys'] = $translationManager->getAllTranslationKeys();
    $array['translation_stats'] = $translationManager->getTranslationStats();
    $array['translation_manager'] = $translationManager;
    
    // Langue sélectionnée (par défaut français)
    $selected_language = $_GET['lang'] ?? 'fr';
    if (!in_array($selected_language, $array['available_languages'])) {
        $selected_language = 'fr';
    }
    
    $array['selected_language'] = $selected_language;
    $array['translations'] = $translationManager->getTranslations($selected_language);
    
    return template("../view/admin_translations.html.php", $array);
}
?>