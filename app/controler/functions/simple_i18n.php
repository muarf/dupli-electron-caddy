<?php
/**
 * Système de traduction simple pour Duplicator
 * Version simplifiée pour commencer avec header/footer
 */

class SimpleI18n {
    private static $instance = null;
    private $currentLanguage = 'fr';
    private $translations = [];
    
    private function __construct() {
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
     * Détection simple de la langue
     */
    private function detectLanguage() {
        // 1. Vérifier les paramètres GET
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
            $this->currentLanguage = $_GET['lang'];
            $_SESSION['language'] = $this->currentLanguage;
            return;
        }
        
        // 2. Vérifier la session
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], ['fr', 'en'])) {
            $this->currentLanguage = $_SESSION['language'];
            return;
        }
        
        // 3. Détecter depuis les headers HTTP
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            if (strpos($acceptLanguage, 'en') !== false) {
                $this->currentLanguage = 'en';
                $_SESSION['language'] = 'en';
                return;
            }
        }
        
        // 4. Français par défaut
        $this->currentLanguage = 'fr';
    }
    
    /**
     * Charger les traductions
     */
    private function loadTranslations() {
        $translationFile = __DIR__ . '/../../translations/' . $this->currentLanguage . '.json';
        
        if (file_exists($translationFile)) {
            $content = file_get_contents($translationFile);
            $this->translations = json_decode($content, true) ?: [];
        } else {
            // Charger le français par défaut
            $fallbackFile = __DIR__ . '/../../translations/fr.json';
            if (file_exists($fallbackFile)) {
                $content = file_get_contents($fallbackFile);
                $this->translations = json_decode($content, true) ?: [];
            }
        }
    }
    
    /**
     * Traduire une clé
     */
    public function translate($key) {
        return isset($this->translations[$key]) ? $this->translations[$key] : $key;
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
        if (in_array($language, ['fr', 'en'])) {
            $this->currentLanguage = $language;
            $_SESSION['language'] = $language;
            $this->loadTranslations();
            return true;
        }
        return false;
    }
}

/**
 * Fonctions helper globales
 */
function __($key) {
    return SimpleI18n::getInstance()->translate($key);
}

function _e($key) {
    echo __($key);
}

function _h($key) {
    return htmlspecialchars(__($key));
}

function _he($key) {
    echo _h($key);
}

function getCurrentLanguage() {
    return SimpleI18n::getInstance()->getCurrentLanguage();
}

function setLanguage($language) {
    return SimpleI18n::getInstance()->setLanguage($language);
}

/**
 * Générer le sélecteur de langue simple
 */
function generateLanguageSelector() {
    $currentLang = getCurrentLanguage();
    
    $html = '<div class="dropdown language-selector" style="display: inline-block;">';
    $html .= '<button class="btn btn-default btn-sm dropdown-toggle" type="button" data-toggle="dropdown">';
    $html .= '<i class="fa fa-globe"></i> ';
    $html .= ($currentLang === 'fr') ? 'Français' : 'English';
    $html .= ' <span class="caret"></span>';
    $html .= '</button>';
    $html .= '<ul class="dropdown-menu">';
    
    // Français
    $active = ($currentLang === 'fr') ? ' class="active"' : '';
    $html .= '<li' . $active . '>';
    $html .= '<a href="?lang=fr">';
    $html .= 'Français';
    if ($currentLang === 'fr') {
        $html .= ' <i class="fa fa-check"></i>';
    }
    $html .= '</a>';
    $html .= '</li>';
    
    // Anglais
    $active = ($currentLang === 'en') ? ' class="active"' : '';
    $html .= '<li' . $active . '>';
    $html .= '<a href="?lang=en">';
    $html .= 'English';
    if ($currentLang === 'en') {
        $html .= ' <i class="fa fa-check"></i>';
    }
    $html .= '</a>';
    $html .= '</li>';
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}
?>