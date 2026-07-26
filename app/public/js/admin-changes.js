// ============================================================
// admin-changes.js — Gestion des changements (historique)
// Extrait de admin.changes.html.php
// ============================================================

/* global CONFIG, showAppModal, $ */

$(document).ready(function () {
  var photocopiers = CONFIG.photocopiers;
  var duplicopieurs_tambours = CONFIG.duplicopieurs_tambours;

  function isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
  }

  function updateTypeOptions(machine, selectElement) {
    $.get('?admin&ajax=get_machine_type&machine=' + encodeURIComponent(machine))
      .done(function (response) {
        if (response.success) {
          var type = response.type;
          var options = '';

          if (type === 'duplicopieur') {
            options = '<option value="master">Master</option>' +
              '<option value="encre">Encre</option>';
          } else if (type === 'photocop_encre') {
            options = '<option value="noir">Noir</option>' +
              '<option value="cyan">Cyan</option>' +
              '<option value="magenta">Magenta</option>' +
              '<option value="yellow">Yellow</option>';
          } else if (type === 'photocop_toner') {
            options = '<option value="noir">Noir</option>' +
              '<option value="cyan">Cyan</option>' +
              '<option value="magenta">Magenta</option>' +
              '<option value="yellow">Yellow</option>' +
              '<option value="dev">Dev</option>' +
              '<option value="tambour">Tambour</option>';
          } else {
            options = '<option value="master">Master</option>';
          }

          const selectTypeTxt = (typeof CONFIG !== 'undefined' && CONFIG.translations && CONFIG.translations.select_type) ? CONFIG.translations.select_type : 'Sélectionner un type';
          selectElement.html('<option value="">' + selectTypeTxt + '</option>' + options);
        }
      });
  }

  function updateCounters(machine) {
    var isPhotocopier = photocopiers.includes(machine);

    if (isPhotocopier) {
      $.get('?admin&ajax=get_last_counters&machine=' + encodeURIComponent(machine))
        .done(function (response) {
          if (response.success) {
            $('#nb_p').val(response.counters.passage_av);
            $('#nb_m').val(response.counters.master_av);
          }
        });
    } else {
      $('#nb_p').val('');
      $('#nb_m').val('');
    }
  }

  function toggleQuantityFields(machine) {
    var isPhotocopier = photocopiers.includes(machine);

    if (isPhotocopier) {
      $('#nb_m').show().prev('label').show();
      $('#tambour').hide().prev('label').hide();
    } else {
      $('#nb_m').hide().prev('label').hide();
      $('#tambour').show().prev('label').show();

      if (duplicopieurs_tambours[machine]) {
        var tambourSelect = $('#tambour');
        tambourSelect.html('<option value="">Sélectionner un tambour</option>');
        $.each(duplicopieurs_tambours[machine], function (index, tambour) {
          tambourSelect.append('<option value="' + tambour + '">' + tambour + '</option>');
        });
      }
    }
  }

  function toggleTambourField(type, machine) {
    var tambourField = $('#tambour');
    var tambourLabel = tambourField.prev('label');

    if (type === 'master') {
      tambourField.hide();
      tambourLabel.hide();
      tambourField.prop('required', false);
    } else if (type === 'encre' || type === 'tambour') {
      tambourField.show();
      tambourLabel.show();
      tambourField.prop('required', true);

      if (duplicopieurs_tambours[machine]) {
        tambourField.html('<option value="">Sélectionner un tambour</option>');
        $.each(duplicopieurs_tambours[machine], function (index, tambour) {
          tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
        });
      }
    } else {
      tambourField.hide();
      tambourLabel.hide();
      tambourField.prop('required', false);
    }
  }

  $('#machine').change(function () {
    var machine = $(this).val();
    updateTypeOptions(machine, $('#type'));
    updateCounters(machine);
    toggleQuantityFields(machine);
  });

  $('#type').change(function () {
    var type = $(this).val();
    var machine = $('#machine').val();
    toggleTambourField(type, machine);
  });

  // --- Edit modal ---
  function toggleEditTambourField(type, machine) {
    var tambourField = $('#edit_tambour');
    var tambourLabel = tambourField.prev('label');

    if (type === 'master') {
      tambourField.hide();
      tambourLabel.hide();
      tambourField.prop('required', false);
    } else if (type === 'encre' || type === 'tambour') {
      tambourField.show();
      tambourLabel.show();
      tambourField.prop('required', true);

      if (duplicopieurs_tambours[machine]) {
        tambourField.html('<option value="">Sélectionner un tambour</option>');
        $.each(duplicopieurs_tambours[machine], function (index, tambour) {
          tambourField.append('<option value="' + tambour + '">' + tambour + '</option>');
        });
      }
    } else {
      tambourField.hide();
      tambourLabel.hide();
      tambourField.prop('required', false);
    }
  }

  function updateEditTypeOptions(machine) {
    return new Promise(function (resolve) {
      $.get('?admin&ajax=get_machine_type&machine=' + encodeURIComponent(machine))
        .done(function (response) {
          if (typeof response === 'string') {
            try { response = JSON.parse(response); } catch (e) { resolve(); return; }
          }

          if (response.success) {
            var type = response.type;
            var options = '';

            if (type === 'duplicopieur') {
              options = '<option value="master">Master</option>' +
                '<option value="encre">Encre</option>';
            } else if (type === 'photocop_encre') {
              options = '<option value="noir">Noir</option>' +
                '<option value="cyan">Cyan</option>' +
                '<option value="magenta">Magenta</option>' +
                '<option value="yellow">Yellow</option>';
            } else if (type === 'photocop_toner') {
              options = '<option value="noir">Noir</option>' +
                '<option value="cyan">Cyan</option>' +
                '<option value="magenta">Magenta</option>' +
                '<option value="yellow">Yellow</option>' +
                '<option value="dev">Dev</option>' +
                '<option value="tambour">Tambour</option>';
            } else {
              options = '<option value="master">Master</option>';
            }

            $('#edit_type').html('<option value="">Sélectionner un type</option>' + options);
          }
          resolve();
        })
        .fail(function () { resolve(); });
    });
  }

  $('.edit-change').click(function () {
    var id = $(this).data('id');

    $.get('?admin&ajax=get_change&id=' + id)
      .done(function (response) {
        if (typeof response === 'string') {
          try { response = JSON.parse(response); } catch (e) {
            showAppModal({ type: 'danger', message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_changes.erreur_lors_du_chargement_du_c'] || 'Erreur lors du chargement du changement') });
            return;
          }
        }

        if (response.success) {
          var change = response.change;
          $('#edit_id').val(change.id);
          $('#edit_machine').val(change.machine);
          var editDate = isNumeric(change.date)
            ? new Date(change.date * 1000).toISOString().split('T')[0]
            : change.date.split(' ')[0];
          $('#edit_date').val(editDate);
          $('#edit_nb_p').val(change.nb_p);
          $('#edit_nb_m').val(change.nb_m);
          $('#edit_tambour').val(change.tambour || '');

          updateEditTypeOptions(change.machine).then(function () {
            var currentType = change.type;
            var typeSelect = $('#edit_type');
            var availableOptions = typeSelect.find('option').map(function () { return $(this).val(); }).get();

            if (availableOptions.includes(currentType)) {
              typeSelect.val(currentType);
            } else {
              typeSelect.val(availableOptions[1] || '');
            }

            var selectedType = typeSelect.val();
            toggleEditTambourField(selectedType, change.machine);
            $('#edit_tambour').val(change.tambour || '');
            $('#editModal').modal('show');
          });
        } else {
          showAppModal({ type: 'danger', message: 'Erreur: ' + (response.error || (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_changes.erreur_inconnue'] || 'Erreur inconnue')) });
        }
      })
      .fail(function (xhr, status, error) {
        showAppModal({ type: 'danger', message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_changes.erreur_lors_du_chargement'] || 'Erreur lors du chargement: ') + error });
      });
  });

  $('#save-edit').click(function () {
    var formData = {
      action: 'edit_change',
      id: $('#edit_id').val(),
      machine: $('#edit_machine').val(),
      type: $('#edit_type').val(),
      date: $('#edit_date').val(),
      nb_p: $('#edit_nb_p').val(),
      nb_m: $('#edit_nb_m').val(),
      tambour: $('#edit_tambour').val()
    };

    $.post('?admin&changes', formData)
      .done(function () { location.reload(); })
      .fail(function (xhr) {
        showAppModal({ type: 'danger', message: 'Erreur: ' + xhr.responseText });
      });
  });

  $('.delete-change').click(function () {
    var id = $(this).data('id');
    showAppModal({
      type: 'warning',
      title: 'Confirmation de suppression',
      message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_changes.tes_vous_s_r_de_vouloir_suppr'] || 'Êtes-vous sûr de vouloir supprimer ce changement ?'),
      confirm: true,
      onConfirm: function () {
        $.post('?admin&changes', { action: 'delete_change', id: id })
          .done(function () { location.reload(); });
      }
    });
  });

  $('#add-change-form').submit(function (e) {
    e.preventDefault();

    $.post('?admin&changes', $(this).serialize())
      .done(function () { location.reload(); })
      .fail(function (xhr) {
        showAppModal({ type: 'danger', message: 'Erreur: ' + xhr.responseText });
      });
  });
});
