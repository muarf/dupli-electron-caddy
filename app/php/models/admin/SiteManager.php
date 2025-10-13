<?php
require_once __DIR__ . '/../../controler/functions/database.php';
require_once __DIR__ . '/../../controler/functions/utilities.php';

/**
 * Module de gestion des statistiques et paramètres du site
 * Gère les statistiques, paramètres et emails
 */

class SiteManager {
    private $conf;
    private $con;
    
    public function __construct($conf) {
        $this->conf = $conf;
        // Utilisation directe de pdo_connect() au lieu de Pdotest
    }
    
    /**
     * Obtenir les statistiques du site
     */
    public function getStats() {
        $stats = array();
        
        try {
            $db = pdo_connect();
            
            // Total des tirages
            $total_tirages = 0;
            $machines = array('a3', 'a4', 'photocop');
            foreach ($machines as $machine) {
                $query = $db->prepare("SELECT COUNT(*) as count FROM $machine");
                $query->execute();
                $result = $query->fetch(PDO::FETCH_OBJ);
                $total_tirages += $result->count;
            }
            $stats['total_tirages'] = $total_tirages;
            
            // Nombre de machines configurées
            $machines_count = 0;
            foreach ($machines as $machine) {
                $query = $db->prepare("SELECT COUNT(*) as count FROM $machine WHERE type = 'initialisation'");
                $query->execute();
                $result = $query->fetch(PDO::FETCH_OBJ);
                $machines_count += $result->count;
            }
            $stats['machines_count'] = $machines_count;
            
            // Nombre de news
            $query = $db->prepare("SELECT COUNT(*) as count FROM news");
            $query->execute();
            $result = $query->fetch(PDO::FETCH_OBJ);
            $stats['news_count'] = $result->count;
            
            // Nombre d'emails
            $stats['emails_count'] = count(count_emails());
            
        } catch (PDOException $e) {
            // En cas d'erreur, retourner des valeurs par défaut
            $stats = array(
                'total_tirages' => 0,
                'machines_count' => 0,
                'news_count' => 0,
                'emails_count' => 0
            );
        }
        
        return $stats;
    }
    
    /**
     * Mettre à jour les paramètres du site
     */
    public function updateSiteSettings($settings) {
        $result = array();
        
        try {
            foreach ($settings as $key => $value) {
                $this->updateSiteSetting($key, $value);
            }
            
            $result['success'] = "Paramètres mis à jour avec succès.";
            
        } catch (Exception $e) {
            $result['error'] = "Erreur lors de la mise à jour des paramètres : " . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Mettre à jour un paramètre du site
     */
    public function updateSiteSetting($setting_name, $setting_value) {
        try {
            $db = pdo_connect();
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO site_settings (setting_name, setting_value) VALUES (?, ?)");
            $stmt->execute(array($setting_name, $setting_value));
            
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la mise à jour du paramètre $setting_name : " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir un paramètre du site
     */
    public function getSiteSetting($setting_name, $default = '') {
        try {
            $db = pdo_connect();
            
            $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_name = ?");
            $stmt->execute(array($setting_name));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['setting_value'] : $default;
            
        } catch (PDOException $e) {
            return $default;
        }
    }
    
    /**
     * Obtenir la liste des emails
     */
    public function getEmails() {
        try {
            return count_emails();
        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Supprimer un email
     */
    public function deleteEmail($email) {
        try {
            delete_mail($email);
            return array('success' => 'Email supprimé avec succès.');
        } catch (Exception $e) {
            return array('error' => 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }
    
    /**
     * Supprimer tous les emails
     */
    public function deleteAllEmails() {
        try {
            $db = pdo_connect();
            $query = $db->query('DELETE FROM email');
            $deleted_count = $query->rowCount();
            return array('success' => "Tous les emails ont été supprimés avec succès ($deleted_count email(s) supprimé(s)).");
        } catch (Exception $e) {
            return array('error' => 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }
    
    /**
     * Obtenir les paramètres actuels du site
     */
    public function getCurrentSettings() {
        return array(
            'show_mailing_list' => $this->getSiteSetting('show_mailing_list', '1'),
            'enable_counter_mode' => $this->getSiteSetting('enable_counter_mode', '0'),
            'enable_manual_mode' => $this->getSiteSetting('enable_manual_mode', '0'),
            'stats_intro_text' => $this->getSiteSetting('stats_intro_text', ''),
            'formphoto_attention_message' => $this->getSiteSetting('formphoto_attention_message', '')
        );
    }
}
?>


