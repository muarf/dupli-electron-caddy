<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/i18n.php';

/**
 * Gestionnaire de traductions pour l'administration
 * Permet de gérer toutes les traductions de l'application
 */
class TranslationManager {
    private $conf;
    private $translationsPath;
    private $availableLanguages = ['fr', 'en', 'es', 'de'];
    
    public function __construct($conf) {
        $this->conf = $conf;
        $this->translationsPath = __DIR__ . '/../../translations/';
    }
    
    /**
     * Obtenir toutes les traductions pour une langue donnée
     */
    public function getTranslations($language) {
        if (!in_array($language, $this->availableLanguages)) {
            return [];
        }
        
        $file = $this->translationsPath . $language . '.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $translations = json_decode($content, true) ?: [];
            
            // Convertir en structure plate pour le template
            $flatTranslations = [];
            $this->flattenTranslations($translations, '', $flatTranslations);
            return $flatTranslations;
        }
        
        return [];
    }
    
    /**
     * Convertir les traductions imbriquées en structure plate
     */
    private function flattenTranslations($array, $prefix, &$flatTranslations) {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $this->flattenTranslations($value, $fullKey, $flatTranslations);
            } else {
                $flatTranslations[$fullKey] = $value;
            }
        }
    }
    
    /**
     * Sauvegarder les traductions pour une langue donnée
     */
    public function saveTranslations($language, $translations) {
        if (!in_array($language, $this->availableLanguages)) {
            return false;
        }
        
        $file = $this->translationsPath . $language . '.json';
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return file_put_contents($file, $json) !== false;
    }
    
    /**
     * Obtenir toutes les clés de traduction disponibles
     */
    public function getAllTranslationKeys() {
        $keys = [];
        $frFile = $this->translationsPath . 'fr.json';
        
        if (file_exists($frFile)) {
            $content = file_get_contents($frFile);
            $frTranslations = json_decode($content, true) ?: [];
            $this->extractKeys($frTranslations, '', $keys);
        }
        
        return $keys;
    }
    
    /**
     * Extraire récursivement toutes les clés de traduction
     */
    private function extractKeys($array, $prefix, &$keys) {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $this->extractKeys($value, $fullKey, $keys);
            } else {
                $keys[] = $fullKey;
            }
        }
    }
    
    /**
     * Obtenir la valeur d'une traduction pour une clé donnée
     */
    public function getTranslationValue($language, $key) {
        $translations = $this->getTranslations($language);
        
        // Les traductions sont déjà en structure plate
        return isset($translations[$key]) ? $translations[$key] : '';
    }
    
    /**
     * Mettre à jour une traduction spécifique
     */
    public function updateTranslation($language, $key, $value) {
        if (!in_array($language, $this->availableLanguages)) {
            return false;
        }
        
        $file = $this->translationsPath . $language . '.json';
        
        if (!file_exists($file) || !is_writable($file)) {
            return false;
        }
        
        // Charger le fichier JSON original (structure imbriquée)
        $translations = [];
        $content = file_get_contents($file);
        $translations = json_decode($content, true) ?: [];
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        // Naviguer et mettre à jour la structure imbriquée
        $keys = explode('.', $key);
        $current = &$translations;
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                $current[$keys[$i]] = [];
            }
            $current = &$current[$keys[$i]];
        }
        
        // Mettre à jour la valeur
        $current[$keys[count($keys) - 1]] = $value;
        
        // Sauvegarder
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return file_put_contents($file, $json) !== false;
    }
    
    /**
     * Obtenir les langues disponibles
     */
    public function getAvailableLanguages() {
        return $this->availableLanguages;
    }
    
    /**
     * Obtenir le nom de la langue en français
     */
    public function getLanguageName($code) {
        $names = [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch'
        ];
        
        return $names[$code] ?? $code;
    }
    
    /**
     * Obtenir les statistiques des traductions
     */
    public function getTranslationStats() {
        $stats = [];
        
        foreach ($this->availableLanguages as $lang) {
            $translations = $this->getTranslations($lang);
            $keys = $this->getAllTranslationKeys();
            $translatedKeys = 0;
            
            foreach ($keys as $key) {
                $value = $this->getTranslationValue($lang, $key);
                if (!empty($value)) {
                    $translatedKeys++;
                }
            }
            
            $stats[$lang] = [
                'name' => $this->getLanguageName($lang),
                'total_keys' => count($keys),
                'translated_keys' => $translatedKeys,
                'percentage' => count($keys) > 0 ? round(($translatedKeys / count($keys)) * 100, 1) : 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Exporter les traductions en CSV
     */
    public function exportToCSV($language) {
        $translations = $this->getTranslations($language);
        $keys = $this->getAllTranslationKeys();
        
        $csv = "Clé,Traduction\n";
        
        foreach ($keys as $key) {
            $value = $this->getTranslationValue($language, $key);
            $csv .= '"' . $key . '","' . str_replace('"', '""', $value) . '"' . "\n";
        }
        
        return $csv;
    }
    
    /**
     * Importer les traductions depuis un CSV
     */
    public function importFromCSV($language, $csvContent) {
        $lines = explode("\n", $csvContent);
        $translations = $this->getTranslations($language);
        $imported = 0;
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parts = str_getcsv($line);
            if (count($parts) >= 2) {
                $key = $parts[0];
                $value = $parts[1];
                
                if ($this->updateTranslation($language, $key, $value)) {
                    $imported++;
                }
            }
        }
        
        return $imported;
    }
    
    /**
     * Obtenir les statistiques par page
     */
    public function getPageStats($language) {
        $allKeys = $this->getAllTranslationKeys();
        $translations = $this->getTranslations($language);
        $pageStats = [];
        
        foreach ($allKeys as $key) {
            $page = explode('.', $key)[0];
            
            if (!isset($pageStats[$page])) {
                $pageStats[$page] = [
                    'total' => 0,
                    'translated' => 0,
                    'percentage' => 0
                ];
            }
            
            $pageStats[$page]['total']++;
            
            if (isset($translations[$key]) && !empty($translations[$key])) {
                $pageStats[$page]['translated']++;
            }
        }
        
        // Calculer les pourcentages
        foreach ($pageStats as $page => &$stats) {
            $stats['percentage'] = $stats['total'] > 0 ? round(($stats['translated'] / $stats['total']) * 100, 1) : 0;
        }
        
        return $pageStats;
    }
    
    /**
     * Obtenir les traductions pour une page spécifique
     */
    public function getPageTranslations($language, $page) {
        $allKeys = $this->getAllTranslationKeys();
        $translations = $this->getTranslations($language);
        $pageTranslations = [];
        
        foreach ($allKeys as $key) {
            $keyPage = explode('.', $key)[0];
            
            if ($keyPage === $page) {
                $pageTranslations[$key] = $translations[$key] ?? '';
            }
        }
        
        return $pageTranslations;
    }
    
    /**
     * Obtenir l'icône pour une page
     */
    public function getPageIcon($page) {
        $icons = [
            'header' => 'fa-header',
            'footer' => 'fa-footer',
            'accueil' => 'fa-home',
            'admin' => 'fa-cogs',
            'admin_aide' => 'fa-question-circle',
            'admin_bdd' => 'fa-database',
            'admin_edit' => 'fa-edit',
            'admin_login' => 'fa-sign-in',
            'admin_mot' => 'fa-key',
            'admin_mots' => 'fa-key',
            'admin_aide_machines' => 'fa-cogs',
            'aide_machines' => 'fa-cogs',
            'admin_tirage' => 'fa-print',
            'stats' => 'fa-chart-bar',
            'tirage_multimachines' => 'fa-print',
            'changement' => 'fa-exchange-alt',
            'imposition' => 'fa-file-pdf',
            'unimpose' => 'fa-file-pdf'
        ];
        
        return $icons[$page] ?? 'fa-file';
    }
    
    /**
     * Obtenir le nom affiché d'une page
     */
    public function getPageName($page) {
        $names = [
            'header' => 'En-tête',
            'footer' => 'Pied de page',
            'accueil' => 'Page d\'accueil',
            'admin' => 'Administration',
            'admin_aide' => 'Aide Administration',
            'admin_bdd' => 'Base de données',
            'admin_edit' => 'Édition',
            'admin_login' => 'Connexion',
            'admin_mot' => 'Mot de passe',
            'admin_mots' => 'Mots de passe',
            'admin_aide_machines' => 'Aide Machines',
            'aide_machines' => 'Aide Machines',
            'admin_tirage' => 'Tirage',
            'stats' => 'Statistiques',
            'tirage_multimachines' => 'Tirage Multi-Machines',
            'changement' => 'Changements',
            'imposition' => 'Imposition',
            'unimpose' => 'Désimposition'
        ];
        
        return $names[$page] ?? ucfirst($page);
    }
}
?>