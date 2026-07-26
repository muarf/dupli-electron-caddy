<?php
// Fonction pour déterminer le nom de la machine pour l'édition
function getTableForMachine($machine)
{
  $db = pdo_connect();

  // Vérifier si c'est un duplicopieur (SQLite compatible)
  $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (TRIM(marque) || " " || TRIM(modele) = ? OR (marque = ? AND modele = ?))');
  $query->execute([$machine, $machine, $machine]);

  if ($query->fetchColumn() > 0) {
    // Pour les duplicopieurs, retourner le type
    return 'duplicopieur';
  } else {
    // Pour les photocopieurs, retourner le type
    return 'photocopieur';
  }
}
?>

<div class="row">
  <div class="col-md-10 col-md-offset-1">
    <h1><?php _e('admin.print_management'); ?></h1>

    <div class="well">
      <form class="form-inline" method="get">
        <input type="hidden" name="admin" value="">
        <input type="hidden" name="tirages" value="">
        
        <div class="form-group">
          <label for="search"><?php _e('admin_tirage.search_contact'); ?></label>
          <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin_tirage.contact_name_placeholder')) ?>">
        </div>
        
        <div class="form-group" style="margin-left: 10px;">
          <label for="paye"><?php _e('admin_tirage.status_label'); ?></label>
          <select name="paye" id="paye" class="form-control">
            <option value="non" <?= ($_GET['paye'] ?? 'non') === 'non' ? 'selected' : '' ?>><?php _e('admin_tirage.unpaid'); ?></option>
            <option value="deja_paye" <?= ($_GET['paye'] ?? '') === 'deja_paye' ? 'selected' : '' ?>><?php _e('admin_tirage.already_paid'); ?></option>
            <option value="tous" <?= ($_GET['paye'] ?? '') === 'tous' ? 'selected' : '' ?>><?php _e('admin_tirage.all'); ?></option>
          </select>
        </div>
        
        <div class="form-group" style="margin-left: 10px;">
          <div class="checkbox">
            <label>
              <input type="checkbox" name="order" value="1" <?= isset($_GET['order']) ? 'checked' : '' ?>> <?php _e('admin_tirage.sort_by_price'); ?>
            </label>
          </div>
        </div>

        <div class="form-group" style="margin-left: 20px;">
          <div class="checkbox">
            <label>
              <input type="checkbox" id="selectAllGlobal" onchange="toggleAllGlobal(this.checked)"> <strong><?php _e('admin_tirage.select_all'); ?></strong>
            </label>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary" style="margin-left: 10px;"><i class="fa fa-search"></i> <?php _e('admin_tirage.filter'); ?></button>
        <a href="?admin&tirages" class="btn btn-default" style="margin-left: 5px;"><?php _e('admin_tirage.reset_filter'); ?></a>
      </form>
    </div>

    <h4><?= $phrase ?></h4>

    <?php if (isset($delete_success)): ?>
      <div class="alert alert-success alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <?= $delete_success ?>
      </div>
    <?php endif; ?>

    <?php if (isset($delete_error)): ?>
      <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <?= $delete_error ?>
      </div>
    <?php endif; ?>

    <?php if (isset($payment_success)): ?>
      <div class="alert alert-success alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <i class="fa fa-check"></i> <?= $payment_success ?>
      </div>
    <?php endif; ?>

    <?php if (isset($payment_error)): ?>
      <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <i class="fa fa-exclamation-triangle"></i> <?= $payment_error ?>
      </div>
    <?php endif; ?>

    <?php foreach ($machines as $machine) { ?>
      <div class="col-md-6">
        <h2><?= $machine ?></h2>
        <div align="right"><?= round($prix_du[$machine] ?? 0, 2) ?> <?php _e('admin_tirage.pending_amount'); ?></div>
        <table class="table">
          <thead>
            <tr>
              <th><?php _e('admin_tirage.contact'); ?></th>
              <th><?php _e('admin_tirage.date'); ?></th>
              <th><?php _e('admin_tirage.price'); ?></th>
              <th><?php _e('admin_tirage.comments'); ?></th>
              <th><?php _e('common.edit'); ?></th>
              <th><input type="checkbox" id="select-all-<?= preg_replace('/[^a-zA-Z0-9]/', '_', $machine) ?>" onclick="toggleSelectMachine('<?= $machine ?>', this)"></th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Extraire les données de pagination
            $pagination = isset($last[$machine]['pagination']) ? $last[$machine]['pagination'] : null;
            $tirages = $last[$machine];

            // Supprimer les données de pagination pour l'affichage des tirages
            if (isset($tirages['pagination'])) {
              unset($tirages['pagination']);
            }

            // Réindexer le tableau pour s'assurer que les indices sont numériques et séquentiels
            // Vérifier que $tirages est bien un tableau avant array_values
            if (!is_array($tirages)) {
              $tirages = array();
            }
            $tirages = array_values($tirages);

            for ($i = 0; $i < count($tirages); $i++) {
              $group = $tirages[$i];

              $isGroup = isset($group['tirages']) && is_array($group['tirages']) && count($group['tirages']) > 1;
              $groupId = $isGroup ? 'group_' . htmlspecialchars($group['tirage_global_id']) . '_' . $i : '';

              // Afficher un en-tête de groupe si c'est un multi-tirage
              if ($isGroup) {
                // Extraire le contact et la date du tirage_global_id
                $tirage_global_id = $group['tirage_global_id'];
                $parts = explode('_', $tirage_global_id);
                $contact_display = '';
                $date_display = '';

                if (count($parts) >= 2) {
                  $date_part = $parts[0];
                  if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_part, $matches)) {
                    $date_display = $matches[3] . '/' . $matches[2]; // jour/mois
                  }

                  if (count($parts) >= 3) {
                    $contact_display = $parts[2];
                    $contact_display = ucfirst($contact_display);
                  }
                }

                if (empty($contact_display) && !empty($group['tirages'][0]['contact'])) {
                  $contact_display = ucfirst($group['tirages'][0]['contact']);
                }

                if (empty($date_display) && !empty($group['tirages'][0]['date'])) {
                  $date_tirage = $group['tirages'][0]['date'];
                  if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $date_tirage, $matches)) {
                    $date_display = $matches[1] . '/' . $matches[2]; // jour/mois
                  }
                }
                ?>
                <tr class="info" style="background-color: #d9edf7;">
                  <td colspan="6">
                    <strong>
                      <i class="fa fa-link"></i> <?= htmlspecialchars($contact_display) ?>
                      <?= htmlspecialchars($date_display) ?> (<?= $group['count'] ?> tirages)
                    </strong>
                    <button type="button" class="btn btn-xs btn-default pull-right" onclick="toggleGroup('<?= $groupId ?>')"
                      style="margin-left: 10px;">
                      <i class="fa fa-chevron-right" id="icon_<?= $groupId ?>"></i>
                    </button>
                    <?php if (!isset($group['all_paid']) || !$group['all_paid']): ?>
                      <div class="pull-right" style="margin-left: 10px;">
                        <label style="margin: 0; font-weight: normal; cursor: pointer; margin-right: 10px;">
                          <input type="checkbox" class="group-checkbox" data-group-id="<?= $groupId ?>"
                            data-total="<?= $group['prix_total'] ?>"
                            onchange="toggleGroupCheckboxes('<?= $groupId ?>', this.checked)">
                          Sélectionner tout
                        </label>
                        <button type="button" class="btn btn-xs btn-success"
                          onclick="markGroupAsPaid('<?= $groupId ?>', <?= $group['prix_total'] ?>, <?= $group['count'] ?>)">
                          <i class="fa fa-check"></i> Marquer comme payé (<?= number_format($group['prix_total'], 2) ?>€)
                        </button>
                      </div>
                    <?php else: ?>
                      <span class="pull-right text-success" style="margin-left: 10px;">
                        <i class="fa fa-check-circle"></i> Payé
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php }

              if ($isGroup) {
                $tiragesToShow = $group['tirages'];
              } else if (isset($group['tirages']) && is_array($group['tirages']) && count($group['tirages']) == 1) {
                $tiragesToShow = $group['tirages'];
              } else {
                $tiragesToShow = array($group);
              }

              $groupClass = $isGroup ? 'group-row ' . $groupId : '';
              $groupStyle = $isGroup ? 'background-color: #f0f8ff; display: none;' : '';
              foreach ($tiragesToShow as $tirage) {
                if (!isset($tirage['id'])) {
                  continue;
                }
                ?>
                <tr class="<?= $groupClass ?>" style="<?= $groupStyle ?>" <?= $isGroup ? 'id="row_' . $groupId . '_' . $tirage['id'] . '"' : '' ?>>
                  <td class="col-md-4"><?= htmlspecialchars($tirage['contact']) ?></td>
                  <td><?= htmlspecialchars($tirage['date']) ?></td>
                  <td><?= number_format(floatval($tirage['prix'] ?? 0), 2) ?></td>
                  <td><?= htmlspecialchars($tirage['mot'] ?? '') ?></td>
                  <td><a href="?admin&edit=<?= $tirage['id'] ?>&table=<?= $machine ?>">Edit</a></td>
                  <td><input type="checkbox" name="chkbox[]" value="<?= $tirage['prix'] ?>" data-id="<?= $tirage['id'] ?>"
                      data-machine="<?= $machine ?>" <?= $isGroup ? 'data-group-id="' . $groupId . '"' : '' ?>
                      class="<?= $isGroup ? 'group-member-checkbox' : '' ?> machine-<?= preg_replace('/[^a-zA-Z0-9]/', '_', $machine) ?>"></td>
                </tr><?php
              }
            } ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pagination && $pagination['total_pages'] > 1): ?>
          <div class="text-center">
            <ul class="pagination">
              <?php
              $baseUrl = "?admin&tirages";
              if (isset($_GET['order'])) $baseUrl .= "&order";
              $baseUrl .= "&paye=" . urlencode($_GET['paye'] ?? 'non');
              if (!empty($_GET['search'])) $baseUrl .= "&search=" . urlencode($_GET['search']);
              $pageVar = "&page_" . strtolower(str_replace(' ', '_', $machine)) . "=";
              ?>

              <?php if ($pagination['current_page'] > 1): ?>
                <li><a href="<?= $baseUrl . $pageVar . ($pagination['current_page'] - 1) ?>">&laquo; <?php _e('admin_tirage.previous'); ?></a></li>
              <?php endif; ?>

              <?php
              $start_page = max(1, $pagination['current_page'] - 2);
              $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);

              for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="<?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                  <a href="<?= $baseUrl . $pageVar . $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <li><a href="<?= $baseUrl . $pageVar . ($pagination['current_page'] + 1) ?>"><?php _e('admin_tirage.next'); ?> &raquo;</a></li>
              <?php endif; ?>

              <?php if ($pagination['total_pages'] > 5 && $pagination['current_page'] < $pagination['total_pages'] - 2): ?>
                <li class="disabled"><span>...</span></li>
                <li><a href="<?= $baseUrl . $pageVar . $pagination['total_pages'] ?>"><?= $pagination['total_pages'] ?></a></li>
              <?php endif; ?>
            </ul>
            <p class="text-muted">Page <?= $pagination['current_page'] ?><?php _e("auto_clean.admin_tirage_html_php_1", [], false); ?><?= $pagination['total_pages'] ?>
              (<?= $pagination['total_entries'] ?><?php _e("auto_clean.admin_tirage_html_php_2", [], false); ?></p>
          </div>
        <?php endif; ?>

        <button class="btn btn-primary" onclick="calculateTotal()"><?php _e('admin_tirage.calculate_total', [], false); ?></button>
        <button class="btn btn-danger" onclick="deleteSelected()" style="margin-left: 10px;"><?php _e('admin_tirage.delete_selected', [], false); ?></button>
      </div><?php } ?>
  </div>
  <div class="modal" tabindex="-1" role="dialog" id="myModal">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?php _e('admin_tirage.confirm_payment'); ?></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p><?php _e('admin_tirage.total'); ?>: <span id="total"></span> <?php _e('admin_tirage.euros'); ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"
            onclick="closeModal()"><?php _e('admin_tirage.back'); ?></button>
          <button type="button" class="btn btn-primary" onclick="pay()"><?php _e('admin_tirage.paid'); ?></button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" tabindex="-1" role="dialog" id="deleteModal">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?php _e('admin_tirage.confirm_deletion'); ?></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p><?php _e('admin_tirage.confirm_delete_prints'); ?> <span id="deleteCount"></span>
            <?php _e('admin_tirage.selected_prints'); ?></p>
          <p class="text-danger"><strong><?php _e('admin_tirage.irreversible_action'); ?></strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary"
            data-dismiss="modal"><?php _e('admin_tirage.cancel'); ?></button>
          <button type="button" class="btn btn-danger"
            onclick="confirmDelete()"><?php _e('admin_tirage.delete'); ?></button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Traductions sans balises HTML pour les popups
    const translations = {
      selectAtLeastOne: <?php echo json_encode(__('admin_tirage.select_at_least_one')); ?>,
      noPrintsSelected: <?php echo json_encode(__('admin_tirage.no_prints_selected')); ?>,
      selectAtLeastOnePay: <?php echo json_encode(__('admin_tirage.select_at_least_one_pay')); ?>,
      confirmPaymentPrints: <?php echo json_encode(__('admin_tirage.confirm_payment_prints')); ?>,
      printsForTotal: <?php echo json_encode(__('admin_tirage.prints_for_total')); ?>
    };
  </script>
  <script src="js/admin-tirage.js" defer></script>

