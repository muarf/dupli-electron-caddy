// ============================================================
// changement.js -- Gestion des Changements de Consommables
// Extrait de changement.html.php
// ============================================================

/* global $, CONFIG, alert, window, document */

$(document).ready(function() {
    var duplicopieurs_tambours = {};
    var duplicopieursNames = [];

    const trans = (typeof CONFIG !== 'undefined' && CONFIG.translations) ? CONFIG.translations : {};
    const duplicopieursData = (typeof CONFIG !== 'undefined' && CONFIG.duplicopieurs) ? CONFIG.duplicopieurs : [];
    const aides = (typeof CONFIG !== 'undefined' && CONFIG.aides) ? CONFIG.aides : {};

    if (Array.isArray(duplicopieursData)) {
        duplicopieursData.forEach(dup => {
            duplicopieurs_tambours[dup.name] = dup.tambours;
            duplicopieursNames.push(dup.name);
        });
    }
    
    function updateTypeOptions(machine, selectElement) {
        $.get('?changement&ajax=get_machine_type&machine=' + encodeURIComponent(machine) + '&t=' + Date.now())
            .done(function(response) {
                if (response.success) {
                    var type = response.type;
                    var options = '';
                    
                    if (type === 'duplicopieur') {
                        options = `<option value="master">${trans.master || 'Master'}</option>` +
                                  `<option value="encre">${trans.ink || 'Encre'}</option>`;
                    } else if (type === 'photocop_encre') {
                        options = `<option value="noire">${trans.black_ink || 'Encre Noire'}</option>` +
                                  `<option value="bleue">${trans.blue_ink || 'Encre Bleue'}</option>` +
                                  `<option value="rouge">${trans.red_ink || 'Encre Rouge'}</option>` +
                                  `<option value="jaune">${trans.yellow_ink || 'Encre Jaune'}</option>`;
                    } else if (type === 'photocop_toner') {
                        options = `<option value="noir">${trans.black || 'Toner Noir'}</option>` +
                                  `<option value="cyan">${trans.cyan || 'Toner Cyan'}</option>` +
                                  `<option value="magenta">${trans.magenta || 'Toner Magenta'}</option>` +
                                  `<option value="yellow">${trans.yellow || 'Toner Jaune'}</option>` +
                                  `<option value="dev">${trans.dev || 'Développeur'}</option>` +
                                  `<option value="tambour">${trans.drum || 'Tambour'}</option>`;
                    } else {
                        options = `<option value="">${trans.machine_type_not_recognized || 'Type de machine non reconnu'}</option>`;
                    }
                    
                    selectElement.html(`<option value="">${trans.select_type || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionner_un_type'] || 'Sélectionner un type')}</option>` + options);
                }
            })
            .fail(function() {
                selectElement.html(`<option value="">${trans.error_loading || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.erreur_de_chargement'] || 'Erreur de chargement')}</option>`);
            });
    }
    
    function toggleTambourField(type, machine) {
        var tambourField = $('#tambour');
        var tambourGroup = $('#tambour-group');
        
        if (type === 'master') {
            tambourGroup.hide();
            tambourField.prop('required', false);
        } else if (type === 'encre' || type === 'tambour') {
            tambourGroup.show();
            tambourField.prop('required', true);
            
            if (duplicopieurs_tambours[machine]) {
                tambourField.html(`<option value="">${trans.select_drum || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionner_un_tambour'] || 'Sélectionner un tambour')}</option>`);
                $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                    tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
                });
            }
        } else {
            tambourGroup.hide();
            tambourField.prop('required', false);
        }
    }
    
    $('#machine').change(function() {
        var machine = $(this).val();
        var typeSelect = $('#type');
        var mastersGroup = $('#masters-group');
        var tambourGroup = $('#tambour-group');
        var tambourSelect = $('#tambour');
        
        typeSelect.html(`<option value="">${trans.select_type || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionner_un_type'] || 'Sélectionner un type')}</option>`);
        tambourSelect.html(`<option value="">${trans.select_drum || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionner_un_tambour'] || 'Sélectionner un tambour')}</option>`);
        
        if (machine) {
            updateTypeOptions(machine, typeSelect);
            
            if (duplicopieursNames.indexOf(machine) !== -1) {
                mastersGroup.show();
                tambourGroup.show();
                
                if (duplicopieurs_tambours[machine]) {
                    tambourSelect.html(`<option value="">${trans.select_drum || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionner_un_tambour'] || 'Sélectionner un tambour')}</option>`);
                    $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                        tambourSelect.append('<option value="' + tambour + '">' + tambour + '</option>');
                    });
                }
                
                $('#nb_m').prop('required', false);
                
                $.get('?changement&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
                    .done(function(response) {
                        if (response.success) {
                            $('#nb_p').val(response.counters.passage_av);
                            $('#nb_m').val(response.counters.master_av);
                        }
                    })
                    .fail(function() {
                        console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.erreur_lors_du_chargement_des'] || 'Erreur lors du chargement des compteurs'));
                    });
            } else {
                mastersGroup.hide();
                tambourGroup.hide();
                $('#nb_m').prop('required', false);
                
                $.get('?changement&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
                    .done(function(response) {
                        if (response.success) {
                            $('#nb_p').val(response.counters.passage_av);
                            $('#nb_m').val(response.counters.master_av);
                        }
                    })
                    .fail(function() {
                        console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.erreur_lors_du_chargement_des'] || 'Erreur lors du chargement des compteurs'));
                    });
            }
        } else {
            mastersGroup.hide();
            tambourGroup.hide();
            $('#nb_m').prop('required', false);
        }
        
        updateAide(machine);
    });
    
    $('#type').change(function() {
        var type = $(this).val();
        var machine = $('#machine').val();
        toggleTambourField(type, machine);
        
        if (duplicopieursNames.indexOf(machine) !== -1) {
            $('#masters-group').show();
            
            if (type === 'master') {
                $('#nb_m').prop('required', true);
            } else {
                $('#nb_m').prop('required', false);
            }
            
            $.get('models/changement.php?ajax=get_last_counters&machine=' + encodeURIComponent(machine), function(data) {
                if (data.success && data.counters && data.counters.master_av !== undefined) {
                    $('#nb_m').val(data.counters.master_av);
                }
            }).fail(function() {
                console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.erreur_lors_de_la_r_cup_ration'] || 'Erreur lors de la récupération des compteurs'));
            });
        } else {
            $('#nb_m').prop('required', false);
            $('#masters-group').hide();
        }
    });
    
    $('#changement-form').submit(function(e) {
        var machine = $('#machine').val();
        var type = $('#type').val();
        var nb_p = $('#nb_p').val();
        
        if (!machine || !type || !nb_p) {
            e.preventDefault();
            const msg = trans.fill_all_required || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.veuillez_remplir_tous_les_cham'] || 'Veuillez remplir tous les champs obligatoires.');
            if (window.showAppModal) {
                window.showAppModal(msg);
            } else {
                alert(msg);
            }
            return false;
        }
        
        if (duplicopieursNames.indexOf(machine) !== -1) {
            if (type === 'master' && !$('#nb_m').val()) {
                e.preventDefault();
                const msg = trans.enter_master_count || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.veuillez_entrer_le_nombre_de_m'] || 'Veuillez entrer le nombre de masters.');
                if (window.showAppModal) {
                    window.showAppModal(msg);
                } else {
                    alert(msg);
                }
                return false;
            }
            if (type === 'tambour' && !$('#tambour').val()) {
                e.preventDefault();
                const msg = trans.select_drum_for_ink || 'Veuillez sélectionner un tambour pour l\'encre.';
                if (window.showAppModal) {
                    window.showAppModal(msg);
                } else {
                    alert(msg);
                }
                return false;
            }
        }
    });
    
    function updateAide(machine) {
        var aideContainer = $('#aide-container');
        
        if (!machine) {
            aideContainer.html(`<div class="alert alert-info"><h4><i class="fa fa-info-circle"></i> ${trans.instructions_title || 'Instructions'}</h4><p>${trans.select_machine_to_see_instructions || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionnez_une_machine_pour'] || 'Sélectionnez une machine pour voir les instructions.')}</p></div>`);
            return;
        }
        
        var aide = aides[machine];
        
        if (aide && aide.length > 0) {
            var html = '<div class="aide-item">';
            html += `<h4><i class="fa fa-tint"></i> ${trans.instructions_for || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.instructions_pour'] || 'Instructions pour')} ${machine}</h4>`;
            
            aide.forEach(function(qa) {
                html += '<div class="qa-item" style="margin-bottom: 15px; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; border-radius: 4px;">';
                html += '<h5 style="color: #007bff; margin-bottom: 10px;"><i class="fa fa-question-circle"></i> ' + qa.question + '</h5>';
                html += '<div class="qa-answer" style="color: #333;">' + qa.reponse + '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            aideContainer.html(html);
        } else {
            var defaultAide = '<div class="alert alert-info">' +
                `<h4><i class="fa fa-info-circle"></i> ${trans.instructions_for || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.instructions_pour'] || 'Instructions pour')} ${machine}</h4>` +
                `<p><strong>${trans.how_to_find_count || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.comment_trouver_le_compteur'] || 'Comment trouver le compteur :')}</strong></p>` +
                '<ul>' +
                `<li>${trans.go_to_machine || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.allez___la_machine'] || 'Allez à la machine')}</li>` +
                `<li>${trans.press_f1 || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.appuyez_sur_f1'] || 'Appuyez sur F1')}</li>` +
                `<li>${trans.print_counters || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.imprimez_les_compteurs'] || 'Imprimez les compteurs')}</li>` +
                `<li>${trans.note_number || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.notez_le_num_ro'] || 'Notez le numéro')}</li>` +
                '</ul>' +
                `<p><strong>${trans.for_duplicators || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.pour_les_duplicopieurs'] || 'Pour les duplicopieurs :')}</strong></p>` +
                '<ul>' +
                `<li>${trans.enter_current_passes || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.entrez_les_passages_actuels'] || 'Entrez les passages actuels')}</li>` +
                `<li>${trans.select_consumable_type || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionnez_le_type_de_consom'] || 'Sélectionnez le type de consommable')}</li>` +
                '</ul>' +
                `<p><strong>${trans.for_photocopiers || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.pour_les_photocopieurs'] || 'Pour les photocopieurs :')}</strong></p>` +
                '<ul>' +
                `<li>${trans.enter_total_copies || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.entrez_le_nombre_total_de_copi'] || 'Entrez le nombre total de copies')}</li>` +
                `<li>${trans.select_consumable_type_photo || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.changement.s_lectionnez_le_type_de_consom'] || 'Sélectionnez le type de consommable')}</li>` +
                '</ul>' +
                `<p><em>${trans.no_specific_help || 'Aucune aide spécifique disponible'}</em></p>` +
                '</div>';
            aideContainer.html(defaultAide);
        }
    }
});
