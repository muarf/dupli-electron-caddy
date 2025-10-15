<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/simple_i18n.php';

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
            return json_decode($content, true) ?: [];
        }
        
        return [];
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
        $frTranslations = $this->getTranslations('fr');
        
        $this->extractKeys($frTranslations, '', $keys);
        
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
        $keys = explode('.', $key);
        $value = $translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return '';
            }
        }
        
        return is_string($value) ? $value : '';
    }
    
    /**
     * Mettre à jour une traduction spécifique
     */
    public function updateTranslation($language, $key, $value) {
        $translations = $this->getTranslations($language);
        $keys = explode('.', $key);
        $current = &$translations;
        
        // Naviguer vers la clé
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                $current[$keys[$i]] = [];
            }
            $current = &$current[$keys[$i]];
        }
        
        // Mettre à jour la valeur
        $current[$keys[count($keys) - 1]] = $value;
        
        return $this->saveTranslations($language, $translations);
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
}
?>