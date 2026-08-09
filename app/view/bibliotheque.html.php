<?php
/**
 * Vue Bibliothèque avec Assistant IA (conditionnel selon réglages admin)
 */
require_once __DIR__ . '/../models/SettingsManager.php';
$_bib_db = pdo_connect();
$_bib_settings = new SettingsManager($_bib_db);
$ai_enabled = (int)$_bib_settings->get('ai_enabled', 0);

// --- SÉCURITÉ PAR MOT DE PASSE ---
$bib_password = $_bib_settings->get('bibliotheque_password', '');
$is_admin = isset($_SESSION['user']);
$is_authenticated = isset($_SESSION['bib_authenticated']) && $_SESSION['bib_authenticated'] === true;

if (!empty($bib_password) && !$is_admin && !$is_authenticated) {
    $maxAttempts = 5;
    $attemptsKey = 'bib_auth_attempts';
    $lockoutKey = 'bib_auth_lockout';

    if (isset($_SESSION[$lockoutKey]) && time() < $_SESSION[$lockoutKey]) {
        $waitTime = $_SESSION[$lockoutKey] - time();
        $bib_error = __("bibliotheque.too_many_attempts_wait", ["waitTime" => $waitTime]);
    } elseif (isset($_SESSION[$lockoutKey]) && time() >= $_SESSION[$lockoutKey]) {
        // Lockout expired — reset counters
        unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);
    }

    if (!isset($_SESSION[$lockoutKey]) && isset($_POST['bib_pass'])) {
        $attempts = (int)($_SESSION[$attemptsKey] ?? 0);
        if ($attempts >= $maxAttempts) {
            $_SESSION[$lockoutKey] = time() + 60; // Verrouillé 60 sec
            $bib_error = __("bibliotheque.too_many_failed_attempts");
        } else {
            $inputPass = $_POST['bib_pass'];
            $isValid = password_verify($inputPass, $bib_password) || ($inputPass === $bib_password);

            if ($isValid) {
                // Auto-migration si le mdp était stocké en clair
                if ($inputPass === $bib_password && !password_verify($inputPass, $bib_password)) {
                    $_bib_settings->set('bibliotheque_password', password_hash($inputPass, PASSWORD_BCRYPT));
                }
                unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);
                $_SESSION['bib_authenticated'] = true;
                header('Location: ?bibliotheque');
                exit;
            } else {
                $_SESSION[$attemptsKey] = $attempts + 1;
                $remaining = $maxAttempts - $_SESSION[$attemptsKey];
                $bib_error = __("bibliotheque.wrong_password", ["remaining" => $remaining]);
            }
        }
    }
    // Affichage du formulaire de login dédié
    include __DIR__ . '/bibliotheque_login.html.php';
    return;
}
?>
<div class="container-fluid mt-4">
    <style>
    /* --- DESIGN SYSTEM PREMIUM --- */
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --secondary: #a855f7;
        --bg-light: #f8fafc;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    /* Toolbar Bibliothèque */
    .library-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background: white;
        padding: 15px 20px;
        border-radius: 15px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        border: 1px solid #f1f5f9;
    }
    .search-container {
        flex: 1;
        min-width: 300px;
        position: relative;
    }
    .search-container i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }
    #search_query {
        width: 100%;
        height: 45px;
        padding-left: 45px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
        transition: all 0.3s;
    }
    #search_query:focus {
        background: white;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        outline: none;
    }
    .filter-group {
        display: flex;
        gap: 10px;
    }
    .filter-group select {
        height: 45px;
        padding: 0 15px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: white;
        font-size: 0.9rem;
        cursor: pointer;
    }

    /* Sidebar Chat AI */
    #aiChatStatus {
        font-size: 0.8rem;
        color: var(--primary);
        padding: 5px 15px;
        margin-bottom: 10px;
        border-radius: 20px;
        background: rgba(var(--primary-rgb), 0.1);
        display: none;
        width: fit-content;
    }
    
    /* Bouton flottant Chat AI */
    .floating-ai-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        border-radius: 30px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: none;
        outline: none;
    }
    .floating-ai-btn:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5);
    }
    .floating-ai-btn:active {
        transform: scale(0.9);
    }
    @media (max-width: 768px) {
        .floating-ai-btn {
            bottom: 20px;
            right: 20px;
            width: 55px;
            height: 55px;
        }
    }

    #aiStatusText {
        font-style: italic;
    }
    .ai-bubble {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px 18px 18px 4px;
        padding: 12px 16px;
        margin-bottom: 15px;
        max-width: 85%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .ai-chat-sidebar {
        position: fixed;
        right: -450px;
        top: 0;
        width: 420px;
        height: 100vh;
        background: #ffffff;
        box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        z-index: 2000;
        transition: right 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
    }
    .ai-chat-sidebar.active { right: 0; }
    
    /* Styles pour les badges de tags */
    .tag-badge {
        display: flex;
        align-items: center;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        border: 1px solid #e2e8f0;
        margin: 2px 0;
    }
    .tag-badge.tag-exclude {
        background: #fef2f2;
        color: #ef4444;
        border-color: #fee2e2;
    }
    .tag-badge .remove-tag {
        margin-left: 6px;
        cursor: pointer;
        opacity: 0.5;
    }
    .tag-badge .remove-tag:hover { opacity: 1; }
    
    .autocomplete-item {
        padding: 10px 15px;
        cursor: pointer;
        transition: background 0.2s;
        font-size: 0.9rem;
    }
    .autocomplete-item:hover { background: #f8fafc; color: var(--primary); }
    .autocomplete-item strong { color: var(--primary); }
    
    .ai-chat-header {
        padding: 25px 20px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        position: relative;
    }
    .ai-chat-header h5 { font-weight: 700; letter-spacing: -0.5px; }
    
    .ai-chat-body {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #fcfcfd;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .chat-message {
        padding: 15px 20px;
        border-radius: 18px;
        max-width: 90%;
        font-size: 1.2rem !important; /* Plus grand */
        line-height: 1.6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .chat-message.user {
        background: var(--primary);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
    }
    .chat-message.ai {
        background: white;
        color: var(--text-main);
        align-self: flex-start;
        border: 1px solid #f1f5f9;
        border-bottom-left-radius: 4px;
    }

    /* Styles pour les extraits de recherche */
    mark {
        background: rgba(99, 102, 241, 0.15);
        color: var(--primary-dark);
        font-weight: 600;
        padding: 0 2px;
        border-radius: 3px;
    }
    
    .file-match-contexts {
        margin-top: 8px;
        padding-left: 12px;
        border-left: 2px solid #e2e8f0;
        font-size: 0.8rem;
        line-height: 1.5;
        color: var(--text-muted);
    }
    
    .file-match-contexts .context-item {
        margin-bottom: 4px;
    }
    
    .file-match-contexts .context-item:last-child {
        margin-bottom: 0;
    }

    /* Footer & Input Alignment */
    .ai-chat-footer {
        padding: 20px;
        padding-bottom: env(safe-area-inset-bottom, 30px); /* Sécurité mobile */
        background: white;
        border-top: 1px solid #f1f5f9;
    }
    @media (max-width: 768px) {
        .ai-chat-sidebar {
            width: 100%;
            right: -100%;
        }
        .ai-chat-footer {
            padding-bottom: 80px; /* Remonte le champ sur mobile pour éviter les barres du navigateur */
        }
    }
    .chat-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f1f5f9;
        padding: 6px 6px 6px 15px;
        border-radius: 25px;
        border: 2px solid transparent;
        transition: all 0.3s;
    }
    .chat-input-wrapper:focus-within {
        background: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
    }
    #aiChatInput {
        flex: 1;
        border: none;
        background: transparent;
        outline: none;
        height: 40px;
        font-size: 0.95rem;
    }
    #aiChatBtn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        border: none;
        cursor: pointer;
        transition: transform 0.2s;
    }
    #aiChatBtn:hover { transform: scale(1.05); background: var(--primary-dark); }
    #aiChatBtn.btn-danger { background: #ef4444; }

    /* Sources & Thinking */
    /* AI Overview (SGE Style) */
    .ai-overview-box {
        background: linear-gradient(to bottom right, #fcfdff, #f8f9ff);
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        display: none;
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.05);
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        display: none;
        animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .ai-overview-header {
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    .ai-overview-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .ai-overview-content {
        font-size: 2rem !important; /* GÉANT */
        line-height: 1.6;
        color: var(--text-main);
        margin-bottom: 25px;
        font-weight: 400;
    }
    .ai-thought-box {
        font-size: 1.2rem !important;
        color: #713f12;
        font-style: italic;
        background: #fefce8; /* Jaune post-it */
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px dashed #fef08a;
        border-left: 6px solid #facc15;
        position: relative;
    }
    .ai-thought-box::before {
        content: "💭 RÉFLEXION INTERNE DE L'IA";
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        margin-bottom: 10px;
        color: #a16207;
        letter-spacing: 0.05em;
    }
    .ai-overview-sources {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); /* Plus large */
        gap: 12px;
        padding-top: 15px;
        border-top: 1px dashed #e2e8f0;
    }
    .source-card {
        background: white;
        border: 1px solid #f1f5f9;
        padding: 15px;
        border-radius: 10px;
        font-size: 1.1rem !important; /* Plus grand */
        transition: all 0.2s;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .source-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    .source-card.is-top { border-left: 3px solid #28a745; }
    
    #aiContextArea { border-radius: 12px; margin: 0 20px 10px 20px; border: 1px solid #e2e8f0; }
    #aiThoughtArea { border-radius: 12px; margin: 0 20px 10px 20px; border: 1px solid #f1f5f9; background: #fafafa; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 font-weight-bold"><i class="fa fa-book-open text-primary"></i> <?php _e('header.library', [], false); ?></h2>
        <div class="text-muted"><?php _e('bibliotheque.available_docs_count', [], false); ?></div>
    </div>

    <!-- Toolbar Premium -->
    <div class="library-toolbar">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" id="search_query" placeholder="<?= htmlspecialchars(__('bibliotheque.search_placeholder')) ?>" onkeypress="if(event.key === 'Enter') { event.preventDefault(); triggerAiSearch(); }">
        </div>
        
        <div class="filter-group">
            <select id="filter_format">
                <option value=""><?php _e('bibliotheque.format', [], false); ?></option>
                <option value="A3">A3</option>
                <option value="A4">A4</option>
                <option value="A5">A5</option>
                <option value="A6">A6</option>
            </select>
            
            <select id="filter_color">
                <option value=""><?php _e('bibliotheque.color', [], false); ?></option>
                <option value="NB">N&B</option>
                <option value="Couleur"><?php _e('bibliotheque.color', [], false); ?></option>
            </select>

            <div class="position-relative" style="min-width: 250px;">
                <div id="tag_filter_wrapper" class="d-flex align-items-center flex-wrap" style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 2px 10px; min-height: 45px; background: #fff; cursor: text;" onclick="document.getElementById('tag_filter_input').focus()">
                    <div id="active_tags" class="d-flex flex-wrap" style="gap: 5px;"></div>
                    <input type="text" id="tag_filter_input" placeholder="<?php _e('bibliotheque.tags_placeholder', [], false); ?>" style="border: none; outline: none; flex: 1; min-width: 80px; padding: 8px 0; font-size: 0.9rem;" autocomplete="off">
                    <button class="btn btn-link p-0 text-muted ml-2" onclick="const v = $('#tag_filter_input').val().trim(); if(v) addTagFilter(v); return false;"><i class="fa fa-plus-circle"></i></button>
                </div>
                <div id="tag_autocomplete" class="shadow-lg border rounded" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background: white; max-height: 250px; overflow-y: auto; margin-top: 5px;"></div>
            </div>

            <?php if ($ai_enabled): ?>
            <select id="ai_model" class="ml-2" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 0 10px; height: 45px; background: #f8fafc; color: #475569; font-weight: 500;">
                <option value="fast">🚀 Mode Rapide (Luth)</option>
                <option value="pro">🧠 Mode Expert (Gemma)</option>
                <option value="nemotron">⚡ Nemotron</option>
            </select>
            <?php endif; ?>
            
            <button id="btnRescanLibrary" class="btn btn-outline-secondary ml-2" onclick="rescanLibrary('internal')" title="<?= __('bibliotheque.refresh_title') ?>" style="border-radius: 10px; height: 45px;">
                <i class="fa fa-refresh"></i>
            </button>
        </div>

        <?php if ($ai_enabled): ?>
        <button class="btn btn-primary px-4 shadow-sm" type="button" onclick="triggerAiSearch()" style="border-radius: 12px; height: 45px;">
            <i class="fa fa-magic mr-2"></i> <?php _e('bibliotheque.ai_assistant', [], false); ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Progress Bar Indexation -->
    <div id="indexProgress" class="progress mb-3 shadow-sm" style="height: 25px; border-radius: 12px; display: none;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; font-weight: bold; line-height: 25px;">0%</div>
    </div>

    <!-- Zone d'upload -->
    <div class="mb-3">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-toggle="collapse" data-target="#uploadZone" style="border-radius: 8px;">
            <i class="fa fa-cloud-upload"></i> <?php _e('bibliotheque.add_documents', [], false); ?>
        </button>
        <div class="collapse mt-2" id="uploadZone">
            <div id="dropZone" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.3s;"
                 onclick="document.getElementById('fileInput').click()"
                 ondragover="event.preventDefault(); this.style.borderColor='#6366f1'; this.style.background='#eef2ff';"
                 ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';"
                 ondrop="event.preventDefault(); this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc'; handleFiles(event.dataTransfer.files);">
                <i class="fa fa-cloud-upload" style="font-size: 2em; color: #94a3b8; margin-bottom: 10px;"></i><br>
                <span style="color: #64748b;"><?php _e('bibliotheque.drag_drop_pdf', [], false); ?></span>
                <input type="file" id="fileInput" class="d-none" accept=".pdf,.png" multiple
                       onchange="handleFiles(this.files)">
            </div>
            <div id="uploadProgress" class="mt-2"></div>
        </div>
    </div>

    <?php if ($ai_enabled): ?>
    <!-- Zone AI Overview (SGE) -->
    <div id="aiOverviewContainer" class="ai-overview-box">
        <div class="ai-overview-header d-flex justify-content-between align-items-center mb-2">
            <div class="ai-overview-title m-0">
                <i class="fa fa-magic"></i> <?php _e('bibliotheque.ai_overview', [], false); ?>
                <span id="aiOverviewLoading" style="display:none; font-size: 0.8rem; font-weight: normal; color: var(--text-muted);">
                    <i class="fa fa-circle-notch fa-spin"></i> <?php _e('bibliotheque.searching', [], false); ?>...
                </span>
                <span id="aiOverviewStatus" class="ml-2" style="font-size: 0.75rem; opacity: 0.8; font-weight: normal; font-style: italic;"></span>
            </div>
            <button class="btn btn-sm btn-link text-muted p-0" onclick="document.getElementById('aiOverviewContainer').style.display='none'"><i class="fa fa-times"></i></button>
        </div>
        <div id="aiOverviewThought" style="display:none; font-size: 0.85rem; color: #64748b; font-style: italic; margin-bottom: 10px; padding: 10px; background: #f8fafc; border-radius: 10px;"></div>
        <div id="aiOverviewContent" class="ai-overview-content"></div>
        <div id="aiOverviewSources" class="ai-overview-sources"></div>
    </div>
    <?php endif; ?>

    <!-- VUE DEBUG IA -->
    <?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
    <script>console.log("DUPLI: Debug View Rendered");</script>
    <div id="aiDebugView" class="debug-container mb-4 p-4 bg-white shadow-lg" style="border-radius: 20px; border-top: 5px solid #dc3545; position: sticky; top: 10px; z-index: 1050; max-height: 80vh; overflow-y: auto;">
        <h3 class="mb-4 d-flex justify-content-between align-items-center">
            <span><i class="fa fa-bug text-danger"></i><?php _e("auto_clean.bibliotheque_html_php_1", [], false); ?></span>
            <div>
                <button onclick="location.reload()" class="btn btn-sm btn-outline-info mr-2"><i class="fa fa-refresh"></i> <?php _e('common.refresh', [], false); ?></button>
                <a href="?bibliotheque" class="btn btn-sm btn-outline-secondary"><?php _e('bibliotheque.close_debug', [], false); ?></a>
            </div>
        </h3>
        
        <?php
        $historyFile = __DIR__ . '/../../logs/chat_history.json';
        $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
        if (empty($history)): ?>
            <p class="text-muted italic"><?php _e('bibliotheque.no_history', [], false); ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover border">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 150px;"><?php _e('auto_clean.bibliotheque_html_php_1', [], false); ?></th>
                            <th>Modèle / Temps</th>
                            <th>Question / Réponse</th>
                            <th>Détails Techniques</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($history, 0, 20) as $item): ?>
                        <tr>
                            <td class="small text-muted"><?= $item['timestamp'] ?></td>
                            <td>
                                <span class="badge badge-<?= $item['model'] === 'pro' ? 'primary' : 'info' ?>">
                                    <?= $item['model'] === 'pro' ? '🧠 EXPERT' : '🚀 RAPIDE' ?>
                                </span>
                                <br><small class="font-weight-bold"><?= $item['elapsed'] ?>s</small>
                            </td>
                            <td>
                                <div class="mb-3" style="font-size: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                    <strong><?php _e('bibliotheque.question_label', [], false); ?> :</strong> <span style="color: #1e293b;"><?= htmlspecialchars($item['question']) ?></span>
                                </div>
                                
                                <?php if (!empty($item['thought'])): ?>
                                <div class="ai-thought-box">
                                    <?= nl2br(htmlspecialchars($item['thought'])) ?>
                                </div>
                                <?php endif; ?>

                                <div style="font-size: 1.5rem !important; line-height: 1.5; background: #f0f7ff; padding: 20px; border-radius: 12px; border-left: 5px solid #007bff; color: #1e293b;">
                                    <strong><?php _e('bibliotheque.answer_label', [], false); ?> :</strong> <?= nl2br(htmlspecialchars($item['response'])) ?>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-xs btn-outline-info mb-1" onclick="$(this).next().toggle()"><?php _e('bibliotheque.see_full_prompt', [], false); ?></button>
                                <div style="display:none; font-size: 0.75rem; background: #1e293b; color: #94a3b8; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                                    <?= nl2br(htmlspecialchars($item['prompt'])) ?>
                                </div>
                                <br>
                                <?php if (!empty($item['thought'])): ?>
                                <button class="btn btn-xs btn-outline-warning mb-1" onclick="$(this).next().toggle()"><?php _e('bibliotheque.see_thought', [], false); ?></button>
                                <div style="display:none; font-size: 0.8rem; font-style: italic; color: #475569; background: #fffbeb; padding: 10px; border: 1px solid #fde68a; border-radius: 5px; margin-bottom: 10px;">
                                    <?= nl2br(htmlspecialchars($item['thought'])) ?>
                                </div>
                                <?php endif; ?>
                                <br>
                                <div style="font-size: 1rem; margin-top: 10px; background: white; padding: 15px; border-radius: 10px; border: 1px solid #cbd5e1; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                                    <div class="mb-2 font-weight-bold text-success"><i class="fa fa-database"></i> <?php _e('bibliotheque.used_sources', [], false); ?> :</div>
                                    <?php foreach ($item['sources'] as $idx => $src): 
                                        $uniqueId = "debug_src_" . md5($item['timestamp'] . $idx);
                                    ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <a href="javascript:void(0)" onclick="openPdfViewer(<?= $src['id'] ?? 0 ?>, '<?= addslashes($src['title']) ?>')" class="font-weight-bold text-primary" style="font-size: 1.2rem;">
                                                    <i class="fa fa-file-pdf-o"></i> <?= htmlspecialchars($src['title']) ?>
                                                </a>
                                            </div>
                                            <?php 
                                            $extracts = $src['contents'] ?? (!empty($src['content']) ? [$src['content']] : []);
                                            if (!empty($extracts)): ?>
                                                <div class="text-muted mb-1" style="font-size: 0.85rem; font-style: italic;"><?php _e('bibliotheque.text_extracts', [], false); ?> :</div>
                                                <?php foreach ($extracts as $cIdx => $content): ?>
                                                    <div id="<?= $uniqueId ?>_<?= $cIdx ?>" style="font-size: 1.1rem; color: #334155; background: #f1f5f9; padding: 12px; border-radius: 8px; border-left: 4px solid #6366f1; line-height: 1.5; margin-bottom: 8px;">
                                                        <?= nl2br(htmlspecialchars($content)) ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="small text-muted italic"><?php _e('bibliotheque.extract_unavailable', [], false); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Liste des fichiers (Tableau ou Grille) -->
    <div id="library_content">
        <!-- Rempli par AJAX -->
    </div>

    <?php if ($ai_enabled): ?>
    <button class="floating-ai-btn" id="ai-chat-btn" onclick="toggleAiChat()" title="Ouvrir l'Assistant Chat" style="position: relative;">
        <i class="fa fa-comments"></i>
        <span id="ai-chat-badge" class="badge badge-danger" style="position: absolute; top: -5px; right: -5px; display: none; border-radius: 50%; padding: 5px 8px; font-size: 0.8rem;"></span>
    </button>
    <?php endif; ?>

    <!-- Modal de Confirmation de Suppression -->
    <div class="modal fade" id="deleteFileModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
                <div class="modal-header bg-danger text-white border-0 py-3" style="border-radius: 20px 20px 0 0;">
                    <h5 class="modal-title font-weight-bold"><i class="fa fa-trash mr-2"></i> <?php _e('bibliotheque.delete_doc', [], false); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3 text-dark font-weight-bold" id="delete_filename_display"></p>
                    <p class="text-muted small"><?php _e('bibliotheque.delete_doc_desc', [], false); ?></p>
                    
                    <div class="custom-control custom-checkbox mt-4 p-3 bg-light rounded border">
                        <input type="checkbox" class="custom-control-input" id="delete_from_disk_check" checked>
                        <label class="custom-control-label font-weight-bold text-danger" for="delete_from_disk_check">
                            <?php _e('bibliotheque.delete_from_disk', [], false); ?>
                        </label>
                        <small class="d-block text-muted mt-1 ml-4"><?php _e('bibliotheque.delete_warning', [], false); ?></small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <input type="hidden" id="delete_file_id">
                    <button type="button" class="btn btn-light px-4" data-dismiss="modal" style="border-radius: 12px;"><?php _e('common.cancel', [], false); ?></button>
                    <button type="button" class="btn btn-danger px-4 shadow-sm" onclick="confirmDeleteFile()" style="border-radius: 12px;">
                        <i class="fa fa-trash mr-2"></i> <?php _e('bibliotheque.confirm_delete', [], false); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>



<script>
    function rescanLibrary(mode) {
        const btn = document.getElementById('btnRescanLibrary');
        if(btn) btn.disabled = true;
        
        fetch('?bibliotheque_maintenance', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'rescan', params: { mode: mode } })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success && data.job_id) {
                monitorIndexing(data.job_id);
            } else {
                alert('<?= __("bibliotheque.scan_error") ?>: ' + (data.error || '<?= __("common.unknown") ?>'));
                if(btn) btn.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            if(btn) btn.disabled = false;
        });
    }

    function checkActiveJob() {
        fetch('?get_indexing_status&job_id=latest')
            .then(res => res.json())
            .then(data => {
                if (data && (data.status === 'indexing' || data.status === 'scanning')) {
                    const btn = document.getElementById('btnRescanLibrary');
                    if(btn) btn.disabled = true;
                    monitorIndexing(data.job_id);
                }
            })
            .catch(e => console.error("Erreur checkActiveJob:", e));
    }

    function monitorIndexing(jobId) {
        const btn = document.getElementById('btnRescanLibrary');
        const progress = document.getElementById('indexProgress');
        const progressBar = progress ? progress.querySelector('.progress-bar') : null;
        
        if (progress) progress.style.display = 'flex';
        if (progressBar) {
            progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.remove('bg-success');
        }
        
        const pollInterval = setInterval(async () => {
            try {
                const statusRes = await fetch('?get_indexing_status&job_id=' + jobId);
                const statusData = await statusRes.json();
                
                if (statusData.percent && progressBar) {
                    progressBar.style.width = statusData.percent + '%';
                    progressBar.textContent = statusData.percent + '%';
                }
                
                if (statusData.status === 'scanning' && progressBar) {
                    progressBar.textContent = 'Scan... (' + (statusData.scanned_count || 0) + ')';
                }

                if (statusData.status === 'completed') {
                    clearInterval(pollInterval);
                    if (progressBar) {
                        progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                        progressBar.classList.add('bg-success');
                        progressBar.textContent = '<?= __("bibliotheque.completed") ?> !';
                    }
                    if(btn) btn.disabled = false;
                    setTimeout(() => { if (progress) progress.style.display = 'none'; }, 3000);
                    if (typeof loadLibrary === 'function') loadLibrary(1);
                } else if (statusData.status === 'error' || statusData.status === 'fatal_error') {
                    clearInterval(pollInterval);
                    if(btn) btn.disabled = false;
                    alert('<?= __("bibliotheque.scan_error") ?>: ' + (statusData.error_msg || '<?= __("common.unknown") ?>'));
                    if (progress) progress.style.display = 'none';
                } else if (statusData.status === 'none' || statusData.status === 'unknown') {
                    clearInterval(pollInterval);
                    if(btn) btn.disabled = false;
                    if (progress) progress.style.display = 'none';
                }
            } catch (e) {
                console.error("Erreur polling:", e);
            }
        }, 1000);
    }

    document.addEventListener('DOMContentLoaded', checkActiveJob);

    function toggleAiChat() {
        document.getElementById('aiChatSidebar').classList.toggle('active');
    }

    let currentAiMode = 'fast';
    let aiAbortController = null;
    let isAiGenerating = false;

    function setAiMode(mode) {
        if (isAiGenerating) return;
        currentAiMode = mode;
        document.getElementById('modeFastLabel').classList.toggle('active', mode === 'fast');
        document.getElementById('modeProLabel').classList.toggle('active', mode === 'pro');
    }

    function updateAiStatus(text, show = true) {
        const statusDiv = document.getElementById('aiChatStatus');
        const statusText = document.getElementById('aiStatusText');
        if (!statusDiv || !statusText) return;
        
        let icon = '<i class="fa fa-circle-notch fa-spin"></i> ';
        if (text.includes("Analyse")) icon = '<i class="fa fa-search fa-pulse"></i> ';
        if (text.includes("Sources")) icon = '<i class="fa fa-book"></i> ';
        if (text.includes("Lecture")) icon = '<i class="fa fa-glasses fa-fade"></i> ';
        if (text.includes("Rédaction")) icon = '<i class="fa fa-pen-nib fa-bounce"></i> ';
        if (text.includes("Connexion")) icon = '<i class="fa fa-wifi"></i> ';

        statusText.innerHTML = icon + text;
        statusDiv.style.display = show ? 'block' : 'none';
    }

    function updateAiChatBtn(generating = false) {
        const btn = document.getElementById('aiChatBtn');
        const icon = document.getElementById('aiChatIcon');
        isAiGenerating = generating;
        
        if (generating) {
            btn.classList.add('btn-danger');
            icon.className = 'fa fa-stop';
        } else {
            btn.classList.remove('btn-danger');
            icon.className = 'fa fa-paper-plane';
        }
    }

    async function sendAiMessage() {
        const input = document.getElementById('aiChatInput');
        if (isAiGenerating) {
            if (aiAbortController) aiAbortController.abort();
            return;
        }

        const question = input.value.trim();
        if (!question) return;

        addChatMessage('user', question);
        input.value = '';
        
        const contextArea = document.getElementById('aiContextArea');
        const contextDetails = document.getElementById('aiContextDetails');
        const thoughtArea = document.getElementById('aiThoughtArea');
        const thoughtContent = document.getElementById('aiThoughtContent');
        
        contextArea.style.display = 'none';
        contextDetails.innerHTML = '';
        thoughtArea.style.display = 'none';
        thoughtContent.innerHTML = '';
        
        updateAiStatus("Recherche...");
        updateAiChatBtn(true);
        
        aiAbortController = new AbortController();

        try {
            const response = await fetch('?chat_rag', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    question: question, 
                    mode: currentAiMode,
                    tags: activeTags.join(','),
                    selected_files: selectedPdfIds
                }),
                signal: aiAbortController.signal
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let aiMsgDiv = null;
            let fullContent = "";
            let isThinking = true;
            let streamBuffer = "";

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                
                streamBuffer += decoder.decode(value, { stream: true });
                const lines = streamBuffer.split("\n");
                streamBuffer = lines.pop();
                
                for (const line of lines) {
                    if (line.trim().startsWith("data: ")) {
                        try {
                            const data = JSON.parse(line.trim().substring(6));
                            if (data.type === 'status') {
                                updateAiStatus(data.message);
                                if (data.sources && data.sources.length > 0) {
                                    const contextArea = document.getElementById('aiContextArea');
                                    const contextDetails = document.getElementById('aiContextDetails');
                                    contextArea.style.display = 'block';
                                    contextDetails.style.display = 'block'; // Afficher par défaut
                                    contextDetails.innerHTML = ''; // Nettoyer avant
                                    data.sources.forEach((src, idx) => {
                                        const sourceId = `src_${Date.now()}_${idx}`;
                                        const topBadge = src.is_top ? '<span class="badge badge-success mr-2" style="font-size:0.6rem; vertical-align:middle;">TOP</span>' : '';
                                        const scoreInfo = src.score ? `<small class="text-muted ml-2">(Score: ${src.score})</small>` : '';
                                        const borderStyle = src.is_top ? 'border-left: 3px solid #28a745 !important;' : 'border-left: 3px solid #ddd !important; opacity: 0.8;';

                                        contextDetails.innerHTML += `
                                            <div class="mb-2 p-2 border rounded bg-white shadow-sm" style="font-size: 0.85rem; ${borderStyle}">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        ${topBadge}
                                                        <strong onclick="openPdfViewer(${src.id}, '${src.title.replace(/'/g, "\\'")}')" style="cursor:pointer; color:var(--primary)">${src.title}</strong>
                                                        ${scoreInfo}
                                                    </div>
                                                    <button class="btn btn-xs btn-link p-0 text-muted" onclick="$('#${sourceId}').toggle()"><?php _e('bibliotheque.show_excerpt', [], false); ?></button>
                                                </div>
                                                <div id="${sourceId}" style="display:none; margin-top:5px; color:#64748b; border-top:1px dashed #eee; padding-top:5px; font-size: 0.8rem;">
                                                    ${src.content.replace(/\n/g, '<br>')}
                                                </div>
                                            </div>`;
                                    });
                                }
                            }
                            if (data.type === 'content') {
                                let text = data.content;
                                if (isThinking && currentAiMode === 'pro') {
                                    thoughtArea.style.display = 'block';
                                    let endTag = null;
                                    if (text.includes("</think>")) endTag = "</think>";
                                    else if (text.includes("<channel|>")) endTag = "<channel|>";

                                    if (endTag) {
                                        const parts = text.split(endTag);
                                        thoughtContent.innerHTML += parts[0].replace(/<\|channel>|thought/g, '').replace(/\n/g, '<br>');
                                        isThinking = false;
                                        updateAiStatus("Rédaction...");
                                        text = parts[1] || "";
                                        if (text.trim() === "") continue;
                                    } else {
                                        thoughtContent.innerHTML += text.replace(/<\|channel>|thought/g, '').replace(/\n/g, '<br>');
                                        continue;
                                    }
                                }
                                
                                if (!aiMsgDiv) aiMsgDiv = addChatMessage('ai', '');
                                fullContent += text;
                                aiMsgDiv.innerHTML = fullContent.replace(/\n/g, '<br>');
                                document.getElementById('aiChatBody').scrollTop = document.getElementById('aiChatBody').scrollHeight;
                            }
                            if (data.type === 'done') {
                                updateAiStatus("Terminé", false);
                                updateAiChatBtn(false);
                            }
                            if (data.type === 'error') {
                                addChatMessage('ai', 'Erreur : ' + data.message);
                                updateAiChatBtn(false);
                            }
                        } catch (e) {}
                    }
                }
            }
        } catch (err) {
            updateAiChatBtn(false);
            updateAiStatus("", false);
        }
    }

    function addChatMessage(role, text) {
        const body = document.getElementById('aiChatBody');
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-message ${role}`;
        msgDiv.innerHTML = text;
        body.appendChild(msgDiv);
        body.scrollTop = body.scrollHeight;
        return msgDiv;
    }

    // --- LOGIQUE FILTRES ---
    let searchTimeout = null;
    let currentSort = 'created_at';
    let currentOrder = 'DESC';
    let activeTags = [];
    let allTagsList = [];
    let selectedPdfIds = [];

    function toggleAllPdfs(checkbox) {
        const isChecked = $(checkbox).prop('checked');
        $('.pdf-select-cb').prop('checked', isChecked);
        updatePdfSelection();
    }

    function updatePdfSelection() {
        selectedPdfIds = [];
        $('.pdf-select-cb:checked').each(function() {
            selectedPdfIds.push($(this).val());
        });
        
        const count = selectedPdfIds.length;
        if (count > 0) {
            $('#ai-chat-badge').text(count).show();
            $('#ai-selection-info').html(`<i class="fa fa-filter"></i> Filtre actif : ${count} document${count > 1 ? 's' : ''} sélectionné${count > 1 ? 's' : ''}`).show();
        } else {
            $('#ai-chat-badge').hide();
            $('#ai-selection-info').hide();
        }
    }

    function restorePdfSelection() {
        if (selectedPdfIds.length === 0) return;
        $('.pdf-select-cb').each(function() {
            if (selectedPdfIds.includes($(this).val())) {
                $(this).prop('checked', true);
            }
        });
        
        // Mettre à jour la case "Tout sélectionner" si toutes les cases affichées sont cochées
        const total = $('.pdf-select-cb').length;
        const checked = $('.pdf-select-cb:checked').length;
        if (total > 0 && total === checked) {
            $('#selectAllPdfs').prop('checked', true);
        }
    }

    $(document).ready(function() {
        loadLibrary();
        
        $('#search_query').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadLibrary(1), 300);
        });

        $('#filter_format, #filter_color').on('change', function() {
            loadLibrary(1);
        });

        // Autocomplete Tags
        $('#tag_filter_input').on('input', function() {
            const val = $(this).val().toLowerCase();
            
            // Si l'utilisateur tape une virgule ou un espace à la fin, on valide le tag
            if (val.length > 1 && (val.endsWith(',') || val.endsWith(' '))) {
                const tag = val.substring(0, val.length - 1).trim();
                if (tag) addTagFilter(tag);
                return;
            }

            const isExclude = val.startsWith('-');
            const searchVal = isExclude ? val.substring(1) : val;
            
            if (searchVal.length < 1) {
                $('#tag_autocomplete').hide();
                return;
            }

            // On force la conversion en String pour éviter le crash sur les tags numériques
            const matches = allTagsList.filter(t => String(t).toLowerCase().includes(searchVal));
            if (matches.length > 0) {
                let html = '';
                matches.forEach(m => {
                    const sM = String(m);
                    const display = isExclude ? `Exclure : <strong>${sM}</strong>` : `Inclure : <strong>${sM}</strong>`;
                    const tagVal = isExclude ? `-${sM}` : sM;
                    html += `<div class="autocomplete-item" onclick="addTagFilter('${tagVal.replace(/'/g, "\\'")}')">${display}</div>`;
                });
                $('#tag_autocomplete').html(html).show();
            } else {
                $('#tag_autocomplete').hide();
            }
        });

        // Touche Entrée et gestion du focus
        $('#tag_filter_input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                const val = $(this).val().trim();
                if (val) {
                    addTagFilter(val);
                }
                return false;
            }
            // Retour arrière pour supprimer le dernier tag si le champ est vide
            if (e.key === 'Backspace' && $(this).val() === '' && activeTags.length > 0) {
                removeTagFilter(activeTags[activeTags.length - 1]);
            }
        });

        // Hide autocomplete on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.position-relative').length) {
                $('#tag_autocomplete').hide();
            }
        });

        loadTags();
    });

    function addTagFilter(tag) {
        if (!activeTags.includes(tag)) {
            activeTags.push(tag);
            updateTagUI();
            $('#tag_filter_input').val('');
            $('#tag_autocomplete').hide();
            loadLibrary(1);
        }
    }

    function removeTagFilter(tag) {
        activeTags = activeTags.filter(t => t !== tag);
        updateTagUI();
        loadLibrary(1);
    }

    function updateTagUI() {
        const container = $('#active_tags');
        container.empty();
        activeTags.forEach(tag => {
            const isExclude = tag.startsWith('-');
            const tagName = isExclude ? tag.substring(1) : tag;
            const badge = $(`
                <div class="tag-badge ${isExclude ? 'tag-exclude' : ''}">
                    ${isExclude ? '<i class="fa fa-minus-circle mr-1"></i>' : ''}
                    ${tagName}
                    <span class="remove-tag" onclick="removeTagFilter('${tag}')">&times;</span>
                </div>
            `);
            container.append(badge);
        });
    }

    function loadLibrary(page = 1, sort = null, order = null) {
        if (sort) currentSort = sort;
        if (order) currentOrder = order;

        const query = $('#search_query').val();
        const format = $('#filter_format').val();
        const color = $('#filter_color').val();
        const tag = activeTags.join(',');

        console.log("--- loadLibrary ---");
        console.log("Params:", { query, format, color, tag, page, sort_by: currentSort, sort_order: currentOrder });

        $('#library_content').css('opacity', '0.5');

        $.ajax({
            url: '?bibliotheque_list',
            method: 'GET',
            data: { 
                query, 
                format, 
                color, 
                tag, 
                page, 
                sort_by: currentSort, 
                sort_order: currentOrder 
            },
            success: function(html) {
                console.log("Success: HTML reçu (" + html.length + " caractères)");
                $('#library_content').html(html).css('opacity', '1');
                restorePdfSelection();
                if (page > 1) {
                    $('html, body').animate({ scrollTop: $('#library_content').offset().top - 100 }, 200);
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX:", status, error);
                console.log("Response text:", xhr.responseText);
                $('#library_content').html('<div class="alert alert-danger"><?php _e("auto_clean.bibliotheque_html_php_11", [], false); ?></div>').css('opacity', '1');
            }
        });
    }

    function filterByTag(tag) {
        addTagFilter(tag);
    }

    function loadTags() {
        $.getJSON('?get_bibliotheque_tags', function(tags) {
            allTagsList = tags;
        });
    }

    async function openLibraryFile(id) {
        if (window.electronAPI && window.electronAPI.openFile) {
            try {
                const res = await fetch('?get_bibliotheque_file_info&id=' + id);
                const data = await res.json();
                if (data.success && data.file && data.file.filepath) {
                    window.electronAPI.openFile(data.file.filepath);
                }
            } catch (e) {
                console.error('Erreur ouverture fichier:', e);
            }
        } else {
            window.open('?get_bibliotheque_file&id=' + id, '_blank');
        }
    }

    function openDeleteModal(id, filename) {
        $('#delete_file_id').val(id);
        $('#delete_filename_display').text(filename);
        $('#delete_from_disk_check').prop('checked', true);
        $('#deleteFileModal').modal('show');
    }

    function confirmDeleteFile() {
        const id = $('#delete_file_id').val();
        const fromDisk = $('#delete_from_disk_check').is(':checked');
        
        const btn = $('#deleteFileModal .btn-danger');
        const oldHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-circle-notch fa-spin"></i> <?= __("bibliotheque.deleting") ?>...');
        
        $.ajax({
            url: '?delete_bibliotheque_file',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: id, delete_from_disk: fromDisk }),
            success: function(response) {
                $('#deleteFileModal').modal('hide');
                loadLibrary();
            },
            error: function(xhr) {
                alert("<?= __("bibliotheque.delete_error") ?>: " + (xhr.responseJSON?.error || "<?= __("common.unknown") ?>"));
            },
            complete: function() {
                btn.prop('disabled', false).html(oldHtml);
            }
        });
    }

    function editFile(id) {
        $.ajax({
            url: '?get_bibliotheque_file_info',
            method: 'GET',
            data: { id: id },
            success: function(response) {
                if (response.success) {
                    const file = response.file;
                    $('#edit_file_id').val(file.id);
                    $('#edit_filename').val(file.filename);
                    $('#edit_page_count').val(file.page_count);
                    $('#edit_tags').val(file.tags || '');
                    
                    // Nouveaux champs
                    const meta = file.metadata || {};
                    $('#edit_is_color').val(meta.is_color ? '1' : '0');
                    $('#edit_imposition').val(meta.imposition || 'ppp');
                    
                    $('#editFileModal').modal('show');
                } else {
                    alert('<?= __("common.error") ?>: ' + response.error);
                }
            },
            error: function() {
                alert('<?= __("bibliotheque.file_info_error") ?>');
            }
        });
    }

    function saveMetadata() {
        const id = $('#edit_file_id').val();
        const data = {
            id: id,
            filename: $('#edit_filename').val(),
            page_count: $('#edit_page_count').val(),
            tags: $('#edit_tags').val(),
            is_color: $('#edit_is_color').val() === '1',
            imposition: $('#edit_imposition').val()
        };

        const btn = $('#btnSaveMetadata');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?= __("bibliotheque.saving") ?>...');

        $.ajax({
            url: '?update_bibliotheque_metadata',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    $('#editFileModal').modal('hide');
                    loadLibrary(1); // Recharger la liste
                } else {
                    alert('<?= __("common.error") ?>: ' + response.message);
                }
            },
            error: function(xhr) {
                alert('<?= __("bibliotheque.save_error") ?>: ' + (xhr.responseJSON ? xhr.responseJSON.error : '<?= __("common.unknown") ?>'));
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    }

    // --- UPLOAD DE FICHIERS ---
    function handleFiles(files) {
        Array.from(files).forEach(file => uploadFile(file));
    }

    function uploadFile(file) {
        const progress = document.getElementById('uploadProgress');
        const id = 'up_' + Date.now();
        progress.insertAdjacentHTML('beforeend',
            `<div id="${id}" class="alert alert-info py-1 px-2 mt-1" style="font-size:0.85rem;">
                <i class="fa fa-spinner fa-spin"></i> <strong>${file.name}</strong> — <?= __("bibliotheque.uploading") ?>...
            </div>`
        );
        const formData = new FormData();
        formData.append('file', file);

        fetch('?upload_bibliotheque', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById(id);
                if (data.success) {
                    el.className = 'alert alert-success py-1 px-2 mt-1';
                    el.innerHTML = '<i class="fa fa-check"></i> <strong>' + file.name + '</strong> — <?= __("bibliotheque.added") ?>.';
                    loadLibrary(1);
                } else {
                    el.className = 'alert alert-danger py-1 px-2 mt-1';
                    el.innerHTML = '<i class="fa fa-times"></i> <strong>' + file.name + '</strong> — <?= __("common.error") ?>: ' + (data.error || '<?= __("common.unknown") ?>');
                }
                setTimeout(() => el.remove(), 5000);
            })
            .catch(err => {
                const el = document.getElementById(id);
                el.className = 'alert alert-danger py-1 px-2 mt-1';
                el.innerHTML = '<i class="fa fa-times"></i> <strong>' + file.name + '</strong> — <?= __("bibliotheque.network_error") ?>.';
            });
    }

    async function triggerAiSearch(query) {
        if (!query) {
            query = document.getElementById('search_query').value;
        }
        if (!query) return;

        // On lance AUSSI la recherche normale dans la liste en bas
        loadLibrary(1);

        if (query.length < 3) return;
        
        const modelSelect = document.getElementById('ai_model');
        const container = document.getElementById('aiOverviewContainer');
        
        // Si l'IA n'est pas activée, ces éléments n'existent pas
        if (!modelSelect || !container) return;

        const model = modelSelect.value;
        container.style.display = 'block';
        container.innerHTML = `
            <div class="ai-overview-header d-flex justify-content-between align-items-center">
                <div class="ai-overview-title"><i class="fa fa-magic"></i><?php _e("auto_clean.bibliotheque_html_php_12", [], false); ?></div>
                <div class="d-flex align-items-center">
                    <span id="aiOverviewStatus" style="font-size: 0.8rem; color: #64748b; margin-right: 15px;"></span>
                    <button id="aiOverviewStopBtn" class="btn btn-sm btn-outline-danger mr-3" onclick="if(aiAbortController) { aiAbortController.abort(); this.innerHTML='<i class=\\\'fa fa-ban\\\'></i> Arrêté'; this.classList.replace('btn-outline-danger', 'btn-secondary'); this.disabled=true; const loader = document.getElementById('aiOverviewLoading'); if(loader) loader.style.display='none'; }">
                        <i class="fa fa-stop"></i> Stop
                    </button>
                    <button class="btn btn-sm btn-link text-muted p-0" onclick="if(aiAbortController) aiAbortController.abort(); document.getElementById('aiOverviewContainer').style.display='none'"><i class="fa fa-times" style="font-size: 1.2rem;"></i></button>
                </div>
            </div>
            <div class="row">
                <div class="col-md-7">
                    <div class="mb-3" style="font-size: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                        <strong><?php _e('bibliotheque.question_label', [], false); ?> :</strong> <span style="color: #1e293b;">${query}</span>
                    </div>
                    <div id="aiOverviewThought" class="ai-thought-box" style="display:none;"></div>
                    <div id="aiOverviewResponse" style="font-size: 1.6rem !important; line-height: 1.5; background: #f0f7ff; padding: 20px; border-radius: 12px; border-left: 5px solid #007bff; color: #1e293b; display:none;">
                        <strong>RÉPONSE :</strong> <span id="aiStreamingContent"></span>
                    </div>
                </div>
                <div class="col-md-5">
                    <div id="aiOverviewSources" class="p-3 bg-white border rounded shadow-sm" style="max-height: 600px; overflow-y: auto;">
                        <div class="text-muted italic"><i class="fa fa-circle-notch fa-spin"></i><?php _e("auto_clean.bibliotheque_html_php_13", [], false); ?></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 text-right">
                <span id="aiOverviewLoading" class="spinner-border spinner-border-sm text-primary" role="status"></span>
            </div>
        `;

        if (aiAbortController) aiAbortController.abort();
        aiAbortController = new AbortController();

        try {
            const response = await fetch('?chat_rag', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    question: query, 
                    mode: model,
                    tags: activeTags.join(','),
                    selected_files: selectedPdfIds
                }),
                signal: aiAbortController.signal
            });

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullContent = "";
            let buffer = "";

            while (true) {
                const { done, value } = await reader.read();
                
                let chunk = "";
                if (value) {
                    chunk = decoder.decode(value, { stream: true });
                }
                
                const combined = buffer + chunk;
                const lines = combined.split("\n");
                buffer = lines.pop();
                
                for (const line of lines) {
                    if (line.trim().startsWith("data: ")) {
                        try {
                            const jsonStr = line.trim().substring(6);
                            if (!jsonStr) continue;
                            const data = JSON.parse(jsonStr);
                            
                            // On cherche les éléments à chaque itération s'ils ne sont pas encore là
                            const sDiv = document.getElementById('aiOverviewSources');
                            const stDiv = document.getElementById('aiOverviewStatus');
                            const tDiv = document.getElementById('aiOverviewThought');
                            const rDiv = document.getElementById('aiOverviewResponse');
                            const scSpan = document.getElementById('aiStreamingContent');

                            if (data.type === 'status') {
                                if (stDiv) stDiv.textContent = data.message;
                                if (data.sources && data.sources.length > 0 && sDiv) {
                                    let html = '<div class="mb-3 font-weight-bold text-success" style="font-size:1.1rem;"><i class="fa fa-database"></i> <?php _e('bibliotheque.identified_sources', [], false); ?> :</div>';
                                    data.sources.forEach((src) => {
                                        const extractsHtml = (src.contents && src.contents.length > 0) 
                                            ? src.contents.map(c => `<div class="p-2 mb-2 bg-light rounded shadow-sm" style="font-size:1.1rem; border-left:4px solid #6366f1; line-height:1.4;">${c}</div>`).join('')
                                            : `<div class="p-2 mb-1 bg-light rounded small italic text-muted" style="font-size:0.9rem;"><?php _e('bibliotheque.extract_unavailable', [], false); ?></div>`;

                                        html += `
                                            <div class="mb-4 pb-2 border-bottom">
                                                <a href="javascript:void(0)" onclick="openPdfViewer(${src.id}, '${src.title.replace(/'/g, "\\'")}')" class="font-weight-bold text-primary d-block mb-2" style="font-size: 1.2rem;">
                                                    <i class="fa fa-file-pdf-o"></i> ${src.title}
                                                </a>
                                                ${extractsHtml}
                                            </div>
                                        `;
                                    });
                                    sDiv.innerHTML = html;
                                }
                            }
                            
                            if (data.type === 'content') {
                                fullContent += data.content;
                                
                                let displayContent = fullContent;
                                if (fullContent.includes('<think>') || fullContent.includes('<channel|>') || fullContent.includes('<|channel>')) {
                                    let splitTag = fullContent.includes('</think>') ? '</think>' : (fullContent.includes('<channel|>') ? '<channel|>' : null);
                                    if (splitTag) {
                                        let parts = fullContent.split(splitTag);
                                        let thoughtText = parts[0].replace(/<think>|<\|channel>|thought/g, '').trim();
                                        if (tDiv) {
                                            tDiv.innerHTML = thoughtText.replace(/\n/g, '<br>');
                                            tDiv.style.display = 'block';
                                        }
                                        displayContent = parts[1].trim();
                                    } else {
                                        let thoughtText = fullContent.replace(/<think>|<\|channel>|thought/g, '').trim();
                                        if (tDiv) {
                                            tDiv.innerHTML = thoughtText.replace(/\n/g, '<br>');
                                            tDiv.style.display = 'block';
                                        }
                                        displayContent = "";
                                    }
                                }
                                
                                if (displayContent && scSpan) {
                                    if (rDiv) rDiv.style.display = 'block';
                                    scSpan.innerHTML = displayContent.replace(/\n/g, '<br>');
                                }
                            }
                            
                            if (data.type === 'done') {
                                const lSpan = document.getElementById('aiOverviewLoading');
                                if (lSpan) lSpan.style.display = 'none';
                            }
                            if (data.type === 'error') {
                                if (stDiv) stDiv.innerHTML = `<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> ${data.message}</span>`;
                                const lSpan = document.getElementById('aiOverviewLoading');
                                if (lSpan) lSpan.style.display = 'none';
                                break;
                            }
                        } catch (e) { console.error("Parse error", e, line); }
                    }
                }

                if (done) break;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                const scSpan = document.getElementById('aiStreamingContent');
                const lSpan = document.getElementById('aiOverviewLoading');
                if (scSpan) scSpan.innerHTML = '<span class="text-danger"><?php _e("auto_clean.bibliotheque_html_php_14", [], false); ?></span>';
                if (lSpan) lSpan.style.display = 'none';
            }
        }
    }

    function printLibraryFile(id) {
        const url = '?get_bibliotheque_file&id=' + id;
        
        // Créer une iframe invisible
        let iframe = document.getElementById('printIframe');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'printIframe';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);
        }
        
        iframe.src = url;
        iframe.onload = function() {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                console.error("Erreur lors de l'impression système:", e);
                // Si l'iframe échoue (ex: blocage navigateur), on ouvre dans un nouvel onglet
                window.open(url + '&print=1', '_blank');
            }
        };
    }
</script>
</div>

<!-- Sidebar de Chat IA -->
<div id="aiChatSidebar" class="ai-chat-sidebar">
    <div class="ai-chat-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1"><i class="fa fa-robot"></i><?php _e("auto_clean.bibliotheque_html_php_15", [], false); ?></h5>
            <div class="btn-group btn-group-toggle" style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 2px;">
                <label class="btn btn-xs text-white px-3 active" id="modeFastLabel" onclick="setAiMode('fast')" style="font-size: 0.7rem; border: none;"><?= __("bibliotheque.ai_mode_fast") ?></label>
                <label class="btn btn-xs text-white px-3" id="modeProLabel" onclick="setAiMode('pro')" style="font-size: 0.7rem; border: none;"><?= __("bibliotheque.ai_mode_expert") ?></label>
            </div>
            <div id="ai-selection-info" class="text-warning mt-1 font-weight-bold" style="font-size: 0.75rem; display: none;"></div>
        </div>
        <button type="button" class="btn text-white" onclick="toggleAiChat()" style="font-size: 1.5rem;">&times;</button>
    </div>
    
    <div class="ai-chat-body" id="aiChatBody">
        <div class="chat-message ai">
            <?= __("bibliotheque.chat_welcome") ?>
        </div>
    </div>

    <!-- Zone de Contexte/Sources -->
    <div id="aiContextArea" class="px-3 py-2 bg-light" style="display:none; font-size: 0.75rem;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <b><i class="fa fa-link"></i><?php _e("auto_clean.bibliotheque_html_php_16", [], false); ?></b>
            <button class="btn btn-xs btn-link p-0 text-primary" onclick="$('#aiContextDetails').toggle()"><?= __("bibliotheque.details") ?></button>
        </div>
        <div id="aiContextDetails" style="display:none; max-height: 120px; overflow-y: auto;"></div>
    </div>

    <!-- Zone de Réflexion -->
    <div id="aiThoughtArea" class="px-3 py-2" style="display:none; font-size: 0.8rem; color: #64748b; font-style: italic;">
        <div class="mb-1"><b><i class="fa fa-brain fa-pulse"></i> <?= __("bibliotheque.analyzing") ?>...</b></div>
        <div id="aiThoughtContent"></div>
    </div>

    <div id="aiChatStatus" class="px-3 py-1 text-center" style="font-size: 0.7rem; color: #94a3b8; display: none;">
        <i class="fa fa-circle-notch fa-spin"></i>         <span id="aiStatusText"><?= __("bibliotheque.in_progress") ?>...</span>
    </div>

    <div class="ai-chat-footer">
        <div class="chat-input-wrapper">
            <input type="text" id="aiChatInput" placeholder="<?= __('bibliotheque.chat_placeholder') ?>" onkeypress="if(event.key === 'Enter') sendAiMessage()">
            <button id="aiChatBtn" onclick="sendAiMessage()">
                <i id="aiChatIcon" class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<!-- Modal d'édition des métadonnées -->
<div class="modal fade" id="editFileModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg" style="border-radius: 15px; border: none;">
            <div class="modal-header bg-primary text-white" style="border-radius: 15px 15px 0 0;">
                <h5 class="modal-title"><i class="fa fa-edit"></i><?php _e("auto_clean.bibliotheque_html_php_17", [], false); ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_file_id">
                
                <div class="form-group mb-3">
                    <label class="font-weight-bold text-muted small uppercase"><?php _e("auto_clean.bibliotheque_html_php_18", [], false); ?></label>
                    <input type="text" id="edit_filename" class="form-control form-control-lg shadow-sm" style="border-radius: 10px; border: 1px solid #e2e8f0;">
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small uppercase"><?php _e('bibliotheque.pages_label', [], false); ?></label>
                            <input type="number" id="edit_page_count" class="form-control shadow-sm" style="border-radius: 10px; border: 1px solid #e2e8f0;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small uppercase"><?php _e("auto_clean.bibliotheque_html_php_19", [], false); ?></label>
                            <select id="edit_is_color" class="form-control shadow-sm" style="border-radius: 10px; border: 1px solid #e2e8f0;">
                                <option value="1"><?php _e("auto_clean.bibliotheque_html_php_20", [], false); ?></option>
                                <option value="0">Noir & Blanc</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-muted small uppercase">Imposition</label>
                            <select id="edit_imposition" class="form-control shadow-sm" style="border-radius: 10px; border: 1px solid #e2e8f0;">
                                <option value="ppp">Standard (PPP)</option>
                                <option value="brochure">Brochure</option>
                                <option value="livre">Livre / Carnet</option>
                                <option value="tracts">Tracts / Flyers</option>
                                <option value="imposed">Déjà Imposé</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="font-weight-bold text-muted small uppercase"><?php _e("auto_clean.bibliotheque_html_php_21", [], false); ?></label>
                    <textarea id="edit_tags" class="form-control shadow-sm" rows="3" style="border-radius: 10px; border: 1px solid #e2e8f0;"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light" style="border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-secondary px-4 shadow-sm" data-dismiss="modal" style="border-radius: 10px;"><?php _e('common.cancel', [], false); ?></button>
                <button type="button" id="btnSaveMetadata" onclick="saveMetadata()" class="btn btn-primary px-4 shadow-sm" style="border-radius: 10px;">
                    <i class="fa fa-check"></i> <?= __("bibliotheque.save") ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Visualisation PDF (PDF.js) -->
<div class="modal fade" id="pdfViewerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content border-0 shadow-lg" style="background: #2c3e50; border-radius: 15px; overflow: hidden;">
            <div class="modal-header border-0 text-white p-3 d-flex align-items-center justify-content-between" style="background: rgba(0,0,0,0.2);">
                <h5 class="modal-title m-0" id="pdfViewerTitle"><i class="fa fa-eye"></i> Visualisation</h5>
                <div class="d-flex align-items-center">
                    <div class="pagination-controls bg-dark rounded-pill px-3 py-1 mr-3 d-flex align-items-center" style="font-size: 0.9rem;">
                        <button id="prevPage" class="btn btn-link text-white p-0 mr-2" onclick="changePage(-1)"><i class="fa fa-chevron-left"></i></button>
                        <span class="text-white mx-1">Page <span id="currentPage">1</span> / <span id="totalPages">?</span></span>
                        <button id="nextPage" class="btn btn-link text-white p-0 ml-2" onclick="changePage(1)"><i class="fa fa-chevron-right"></i></button>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.8; outline: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 position-relative" style="height: 75vh; background: #525659; overflow-y: auto; text-align: center;">
                <div id="pdfLoading" class="position-absolute w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white" style="background: #525659; z-index: 10;">
                    <i class="fa fa-circle-notch fa-spin fa-3x mb-3"></i>
                    <p><?php _e("auto_clean.bibliotheque_html_php_22", [], false); ?></p>
                </div>
                <canvas id="pdfCanvas" class="shadow-lg my-4 mx-auto" style="max-width: 95%;"></canvas>
            </div>
            <div class="modal-footer border-0 p-3 d-flex justify-content-between align-items-center" id="pdfViewerFooter" style="background: rgba(0,0,0,0.2);">
                <div class="viewer-actions d-flex" id="pdfModalActions">
                    <!-- Les boutons seront injectés ici en JS pour garder l'ID courant -->
                </div>
                <button type="button" class="btn btn-outline-light px-4" data-dismiss="modal" style="border-radius: 8px;"><?php _e('common.close', [], false); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Inclusion de PDF.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    var pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    var pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        scale = 1.5,
        canvas = document.getElementById('pdfCanvas'),
        ctx = canvas.getContext('2d');

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(function(page) {
            var viewport = page.getViewport({scale: scale});
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            var renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            var renderTask = page.render(renderContext);

            renderTask.promise.then(function() {
                pageRendering = false;
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
                $('#pdfLoading').fadeOut();
            });
        });
        document.getElementById('currentPage').textContent = num;
    }

    function changePage(delta) {
        if (!pdfDoc) return;
        var newPage = pageNum + delta;
        if (newPage < 1 || newPage > pdfDoc.numPages) return;
        pageNum = newPage;
        renderPage(pageNum);
    }

    function openPdfViewer(id, filename) {
        $('#pdfViewerTitle').html('<i class="fa fa-eye"></i> ' + filename);
        $('#pdfLoading').show();
        $('#pdfCanvas').hide();
        $('#pdfViewerModal').modal('show');
        
        // Préparer les boutons d'action sous le PDF
        const actionsHtml = `
            <div class="btn-group">
                <button class="btn btn-primary" onclick="openLibraryFile(${id})"><i class="fa fa-external-link"></i> <?= __("bibliotheque.open") ?></button>
                <button class="btn btn-info" onclick="printLibraryFile(${id})"><i class="fa fa-print"></i> <?= __('common.print', [], false); ?></button>
                
                <button class="btn btn-warning" onclick="window.location.href='?studio&file_id=${id}'">
                    <i class="fa fa-magic"></i> <?= __("bibliotheque.edit_in_studio") ?>
                </button>
            </div>
        `;
        $('#pdfModalActions').html(actionsHtml);

        var url = '?get_bibliotheque_file&id=' + id;
        pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('totalPages').textContent = pdfDoc.numPages;
            pageNum = 1;
            $('#pdfCanvas').show();
            renderPage(pageNum);
        }).catch(err => {
            $('#pdfLoading').html('<i class="fa fa-exclamation-triangle fa-2x text-warning"></i><p class="mt-2"><?php _e("auto_clean.bibliotheque_html_php_23", [], false); ?></p>');
        });
    }
</script>
