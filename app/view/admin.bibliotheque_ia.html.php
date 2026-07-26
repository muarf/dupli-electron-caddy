<?php
require_once __DIR__ . '/../models/SettingsManager.php';
$_db = pdo_connect();
$_settings = new SettingsManager($_db);
$_ai = $_settings->getAll();

// Valeurs courantes avec fallbacks
$ai_enabled        = $_ai['ai_enabled'] ?? '0';
$ai_llm_url        = $_ai['ai_llm_url'] ?? 'http://localhost:11436/completion';
$ai_llm_url_pro    = $_ai['ai_llm_url_pro'] ?? 'http://localhost:11435/completion';
$ai_llm_url_nemotron = $_ai['ai_llm_url_nemotron'] ?? 'http://localhost:11438/completion';
$ai_embedding_url  = $_ai['ai_embedding_url'] ?? 'http://localhost:11434/api/embeddings';
$ai_embedding_model= $_ai['ai_embedding_model'] ?? 'bge-m3';
$ai_reranker_url   = $_ai['ai_reranker_url'] ?? 'http://localhost:11437/rerank';
$ai_token          = $_ai['ai_token'] ?? '';
$ai_system_prompt  = $_ai['ai_system_prompt'] ?? '';
$studio_api_fonts_url   = $_ai['studio_api_fonts_url'] ?? '';
$studio_api_docling_url = $_ai['studio_api_docling_url'] ?? '';
$whatfontis_api_key     = $_ai['whatfontis_api_key'] ?? '';
?>

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">

        <h1 class="text-center"><i class="fa fa-robot"></i> Intelligence Artificielle &amp; Bibliothèque</h1>
        <hr>

        <div id="ai-save-alert" class="alert" style="display:none;"></div>

        <form id="ai-settings-form">

          <!-- Section 1 : Activation -->
          <div class="panel panel-<?php echo $ai_enabled ? 'success' : 'default'; ?>" id="panel-enabled">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-power-off"></i><?php _e("admin_bibliotheque_ia.auto_key_1", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label class="switch-label">
                  <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?php echo $ai_enabled ? 'checked' : ''; ?>>
                  <span class="switch-slider"></span>
                  Activer l'IA pour la Bibliothèque
                </label>
                <p class="text-muted" style="margin-top:8px;">
                  Si désactivé, la bibliothèque fonctionne en mode classique (recherche SQL uniquement). Aucun bloc IA n'est visible pour les utilisateurs.
                </p>
              </div>
            </div>
          </div>

          <!-- Section 2 : LLM -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-comments"></i><?php _e("admin_bibliotheque_ia.auto_key_2", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <p class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_3", [], false); ?><code><?php _e("admin_bibliotheque_ia.auto_key_4", [], false); ?></code> ou <code><?php _e("admin_bibliotheque_ia.auto_key_5", [], false); ?></code>.</p>
              <div class="form-group">
                <label for="ai_llm_url"><?php _e("admin_bibliotheque_ia.auto_key_6", [], false); ?></label>
                <input type="url" class="form-control" id="ai_llm_url" name="ai_llm_url"
                       value="<?php echo htmlspecialchars($ai_llm_url); ?>"
                       placeholder="http://localhost:11436/completion">
                <small class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_7", [], false); ?></small>
              </div>
              <div class="form-group mb-4">
                <label for="ai_llm_url_pro"><?php _e("admin_bibliotheque_ia.auto_key_8", [], false); ?></label>
                <input type="url" class="form-control" id="ai_llm_url_pro" name="ai_llm_url_pro"
                       value="<?php echo htmlspecialchars($ai_llm_url_pro); ?>"
                       placeholder="http://localhost:11435/completion">
                <small class="form-text text-muted"><?php _e("admin_bibliotheque_ia.auto_key_9", [], false); ?></small>
            </div>
            
            <div class="form-group mb-4">
                <label for="ai_llm_url_nemotron"><?php _e("admin_bibliotheque_ia.auto_key_10", [], false); ?></label>
                <input type="url" class="form-control" id="ai_llm_url_nemotron" name="ai_llm_url_nemotron"
                       value="<?php echo htmlspecialchars($ai_llm_url_nemotron); ?>"
                       placeholder="http://localhost:11438/completion">
                <small class="form-text text-muted"><?php _e("admin_bibliotheque_ia.auto_key_11", [], false); ?></small>
            </div>
            </div>
          </div>

          <!-- Section 3 : Embedding -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-database"></i><?php _e("admin_bibliotheque_ia.auto_key_12", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                <strong><?php _e("admin_bibliotheque_ia.auto_key_13", [], false); ?></strong><?php _e("admin_bibliotheque_ia.auto_key_14", [], false); ?><strong><?php _e("admin_bibliotheque_ia.auto_key_15", [], false); ?></strong>.
                Si vous modifiez ces champs, relancez impérativement la vectorisation complète ci-dessous.
              </div>
              <div class="form-group">
                <label for="ai_embedding_url"><?php _e("admin_bibliotheque_ia.auto_key_16", [], false); ?></label>
                <input type="url" class="form-control" id="ai_embedding_url" name="ai_embedding_url"
                       value="<?php echo htmlspecialchars($ai_embedding_url); ?>"
                       placeholder="http://localhost:11434/api/embeddings">
                <small class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_17", [], false); ?></small>
              </div>
              <div class="form-group">
                <label for="ai_embedding_model"><?php _e("admin_bibliotheque_ia.auto_key_18", [], false); ?></label>
                <input type="text" class="form-control" id="ai_embedding_model" name="ai_embedding_model"
                       value="<?php echo htmlspecialchars($ai_embedding_model); ?>"
                       placeholder="bge-m3">
              </div>
            </div>
          </div>

          <!-- Section 4 : Re-ranker -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-sort-amount-desc"></i><?php _e("admin_bibliotheque_ia.auto_key_19", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_reranker_url"><?php _e("admin_bibliotheque_ia.auto_key_20", [], false); ?></label>
                <input type="url" class="form-control" id="ai_reranker_url" name="ai_reranker_url"
                       value="<?php echo htmlspecialchars($ai_reranker_url); ?>"
                       placeholder="http://localhost:11437/rerank">
                <small class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_21", [], false); ?></small>
              </div>
            </div>
          </div>

          <!-- Section 5 : Authentification -->
          <div class="panel panel-warning">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-key"></i><?php _e("admin_bibliotheque_ia.auto_key_22", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_token"><?php _e("admin_bibliotheque_ia.auto_key_23", [], false); ?></label>
                <div class="input-group">
                  <input type="password" class="form-control" id="ai_token" name="ai_token"
                         value="<?php echo htmlspecialchars($ai_token); ?>"
                         placeholder="sk-... ou Bearer token">
                  <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="toggleToken()">
                      <i class="fa fa-eye" id="token-eye"></i>
                    </button>
                  </span>
                </div>
                <small class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_24", [], false); ?></small>
              </div>
            </div>
          </div>

          <!-- Section 6 : Prompt Système -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-pencil"></i><?php _e("admin_bibliotheque_ia.auto_key_25", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_system_prompt"><?php _e("admin_bibliotheque_ia.auto_key_26", [], false); ?></label>
                <textarea class="form-control" id="ai_system_prompt" name="ai_system_prompt" rows="6"
                          placeholder="Tu es un assistant expert..."><?php echo htmlspecialchars($ai_system_prompt); ?></textarea>
                <small class="text-muted">
                  Ce texte définit le comportement et la "personnalité" de l'IA.
                  Le contexte documentaire et la question de l'utilisateur sont ajoutés automatiquement.
                </small>
              </div>
            </div>
          </div>

          <!-- Section 7 : Sécurité de la Bibliothèque -->
          <div class="panel panel-warning">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-lock"></i><?php _e("admin_bibliotheque_ia.auto_key_27", [], false); ?></h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="bibliotheque_password"><?php _e("admin_bibliotheque_ia.auto_key_28", [], false); ?></label>
                <input type="text" class="form-control" id="bibliotheque_password" name="bibliotheque_password"
                       value="<?php echo htmlspecialchars($_ai['bibliotheque_password'] ?? ''); ?>"
                       placeholder="<?= __('admin_bibliotheque_ia.password_placeholder') ?>">
                <small class="text-muted">
                  Si défini, un mot de passe sera demandé pour accéder à la bibliothèque. 
                  L'administrateur (vous) a toujours accès s'il est connecté.
                </small>
              </div>
            </div>
          </div>

          <!-- Section Studio IA : Endpoints VPS -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-magic"></i> Studio — Outils IA (VPS)</h3>
            </div>
            <div class="panel-body">
              <p class="text-muted">
                Ces outils utilisent des modèles d'IA lourds (reconnaissance de polices, conversion Docling)
                qui doivent tourner sur un serveur distant. Laissez vide pour désactiver la fonctionnalité
                ou pour utiliser un environnement local de développement.
              </p>

              <div class="form-group">
                <label for="studio_api_fonts_url">URL API — Reconnaissance de police</label>
                <input type="url" class="form-control" id="studio_api_fonts_url" name="studio_api_fonts_url"
                       value="<?php echo htmlspecialchars($studio_api_fonts_url); ?>"
                       placeholder="https://vps.example.com/api/font-recognizer">
                <small class="text-muted">
                  Endpoint POST JSON <code>{"image": "&lt;base64&gt;"}</code> → réponse <code>[{"label": "...", "score": 0.xx}]</code>.
                  Si vide, le bouton "Reconnaître" sera inopérant sur Windows.
                </small>
              </div>

              <div class="form-group">
                <label for="whatfontis_api_key">Clé API — WhatFontIs (Optionnel)</label>
                <input type="text" class="form-control" id="whatfontis_api_key" name="whatfontis_api_key"
                       value="<?php echo htmlspecialchars($whatfontis_api_key); ?>"
                       placeholder="<?= __('admin_bibliotheque_ia.whatfontis_placeholder') ?>">
                <small class="text-muted">
                  Si renseignée, l'API commerciale tierce WhatFontIs.com sera utilisée pour la reconnaissance de police au lieu du modèle local/VPS.
                </small>
              </div>

              <div class="form-group">
                <label for="studio_api_docling_url">URL API — Conversion Docling (PDF → DOCX)</label>
                <input type="url" class="form-control" id="studio_api_docling_url" name="studio_api_docling_url"
                       value="<?php echo htmlspecialchars($studio_api_docling_url); ?>"
                       placeholder="https://vps.example.com/api/docling-convert">
                <small class="text-muted">
                  Endpoint POST JSON <code>{"pdf": "&lt;base64&gt;", "filename": "doc.pdf"}</code>
                  → réponse <code>{"docx": "&lt;base64&gt;"}</code> ou binaire .docx direct.
                  Si vide, seul l'environnement local <code><?php _e("admin_bibliotheque_ia.auto_key_29", [], false); ?></code> est utilisé.
                </small>
              </div>
            </div>
          </div>

          <!-- Section Studio IA : Installation Locale -->
          <div class="panel panel-default">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-download"></i> Studio — Installation IA Locale (Optionnel)</h3>
            </div>
            <div class="panel-body">
              <p class="text-muted">
                Si vous ne disposez pas d'un VPS, vous pouvez télécharger les modèles lourds (PyTorch, Docling, etc.)
                sur cette machine. <strong><?php _e("admin_bibliotheque_ia.auto_key_30", [], false); ?></strong>
              </p>
              
              <div class="form-group">
                <label for="ai_local_path"><?php _e("admin_bibliotheque_ia.auto_key_31", [], false); ?></label>
                <input type="text" class="form-control" id="ai_local_path" name="ai_local_path"
                       value="<?php echo htmlspecialchars($_ai['ai_local_path'] ?? ''); ?>"
                       placeholder="Ex: D:\Duplicator_IA (Laissez vide pour le dossier par défaut)">
                <small class="text-muted"><?php _e("admin_bibliotheque_ia.auto_key_32", [], false); ?></small>
              </div>

              <div class="form-group" style="margin-top: 15px;">
                <button type="button" class="btn btn-default" onclick="installLocalAi()">
                  <i class="fa fa-terminal"></i> Installer / Mettre à jour l'IA Locale
                </button>
              </div>
            </div>
          </div>

          <!-- Bouton Sauvegarde -->
          <div class="form-group">
            <button type="submit" class="btn btn-success btn-lg btn-block" id="btn-save">
              <i class="fa fa-save"></i> Sauvegarder les réglages IA
            </button>
          </div>

        </form>

        <hr>

        <!-- Section 7 : Maintenance -->
        <div class="panel panel-danger">
          <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-cog fa-spin-hover"></i><?php _e("admin_bibliotheque_ia.auto_key_33", [], false); ?></h3>
          </div>
          <div class="panel-body">
            <div class="alert alert-info">
              <i class="fa fa-info-circle"></i>
              <strong><?php _e("admin_bibliotheque_ia.auto_key_34", [], false); ?></strong>
              <ul>
                <li><strong><?php _e("admin_bibliotheque_ia.auto_key_35", [], false); ?></strong><?php _e("admin_bibliotheque_ia.auto_key_36", [], false); ?></li>
                <li><strong><?php _e("admin_bibliotheque_ia.auto_key_37", [], false); ?></strong><?php _e("admin_bibliotheque_ia.auto_key_38", [], false); ?><strong><?php _e("admin_bibliotheque_ia.auto_key_39", [], false); ?></strong><?php _e("admin_bibliotheque_ia.auto_key_40", [], false); ?></li>
              </ul>
            </div>
            
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;">
              <button class="btn btn-warning" id="btn-vectorize-missing" onclick="triggerVectorization('missing')">
                <i class="fa fa-search-plus"></i> Compléter les vecteurs manquants
              </button>
              <button class="btn btn-danger" id="btn-vectorize-all" onclick="triggerVectorization('all')">
                <i class="fa fa-refresh"></i> Réinitialiser et TOUT re-vectoriser
              </button>
            </div>
            
            <div id="vectorize-status" style="display:none; margin-top:15px;">
              <div class="progress">
                <div class="progress-bar progress-bar-striped active" style="width:100%">
                  Vectorisation en cours en arrière-plan...
                </div>
              </div>
              <p class="text-muted text-center" id="vectorize-msg"></p>
            </div>
            <hr>
            <h4><i class="fa fa-file-text-o"></i><?php _e("admin_bibliotheque_ia.auto_key_41", [], false); ?></h4>
            <p>
              Retraite tous les PDF avec <strong><?php _e("admin_bibliotheque_ia.auto_key_42", [], false); ?></strong> pour générer des chunks sémantiques par sections (titres, chapitres…) au lieu du découpage naïf par mots.
              Les anciens chunks sont <strong><?php _e("admin_bibliotheque_ia.auto_key_43", [], false); ?></strong> — le RAG reste fonctionnel pendant l'opération.
            </p>
            <div id="markdown-counts" class="row" style="margin-bottom:12px;">
              <div class="col-xs-3 text-center"><span class="badge" style="background:#777;" id="md-count-raw">…</span><br><small><?php _e("admin_bibliotheque_ia.auto_key_44", [], false); ?></small></div>
              <div class="col-xs-3 text-center"><span class="badge" style="background:#5bc0de;" id="md-count-processing">…</span><br><small><?php _e("admin_bibliotheque_ia.auto_key_45", [], false); ?></small></div>
              <div class="col-xs-3 text-center"><span class="badge" style="background:#5cb85c;" id="md-count-done">…</span><br><small><?php _e("admin_bibliotheque_ia.auto_key_46", [], false); ?></small></div>
              <div class="col-xs-3 text-center"><span class="badge" style="background:#d9534f;" id="md-count-error">…</span><br><small><?php _e("admin_bibliotheque_ia.auto_key_47", [], false); ?></small></div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
              <button class="btn btn-primary" id="btn-markdown-all" onclick="triggerMarkdownMigration('all')">
                <i class="fa fa-magic"></i> Migrer tous les PDFs (raw)
              </button>
              <button class="btn btn-warning" id="btn-markdown-retry" onclick="triggerMarkdownMigration('retry')">
                <i class="fa fa-refresh"></i> Relancer les erreurs
              </button>
              <button class="btn btn-default" id="btn-markdown-force" onclick="triggerMarkdownMigration('force')" title="<?= __('admin_bibliotheque_ia.force_all_title') ?>">
                <i class="fa fa-repeat"></i> Tout retraiter
              </button>
              <button class="btn btn-danger" id="btn-markdown-stop" onclick="stopMarkdownMigration()" style="display:none;">
                <i class="fa fa-stop"></i> Stopper la migration
              </button>
            </div>
            <div id="markdown-status" style="display:none; margin-top:10px;">
              <div class="progress">
                <div class="progress-bar progress-bar-striped active" id="markdown-progress-bar" style="width:100%">
                  Migration en cours en arrière-plan…
                </div>
              </div>
              <p class="text-muted text-center" id="markdown-msg" style="font-size:0.9em;"></p>
            </div>
            
            <div id="markdown-logs-container" style="display:none; margin-top:15px;">
              <p><strong><i class="fa fa-terminal"></i><?php _e("admin_bibliotheque_ia.auto_key_48", [], false); ?></strong></p>
              <pre id="markdown-logs" style="font-size: 0.85em; background: #222; color: #0f0; max-height: 250px; overflow-y: auto; border: 1px solid #000; padding: 10px;"></pre>
            </div>

            <hr>
            <h4><i class="fa fa-search"></i><?php _e("admin_bibliotheque_ia.auto_key_49", [], false); ?></h4>
            <p><?php _e("admin_bibliotheque_ia.auto_key_50", [], false); ?></p>
            <button class="btn btn-warning btn-lg" id="btn-rescan" onclick="rescanLibrary('all')">
              <i class="fa fa-refresh"></i> Lancer un scan complet
            </button>
            <div id="rescan-status" style="display:none; margin-top:15px;">
              <div class="progress">
                <div class="progress-bar progress-bar-striped active progress-bar-warning" id="rescan-progress-bar" style="width:0%">0%</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Retour -->
        <div class="row">
          <div class="col-md-12">
            <a href="?admin" class="btn btn-default btn-block">
              <i class="fa fa-arrow-left"></i> Retour à l'administration
            </a>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
.switch-label {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 1.1em;
    cursor: pointer;
    user-select: none;
}
.switch-label input[type="checkbox"] {
    width: 0;
    height: 0;
    opacity: 0;
    position: absolute;
}
.switch-slider {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    background: #ccc;
    border-radius: 28px;
    transition: background 0.3s;
    flex-shrink: 0;
}
.switch-slider::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s;
}
.switch-label input:checked + .switch-slider {
    background: #5cb85c;
}
.switch-label input:checked + .switch-slider::after {
    transform: translateX(24px);
}
</style>

<script src="js/admin-bibliotheque-ia.js" defer></script>
