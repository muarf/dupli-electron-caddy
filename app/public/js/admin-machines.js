// ============================================================
// admin-machines.js — Gestion des machines (admin)
// Extrait de admin.machines.html.php
// ============================================================

/* global CONFIG, showAppModal, $ */

$(document).ready(function () {
  // Affichage des champs selon le type de photocopieur
  $('#photocop_type').change(function () {
    var selectedType = $(this).val();
    if (selectedType === 'photocop_toner') {
      $('#toner_fields').show();
      $('#encre_fields').hide();
    } else if (selectedType === 'photocop_encre') {
      $('#encre_fields').show();
      $('#toner_fields').hide();
    } else {
      $('#toner_fields').hide();
      $('#encre_fields').hide();
    }
  });

  // Suppression de machines
  $('.delete-machine').click(function () {
    var machineId = $(this).data('id');
    var machineType = $(this).data('type');
    var machineName = $(this).data('name');
    var $btn = $(this);

    showAppModal({
      type: 'danger',
      title: CONFIG.translations.delete_confirm_title,
      message: CONFIG.translations.delete_confirm_msg.replace(':name', machineName),
      confirm: true,
      onConfirm: function () {
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + CONFIG.translations.deleting);

        $.ajax({
          url: '?ajax_delete_machine',
          type: 'POST',
          data: { machine_id: machineId, machine_type: machineType },
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              showAppModal({
                type: 'success',
                message: response.success,
                onClose: function () { location.reload(); }
              });
            } else {
              showAppModal({ type: 'danger', message: 'Erreur : ' + response.error });
              $('.delete-machine[data-id="' + machineId + '"]').prop('disabled', false).html('<i class="fa fa-trash"></i> ' + CONFIG.translations.delete);
            }
          },
          error: function () {
            showAppModal({ type: 'danger', message: 'Erreur lors de la suppression de la machine. Vérifiez la console pour plus de détails.' });
            $('.delete-machine[data-id="' + machineId + '"]').prop('disabled', false).html('<i class="fa fa-trash"></i> ' + CONFIG.translations.delete);
          }
        });
      }
    });
  });

  // Tambours — ajout/suppression dans le formulaire principal
  $('#add-tambour').click(function () {
    var tambourHtml =
      '<div class="tambour-item" style="margin-bottom: 10px;">' +
      '<div class="row">' +
      '<div class="col-md-4"><input type="text" class="form-control" name="tambours[]" placeholder="ex: tambour_bleu" required></div>' +
      '<div class="col-md-3"><input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="' + CONFIG.translations.unit_price + '" step="0.001" min="0" required></div>' +
      '<div class="col-md-3"><input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="' + CONFIG.translations.price_pack + '" step="0.01" min="0" value="11"></div>' +
      '<div class="col-md-2"><button type="button" class="btn btn-danger btn-sm remove-tambour"><i class="fa fa-trash"></i></button></div>' +
      '</div></div>';
    $('#tambours-container').append(tambourHtml);
    updateRemoveButtons();
  });

  $(document).on('click', '.remove-tambour', function () {
    $(this).closest('.tambour-item').remove();
    updateRemoveButtons();
  });

  function updateRemoveButtons() {
    var count = $('.tambour-item').length;
    if (count > 1) { $('.remove-tambour').show(); } else { $('.remove-tambour').hide(); }
  }

  updateRemoveButtons();
});

// --- Édition des tambours (modal) ---
$('.edit-tambours').click(function () {
  var machineId = $(this).data('id');
  var machineName = $(this).data('name');
  var tamboursData = $(this).data('tambours');

  var tambours = [];
  if (Array.isArray(tamboursData)) {
    tambours = tamboursData;
  } else {
    try { tambours = JSON.parse(tamboursData); } catch (e) { tambours = ['tambour_noir']; }
  }

  $('#edit-tambours-modal .modal-title').text(CONFIG.translations.edit_tambours + ' - ' + machineName);
  $('#edit-tambours-modal').data('machine-id', machineId);
  $('#edit-tambours-container').empty();

  $.ajax({
    url: '?ajax_get_tambour_prices',
    type: 'POST',
    data: { machine_id: machineId },
    dataType: 'text',
    success: function (response) {
      try {
        var jsonMatch = response.match(/\{.*\}/);
        if (jsonMatch) { response = JSON.parse(jsonMatch[0]); } else { return; }
      } catch (e) { return; }

      if (response.success && response.prices) {
        tambours.forEach(function (tambour) {
          var prix = response.prices[tambour] || { unite: 0.002, pack: 0 };
          addEditTambourItem(tambour, prix.unite, prix.pack);
        });
        if (tambours.length === 0) { addEditTambourItem('tambour_noir', 0.002, 0); }
      } else {
        tambours.forEach(function (tambour) { addEditTambourItem(tambour, 0.002, 0); });
        if (tambours.length === 0) { addEditTambourItem('tambour_noir', 0.002, 0); }
      }

      updateEditRemoveButtons();
      $('#edit-tambours-modal').modal('show');
    },
    error: function () {
      tambours.forEach(function (tambour) { addEditTambourItem(tambour, 0.002, 0); });
      if (tambours.length === 0) { addEditTambourItem('tambour_noir', 0.002, 0); }
      updateEditRemoveButtons();
      $('#edit-tambours-modal').modal('show');
    }
  });
});

function addEditTambourItem(tambourName, prixUnite, prixPack) {
  prixUnite = prixUnite || 0.002;
  prixPack = prixPack || 0;

  var html =
    '<div class="edit-tambour-item" style="margin-bottom: 10px;">' +
    '<div class="row">' +
    '<div class="col-md-5"><input type="text" class="form-control" name="edit_tambours[]" placeholder="ex: tambour_noir" value="' + tambourName + '" required></div>' +
    '<div class="col-md-3"><input type="number" class="form-control" name="edit_prix_tambour_unite[]" placeholder="' + CONFIG.translations.unit_price + '" step="0.001" min="0" value="' + prixUnite + '" required></div>' +
    '<div class="col-md-3"><input type="number" class="form-control" name="edit_prix_tambour_pack[]" placeholder="' + CONFIG.translations.price_pack + '" step="0.01" min="0" value="' + prixPack + '"></div>' +
    '<div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-edit-tambour"><i class="fa fa-trash"></i></button></div>' +
    '</div></div>';
  $('#edit-tambours-container').append(html);
}

$(document).on('click', '#add-edit-tambour', function () {
  addEditTambourItem('', 0.002, 0);
  updateEditRemoveButtons();
});

$(document).on('click', '.remove-edit-tambour', function () {
  $(this).closest('.edit-tambour-item').remove();
  updateEditRemoveButtons();
});

function updateEditRemoveButtons() {
  var count = $('.edit-tambour-item').length;
  if (count > 1) { $('.remove-edit-tambour').show(); } else { $('.remove-edit-tambour').hide(); }
}

// Soumission du formulaire d'édition des tambours
$(document).on('submit', '#edit-tambours-form', function (e) {
  e.preventDefault();

  var machineId = $('#edit-tambours-modal').data('machine-id');
  var tambours = [];
  var prixUnite = [];
  var prixPack = [];

  $('.edit-tambour-item').each(function () {
    var name = $(this).find('input[name="edit_tambours[]"]').val();
    var unite = $(this).find('input[name="edit_prix_tambour_unite[]"]').val();
    var pack = $(this).find('input[name="edit_prix_tambour_pack[]"]').val();

    if (name && unite) {
      tambours.push(name);
      prixUnite.push(unite);
      prixPack.push(pack || 0);
    }
  });

  if (tambours.length === 0) {
    showAppModal('Veuillez définir au moins un tambour.');
    return;
  }

  $.ajax({
    url: '?ajax_edit_tambours',
    type: 'POST',
    data: {
      machine_id: machineId,
      tambours: tambours,
      prix_tambour_unite: prixUnite,
      prix_tambour_pack: prixPack
    },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        showAppModal({
          type: 'success',
          message: response.success,
          onClose: function () { location.reload(); }
        });
      } else {
        showAppModal({ type: 'danger', message: 'Erreur: ' + (response.error || 'Erreur inconnue') });
      }
    },
    error: function () {
      showAppModal({ type: 'danger', message: 'Erreur lors de la sauvegarde des tambours.' });
    }
  });
});
