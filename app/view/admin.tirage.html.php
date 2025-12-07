 <?php
// Fonction pour déterminer le nom de la machine pour l'édition
function getTableForMachine($machine) {
    $db = pdo_connect();
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

            <h4><?=  $phrase ?></h4>
            
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

            <?php foreach ($machines as $machine){?>
             <div class="col-md-6">
            <h2><?=$machine?></h2><div align="right" ><?= round($prix_du[$machine] ?? 0, 2)?> euros en attente</div>
            <table class="table">
            <thead>
              
              <tr>
              <th>Contact</th><th>date</th><th>prix</th><th>commentaires</th><th>edit</th></tr></thead>
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
                    
                    // Debug: log pour comprendre combien de groupes sont affichés
                    $page_param = 'page_' . strtolower(str_replace(' ', '_', $machine));
                    $current_page = isset($_GET[$page_param]) ? intval($_GET[$page_param]) : 1;
                    $debug_msg = "DEBUG view admin.tirage: machine=$machine, page=$current_page, count(tirages)=" . count($tirages) . "\n";
                    if ($machine == 'comcolor') {
                        file_put_contents('/tmp/pagination_debug.log', date('Y-m-d H:i:s') . ' - ' . $debug_msg, FILE_APPEND);
                    }
                    
                    for($i=0; $i < count($tirages); $i++){
                      $group = $tirages[$i];
                      
                      // Debug: vérifier la structure du groupe
                      if ($machine == 'comcolor' && $current_page == 4 && $i < 3) {
                          $debug_msg = "DEBUG view: group[$i] keys=" . implode(',', array_keys($group)) . ", has tirages=" . (isset($group['tirages']) ? 'yes' : 'no') . ", tirages count=" . (isset($group['tirages']) ? count($group['tirages']) : 0) . "\n";
                          file_put_contents('/tmp/pagination_debug.log', date('Y-m-d H:i:s') . ' - ' . $debug_msg, FILE_APPEND);
                      }
                      
                      $isGroup = isset($group['tirages']) && is_array($group['tirages']) && count($group['tirages']) > 1;
                      $groupId = $isGroup ? 'group_' . htmlspecialchars($group['tirage_global_id']) . '_' . $i : '';
                      
                      // Afficher un en-tête de groupe si c'est un multi-tirage
                      if ($isGroup) { 
                        // Extraire le contact et la date du tirage_global_id
                        // Format: Y-m-d_H-i-s_contact_machine
                        $tirage_global_id = $group['tirage_global_id'];
                        $parts = explode('_', $tirage_global_id);
                        $contact_display = '';
                        $date_display = '';
                        
                        if (count($parts) >= 2) {
                          // Date est au format Y-m-d (première partie)
                          $date_part = $parts[0];
                          if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date_part, $matches)) {
                            $date_display = $matches[3] . '/' . $matches[2]; // jour/mois
                          }
                          
                          // Contact est après la date et l'heure
                          // Format: Y-m-d_H-i-s_contact_machine
                          // parts[0] = date (Y-m-d)
                          // parts[1] = heure (H-i-s)
                          // parts[2] = contact
                          // parts[3] = machine (optionnel)
                          if (count($parts) >= 3) {
                            $contact_display = $parts[2];
                            // Première lettre en majuscule
                            $contact_display = ucfirst($contact_display);
                          }
                        }
                        
                        // Si on n'a pas pu extraire, utiliser le contact du premier tirage
                        if (empty($contact_display) && !empty($group['tirages'][0]['contact'])) {
                          $contact_display = ucfirst($group['tirages'][0]['contact']);
                        }
                        
                        // Si on n'a pas pu extraire la date, utiliser la date du premier tirage
                        if (empty($date_display) && !empty($group['tirages'][0]['date'])) {
                          // La date est au format d.m.y, on doit la convertir
                          $date_tirage = $group['tirages'][0]['date'];
                          if (preg_match('/^(\d{2})\.(\d{2})\.(\d{2})$/', $date_tirage, $matches)) {
                            $date_display = $matches[1] . '/' . $matches[2]; // jour/mois
                          }
                        }
                      ?>
                        <tr class="info" style="background-color: #d9edf7;">
                          <td colspan="6">
                            <strong>
                              <i class="fa fa-link"></i> <?= htmlspecialchars($contact_display) ?> <?= htmlspecialchars($date_display) ?> (<?= $group['count'] ?> tirages)
                            </strong>
                            <button type="button" class="btn btn-xs btn-default pull-right" onclick="toggleGroup('<?= $groupId ?>')" style="margin-left: 10px;">
                              <i class="fa fa-chevron-right" id="icon_<?= $groupId ?>"></i>
                            </button>
                            <?php if (!isset($group['all_paid']) || !$group['all_paid']): ?>
                            <div class="pull-right" style="margin-left: 10px;">
                              <label style="margin: 0; font-weight: normal; cursor: pointer; margin-right: 10px;">
                                <input type="checkbox" 
                                       class="group-checkbox" 
                                       data-group-id="<?= $groupId ?>" 
                                       data-total="<?= $group['prix_total'] ?>"
                                       onchange="toggleGroupCheckboxes('<?= $groupId ?>', this.checked)">
                                Sélectionner tout
                              </label>
                              <button type="button" 
                                      class="btn btn-xs btn-success" 
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
                      
                      // Afficher chaque tirage du groupe
                      // Si c'est un groupe (multi-tirage), utiliser les tirages du groupe
                      // Sinon, si c'est un groupe avec un seul tirage, utiliser le premier tirage du groupe
                      // Sinon, traiter comme un tirage individuel (ancien format)
                      if ($isGroup) {
                          $tiragesToShow = $group['tirages'];
                      } else if (isset($group['tirages']) && is_array($group['tirages']) && count($group['tirages']) == 1) {
                          // Groupe avec un seul tirage
                          $tiragesToShow = $group['tirages'];
                      } else {
                          // Ancien format : tirage individuel
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
                       <td><input type="checkbox" 
                                  name="chkbox[]" 
                                  value="<?= $tirage['prix'] ?>" 
                                  data-id="<?= $tirage['id'] ?>" 
                                  data-machine="<?= $machine ?>"
                                  <?= $isGroup ? 'data-group-id="' . $groupId . '"' : '' ?>
                                  class="<?= $isGroup ? 'group-member-checkbox' : '' ?>"></td>

                </tr><?php
                      }
            } ?></tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($pagination && $pagination['total_pages'] > 1): ?>
            <div class="text-center">
                <ul class="pagination">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <li><a href="?admin&tirages<?= isset($_GET['order']) ? '&order' : '' ?><?= isset($_GET['paye']) ? '&paye' : '' ?>&page_<?= strtolower(str_replace(' ', '_', $machine)) ?>=<?= $pagination['current_page'] - 1 ?>">&laquo; Précédent</a></li>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $pagination['current_page'] - 2);
                    $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="<?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                            <a href="?admin&tirages<?= isset($_GET['order']) ? '&order' : '' ?><?= isset($_GET['paye']) ? '&paye' : '' ?>&page_<?= strtolower(str_replace(' ', '_', $machine)) ?>=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <li><a href="?admin&tirages<?= isset($_GET['order']) ? '&order' : '' ?><?= isset($_GET['paye']) ? '&paye' : '' ?>&page_<?= strtolower(str_replace(' ', '_', $machine)) ?>=<?= $pagination['current_page'] + 1 ?>">Suivant &raquo;</a></li>
                    <?php endif; ?>
                    
                    <?php if ($pagination['total_pages'] > 5 && $pagination['current_page'] < $pagination['total_pages'] - 2): ?>
                        <li class="disabled"><span>...</span></li>
                        <li><a href="?admin&tirages<?= isset($_GET['order']) ? '&order' : '' ?><?= isset($_GET['paye']) ? '&paye' : '' ?>&page_<?= strtolower(str_replace(' ', '_', $machine)) ?>=<?= $pagination['total_pages'] ?>"><?= $pagination['total_pages'] ?></a></li>
                    <?php endif; ?>
                </ul>
                <p class="text-muted">Page <?= $pagination['current_page'] ?> sur <?= $pagination['total_pages'] ?> (<?= $pagination['total_entries'] ?> groupes au total)</p>
            </div>
            <?php endif; ?>
            
            <button  class="btn btn-primary" onclick="calculateTotal()">Calculer total</button>
            <button class="btn btn-danger" onclick="deleteSelected()" style="margin-left: 10px;">Supprimer sélectionnés</button>
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
        <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeModal()"><?php _e('admin_tirage.back'); ?></button>
        <button type="button" class="btn btn-primary" onclick="pay()" ><?php _e('admin_tirage.paid'); ?></button>
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
        <p><?php _e('admin_tirage.confirm_delete_prints'); ?> <span id="deleteCount"></span> <?php _e('admin_tirage.selected_prints'); ?></p>
        <p class="text-danger"><strong><?php _e('admin_tirage.irreversible_action'); ?></strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('admin_tirage.cancel'); ?></button>
        <button type="button" class="btn btn-danger" onclick="confirmDelete()"><?php _e('admin_tirage.delete'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
// Variables globales pour stocker les tirages sélectionnés
let selectedTirages = [];

// Traductions sans balises HTML pour les popups
const translations = {
    selectAtLeastOne: <?php echo json_encode(__('admin_tirage.select_at_least_one')); ?>,
    noPrintsSelected: <?php echo json_encode(__('admin_tirage.no_prints_selected')); ?>,
    selectAtLeastOnePay: <?php echo json_encode(__('admin_tirage.select_at_least_one_pay')); ?>,
    confirmPaymentPrints: <?php echo json_encode(__('admin_tirage.confirm_payment_prints')); ?>,
    printsForTotal: <?php echo json_encode(__('admin_tirage.prints_for_total')); ?>
};

// Fonction utilitaire pour construire l'URL en préservant les paramètres GET
function buildActionUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const actionParams = ['admin', 'tirages'];
    
    // Préserver les paramètres importants
    if (urlParams.has('paye')) {
        actionParams.push('paye');
    }
    if (urlParams.has('order')) {
        actionParams.push('order');
    }
    
    return '?' + actionParams.join('&');
}

// Fonction pour supprimer les tirages sélectionnés
function deleteSelected() {
    selectedTirages = [];
    
    // Récupérer toutes les checkboxes cochées
    const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert(translations.selectAtLeastOne);
        return;
    }
    
    // Stocker les informations des tirages sélectionnés
    checkboxes.forEach(checkbox => {
        selectedTirages.push({
            id: checkbox.getAttribute('data-id'),
            machine: checkbox.getAttribute('data-machine')
        });
    });
    
    // Afficher le modal de confirmation
    document.getElementById('deleteCount').textContent = selectedTirages.length;
    $('#deleteModal').modal('show');
}

// Fonction pour confirmer la suppression
function confirmDelete() {
    if (selectedTirages.length === 0) {
        alert(translations.noPrintsSelected);
        return;
    }
    
    // Créer un formulaire pour envoyer les données
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = buildActionUrl();
    
    // Ajouter les tirages à supprimer
    selectedTirages.forEach((tirage, index) => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'delete_ids[]';
        idInput.value = tirage.id;
        form.appendChild(idInput);
        
        const machineInput = document.createElement('input');
        machineInput.type = 'hidden';
        machineInput.name = 'delete_machines[]';
        machineInput.value = tirage.machine;
        form.appendChild(machineInput);
    });
    
    // Ajouter un champ pour indiquer que c'est une suppression
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete_selected';
    form.appendChild(actionInput);
    
    // Soumettre le formulaire
    document.body.appendChild(form);
    form.submit();
}

// Fonction existante pour calculer le total (si elle n'existe pas déjà)
function calculateTotal() {
    let total = 0;
    const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');
    
    checkboxes.forEach(checkbox => {
        total += parseFloat(checkbox.value) || 0;
    });
    
    document.getElementById('total').textContent = total.toFixed(2);
    $('#myModal').modal('show');
}

// Fonction pour marquer les tirages sélectionnés comme payés
function pay() {
    // Récupérer toutes les checkboxes cochées
    const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert(translations.selectAtLeastOnePay);
        return;
    }
    
    // Collecter les informations des tirages sélectionnés
    const selectedTirages = [];
    checkboxes.forEach(checkbox => {
        selectedTirages.push({
            id: checkbox.getAttribute('data-id'),
            machine: checkbox.getAttribute('data-machine'),
            prix: checkbox.value
        });
    });
    
    // Confirmer le paiement
    const total = selectedTirages.reduce((sum, tirage) => sum + parseFloat(tirage.prix), 0);
    if (!confirm(`${translations.confirmPaymentPrints} ${selectedTirages.length} ${translations.printsForTotal} ${total.toFixed(2)}€ ?`)) {
        return;
    }
    
    // Envoyer la requête de paiement
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = buildActionUrl();
    
    // Ajouter les tirages à marquer comme payés
    selectedTirages.forEach((tirage, index) => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'pay_ids[]';
        idInput.value = tirage.id;
        form.appendChild(idInput);
        
        const machineInput = document.createElement('input');
        machineInput.type = 'hidden';
        machineInput.name = 'pay_machines[]';
        machineInput.value = tirage.machine;
        form.appendChild(machineInput);
    });
    
    // Ajouter un champ pour indiquer que c'est un paiement
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'mark_as_paid';
    form.appendChild(actionInput);
    
    // Soumettre le formulaire
    document.body.appendChild(form);
    form.submit();
}

// Fonction existante pour fermer le modal (si elle n'existe pas déjà)
function closeModal() {
    $('#myModal').modal('hide');
}

// Fonction pour développer/réduire un groupe de multi-tirages
function toggleGroup(groupId) {
    const rows = document.querySelectorAll('tr.group-row.' + groupId);
    const icon = document.getElementById('icon_' + groupId);
    
    if (rows.length === 0) return;
    
    // Vérifier si le groupe est actuellement visible
    // Par défaut les groupes sont repliés (display: none)
    const firstRow = rows[0];
    const currentDisplay = window.getComputedStyle(firstRow).display;
    const isVisible = currentDisplay !== 'none';
    
    // Basculer la visibilité
    rows.forEach(row => {
        row.style.display = isVisible ? 'none' : '';
    });
    
    // Changer l'icône (chevron-right = replié, chevron-down = développé)
    if (icon) {
        icon.className = isVisible ? 'fa fa-chevron-right' : 'fa fa-chevron-down';
    }
}

// Fonction pour sélectionner/désélectionner tous les tirages d'un groupe
function toggleGroupCheckboxes(groupId, checked) {
    const checkboxes = document.querySelectorAll('input.group-member-checkbox[data-group-id="' + groupId + '"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
}

// Fonction pour marquer tout un groupe de multi-tirages comme payé
function markGroupAsPaid(groupId, total, count) {
    const checkboxes = document.querySelectorAll('input.group-member-checkbox[data-group-id="' + groupId + '"]');
    
    if (checkboxes.length === 0) {
        alert('Aucun tirage trouvé dans ce groupe');
        return;
    }
    
    // Confirmer le paiement pour tout le groupe
    if (!confirm(`Marquer ${count} tirages du multi-tirage comme payés pour un total de ${total.toFixed(2)}€ ?`)) {
        return;
    }
    
    // Créer un formulaire pour envoyer les données
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = buildActionUrl();
    
    // Ajouter tous les tirages du groupe
    checkboxes.forEach(checkbox => {
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'pay_ids[]';
        idInput.value = checkbox.getAttribute('data-id');
        form.appendChild(idInput);
        
        const machineInput = document.createElement('input');
        machineInput.type = 'hidden';
        machineInput.name = 'pay_machines[]';
        machineInput.value = checkbox.getAttribute('data-machine');
        form.appendChild(machineInput);
    });
    
    // Ajouter un champ pour indiquer que c'est un paiement
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'mark_as_paid';
    form.appendChild(actionInput);
    
    // Soumettre le formulaire
    document.body.appendChild(form);
    form.submit();
}

// Écouter les changements sur les checkboxes individuelles pour mettre à jour la checkbox du groupe
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter des écouteurs sur toutes les checkboxes de groupe
    const groupCheckboxes = document.querySelectorAll('input.group-member-checkbox');
    groupCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const groupId = this.getAttribute('data-group-id');
            if (!groupId) return;
            
            const groupCheckbox = document.querySelector('input.group-checkbox[data-group-id="' + groupId + '"]');
            if (!groupCheckbox) return;
            
            // Vérifier si toutes les checkboxes du groupe sont cochées
            const allCheckboxes = document.querySelectorAll('input.group-member-checkbox[data-group-id="' + groupId + '"]');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(allCheckboxes).some(cb => cb.checked);
            
            // Mettre à jour la checkbox du groupe (indéterminé si partiellement sélectionné)
            groupCheckbox.checked = allChecked;
            groupCheckbox.indeterminate = someChecked && !allChecked;
        });
    });
    
    // Par défaut, tous les groupes sont développés (visibles)
    // On peut ajouter une logique pour restaurer l'état si nécessaire
});
</script>
