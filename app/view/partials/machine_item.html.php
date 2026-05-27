<?php
// Template partiel pour un item de machine
// Variables attendues: $index, $duplicopieurs, $duplicopieur_selectionne, $photocopiers
// Variables optionnelles: $master_av, $passage_av (valeurs par défaut: 0)

// Valeurs par défaut pour les compteurs
if (!isset($master_av)) $master_av = 0;
if (!isset($passage_av)) $passage_av = 0;

// Déterminer quel onglet est actif par défaut (photocopieur pour tous, comme le premier tirage)
$default_tab_active = 'photocopieur';
$duplicopieur_active = '';
$photocopieur_active = 'active';
$duplicopieur_display = 'display:none;';
$photocopieur_display = '';
$duplicopieur_checked = '';
$photocopieur_checked = 'checked';
?>
<div class="machine-item panel panel-primary" data-index="<?= $index ?>">
    <!-- Header cliquable avec preview -->
    <div class="panel-heading" style="cursor: pointer;">
        <div class="row" onclick="toggleMachinePanel(<?= $index ?>)">
            <div class="col-xs-8 col-sm-9">
                <h4 class="panel-title" style="margin: 0;">
                    <i class="fa fa-chevron-down toggle-icon" id="toggle-icon-<?= $index ?>"></i>
                    <strong><?php _e('tirage_multimachines.tirage_number'); ?><?= $index + 1 ?></strong>
                    <span class="machine-type-badge badge" id="type-badge-<?= $index ?>"><?php _e('tirage_multimachines.' . $default_tab_active); ?></span>
                </h4>
            </div>
            <div class="col-xs-4 col-sm-3 text-right">
                <span class="machine-price-preview" id="price-preview-<?= $index ?>">0.00 <?php _e('tirage_multimachines.currency'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Corps du panel (pliable) -->
    <div class="panel-body machine-content" id="machine-content-<?= $index ?>" style="padding: 20px;">
    
    <!-- Type de machine - Système d'onglets -->
    <div class="form-group">
        <div class="col-md-12">
            <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 20px;">
                <li role="presentation" class="<?= $duplicopieur_active ?>" id="tab-duplicopieur-<?= $index ?>">
                    <a href="#" onclick="selectMachineTypeTab(<?= $index ?>, 'duplicopieur'); return false;" style="font-size: 16px;">
                        <i class="fa fa-print" style="margin-right: 5px;"></i> <?php _e('tirage_multimachines.duplicopieur'); ?>
                    </a>
                </li>
                <li role="presentation" class="<?= $photocopieur_active ?>" id="tab-photocopieur-<?= $index ?>">
                    <a href="#" onclick="selectMachineTypeTab(<?= $index ?>, 'photocopieur'); return false;" style="font-size: 16px;">
                        <i class="fa fa-copy" style="margin-right: 5px;"></i> <?php _e('tirage_multimachines.photocopieur'); ?>
                    </a>
                </li>
            </ul>
            <!-- Inputs cachés pour les valeurs -->
            <input type="radio" name="machines[<?= $index ?>][type]" value="duplicopieur" <?= $duplicopieur_checked ?> onchange="toggleMachineType(<?= $index ?>)" style="display: none;" id="radio-duplicopieur-<?= $index ?>">
            <input type="radio" name="machines[<?= $index ?>][type]" value="photocopieur" <?= $photocopieur_checked ?> onchange="toggleMachineType(<?= $index ?>)" style="display: none;" id="radio-photocopieur-<?= $index ?>">
        </div>
    </div>
    
    <!-- Interface duplicopieur -->
    <div id="duplicopieur-interface-<?= $index ?>" class="machine-interface" style="<?= $duplicopieur_display ?> background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #17a2b8;">
        <!-- Affichage du duplicopieur -->
        <div class="form-group">
            <label class="col-md-3 control-label">
                <i class="fa fa-cog" style="margin-right: 5px;"></i> Duplicopieur
            </label>
            <div class="col-md-9">
                <?php if(isset($duplicopieur_selectionne)): ?>
                    <input type="hidden" name="machines[<?= $index ?>][duplicopieur_id]" value="<?= $duplicopieur_selectionne['id'] ?>">
                    <p class="form-control-static">
                        <strong><?= htmlspecialchars($duplicopieur_selectionne['marque']) ?> <?= htmlspecialchars($duplicopieur_selectionne['modele']) ?></strong>
                        <br><small class="text-muted"><?php _e('tirage_multimachines.supports_a3_a4'); ?></small>
                    </p>
                <?php elseif(isset($duplicopieurs) && count($duplicopieurs) > 1): ?>
                    <select name="machines[<?= $index ?>][duplicopieur_id]" class="form-control" required onchange="updateDuplicopieurCounters(this.value, <?= $index ?>)">
                        <option value=""><?php _e('tirage_multimachines.choose_duplicopieur'); ?></option>
                        <?php foreach($duplicopieurs as $dup_index => $dup): ?>
                            <?php 
                            $machine_name = $dup['marque'];
                            if ($dup['marque'] !== $dup['modele']) {
                                $machine_name = $dup['marque'] . ' ' . $dup['modele'];
                            }
                            ?>
                            <option value="<?= $dup['id'] ?>" data-name="<?= htmlspecialchars($machine_name) ?>">
                                <?= htmlspecialchars($dup['marque']) ?> <?= htmlspecialchars($dup['modele']) ?> 
                                (<?= $dup['supporte_a3'] ? 'A3' : '' ?><?= $dup['supporte_a3'] && $dup['supporte_a4'] ? '/' : '' ?><?= $dup['supporte_a4'] ? 'A4' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <p class="form-control-static text-danger"><?php _e('tirage_multimachines.no_duplicopieur'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sélection du tambour -->
        <div class="form-group" id="tambour-group-<?= $index ?>" style="display: none;">
            <label class="col-md-3 control-label">
                <i class="fa fa-circle" style="margin-right: 5px;"></i> <?php _e('tirage_multimachines.tambour_used'); ?>
            </label>
            <div class="col-md-9">
                <select name="machines[<?= $index ?>][tambour]" class="form-control" id="tambour-select-<?= $index ?>">
                    
                </select>
                <span class="help-block"><?php _e('tirage_multimachines.choose_tambour'); ?></span>
            </div>
        </div>
        
        <!-- Options duplicopieur -->
        <div class="form-group" style="padding: 10px; margin: 10px 0;">
            <label class="col-md-2 control-label">
                <i class="fa fa-sliders" style="margin-right: 5px;"></i> <?php _e('tirage_multimachines.options'); ?>
            </label>
            <div class="col-md-10">
                <div class="row">
                    <div class="col-xs-4 col-sm-3">
                        <div class="checkbox">
                            <label for="A4_<?= $index ?>">
                                <input name="machines[<?= $index ?>][A4]" value="A4" type="checkbox" onchange="calculateTotalPrice()" id="A4_<?= $index ?>">
                                <i class="fa fa-file-text-o"></i> Format A4
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-4 col-sm-3">
                        <div class="checkbox">
                            <label for="rv_<?= $index ?>">
                                <input name="machines[<?= $index ?>][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="rv_<?= $index ?>">
                                <i class="fa fa-files-o"></i> <?php _e('tirage_multimachines.recto_verso'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-6">
                        <div class="checkbox">
                            <label for="feuilles_payees_<?= $index ?>">
                                <input name="machines[<?= $index ?>][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="feuilles_payees_<?= $index ?>">
                                <i class="fa fa-money" style="color: #f39c12;"></i> <?php _e('tirage_multimachines.sheets_already_paid'); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mode de saisie -->
        <div class="col-md-12" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; border-left: 4px solid #28a745;">
            <legend style="border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 15px; font-size: 18px;">
                <i class="fa fa-keyboard-o" style="margin-right: 8px; color: #28a745;"></i> <?php _e('tirage_multimachines.input_mode'); ?>
            </legend>
            <div class="form-group">
                <label class="col-md-3 control-label"><?php _e('tirage_multimachines.input_type'); ?></label>
                <div class="col-md-4">
                    <div class="radio">
                        <label>
                            <input type="radio" name="machines[<?= $index ?>][mode_saisie]" value="compteurs" checked onchange="toggleSaisieMode(<?= $index ?>)">
                            <?php _e('tirage_multimachines.counters_before_after'); ?>
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="machines[<?= $index ?>][mode_saisie]" value="manuel" onchange="toggleSaisieMode(<?= $index ?>)">
                            <?php _e('tirage_multimachines.masters_and_passes'); ?>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Mode compteurs -->
            <div id="compteurs-mode-<?= $index ?>" class="saisie-mode">
                <div class="row">
                    <div class="col-md-6">
                        <fieldset style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <legend style="width: auto; padding: 0 10px; font-size: 16px; margin-bottom: 10px;"><?php _e('tirage_multimachines.counters_before'); ?></legend>
                            <div class="form-group">
                                <label class="col-xs-4 control-label" for="master_av_<?= $index ?>"><?php _e('tirage_multimachines.masters'); ?></label>  
                                <div class="col-xs-8">
                                    <input id="master_av_<?= $index ?>" name="machines[<?= $index ?>][master_av]" class="form-control input-sm" type="number" min="0" value="<?= $master_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-xs-4 control-label" for="passage_av_<?= $index ?>"><?php _e('tirage_multimachines.passes'); ?></label>  
                                <div class="col-xs-8">
                                    <input id="passage_av_<?= $index ?>" name="machines[<?= $index ?>][passage_av]" class="form-control input-sm" type="number" min="0" value="<?= $passage_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="col-md-6">
                        <fieldset style="border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <legend style="width: auto; padding: 0 10px; font-size: 16px; margin-bottom: 10px;"><?php _e('tirage_multimachines.counters_after'); ?></legend>
                            <div class="form-group">
                                <label class="col-xs-4 control-label" for="master_ap_<?= $index ?>"><?php _e('tirage_multimachines.masters_label'); ?></label>  
                                <div class="col-xs-8">
                                    <input id="master_ap_<?= $index ?>" name="machines[<?= $index ?>][master_ap]" class="form-control input-sm" type="number" min="0" value="<?= $master_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-xs-4 control-label" for="passage_ap_<?= $index ?>"><?php _e('tirage_multimachines.passes'); ?></label>  
                                <div class="col-xs-8">
                                    <input id="passage_ap_<?= $index ?>" name="machines[<?= $index ?>][passage_ap]" class="form-control input-sm" type="number" min="0" value="<?= $passage_av ?>" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </div>
            
            <!-- Mode manuel -->
            <div id="manuel-mode-<?= $index ?>" class="saisie-mode" style="display:none;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-xs-4 control-label" for="nb_masters_<?= $index ?>"><?php _e('tirage_multimachines.masters_label'); ?></label>  
                            <div class="col-xs-8">
                                <input id="nb_masters_<?= $index ?>" name="machines[<?= $index ?>][nb_masters]" class="form-control input-sm" type="number" min="0" value="0" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                <span class="help-block">Nombre utilisé</span>  
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-xs-4 control-label" for="nb_passages_<?= $index ?>"><?php _e('tirage_multimachines.passes_label'); ?></label>  
                            <div class="col-xs-8">
                                <input id="nb_passages_<?= $index ?>" name="machines[<?= $index ?>][nb_passages]" class="form-control input-sm" type="number" min="0" value="0" onchange="calculateTotalPrice()" style="max-width: 120px;">
                                <span class="help-block">Nombre effectué</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Interface photocopieur -->
    <div id="photocopieur-interface-<?= $index ?>" class="machine-interface" style="<?= $photocopieur_display ?> background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #e83e8c;">
        <!-- Sélection photocopieuse -->
        <div class="form-group">
            <label class="col-md-3 control-label" for="marque_<?= $index ?>">
                <i class="fa fa-desktop" style="margin-right: 5px;"></i> Photocopieuse
            </label>
            <div class="col-md-9">
                <select id="marque_<?= $index ?>" name="machines[<?= $index ?>][machine]" class="form-control">
                    <?php
                    if (isset($photocopiers) && !empty($photocopiers)) {
                        $first_photocop = true;
                        foreach ($photocopiers as $photocop) {
                            $selected = $first_photocop ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($photocop->marque) . '" ' . $selected . '>' . htmlspecialchars($photocop->marque) . '</option>';
                            $first_photocop = false;
                        }
                    } else {
                        echo '<option value="">-- Aucune photocopieuse disponible --</option>';
                    }
                    ?>
                </select>
                <span class="help-block"><?php _e('tirage_multimachines.which_photocopier'); ?></span>
            </div>
        </div>
        
        
        <!-- Section pour les brochures/tracts -->
        <div class="brochures-container" data-machine="<?= $index ?>">
            <h5 style="background: #f8f9fa; padding: 12px; border-radius: 5px; margin-bottom: 15px; border-left: 3px solid #9c27b0;">
                <i class="fa fa-book" style="margin-right: 8px; color: #9c27b0;"></i> <?php _e('tirage_multimachines.brochures_tracts'); ?>
            </h5>
            <div class="brochure-item" data-brochure="0" style="padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 5px; margin-bottom: 10px;">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label" for="nb_exemplaires_<?= $index ?>_0">
                                <i class="fa fa-copy"></i> <?php _e('tirage_multimachines.number_copies'); ?>
                            </label>  
                            <input id="nb_exemplaires_<?= $index ?>_0" name="machines[<?= $index ?>][brochures][0][nb_exemplaires]" class="form-control input-sm" type="number" min="1" value="1" onchange="calculateTotalPrice()" style="max-width: 100px;" placeholder="Ex: 10">
                            <small class="text-muted"><?php _e('tirage_multimachines.copies_question'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label" for="nb_feuilles_<?= $index ?>_0">
                                <i class="fa fa-file-text-o"></i> <?php _e('tirage_multimachines.sheets_per_copy'); ?>
                            </label>  
                            <input id="nb_feuilles_<?= $index ?>_0" name="machines[<?= $index ?>][brochures][0][nb_feuilles]" class="form-control input-sm" type="number" min="1" onchange="calculateTotalPrice()" style="max-width: 100px;" placeholder="Ex: 5">
                            <small class="text-muted"><?php _e('tirage_multimachines.pages_per_copy'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">
                                <i class="fa fa-calculator"></i> <?php _e('tirage_multimachines.total_sheets'); ?>
                            </label>
                            <div class="well well-sm" style="margin: 0; padding: 8px; background: #f8f9fa; border: 1px solid #ddd;">
                                <span id="total-feuilles-<?= $index ?>-0" style="font-weight: bold; color: #007bff;">1 feuille</span>
                                <small class="text-muted">(<?php _e('tirage_multimachines.sheets_calculation'); ?>)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label" for="radios_<?= $index ?>_0"><?php _e('tirage_multimachines.size'); ?></label>
                            <div> 
                                <label class="radio-inline" for="radios-<?= $index ?>-0-0">
                                    <input name="machines[<?= $index ?>][brochures][0][taille]" id="radios-<?= $index ?>-0-0" value="A4" checked="checked" type="radio" onchange="calculateTotalPrice()">
                                    A4
                                </label> 
                                <label class="radio-inline" for="radios-<?= $index ?>-0-1">
                                    <input name="machines[<?= $index ?>][brochures][0][taille]" id="radios-<?= $index ?>-0-1" value="A3" type="radio" onchange="calculateTotalPrice()">
                                    A3
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <label class="control-label"><i class="fa fa-cogs"></i> <?php _e('tirage_multimachines.options'); ?></label>
                        <div class="checkbox-inline" style="margin-right: 20px;">
                            <label for="rv_<?= $index ?>_0">
                                <input name="machines[<?= $index ?>][brochures][0][rv]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="rv_<?= $index ?>_0">
                                <i class="fa fa-files-o"></i> <?php _e('tirage_multimachines.recto_verso'); ?>
                            </label>
                        </div>
                        <div class="checkbox-inline" style="margin-right: 20px;">
                            <label for="couleur_<?= $index ?>_0">
                                <input name="machines[<?= $index ?>][brochures][0][couleur]" value="oui" type="checkbox" onchange="calculateTotalPrice(); toggleFillRateDisplay(<?= $index ?>);" id="couleur_<?= $index ?>_0">
                                <i class="fa fa-tint"></i> Couleur
                            </label>
                        </div>
                        <div class="checkbox-inline">
                            <label for="feuilles_payees_<?= $index ?>_0">
                                <input name="machines[<?= $index ?>][brochures][0][feuilles_payees]" value="oui" type="checkbox" onchange="calculateTotalPrice()" id="feuilles_payees_<?= $index ?>_0">
                                <i class="fa fa-money" style="color: #f39c12;"></i> <?php _e('tirage_multimachines.sheets_already_paid'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Taux de remplissage couleur - sous la case couleur -->
                <div class="form-group" id="fill-rate-group-<?= $index ?>" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; display: none; border-left: 3px solid #e83e8c;">
                    <label class="col-md-3 control-label">
                        <i class="fa fa-percent" style="margin-right: 5px;"></i> <?php _e('tirage_multimachines.fill_rate_color'); ?>
                    </label>
                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="range" id="fill_rate_photocop_slider_<?= $index ?>" min="0" max="100" value="50" step="5" 
                                       class="form-control" oninput="updateFillRateDisplay('photocop', <?= $index ?>)" style="margin: 8px 0;">
                            </div>
                            <div class="col-md-4">
                                <span id="fill_rate_photocop_display_<?= $index ?>" style="font-size: 16px; font-weight: bold; color: #e83e8c;">50%</span>
                            </div>
                        </div>
                        <input type="hidden" id="fill_rate_photocop_<?= $index ?>" name="machines[<?= $index ?>][fill_rate]" value="0.5">
                        <span class="help-block">
                            <?php _e('tirage_multimachines.adjust_fill_rate'); ?> 
                            <a href="/index.php?taux_remplissage" target="_blank" title="<?php echo __('tirage_multimachines.calculate_fill_rate'); ?>">
                                <i class="fa fa-info-circle"></i> <?php _e('tirage_multimachines.calculate_fill_rate'); ?>
                            </a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- Fin photocopieur-interface -->
    
    <!-- Prix de la machine -->
    <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #28a745;">
        <label class="col-md-4 control-label" style="font-size: 14px; font-weight: normal;">
            <i class="fa fa-euro" style="margin-right: 5px; color: #28a745;"></i> <?php _e('tirage_multimachines.price_this_tirage'); ?>
        </label>
        <div class="col-md-8">
            <div class="form-control-static machine-price" data-machine="<?= $index ?>" id="machine-price-<?= $index ?>" style="font-size: 16px; font-weight: bold; color: #28a745;">0.00€</div>
        </div>
    </div>
    
    <!-- Bouton supprimer (seulement pour les machines ajoutées, pas pour la première) -->
    <?php if ($index > 0): ?>
    <div class="form-group">
        <div class="col-md-4"></div>
        <div class="col-md-4">
            <button type="button" class="btn btn-danger btn-sm remove-machine" data-index="<?= $index ?>">
                <i class="fa fa-trash"></i> <?php _e('tirage_multimachines.remove_this_tirage'); ?>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    </div><!-- Fin panel-body -->
</div><!-- Fin machine-item -->

