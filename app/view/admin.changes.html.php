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
                        <label for="machine"><?php _e('changement.machine', [], false); ?> :</label>
                        <select class="form-control" id="machine" name="machine" required>
                          <option value=""><?php _e('changement.select_machine', [], false); ?></option>
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
                        <label for="type"><?php _e('changement.type', [], false); ?> :</label>
                        <select class="form-control" id="type" name="type" required>
                          <option value=""><?php _e('changement.select_type', [], false); ?></option>
                        </select>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="date"><?php _e('common.date', [], false); ?> :</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>"
                          required>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_p"><?php _e('changement.passages', [], false); ?> :</label>
                        <input type="number" class="form-control" id="nb_p" name="nb_p" placeholder="Ex: 12345"
                          required>
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="nb_m"><?php _e('changement.masters', [], false); ?> :</label>
                        <input type="number" class="form-control" id="nb_m" name="nb_m" placeholder="Ex: 67890"
                          style="display: none;">
                      </div>
                    </div>

                    <div class="col-md-2">
                      <div class="form-group">
                        <label for="tambour"><?php _e('changement.drum', [], false); ?> :</label>
                        <select class="form-control" id="tambour" name="tambour" style="display: none;">
                          <option value=""><?php _e('changement.select_drum', [], false); ?></option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-md-12 text-center">
                      <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-plus"></i> <?php _e('admin.changes.add_btn', [], false); ?>
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
                <h3 class="panel-title"><i class="fa fa-history"></i> <?php _e('admin.changes.history_title', [], false); ?></h3>
              </div>
              <div class="panel-body">
                <?php if (isset($changes_by_machine) && !empty($changes_by_machine)): ?>
                  <?php foreach ($changes_by_machine as $machine_name => $machine_changes): ?>
                    <div class="panel panel-default">
                      <div class="panel-heading">
                        <h3 class="panel-title">
                          <i class="fa fa-print"></i>
                          <?= htmlspecialchars($machine_name) ?>
                          <span class="badge"><?= count($machine_changes) ?> <?php _e('admin.changes.change_count', [], false); ?></span>
                        </h3>
                      </div>
                      <div class="panel-body">
                        <div class="table-responsive">
                          <table class="table table-striped table-hover">
                            <thead>
                              <tr>
                                <th><?php _e('common.date', [], false); ?></th>
                                <th><?php _e('changement.type', [], false); ?></th>
                                <th><?php _e('changement.passages', [], false); ?></th>
                                <th><?php _e('changement.masters', [], false); ?></th>
                                <th><?php _e('changement.drum', [], false); ?></th>
                                <th><?php _e('common.actions', [], false); ?></th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($machine_changes as $change): ?>
                                <tr>
                                  <td>
                                    <?=
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
                    <i class="fa fa-info-circle"></i> <?php _e('admin.changes.no_changes', [], false); ?>
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
                <h3 class="panel-title"><i class="fa fa-arrow-left"></i> <?php _e('common.navigation', [], false); ?></h3>
              </div>
              <div class="panel-body">
                <a href="?admin" class="btn btn-primary">
                  <i class="fa fa-arrow-left"></i> <?php _e('admin.emails.back_to_admin', [], false); ?>
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
        <h4 class="modal-title"><?php _e('admin.changes.edit_change_title', [], false); ?></h4>
      </div>
      <div class="modal-body">
        <form id="edit-form">
          <input type="hidden" id="edit_id" name="id">

          <div class="form-group">
            <label for="edit_machine"><?php _e('changement.machine', [], false); ?> :</label>
            <select class="form-control" id="edit_machine" name="machine" required>
              <option value=""><?php _e('changement.select_machine', [], false); ?></option>
              <?php if (isset($machines) && !empty($machines)): ?>
                <?php foreach ($machines as $machine): ?>
                  <option value="<?= htmlspecialchars($machine) ?>"><?= htmlspecialchars($machine) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="edit_type"><?php _e('changement.type', [], false); ?> :</label>
            <select class="form-control" id="edit_type" name="type" required>
              <option value=""><?php _e('changement.select_type', [], false); ?></option>
            </select>
          </div>

          <div class="form-group">
            <label for="edit_date"><?php _e('common.date', [], false); ?> :</label>
            <input type="date" class="form-control" id="edit_date" name="date" required>
          </div>

          <div class="form-group">
            <label for="edit_nb_p"><?php _e('changement.passages', [], false); ?> :</label>
            <input type="number" class="form-control" id="edit_nb_p" name="nb_p" required>
          </div>

          <div class="form-group">
            <label for="edit_nb_m"><?php _e('changement.masters', [], false); ?> :</label>
            <input type="number" class="form-control" id="edit_nb_m" name="nb_m">
          </div>

          <div class="form-group">
            <label for="edit_tambour"><?php _e('changement.drum', [], false); ?> :</label>
            <select class="form-control" id="edit_tambour" name="tambour">
              <option value=""><?php _e('changement.select_drum', [], false); ?></option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('common.cancel', [], false); ?></button>
        <button type="button" class="btn btn-primary" id="save-edit"><?php _e('common.save', [], false); ?></button>
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
