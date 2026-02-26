<?php
// Messages de succès/erreur
if(isset($success_message)): ?>
    <div class="alert alert-success">
        <strong><?php _e('changement.success_title'); ?></strong> <?= htmlspecialchars($success_message) ?>
        <br><br>
        <a href="?accueil" class="btn btn-primary">
            <i class="fa fa-home"></i> <?php _e('changement.back_home'); ?>
        </a>
    </div>
<?php elseif(isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong><?php _e('changement.error_title'); ?>:</strong> <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<?php if(!isset($success_message)): ?>
<div class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="text-center">
                    <i class="fa fa-tint"></i> <?php _e('changement.title'); ?>
                </h1>
                <hr>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form class="form-horizontal" action="" method="post" id="changement-form">
                    <fieldset>
                        <legend><i class="fa fa-cog"></i> <?php _e('changement.change_info'); ?></legend>
                        
                        <!-- Sélection de la machine -->
                        <div class="form-group">
                            <label class="col-md-4 control-label" for="machine"><?php _e('changement.machine_label'); ?></label>
                            <div class="col-md-4">
                                <select name="machine" id="machine" class="form-control" required>
                                    <option value=""><?php _e('changement.select_machine_placeholder'); ?></option>
                                    
                                    <!-- Duplicopieurs -->
                                    <?php if(isset($duplicopieurs) && count($duplicopieurs) > 0): ?>
                                        <optgroup label= "<?php echo __('changement.duplicators'); ?>" >
                                            <?php foreach($duplicopieurs as $dup): ?>
                                                <option value="<?= htmlspecialchars($dup['name']) ?>"><?= htmlspecialchars($dup['name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    
                                    <!-- Photocopieurs -->
                                    <?php if(isset($photocopiers) && count($photocopiers) > 0): ?>
                                        <optgroup label= "<?php echo __('changement.photocopiers'); ?>" >
                                            <?php foreach($photocopiers as $photocop): ?>
                                                <option value="<?= htmlspecialchars($photocop) ?>"><?= htmlspecialchars($photocop) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Type de consommable -->
                        <div class="form-group">
                            <label class="col-md-4 control-label" for="type"><?php _e('changement.consumable_type_label'); ?></label>
                            <div class="col-md-4">
                                <select name="type" id="type" class="form-control" required>
                                    <option value=""><?php _e('changement.select_type_placeholder'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Nombre de passages -->
                        <div class="form-group">
                            <label class="col-md-4 control-label" for="nb_p"><?php _e('changement.passages_count_label'); ?></label>
                            <div class="col-md-4">
                                <input id="nb_p" name="nb_p" class="form-control input-md" required type="number" placeholder="Ex: 12345">
                                <span class="help-block"><?php _e('changement.passages_help'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Nombre de masters (pour duplicopieurs) -->
                        <div class="form-group" id="masters-group" style="display: none;">
                            <label class="col-md-4 control-label" for="nb_m"><?php _e('changement.masters_count_label'); ?></label>
                            <div class="col-md-4">
                                <input id="nb_m" name="nb_m" class="form-control input-md" type="number" placeholder="Ex: 67890">
                                <span class="help-block"><?php _e('changement.masters_help'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Sélection du tambour (pour duplicopieurs) -->
                        <div class="form-group" id="tambour-group" style="display: none;">
                            <label class="col-md-4 control-label" for="tambour"><?php _e('changement.drum_label'); ?></label>
                            <div class="col-md-4">
                                <select name="tambour" id="tambour" class="form-control">
                                    <option value=""><?php _e('changement.select_drum_placeholder'); ?></option>
                                </select>
                                <span class="help-block"><?php _e('changement.drum_help'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Bouton de soumission -->
                        <div class="form-group">
                            <div class="col-md-4 col-md-offset-4">
                                <button type="submit" class="btn btn-success btn-block btn-lg">
                                    <i class="fa fa-save"></i> <?php _e('changement.submit_change'); ?>
                                </button>
                            </div>
                        </div>
                    </fieldset>
                </form>
                
                <!-- Aide dynamique -->
                <div class="row">
                    <div class="col-md-12">
                        <div id="aide-container">
                            <div class="alert alert-info">
                                <h4><i class="fa fa-info-circle"></i> <?php _e('changement.instructions_title'); ?></h4>
                                <p><?php _e('changement.select_machine_to_see_instructions'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="row">
                    <div class="col-md-12 text-center">
                        <a href="?accueil" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> <?php _e('changement.back_home'); ?>
                        </a>
                        <a href="?stats" class="btn btn-info">
                            <i class="fa fa-bar-chart"></i> <?php _e('stats.title'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Variables globales
    var duplicopieurs_tambours = {};
    var duplicopieursNames = [];
    
    // Ajouter les données des duplicopieurs
    <?php if(isset($duplicopieurs) && count($duplicopieurs) > 0): ?>
        <?php foreach($duplicopieurs as $dup): ?>
            duplicopieurs_tambours['<?= htmlspecialchars($dup['name']) ?>'] = <?= json_encode($dup['tambours']) ?>;
            duplicopieursNames.push('<?= htmlspecialchars($dup['name']) ?>');
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Fonction pour mettre à jour les options de type selon la machine
    function updateTypeOptions(machine, selectElement) {
        $.get('?changement&ajax=get_machine_type&machine=' + encodeURIComponent(machine) + '&t=' + Date.now())
            .done(function(response) {
                if (response.success) {
                    var type = response.type;
                    var options = '';
                    
                    if (type === 'duplicopieur') {
                        // Duplicopieurs : master et encre
                        options = '<option value="master"><?php _ejs('changement.master'); ?></option>' +
                                 '<option value="encre"><?php _ejs('changement.ink'); ?></option>';
                    } else if (type === 'photocop_encre') {
                        // Photocopieurs à encre : 4 couleurs seulement
                        options = '<option value="noire"><?php _ejs('changement.black_ink'); ?></option>' +
                                 '<option value="bleue"><?php _ejs('changement.blue_ink'); ?></option>' +
                                 '<option value="rouge"><?php _ejs('changement.red_ink'); ?></option>' +
                                 '<option value="jaune"><?php _ejs('changement.yellow_ink'); ?></option>';
                    } else if (type === 'photocop_toner') {
                        // Photocopieurs à toner : 4 couleurs + dev + tambour
                        options = '<option value="noir"><?php _ejs('changement.black'); ?></option>' +
                                 '<option value="cyan"><?php _ejs('changement.cyan'); ?></option>' +
                                 '<option value="magenta"><?php _ejs('changement.magenta'); ?></option>' +
                                 '<option value="yellow"><?php _ejs('changement.yellow'); ?></option>' +
                                 '<option value="dev"><?php _ejs('changement.dev'); ?></option>' +
                                 '<option value="tambour"><?php _ejs('changement.drum'); ?></option>';
                    } else {
                        options = '<option value=""><?php _ejs('changement.machine_type_not_recognized'); ?></option>';
                    }
                    
                    selectElement.html('<option value=""><?php _ejs('changement.select_type'); ?></option>' + options);
                }
            })
            .fail(function() {
                selectElement.html('<option value=""><?php _ejs('changement.error_loading'); ?></option>');
            });
    }
    
    // Fonction pour gérer l'affichage du champ tambour selon le type
    function toggleTambourField(type, machine) {
        var tambourField = $('#tambour');
        var tambourLabel = $('#tambour').prev('label');
        var tambourGroup = $('#tambour-group');
        
        if (type === 'master') {
            // Pour les masters, pas besoin de tambour
            tambourGroup.hide();
            tambourField.prop('required', false);
        } else if (type === 'encre' || type === 'tambour') {
            // Pour l'encre et les tambours, on doit choisir le tambour
            tambourGroup.show();
            tambourField.prop('required', true);
            
            // Remplir les options de tambours
            if (duplicopieurs_tambours[machine]) {
                tambourField.html('<option value=""><?php _ejs('changement.select_drum'); ?></option>');
                $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                    tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
                });
            }
        } else {
            tambourGroup.hide();
            tambourField.prop('required', false);
        }
    }
    
    // Gestion du changement de machine
    $('#machine').change(function() {
        var machine = $(this).val();
        var typeSelect = $('#type');
        var mastersGroup = $('#masters-group');
        var tambourGroup = $('#tambour-group');
        var tambourSelect = $('#tambour');
        
        // Vider les options
        typeSelect.html('<option value=""><?php _ejs('changement.select_type'); ?></option>');
        tambourSelect.html('<option value=""><?php _ejs('changement.select_drum'); ?></option>');
        
        if (machine) {
            // Utiliser la nouvelle fonction pour mettre à jour les types
            updateTypeOptions(machine, typeSelect);
            
            // Afficher/masquer les champs selon le type de machine
            if (duplicopieursNames.indexOf(machine) !== -1) {
                mastersGroup.show();
                tambourGroup.show();
                
                // Remplir les options de tambours
                if (duplicopieurs_tambours[machine]) {
                    tambourSelect.html('<option value=""><?php _ejs('changement.select_drum'); ?></option>');
                    $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                        tambourSelect.append('<option value="' + tambour + '">' + tambour + '</option>');
                    });
                }
                
                // Le champ masters sera rendu obligatoire seulement si le type "master" est sélectionné
                $('#nb_m').prop('required', false);
                
                // Récupérer les derniers compteurs pour les duplicopieurs
                $.get('?changement&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
                    .done(function(response) {
                        if (response.success) {
                            $('#nb_p').val(response.counters.passage_av);
                            $('#nb_m').val(response.counters.master_av);
                        }
                    })
                    .fail(function() {
                        console.log('Erreur lors du chargement des compteurs');
                    });
            } else {
                // C'est un photocopieur - cacher le champ masters
                mastersGroup.hide();
                tambourGroup.hide();
                $('#nb_m').prop('required', false);
                
                // Pour les photocopieurs, récupérer les compteurs depuis la table cons
                $.get('?changement&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
                    .done(function(response) {
                        if (response.success) {
                            $('#nb_p').val(response.counters.passage_av);
                            $('#nb_m').val(response.counters.master_av);
                        }
                    })
                    .fail(function() {
                        console.log('Erreur lors du chargement des compteurs');
                    });
            }
        } else {
            mastersGroup.hide();
            tambourGroup.hide();
            $('#nb_m').prop('required', false);
        }
        
        // Mettre à jour l'aide pour la machine sélectionnée
        updateAide(machine);
    });
    
    // Event listener pour le changement de type
    $('#type').change(function() {
        var type = $(this).val();
        var machine = $('#machine').val();
        toggleTambourField(type, machine);
        
        // Gestion du champ masters pour les duplicopieurs
        if (duplicopieursNames.indexOf(machine) !== -1) {
            // Toujours afficher le champ masters pour les duplicopieurs
            $('#masters-group').show();
            
            if (type === 'master') {
                $('#nb_m').prop('required', true);
            } else {
                // Pour tous les autres types (tambour, encre), le champ masters est optionnel
                $('#nb_m').prop('required', false);
            }
            
            // Récupérer la dernière valeur de masters
            $.get('models/changement.php?ajax=get_last_counters&machine=' + encodeURIComponent(machine), function(data) {
                if (data.success && data.counters && data.counters.master_av !== undefined) {
                    $('#nb_m').val(data.counters.master_av);
                }
            }).fail(function() {
                console.log('Erreur lors de la récupération des compteurs');
            });
        } else {
            // Pour les photocopieurs, cacher le champ masters
            $('#nb_m').prop('required', false);
            $('#masters-group').hide();
        }
    });
    
    
    // Validation du formulaire
    $('#changement-form').submit(function(e) {
        var machine = $('#machine').val();
        var type = $('#type').val();
        var nb_p = $('#nb_p').val();
        
        if (!machine || !type || !nb_p) {
            e.preventDefault();
            if (window.showAppModal) {
                window.showAppModal( "<?php echo __js('changement.fill_all_required'); ?>" );
            } else {
                alert( "<?php echo __js('changement.fill_all_required'); ?>" );
            }
            return false;
        }
        
        // Validation pour les duplicopieurs
        if (duplicopieursNames.indexOf(machine) !== -1) {
            if (type === 'master' && !$('#nb_m').val()) {
                e.preventDefault();
                if (window.showAppModal) {
                    window.showAppModal( "<?php echo __js('changement.enter_master_count'); ?>" );
                } else {
                    alert( "<?php echo __js('changement.enter_master_count'); ?>" );
                }
                return false;
            }
            if (type === 'tambour' && !$('#tambour').val()) {
                e.preventDefault();
                if (window.showAppModal) {
                    window.showAppModal( "<?php echo __js('changement.select_drum_for_ink'); ?>" );
                } else {
                    alert( "<?php echo __js('changement.select_drum_for_ink'); ?>" );
                }
                return false;
            }
        }
    });
    
    // Gestion de l'aide dynamique
    var aides = <?= json_encode(json_decode($aide_dynamique)) ?>;
    
    function updateAide(machine) {
        var aideContainer = $('#aide-container');
        
        if (!machine) {
            aideContainer.html('<div class="alert alert-info"><h4><i class="fa fa-info-circle"></i> <?php _ejs('changement.instructions_title'); ?></h4><p><?php _ejs('changement.select_machine_to_see_instructions'); ?></p></div>');
            return;
        }
        
        // Chercher l'aide pour cette machine dans la catégorie 'changement'
        var aide = aides[machine];
        
        if (aide && aide.length > 0) {
            // Construire l'affichage avec les Q&A
            var html = '<div class="aide-item">';
            html += '<h4><i class="fa fa-tint"></i> <?php _ejs('changement.instructions_for'); ?> ' + machine + '</h4>';
            
            aide.forEach(function(qa) {
                html += '<div class="qa-item" style="margin-bottom: 15px; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; border-radius: 4px;">';
                html += '<h5 style="color: #007bff; margin-bottom: 10px;"><i class="fa fa-question-circle"></i> ' + qa.question + '</h5>';
                html += '<div class="qa-answer" style="color: #333;">' + qa.reponse + '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            aideContainer.html(html);
        } else {
            // Aide par défaut si aucune aide spécifique
            var defaultAide = '<div class="alert alert-info">' +
                '<h4><i class="fa fa-info-circle"></i> <?php _ejs('changement.instructions_for'); ?> ' + machine + '</h4>' +
                '<p><strong><?php _ejs('changement.how_to_find_count'); ?></strong></p>' +
                '<ul>' +
                '<li><?php _ejs('changement.go_to_machine'); ?></li>' +
                '<li><?php _ejs('changement.press_f1'); ?></li>' +
                '<li><?php _ejs('changement.print_counters'); ?></li>' +
                '<li><?php _ejs('changement.note_number'); ?></li>' +
                '</ul>' +
                '<p><strong><?php _ejs('changement.for_duplicators'); ?></strong></p>' +
                '<ul>' +
                '<li><?php _ejs('changement.enter_current_passes'); ?></li>' +
                '<li><?php _ejs('changement.select_consumable_type'); ?></li>' +
                '</ul>' +
                '<p><strong><?php _ejs('changement.for_photocopiers'); ?></strong></p>' +
                '<ul>' +
                '<li><?php _ejs('changement.enter_total_copies'); ?></li>' +
                '<li><?php _ejs('changement.select_consumable_type_photo'); ?></li>' +
                '</ul>' +
                '<p><em><?php _ejs('changement.no_specific_help'); ?></em></p>' +
                '</div>';
            aideContainer.html(defaultAide);
        }
    }
    
    // Mettre à jour l'aide quand la machine change (déjà géré dans le gestionnaire principal)
    // La fonction updateAide() est appelée dans le gestionnaire principal du changement de machine
});
</script>

<?php endif; ?>
