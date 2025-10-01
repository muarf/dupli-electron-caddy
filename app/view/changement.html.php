<?php
// Messages de succès/erreur
if(isset($success_message)): ?>
    <div class="alert alert-success">
        <strong>Succès !</strong> <?= htmlspecialchars($success_message) ?>
        <br><br>
        <a href="?accueil" class="btn btn-primary">
            <i class="fa fa-home"></i> Retour à l'accueil
        </a>
    </div>
<?php elseif(isset($error_message)): ?>
    <div class="alert alert-danger">
        <strong>Erreur :</strong> <?= htmlspecialchars($error_message) ?>
    </div>
<?php endif; ?>

<?php if(!isset($success_message)): ?>
<div class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="text-center">
                    <i class="fa fa-tint"></i> Signalement de changement de consommable
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
                        <legend><i class="fa fa-cog"></i> Informations du changement</legend>
                        
                        <!-- Sélection de la machine -->
                        <div class="form-group">
                            <label class="col-md-4 control-label" for="machine">Machine :</label>
                            <div class="col-md-4">
                                <select name="machine" id="machine" class="form-control" required>
                                    <option value="">Sélectionnez une machine</option>
                                    
                                    <!-- Duplicopieurs -->
                                    <?php if(isset($duplicopieurs) && count($duplicopieurs) > 0): ?>
                                        <optgroup label="Duplicopieurs">
                                            <?php foreach($duplicopieurs as $dup): ?>
                                                <option value="<?= htmlspecialchars($dup['name']) ?>"><?= htmlspecialchars($dup['name']) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    
                                    <!-- Photocopieurs -->
                                    <?php if(isset($photocopiers) && count($photocopiers) > 0): ?>
                                        <optgroup label="Photocopieurs">
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
                            <label class="col-md-4 control-label" for="type">Type de consommable :</label>
                            <div class="col-md-4">
                                <select name="type" id="type" class="form-control" required>
                                    <option value="">Sélectionnez un type</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Nombre de passages -->
                        <div class="form-group">
                            <label class="col-md-4 control-label" for="nb_p">Nombre de passages :</label>
                            <div class="col-md-4">
                                <input id="nb_p" name="nb_p" class="form-control input-md" required type="number" placeholder="Ex: 12345">
                                <span class="help-block">Nombre total de copies depuis le dernier changement</span>
                            </div>
                        </div>
                        
                        <!-- Nombre de masters (pour duplicopieurs) -->
                        <div class="form-group" id="masters-group" style="display: none;">
                            <label class="col-md-4 control-label" for="nb_m">Nombre de masters :</label>
                            <div class="col-md-4">
                                <input id="nb_m" name="nb_m" class="form-control input-md" type="number" placeholder="Ex: 67890">
                                <span class="help-block">Nombre de masters actuels</span>
                            </div>
                        </div>
                        
                        <!-- Sélection du tambour (pour duplicopieurs) -->
                        <div class="form-group" id="tambour-group" style="display: none;">
                            <label class="col-md-4 control-label" for="tambour">Tambour :</label>
                            <div class="col-md-4">
                                <select name="tambour" id="tambour" class="form-control">
                                    <option value="">Sélectionnez un tambour</option>
                                </select>
                                <span class="help-block">encre qui a été changé</span>
                            </div>
                        </div>
                        
                        <!-- Bouton de soumission -->
                        <div class="form-group">
                            <div class="col-md-4 col-md-offset-4">
                                <button type="submit" class="btn btn-success btn-block btn-lg">
                                    <i class="fa fa-save"></i> Enregistrer le changement
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
                                <h4><i class="fa fa-info-circle"></i> Instructions</h4>
                                <p>Sélectionnez une machine pour voir les instructions spécifiques.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="row">
                    <div class="col-md-12 text-center">
                        <a href="?accueil" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Retour à l'accueil
                        </a>
                        <a href="?stats" class="btn btn-info">
                            <i class="fa fa-bar-chart"></i> Voir les statistiques
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Types de consommables par machine
    var consumableTypes = {};
    var duplicopieursNames = [];
    
    // Ajouter les types pour les duplicopieurs
    var duplicopieursData = {};
    <?php if(isset($duplicopieurs) && count($duplicopieurs) > 0): ?>
        <?php foreach($duplicopieurs as $dup): ?>
            consumableTypes['<?= htmlspecialchars($dup['name']) ?>'] = [
                {value: 'master', text: 'Master'},
                {value: 'encre', text: 'Encre'}
            ];
            duplicopieursNames.push('<?= htmlspecialchars($dup['name']) ?>');
            duplicopieursData['<?= htmlspecialchars($dup['name']) ?>'] = <?= json_encode($dup['tambours']) ?>;
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Ajouter les types pour les photocopieurs
    <?php if(isset($photocopiers) && count($photocopiers) > 0): ?>
        <?php foreach($photocopiers as $photocop): ?>
            consumableTypes['<?= htmlspecialchars($photocop) ?>'] = [
                {value: 'noire', text: 'Encre noire'},
                {value: 'bleue', text: 'Encre bleue'},
                {value: 'rouge', text: 'Encre rouge'},
                {value: 'jaune', text: 'Encre jaune'},
                {value: 'couleur', text: 'Encre couleur'},
                {value: 'tambour', text: 'Tambour (Drum Unit)'},
                {value: 'dev', text: 'Unité de développement'}
            ];
        <?php endforeach; ?>
    <?php endif; ?>
    
    // Gestion du changement de machine
    $('#machine').change(function() {
        var machine = $(this).val();
        var typeSelect = $('#type');
        var mastersGroup = $('#masters-group');
        var tambourGroup = $('#tambour-group');
        var tambourSelect = $('#tambour');
        
        // Vider les options
        typeSelect.html('<option value="">Sélectionnez un type</option>');
        tambourSelect.html('<option value="">Sélectionnez un tambour</option>');
        
        if (machine && consumableTypes[machine]) {
            // Ajouter les options pour cette machine
            $.each(consumableTypes[machine], function(index, option) {
                typeSelect.append('<option value="' + option.value + '">' + option.text + '</option>');
            });
            
            // Afficher/masquer les champs selon le type de machine
            if (duplicopieursNames.indexOf(machine) !== -1) {
                mastersGroup.show();
                tambourGroup.show();
                
                // Remplir les options de tambours
                if (duplicopieursData[machine]) {
                    $.each(duplicopieursData[machine], function(index, tambour) {
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
    });
    
    // Gestion du changement de type de consommable
    $('#type').change(function() {
        var type = $(this).val();
        var machine = $('#machine').val();
        
        // Pour les duplicopieurs, rendre les champs obligatoires selon le type
        if (duplicopieursNames.indexOf(machine) !== -1) {
            if (type === 'master') {
                $('#nb_m').prop('required', true);
                $('#tambour').prop('required', false);
            } else if (type === 'tambour') {
                $('#nb_m').prop('required', false);
                $('#tambour').prop('required', true);
            } else {
                $('#nb_m').prop('required', false);
                $('#tambour').prop('required', false);
            }
        } else {
            $('#nb_m').prop('required', false);
            $('#tambour').prop('required', false);
        }
    });
    
    // Validation du formulaire
    $('#changement-form').submit(function(e) {
        var machine = $('#machine').val();
        var type = $('#type').val();
        var nb_p = $('#nb_p').val();
        
        if (!machine || !type || !nb_p) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
            return false;
        }
        
        // Validation pour les duplicopieurs
        if (duplicopieursNames.indexOf(machine) !== -1) {
            if (type === 'master' && !$('#nb_m').val()) {
                e.preventDefault();
                alert('Veuillez entrer le nombre de masters pour les changements de master.');
                return false;
            }
            if (type === 'tambour' && !$('#tambour').val()) {
                e.preventDefault();
                alert('Veuillez sélectionner un tambour pour les changements de tambour.');
                return false;
            }
        }
    });
    
    // Gestion de l'aide dynamique
    var aides = <?= json_encode(json_decode($aide_dynamique)) ?>;
    
    function updateAide(machine) {
        var aideContainer = $('#aide-container');
        
        if (!machine) {
            aideContainer.html('<div class="alert alert-info"><h4><i class="fa fa-info-circle"></i> Instructions</h4><p>Sélectionnez une machine pour voir les instructions spécifiques.</p></div>');
            return;
        }
        
        // Chercher l'aide pour cette machine
        var aide = aides.find(function(a) {
            return a.machine === machine;
        });
        
        if (aide) {
            // Afficher l'aide spécifique
            aideContainer.html('<div class="aide-item"><h5><strong>' + machine + '</strong></h5><div class="aide-content">' + aide.contenu_aide + '</div></div>');
        } else {
            // Aide par défaut si aucune aide spécifique
            var defaultAide = '<div class="alert alert-info">' +
                '<h4><i class="fa fa-info-circle"></i> Instructions pour ' + machine + '</h4>' +
                '<p><strong>Pour les duplicopieurs (A3/A4) :</strong></p>' +
                '<ul>' +
                '<li>Entrez le nombre de passages actuels</li>' +
                '<li>Sélectionnez le type de consommable changé (Master)</li>' +
                '</ul>' +
                '<p><strong>Pour les photocopieurs :</strong></p>' +
                '<ul>' +
                '<li>Entrez le nombre total de copies depuis le dernier changement</li>' +
                '<li>Sélectionnez le type de consommable changé (encre, toner, tambour, etc.)</li>' +
                '</ul>' +
                '<p><strong>Pour connaître le nombre à entrer :</strong></p>' +
                '<ul>' +
                '<li>Allez sur la machine</li>' +
                '<li>Appuyez sur F1</li>' +
                '<li>Imprimez la liste des compteurs</li>' +
                '<li>Notez le nombre correspondant au consommable changé</li>' +
                '</ul>' +
                '</div>';
            aideContainer.html(defaultAide);
        }
    }
    
    // Mettre à jour l'aide quand la machine change
    $('#machine').change(function() {
        updateAide($(this).val());
    });
});
</script>

<?php endif; ?>
