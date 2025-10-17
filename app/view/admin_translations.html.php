<!-- CSS pour l'interface de traduction améliorée -->
<style>
.translation-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* En-tête avec statistiques */
.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.header-section h1 {
    margin: 0 0 20px 0;
    font-size: 2.5em;
    font-weight: 300;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.stat-card {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.stat-card h4 {
    margin: 0 0 10px 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.stat-percentage {
    font-size: 2.5em;
    font-weight: bold;
    margin-bottom: 5px;
}

/* Onglets de langue */
.language-tabs-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.language-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.language-tab {
    flex: 1;
    padding: 20px;
    text-align: center;
    background: #f8f9fa;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.1em;
    font-weight: 500;
    color: #6c757d;
    position: relative;
}

.language-tab:hover {
    background: #e9ecef;
    color: #495057;
}

.language-tab.active {
    background: white;
    color: #007bff;
    box-shadow: inset 0 -3px 0 #007bff;
}

.language-tab .stat-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 0.7em;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Contenu des onglets */
.language-content {
    display: none;
    padding: 30px;
}

.language-content.active {
    display: block;
}

/* Recherche */
.search-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.search-box {
    position: relative;
}

.search-box input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid #e9ecef;
    border-radius: 25px;
    font-size: 1.1em;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.search-box i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Accordéons par page */
.page-accordions {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    overflow: hidden;
}

.page-accordion {
    border-bottom: 1px solid #f1f3f4;
}

.page-accordion:last-child {
    border-bottom: none;
}

.page-accordion-header {
    background: #f8f9fa;
    padding: 20px 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 1.2em;
    font-weight: 600;
    color: #495057;
}

.page-accordion-header:hover {
    background: #e9ecef;
}

.page-accordion-header.active {
    background: #007bff;
    color: white;
}

.page-accordion-header .page-icon {
    margin-right: 15px;
    font-size: 1.3em;
}

.page-accordion-header .page-stats {
    font-size: 0.8em;
    opacity: 0.8;
    margin-left: 10px;
}

.page-accordion-content {
    display: none;
    padding: 0;
}

.page-accordion-content.active {
    display: block;
}

/* Traductions dans les accordéons */
.translation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 0;
}

.translation-item {
    padding: 20px 25px;
    border-bottom: 1px solid #f8f9fa;
    transition: all 0.3s ease;
}

.translation-item:hover {
    background: #f8f9fa;
}

.translation-item:last-child {
    border-bottom: none;
}

.translation-key {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 8px;
    word-break: break-all;
}

.translation-value {
    margin-bottom: 10px;
}

.translation-value input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1em;
    transition: all 0.3s ease;
    background: white;
}

.translation-value input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

.translation-value input:disabled {
    background: #f8f9fa;
    color: #6c757d;
}

.translation-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-save {
    padding: 8px 16px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9em;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-save:hover {
    background: #218838;
    transform: translateY(-1px);
}

.btn-save:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.btn-save.saving {
    background: #ffc107;
}

.btn-save.success {
    background: #28a745;
}

.btn-save.error {
    background: #dc3545;
}

/* Import/Export */
.import-export {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.import-export h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 1.3em;
}

.import-export .btn {
    margin-right: 10px;
    margin-bottom: 10px;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-info {
    background: #17a2b8;
    color: white;
    border: none;
}

.btn-success {
    background: #28a745;
    color: white;
    border: none;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Messages */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: none;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert .close {
    background: none;
    border: none;
    font-size: 1.5em;
    margin-left: auto;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 768px) {
    .translation-container {
        padding: 10px;
    }
    
    .header-section {
        padding: 20px;
    }
    
    .header-section h1 {
        font-size: 2em;
    }
    
    .language-tabs {
        flex-direction: column;
    }
    
    .language-content {
        padding: 20px;
    }
    
    .translation-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}

/* Animations */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-accordion-content.active {
    animation: slideDown 0.3s ease-out;
}

/* Indicateurs de progression */
.progress-bar {
    width: 100%;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 2px;
    transition: width 0.3s ease;
}
</style>

<div class="translation-container">
    <!-- En-tête avec statistiques -->
    <div class="header-section">
        <h1>
            <i class="fa fa-globe"></i> Gestion des Traductions
        </h1>
        <p style="margin: 0; opacity: 0.9; font-size: 1.1em;">
            Interface améliorée avec organisation par pages et langues
        </p>
        
        <div class="stats-grid">
            <?php foreach($available_languages as $lang): ?>
                <div class="stat-card">
                    <h4><?= $translation_stats[$lang]['name'] ?></h4>
                    <div class="stat-percentage"><?= $translation_stats[$lang]['percentage'] ?>%</div>
                    <small style="opacity: 0.8;">
                        <?= $translation_stats[$lang]['translated_keys'] ?> / <?= $translation_stats[$lang]['total_keys'] ?> traductions
                    </small>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $translation_stats[$lang]['percentage'] ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Messages de succès/erreur -->
    <?php if(isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible">
            <i class="fa fa-check"></i>
            <span><?= htmlspecialchars($success_message) ?></span>
            <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible">
            <i class="fa fa-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error_message) ?></span>
            <button type="button" class="close" onclick="this.parentElement.style.display='none'">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Import/Export -->
    <div class="import-export">
        <h4><i class="fa fa-download"></i> Import/Export des Traductions</h4>
        <p>Importez ou exportez les traductions au format CSV pour faciliter la traduction en masse.</p>
        
        <form method="post" style="display: inline-block;">
            <input type="hidden" name="action" value="export_csv">
            <input type="hidden" name="language" value="<?= $selected_language ?>">
            <button type="submit" class="btn btn-info">
                <i class="fa fa-download"></i> Exporter CSV
            </button>
        </form>
        
        <form method="post" enctype="multipart/form-data" style="display: inline-block;">
            <input type="hidden" name="action" value="import_csv">
            <input type="hidden" name="language" value="<?= $selected_language ?>">
            <input type="file" name="csv_file" accept=".csv" style="display: none;" id="csv-file" onchange="this.form.submit()">
            <button type="button" class="btn btn-success" onclick="document.getElementById('csv-file').click()">
                <i class="fa fa-upload"></i> Importer CSV
            </button>
        </form>
    </div>
    
    <!-- Onglets de langue -->
    <div class="language-tabs-container">
        <div class="language-tabs">
            <?php foreach($available_languages as $lang): ?>
                <button class="language-tab <?= $lang === $selected_language ? 'active' : '' ?>" 
                        data-language="<?= $lang ?>">
                    <span class="stat-badge"><?= $translation_stats[$lang]['percentage'] ?>%</span>
                    <?= $translation_stats[$lang]['name'] ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Contenu des onglets -->
        <?php foreach($available_languages as $lang): ?>
            <div class="language-content <?= $lang === $selected_language ? 'active' : '' ?>" 
                 id="content-<?= $lang ?>">
                
                <!-- Recherche pour cette langue -->
                <div class="search-section">
                    <div class="search-box">
                        <input type="text" 
                               id="search-<?= $lang ?>" 
                               placeholder="Rechercher dans les traductions <?= $translation_stats[$lang]['name'] ?>..." 
                               class="form-control">
                        <i class="fa fa-search"></i>
                    </div>
                </div>
                
                <!-- Accordéons par page -->
                <div class="page-accordions" id="accordions-<?= $lang ?>">
                    <?php 
                    $translations_by_lang = $translation_manager->getTranslations($lang);
                    $page_stats = $translation_manager->getPageStats($lang);
                    
                    foreach($page_stats as $page => $stats): 
                        $page_icon = $translation_manager->getPageIcon($page);
                        $page_name = $translation_manager->getPageName($page);
                    ?>
                        <div class="page-accordion">
                            <div class="page-accordion-header" onclick="toggleAccordion('<?= $lang ?>', '<?= $page ?>')">
                                <div>
                                    <i class="fa <?= $page_icon ?> page-icon"></i>
                                    <?= $page_name ?>
                                    <span class="page-stats">(<?= $stats['translated'] ?>/<?= $stats['total'] ?> - <?= $stats['percentage'] ?>%)</span>
                                </div>
                                <i class="fa fa-chevron-down accordion-arrow" id="arrow-<?= $lang ?>-<?= $page ?>"></i>
                            </div>
                            
                            <div class="page-accordion-content" id="content-<?= $lang ?>-<?= $page ?>">
                                <div class="translation-grid">
                                    <?php 
                                    $page_translations = $translation_manager->getPageTranslations($lang, $page);
                                    foreach($page_translations as $key => $value): 
                                    ?>
                                        <div class="translation-item" data-key="<?= htmlspecialchars($key) ?>">
                                            <div class="translation-key"><?= htmlspecialchars($key) ?></div>
                                            <div class="translation-value">
                                                <input type="text" 
                                                       value="<?= htmlspecialchars($value) ?>" 
                                                       data-key="<?= htmlspecialchars($key) ?>"
                                                       data-language="<?= $lang ?>"
                                                       class="translation-input">
                                            </div>
                                            <div class="translation-actions">
                                                <button type="button" 
                                                        class="btn-save" 
                                                        data-key="<?= htmlspecialchars($key) ?>"
                                                        data-language="<?= $lang ?>">
                                                    <i class="fa fa-save"></i> Sauver
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Gestion des onglets de langue
    $('.language-tab').on('click', function() {
        var language = $(this).data('language');
        
        // Changer l'URL sans recharger la page
        var url = new URL(window.location);
        url.searchParams.set('lang', language);
        window.history.pushState({}, '', url);
        
        // Mettre à jour l'affichage
        $('.language-tab').removeClass('active');
        $('.language-content').removeClass('active');
        
        $(this).addClass('active');
        $('#content-' + language).addClass('active');
    });
    
    // Recherche dans les traductions
    $('.search-box input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        var language = $(this).attr('id').replace('search-', '');
        
        $('.language-content[data-language="' + language + '"] .translation-item').each(function() {
            var key = $(this).find('.translation-key').text().toLowerCase();
            var value = $(this).find('.translation-value input').val().toLowerCase();
            
            if (key.includes(searchTerm) || value.includes(searchTerm)) {
                $(this).show();
                $(this).closest('.page-accordion-content').show();
                $(this).closest('.page-accordion').show();
            } else {
                $(this).hide();
            }
        });
        
        // Masquer les accordéons vides
        $('.page-accordion-content').each(function() {
            var visibleItems = $(this).find('.translation-item:visible').length;
            if (visibleItems === 0 && searchTerm !== '') {
                $(this).hide();
                $(this).closest('.page-accordion').hide();
            } else {
                $(this).closest('.page-accordion').show();
            }
        });
    });
    
    // Sauvegarde des traductions
    $('.btn-save').on('click', function() {
        var key = $(this).data('key');
        var language = $(this).data('language');
        var value = $(this).closest('.translation-item').find('.translation-input').val();
        var button = $(this);
        
        // Permettre la modification de toutes les langues
        
        // Animation de sauvegarde
        button.removeClass('btn-save success error').addClass('btn-save saving');
        button.html('<i class="fa fa-spinner fa-spin"></i> Sauvegarde...');
        button.prop('disabled', true);
        
        $.ajax({
            url: '?admin_translations',
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: {
                action: 'update_translation',
                language: language,
                key: key,
                value: value
            },
            success: function(response) {
                console.log('Sauvegarde réussie:', response);
                button.removeClass('saving').addClass('success');
                button.html('<i class="fa fa-check"></i> Sauvé');
                
                setTimeout(function() {
                    button.removeClass('success').addClass('btn-save');
                    button.html('<i class="fa fa-save"></i> Sauver');
                    button.prop('disabled', false);
                }, 2000);
            },
            error: function(xhr, status, error) {
                console.error('Erreur de sauvegarde:', xhr.responseText, status, error);
                button.removeClass('saving').addClass('error');
                button.html('<i class="fa fa-times"></i> Erreur');
                
                setTimeout(function() {
                    button.removeClass('error').addClass('btn-save');
                    button.html('<i class="fa fa-save"></i> Sauver');
                    button.prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // Sauvegarde automatique avec Ctrl+S
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            $('.translation-item:visible .btn-save:enabled').first().click();
        }
    });
});

// Fonction pour basculer les accordéons
function toggleAccordion(language, page) {
    var content = $('#content-' + language + '-' + page);
    var arrow = $('#arrow-' + language + '-' + page);
    var header = arrow.closest('.page-accordion-header');
    
    if (content.hasClass('active')) {
        content.removeClass('active');
        header.removeClass('active');
        arrow.removeClass('fa-chevron-up').addClass('fa-chevron-down');
    } else {
        // Fermer tous les autres accordéons de cette langue
        $('#accordions-' + language + ' .page-accordion-content.active').removeClass('active');
        $('#accordions-' + language + ' .page-accordion-header.active').removeClass('active');
        $('#accordions-' + language + ' .accordion-arrow').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        
        // Ouvrir celui-ci
        content.addClass('active');
        header.addClass('active');
        arrow.removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }
}
</script>