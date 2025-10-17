<!-- CSS pour les icônes de consommables -->
<link href="css/consumable-icons.css" rel="stylesheet">

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center"><?php _e('admin.change_management'); ?></h1>
        <hr>
        
        <!-- Messages d'erreur/succès -->
        <?php if(isset($change_error)): ?>
          <div class="alert alert-danger">
            <strong><?php _e('common.error'); ?> :</strong> <?= htmlspecialchars($change_error) ?>
          </div>
        <?php endif; ?>
        
        <?php if(isset($change_success)): ?>
          <div class="alert alert-success">
            <strong><?php _e('common.success'); ?> :</strong> <?= htmlspecialchars($change_success) ?>
          </div>
        <?php endif; ?>
        
        <!-- Section Ajouter un changement -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus"></i> <?php _e('common.add'); ?> <?php _e('changement.change_info'); ?></h3>
              </div>
              <div class="panel-body">
                <form method="POST" id="add-change-form">
                  <input type="hidden" name="action" value="add_change">
                  
                  <div class="row">
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="machine">Machine :</label>
                        <select class="form-control" id="machine" name="machine" required>
                          <option value="">Sélectionner une machine</option>
                          <?php if(isset($machines) && !empty($machines)): ?>
                            <?php foreach($machines as $machine): ?>
                              <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </select>
                      </div>
                    </div>
                    
                    <div class="col-md-3">
                      <div class="form-group">
                        <label for="type">Type :</label>
                        <select class="form-control" id="type" name="type" required>
                          <option value="">Sélectionner un type</option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="date">Date :</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
                      </div>
                    </div>
                    
                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_p">Passages :</label>
                        <input type="number" class="form-control" id="nb_p" name="nb_p" placeholder="Ex: 12345" required>
                      </div>
                    </div>
                    
                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_m">Masters :</label>
                        <input type="number" class="form-control" id="nb_m" name="nb_m" placeholder="Ex: 67890" style="display: none;">
                      </div>
                    </div>
                    
                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="tambour">Tambour :</label>
                        <select class="form-control" id="tambour" name="tambour" style="display: none;">
                          <option value="">Sélectionner un tambour</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-12 text-center">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-plus"></i> Ajouter le changement
                      </button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Section Historique des changements -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-history"></i> Historique des changements</h3>
              </div>
              <div class="panel-body">
                <?php if(isset($changes_by_machine) && !empty($changes_by_machine)): ?>
                  <?php foreach($changes_by_machine as $machine_name => $machine_changes): ?>
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h3 class="panel-title">
                          <i class="fa fa-print"></i> 
                          <?= htmlspecialchars($machine_name) ?>
                          <span class="badge"><?= count($machine_changes) ?> changement(s)</span>
                        </h3>
                      </div>
                      <div class="panel-body">
                        <div class="table-responsive">
                          <table class="table table-striped table-hover">
                            <thead>
                              <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Passages</th>
                                <th>Masters</th>
                                <th>Tambour</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach($machine_changes as $change): ?>
                                <tr>
                                  <td><?= 
                                      // Normaliser l'affichage de la date (gérer timestamps Unix et datetime)
                                      is_numeric($change['date']) 
                                          ? date('d/m/Y', $change['date']) 
                                          : date('d/m/Y', strtotime($change['date']))
                                  ?></td>
                                  <td>
                                    <?php
                                    $type = $change['type'];
                                    $isToner = in_array($type, ['noir', 'cyan', 'magenta', 'yellow', 'tambour', 'dev']);
                                    $iconClass = $isToner ? 'toner-icon' : 'encre-icon';
                                    $iconSymbol = $isToner ? '●' : '💧';
                                    ?>
                                    <span class="<?= $iconClass ?>"><?= $iconSymbol ?></span>
                                    <?= htmlspecialchars($type) ?>
                                  </td>
                                  <td><?= $change['nb_p'] ?></td>
                                  <td><?= $change['nb_m'] ?></td>
                                  <td><?= htmlspecialchars($change['tambour'] ?? '') ?></td>
                                  <td>
                                    <button class="btn btn-sm btn-info edit-change" data-id="<?= $change['id'] ?>">
                                      <i class="fa fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-change" data-id="<?= $change['id'] ?>">
                                      <i class="fa fa-trash"></i>
                                    </button>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="alert alert-info text-center">
                    <i class="fa fa-info-circle"></i> Aucun changement enregistré pour le moment.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Navigation -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> Navigation</h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> Retour à l'administration
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal d'édition -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Modifier le changement</h4>
      </div>
      <div class="modal-body">
        <form id="edit-form">
          <input type="hidden" id="edit_id" name="id">
          
          <div class="form-group">
            <label for="edit_machine">Machine :</label>
            <select class="form-control" id="edit_machine" name="machine" required>
              <option value="">Sélectionner une machine</option>
              <?php if(isset($machines) && !empty($machines)): ?>
                <?php foreach($machines as $machine): ?>
                  <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          
          <div class="form-group">
            <label for="edit_type">Type :</label>
            <select class="form-control" id="edit_type" name="type" required>
              <option value="">Sélectionner un type</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="edit_date">Date :</label>
            <input type="date" class="form-control" id="edit_date" name="date" required>
          </div>
          
          <div class="form-group">
            <label for="edit_nb_p">Passages :</label>
            <input type="number" class="form-control" id="edit_nb_p" name="nb_p" required>
          </div>
          
          <div class="form-group">
            <label for="edit_nb_m">Masters :</label>
            <input type="number" class="form-control" id="edit_nb_m" name="nb_m">
          </div>
          
          <div class="form-group">
            <label for="edit_tambour">Tambour :</label>
            <select class="form-control" id="edit_tambour" name="tambour">
              <option value="">Sélectionner un tambour</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="save-edit">Sauvegarder</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    // Variables globales
    var photocopiers = <?= json_encode($machines ?? []) ?>;
    var duplicopieurs_tambours = <?= json_encode($duplicopieurs_tambours ?? []) ?>;
    
    // Fonction utilitaire pour vérifier si une valeur est numérique
    function isNumeric(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }
    
    // Fonction pour mettre à jour les options de type selon la machine
    function updateTypeOptions(machine, selectElement) {
        $.get('?admin&ajax=get_machine_type&machine=' + encodeURIComponent(machine))
            .done(function(response) {
                if (response.success) {
                    var type = response.type;
                    var options = '';
                    
                    if (type === 'duplicopieur') {
                        // Duplicopieurs : master et encre
                        options = '<option value="master">Master</option>' +
                                 '<option value="encre">Encre</option>';
                    } else if (type === 'photocop_encre') {
                        // Photocopieurs à encre : 4 couleurs
                        options = '<option value="noir">Noir</option>' +
                                 '<option value="cyan">Cyan</option>' +
                                 '<option value="magenta">Magenta</option>' +
                                 '<option value="yellow">Yellow</option>';
                    } else if (type === 'photocop_toner') {
                        // Photocopieurs à toner : 4 couleurs + dev + tambour
                        options = '<option value="noir">Noir</option>' +
                                 '<option value="cyan">Cyan</option>' +
                                 '<option value="magenta">Magenta</option>' +
                                 '<option value="yellow">Yellow</option>' +
                                 '<option value="dev">Dev</option>' +
                                 '<option value="tambour">Tambour</option>';
                    } else {
                        // Fallback par défaut
                        options = '<option value="master">Master</option>';
                    }
                    
                    selectElement.html('<option value="">Sélectionner un type</option>' + options);
                }
            });
    }
    
    // Fonction pour mettre à jour les compteurs
    function updateCounters(machine) {
        var isPhotocopier = photocopiers.includes(machine);
        
        if (isPhotocopier) {
            $.get('?admin&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
                .done(function(response) {
                    if (response.success) {
                        $('#nb_p').val(response.counters.passage_av);
                        $('#nb_m').val(response.counters.master_av);
                    }
                });
        } else {
            // Pour les duplicopieurs, utiliser la logique existante
            $('#nb_p').val('');
            $('#nb_m').val('');
        }
    }
    
    // Fonction pour afficher/masquer les champs Masters et Tambour
    function toggleQuantityFields(machine) {
        var isPhotocopier = photocopiers.includes(machine);
        
        if (isPhotocopier) {
            $('#nb_m').show().prev('label').show();
            $('#tambour').hide().prev('label').hide();
        } else {
            $('#nb_m').hide().prev('label').hide();
            $('#tambour').show().prev('label').show();
            
            // Remplir les options de tambours si c'est un duplicopieur
            if (duplicopieurs_tambours[machine]) {
                var tambourSelect = $('#tambour');
                tambourSelect.html('<option value="">Sélectionner un tambour</option>');
                $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                    tambourSelect.append('<option value="' + tambour + '">' + tambour + '</option>');
                });
            }
        }
    }
    
    // Fonction pour gérer l'affichage du champ tambour selon le type
    function toggleTambourField(type, machine) {
        var tambourField = $('#tambour');
        var tambourLabel = tambourField.prev('label');
        
        if (type === 'master') {
            // Pour les masters, pas besoin de tambour
            tambourField.hide();
            tambourLabel.hide();
            tambourField.prop('required', false);
        } else if (type === 'encre' || type === 'tambour') {
            // Pour l'encre et les tambours, on doit choisir le tambour
            tambourField.show();
            tambourLabel.show();
            tambourField.prop('required', true);
            
            // Remplir les options de tambours
            if (duplicopieurs_tambours[machine]) {
                tambourField.html('<option value="">Sélectionner un tambour</option>');
                $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                    tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
                });
            }
        } else {
            tambourField.hide();
            tambourLabel.hide();
            tambourField.prop('required', false);
        }
    }
    
    // Event listeners pour le formulaire d'ajout
    $('#machine').change(function() {
        var machine = $(this).val();
        updateTypeOptions(machine, $('#type'));
        updateCounters(machine);
        toggleQuantityFields(machine);
    });
    
    // Event listener pour le changement de type
    $('#type').change(function() {
        var type = $(this).val();
        var machine = $('#machine').val();
        toggleTambourField(type, machine);
    });
    
    // Fonction pour gérer l'affichage du champ tambour dans le modal d'édition
    function toggleEditTambourField(type, machine) {
        var tambourField = $('#edit_tambour');
        var tambourLabel = tambourField.prev('label');
        
        if (type === 'master') {
            // Pour les masters, pas besoin de tambour
            tambourField.hide();
            tambourLabel.hide();
            tambourField.prop('required', false);
        } else if (type === 'encre' || type === 'tambour') {
            // Pour l'encre et les tambours, on doit choisir le tambour
            tambourField.show();
            tambourLabel.show();
            tambourField.prop('required', true);
            
            // Remplir les options de tambours
            if (duplicopieurs_tambours[machine]) {
                tambourField.html('<option value="">Sélectionner un tambour</option>');
                $.each(duplicopieurs_tambours[machine], function(index, tambour) {
                    tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
                });
            }
        } else {
            tambourField.hide();
            tambourLabel.hide();
            tambourField.prop('required', false);
        }
    }
    
    // Event listeners pour le formulaire d'édition
    $('#edit_machine').change(function() {
        var machine = $(this).val();
        updateEditTypeOptions(machine);
    });
    
    // Event listener pour le changement de type dans le modal d'édition
    $('#edit_type').change(function() {
        var type = $(this).val();
        var machine = $('#edit_machine').val();
        toggleEditTambourField(type, machine);
    });
    
    // Fonction pour mettre à jour les options de type dans le modal d'édition
    function updateEditTypeOptions(machine) {
        return new Promise(function(resolve) {
            $.get('?admin&ajax=get_machine_type&machine=' + encodeURIComponent(machine))
                .done(function(response) {
                    // Parser la réponse JSON si c'est une chaîne
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Erreur de parsing JSON pour get_machine_type:', e);
                            resolve();
                            return;
                        }
                    }
                    
                    if (response.success) {
                        var type = response.type;
                        var options = '';
                        
                        if (type === 'duplicopieur') {
                            // Duplicopieurs : master et encre
                            options = '<option value="master">Master</option>' +
                                     '<option value="encre">Encre</option>';
                        } else if (type === 'photocop_encre') {
                            // Photocopieurs à encre : 4 couleurs
                            options = '<option value="noir">Noir</option>' +
                                     '<option value="cyan">Cyan</option>' +
                                     '<option value="magenta">Magenta</option>' +
                                     '<option value="yellow">Yellow</option>';
                        } else if (type === 'photocop_toner') {
                            // Photocopieurs à toner : 4 couleurs + dev + tambour
                            options = '<option value="noir">Noir</option>' +
                                     '<option value="cyan">Cyan</option>' +
                                     '<option value="magenta">Magenta</option>' +
                                     '<option value="yellow">Yellow</option>' +
                                     '<option value="dev">Dev</option>' +
                                     '<option value="tambour">Tambour</option>';
                        } else {
                            // Fallback par défaut
                            options = '<option value="master">Master</option>';
                        }
                        
                        $('#edit_type').html('<option value="">Sélectionner un type</option>' + options);
                    }
                    resolve();
                })
                .fail(function(xhr, status, error) {
                    console.error('Erreur AJAX get_machine_type:', error);
                    resolve(); // Résoudre quand même pour ne pas bloquer
                });
        });
    }
    
    // Gestion de l'édition
    $('.edit-change').click(function() {
        var id = $(this).data('id');
        
        $.get('?admin&ajax=get_change&id=' + id)
            .done(function(response) {
                // Parser la réponse JSON si c'est une chaîne
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('Erreur de parsing JSON:', e);
                        alert('Erreur lors du chargement du changement');
                        return;
                    }
                }
                
                console.log('Réponse reçue:', response);
                
                if (response.success) {
                    var change = response.change;
                    $('#edit_id').val(change.id);
                    $('#edit_machine').val(change.machine);
                    // Normaliser la date pour l'input date (format Y-m-d)
                    var editDate = isNumeric(change.date) 
                        ? new Date(change.date * 1000).toISOString().split('T')[0]
                        : change.date.split(' ')[0];
                    $('#edit_date').val(editDate);
                    $('#edit_nb_p').val(change.nb_p);
                    $('#edit_nb_m').val(change.nb_m);
                    $('#edit_tambour').val(change.tambour || '');
                    
                    // Mettre à jour les options de type et de tambour
                    updateEditTypeOptions(change.machine).then(function() {
                        console.log('Options chargées, type à sélectionner:', change.type);
                        console.log('Options disponibles:', $('#edit_type option').map(function() { return this.value; }).get());
                        
                        // Vérifier si le type actuel est compatible avec le type de machine
                        var currentType = change.type;
                        var typeSelect = $('#edit_type');
                        var availableOptions = typeSelect.find('option').map(function() { return $(this).val(); }).get();
                        
                        if (availableOptions.includes(currentType)) {
                            // Le type est compatible, on peut le sélectionner
                            typeSelect.val(currentType);
                            console.log('Type compatible sélectionné:', currentType);
                        } else {
                            // Le type n'est pas compatible, on sélectionne le premier disponible
                            console.warn('Type "' + currentType + '" non compatible avec le type de machine. Sélection du premier type disponible.');
                            typeSelect.val(availableOptions[1] || ''); // [0] est généralement l'option vide
                        }
                        
                        console.log('Valeur sélectionnée après .val():', $('#edit_type').val());
                        
                        // Gérer l'affichage du champ tambour selon le type sélectionné
                        var selectedType = typeSelect.val();
                        toggleEditTambourField(selectedType, change.machine);
                        
                        // Remplir la valeur du tambour si elle existe
                        $('#edit_tambour').val(change.tambour || '');
                        
                        $('#editModal').modal('show');
                    });
                } else {
                    console.error('Erreur dans la réponse:', response);
                    alert('Erreur lors du chargement du changement: ' + (response.error || 'Erreur inconnue'));
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Erreur AJAX:', xhr.responseText);
                alert('Erreur lors du chargement du changement: ' + error);
            });
    });
    
    // Sauvegarde de l'édition
    $('#save-edit').click(function() {
        var id = $('#edit_id').val();
        var formData = {
            action: 'edit_change',
            id: id,
            machine: $('#edit_machine').val(),
            type: $('#edit_type').val(),
            date: $('#edit_date').val(),
            nb_p: $('#edit_nb_p').val(),
            nb_m: $('#edit_nb_m').val(),
            tambour: $('#edit_tambour').val()
        };
        
        $.post('?admin&changes', formData)
            .done(function(response) {
                console.log('Réponse de modification:', response);
                location.reload();
            })
            .fail(function(xhr, status, error) {
                console.error('Erreur lors de la modification:', xhr.responseText);
                alert('Erreur lors de la modification: ' + xhr.responseText);
            });
    });
    
    // Gestion de la suppression
    $('.delete-change').click(function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce changement ?')) {
            var id = $(this).data('id');
            
            $.post('?admin&changes', {
                action: 'delete_change',
                id: id
            }).done(function(response) {
                location.reload();
            });
        }
    });
    
    // Soumission du formulaire d'ajout
    $('#add-change-form').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.post('?admin&changes', formData)
            .done(function(response) {
                console.log('Réponse de modification:', response);
                location.reload();
            })
            .fail(function(xhr, status, error) {
                console.error('Erreur lors de la modification:', xhr.responseText);
                alert('Erreur lors de la modification: ' + xhr.responseText);
            });
    });
});
</script>


