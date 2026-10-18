/**
 * tapis-tri.js
 * Logique client du Tapis de Tri des impressions.
 * - Rafraîchissement automatique (IPC Electron onPrintJobDetected ou Polling HTTP léger)
 * - Sélection multi-cartes réactive
 * - Barre inférieure de synthèse et calcul du total en direct
 * - Modal d'enregistrement direct (Contact libre, Payé/Non payé en espèces, Ajout manuel facultatif)
 * - Suppression / annulation de jobs
 */

(function () {
  'use strict';

  // État local du tapis
  let jobs = [];
  let selectedJobIds = new Set();
  let isPolling = false;
  let pollInterval = null;
  let isSaving = false;

  // Initialisation au chargement du DOM
  document.addEventListener('DOMContentLoaded', async () => {
    initEventListeners();
    setupRealtimeEvents();
    await syncSpoolerJobs();
    await loadJobs();
  });

  /**
   * Écoute des événements IPC Electron ou Polling HTTP
   */
  function setupRealtimeEvents() {
    if (window.electronAPI && typeof window.electronAPI.onPrintJobDetected === 'function') {
      console.log('[TapisTri] Mode Electron détecté : écoute IPC active');
      window.electronAPI.onPrintJobDetected((jobData) => {
        console.log('[TapisTri] Nouvelle impression détectée via IPC :', jobData);
        // Léger debounce pour laisser le temps au spooler d'écrire en BDD et générer la miniature
        setTimeout(() => loadJobs(true), 800);
      });
    }

    // Polling HTTP (toutes les 2.5 secondes si non Electron, ou en filet de sécurité)
    startPolling(3000);
  }

  function startPolling(intervalMs) {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => {
      // Ne pas recharger si le modal est ouvert
      const isModalOpen = $('#saveModal').hasClass('in');
      if (!isModalOpen) {
        loadJobs(true);
      }
    }, intervalMs);
  }

  /**
   * Charge la liste des jobs depuis l'API tapis_tri
   */
  async function loadJobs(silent = false) {
    if (isPolling) return;
    isPolling = true;

    try {
      const response = await fetch('?tapis_tri&action=list', {
        headers: { 'Accept': 'application/json' }
      });
      const data = await response.json();

      if (data.success && Array.isArray(data.jobs)) {
        jobs = data.jobs;
        renderJobsGrid();
        updateBottomBar();
        updateStatsCounters();
      } else if (data.error && !silent) {
        console.error('[TapisTri] Erreur API:', data.error);
      }
    } catch (err) {
      if (!silent) console.error('[TapisTri] Erreur de chargement des impressions:', err);
    } finally {
      isPolling = false;
    }
  }

  /**
   * Rendu de la grille des cartes du Tapis de Tri
   */
  function renderJobsGrid() {
    const grid = document.getElementById('tapis-grid');
    const emptyState = document.getElementById('empty-state');
    if (!grid) return;

    if (!jobs || jobs.length === 0) {
      grid.innerHTML = '';
      if (emptyState) emptyState.style.display = 'block';
      return;
    }

    if (emptyState) emptyState.style.display = 'none';

    // Conserver la sélection existante
    let html = '';
    jobs.forEach(job => {
      const isSelected = selectedJobIds.has(job.id);
      const thumb = job.thumbnail_url || 'img/document-placeholder.png';
      const docName = escapeHtml(job.document_display_name || job.document || 'Document');
      const printer = escapeHtml(job.printer_name || 'Imprimante');
      const price = parseFloat(job.calculated_price || 0).toFixed(2);
      const pages = parseInt(job.total_pages || 1, 10);
      const copies = parseInt(job.copies || 1, 10);
      const isDuplex = parseInt(job.duplex || 0, 10) === 1;
      const isColor = (job.color_mode || '').toLowerCase().includes('color');
      const timeStr = formatJobTime(job.created_at || job.timestamp);

      html += `
        <div class="tapis-card ${isSelected ? 'selected' : ''}" data-id="${job.id}" onclick="window.tapisToggleJob(${job.id}, event)">
          <div class="card-selection-check">
            <input type="checkbox" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); window.tapisToggleJob(${job.id});">
          </div>

          <div class="card-thumb-container">
            <img src="${thumb}" alt="${docName}" class="card-thumb" onerror="this.onerror=null;this.src='img/document-placeholder.png';" onclick="event.stopPropagation(); window.tapisPreviewThumb('${thumb}', '${docName}')">
          </div>

          <div class="card-body-content">
            <div class="card-title-row" title="${docName}">
              <span class="card-doc-name">${docName}</span>
            </div>

            <div class="card-badges-row">
              <span class="tapis-badge badge-printer">${printer}</span>
              <span class="tapis-badge ${isColor ? 'badge-color' : 'badge-mono'}">${isColor ? 'Couleur' : 'N&B'}</span>
              <span class="tapis-badge ${isDuplex ? 'badge-duplex' : 'badge-simplex'}">${isDuplex ? 'R/V' : 'Recto'}</span>
              ${copies > 1 ? `<span class="tapis-badge badge-copies">${copies} ex.</span>` : ''}
              <span class="tapis-badge badge-pages">${pages} p.</span>
            </div>

            <div class="card-footer-row">
              <div class="card-time"><i class="fa fa-clock-o"></i> ${timeStr}</div>
              <div class="card-price">${price} €</div>
            </div>
          </div>
        </div>
      `;
    });

    grid.innerHTML = html;
  }

  /**
   * Bascule de sélection d'un job
   */
  window.tapisToggleJob = function (jobId, event) {
    if (event && event.target && event.target.tagName === 'IMG') {
      return; // Ne pas cocher si on clique sur la loupe/miniature
    }

    if (selectedJobIds.has(jobId)) {
      selectedJobIds.delete(jobId);
    } else {
      selectedJobIds.add(jobId);
    }

    // Mise à jour visuelle ciblée
    const card = document.querySelector(`.tapis-card[data-id="${jobId}"]`);
    if (card) {
      const isSel = selectedJobIds.has(jobId);
      card.classList.toggle('selected', isSel);
      const chk = card.querySelector('input[type="checkbox"]');
      if (chk) chk.checked = isSel;
    }

    updateBottomBar();
  };

  /**
   * Tout sélectionner / Tout désélectionner
   */
  window.tapisToggleSelectAll = function () {
    if (selectedJobIds.size === jobs.length) {
      selectedJobIds.clear();
    } else {
      jobs.forEach(j => selectedJobIds.add(j.id));
    }
    renderJobsGrid();
    updateBottomBar();
  };

  /**
   * Mise à jour de la barre inférieure flottante
   */
  function updateBottomBar() {
    const bottomBar = document.getElementById('bottom-action-bar');
    const countBadge = document.getElementById('bar-selected-count');
    const totalAmountSpan = document.getElementById('bar-total-amount');
    const saveBtn = document.getElementById('btn-open-save');
    const deleteBtn = document.getElementById('btn-delete-selected');

    const selectedCount = selectedJobIds.size;

    // Calcul du total
    let total = 0.0;
    jobs.forEach(j => {
      if (selectedJobIds.has(j.id)) {
        total += parseFloat(j.calculated_price || 0);
      }
    });

    if (selectedCount > 0) {
      bottomBar.classList.add('visible');
    } else {
      bottomBar.classList.remove('visible');
    }

    if (countBadge) countBadge.textContent = selectedCount;
    if (totalAmountSpan) totalAmountSpan.textContent = total.toFixed(2) + ' €';

    if (saveBtn) {
      saveBtn.disabled = (selectedCount === 0);
    }
    if (deleteBtn) {
      deleteBtn.disabled = (selectedCount === 0);
    }
  }

  /**
   * Compteurs d'en-tête
   */
  function updateStatsCounters() {
    const totalJobsElem = document.getElementById('stat-total-jobs');
    if (totalJobsElem) totalJobsElem.textContent = jobs.length;
  }

  /**
   * Ouverture du modal d'enregistrement direct
   */
  window.tapisOpenSaveModal = function () {
    const selectedCount = selectedJobIds.size;
    if (selectedCount === 0) return;

    // Calcul du montant final
    let total = 0.0;
    jobs.forEach(j => {
      if (selectedJobIds.has(j.id)) total += parseFloat(j.calculated_price || 0);
    });

    document.getElementById('modal-final-total').textContent = total.toFixed(2) + ' €';
    document.getElementById('modal-contact-input').value = '';
    document.getElementById('modal-mot-input').value = '';
    
    const cbInput = document.getElementById('modal-cb-input');
    if (cbInput) {
      cbInput.value = total.toFixed(2);
    }

    // Par défaut : Payé sélectionné
    setPaymentStatus('oui');

    $('#saveModal').modal('show');
    setTimeout(() => {
      document.getElementById('modal-contact-input').focus();
    }, 400);
  };

  /**
   * Statut de paiement dans le modal (Payé ou Non payé)
   */
  let currentPaymentStatus = 'oui';
  window.setPaymentStatus = function (status) {
    currentPaymentStatus = status;
    const btnOui = document.getElementById('btn-paye-oui');
    const btnNon = document.getElementById('btn-paye-non');
    const groupCb = document.getElementById('group-cb-montant');

    if (groupCb) {
      groupCb.style.display = (status === 'oui') ? 'block' : 'none';
    }

    if (btnOui && btnNon) {
      if (status === 'oui') {
        btnOui.classList.add('active', 'btn-success');
        btnOui.classList.remove('btn-default');
        btnNon.classList.remove('active', 'btn-warning');
        btnNon.classList.add('btn-default');
      } else {
        btnNon.classList.add('active', 'btn-warning');
        btnNon.classList.remove('btn-default');
        btnOui.classList.remove('active', 'btn-success');
        btnOui.classList.add('btn-default');
      }
    }
  };

  /**
   * Validation finale de l'enregistrement
   */
  window.tapisConfirmSave = async function () {
    if (isSaving) return;

    const contactInput = document.getElementById('modal-contact-input');
    const contact = contactInput.value.trim();

    if (!contact) {
      contactInput.style.borderColor = '#e74c3c';
      contactInput.focus();
      return;
    }
    contactInput.style.borderColor = '';

    const mot = document.getElementById('modal-mot-input').value.trim();
    const btnConfirm = document.getElementById('btn-confirm-save');

    const cbInput = document.getElementById('modal-cb-input');
    const cbVal = (currentPaymentStatus === 'oui')
      ? (cbInput && cbInput.value !== '' ? parseFloat(cbInput.value) : null)
      : 0;

    isSaving = true;
    btnConfirm.disabled = true;
    btnConfirm.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enregistrement...';

    try {
      const payload = {
        action: 'save',
        contact: contact,
        paye: currentPaymentStatus,
        cb: cbVal,
        mot: mot,
        job_ids: Array.from(selectedJobIds)
      };

      const res = await fetch('?tapis_tri&action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        $('#saveModal').modal('hide');
        selectedJobIds.clear();
        showFeedback(data.message || 'Tirage enregistré !', 'success');
        await loadJobs();
      } else {
        alert(data.error || 'Erreur lors de l\'enregistrement.');
      }
    } catch (err) {
      console.error('[TapisTri] Erreur enregistrement:', err);
      alert('Erreur réseau ou serveur lors de l\'enregistrement.');
    } finally {
      isSaving = false;
      btnConfirm.disabled = false;
      btnConfirm.innerHTML = '<i class="fa fa-check"></i> Valider et enregistrer';
    }
  };

  /**
   * Suppression des documents sélectionnés
   */
  window.tapisDeleteSelected = function () {
    const count = selectedJobIds.size;
    if (count === 0) return;

    if (!confirm(`Supprimer ${count} document(s) du tapis de tri ?`)) {
      return;
    }

    fetch('?tapis_tri&action=delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'delete',
        job_ids: Array.from(selectedJobIds)
      })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        selectedJobIds.clear();
        loadJobs();
        showFeedback(`${count} document(s) supprimé(s)`, 'info');
      } else {
        alert(data.error || 'Erreur de suppression');
      }
    })
    .catch(err => {
      console.error('[TapisTri] Erreur delete:', err);
    });
  };

  /**
   * Aperçu grande miniature
   */
  window.tapisPreviewThumb = function (src, title) {
    const modalImg = document.getElementById('preview-modal-img');
    const modalTitle = document.getElementById('preview-modal-title');
    if (modalImg) modalImg.src = src;
    if (modalTitle) modalTitle.textContent = title;
    $('#previewModal').modal('show');
  };

  /**
   * Notification feedback visuelle
   */
  function showFeedback(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `tapis-toast toast-${type}`;
    toast.innerHTML = `<i class="fa fa-info-circle"></i> ${escapeHtml(msg)}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('visible'), 10);
    setTimeout(() => {
      toast.classList.remove('visible');
      setTimeout(() => toast.remove(), 400);
    }, 2800);
  }

  function formatJobTime(isoOrTimestamp) {
    if (!isoOrTimestamp) return '';
    try {
      const d = new Date(isoOrTimestamp);
      if (isNaN(d.getTime())) return isoOrTimestamp;
      const hh = String(d.getHours()).padStart(2, '0');
      const mm = String(d.getMinutes()).padStart(2, '0');
      return `${hh}:${mm}`;
    } catch {
      return isoOrTimestamp;
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
  }

  /**
   * Synchronise les impressions actuellement dans la file d'attente du spouleur
   */
  async function syncSpoolerJobs() {
    if (!window.electronAPI || typeof window.electronAPI.getPrintJobs !== 'function') {
      return;
    }
    try {
      const spoolJobs = await window.electronAPI.getPrintJobs('');
      if (Array.isArray(spoolJobs) && spoolJobs.length > 0) {
        console.log('[TapisTri] Spooler jobs trouvés :', spoolJobs.length);
        for (const sj of spoolJobs) {
          try {
            await fetch('?print_notification', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                jobId: sj.jobId || sj.job_id,
                document: sj.document || 'Sans nom',
                printerName: sj.printerName || sj.printer_name || 'RISO ComColor FW5230',
                status: sj.status || 0,
                totalPages: sj.totalPages || sj.total_pages || 0,
                paperSize: sj.paperSize || sj.paper_size || 'A4',
                duplex: sj.isDuplex !== undefined ? sj.isDuplex : false,
                colorMode: sj.isColor ? 'Color' : 'Monochrome',
                isGrayscale: !sj.isColor,
                copies: sj.copies || 1,
                timestamp: sj.timeSubmitted || sj.time_submitted || new Date().toISOString(),
                timeSubmitted: sj.timeSubmitted || sj.time_submitted || new Date().toISOString(),
                eventType: 'job_detected',
                platform: 'windows'
              })
            });
          } catch (e) {
            console.warn('[TapisTri] Erreur sync print_notification pour job', sj, e);
          }
        }
      }
    } catch (err) {
      console.warn('[TapisTri] Impossible d\'interroger le spouleur :', err);
    }
  }

  /**
   * Actualise le tapis de tri et resynchronise le spouleur
   */
  window.tapisRefresh = async function () {
    const btn = document.getElementById('btn-tapis-refresh');
    if (btn) btn.classList.add('fa-spin');
    try {
      await syncSpoolerJobs();
      await loadJobs();
      showFeedback('Tapis de tri synchronisé', 'info');
    } finally {
      if (btn) btn.classList.remove('fa-spin');
    }
  };

  /**
   * Suppression des jobs cochés
   */
  window.tapisDeleteSelected = async function () {
    if (selectedJobIds.size === 0) return;

    const count = selectedJobIds.size;
    if (!confirm(`Supprimer ${count} document(s) du tapis de tri ?`)) {
      return;
    }

    const selectedList = jobs.filter(j => selectedJobIds.has(j.id));
    const btn = document.getElementById('btn-delete-selected');
    if (btn) btn.disabled = true;

    try {
      // 1. Supprimer du spouleur Windows si disponible
      if (window.electronAPI && typeof window.electronAPI.deletePrintJob === 'function') {
        for (const j of selectedList) {
          if (j.job_id) {
            try {
              await window.electronAPI.deletePrintJob(j.printer_name || '', Number(j.job_id));
            } catch (err) {
              console.warn('[TapisTri] Erreur suppression spouleur pour job', j.job_id, err);
            }
          }
        }
      }

      // 2. Supprimer de la table print_jobs
      const res = await fetch('?tapis_tri&action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          job_ids: Array.from(selectedJobIds)
        })
      });

      const data = await res.json();
      if (data.success) {
        showFeedback(data.message || `${count} document(s) supprimé(s)`, 'info');
        selectedJobIds.clear();
        await loadJobs();
      } else {
        alert('Erreur lors de la suppression : ' + (data.error || 'Erreur inconnue'));
      }
    } catch (e) {
      console.error('[TapisTri] Erreur deleteSelected:', e);
      alert('Erreur réseau lors de la suppression.');
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  function initEventListeners() {
    // Raccourci Entrée dans le champ contact du modal
    const contactInput = document.getElementById('modal-contact-input');
    if (contactInput) {
      contactInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          window.tapisConfirmSave();
        }
      });
    }
  }

})();
