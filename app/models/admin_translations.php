<?php
require_once __DIR__ . '/admin/TranslationManager.php';

function Action($conf) {
    $array = array();
    $translationManager = new TranslationManager($conf);
    
    // Gestion des actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_translation':
                $language = $_POST['language'];
                $key = $_POST['key'];
                $value = $_POST['value'];
                
                if ($translationManager->updateTranslation($language, $key, $value)) {
                    $array['success_message'] = "Traduction mise à jour avec succès !";
                } else {
                    $array['error_message'] = "Erreur lors de la mise à jour de la traduction.";
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