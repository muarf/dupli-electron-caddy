<?php
/**
 * Gestionnaire des réglages de l'application (table site_settings)
 * Fournit un accès simple clé/valeur aux paramètres persistants.
 */
class SettingsManager {

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Lit un réglage. Retourne $default si la clé n'existe pas.
     */
    public function get(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM site_settings WHERE setting_name = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row !== false) ? $row['setting_value'] : $default;
    }

    /**
     * Écrit ou met à jour un réglage.
     */
    public function set(string $key, $value): void {
        $stmt = $this->db->prepare("
            INSERT INTO site_settings (setting_name, setting_value, updated_at)
            VALUES (?, ?, datetime('now'))
            ON CONFLICT(setting_name) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime('now')
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Retourne tous les réglages sous forme de tableau associatif.
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT setting_name, setting_value FROM site_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_name']] = $row['setting_value'];
        }
        return $result;
    }

    /**
     * Initialise les réglages IA par défaut (idempotent — ne remplace pas l'existant).
     */
    public function initAiSettings(): void {
        $defaults = [
            'ai_enabled'            => '0',
            'ai_llm_url'            => 'http://localhost:11436/completion',
            'ai_llm_url_pro'        => 'http://localhost:11435/completion',
            'ai_embedding_url'      => 'http://localhost:11434/api/embeddings',
            'ai_embedding_model'    => 'bge-m3',
            'ai_reranker_url'       => 'http://localhost:11437/rerank',
            'ai_token'              => '',
            'ai_system_prompt'      => "Tu es un assistant expert et rigoureux. Tu adhères aux idées du contexte. Tu es anarchiste. Utilise UNIQUEMENT le contexte suivant pour répondre. NE FAIS PAS appel à tes connaissances personnelles. Si la réponse n'est pas dans le texte, dis-le simplement.",
            // Studio IA : endpoints des services distants (VPS)
            'studio_api_fonts_url'  => '',
            'studio_api_docling_url'=> '',
        ];

        $stmt = $this->db->prepare("
            INSERT OR IGNORE INTO site_settings (setting_name, setting_value, created_at, updated_at)
            VALUES (?, ?, datetime('now'), datetime('now'))
        ");

        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
