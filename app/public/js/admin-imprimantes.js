/**
 * admin-imprimantes.js
 *
 * Logique de la page d'administration des imprimantes.
 * Extrait de app/view/admin.imprimantes.html.php
 *
 * Dépendances :
 *   - window.electronAPI (Tauri bridge)
 *   - showAppModal() (component global)
 *   - CONFIG (objet injecté par le PHP via json_encode)
 */
(function () {
  'use strict';

  const S = CONFIG.strings;
  const PHOTOCOPIEURS = CONFIG.photocopieurs || [];
  const DUPLICOPIEURS = CONFIG.duplicopieurs || [];

  const hasElectronAPI = typeof window.electronAPI !== 'undefined';

  // =========================================================================
  // Preview modal
  // =========================================================================

  window.showPreview = function (url, title) {
    document.getElementById('previewModalLabel').textContent = title;
    const img = document.getElementById('previewImage');
    img.src = url;
    document.getElementById('previewError').style.display = 'none';
    img.style.display = 'block';
    img.onerror = function () {
      this.style.display = 'none';
      document.getElementById('previewError').style.display = 'block';
    };
    $('#previewModal').modal('show');
  };

  // =========================================================================
  // Admin status
  // =========================================================================

  async function checkAdminStatus() {
    if (!hasElectronAPI) return;
    try {
      const result = await window.electronAPI.checkAdminStatus();
      if (result.success && !result.isAdmin) {
        document.getElementById('admin-warning-panel').style.display = 'block';
      }
    } catch (error) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_imprimantes.erreur_lors_de_la_v_rification'] || 'Erreur lors de la vérification admin:'), error);
    }
  }

  window.restartAsAdmin = async function () {
    if (!hasElectronAPI) {
      showAppModal({ type: 'warning', message: S.electron_api_unavailable });
      return;
    }
    const confirmed = await new Promise(resolve => {
      showAppModal({
        type: 'warning',
        title: S.restart_required,
        message: S.restart_admin_confirm,
        confirm: true,
        onConfirm: () => resolve(true),
        onClose: () => resolve(false)
      });
    });
    if (!confirmed) return;

    try {
      const btn = document.getElementById('btn-restart-admin');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + S.restarting;
      const result = await window.electronAPI.restartAsAdmin();
      if (!result.success) {
        showAppModal({ type: 'danger', message: S.restart_error + result.error });
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> ' + S.restart_admin;
      }
    } catch (error) {
      showAppModal({ type: 'danger', message: S.common_error + ' : ' + error.message });
    }
  };

  // =========================================================================
  // Monitor status
  // =========================================================================

  window.refreshStatus = async function () {
    const statusDiv = document.getElementById('monitor-status');
    const startBtn = document.getElementById('btn-start-monitor');
    const stopBtn = document.getElementById('btn-stop-monitor');

    if (!hasElectronAPI) {
      statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> ' + S.electron_api_unavailable + '</div>';
      startBtn.style.display = 'none';
      stopBtn.style.display = 'none';
      return;
    }

    try {
      const status = await window.electronAPI.getPrinterMonitorStatus();
      if (!status.available) {
        statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> ' + S.windows_only + '</div>';
        startBtn.style.display = 'none';
        stopBtn.style.display = 'none';
      } else if (status.status === 'active') {
        statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> ' + S.monitor_active_desc + '</div>';
        startBtn.style.display = 'none';
        stopBtn.style.display = 'inline-block';
      } else {
        statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-pause-circle"></i> ' + S.monitor_inactive_desc + '</div>';
        startBtn.style.display = 'inline-block';
        stopBtn.style.display = 'none';
      }
    } catch (error) {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> ' + S.common_error + ' : ' + error.message + '</div>';
    }
  };

  window.toggleMonitor = async function (start) {
    if (!hasElectronAPI) {
      showAppModal({ type: 'warning', message: S.electron_api_unavailable });
      return;
    }
    try {
      const result = await window.electronAPI.togglePrinterMonitor(start);
      if (result.success) {
        setTimeout(() => {
          refreshStatus();
          if (start) setTimeout(loadPrinters, 1000);
        }, 500);
        loadPrintJobs();
      } else {
        showAppModal({ type: 'danger', message: S.common_error + ' : ' + result.error });
      }
    } catch (error) {
      showAppModal({ type: 'danger', message: S.common_error + ' : ' + error.message });
    }
  };

  // =========================================================================
  // Printers list
  // =========================================================================

  async function loadPrinters() {
    const printersDiv = document.getElementById('printers-list');
    if (!hasElectronAPI) {
      printersDiv.innerHTML = '<p class="text-muted">' + S.electron_api_unavailable + '</p>';
      return;
    }

    try {
      const status = await window.electronAPI.getPrinterMonitorStatus();
      if (!status.available || status.status !== 'active') {
        printersDiv.innerHTML = '<p class="text-muted">' + S.no_printers_found + '. <button class="btn btn-sm btn-success" onclick="toggleMonitor(true)">' + S.start + '</button></p>';
        return;
      }

      const result = await window.electronAPI.getPrinters();
      if (result.success && result.printers && result.printers.length > 0) {
        let html = '<table class="table table-striped"><thead><tr><th>' + S.name + '</th><th>' + S.status + '</th><th>' + S.is_default + '</th><th>' + S.actions + '</th></tr></thead><tbody>';
        result.printers.forEach(printer => {
          const pName = printer.name || printer.Name;
          const pStatus = (printer.status || printer.Status || '').toString();
          const pDefault = printer.isDefault || printer.Default;
          const isDefault = pDefault ? '<span class="label label-success">' + S.yes + '</span>' : '<span class="label label-default">' + S.no + '</span>';
          const statusLower = pStatus.toLowerCase();
          const nameLower = (pName || '').toLowerCase();
          const isError = statusLower === 'error' || nameLower.includes('photocopilleuse');
          const statusClass = isError ? 'danger' : statusLower === '0' || statusLower === 'ok' || statusLower === 'idle' ? 'success' : 'warning';
          const deleteBtn = isError ? '<button class="btn btn-xs btn-danger" onclick="deletePrinter(\'' + pName.replace(/'/g, "\\'") + '\')" title="' + S.common_delete + '"><i class="fa fa-trash"></i></button>' : '';
          html += '<tr class="' + (isError ? 'danger' : '') + '"><td>' + (pName || 'N/A') + '</td><td><span class="label label-' + statusClass + '">' + (pStatus || 'N/A') + '</span></td><td>' + isDefault + '</td><td>' + deleteBtn + '</td></tr>';
        });
        html += '</tbody></table>';
        printersDiv.innerHTML = html;
      } else {
        printersDiv.innerHTML = '<p class="text-muted">' + S.no_printers_found + ': ' + (result.error || 'Inconnu') + '</p>';
      }
    } catch (error) {
      printersDiv.innerHTML = '<div class="alert alert-danger">' + S.common_error + ' : ' + error.message + '</div>';
    }
  }

  window.deletePrinter = function (printerName) {
    showAppModal({
      type: 'warning',
      title: S.delete_printer,
      message: S.delete_printer_confirm.replace(':name', printerName),
      confirm: true,
      onConfirm: async function () {
        if (!hasElectronAPI) {
          showAppModal({ type: 'warning', message: S.electron_api_unavailable });
          return;
        }
        try {
          const result = await window.electronAPI.deletePrinter(printerName);
          if (result.success) {
            showAppModal({ type: 'success', message: S.delete_printer_success });
            loadPrinters();
          } else {
            showAppModal({ type: 'danger', message: S.restart_error + result.error });
          }
        } catch (error) {
          showAppModal({ type: 'danger', message: S.common_error + ' : ' + error.message });
        }
      }
    });
  };

  // =========================================================================
  // Stats
  // =========================================================================

  async function loadStats() {
    const statsDiv = document.getElementById('stats-container');
    try {
      const response = await fetch('?check_print_jobs');
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();

      if (data.success) {
        let html = '<div class="row">';
        html += '<div class="col-md-4"><div class="well text-center"><h3>' + data.total_jobs + '</h3><p>' + S.total_prints + '</p></div></div>';
        if (data.stats && data.stats.by_printer && data.stats.by_printer.length > 0) {
          html += '<div class="col-md-8"><h4>' + S.associated_machine + ':</h4><ul>';
          data.stats.by_printer.forEach(stat => {
            html += '<li><strong>' + stat.printer_name + '</strong>: ' + stat.total_jobs + ' jobs, ' + (stat.total_pages || 0) + ' ' + S.common_pages + '</li>';
          });
          html += '</ul></div>';
        }
        html += '</div>';
        statsDiv.innerHTML = html;
      } else {
        statsDiv.innerHTML = '<p class="text-muted">' + (data.message || data.error || S.no_data) + '</p>';
      }
    } catch (error) {
      statsDiv.innerHTML = '<div class="alert alert-danger">' + S.common_error + ' : ' + error.message + '</div>';
    }
  }

  // =========================================================================
  // Print jobs + pagination
  // =========================================================================

  let currentPage = 1;
  let itemsPerPage = 20;
  let totalJobs = 0;
  let allJobs = [];

  window.loadPrintJobs = async function (page) {
    if (page !== undefined && page !== null) currentPage = page;
    const jobsDiv = document.getElementById('print-jobs-list');

    try {
      const showHistory = document.getElementById('show-history') ? document.getElementById('show-history').checked : false;
      const response = await fetch('?check_print_jobs&history=' + showHistory);
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();

      if (data.success && data.jobs && data.jobs.length > 0) {
        allJobs = data.jobs;
        totalJobs = data.total_jobs || data.jobs.length;
        const totalPages = Math.ceil(totalJobs / itemsPerPage);
        const startIndex = (currentPage - 1) * itemsPerPage;
        const jobsToDisplay = allJobs.slice(startIndex, Math.min(startIndex + itemsPerPage, totalJobs));

        let html = '<table class="table table-striped table-hover"><thead><tr><th><input type="checkbox" id="select-all-jobs" onclick="toggleSelectAll(this)"></th><th>' + S.preview_doc + '</th><th>' + S.common_date + '</th><th>' + S.common_document + '</th><th>' + S.common_format + '</th><th>' + S.common_duplex + '</th><th>' + S.common_color + '</th><th>' + S.ink_coverage + '</th><th>' + S.common_status + '</th><th>' + S.common_pages + '</th></tr></thead><tbody>';

        jobsToDisplay.forEach(job => {
          const date = new Date(job.timestamp).toLocaleString(CONFIG.lang === 'fr' ? 'fr-FR' : 'en-US');
          const copies = job.copies || 1;
          const totalDocPages = job.total_pages || 0;
          const isDuplex = job.duplex === 1 || job.duplex === '1' || job.duplex === true;
          const totalPagesCalc = totalDocPages * copies;
          const sheets = isDuplex ? Math.ceil(totalDocPages / 2) * copies : totalDocPages * copies;
          const pages = totalPagesCalc + ' ' + S.common_pages + ', ' + sheets + ' ' + S.sheets + (copies > 1 ? ' (' + copies + ' copies)' : '');
          const statusClass = job.status === 'Completed' ? 'success' : job.status === 'Printing' ? 'info' : 'warning';
          const paperSize = job.paper_size || 'N/A';
          const duplex = isDuplex ? S.yes : S.no;

          let colorMode = 'N/A';
          if (job.color_mode) {
            const cv = job.color_mode.toLowerCase();
            if (cv === 'color' || cv.includes('color') || cv === '2') colorMode = S.common_color;
            else if (cv === 'monochrome' || cv.includes('mono') || cv === '1') colorMode = S.common_bw;
          }

          const fillRate = job.fill_rate !== null && job.fill_rate !== undefined ? parseFloat(job.fill_rate).toFixed(1) + '%' : 'N/A';

          let thumbnailHtml = '<span class="text-muted"><i class="fa fa-image"></i> N/A</span>';
          if (job.thumbnail_url) {
            const escapedName = (job.document || '').replace(/'/g, "\\'");
            thumbnailHtml = '<a href="#" onclick="showPreview(\'' + job.thumbnail_url + '\', \'' + escapedName + ' - ' + pages.replace(/'/g, "\\'") + '\'); return false;"><img src="' + job.thumbnail_url + '" style="height: 40px; border: 1px solid #ddd; border-radius: 4px;" onerror="this.onerror=null; this.src=\'\'; this.parentElement.innerHTML=\'<span class=\\\\\'text-muted\\\\\'><i class=\\\\\'fa fa-exclamation-circle\\\\\'></i> Err</span>\'"></a>';
          }

          html += '<tr><td><input type="checkbox" class="job-checkbox" value="' + job.id + '" onclick="updateDeleteButton()"></td><td>' + thumbnailHtml + '</td><td>' + date + '</td><td>' + (job.document || 'N/A') + '</td><td>' + paperSize + '</td><td>' + duplex + '</td><td>' + colorMode + '</td><td>' + fillRate + '</td><td><span class="label label-' + statusClass + '">' + (job.status || 'N/A') + '</span></td><td>' + pages + '</td></tr>';
        });

        html += '</tbody></table>';
        jobsDiv.innerHTML = html;
        updatePaginationControls(totalPages);
        const selectAll = document.getElementById('select-all-jobs');
        if (selectAll) selectAll.checked = false;
        updateDeleteButton();
      } else {
        jobsDiv.innerHTML = '<p class="text-muted">' + (data.message || S.no_prints_found) + '</p>';
        document.getElementById('pagination-controls').style.display = 'none';
        document.getElementById('pagination-info').textContent = '';
      }
    } catch (error) {
      jobsDiv.innerHTML = '<div class="alert alert-danger">' + S.common_error + ' : ' + error.message + '</div>';
    }
  };

  function updatePaginationControls(totalPages) {
    const controls = document.getElementById('pagination-controls');
    const info = document.getElementById('pagination-info');
    const display = document.getElementById('current-page-display');
    const btnFirst = document.getElementById('btn-first-page');
    const btnPrev = document.getElementById('btn-prev-page');
    const btnNext = document.getElementById('btn-next-page');
    const btnLast = document.getElementById('btn-last-page');

    controls.style.display = totalPages <= 1 ? 'none' : 'block';
    display.textContent = S.page + ' ' + currentPage + ' ' + S.of + ' ' + totalPages;

    const start = (currentPage - 1) * itemsPerPage + 1;
    const end = Math.min(currentPage * itemsPerPage, totalJobs);
    info.textContent = S.pagination_info.replace(':start', start).replace(':end', end).replace(':total', totalJobs);

    btnFirst.classList.toggle('disabled', currentPage === 1);
    btnPrev.classList.toggle('disabled', currentPage === 1);
    btnNext.classList.toggle('disabled', currentPage === totalPages);
    btnLast.classList.toggle('disabled', currentPage === totalPages);
  }

  window.goToPage = function (page) {
    const tp = Math.ceil(totalJobs / itemsPerPage);
    if (page >= 1 && page <= tp) loadPrintJobs(page);
  };
  window.goToPreviousPage = function () { if (currentPage > 1) loadPrintJobs(currentPage - 1); };
  window.goToNextPage = function () { if (currentPage < Math.ceil(totalJobs / itemsPerPage)) loadPrintJobs(currentPage + 1); };
  window.goToLastPage = function () { loadPrintJobs(Math.ceil(totalJobs / itemsPerPage)); };

  // =========================================================================
  // Selection + delete
  // =========================================================================

  window.toggleSelectAll = function (source) {
    document.querySelectorAll('.job-checkbox').forEach(cb => { cb.checked = source.checked; });
    updateDeleteButton();
  };

  window.updateDeleteButton = function () {
    const count = document.querySelectorAll('.job-checkbox:checked').length;
    const btn = document.getElementById('btn-delete-selection');
    if (btn) {
      btn.disabled = count === 0;
      btn.innerHTML = '<i class="fa fa-trash"></i> ' + S.delete_selection_count.replace(':count', count);
    }
  };

  window.deleteSelectedJobs = function () {
    const checkboxes = document.querySelectorAll('.job-checkbox:checked');
    if (checkboxes.length === 0) return;
    showAppModal({
      type: 'warning',
      title: S.delete_selection,
      message: S.confirm_delete_count.replace(':count', checkboxes.length),
      confirm: true,
      onConfirm: async function () {
        const ids = Array.from(checkboxes).map(cb => cb.value);
        try {
          const response = await fetch('?check_print_jobs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_jobs', ids: ids })
          });
          const result = await response.json();
          if (result.success) {
            loadPrintJobs();
            loadStats();
            const selectAll = document.getElementById('select-all-jobs');
            if (selectAll) selectAll.checked = false;
          } else {
            showAppModal({ type: 'danger', message: 'Erreur lors de la suppression: ' + (result.error || result.message) });
          }
        } catch (error) {
          showAppModal({ type: 'danger', message: 'Erreur réseau: ' + error.message });
        }
      }
    });
  };

  window.purgeAllJobs = function () {
    showAppModal({
      type: 'danger',
      title: 'Purger l\'historique',
      message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_imprimantes.attention__cette_action_est_ir'] || 'ATTENTION: Cette action est irréversible !<br><br>Êtes-vous sûr de vouloir supprimer TOUT l\'historique des impressions ?'),
      confirm: true,
      onConfirm: async function () {
        try {
          const response = await fetch('?check_print_jobs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'purge_all' })
          });
          const result = await response.json();
          if (result.success) {
            loadPrintJobs();
            loadStats();
          } else {
            showAppModal({ type: 'danger', message: 'Erreur lors de la purge: ' + (result.error || result.message) });
          }
        } catch (error) {
          showAppModal({ type: 'danger', message: 'Erreur réseau: ' + error.message });
        }
      }
    });
  };

  // =========================================================================
  // Mappings
  // =========================================================================

  async function loadMappings() {
    if (!hasElectronAPI) {
      document.querySelector('#mappings-table tbody').innerHTML = '<tr><td colspan="3" class="text-center text-warning">' + S.electron_api_required + '</td></tr>';
      return;
    }

    try {
      const printersResult = await window.electronAPI.getPrinters();
      const systemPrinters = printersResult.success ? printersResult.printers : [];
      const response = await fetch('?manage_mappings');
      const data = await response.json();
      const mappings = data.success ? data.mappings : [];
      const mappingsMap = {};
      mappings.forEach(m => { mappingsMap[m.system_printer_name] = { type: m.machine_type, id: m.machine_id }; });

      const validPrinters = systemPrinters.filter(p => {
        if (!p.name && !p.Name) return false;
        const name = (p.name || p.Name).toLowerCase();
        const status = (p.status || p.Status || '').toString().toLowerCase();
        return status !== 'error' && !name.includes('onenote') && !name.includes('pdf');
      });

      let html = '';
      validPrinters.forEach(printer => {
        const pName = printer.name || printer.Name;
        if (!pName) return;
        const currentMapping = mappingsMap[pName];

        html += '<tr><td style="vertical-align: middle;"><strong>' + pName + '</strong></td><td><select class="form-control input-sm mapping-select" data-printer="' + pName + '"><option value="">' + S.not_assigned + '</option>';
        html += '<optgroup label="' + S.photocopieur + 's">';
        PHOTOCOPIEURS.forEach(p => {
          const selected = currentMapping && currentMapping.type === 'photocop' && currentMapping.id == p.id ? ' selected' : '';
          html += '<option value="photocop_' + p.id + '"' + selected + '>' + p.marque + ' (' + p.type_encre + ')</option>';
        });
        html += '</optgroup><optgroup label="' + S.duplicopieur + 's">';
        DUPLICOPIEURS.forEach(d => {
          const selected = currentMapping && currentMapping.type === 'dupli' && currentMapping.id == d.id ? ' selected' : '';
          html += '<option value="dupli_' + d.id + '"' + selected + '>' + d.marque + ' ' + d.modele + '</option>';
        });
        html += '</optgroup></select></td><td><button class="btn btn-primary btn-sm btn-save-mapping" onclick="saveMapping(\'' + pName.replace(/'/g, "\\'") + '\')"><i class="fa fa-save"></i> ' + S.save + '</button></td></tr>';
      });

      if (validPrinters.length === 0) {
        html = '<tr><td colspan="3" class="text-center">Aucune imprimante détectée</td></tr>';
      }
      document.querySelector('#mappings-table tbody').innerHTML = html;
    } catch (error) {
      console.error(error);
      document.querySelector('#mappings-table tbody').innerHTML = '<tr><td colspan="3" class="text-center text-danger">Erreur: ' + error.message + '</td></tr>';
    }
  }

  window.saveMapping = async function (printerName) {
    const select = document.querySelector('select[data-printer="' + printerName + '"]');
    const value = select.value;
    if (!value) { showAppModal((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.admin_imprimantes.veuillez_s_lectionner_une_mach'] || 'Veuillez sélectionner une machine.')); return; }
    const [type, id] = value.split('_');

    try {
      const response = await fetch('?manage_mappings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ system_printer_name: printerName, machine_type: type, machine_id: id })
      });
      const result = await response.json();
      if (result.success) {
        const btn = select.closest('tr').querySelector('.btn-save-mapping');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check"></i> OK';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.classList.add('btn-primary');
          btn.classList.remove('btn-success');
        }, 2000);
      } else {
        showAppModal({ type: 'danger', message: 'Erreur: ' + result.error });
      }
    } catch (error) {
      showAppModal({ type: 'danger', message: 'Erreur réseau: ' + error.message });
    }
  };

  // =========================================================================
  // Init
  // =========================================================================

  document.addEventListener('DOMContentLoaded', function () {
    checkAdminStatus();
    refreshStatus();
    loadPrinters();
    loadStats();
    loadPrintJobs();
    loadMappings();

    setInterval(() => {
      loadPrintJobs();
      loadStats();
    }, 30000);

    document.getElementById('items-per-page').addEventListener('change', function () {
      itemsPerPage = parseInt(this.value);
      currentPage = 1;
      loadPrintJobs();
    });

    if (hasElectronAPI) {
      window.electronAPI.onPrintJobDetected(function () {
        loadPrintJobs();
        loadStats();
      });
    }
  });

})();
