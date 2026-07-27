/**
 * auto-tirage.js
 *
 * Logique de la session d'impression automatique (auto_tirage).
 * Extrait de app/view/auto_tirage.html.php
 *
 * Dépendances :
 *   - CONFIG (objet injecté par le PHP via json_encode) avec :
 *       .translations  – objet clé → chaîne traduite
 *   - jQuery ($, $.fn.modal) — requis par Bootstrap 4 modals
 *   - showAppModal() (composant global)
 *   - window.electronAPI (Tauri bridge, optionnel)
 */
(function () {
  'use strict';

  const S = CONFIG.strings || {};

  function translate(key, params) {
    let text = S[key] || key;
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        text = text.replace(new RegExp(':' + k, 'g'), v);
      }
    }
    return text;
  }

  let sessionUser = '';
  let processedJobIds = new Set();
  let pendingJobs = new Map();
  let sessionJobs = [];
  const STABILIZATION_DELAY = 1000;
  let lastCheckTime = Date.now() - (24 * 60 * 60 * 1000);
  let pollingInterval = null;

  let currentSessionId = null;
  let activeSessions = [];

  // ── DOM-ready ─────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    loadActiveSessions();
  });

  // ── Session lifecycle ─────────────────────────────────────────────────────

  async function startSession() {
    const pseudo = document.getElementById('pseudo-input').value.trim();
    if (!pseudo) return showAppModal({ message: "Merci d'entrer un nom", type: "warning" });

    sessionUser = pseudo;
    const sessionName = document.getElementById('session-name-input').value.trim();
    sessionStorage.setItem('auto_tirage_session_user', sessionUser);
    localStorage.setItem('auto_tirage_user', sessionUser);

    try {
      const response = await fetch('?sessions&action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          contact: sessionUser,
          session_name: sessionName,
          force_new: true
        })
      });
      const data = await response.json();
      if (data.success) {
        currentSessionId = data.session_id;
        console.log('[AutoTirage] Session créée:', currentSessionId);
        await loadActiveSessions();
      }
    } catch (error) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.autotirage__erreur_cr_ation_s'] || '[AutoTirage] Erreur création session:'), error);
    }

    document.getElementById('step-identity').style.display = 'none';
    document.getElementById('step-listening').style.display = 'block';

    startPolling();
  }

  window.suspendSession = function () {
    currentSessionId = null;
    sessionUser = '';
    sessionJobs = [];
    processedJobIds.clear();
    bufferJobs.clear();

    sessionStorage.removeItem('auto_tirage_session_jobs');
    sessionStorage.removeItem('auto_tirage_session_user');

    document.getElementById('step-identity').style.display = 'block';
    document.getElementById('step-listening').style.display = 'none';

    loadActiveSessions();
  };

  window.quitSession = function (idToClose) {
    const id = idToClose || currentSessionId;

    showAppModal({
      title: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.cl_turer_la_session'] || "Clôturer la session"),
      message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.voulez_vous_cl_turer_d_finitiv'] || "Voulez-vous CLÔTURER définitivement cette session ?<br><br>Cela la retirera de la liste des sessions actives."),
      type: "warning",
      confirm: true
    }, async function (confirmed) {
      if (!confirmed) return;

      if (id) {
        try {
          await fetch('?sessions&action=close&id=' + id);
        } catch (e) {
          console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_fermeture_session'] || 'Erreur fermeture session:'), e);
        }
      }

      if (id == currentSessionId) {
        sessionStorage.removeItem('auto_tirage_session_user');
        localStorage.removeItem('auto_tirage_user');
        localStorage.removeItem('auto_tirage_last_session_id');
        window.location.reload();
      } else {
        loadActiveSessions();
      }
    });
  };

  window.finishSession = async function () {
    if (sessionJobs.length === 0) return showAppModal({ message: S['admin_tirage.no_prints_selected'], type: "warning" });

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?tirage_multimachines';

    addHidden(form, 'contact', sessionUser);
    addHidden(form, 'ok', '1');

    if (currentSessionId) {
      addHidden(form, 'session_id', currentSessionId);
    }

    sessionJobs.forEach((job, index) => {
      addHidden(form, 'machines[' + index + '][type]', job.type === 'photocop' ? 'photocopieur' : 'duplicopieur');

      if (job.type === 'photocop') {
        addHidden(form, 'machines[' + index + '][machine]', job.machine);
        if (job.originalJobId) {
          addHidden(form, 'machines[' + index + '][job_id]', job.originalJobId);
          addHidden(form, 'machines[' + index + '][printer_name]', job.printerName || job.machine);
        }
        if (job.raw_fill_rate !== undefined) {
          addHidden(form, 'machines[' + index + '][fill_rate]', job.raw_fill_rate);
        }
        if (job.thumbnail_url) {
          addHidden(form, 'machines[' + index + '][thumbnail_url]', job.thumbnail_url);
        }
        if (job.document_name || job.document) {
          addHidden(form, 'machines[' + index + '][document_name]', job.document_name || job.document);
        }

        const bPrefix = 'machines[' + index + '][brochures][0]';
        const jobCopies = job.copies || 1;
        const sheetsPerCopy = job.nb_feuilles
          ? Math.ceil(job.nb_feuilles / jobCopies)
          : Math.ceil((job.pages / jobCopies) / (job.duplex ? 2 : 1));

        addHidden(form, bPrefix + '[nb_exemplaires]', jobCopies);
        addHidden(form, bPrefix + '[nb_feuilles]', sheetsPerCopy);
        addHidden(form, bPrefix + '[nb_pages]', job.pages / jobCopies);
        addHidden(form, bPrefix + '[taille]', job.taille);
        addHidden(form, bPrefix + '[rv]', job.duplex ? 'oui' : 'non');
        addHidden(form, bPrefix + '[couleur]', job.color ? 'oui' : 'non');
        addHidden(form, bPrefix + '[feuilles_payees]', job.feuilles_payees ? 'oui' : 'non');
      } else {
        addHidden(form, 'machines[' + index + '][duplicopieur_id]', job.machine_id);
        if (job.originalJobId) {
          addHidden(form, 'machines[' + index + '][job_id]', job.originalJobId);
          addHidden(form, 'machines[' + index + '][printer_name]', job.printerName || job.machine);
        }
        addHidden(form, 'machines[' + index + '][nb_masters]', job.nb_masters);
        addHidden(form, 'machines[' + index + '][nb_passages]', job.nb_passages);
        addHidden(form, 'machines[' + index + '][rv]', job.duplex ? 'oui' : 'non');
        addHidden(form, 'machines[' + index + '][feuilles_payees]', job.feuilles_payees ? 'oui' : 'non');
        addHidden(form, 'machines[' + index + '][A4]', job.taille === 'A4' ? 'A4' : 'A3');
        addHidden(form, 'machines[' + index + '][tambour]', job.selected_tambour || 'tambour_noir');

        addHidden(form, 'machines[' + index + '][master_av]', job.master_av !== undefined ? job.master_av : 0);
        addHidden(form, 'machines[' + index + '][master_ap]', job.master_ap !== undefined ? job.master_ap : 0);
        addHidden(form, 'machines[' + index + '][passage_av]', job.passage_av !== undefined ? job.passage_av : 0);
        addHidden(form, 'machines[' + index + '][passage_ap]', job.passage_ap !== undefined ? job.passage_ap : 0);

        if (currentSessionId) {
          addHidden(form, 'machines[' + index + '][session_id]', currentSessionId);
        }
        if (job.thumbnail_url) {
          addHidden(form, 'machines[' + index + '][thumbnail_url]', job.thumbnail_url);
        }
        if (job.document_name || job.document) {
          addHidden(form, 'machines[' + index + '][document_name]', job.document_name || job.document);
        }
        if (job.raw_fill_rate !== undefined) {
          addHidden(form, 'machines[' + index + '][fill_rate]', job.raw_fill_rate);
        }
      }

      if (job.id) {
        addHidden(form, 'machines[' + index + '][db_id]', job.id);
      }
    });

    document.body.appendChild(form);
    form.submit();
  };

  function addHidden(form, name, value) {
    const i = document.createElement('input');
    i.type = 'hidden';
    i.name = name;
    i.value = value;
    form.appendChild(i);
  }

  // ── Polling ────────────────────────────────────────────────────────────────

  function startPolling() {
    pollingInterval = setInterval(checkPrintJobs, 3000);
    addLog('info', '✅ ' + S['auto_tirage.system_ready']);
  }

  async function checkPrintJobs() {
    try {
      let url = '?check_print_jobs&after=' + lastCheckTime;
      if (currentSessionId) url += '&session_id=' + currentSessionId;
      const response = await fetch(url);
      const data = await response.json();
      if (data.jobs) console.log('[AutoTirage] Polling: ' + data.jobs.length + ' jobs reçus (session_id=' + currentSessionId + ')');

      if (data.success && data.jobs && data.jobs.length > 0) {
        const newJobs = data.jobs.filter(job => {
          const jobTime = new Date(job.timestamp).getTime();
          if (jobTime > lastCheckTime && !processedJobIds.has(job.job_id)) {
            return true;
          }
          return false;
        });

        newJobs.reverse().forEach(async (job) => {
          handleJobCandidate(job);
        });
      }

      if (data.jobs && data.jobs.length > 0) {
        data.jobs.forEach(job => {
          checkForUpdate(job);
        });
      }

      if (data.success && Array.isArray(data.jobs)) {
        const apiJobIds = new Set(data.jobs.map(j => String(j.job_id)));
        bufferJobs.forEach((job, jobId) => {
          if (!apiJobIds.has(String(jobId))) {
            bufferJobs.delete(jobId);
            const row = document.getElementById('buffer-row-' + jobId);
            if (row) row.remove();
          }
        });

        if (bufferJobs.size === 0) {
          document.getElementById('buffer-zone').style.display = 'none';
        }
        updateBulkActionsVisibility();
      }

      processPendingJobs();

    } catch (error) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_polling'] || 'Erreur polling:'), error);
    }
  }

  function handleJobCandidate(job) {
    if (processedJobIds.has(job.job_id)) return;

    const now = Date.now();
    if (!pendingJobs.has(job.job_id)) {
      pendingJobs.set(job.job_id, {
        job: job,
        firstSeen: now,
        lastUpdate: now
      });
      addLog('info', '⏳ ' + S['auto_tirage.job_detected'] + ': ' + job.document + ' (' + job.total_pages + ' pages). ' + S['auto_tirage.stabilizing']);

      job.stabilizing = true;
      addToBuffer(job);
    } else {
      const candidate = pendingJobs.get(job.job_id);
      if (candidate.job.total_pages !== job.total_pages || candidate.job.status !== job.status) {
        const oldPages = candidate.job.total_pages;
        candidate.job = job;
        candidate.job.stabilizing = true;
        candidate.lastUpdate = now;
        if (oldPages !== job.total_pages) {
          addLog('info', '... ' + S['auto_tirage.page_update'] + ' : ' + job.total_pages);
        }
        renderBufferRow(candidate.job);
      }
    }
  }

  function checkForUpdate(apiJob) {
    const existingIndex = sessionJobs.findIndex(j => {
      const match = String(j.originalJobId) === String(apiJob.job_id);
      return match;
    });
    if (existingIndex !== -1) {
      const currentJob = sessionJobs[existingIndex];
      const newFillRate = parseFloat(apiJob.fill_rate || 0);
      const oldFillRate = parseFloat(currentJob.raw_fill_rate || 0);
      const thumbnailUpdate = !currentJob.thumbnail_url && apiJob.thumbnail_url;
      const normalizedNewFill = (newFillRate > 1.0) ? newFillRate / 100.0 : newFillRate;
      const fillRateChanged = Math.abs(normalizedNewFill - oldFillRate) > 0.001;

      if (currentJob.raw_total_pages !== apiJob.total_pages || thumbnailUpdate || fillRateChanged) {
        console.log('[AutoTirage] Triggering update for job ' + apiJob.job_id);
        simulateJob(apiJob, existingIndex);
      }
      return;
    }

    const jobIdKey = String(apiJob.job_id);
    if (bufferJobs.has(jobIdKey)) {
      const existing = bufferJobs.get(jobIdKey);
      const newFill = parseFloat(apiJob.fill_rate || 0);
      const oldFill = parseFloat(existing.fill_rate || 0);

      const pagesChanged = existing.total_pages !== apiJob.total_pages;
      const thumbChanged = existing.thumbnail_url !== apiJob.thumbnail_url;
      const fillChanged = Math.abs(newFill - oldFill) > 0.001;
      const idChanged = String(existing.id) !== String(apiJob.id);

      if (pagesChanged || thumbChanged || fillChanged || idChanged) {
        addToBuffer(apiJob);
      }
    } else if (currentSessionId && apiJob.session_id == currentSessionId) {
      console.log('[AutoTirage] Job ' + apiJob.job_id + ' assigned to session ' + currentSessionId + ' on server. Moving to session.');
      handleJobCandidate(apiJob);
    }
  }

  function processPendingJobs() {
    const now = Date.now();
    pendingJobs.forEach((candidate, jobId) => {
      if (now - candidate.lastUpdate > STABILIZATION_DELAY) {
        pendingJobs.delete(jobId);
        processedJobIds.add(jobId);

        candidate.job.stabilizing = false;

        if (currentSessionId && candidate.job.session_id == currentSessionId) {
          addLog('success', '📥 ' + S['auto_tirage.job_assigned'] + ' : ' + candidate.job.document);

          bufferJobs.delete(jobId);
          const row = document.getElementById('buffer-row-' + jobId);
          if (row) row.remove();
          if (bufferJobs.size === 0) {
            document.getElementById('buffer-zone').style.display = 'none';
          }

          simulateJob(candidate.job);
        } else {
          addLog('info', '⏸️ ' + S['auto_tirage.job_waiting'] + ' : ' + candidate.job.document);
          renderBufferRow(candidate.job);
        }
      }
    });
  }

  // ── Buffer Zone ────────────────────────────────────────────────────────────

  let bufferJobs = new Map();

  function addToBuffer(job) {
    bufferJobs.set(job.job_id, job);
    renderBufferRow(job);
    document.getElementById('buffer-zone').style.display = 'block';
  }

  function renderBufferRow(job) {
    const tbody = document.querySelector('#buffer-table tbody');
    let row = document.getElementById('buffer-row-' + job.job_id);

    let isChecked = false;
    if (row) {
      const cb = row.querySelector('.buffer-checkbox');
      if (cb) isChecked = cb.checked;
    }

    if (!row) {
      row = document.createElement('tr');
      row.id = 'buffer-row-' + job.job_id;
      tbody.appendChild(row);
    }

    const date = new Date(job.timestamp).toLocaleTimeString();
    const pages = job.total_pages * (job.copies || 1);

    const isDuplex = (job.duplex == 1 || job.duplex == '1' || job.duplex === true || String(job.duplex).toLowerCase() === 'oui');
    const colorMode = (String(job.color_mode).toLowerCase().includes('color') || String(job.color_mode) === '2') ? S['tirage_multimachines.color'] : 'N&B';
    const duplexLabel = isDuplex ? 'R/V' : 'Recto';
    const rawFillValue = parseFloat(job.fill_rate || 0);
    const fillPct = rawFillValue.toFixed(1) + '%';

    const paperMap = { '9': 'A4', '8': 'A3', '11': 'A5', '1': 'Letter', '5': 'Legal' };
    const paperLabel = paperMap[String(job.paper_size)] || job.paper_size || 'A4';

    const actions = job.stabilizing ? '' +
      '<div class="text-center">' +
      '<i class="fa fa-spinner fa-spin text-primary"></i>' +
      '<div style="font-size: 10px;" class="text-muted">' + S['auto_tirage.stabilization'] + '</div>' +
      '</div>'
      : '' +
      '<button class="btn btn-info btn-sm" onclick="refreshJobAnalysis(\'' + job.job_id + '\', this, \'' + job.printer_name.replace(/'/g, "\\'") + '\')" title="' + S['common.refresh'] + '">' +
      '<i class="fa fa-refresh"></i>' +
      '</button>' +
      '<button class="btn btn-primary btn-sm" onclick="moveBufferToSession(\'' + job.job_id + '\')" title="' + S['auto_tirage.add_selected'] + '">' +
      '<i class="fa fa-plus"></i>' +
      '</button>' +
      '<button class="btn btn-outline-danger btn-sm" onclick="deleteBufferJob(\'' + job.id + '\', \'' + job.job_id + '\')" title="' + S['auto_tirage.delete_selected'] + '">' +
      '<i class="fa fa-trash"></i>' +
      '</button>';

    row.innerHTML =
      '<td><input type="checkbox" class="buffer-checkbox" data-id="' + job.id + '" data-job-id="' + job.job_id + '" onchange="updateBulkActionsVisibility()" ' + (isChecked ? 'checked' : '') + ' ' + (job.stabilizing ? 'disabled' : '') + '></td>' +
      '<td>' + (job.thumbnail_url ? '<img src="' + job.thumbnail_url + '" height="30" style="cursor: pointer; border-radius: 3px;" onclick="showThumbnailModal(\'' + job.thumbnail_url + '\', \'' + job.document.replace(/'/g, "\\'") + '\')">' : '<i class="fa fa-file-o"></i>') + '</td>' +
      '<td><small>' + date + '</small></td>' +
      '<td><div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + job.printer_name + '">' + job.printer_name + '</div></td>' +
      '<td>' +
      '<div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" ' +
      'title="' + (job.document_full_path || job.document) + '">' +
      '<strong>' + (job.document_display_name || job.document) + '</strong>' +
      '</div>' +
      '</td>' +
      '<td>' +
      '<span class="badge badge-secondary">' + paperLabel + '</span> ' +
      '<span class="badge badge-info">' + colorMode + '</span> ' +
      '<span class="badge badge-light border">' + duplexLabel + '</span>' +
      '<div class="mt-1"><small>' + pages + ' pages</small></div>' +
      '</td>' +
      '<td>' +
      '<div class="progress" style="height: 10px; width: 60px;" title="' + S['auto_tirage.fill_rate'] + ': ' + fillPct + '">' +
      '<div class="progress-bar bg-info" role="progressbar" style="width: ' + fillPct + '" aria-valuenow="' + rawFillValue + '" aria-valuemin="0" aria-valuemax="100"></div>' +
      '</div>' +
      '<small>' + fillPct + '</small>' +
      '</td>' +
      '<td class="align-middle">' +
      actions +
      '</td>';
  }

  window.moveBufferToSession = async function (jobId) {
    let job = null;
    let dbId = null;
    for (let [key, val] of bufferJobs) {
      if (String(key) === String(jobId)) {
        job = val;
        dbId = job.id;
        break;
      }
    }

    if (job) {
      const row = document.getElementById('buffer-row-' + jobId);
      if (row) row.style.display = 'none';

      simulateJob(job, null, jobId).then(success => {
        if (success) {
          bufferJobs.delete(jobId);
          if (row) row.remove();
          if (bufferJobs.size === 0) {
            document.getElementById('buffer-zone').style.display = 'none';
          }
        } else {
          if (row) row.style.display = '';
          showAppModal({ message: (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_lors_de_l_ajout___la_se'] || "Erreur lors de l'ajout à la session. Veuillez réessayer."), type: "danger" });
        }
      });
    }
  };

  window.deleteBufferJob = async function (dbId, spoolJobId) {
    showAppModal({
      message: S['auto_tirage.confirm_delete'],
      confirm: true,
      type: "warning"
    }, async (confirmed) => {
      if (!confirmed) return;

      const rowToHide = document.getElementById('buffer-row-' + spoolJobId);
      if (rowToHide) rowToHide.style.opacity = '0.5';

      const targetJob = bufferJobs.get(spoolJobId) || bufferJobs.get(Number(spoolJobId));
      const printerName = targetJob ? (targetJob.printer_name || targetJob.nom_machine || '') : '';

      try {
        if (window.electronAPI && window.electronAPI.deletePrintJob) {
          console.log('[DELETE] Appel IPC deletePrintJob:', spoolJobId, 'imprimante:', printerName);
          try {
            await Promise.race([
              window.electronAPI.deletePrintJob(printerName, spoolJobId),
              new Promise((_, reject) => setTimeout(() => reject(new Error('IPC Timeout')), 3000))
            ]);
          } catch (ipcErr) {
            console.warn('[DELETE] Avertissement/Timeout IPC deletePrintJob:', ipcErr);
          }
        }

        const response = await fetch('?check_print_jobs', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'delete_jobs',
            ids: [dbId]
          })
        });

        const result = await response.json();

        fetch('?check_print_jobs', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'delete_by_job_id',
            job_id: spoolJobId
          })
        }).catch(e => console.warn('[DELETE] Nettoyage secondaire par job_id ignoré:', e));

        if (result && result.success) {
          addLog('info', '🗑️ ' + S['auto_tirage.job_deleted']);
        }

        bufferJobs.delete(spoolJobId);
        bufferJobs.delete(Number(spoolJobId));
        if (rowToHide) rowToHide.remove();
        if (bufferJobs.size === 0) {
          const bufferZone = document.getElementById('buffer-zone');
          if (bufferZone) bufferZone.style.display = 'none';
        }

      } catch (error) {
        console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_suppression_job'] || 'Erreur suppression job:'), error);
        bufferJobs.delete(spoolJobId);
        bufferJobs.delete(Number(spoolJobId));
        if (rowToHide) rowToHide.remove();
        if (bufferJobs.size === 0) {
          const bufferZone = document.getElementById('buffer-zone');
          if (bufferZone) bufferZone.style.display = 'none';
        }
      }
    });
  };

  window.toggleAllBuffer = function (master) {
    const checkboxes = document.querySelectorAll('.buffer-checkbox');
    checkboxes.forEach(cb => cb.checked = master.checked);
    updateBulkActionsVisibility();
  };

  window.updateBulkActionsVisibility = function () {
    const selected = document.querySelectorAll('.buffer-checkbox:checked');
    const bulkActions = document.getElementById('buffer-bulk-actions');
    if (!bulkActions) return;

    if (selected.length > 0) {
      bulkActions.style.display = 'block';
      const btnAdd = bulkActions.querySelector('button:first-child');
      const btnDel = bulkActions.querySelector('button:last-child');
      if (btnAdd) btnAdd.innerHTML = '<i class="fa fa-plus"></i> ' + S['auto_tirage.add_selected'] + ' (' + selected.length + ')';
      if (btnDel) btnDel.innerHTML = '<i class="fa fa-trash"></i> ' + S['auto_tirage.delete_selected'] + ' (' + selected.length + ')';
    } else {
      bulkActions.style.display = 'none';
      const selectAll = document.getElementById('select-all-buffer');
      if (selectAll) selectAll.checked = false;
    }
  };

  window.bulkMoveBufferToSession = async function () {
    const selected = document.querySelectorAll('.buffer-checkbox:checked');
    if (selected.length === 0) return;

    addLog('process', '🚀 ' + translate('auto_tirage.adding_jobs', { count: selected.length }));

    for (const cb of selected) {
      const jobId = cb.getAttribute('data-job-id');
      await window.moveBufferToSession(jobId);
    }

    const selectAll = document.getElementById('select-all-buffer');
    if (selectAll) selectAll.checked = false;
    updateBulkActionsVisibility();
  };

  window.bulkDeleteBufferJob = async function () {
    const selected = document.querySelectorAll('.buffer-checkbox:checked');
    if (selected.length === 0) return;

    const dbIds = Array.from(selected).map(cb => cb.getAttribute('data-id'));
    const spoolJobIds = Array.from(selected).map(cb => cb.getAttribute('data-job-id'));

    showAppModal({
      message: translate('auto_tirage.confirm_delete_many', { count: selected.length }),
      confirm: true,
      type: "warning"
    }, async (confirmed) => {
      if (!confirmed) return;

      spoolJobIds.forEach(id => {
        const r = document.getElementById('buffer-row-' + id);
        if (r) r.style.opacity = '0.5';
      });

      try {
        const response = await fetch('?check_print_jobs', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'delete_jobs',
            ids: dbIds
          })
        });

        const result = await response.json();
        if (result.success) {
          addLog('info', '🗑️ ' + translate('auto_tirage.jobs_deleted', { count: selected.length }));
          spoolJobIds.forEach(spoolJobId => {
            bufferJobs.delete(spoolJobId);
            const row = document.getElementById('buffer-row-' + spoolJobId);
            if (row) row.remove();
          });

          if (bufferJobs.size === 0) {
            document.getElementById('buffer-zone').style.display = 'none';
          }
          const selectAll = document.getElementById('select-all-buffer');
          if (selectAll) selectAll.checked = false;
          updateBulkActionsVisibility();
        } else {
          showAppModal({ message: S['auto_tirage.delete_error'] + ": " + result.error, type: "danger" });
        }
      } catch (error) {
        console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_suppression_jobs'] || 'Erreur suppression jobs:'), error);
        spoolJobIds.forEach(id => {
          const r = document.getElementById('buffer-row-' + id);
          if (r) r.style.opacity = '1';
        });
        showAppModal({ message: S['auto_tirage.communication_error'], type: "danger" });
      }
    });
  };

  // ── Job simulation / save ──────────────────────────────────────────────────

  async function simulateJob(job, updateIndex, bufferJobId, isSimulation) {
    addLog('process', '⚙️ ' + S['auto_tirage.analyzing_job'] + ' : ' + job.document + '...');

    try {
      const copies = job.copies || 1;
      const pagesPerCopy = job.total_pages;
      const globalTotalPages = job.total_pages * copies;

      const isDuplex = (job.duplex == 1 || job.duplex == '1' || job.duplex === true || String(job.duplex).toLowerCase() === 'oui');

      let rawFillRate = job.fill_rate;
      let parsedFillRate = (rawFillRate !== undefined && rawFillRate !== null) ? parseFloat(rawFillRate) : 0.5;

      if (parsedFillRate > 1.0) parsedFillRate = parsedFillRate / 100.0;

      const paperMapSimulate = { '9': 'A4', '8': 'A3', '11': 'A5', '1': 'Letter', '5': 'Legal' };
      const mappedPaperSize = paperMapSimulate[String(job.paper_size)] || job.paper_size || 'A4';

      const payload = {
        printerName: job.printer_name,
        pages: pagesPerCopy,
        contact: sessionUser,
        document: job.document,
        copies: copies,
        total_pages: globalTotalPages,
        duplex: isDuplex,
        color_mode: job.color_mode,
        paper_size: mappedPaperSize,
        fill_rate: parsedFillRate,
        thumbnail_url: job.thumbnail_url,
        timestamp: job.timestamp,

        job_id: job.job_id,
        internal_id: job.id,
        session_id: currentSessionId,
        simulate: isSimulation
      };

      const response = await fetch('?save_auto_print', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result.success) {
        if (result.debug_info) {
          addLog('info', '🔧 DEBUG: ' + result.debug_info);
          console.log('DEBUG INFO:', result.debug_info);
        }
        if (result.already_recorded) {
          console.log('Job déjà enregistré, ignoré:', result.message || '');
          addLog('info', '⏭️ ' + S['auto_tirage.already_recorded']);
          return true;
        }
        if (!result.details) {
          console.error('CRITICAL: result.details is null/undefined', result);
          addLog('error', '❌ ' + S['auto_tirage.internal_error']);
          return false;
        }
        result.details.raw_total_pages = job.total_pages;
        result.details.raw_fill_rate = parsedFillRate;
        result.details.document_name = job.document || job.document_name;
        result.details.thumbnail_url = job.thumbnail_url;

        console.log('SIMULATE_RESULT:', result.details);

        if (updateIndex !== null && sessionJobs[updateIndex] && sessionJobs[updateIndex].id && !result.details.id) {
          result.details.id = sessionJobs[updateIndex].id;
        }

        if (updateIndex !== undefined && updateIndex !== null) {
          addLog('info', '🔄 ' + S['auto_tirage.updating_job'] + ' : ' + job.total_pages + ' pages');
          updateJobInSession(result.details, updateIndex, job.printer_name);
          return true;
        } else {
          addLog('success', '✅ ' + job.document + ' : ' + result.details.price + ' €');
          addJobToSession(result.details, job.job_id, job.printer_name);

          if (window.electronAPI && window.electronAPI.deletePrintJob) {
            addLog('info', '🗑️ ' + S['auto_tirage.delete_spooler']);
            window.electronAPI.deletePrintJob(job.printer_name, job.job_id)
              .then(res => {
                if (res.success) addLog('success', '🗑️ ' + S['auto_tirage.spooler_cleaned']);
                else console.warn((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_suppression_spool'] || 'Erreur suppression spool:'), res.error);
              })
              .catch(err => console.error(err));
          }

          setTimeout(() => {
            cleanLogs();
          }, 10000);

          return true;
        }
      } else {
        addLog('error', '❌ Erreur: ' + result.error);
        return false;
      }

    } catch (e) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_lors_de_la_simulation_s'] || 'Erreur lors de la simulation/sauvegarde du job:'), e);
      addLog('error', '❌ ' + S['auto_tirage.communication_error']);
      if (bufferJobId) {
        console.log('Échec sauvegarde job du buffer, annulation mouvement.');
      } else {
        const indexToRemove = sessionJobs.findIndex(sj => sj.originalJobId == job.job_id && !sj.id);
        if (indexToRemove !== -1) {
          sessionJobs.splice(indexToRemove, 1);
          renderSessionTable();
          console.log('Job ' + job.job_id + ' removed from local session due to save failure.');
        }
      }
      return false;
    }
  }

  // ── Session jobs management ────────────────────────────────────────────────

  function addJobToSession(details, jobId, printerName) {
    if (sessionJobs.some(existingJob =>
      existingJob.originalJobId == jobId &&
      (existingJob.printerName || existingJob.machine) === (printerName || details.machine)
    )) {
      console.warn('[AUTO_TIRAGE] Job (' + jobId + ', ' + printerName + ') déjà présent dans la session, ignoré.');
      return;
    }

    details.localId = Date.now() + Math.random().toString(36).substr(2, 9);
    details.originalJobId = jobId;
    details.printerName = printerName;
    details.feuilles_payees = false;

    if (details.type === 'duplicopieur') {
      details.unit_master = details.nb_masters > 0 ? (details.cout_masters / details.nb_masters) : 0;
      details.unit_passage = details.nb_passages > 0 ? (details.cout_passages / details.nb_passages) : 0;
      details.unit_papier = details.nb_feuilles > 0 ? (details.cout_papier / details.nb_feuilles) : 0;

      let lastJob = null;
      for (let i = sessionJobs.length - 1; i >= 0; i--) {
        if (sessionJobs[i].machine === details.machine) {
          lastJob = sessionJobs[i];
          break;
        }
      }

      if (lastJob) {
        details.master_av = lastJob.master_ap;
        details.passage_av = lastJob.passage_ap;
        details.master_ap = details.master_av + details.nb_masters;
        details.passage_ap = details.passage_av + details.nb_passages;
      }
    }

    sessionJobs.push(details);
    saveSession();
    renderSessionTable();
  }

  function updateJobInSession(newDetails, index, printerName) {
    const oldJob = sessionJobs[index];
    if (!oldJob) return;

    for (let key in oldJob) {
      if (!newDetails[key] && oldJob[key]) {
        newDetails[key] = oldJob[key];
      }
    }

    newDetails.localId = oldJob.localId;
    newDetails.originalJobId = oldJob.originalJobId;
    newDetails.printerName = printerName || oldJob.printerName;

    if (newDetails.type === 'duplicopieur') {
      newDetails.unit_master = newDetails.nb_masters > 0 ? (newDetails.cout_masters / newDetails.nb_masters) : 0;
      newDetails.unit_passage = newDetails.nb_passages > 0 ? (newDetails.cout_passages / newDetails.nb_passages) : 0;
      newDetails.unit_papier = newDetails.nb_feuilles > 0 ? (newDetails.cout_papier / newDetails.nb_feuilles) : 0;
    }

    sessionJobs[index] = newDetails;
    saveSession();
    renderSessionTable();

    const row = document.getElementById('pending-jobs-body').children[index];
    if (row) {
      row.style.backgroundColor = '#fff3cd';
      setTimeout(() => row.style.backgroundColor = '', 1000);
    }
  }

  function saveSession() {
    if (!currentSessionId) return;
    sessionStorage.setItem('auto_tirage_session_jobs_' + currentSessionId, JSON.stringify(sessionJobs));
    sessionStorage.setItem('auto_tirage_session_user', sessionUser);
  }

  // ── Render session table ───────────────────────────────────────────────────

  function renderSessionTable() {
    const container = document.getElementById('pending-list-container');
    const tbody = document.getElementById('pending-jobs-body');
    const totalSpan = document.getElementById('session-total');
    const badge = document.getElementById('finish-badge');

    container.style.display = 'block';
    tbody.innerHTML = '';

    let globalTotal = 0;

    sessionJobs.forEach((job, index) => {
      job.price = (typeof job.price === 'number') ? job.price : parseFloat(job.price || 0);
      job.cout_papier = (typeof job.cout_papier === 'number') ? job.cout_papier : parseFloat(job.cout_papier || 0);
      job.cout_masters = (typeof job.cout_masters === 'number') ? job.cout_masters : parseFloat(job.cout_masters || 0);
      job.cout_passages = (typeof job.cout_passages === 'number') ? job.cout_passages : parseFloat(job.cout_passages || 0);
      job.cout_encre = (typeof job.cout_encre === 'number') ? job.cout_encre : parseFloat(job.cout_encre || 0);

      let currentPrice = job.price;
      if (job.feuilles_payees && job.cout_papier) {
        currentPrice = Math.max(0, currentPrice - job.cout_papier);
      }
      if (isNaN(currentPrice)) currentPrice = 0;

      globalTotal += currentPrice;

      const tr = document.createElement('tr');

      const badgeClass = job.type === 'photocop' ? 'badge-primary' : 'badge-secondary';
      const machineName = job.type === 'photocop' ? S['tirage_multimachines.photocopieur'] : S['tirage_multimachines.duplicopieur'];

      const docName = job.document_name || job.document || S['library.file'];

      let thumbHtml = '';
      const thumbUrl = job.thumbnail_url;
      if (thumbUrl) {
        thumbHtml = '<img src="' + thumbUrl + '" alt="' + S['auto_tirage.preview'] + '" class="img-thumbnail rounded mr-2" style="width: 50px; height: 50px; object-fit: contain; cursor: pointer;" onclick="event.stopPropagation(); showThumbnailModal(\'' + thumbUrl + '\', \'' + docName.replace(/'/g, "\\'") + '\')">';
      } else {
        thumbHtml = '<div class="d-inline-flex align-items-center justify-content-center bg-light text-muted border rounded mr-2" style="width: 50px; height: 50px;"><i class="fa fa-file-o fa-lg"></i></div>';
      }

      tr.classList.add('editable-job-row');
      tr.style.position = 'relative';

      tr.onclick = (e) => {
        if (e.target.tagName === 'BUTTON' ||
          e.target.tagName === 'INPUT' ||
          e.target.tagName === 'SELECT' ||
          e.target.tagName === 'LABEL' ||
          e.target.tagName === 'IMG' ||
          e.target.closest('.custom-control') ||
          e.target.closest('button') ||
          e.target.closest('.img-thumbnail')) {
          return;
        }
        openEditJobModal(index);
      };

      const colMachine = '' +
        '<td class="align-middle">' +
        '<span class="badge ' + badgeClass + '">' + machineName + '</span><br>' +
        '<small class="text-muted" style="font-size: 0.8em;">' + (job.machine || '') + '</small>' +
        '</td>';

      const colDoc = '' +
        '<td class="align-middle" style="max-width: 250px;">' +
        '<div class="d-flex align-items-center" style="max-width: 100%;">' +
        thumbHtml +
        '<div style="line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">' +
        '<strong style="font-size: 0.95em;" title="' + docName.replace(/"/g, '&quot;') + '">' + docName + '</strong><br>' +
        '<small class="text-muted">' + Math.round(job.pages / job.copies) + ' Pg ' + (job.copies > 1 ? '× ' + job.copies + ' Ex' : '') + '</small>' +
        '</div>' +
        '</div>' +
        '</td>';

      let colDetails = '';
      let colPaidInDetails = '';

      if (job.type === 'photocop') {
        const pPerEx = Math.round(job.pages / job.copies);
        colDetails = '' +
          '<div style="font-size: 0.9em;">' +
          job.copies + ' ex × ' + pPerEx + ' pg.<br>' +
          '<small>' + (job.duplex ? S['common.duplex'] : S['common.simplex']) + ' - ' + ((job.taille && job.taille !== 'undefined') ? job.taille : 'A4') + '</small>' +
          (job.color && job.fill_rate_percent ? '<br><small class="text-muted">' + S['auto_tirage.fill_rate'] + ': ' + job.fill_rate_percent + '%</small>' : '') +
          '</div>';
      } else {
        const masterAv = job.master_av !== null ? job.master_av : 0;
        const masterAp = job.master_ap !== null ? job.master_ap : 0;
        const passageAv = job.passage_av !== null ? job.passage_av : 0;
        const passageAp = job.passage_ap !== null ? job.passage_ap : 0;

        let tambourSelect = '';
        if (job.tambours && job.tambours.length > 0) {
          let options = job.tambours.map(t =>
            '<option value="' + t.value + '" ' + (t.value === (job.selected_tambour || 'tambour_noir') ? 'selected' : '') + ' data-price="' + t.price + '">' + t.label + '</option>'
          ).join('');
          tambourSelect = '' +
            '<select class="form-control form-control-sm py-0 border-secondary mr-2" style="height: 24px; font-size: 11px; width: auto; min-width: 80px;" onchange="updateTambour(' + index + ', this)">' +
            options +
            '</select>';

          if (!job.selected_tambour) {
            const noir = job.tambours.find(t => t.value === 'tambour_noir');
            if (noir) job.selected_tambour = 'tambour_noir';
            else job.selected_tambour = job.tambours[0].value;
          }
        }

        colDetails = '' +
          '<div style="min-width: 320px;">' +
          '<div style="display: flex; align-items: center; white-space: nowrap; margin-bottom: 5px; padding: 4px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;">' +
          '<span class="mr-1" style="font-size: 11px; font-weight:bold;">' + S['tirage_multimachines.tambour_used'] + ':</span>' +
          tambourSelect +
          '<div class="custom-control custom-checkbox mr-3" style="display: inline-flex; align-items: center;">' +
          '<input type="checkbox" class="custom-control-input" id="duplex-' + index + '" ' + (job.duplex ? 'checked' : '') + ' onchange="toggleDuplex(' + index + ')">' +
          '<label class="custom-control-label" for="duplex-' + index + '" style="font-size: 11px; padding-top: 2px; margin-bottom: 0;">' + S['common.duplex'] + '</label>' +
          '</div>' +
          '<div class="custom-control custom-checkbox" style="display: inline-flex; align-items: center;">' +
          '<input type="checkbox" class="custom-control-input" id="paid-details-' + index + '" ' + (job.feuilles_payees ? 'checked' : '') + ' onchange="togglePaid(' + index + ')">' +
          '<label class="custom-control-label" for="paid-details-' + index + '" style="font-size: 11px; padding-top: 2px; margin-bottom: 0;">' + S['auto_tirage.paper_paid'] + '</label>' +
          '</div>' +
          '</div>' +
          '<div class="card p-1 border bg-light" style="border-radius: 4px;">' +
          '<table class="table table-borderless table-sm mb-0" style="font-size: 11px;">' +
          '<thead>' +
          '<tr class="text-muted text-center" style="line-height: 1;">' +
          '<th class="py-0 px-1 text-left">' + S['admin_machines.counter'] + '</th>' +
          '<th class="py-0 px-1">' + S['common.before'] + '</th>' +
          '<th class="py-0 px-1">' + S['common.after'] + '</th>' +
          '<th class="py-0 px-1 text-right">' + S['common.total'] + '</th>' +
          '</tr>' +
          '</thead>' +
          '<tbody>' +
          '<tr>' +
          '<td class="py-1 px-1 align-middle text-left"><strong>Master</strong></td>' +
          '<td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center" style="height: 20px; font-size: 11px;" value="' + masterAv + '" onchange="updateCounterAndCalc(\'master_av\', ' + index + ', this.value)"></td>' +
          '<td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center font-weight-bold" style="height: 20px; font-size: 11px;" value="' + masterAp + '" onchange="updateCounterAndCalc(\'master_ap\', ' + index + ', this.value)"></td>' +
          '<td class="py-1 px-1 text-right align-middle"><span class="badge badge-light border text-dark" id="diff-master-' + index + '">' + job.nb_masters + '</span></td>' +
          '</tr>' +
          '<tr>' +
          '<td class="py-1 px-1 align-middle text-left"><strong>Passage</strong></td>' +
          '<td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center" style="height: 20px; font-size: 11px;" value="' + passageAv + '" onchange="updateCounterAndCalc(\'passage_av\', ' + index + ', this.value)"></td>' +
          '<td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center font-weight-bold" style="height: 20px; font-size: 11px;" value="' + passageAp + '" onchange="updateCounterAndCalc(\'passage_ap\', ' + index + ', this.value)"></td>' +
          '<td class="py-1 px-1 text-right align-middle"><span class="badge badge-light border text-dark" id="diff-passage-' + index + '">' + job.nb_passages + '</span></td>' +
          '</tr>' +
          '</tbody>' +
          '</table>' +
          '</div>' +
          '</div>';
      }

      const colPapier = '<td class="align-middle text-right"><small id="cout-papier-' + index + '" class="text-muted">' + (job.cout_papier ? job.cout_papier.toFixed(2) + ' €' : '-') + '</small></td>';

      let inkPriceDisplay = '-';
      if (job.type === 'photocop') {
        inkPriceDisplay = job.cout_encre ? job.cout_encre.toFixed(2) + ' €' : '-';
      } else {
        inkPriceDisplay = (job.cout_masters + job.cout_passages).toFixed(2) + ' €';
      }
      const colEncre = '<td class="align-middle text-right"><small id="cout-encre-' + index + '" class="text-muted">' + inkPriceDisplay + '</small></td>';
      const colTotal = '<td class="align-middle text-right"><strong id="total-price-' + index + '" class="text-dark" style="font-size: 1.1em;">' + currentPrice.toFixed(2) + ' €</strong></td>';

      let colPaid = '';
      if (job.type === 'photocop') {
        colPaid = '' +
          '<td class="align-middle text-center">' +
          '<div class="custom-control custom-switch">' +
          '<input type="checkbox" class="custom-control-input" id="paid-' + index + '" ' + (job.feuilles_payees ? 'checked' : '') + ' onchange="togglePaid(' + index + ')">' +
          '<label class="custom-control-label" for="paid-' + index + '"></label>' +
          '</div>' +
          '</td>';
      } else {
        colPaid = '<td class="align-middle text-center"><small class="text-muted">-</small></td>';
      }

      const colAction = '' +
        '<td class="align-middle text-center" style="white-space: nowrap; width: 80px;">' +
        '<div class="d-flex justify-content-center align-items-center gap-2">' +
        '<button class="btn btn-sm btn-outline-info shadow-sm mr-1" ' +
        'style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" ' +
        'onclick="refreshJobAnalysis(\'' + job.originalJobId + '\', this)" title="' + S['common.refresh'] + '">' +
        '<i class="fa fa-refresh"></i>' +
        '</button>' +
        '<button class="btn btn-sm btn-outline-primary shadow-sm mr-1" ' +
        'style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" ' +
        'onclick="openEditJobModal(' + index + ')" title="' + S['common.edit'] + '">' +
        '<i class="fa fa-pencil"></i>' +
        '</button>' +
        '<button class="btn btn-sm btn-outline-danger shadow-sm" ' +
        'style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" ' +
        'onclick="removeJob(' + index + ')" title="' + S['common.delete'] + '">' +
        '<i class="fa fa-trash"></i>' +
        '</button>' +
        '</div>' +
        '</td>';

      tr.innerHTML = colMachine + colDoc + '<td class="p-2 align-middle">' + colDetails + '</td>' + colPapier + colEncre + colTotal + colPaid + colAction;
      tbody.appendChild(tr);
    });

    totalSpan.textContent = globalTotal.toFixed(2);
    badge.textContent = sessionJobs.length;
  }

  // ── Duplicopieur counters ──────────────────────────────────────────────────

  window.updateCounterAndCalc = function (field, index, value) {
    let job = sessionJobs[index];
    const val = parseInt(value) || 0;

    job[field] = val;

    if (field === 'master_av' || field === 'master_ap') {
      const av = job.master_av || 0;
      const ap = job.master_ap || 0;
      job.nb_masters = Math.max(0, ap - av);
      job.cout_masters = job.nb_masters * (job.unit_master || 0);
    }

    if (field === 'passage_av' || field === 'passage_ap') {
      const av = job.passage_av || 0;
      const ap = job.passage_ap || 0;
      job.nb_passages = Math.max(0, ap - av);
      job.cout_passages = job.nb_passages * (job.unit_passage || 0);
      recalcPaper(job);
    }

    recalcTotal(index);
    saveSession();

    const mBadge = document.getElementById('diff-master-' + index);
    if (mBadge) mBadge.textContent = job.nb_masters + ' M';

    const pBadge = document.getElementById('diff-passage-' + index);
    if (pBadge) pBadge.textContent = job.nb_passages + ' P';
  };

  window.updateTambour = function (index, selectElement) {
    let job = sessionJobs[index];
    const selectedValue = selectElement.value;
    const selectedPrice = parseFloat(selectElement.options[selectElement.selectedIndex].dataset.price || 0);

    job.selected_tambour = selectedValue;

    let effectivePrice = selectedPrice;
    if (job.taille === 'A4') {
      effectivePrice = effectivePrice / 2;
    }

    job.unit_passage = effectivePrice;
    job.cout_passages = job.nb_passages * job.unit_passage;

    recalcTotal(index);
    saveSession();
  };

  window.toggleDuplex = function (index) {
    let job = sessionJobs[index];
    job.duplex = !job.duplex;

    recalcPaper(job);
    recalcTotal(index);
    saveSession();
  };

  function recalcPaper(job) {
    if (job.type !== 'duplicopieur') return;

    let sheets = job.nb_passages;
    if (job.duplex) {
      sheets = Math.ceil(job.nb_passages / 2);
    }
    job.nb_feuilles = sheets;
    job.cout_papier = sheets * (job.unit_papier || 0);
  }

  function recalcTotal(index) {
    let job = sessionJobs[index];

    let total = (job.cout_masters || 0) + (job.cout_passages || 0) + (job.cout_papier || 0) + (job.cout_encre || 0);
    job.price = parseFloat(total.toFixed(2));

    let finalPrice = job.price;
    if (job.feuilles_payees) {
      finalPrice = Math.max(0, finalPrice - (job.cout_papier || 0));
    }

    const elPapier = document.getElementById('cout-papier-' + index);
    if (elPapier) elPapier.textContent = job.cout_papier.toFixed(2) + ' €';

    const elEncre = document.getElementById('cout-encre-' + index);
    if (elEncre) {
      const ink = (job.type === 'photocop') ? (job.cout_encre || 0) : ((job.cout_masters || 0) + (job.cout_passages || 0));
      elEncre.textContent = ink.toFixed(2) + ' €';
    }

    const elTotal = document.getElementById('total-price-' + index);
    if (elTotal) elTotal.textContent = finalPrice.toFixed(2) + ' €';

    let globalTotal = 0;
    sessionJobs.forEach(j => {
      let p = j.price;
      if (j.feuilles_payees) p -= (j.cout_papier || 0);
      globalTotal += Math.max(0, p);
    });
    document.getElementById('session-total').textContent = globalTotal.toFixed(2);
  }

  window.togglePaid = function (index) {
    sessionJobs[index].feuilles_payees = !sessionJobs[index].feuilles_payees;
    recalcTotal(index);
    saveSession();
  };

  window.refreshJobAnalysis = async function (jobId, btn, printerName) {
    if (!window.electronAPI || !window.electronAPI.reanalyzePrintJob) {
      return showAppModal({ message: "API Electron non disponible", type: "warning" });
    }

    const icon = btn.querySelector('i');
    icon.classList.add('fa-spin');
    btn.disabled = true;

    try {
      addLog('process', (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.r__analyse_forc_e_pour_le_jo'] || '🔄 Ré-analyse forcée pour le job ') + jobId + ' (' + printerName + ')...');
      const result = await window.electronAPI.reanalyzePrintJob(jobId);

      if (result.success) {
        addLog('process', '📊 Résultat: ' + result.totalPages + ' pages, ' + (result.fillRate ? result.fillRate.toFixed(1) : 0) + '% fillRate, ' + (result.isGrayscale ? 'N&B' : 'Couleur'));

        const response = await fetch('?check_print_jobs', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_job_analysis',
            job_id: jobId,
            printer_name: printerName,
            thumbnail_url: result.thumbnailUrl || '',
            fill_rate: result.fillRate || 0,
            is_grayscale: result.isGrayscale,
            total_pages: result.totalPages || 0
          })
        });
        const updateResult = await response.json();

        if (updateResult.success) {
          addLog('success', '✅ Mise à jour DB: ' + result.totalPages + ' pages, ' + (result.fillRate ? result.fillRate.toFixed(1) : 0) + '%');
        } else {
          addLog('error', '⚠️ Mise à jour DB échouée: ' + updateResult.error);
        }

        await checkPrintJobs();
      } else {
        addLog('error', (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.chec_de_la_r__analyse'] || '❌ Échec de la ré-analyse: ') + result.error);
      }
    } catch (e) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_r__analyse'] || 'Erreur ré-analyse:'), e);
      addLog('error', (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_lors_de_l__appel___la'] || '❌ Erreur lors de l\'appel à la ré-analyse'));
    } finally {
      icon.classList.remove('fa-spin');
      btn.disabled = false;
    }
  };

  window.removeJob = async function (index) {
    const job = sessionJobs[index];
    showAppModal({
      message: S['common.delete'] + ' ?',
      confirm: true,
      type: 'warning'
    }, async (confirmed) => {
      if (!confirmed) return;

      if (job.job_id && window.electronAPI && window.electronAPI.deletePrintJob) {
        try {
          console.log((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.delete_session__appel_ipc_del'] || '[DELETE SESSION] Appel IPC deletePrintJob pour job Windows:'), job.job_id);
          const ipcResult = await window.electronAPI.deletePrintJob(job.printer_name || null, job.job_id);
          console.log('[DELETE SESSION] Résultat IPC:', ipcResult);
        } catch (ipcError) {
          console.warn((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.delete_session__erreur_ipc__n'] || '[DELETE SESSION] Erreur IPC (non bloquante):'), ipcError);
        }
      }

      if (job.id) {
        try {
          let apiType = 'print_jobs';
          if (!job.staged) {
            apiType = job.type === 'duplicopieur' ? 'dupli' : job.type;
          }

          const resp = await fetch('?delete_session_job&id=' + job.id + '&type=' + apiType);
          const result = await resp.json();
          if (!result.success) {
            console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_serveur_lors_de_la_supp'] || 'Erreur serveur lors de la suppression:'), result.error);
          }
        } catch (e) {
          console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.erreur_r_seau_lors_de_la_suppr'] || 'Erreur réseau lors de la suppression:'), e);
        }
      }

      sessionJobs.splice(index, 1);
      saveSession();
      renderSessionTable();
      if (sessionJobs.length === 0) {
        document.getElementById('pending-list-container').style.display = 'none';
        if (currentSessionId) {
          sessionStorage.removeItem('auto_tirage_session_jobs_' + currentSessionId);
        }
      }
    });
  };

  // ── Activity logs ──────────────────────────────────────────────────────────

  function addLog(type, message) {
    const logContainer = document.getElementById('activity-log');
    const div = document.createElement('div');
    const alertType = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'process' ? 'warning' : 'info'));
    div.className = 'alert alert-' + alertType + ' py-2 mb-2';

    div.dataset.type = type;

    const time = new Date().toLocaleTimeString();
    div.innerHTML = '<strong>[' + time + ']</strong> ' + message;
    logContainer.prepend(div);

    if (logContainer.children.length > 8) {
      logContainer.lastChild.remove();
    }
  }

  function cleanLogs() {
    const logContainer = document.getElementById('activity-log');
    logContainer.innerHTML = '';
    addLog('info', '✅ ' + S['auto_tirage.system_ready']);
  }

  window.toggleLogs = function () {
    const el = document.getElementById('activity-log');
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';

    const btn = document.querySelector('button[onclick="toggleLogs()"]');
    if (btn) {
      btn.innerHTML = isHidden ? '<i class="fa fa-list"></i> Masquer l\'activité' : '<i class="fa fa-list"></i> Voir l\'activité';
    }
  };

  // ── Multi-session ──────────────────────────────────────────────────────────

  async function loadActiveSessions() {
    try {
      const response = await fetch('?sessions&action=list');
      const data = await response.json();
      activeSessions = data.sessions || [];

      renderSessionTabs();

      console.log('[AutoTirage] Sessions chargées:', activeSessions.length);

      if (activeSessions.length === 0 && !currentSessionId) {
        createNewSessionClick();
      } else if (!currentSessionId && activeSessions.length > 0) {
        const lastId = localStorage.getItem('auto_tirage_last_session_id');
        const sessionToSelect = (lastId && activeSessions.some(s => s.id == lastId))
          ? parseInt(lastId)
          : activeSessions[0].id;
        console.log('[AutoTirage] Auto-sélection session:', sessionToSelect);
        switchSession(sessionToSelect);
      }
    } catch (error) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.autotirage__erreur_chargement'] || '[AutoTirage] Erreur chargement sessions:'), error);
    }
  }

  function renderSessionTabs() {
    const container = document.getElementById('session-tabs-container');
    const addButton = container.querySelector('.add-session-tab');

    const oldTabs = container.querySelectorAll('.session-tab');
    oldTabs.forEach(tab => tab.remove());

    activeSessions.forEach(session => {
      const tab = document.createElement('div');
      tab.className = 'session-tab ' + (currentSessionId == session.id ? 'active' : '');
      tab.onclick = (e) => {
        if (!e.target.classList.contains('close-tab') && !e.target.parentElement.classList.contains('close-tab')) {
          switchSession(session.id);
        }
      };

      const name = document.createElement('span');
      name.textContent = session.contact + (session.session_name ? ' (' + session.session_name + ')' : '');

      const closeBtn = document.createElement('span');
      closeBtn.className = 'close-tab';
      closeBtn.innerHTML = '<i class="fa fa-times"></i>';
      closeBtn.onclick = (e) => {
        e.stopPropagation();
        quitSession(session.id);
      };

      tab.appendChild(name);
      tab.appendChild(closeBtn);

      container.insertBefore(tab, addButton);
    });
  }

  function createNewSessionClick() {
    currentSessionId = null;
    sessionUser = '';
    sessionJobs = [];
    processedJobIds.clear();

    document.getElementById('step-identity').style.display = 'block';
    document.getElementById('step-listening').style.display = 'none';

    document.querySelectorAll('.session-tab').forEach(t => t.classList.remove('active'));
  }

  async function switchSession(sessionId) {
    if (sessionId) {
      const session = activeSessions.find(s => s.id == sessionId);
      if (session) {
        saveSession();

        currentSessionId = sessionId;
        sessionUser = session.contact;

        document.getElementById('pseudo-input').value = sessionUser;
        document.getElementById('session-name-input').value = session.session_name || '';

        sessionStorage.setItem('auto_tirage_session_user', sessionUser);
        localStorage.setItem('auto_tirage_user', sessionUser);

        document.getElementById('step-identity').style.display = 'none';
        document.getElementById('step-listening').style.display = 'block';

        console.log('[AutoTirage] Session sélectionnée:', session.contact);

        renderSessionTabs();

        sessionJobs = [];
        const saved = sessionStorage.getItem('auto_tirage_session_jobs_' + sessionId);
        if (saved) {
          try {
            sessionJobs = JSON.parse(saved);
          } catch (e) { sessionJobs = []; }
        }

        await loadSessionJobs(sessionId);

        localStorage.setItem('auto_tirage_last_session_id', sessionId);

        if (!pollingInterval) startPolling();
      }
    } else {
      suspendSession();
    }
  }

  async function loadSessionJobs(sessionId) {
    try {
      const response = await fetch('?get_session_jobs&session_id=' + sessionId);
      const data = await response.json();

      if (data.jobs && data.jobs.length > 0) {
        data.jobs.forEach(job => {
          const exists = sessionJobs.some(sj => {
            if (sj.originalJobId != null && job.job_id != null) {
              return String(sj.originalJobId) === String(job.job_id) &&
                (sj.printerName || sj.machine) === (job.printer_name || job.printerName);
            }
            if (sj.id != null && job.id != null) {
              return String(sj.id) === String(job.id) && sj.type === job.table_source;
            }
            return false;
          });
          if (!exists) {
            sessionJobs.push({
              id: job.id,
              type: job.table_source,
              machine: job.printerName,
              machine_id: job.printerName,
              copies: parseInt(job.copies) || 1,
              pages: parseInt(job.pages) || 0,
              document: job.document || 'Document',
              price: parseFloat(job.prix) || 0,
              cout_papier: parseFloat(job.paper_cost) || 0,
              cout_encre: parseFloat(job.ink_cost) || 0,
              feuilles_payees: job.papierPaye === 'oui',
              staged: !!job.staged,
              thumbnail_url: job.thumbnail_url,
              timestamp: job.date
            });
          }
        });

        renderSessionTable();
        addLog('info', '📥 ' + data.jobs.length + ' jobs chargés de la session');
      } else if (data.jobs && data.jobs.length === 0) {
        if (sessionJobs.length === 0) renderSessionTable();
      }
    } catch (error) {
      console.error((window.CONFIG && window.CONFIG.translations && window.CONFIG.translations['js.auto_tirage.autotirage__erreur_chargement'] || '[AutoTirage] Erreur chargement jobs session:'), error);
    }
  }

  // ── Modals / thumbnail ─────────────────────────────────────────────────────

  window.showThumbnailModal = function (url, title) {
    const modal = $('#thumbnail-modal');
    $('#modal-thumbnail-img').attr('src', url);
    $('#thumbnail-modal-title').text(title || S['auto_tirage.document_preview']);
    modal.modal('show');
  };

  let currentEditingIndex = -1;

  window.openEditJobModal = function (index) {
    const job = sessionJobs[index];
    if (!job) return;

    currentEditingIndex = index;

    document.getElementById('edit-document-name').value = job.document_name || job.document || 'Document';

    document.getElementById('edit-photocop-fields').style.display = 'none';
    document.getElementById('edit-dupli-fields').style.display = 'none';

    if (job.type === 'photocop') {
      document.getElementById('edit-photocop-fields').style.display = 'block';

      const copies = parseInt(job.copies) || 1;
      document.getElementById('edit-copies').value = copies;

      const pEx = Math.round(job.pages / copies);
      document.getElementById('edit-pages').value = pEx || 1;
      document.getElementById('edit-paper-size').value = job.taille || 'A4';
      document.getElementById('edit-color').checked = !!job.color;
      document.getElementById('edit-duplex').checked = !!job.duplex;

      let fr = 0;
      if (job.fill_rate_percent) fr = parseFloat(job.fill_rate_percent);
      else if (job.raw_fill_rate) fr = parseFloat(job.raw_fill_rate) * 100;

      document.getElementById('edit-fill-rate').value = Math.round(fr);

    } else if (job.type === 'duplicopieur') {
      document.getElementById('edit-dupli-fields').style.display = 'block';

      document.getElementById('edit-masters').value = job.nb_masters || 0;
      document.getElementById('edit-passages').value = job.nb_passages || 0;
      document.getElementById('edit-dupli-duplex').checked = !!job.duplex;

      const tambourSelect = document.getElementById('edit-tambour');
      tambourSelect.innerHTML = '';
      if (job.tambours && job.tambours.length > 0) {
        job.tambours.forEach(t => {
          const opt = document.createElement('option');
          opt.value = t.value;
          opt.text = t.label;
          opt.dataset.price = t.price;
          if (t.value === (job.selected_tambour || 'tambour_noir')) {
            opt.selected = true;
          }
          tambourSelect.appendChild(opt);
        });
      } else {
        const opt = document.createElement('option');
        opt.value = 'tambour_noir';
        opt.text = 'Noir';
        tambourSelect.appendChild(opt);
      }
    }

    $('#edit-job-modal').modal('show');
  };

  window.saveEditedJob = function () {
    if (currentEditingIndex === -1) return;
    let job = sessionJobs[currentEditingIndex];

    if (job.type === 'photocop') {
      const copies = parseInt(document.getElementById('edit-copies').value) || 1;
      const size = document.getElementById('edit-paper-size').value;
      const color = document.getElementById('edit-color').checked;
      const duplex = document.getElementById('edit-duplex').checked;
      const fillRatePercent = parseFloat(document.getElementById('edit-fill-rate').value) || 0;

      job.copies = copies;
      job.taille = size;
      job.color = color;
      job.duplex = duplex;
      job.fill_rate_percent = fillRatePercent;
      job.raw_fill_rate = fillRatePercent / 100;

      const candidate = {
        job_id: job.originalJobId,
        document: document.getElementById('edit-document-name').value,
        document_name: document.getElementById('edit-document-name').value,
        thumbnail_url: job.thumbnail_url,
        timestamp: Date.now(),
        printer_name: job.printerName || job.machine,

        copies: copies,
        duplex: duplex,
        color_mode: color ? 'Color' : 'Monochrome',
        paper_size: size,
        fill_rate: job.raw_fill_rate,

        total_pages: parseInt(document.getElementById('edit-pages').value) || 1
      };

      simulateJob(candidate, currentEditingIndex, null, true).then(success => {
        if (success) $('#edit-job-modal').modal('hide');
      });
      return;

    } else if (job.type === 'duplicopieur') {
      job.nb_masters = parseInt(document.getElementById('edit-masters').value) || 0;
      job.nb_passages = parseInt(document.getElementById('edit-passages').value) || 0;
      job.duplex = document.getElementById('edit-dupli-duplex').checked;

      const select = document.getElementById('edit-tambour');
      job.selected_tambour = select.value;
      const price = parseFloat(select.options[select.selectedIndex].dataset.price || 0);

      let effectivePrice = price;
      if (job.taille === 'A4') effectivePrice = effectivePrice / 2;
      job.unit_passage = effectivePrice;

      job.cout_masters = job.nb_masters * (job.unit_master || 0);
      job.cout_passages = job.nb_passages * job.unit_passage;

      recalcPaper(job);
      recalcTotal(currentEditingIndex);

      updateJobInSession(job, currentEditingIndex);
      $('#edit-job-modal').modal('hide');
    }
  };

})();
