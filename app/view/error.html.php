<style>
    .error-container {
        max-width: 800px;
        margin: 50px auto;
        padding: 30px;
    }
    .error-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .error-header h1 {
        margin: 0;
        font-size: 48px;
    }
    .error-header p {
        margin: 10px 0 0 0;
        font-size: 18px;
        opacity: 0.9;
    }
    .error-details {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .error-type {
        display: inline-block;
        padding: 5px 15px;
        background: #f44336;
        color: white;
        border-radius: 20px;
        font-size: 14px;
        margin-bottom: 15px;
    }
    .error-message {
        font-size: 18px;
        color: #333;
        margin: 15px 0;
        padding: 15px;
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
    }
    .error-file {
        font-family: monospace;
        color: #666;
        margin: 10px 0;
        padding: 10px;
        background: #f5f5f5;
        border-radius: 4px;
    }
    .error-trace {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    .error-trace pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
        font-size: 13px;
        line-height: 1.6;
    }
    .error-actions {
        text-align: center;
        margin-top: 30px;
    }
    .btn-error-action {
        margin: 0 10px;
        padding: 12px 30px;
        font-size: 16px;
    }
</style>

<div class="error-container">
    <div class="error-header">
        <h1><i class="fa fa-exclamation-triangle"></i></h1>
        <p><?= isset($error_title) ? htmlspecialchars($error_title) : 'Une erreur est survenue' ?></p>
    </div>
    
    <div class="error-details">
        <?php if (isset($error_type)): ?>
            <span class="error-type"><?= htmlspecialchars($error_type) ?></span>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <strong><i class="fa fa-info-circle"></i> Message :</strong><br>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_file)): ?>
            <div class="error-file">
                <strong><i class="fa fa-file-code-o"></i> Fichier :</strong> <?= htmlspecialchars($error_file) ?>
                <?php if (isset($error_line)): ?>
                    à la ligne <strong><?= htmlspecialchars($error_line) ?></strong>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_trace) && !empty($error_trace)): ?>
            <div class="error-trace">
                <strong><i class="fa fa-list"></i> Trace d'exécution :</strong>
                <pre><?= htmlspecialchars($error_trace) ?></pre>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="error-actions">
        <button onclick="history.back()" class="btn btn-primary btn-error-action">
            <i class="fa fa-arrow-left"></i> Retour
        </button>
        <a href="?accueil" class="btn btn-default btn-error-action">
            <i class="fa fa-home"></i> Accueil
        </a>
        <?php if (isset($_SESSION['user'])): ?>
            <a href="?admin" class="btn btn-info btn-error-action">
                <i class="fa fa-cog"></i> Administration
            </a>
        <?php endif; ?>
    </div>
    
    <?php if (isset($error_help)): ?>
        <div class="alert alert-info" style="margin-top: 30px;">
            <strong><i class="fa fa-lightbulb-o"></i> Suggestion :</strong>
            <?= htmlspecialchars($error_help) ?>
        </div>
    <?php endif; ?>
</div>

