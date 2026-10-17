// ============================================================
// admin-tirage.js -- Gestion des tirages (Admin)
// Extrait de admin.tirage.html.php
// ============================================================

/* global translations, showAppModal, $ */

// Variables globales pour stocker les tirages sélectionnés
let selectedTirages = [];

window.toggleSelectMachine = function (machine, master) {
    const machineClass = '.machine-' + machine.replace(/[^a-zA-Z0-9]/g, '_');
    const checkboxes = document.querySelectorAll(machineClass);
    checkboxes.forEach(cb => cb.checked = master.checked);
};

// Fonction utilitaire pour construire l'URL en préservant les paramètres GET
function buildActionUrl() {
  const urlParams = new URLSearchParams(window.location.search);
  const actionParams = ['admin', 'tirages'];

  // Préserver les paramètres importants
  if (urlParams.has('paye')) {
    actionParams.push('paye=' + encodeURIComponent(urlParams.get('paye')));
  }
  if (urlParams.has('order')) {
    actionParams.push('order');
  }
  if (urlParams.has('search')) {
    actionParams.push('search=' + encodeURIComponent(urlParams.get('search')));
  }

  return '?' + actionParams.join('&');
}

// Fonction pour supprimer les tirages sélectionnés
window.deleteSelected = function () {
  selectedTirages = [];

  // Récupérer toutes les checkboxes cochées
  const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');

  if (checkboxes.length === 0) {
    showAppModal({
      type: 'warning',
      message: translations.selectAtLeastOne
    });
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
};

// Fonction pour confirmer la suppression
window.confirmDelete = function () {
  if (selectedTirages.length === 0) {
    showAppModal({
      type: 'warning',
      message: translations.noPrintsSelected
    });
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
};

// Fonction existante pour calculer le total (si elle n'existe pas déjà)
window.calculateTotal = function () {
  let total = 0;
  const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');

  checkboxes.forEach(checkbox => {
    total += parseFloat(checkbox.value) || 0;
  });

  document.getElementById('total').textContent = total.toFixed(2);
  $('#myModal').modal('show');
};

// Fonction pour marquer les tirages sélectionnés comme payés
window.pay = function () {
  // Récupérer toutes les checkboxes cochées
  const checkboxes = document.querySelectorAll('input[name="chkbox[]"]:checked');

  if (checkboxes.length === 0) {
    showAppModal({
      type: 'warning',
      message: translations.selectAtLeastOnePay
    });
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

  showAppModal({
    title: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_tirage.confirmer_le_paiement'] || 'Confirmer le paiement'),
    message: `${translations.confirmPaymentPrints} ${selectedTirages.length} ${translations.printsForTotal} ${total.toFixed(2)}€ ?`,
    confirm: true,
    onConfirm: function () {
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
  });
};

// Fonction existante pour fermer le modal (si elle n'existe pas déjà)
window.closeModal = function () {
  $('#myModal').modal('hide');
};

// Fonction pour développer/réduire un groupe de multi-tirages
window.toggleGroup = function (groupId) {
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
};

// Fonction pour sélectionner/désélectionner tous les tirages d'un groupe
window.toggleGroupCheckboxes = function (groupId, checked) {
  const checkboxes = document.querySelectorAll('input.group-member-checkbox[data-group-id="' + groupId + '"]');
  checkboxes.forEach(checkbox => {
    checkbox.checked = checked;
  });
};

// Fonction pour marquer tout un groupe de multi-tirages comme payé
window.markGroupAsPaid = function (groupId, total, count) {
  const checkboxes = document.querySelectorAll('input.group-member-checkbox[data-group-id="' + groupId + '"]');

  if (checkboxes.length === 0) {
    showAppModal({
      type: 'warning',
      message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_tirage.aucun_tirage_trouv__dans_ce_gr'] || 'Aucun tirage trouvé dans ce groupe')
    });
    return;
  }

  // Confirmer le paiement pour tout le groupe
  showAppModal({
    title: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_tirage.confirmer_le_paiement_du_group'] || 'Confirmer le paiement du groupe'),
    message: `Marquer ${count} tirages du multi-tirage comme payés pour un total de ${total.toFixed(2)}€ ?`,
    confirm: true,
    onConfirm: function () {
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
  });
};

// Fonction pour sélectionner/désélectionner absolument tout sur la page
window.toggleAllGlobal = function (checked) {
  // Sélectionner toutes les checkboxes de tirages individuels
  const individualCheckboxes = document.querySelectorAll('input[name="chkbox[]"]');
  individualCheckboxes.forEach(cb => {
    cb.checked = checked;
  });

  // Sélectionner toutes les checkboxes de groupe
  const groupCheckboxes = document.querySelectorAll('input.group-checkbox');
  groupCheckboxes.forEach(cb => {
    cb.checked = checked;
    cb.indeterminate = false;
  });
};

// Écouter les changements sur les checkboxes individuelles pour mettre à jour la checkbox du groupe et la globale
document.addEventListener('DOMContentLoaded', function () {
  const globalCheckbox = document.getElementById('selectAllGlobal');

  // Fonction pour mettre à jour l'état de la checkbox globale
  function updateGlobalCheckboxStatus() {
    if (!globalCheckbox) return;

    const allCheckboxes = document.querySelectorAll('input[name="chkbox[]"]');
    if (allCheckboxes.length === 0) return;

    const checkedCount = Array.from(allCheckboxes).filter(cb => cb.checked).length;

    globalCheckbox.checked = checkedCount === allCheckboxes.length;
    globalCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
  }

  // Ajouter des écouteurs sur toutes les checkboxes individuelles
  const allCheckboxes = document.querySelectorAll('input[name="chkbox[]"], input.group-checkbox');
  allCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', updateGlobalCheckboxStatus);
  });
  // Ajouter des écouteurs sur toutes les checkboxes de groupe
  const groupCheckboxes = document.querySelectorAll('input.group-member-checkbox');
  groupCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function () {
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
});
