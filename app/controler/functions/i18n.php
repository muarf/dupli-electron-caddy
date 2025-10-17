<?php
/**
 * Système d'internationalisation (i18n) pour Duplicator
 * Gestion complète des traductions avec support multilingue
 */

class I18nManager {
    private static $instance = null;
    private $currentLanguage = 'fr';
    private $fallbackLanguage = 'fr';
    private $translations = [];
    private $availableLanguages = ['fr', 'en', 'es', 'de', 'it', 'pt'];
    private $translationsPath;
    
    private function __construct() {
        $this->translationsPath = __DIR__ . '/../../translations/';
        $this->detectLanguage();
        $this->loadTranslations();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Détection automatique de la langue préférée
     */
    private function detectLanguage() {
        // 1. Vérifier la session utilisateur
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $this->availableLanguages)) {
            $this->currentLanguage = $_SESSION['language'];
            return;
        }
        
        // 2. Vérifier les paramètres GET/POST
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->availableLanguages)) {
            $this->currentLanguage = $_GET['lang'];
            $_SESSION['language'] = $this->currentLanguage;
            return;
        }
        
        if (isset($_POST['lang']) && in_array($_POST['lang'], $this->availableLanguages)) {
            $this->currentLanguage = $_POST['lang'];
            $_SESSION['language'] = $this->currentLanguage;
            return;
        }
        
        // 3. Détecter depuis les headers HTTP Accept-Language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $preferredLanguages = $this->parseAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($preferredLanguages as $lang) {
                if (in_array($lang, $this->availableLanguages)) {
                    $this->currentLanguage = $lang;
                    $_SESSION['language'] = $this->currentLanguage;
                    return;
                }
            }
        }
        
        // 4. Langue par défaut
        $this->currentLanguage = $this->fallbackLanguage;
    }
    
    /**
     * Parse les headers Accept-Language
     */
    private function parseAcceptLanguage($acceptLanguage) {
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $lang = trim(explode(';', $part)[0]);
            $lang = explode('-', $lang)[0]; // Prendre seulement la partie principale (fr au lieu de fr-FR)
            $languages[] = $lang;
        }
        
        return $languages;
    }
    
    /**
     * Charger les traductions pour la langue courante
     */
    private function loadTranslations() {
        $translationFile = $this->translationsPath . $this->currentLanguage . '.json';
        
        if (file_exists($translationFile)) {
            $content = file_get_contents($translationFile);
            $this->translations = json_decode($content, true) ?: [];
        } else {
            // Charger la langue de fallback si la langue courante n'existe pas
            $fallbackFile = $this->translationsPath . $this->fallbackLanguage . '.json';
            if (file_exists($fallbackFile)) {
                $content = file_get_contents($fallbackFile);
                $this->translations = json_decode($content, true) ?: [];
            }
        }
    }
    
    /**
     * Fonction principale de traduction
     */
    public function translate($key, $params = []) {
        $translation = $this->getTranslation($key);
        
        // Remplacer les paramètres si fournis
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $translation = str_replace(':' . $param, $value, $translation);
            }
        }
        
        return $translation;
    }
    
    /**
     * Récupérer une traduction avec support de clés imbriquées
     */
    private function getTranslation($key) {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Retourner la clé si la traduction n'existe pas
                return $key;
            }
        }
        
        return is_string($value) ? $value : $key;
    }
    
    /**
     * Obtenir la langue courante
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Changer la langue
     */
    public function setLanguage($language) {
        if (in_array($language, $this->availableLanguages)) {
            $this->currentLanguage = $language;
            $_SESSION['language'] = $language;
            $this->loadTranslations();
            return true;
        }
        return false;
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
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'pt' => 'Português'
        ];
        
        return $names[$code] ?? $code;
    }
    
    /**
     * Obtenir toutes les traductions pour l'administration
     */
    public function getAllTranslations() {
        return $this->translations;
    }
    
    /**
     * Sauvegarder les traductions
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
     * Vérifier si une traduction existe
     */
    public function hasTranslation($key) {
        return $this->getTranslation($key) !== $key;
    }
}

/**
 * Fonctions helper globales pour faciliter l'usage
 */

/**
 * Fonction de traduction courte
 */
function __($key, $params = []) {
    return I18nManager::getInstance()->translate($key, $params);
}

/**
 * Fonction de traduction avec echo
 */
function _e($key, $params = []) {
    echo __($key, $params);
}

/**
 * Fonction pour les traductions HTML
 */
function _h($key, $params = []) {
    return htmlspecialchars(__($key, $params));
}

/**
 * Fonction pour les traductions HTML avec echo
 */
function _he($key, $params = []) {
    echo _h($key, $params);
}

/**
 * Obtenir la langue courante
 */
function getCurrentLanguage() {
    return I18nManager::getInstance()->getCurrentLanguage();
}

/**
 * Changer la langue
 */
function setLanguage($language) {
    return I18nManager::getInstance()->setLanguage($language);
}

/**
 * Obtenir les langues disponibles
 */
function getAvailableLanguages() {
    return I18nManager::getInstance()->getAvailableLanguages();
}

/**
 * Obtenir le nom de la langue
 */
function getLanguageName($code) {
    return I18nManager::getInstance()->getLanguageName($code);
}

/**
 * Générer le sélecteur de langue
 */
function generateLanguageSelector() {
    $currentLang = getCurrentLanguage();
    $availableLangs = getAvailableLanguages();
    
    $html = '<div class="dropdown language-selector">';
    $html .= '<button class="btn btn-default dropdown-toggle" type="button" data-toggle="dropdown">';
    $html .= '<i class="fa fa-globe"></i> ' . getLanguageName($currentLang) . ' <span class="caret"></span>';
    $html .= '</button>';
    $html .= '<ul class="dropdown-menu">';
    
    foreach ($availableLangs as $lang) {
        $active = ($lang === $currentLang) ? ' class="active"' : '';
        $html .= '<li' . $active . '>';
        $html .= '<a href="?lang=' . $lang . '">';
        $html .= getLanguageName($lang);
        if ($lang === $currentLang) {
            $html .= ' <i class="fa fa-check"></i>';
        }
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}
?>