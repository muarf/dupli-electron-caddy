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
    window.CONFIG = window.CONFIG || {};
    window.CONFIG.duplicopieurs = <?= json_encode($duplicopieurs ?? []) ?>;
    window.CONFIG.aides = <?= json_encode(json_decode($aide_dynamique ?? '{}')) ?>;
    window.CONFIG.translations = Object.assign(window.CONFIG.translations || {}, {
        master: <?= json_encode(__('changement.master')) ?>,
        ink: <?= json_encode(__('changement.ink')) ?>,
        black_ink: <?= json_encode(__('changement.black_ink')) ?>,
        blue_ink: <?= json_encode(__('changement.blue_ink')) ?>,
        red_ink: <?= json_encode(__('changement.red_ink')) ?>,
        yellow_ink: <?= json_encode(__('changement.yellow_ink')) ?>,
        black: <?= json_encode(__('changement.black')) ?>,
        cyan: <?= json_encode(__('changement.cyan')) ?>,
        magenta: <?= json_encode(__('changement.magenta')) ?>,
        yellow: <?= json_encode(__('changement.yellow')) ?>,
        dev: <?= json_encode(__('changement.dev')) ?>,
        drum: <?= json_encode(__('changement.drum')) ?>,
        machine_type_not_recognized: <?= json_encode(__('changement.machine_type_not_recognized')) ?>,
        select_type: <?= json_encode(__('changement.select_type')) ?>,
        error_loading: <?= json_encode(__('changement.error_loading')) ?>,
        select_drum: <?= json_encode(__('changement.select_drum')) ?>,
        fill_all_required: <?= json_encode(__('changement.fill_all_required')) ?>,
        enter_master_count: <?= json_encode(__('changement.enter_master_count')) ?>,
        select_drum_for_ink: <?= json_encode(__('changement.select_drum_for_ink')) ?>,
        instructions_title: <?= json_encode(__('changement.instructions_title')) ?>,
        select_machine_to_see_instructions: <?= json_encode(__('changement.select_machine_to_see_instructions')) ?>,
        instructions_for: <?= json_encode(__('changement.instructions_for')) ?>,
        how_to_find_count: <?= json_encode(__('changement.how_to_find_count')) ?>,
        go_to_machine: <?= json_encode(__('changement.go_to_machine')) ?>,
        press_f1: <?= json_encode(__('changement.press_f1')) ?>,
        print_counters: <?= json_encode(__('changement.print_counters')) ?>,
        note_number: <?= json_encode(__('changement.note_number')) ?>,
        for_duplicators: <?= json_encode(__('changement.for_duplicators')) ?>,
        enter_current_passes: <?= json_encode(__('changement.enter_current_passes')) ?>,
        select_consumable_type: <?= json_encode(__('changement.select_consumable_type')) ?>,
        for_photocopiers: <?= json_encode(__('changement.for_photocopiers')) ?>,
        enter_total_copies: <?= json_encode(__('changement.enter_total_copies')) ?>,
        select_consumable_type_photo: <?= json_encode(__('changement.select_consumable_type_photo')) ?>,
        no_specific_help: <?= json_encode(__('changement.no_specific_help')) ?>
    });
</script>
<script src="js/changement.js" defer></script>


<?php endif; ?>
