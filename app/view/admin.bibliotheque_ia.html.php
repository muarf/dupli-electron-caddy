<?php
require_once __DIR__ . '/../models/SettingsManager.php';
$_db = pdo_connect();
$_settings = new SettingsManager($_db);
$_ai = $_settings->getAll();

// Valeurs courantes avec fallbacks
$ai_enabled        = $_ai['ai_enabled'] ?? '0';
$ai_llm_url        = $_ai['ai_llm_url'] ?? 'http://localhost:11436/completion';
$ai_llm_url_pro    = $_ai['ai_llm_url_pro'] ?? 'http://localhost:11435/completion';
$ai_embedding_url  = $_ai['ai_embedding_url'] ?? 'http://localhost:11434/api/embeddings';
$ai_embedding_model= $_ai['ai_embedding_model'] ?? 'bge-m3';
$ai_reranker_url   = $_ai['ai_reranker_url'] ?? 'http://localhost:11437/rerank';
$ai_token          = $_ai['ai_token'] ?? '';
$ai_system_prompt  = $_ai['ai_system_prompt'] ?? '';
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
              <h3 class="panel-title"><i class="fa fa-power-off"></i> Activation</h3>
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
              <h3 class="panel-title"><i class="fa fa-comments"></i> Endpoints LLM (Synthèse / RAG)</h3>
            </div>
            <div class="panel-body">
              <p class="text-muted">Compatible : llama.cpp, Ollama, OpenRouter, Claude, OpenAI, et tout endpoint au format <code>/completion</code> ou <code>/v1/chat/completions</code>.</p>
              <div class="form-group">
                <label for="ai_llm_url">URL LLM Rapide (mode "Fast")</label>
                <input type="url" class="form-control" id="ai_llm_url" name="ai_llm_url"
                       value="<?php echo htmlspecialchars($ai_llm_url); ?>"
                       placeholder="http://localhost:11436/completion">
                <small class="text-muted">Ex : Luth via llama.cpp. Port actuel : 11436.</small>
              </div>
              <div class="form-group">
                <label for="ai_llm_url_pro">URL LLM Avancé (mode "Pro")</label>
                <input type="url" class="form-control" id="ai_llm_url_pro" name="ai_llm_url_pro"
                       value="<?php echo htmlspecialchars($ai_llm_url_pro); ?>"
                       placeholder="http://localhost:11435/completion">
                <small class="text-muted">Ex : Gemma via llama.cpp. Port actuel : 11435.</small>
              </div>
            </div>
          </div>

          <!-- Section 3 : Embedding -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-database"></i> Endpoint Embedding (Vectorisation)</h3>
            </div>
            <div class="panel-body">
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Attention :</strong> Changer de modèle d'embedding rend les vecteurs existants <strong>incompatibles</strong>.
                Si vous modifiez ces champs, relancez impérativement la vectorisation complète ci-dessous.
              </div>
              <div class="form-group">
                <label for="ai_embedding_url">URL Endpoint Embedding</label>
                <input type="url" class="form-control" id="ai_embedding_url" name="ai_embedding_url"
                       value="<?php echo htmlspecialchars($ai_embedding_url); ?>"
                       placeholder="http://localhost:11434/api/embeddings">
                <small class="text-muted">Ex : Ollama local (port 11434) ou endpoint distant compatible.</small>
              </div>
              <div class="form-group">
                <label for="ai_embedding_model">Nom du modèle d'embedding</label>
                <input type="text" class="form-control" id="ai_embedding_model" name="ai_embedding_model"
                       value="<?php echo htmlspecialchars($ai_embedding_model); ?>"
                       placeholder="bge-m3">
              </div>
            </div>
          </div>

          <!-- Section 4 : Re-ranker -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-sort-amount-desc"></i> Re-ranker (optionnel)</h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_reranker_url">URL Re-ranker</label>
                <input type="url" class="form-control" id="ai_reranker_url" name="ai_reranker_url"
                       value="<?php echo htmlspecialchars($ai_reranker_url); ?>"
                       placeholder="http://localhost:11437/rerank">
                <small class="text-muted">Si vide ou indisponible, le système utilise automatiquement le mode dégradé (similarité vectorielle seule).</small>
              </div>
            </div>
          </div>

          <!-- Section 5 : Authentification -->
          <div class="panel panel-warning">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-key"></i> Authentification</h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_token">Token / API Key</label>
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
                <small class="text-muted">Laisser vide pour les endpoints locaux (Ollama, llama.cpp). Requis pour OpenAI, OpenRouter, OVH, etc.</small>
              </div>
            </div>
          </div>

          <!-- Section 6 : Prompt Système -->
          <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title"><i class="fa fa-pencil"></i> Prompt Système</h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="ai_system_prompt">Instructions pour l'IA</label>
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
              <h3 class="panel-title"><i class="fa fa-lock"></i> Accès Sécurisé</h3>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="bibliotheque_password">Mot de passe de la Bibliothèque</label>
                <input type="text" class="form-control" id="bibliotheque_password" name="bibliotheque_password"
                       value="<?php echo htmlspecialchars($_ai['bibliotheque_password'] ?? ''); ?>"
                       placeholder="Laisser vide pour un accès libre">
                <small class="text-muted">
                  Si défini, un mot de passe sera demandé pour accéder à la bibliothèque. 
                  L'administrateur (vous) a toujours accès s'il est connecté.
                </small>
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
            <h3 class="panel-title"><i class="fa fa-cog fa-spin-hover"></i> Maintenance — Vectorisation</h3>
          </div>
          <div class="panel-body">
            <div class="alert alert-danger">
              <i class="fa fa-exclamation-circle"></i>
              <strong>⚠️ Opération longue :</strong> Cette action va recalculer <strong>TOUS</strong> les vecteurs de la bibliothèque depuis zéro.
              Selon la taille de votre bibliothèque et la puissance de votre serveur, cela peut prendre <strong>plusieurs heures, voire plusieurs jours</strong>.
              N'éteignez pas le serveur pendant l'opération.
            </div>
            <p>Utilisez ce bouton si vous avez changé de modèle d'embedding, ou si vous suspectez que les vecteurs sont corrompus ou incomplets.</p>
            <button class="btn btn-danger btn-lg" id="btn-vectorize" onclick="triggerVectorization()">
              <i class="fa fa-refresh"></i> Relancer la vectorisation totale
            </button>
            <div id="vectorize-status" style="display:none; margin-top:15px;">
              <div class="progress">
                <div class="progress-bar progress-bar-striped active" style="width:100%">
                  Vectorisation en cours en arrière-plan...
                </div>
              </div>
              <p class="text-muted text-center" id="vectorize-msg"></p>
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

<script>
function toggleToken() {
    const field = document.getElementById('ai_token');
    const eye = document.getElementById('token-eye');
    if (field.type === 'password') {
        field.type = 'text';
        eye.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        eye.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.getElementById('ai-settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sauvegarde...';

    const formData = new FormData(this);

    fetch('?save_ai_settings', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            const alert = document.getElementById('ai-save-alert');
            if (data.success) {
                alert.className = 'alert alert-success';
                alert.innerHTML = '<i class="fa fa-check"></i> ' + data.message;
            } else {
                alert.className = 'alert alert-danger';
                alert.innerHTML = '<i class="fa fa-times"></i> Erreur : ' + (data.error || 'Inconnue');
            }
            alert.style.display = 'block';
            window.scrollTo(0, 0);
        })
        .catch(err => {
            const alert = document.getElementById('ai-save-alert');
            alert.className = 'alert alert-danger';
            alert.innerHTML = '<i class="fa fa-times"></i> Erreur réseau : ' + err;
            alert.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> Sauvegarder les réglages IA';
        });
});

function triggerVectorization() {
    if (!confirm('⚠️ Êtes-vous sûr ? Cette opération peut durer plusieurs heures. Continuer ?')) return;

    const btn = document.getElementById('btn-vectorize');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Lancement en cours...';

    fetch('?trigger_vectorization', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('vectorize-status').style.display = 'block';
                document.getElementById('vectorize-msg').textContent = data.message;
            } else {
                alert('Erreur : ' + (data.error || 'Inconnue'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-refresh"></i> Relancer la vectorisation totale';
            }
        })
        .catch(() => {
            alert('Erreur réseau lors du déclenchement.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-refresh"></i> Relancer la vectorisation totale';
        });
}
</script>
