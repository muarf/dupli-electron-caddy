// ============================================================
// setup.js -- Configuration setup pour Dupli
// Extrait de setup.html.php
// ============================================================

/* global CONFIG, showAppModal, $ */

$(document).ready(function () {
  let machines = [];
  let machineCounter = 0;
  let selectedPrinter = null;
  let systemPrinters = [];

  // Charger les imprimantes système au démarrage
  fetchSystemPrinters();

  async function fetchSystemPrinters() {
    if (window.electronAPI && window.electronAPI.getPrinters) {
      try {
        const response = await window.electronAPI.getPrinters();
        if (response && response.success && Array.isArray(response.printers)) {
          systemPrinters = response.printers;
          displaySystemPrinters();
        }
      } catch (err) {
        console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.setup.erreur_r_cup_ration_imprimante'] || 'Erreur récupération imprimantes:'), err);
      }
    }
  }

  function displaySystemPrinters() {
    $('#loading-printers').hide();
    let html = '';

    if (systemPrinters.length === 0) {
      html = '<div class="alert alert-info">Aucune imprimante système détectée.</div>';
    } else {
      systemPrinters.forEach(printer => {
        const isMapped = machines.some(m => m.systemPrinterName === printer.name);
        html += `
          <div class="printer-card ${isMapped ? 'active' : ''}" id="printer-${printer.name.replace(/\s+/g, '_')}">
            <div class="printer-info">
              <div class="printer-icon"><i class="fa fa-print"></i></div>
              <div>
                <div class="printer-name">${printer.name}</div>
                <div class="printer-status">${printer.description || CONFIG.translations.configure}</div>
                ${isMapped ? `<span class="machine-badge badge-dupli"><i class="fa fa-check"></i> ${CONFIG.translations.already_configured}</span>` : ''}
              </div>
            </div>
            <div>
              <button type="button" class="btn btn-primary btn-sm" onclick="selectPrinter('${printer.name}')">
                ${isMapped ? CONFIG.translations.reconfigure : CONFIG.translations.configure}
              </button>
            </div>
          </div>
        `;
      });
    }

    $('#printers-container').html(html);
  }

  window.selectPrinter = function (printerName) {
    selectedPrinter = printerName;
    $('#selected-printer-name').text(printerName);
    $('#type-selector-section').fadeIn();
    $('#machine-form').hide();
    $('input[name="machine_type"]').prop('checked', false);

    $('html, body').animate({
      scrollTop: $("#type-selector-section").offset().top - 100
    }, 500);

    $('#machine_name').val(printerName);
  };

  $('#add-manual-btn').click(function () {
    selectedPrinter = null;
    $('#selected-printer-name').text(CONFIG.translations.manual);
    $('#type-selector-section').fadeIn();
    $('#machine-form').hide();
    $('input[name="machine_type"]').prop('checked', false);
    $('#machine_name').val('');
  });

  $('input[name="machine_type"]').change(function () {
    const type = $(this).val();
    showMachineForm(type);
  });

  function showMachineForm(type) {
    $('#machine-form').show();
    $('#duplicopieur-prices, #photocop-encre-prices, #photocop-toner-prices').hide();

    if (type === 'duplicopieur') {
      $('#machine-title').text(CONFIG.translations.duplicator_config_title);
      $('#duplicopieur-prices').show();
      $('#master-counter-field').show();
      $('#master_counter').prop('required', true);
    } else if (type === 'photocop_encre') {
      $('#machine-title').text(CONFIG.translations.photocopier_ink_config_title);
      $('#photocop-encre-prices').show();
      $('#master-counter-field').hide();
      $('#master_counter').prop('required', false);
    } else if (type === 'photocop_toner') {
      $('#machine-title').text(CONFIG.translations.photocopier_toner_config_title);
      $('#photocop-toner-prices').show();
      $('#master-counter-field').hide();
      $('#master_counter').prop('required', false);
    }

    $('html, body').animate({
      scrollTop: $("#machine-form").offset().top - 50
    }, 500);
  }

  $('#add-machine-btn').click(function () {
    const type = $('input[name="machine_type"]:checked').val();
    const name = $('#machine_name').val();
    const masterCounter = $('#master_counter').val();
    const passageCounter = $('#passage_counter').val();

    if (!name || !passageCounter) {
      let missing = [];
      if (!name) missing.push(CONFIG.translations.machine_name);
      if (!passageCounter) missing.push(CONFIG.translations.passage_counter);
      showAppModal({
        title: CONFIG.translations.missing_fields,
        message: CONFIG.translations.missing_fields_msg + '<br>• ' + missing.join('<br>• '),
        type: 'warning'
      });
      return;
    }

    if (type === 'duplicopieur' && !masterCounter) {
      showAppModal({
        title: CONFIG.translations.missing_fields,
        message: CONFIG.translations.missing_master_msg,
        type: 'warning'
      });
      return;
    }

    const machine = {
      id: machineCounter++,
      type: type,
      name: name,
      systemPrinterName: selectedPrinter,
      masterCounter: masterCounter || 0,
      passageCounter: passageCounter,
      tambours: getTambours(),
      prices: getPricesForType(type)
    };

    machines.push(machine);
    updateMachinesList();
    clearForm();
    updateSubmitButton();
    displaySystemPrinters();

    $('html, body').animate({
      scrollTop: $("#machines-list").offset().top - 100
    }, 500);
  });

  function getTambours() {
    const tambours = [];
    const tambourNames = $('input[name="tambours[]"]');
    const tambourUnite = $('input[name="prix_tambour_unite[]"]');
    const tambourPack = $('input[name="prix_tambour_pack[]"]');

    tambourNames.each(function (index) {
      tambours.push({
        name: $(this).val(),
        unite: tambourUnite.eq(index).val(),
        pack: tambourPack.eq(index).val()
      });
    });
    return tambours;
  }

  function getPricesForType(type) {
    const prices = {};
    if (type === 'duplicopieur') {
      prices.master_unite = $('#prix_master_unite').val();
      prices.master_pack = $('#prix_master_pack').val();
    } else if (type === 'photocop_encre') {
      prices.noire_unite = $('#prix_noire_unite').val();
      prices.noire_pack = $('#prix_noire_pack').val();
      prices.bleue_unite = $('#prix_bleue_unite').val();
      prices.bleue_pack = $('#prix_bleue_pack').val();
      prices.rouge_unite = $('#prix_rouge_unite').val();
      prices.rouge_pack = $('#prix_rouge_pack').val();
      prices.jaune_unite = $('#prix_jaune_unite').val();
      prices.jaune_pack = $('#prix_jaune_pack').val();
    } else if (type === 'photocop_toner') {
      prices.toner_noir_prix = $('#toner_noir_prix').val();
      prices.toner_noir_prix_copie = $('#toner_noir_prix_copie').val();
      prices.toner_cyan_prix = $('#toner_cyan_prix').val();
      prices.toner_cyan_prix_copie = $('#toner_cyan_prix_copie').val();
      prices.toner_magenta_prix = $('#toner_magenta_prix').val();
      prices.toner_magenta_prix_copie = $('#toner_magenta_prix_copie').val();
      prices.toner_jaune_prix = $('#toner_jaune_prix').val();
      prices.toner_jaune_prix_copie = $('#toner_jaune_prix_copie').val();
      prices.tambour_prix = $('#tambour_prix').val();
      prices.tambour_prix_copie = $('#tambour_prix_copie').val();
      prices.dev_prix = $('#dev_prix').val();
      prices.dev_prix_copie = $('#dev_prix_copie').val();
    }
    return prices;
  }

  function updateMachinesList() {
    if (machines.length === 0) {
      $('#machines-list').hide();
      return;
    }

    $('#machines-list').show();
    let html = '';

    machines.forEach((machine, index) => {
      const typeLabel = getTypeLabel(machine.type);
      html += `
        <div class="alert alert-info d-flex justify-content-between align-items-center">
          <div>
            <strong>${typeLabel}:</strong> ${machine.name}
            ${machine.systemPrinterName ? `<br><small class="text-muted"><i class="fa fa-link"></i> Mappée sur : ${machine.systemPrinterName}</small>` : ''}
            <br><small class="text-muted">${CONFIG.translations.passage_counter} ${machine.passageCounter}</small>
          </div>
          <button type="button" class="btn btn-sm btn-danger" onclick="removeMachine(${machine.id})">
            ❌ ${CONFIG.translations.remove}
          </button>
        </div>
      `;
    });

    $('#machines-container').html(html);
  }

  function getTypeLabel(type) {
    const labels = {
      'duplicopieur': CONFIG.translations.duplicator,
      'photocop_encre': CONFIG.translations.photocopier_ink,
      'photocop_toner': CONFIG.translations.photocopier_toner
    };
    return labels[type] || type;
  }

  window.removeMachine = function (id) {
    machines = machines.filter(m => m.id !== id);
    updateMachinesList();
    updateSubmitButton();
    displaySystemPrinters();
  };

  function clearForm() {
    $('#machine_name, #master_counter, #passage_counter').val('');
    $('input[name="machine_type"]').prop('checked', false);
    $('#machine-form').hide();
    $('#type-selector-section').hide();
    selectedPrinter = null;
    $('#machine_name, #passage_counter, #master_counter').prop('required', false);
  }

  function updateSubmitButton() {
    const hasMachines = machines.length > 0;
    const hasPaperPrice = $('#prix_papier_A3').val() !== '';
    const hasPassword = $('#admin_password').val() !== '';
    const passwordsMatch = $('#admin_password').val() === $('#admin_password_confirm').val();
    const passwordValid = $('#admin_password').val().length >= 6;

    $('#submitBtn').prop('disabled', !hasMachines || !hasPaperPrice || !hasPassword || !passwordsMatch || !passwordValid);
  }

  $('#prix_papier_A3, #admin_password, #admin_password_confirm').on('input', updateSubmitButton);

  $('#setupForm').submit(function (e) {
    if (machines.length === 0) {
      e.preventDefault();
      showAppModal({
        title: CONFIG.translations.no_machine_title,
        message: CONFIG.translations.no_machine_msg,
        type: 'warning'
      });
      return;
    }

    $('#machine_name, #passage_counter, #master_counter').prop('required', false);

    machines.forEach((machine, index) => {
      $('<input>').attr({ type: 'hidden', name: `machines[${index}][type]`, value: machine.type }).appendTo('#setupForm');
      $('<input>').attr({ type: 'hidden', name: `machines[${index}][name]`, value: machine.name }).appendTo('#setupForm');
      $('<input>').attr({ type: 'hidden', name: `machines[${index}][system_printer_name]`, value: machine.systemPrinterName || '' }).appendTo('#setupForm');
      $('<input>').attr({ type: 'hidden', name: `machines[${index}][master_counter]`, value: machine.masterCounter }).appendTo('#setupForm');
      $('<input>').attr({ type: 'hidden', name: `machines[${index}][passage_counter]`, value: machine.passageCounter }).appendTo('#setupForm');

      if (machine.type === 'duplicopieur' && machine.tambours) {
        machine.tambours.forEach((tambour, tambourIndex) => {
          $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][name]`, value: tambour.name }).appendTo('#setupForm');
          $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][unite]`, value: tambour.unite }).appendTo('#setupForm');
          $('<input>').attr({ type: 'hidden', name: `machines[${index}][tambours][${tambourIndex}][pack]`, value: tambour.pack }).appendTo('#setupForm');
        });
      }

      Object.keys(machine.prices).forEach(key => {
        $('<input>').attr({ type: 'hidden', name: `machines[${index}][${key}]`, value: machine.prices[key] }).appendTo('#setupForm');
      });
    });
  });

  // Gestion des tambours
  $('#add-tambour').click(function () {
    var tambourHtml = `
      <div class="tambour-item" style="margin-bottom: 10px;">
        <div class="row">
          <div class="col-md-4">
            <label>${CONFIG.translations.drum_name}</label>
            <input type="text" class="form-control" name="tambours[]" placeholder="${CONFIG.translations.drum_name_placeholder}" required>
          </div>
          <div class="col-md-3">
            <label>${CONFIG.translations.unit_price}</label>
            <input type="number" class="form-control" name="prix_tambour_unite[]" placeholder="${CONFIG.translations.unit_price}" step="0.001" min="0" required>
          </div>
          <div class="col-md-3">
            <label>${CONFIG.translations.pack_price}</label>
            <input type="number" class="form-control" name="prix_tambour_pack[]" placeholder="${CONFIG.translations.pack_price}" step="0.01" min="0" value="11">
          </div>
          <div class="col-md-2">
            <label>&nbsp;</label>
            <button type="button" class="btn btn-danger btn-sm remove-tambour"><i class="fa fa-trash"></i></button>
          </div>
        </div>
      </div>
    `;
    $('#tambours-container').append(tambourHtml);
    updateRemoveButtons();
  });

  $(document).on('click', '.remove-tambour', function () {
    $(this).closest('.tambour-item').remove();
    updateRemoveButtons();
  });

  function updateRemoveButtons() {
    var tambourItems = $('.tambour-item');
    if (tambourItems.length > 1) {
      $('.remove-tambour').show();
    } else {
      $('.remove-tambour').hide();
    }
  }

  updateRemoveButtons();
});
