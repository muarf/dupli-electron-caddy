<!-- CSS pour l'interface de traduction -->
<style>
.translation-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.language-selector {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.language-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.language-tab {
    padding: 10px 20px;
    border: 2px solid #dee2e6;
    border-radius: 5px;
    background: white;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    color: #495057;
}

.language-tab:hover {
    border-color: #007bff;
    color: #007bff;
}

.language-tab.active {
    border-color: #007bff;
    background: #007bff;
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.stat-card h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.stat-percentage {
    font-size: 2em;
    font-weight: bold;
    color: #28a745;
}

.translation-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #dee2e6;
    font-weight: bold;
    color: #495057;
}

.translation-items {
    padding: 20px;
}

.translation-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.translation-item:last-child {
    border-bottom: none;
}

.translation-key {
    flex: 0 0 300px;
    font-family: monospace;
    font-size: 0.9em;
    color: #6c757d;
    margin-right: 20px;
}

.translation-value {
    flex: 1;
    margin-right: 10px;
}

.translation-value input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.9em;
}

.translation-actions {
    flex: 0 0 100px;
    text-align: right;
}

.btn-save {
    padding: 6px 12px;
    font-size: 0.8em;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-save:hover {
    background: #218838;
}

.import-export {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.import-export h4 {
    margin-top: 0;
    color: #495057;
}

.import-export .btn {
    margin-right: 10px;
    margin-bottom: 10px;
}

.search-box {
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 1em;
}

.no-translations {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 40px;
}
</style>

<div class="translation-container">
    <h1 class="text-center">
        <i class="fa fa-globe"></i> Gestion des Traductions
    </h1>
    <hr>
    
    <!-- Messages de succès/erreur -->
    <?php if(isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-check"></i> <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Sélecteur de langue -->
    <div class="language-selector">
        <h4><i class="fa fa-language"></i> Sélectionner une langue</h4>
        <div class="language-tabs">
            <?php foreach($available_languages as $lang): ?>
                <a href="?admin&translations&lang=<?= $lang ?>" 
                   class="language-tab <?= $lang === $selected_language ? 'active' : '' ?>">
                    <?= $translation_stats[$lang]['name'] ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <?php foreach($available_languages as $lang): ?>
                <div class="stat-card">
                    <h4><?= $translation_stats[$lang]['name'] ?></h4>
                    <div class="stat-percentage"><?= $translation_stats[$lang]['percentage'] ?>%</div>
                    <small class="text-muted">
                        <?= $translation_stats[$lang]['translated_keys'] ?> / <?= $translation_stats[$lang]['total_keys'] ?> traductions
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Import/Export -->
    <div class="import-export">
        <h4><i class="fa fa-download"></i> Import/Export</h4>
        <p>Importez ou exportez les traductions au format CSV pour faciliter la traduction.</p>
        
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
    
    <!-- Recherche -->
    <div class="search-box">
        <input type="text" id="search-translations" placeholder="Rechercher une traduction..." class="form-control">
    </div>
    
    <!-- Traductions -->
    <div class="translation-section">
        <div class="section-header">
            <i class="fa fa-edit"></i> Traductions - <?= $translation_stats[$selected_language]['name'] ?>
        </div>
        <div class="translation-items" id="translation-items">
            <?php if(empty($translation_keys)): ?>
                <div class="no-translations">
                    <i class="fa fa-info-circle"></i> Aucune traduction trouvée.
                </div>
            <?php else: ?>
                <?php 
                $currentSection = '';
                foreach($translation_keys as $key): 
                    $section = explode('.', $key)[0];
                    if($section !== $currentSection):
                        $currentSection = $section;
                        if($currentSection !== ''):
                ?>
                    </div>
                <?php endif; ?>
                <div class="translation-section">
                    <div class="section-header">
                        <i class="fa fa-folder"></i> <?= ucfirst($section) ?>
                    </div>
                    <div class="translation-items">
                <?php endif; ?>
                
                <div class="translation-item" data-key="<?= htmlspecialchars($key) ?>">
                    <div class="translation-key"><?= htmlspecialchars($key) ?></div>
                    <div class="translation-value">
                        <input type="text" 
                               value="<?= htmlspecialchars($translations[$key] ?? '') ?>" 
                               data-key="<?= htmlspecialchars($key) ?>"
                               class="translation-input">
                    </div>
                    <div class="translation-actions">
                        <button type="button" 
                                class="btn-save" 
                                data-key="<?= htmlspecialchars($key) ?>"
                                data-language="<?= $selected_language ?>">
                            <i class="fa fa-save"></i> Sauver
                        </button>
                    </div>
                </div>
                
                <?php endforeach; ?>
                <?php if($currentSection !== ''): ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Recherche
    $('#search-translations').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.translation-item').each(function() {
            var key = $(this).find('.translation-key').text().toLowerCase();
            var value = $(this).find('.translation-value input').val().toLowerCase();
            
            if (key.includes(searchTerm) || value.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Sauvegarde des traductions
    $('.btn-save').on('click', function() {
        var key = $(this).data('key');
        var language = $(this).data('language');
        var value = $(this).closest('.translation-item').find('.translation-input').val();
        
        var button = $(this);
        var originalText = button.html();
        
        button.html('<i class="fa fa-spinner fa-spin"></i> Sauvegarde...');
        button.prop('disabled', true);
        
        $.ajax({
            url: '?admin&translations',
            method: 'POST',
            data: {
                action: 'update_translation',
                language: language,
                key: key,
                value: value
            },
            success: function(response) {
                button.html('<i class="fa fa-check"></i> Sauvé');
                button.removeClass('btn-save').addClass('btn-success');
                
                setTimeout(function() {
                    button.html(originalText);
                    button.removeClass('btn-success').addClass('btn-save');
                    button.prop('disabled', false);
                }, 2000);
            },
            error: function() {
                button.html('<i class="fa fa-times"></i> Erreur');
                button.removeClass('btn-save').addClass('btn-danger');
                
                setTimeout(function() {
                    button.html(originalText);
                    button.removeClass('btn-danger').addClass('btn-save');
                    button.prop('disabled', false);
                }, 2000);
            }
        });
    });
    
    // Sauvegarde automatique avec Ctrl+S
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.keyCode === 83) {
            e.preventDefault();
            $('.translation-item:visible .btn-save').first().click();
        }
    });
});
</script>