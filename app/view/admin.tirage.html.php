 <?php
// Fonction pour déterminer le nom de la machine pour l'édition
function getTableForMachine($machine) {
    $db = pdo_connect();
    $db = pdo_connect();
    
    // Vérifier si c'est un duplicopieur
    $query = $db->prepare('SELECT COUNT(*) FROM duplicopieurs WHERE actif = 1 AND (CONCAT(marque, " ", modele) = ? OR (marque = ? AND modele = ?))');
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
            <h1>Derniers Tirages </h1>

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
                    
                    for($i=0; $i < count($tirages); $i++){
                      //print_array($last[$machine][0]);?>
                    <tr>
                      <td class="col-md-4"><?= $tirages[$i]['contact'] ?></td>
                      <td><?= $tirages[$i]['date'] ?></td>
                      <td><?= $tirages[$i]['prix'] ?></td> 

                      <td><?= $tirages[$i]['mot'] ?></td>  
                      <td><a href="?admin&edit=<?= $tirages[$i]['id'] ?>&table=<?= $machine ?>">Edit</a></td>
                       <!--<td><input type="checkbox" name="chkbox[]" value="<?= $last[$machine][$i]['prix'] ?>"></td>-->
                       <td><input type="checkbox" name="chkbox[]" value="<?= $tirages[$i]['prix'] ?>" data-id="<?= $tirages[$i]['id'] ?>" data-machine="<?= $machine ?>" ></td>
                     <!-- <input type="hidden" name="id[]" value="<?= $last[$machine][$i]['id'] ?>">-->

                </tr><?php
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
                </ul>
                <p class="text-muted">Page <?= $pagination['current_page'] ?> sur <?= $pagination['total_pages'] ?> (<?= $pagination['total_entries'] ?> tirages au total)</p>
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
        <h5 class="modal-title">Confirmer le paiement</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Total: <span id="total"></span> euros</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeModal()">Retour</button>
        <button type="button" class="btn btn-primary" onclick="pay()" >Payé</button>
      </div>
    </div>
  </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="deleteModal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmer la suppression</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Êtes-vous sûr de vouloir supprimer <span id="deleteCount"></span> tirage(s) sélectionné(s) ?</p>
        <p class="text-danger"><strong>Cette action est irréversible !</strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-danger" onclick="confirmDelete()">Supprimer</button>
      </div>
    </div>
  </div>
</div>

<script>
// Variables globales pour stocker les tirages sélectionnés
let selectedTirages = [];

// Fonction pour supprimer les tirages sélectionnés
function deleteSelected() {
    selectedTirages = [];
    
    // Récupérer toutes les checkboxes cochées
    const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('Veuillez sélectionner au moins un tirage à supprimer.');
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
        alert('Aucun tirage sélectionné.');
        return;
    }
    
    // Créer un formulaire pour envoyer les données
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?admin&tirages';
    
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
        alert('Veuillez sélectionner au moins un tirage à marquer comme payé.');
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
    if (!confirm(`Confirmer le paiement de ${selectedTirages.length} tirage(s) pour un total de ${total.toFixed(2)}€ ?`)) {
        return;
    }
    
    // Envoyer la requête de paiement
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?admin&tirages';
    
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
</script>
