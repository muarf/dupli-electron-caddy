<!-- CSS pour les icônes de consommables -->
<link href="css/consumable-icons.css" rel="stylesheet">

<div class="section">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <h1 class="text-center"><?php _e('admin.change_management'); ?></h1>
        <hr>

        <!-- Messages d'erreur/succès -->
        <?php if (isset($change_error)): ?>
          <div class="alert alert-danger">
            <strong><?php _e('common.error'); ?> :</strong> <?= htmlspecialchars($change_error) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($change_success)): ?>
          <div class="alert alert-success">
            <strong><?php _e('common.success'); ?> :</strong> <?= htmlspecialchars($change_success) ?>
          </div>
        <?php endif; ?>

        <!-- Section Ajouter un changement -->
        <div class="row">
          <div class="col-md-12">
            <div class="panel panel-primary">
              <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-plus"></i> <?php _e('common.add'); ?>
                  <?php _e('changement.change_info'); ?>
                </h3>
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
                          <?php if (isset($machines) && !empty($machines)): ?>
                            <?php foreach ($machines as $machine): ?>
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
                        <input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>"
                          required>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_p">Passages :</label>
                        <input type="number" class="form-control" id="nb_p" name="nb_p" placeholder="Ex: 12345"
                          required>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_m">Masters :</label>
                        <input type="number" class="form-control" id="nb_m" name="nb_m" placeholder="Ex: 67890"
                          style="display: none;">
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
                <?php if (isset($changes_by_machine) && !empty($changes_by_machine)): ?>
                  <?php foreach ($changes_by_machine as $machine_name => $machine_changes): ?>
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
                              <?php foreach ($machine_changes as $change): ?>
                                <tr>
                                  <td>
                                    <?=
                                      // Normaliser l'affichage de la date (gérer timestamps Unix et datetime)
                                      is_numeric($change['date'])
                                      ? date('d/m/Y', $change['date'])
                                      : date('d/m/Y', strtotime($change['date']))
                                      ?>
                                  </td>
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
              <?php if (isset($machines) && !empty($machines)): ?>
                <?php foreach ($machines as $machine): ?>
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
  var CONFIG = {
    photocopiers: <?= json_encode($machines ?? []) ?>,
    duplicopieurs_tambours: <?= json_encode($duplicopieurs_tambours ?? []) ?>
  };
</script>
<script src="js/admin-changes.js"></script>
