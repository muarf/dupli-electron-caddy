/**
 * tapis-tri.js
 * 
 * Gestion interactive du Tapis de Tri des impressions.
 * Permet la sélection libre multi-documents, l'encaissement direct au comptoir,
 * l'assignation à un compte/session et l'ajout de tirages manuels.
 */
(function () {
  'use strict';

  const S = (window.CONFIG && window.CONFIG.strings) || {};

  function translate(key, fallback) {
    return S[key] || fallback || key;
  }

  // État du Tapis de Tri
  let allJobs = [];
  const selectedJobIds = new Set();
  const manualItems = []; // Tirages manuels ajoutés au panier en cours
  let pollingTimer = null;
  let isFetching = false;
  let activeSessions = [];

  document.addEventListener('DOMContentLoaded', () => {
    initTapisTri();
  });

  function initTapisTri() {
    loadJobs();
    loadActiveSessions();
    startPolling();
    initEventListeners();

    // Écoute des événements IPC si on est sous Electron / Tauri
    if (window.electronAPI && typeof window.electronAPI.onPrintJobDetected === 'function') {
      try {
        window.electronAPI.onPrintJobDetected(() => {
          console.log('[TapisTri] Impression détectée via IPC, rafraîchissement immédiat...');
          loadJobs();
        });
      } catch (err) {
        console.warn('[TapisTri] Erreur init IPC onPrintJobDetected:', err);
      }
    }
  }

  function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(() => {
      loadJobs(true);
    }, 3000);
  }

  async function loadJobs(isBackground = false) {
    if (isFetching) return;
    isFetching = true;

    try {
      const response = await fetch('?tapis_tri&action=list');
      const data = await response.json();

      if (data && data.success && Array.isArray(data.jobs)) {
        allJobs = data.jobs;
        renderTapisGrid();
        updateBottomBar();
      }
    } catch (e) {
      if (!isBackground) {
        console.error('[TapisTri] Erreur chargement jobs:', e);
      }
    } finally {
      isFetching = false;
    }
  }

  async function loadActiveSessions() {
    try {
      const response = await fetch('?sessions&action=list');
      const data = await response.json();
      if (data && data.success && Array.isArray(data.sessions)) {
        activeSessions = data.sessions;
        renderSessionSelect();
      }
    } catch (e) {
      console.warn('[TapisTri] Erreur chargement sessions actives:', e);
    }
  }

  function renderSessionSelect() {
    const select = document.getElementById('assign-session-select');
    if (!select) return;

    select.innerHTML = '<option value="">-- Créer un nouveau compte / session --</option>';
    activeSessions.forEach(sess => {
      const opt = document.createElement('option');
      opt.value = sess.id;
      const count = sess.job_count || 0;
      const total = parseFloat(sess.total_price || 0).toFixed(2);
      opt.textContent = `${sess.contact} ${sess.session_name ? '(' + sess.session_name + ')' : ''} — ${count} jobs (${total} €)`;
      select.appendChild(opt);
    });
  }

  function renderTapisGrid() {
    const grid = document.getElementById('tapis-grid');
    const emptyState = document.getElementById('tapis-empty-state');
    const badgeCount = document.getElementById('tapis-badge-count');

    if (!grid) return;

    if (badgeCount) {
      badgeCount.textContent = allJobs.length;
    }

    if (allJobs.length === 0) {
      grid.style.display = 'none';
      if (emptyState) emptyState.style.display = 'block';
      return;
    }

    if (emptyState) emptyState.style.display = 'none';
    grid.style.display = 'grid';

    // Conserver les cartes existantes et mettre à jour le DOM pour éviter de clignoter
    const currentDomJobIds = new Set();
    grid.querySelectorAll('.tapis-card').forEach(card => {
      const id = parseInt(card.getAttribute('data-job-id'), 10);
      if (!allJobs.some(j => j.id === id)) {
        card.remove();
      } else {
        currentDomJobIds.add(id);
      }
    });

    allJobs.forEach(job => {
      const isSelected = selectedJobIds.has(job.id);

      if (currentDomJobIds.has(job.id)) {
        // Mettre à jour l'état sélectionné
        const card = grid.querySelector(`.tapis-card[data-job-id="${job.id}"]`);
        if (card) {
          if (isSelected) {
            card.classList.add('selected');
          } else {
            card.classList.remove('selected');
          }
          const checkbox = card.querySelector('.tapis-card-checkbox');
          if (checkbox) checkbox.checked = isSelected;
        }
      } else {
        // Créer une nouvelle carte
        const card = createJobCard(job, isSelected);
        grid.appendChild(card);
      }
    });
  }

  function createJobCard(job, isSelected) {
    const card = document.createElement('div');
    card.className = 'tapis-card' + (isSelected ? ' selected' : '');
    card.setAttribute('data-job-id', job.id);

    const price = parseFloat(job.calculated_price || 0).toFixed(2);
    const dateStr = formatTime(job.created_at || job.timestamp);
    const copies = job.copies > 1 ? `x${job.copies}` : '';
    const isDuplex = (job.duplex == 1 || job.duplex === true || job.duplex === '1');
    const duplexLabel = isDuplex ? 'R/V' : 'Recto';
    const isColor = (String(job.color_mode || '').toLowerCase().includes('color') || String(job.color_mode) === '2');
    const colorLabel = isColor ? 'Couleur' : 'N&B';
    const colorBadgeClass = isColor ? 'badge-color' : 'badge-mono';
    const paperSize = (job.paper_size || 'A4').toUpperCase();
    const docName = job.document_display_name || job.document || 'Document sans titre';

    let thumbHtml = '';
    if (job.thumbnail_url) {
      thumbHtml = `<div class="tapis-card-thumb-wrapper">
        <img src="${job.thumbnail_url}" alt="Aperçu" class="tapis-card-thumb" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\\'tapis-card-thumb-placeholder\\'><i class=\\'fa fa-file-pdf-o\\'></i></div>';">
      </div>`;
    } else {
      thumbHtml = `<div class="tapis-card-thumb-placeholder">
        <i class="fa fa-file-text-o"></i>
      </div>`;
    }

    card.innerHTML = `
      <div class="tapis-card-header">
        <label class="tapis-checkbox-container" onclick="event.stopPropagation()">
          <input type="checkbox" class="tapis-card-checkbox" ${isSelected ? 'checked' : ''}>
          <span class="tapis-checkbox-checkmark"></span>
        </label>
        <span class="tapis-card-time"><i class="fa fa-clock-o"></i> ${dateStr}</span>
        <div class="tapis-card-actions" onclick="event.stopPropagation()">
          ${job.thumbnail_url ? `<button class="tapis-btn-icon" title="Agrandir l'aperçu" onclick="window.tapisPreviewThumb('${job.thumbnail_url}', '${escapeHtml(docName)}')"><i class="fa fa-search-plus"></i></button>` : ''}
          <button class="tapis-btn-icon text-danger" title="Supprimer" onclick="window.tapisDeleteSingleJob(${job.id})"><i class="fa fa-trash-o"></i></button>
        </div>
      </div>

      ${thumbHtml}

      <div class="tapis-card-body">
        <h5 class="tapis-card-title" title="${escapeHtml(docName)}">${escapeHtml(docName)}</h5>
        <div class="tapis-card-machine text-muted"><i class="fa fa-print"></i> ${escapeHtml(job.printer_name || job.machine_name || 'Imprimante')}</div>

        <div class="tapis-card-badges">
          <span class="tapis-badge badge-paper">${paperSize}</span>
          <span class="tapis-badge ${colorBadgeClass}">${colorLabel}</span>
          <span class="tapis-badge badge-duplex">${duplexLabel}</span>
          <span class="tapis-badge badge-pages">${job.total_pages || 1} p. ${copies}</span>
        </div>
      </div>

      <div class="tapis-card-footer">
        <span class="tapis-card-price-label">Prix calculé :</span>
        <span class="tapis-card-price">${price} €</span>
      </div>
    `;

    // Clic sur toute la carte = toggle sélection
    card.addEventListener('click', () => {
      toggleJobSelection(job.id);
    });

    const chk = card.querySelector('.tapis-card-checkbox');
    if (chk) {
      chk.addEventListener('change', (e) => {
        e.stopPropagation();
        setJobSelection(job.id, chk.checked);
      });
    }

    return card;
  }

  function toggleJobSelection(jobId) {
    if (selectedJobIds.has(jobId)) {
      selectedJobIds.delete(jobId);
    } else {
      selectedJobIds.add(jobId);
    }
    updateCardSelectionVisuals();
    updateBottomBar();
  }

  function setJobSelection(jobId, isSelected) {
    if (isSelected) {
      selectedJobIds.add(jobId);
    } else {
      selectedJobIds.delete(jobId);
    }
    updateCardSelectionVisuals();
    updateBottomBar();
  }

  function updateCardSelectionVisuals() {
    const grid = document.getElementById('tapis-grid');
    if (!grid) return;

    grid.querySelectorAll('.tapis-card').forEach(card => {
      const id = parseInt(card.getAttribute('data-job-id'), 10);
      const isSelected = selectedJobIds.has(id);
      if (isSelected) {
        card.classList.add('selected');
      } else {
        card.classList.remove('selected');
      }
      const checkbox = card.querySelector('.tapis-card-checkbox');
      if (checkbox) checkbox.checked = isSelected;
    });
  }

  function updateBottomBar() {
    const bar = document.getElementById('tapis-bottom-bar');
    if (!bar) return;

    const count = selectedJobIds.size + manualItems.length;
    if (count > 0) {
      bar.classList.add('visible');
    } else {
      bar.classList.remove('visible');
    }

    const countEl = document.getElementById('tapis-selected-count');
    const totalEl = document.getElementById('tapis-selected-total');
    const btnPay = document.getElementById('tapis-btn-checkout');

    let total = 0;
    allJobs.forEach(j => {
      if (selectedJobIds.has(j.id)) {
        total += parseFloat(j.calculated_price || 0);
      }
    });

    manualItems.forEach(m => {
      total += parseFloat(m.prix || 0);
    });

    total = Math.round(total * 100) / 100;
    const formattedTotal = total.toFixed(2);

    if (countEl) {
      countEl.textContent = `${count} ${translate('auto_tirage.selected_docs', 'document(s) sélectionné(s)')}`;
    }
    if (totalEl) {
      totalEl.textContent = `${formattedTotal} €`;
    }
    if (btnPay) {
      btnPay.innerHTML = `<i class="fa fa-credit-card"></i> <strong>ENCAISSER DIRECT (${formattedTotal} €)</strong>`;
    }

    // Mise à jour du checkbox select-all
    const selectAllChk = document.getElementById('tapis-select-all');
    if (selectAllChk) {
      selectAllChk.checked = (allJobs.length > 0 && selectedJobIds.size === allJobs.length);
      selectAllChk.indeterminate = (selectedJobIds.size > 0 && selectedJobIds.size < allJobs.length);
    }
  }

  // ── Modale Encaissement Express ───────────────────────────────────────────

  window.tapisOpenCheckoutModal = function () {
    const count = selectedJobIds.size + manualItems.length;
    if (count === 0) {
      if (typeof showAppModal === 'function') {
        showAppModal({ message: "Veuillez sélectionner au moins un document sur le tapis.", type: "warning" });
      } else {
        alert("Veuillez sélectionner au moins un document sur le tapis.");
      }
      return;
    }

    let total = 0;
    const itemsSummary = [];

    allJobs.forEach(j => {
      if (selectedJobIds.has(j.id)) {
        const p = parseFloat(j.calculated_price || 0);
        total += p;
        itemsSummary.push({
          title: j.document_display_name || j.document,
          detail: `${j.paper_size || 'A4'} • ${j.total_pages || 1} pages`,
          price: p.toFixed(2)
        });
      }
    });

    manualItems.forEach(m => {
      const p = parseFloat(m.prix || 0);
      total += p;
      itemsSummary.push({
        title: m.label || 'Tirage manuel',
        detail: `${m.taille || 'A4'} • ${m.nb_f || 1} f.`,
        price: p.toFixed(2)
      });
    });

    total = Math.round(total * 100) / 100;

    // Remplir le modal
    const totalDisplay = document.getElementById('checkout-modal-total');
    if (totalDisplay) totalDisplay.textContent = total.toFixed(2) + ' €';

    const inputReceived = document.getElementById('checkout-amount-received');
    if (inputReceived) {
      inputReceived.value = total.toFixed(2);
    }

    const changeDisplay = document.getElementById('checkout-change-due');
    if (changeDisplay) changeDisplay.textContent = '0.00 €';

    const listContainer = document.getElementById('checkout-modal-items-list');
    if (listContainer) {
      listContainer.innerHTML = itemsSummary.map(item => `
        <div class="checkout-item-row">
          <div>
            <strong>${escapeHtml(item.title)}</strong>
            <small class="text-muted d-block">${escapeHtml(item.detail)}</small>
          </div>
          <span class="badge badge-light border">${item.price} €</span>
        </div>
      `).join('');
    }

    // Réinitialiser les choix de paiement
    setPaymentMethod('especes');

    if (window.$ && typeof $('#modal-checkout-direct').modal === 'function') {
      $('#modal-checkout-direct').modal('show');
      setTimeout(() => {
        if (inputReceived) inputReceived.select();
      }, 300);
    }
  };

  window.tapisSetQuickAmount = function (amount) {
    const inputReceived = document.getElementById('checkout-amount-received');
    if (!inputReceived) return;

    if (amount === 'exact') {
      let total = getSelectedTotal();
      inputReceived.value = total.toFixed(2);
    } else {
      inputReceived.value = parseFloat(amount).toFixed(2);
    }
    tapisComputeChange();
  };

  window.tapisComputeChange = function () {
    const inputReceived = document.getElementById('checkout-amount-received');
    const changeDisplay = document.getElementById('checkout-change-due');
    if (!inputReceived || !changeDisplay) return;

    const received = parseFloat(inputReceived.value.replace(',', '.')) || 0;
    const total = getSelectedTotal();
    const change = Math.max(0, received - total);

    changeDisplay.textContent = change.toFixed(2) + ' €';
    if (received < total) {
      changeDisplay.classList.add('text-danger');
      changeDisplay.classList.remove('text-success');
    } else {
      changeDisplay.classList.remove('text-danger');
      changeDisplay.classList.add('text-success');
    }
  };

  function getSelectedTotal() {
    let total = 0;
    allJobs.forEach(j => {
      if (selectedJobIds.has(j.id)) {
        total += parseFloat(j.calculated_price || 0);
      }
    });
    manualItems.forEach(m => {
      total += parseFloat(m.prix || 0);
    });
    return Math.round(total * 100) / 100;
  }

  let selectedPaymentMethod = 'especes';
  window.tapisSetPaymentMethod = function (method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.btn-payment-method').forEach(btn => {
      if (btn.getAttribute('data-method') === method) {
        btn.classList.add('active', 'btn-primary');
        btn.classList.remove('btn-outline-secondary');
      } else {
        btn.classList.remove('active', 'btn-primary');
        btn.classList.add('btn-outline-secondary');
      }
    });

    const cashDetails = document.getElementById('checkout-cash-details');
    if (cashDetails) {
      cashDetails.style.display = (method === 'especes') ? 'block' : 'none';
    }
  };

  function setPaymentMethod(method) {
    window.tapisSetPaymentMethod(method);
  }

  window.tapisSubmitCheckout = async function () {
    const btnSubmit = document.getElementById('btn-submit-checkout');
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Validation...';
    }

    const contactInput = document.getElementById('checkout-client-name');
    const contact = (contactInput && contactInput.value.trim()) ? contactInput.value.trim() : 'Passage';
    const amountReceivedInput = document.getElementById('checkout-amount-received');
    const amountReceived = parseFloat(amountReceivedInput ? amountReceivedInput.value.replace(',', '.') : 0) || 0;

    const payload = {
      job_ids: Array.from(selectedJobIds),
      contact: contact,
      payment_method: selectedPaymentMethod,
      amount_received: amountReceived,
      manual_items: manualItems
    };

    try {
      const response = await fetch('?tapis_tri&action=checkout_direct', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const result = await response.json();

      if (result && result.success) {
        if (window.$ && typeof $('#modal-checkout-direct').modal === 'function') {
          $('#modal-checkout-direct').modal('hide');
        }

        // Vider la sélection
        selectedJobIds.clear();
        manualItems.length = 0;

        let successMsg = `✅ <strong>Encaissement validé (${result.total_price.toFixed(2)} €)</strong>`;
        if (selectedPaymentMethod === 'especes' && result.change > 0) {
          successMsg += `<br><span class="badge badge-success" style="font-size: 16px; margin-top: 8px;">Rendu monnaie : ${result.change.toFixed(2)} €</span>`;
        }

        if (typeof showAppModal === 'function') {
          showAppModal({
            title: "Paiement réussi",
            message: successMsg,
            type: "success"
          });
        }

        // Rafraîchir les jobs immédiatement
        await loadJobs();
      } else {
        const err = (result && result.error) ? result.error : "Erreur inconnue lors du paiement.";
        if (typeof showAppModal === 'function') {
          showAppModal({ message: err, type: "danger" });
        } else {
          alert(err);
        }
      }
    } catch (e) {
      console.error('[TapisTri] Erreur encaissement:', e);
      alert("Erreur de communication avec le serveur.");
    } finally {
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="fa fa-check"></i> Valider et libérer';
      }
    }
  };

  // ── Modale Assignation à un compte / session ──────────────────────────────

  window.tapisOpenAssignModal = function () {
    if (selectedJobIds.size === 0) return;
    renderSessionSelect();
    const contactInput = document.getElementById('assign-new-contact-name');
    if (contactInput) contactInput.value = '';

    if (window.$ && typeof $('#modal-assign-session').modal === 'function') {
      $('#modal-assign-session').modal('show');
    }
  };

  window.tapisSubmitAssign = async function () {
    const select = document.getElementById('assign-session-select');
    const contactInput = document.getElementById('assign-new-contact-name');
    const sessionId = select ? select.value : '';
    const contactName = contactInput ? contactInput.value.trim() : '';

    if (!sessionId && !contactName) {
      alert("Veuillez sélectionner une session existante ou saisir un nom de contact.");
      return;
    }

    const payload = {
      job_ids: Array.from(selectedJobIds),
      session_id: sessionId ? parseInt(sessionId, 10) : null,
      contact: contactName
    };

    try {
      const response = await fetch('?tapis_tri&action=assign_to_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();

      if (result && result.success) {
        if (window.$ && typeof $('#modal-assign-session').modal === 'function') {
          $('#modal-assign-session').modal('hide');
        }
        selectedJobIds.clear();
        await loadJobs();
        await loadActiveSessions();
        if (typeof showAppModal === 'function') {
          showAppModal({
            title: "Impressions assignées",
            message: `Les documents ont été transférés sur le compte de <strong>${escapeHtml(result.contact)}</strong>.`,
            type: "success"
          });
        }
      } else {
        alert(result.error || "Erreur lors de l'assignation.");
      }
    } catch (e) {
      console.error('[TapisTri] Erreur assignation:', e);
      alert("Erreur serveur.");
    }
  };

  // ── Modale Ajout Manuel ───────────────────────────────────────────────────

  window.tapisOpenManualModal = function () {
    if (window.$ && typeof $('#modal-add-manual').modal === 'function') {
      $('#modal-add-manual').modal('show');
    }
  };

  window.tapisSubmitManual = function () {
    const machine = document.getElementById('manual-machine-select').value;
    const taille = document.getElementById('manual-taille-select').value;
    const couleur = document.getElementById('manual-couleur-select').value;
    const rv = document.getElementById('manual-rv-select').value;
    const nbF = parseInt(document.getElementById('manual-nbf-input').value, 10) || 1;
    const prix = parseFloat(document.getElementById('manual-prix-input').value) || 0.10;

    manualItems.push({
      type: 'photocop',
      marque: machine,
      taille: taille,
      couleur: couleur,
      rv: rv,
      nb_f: nbF,
      prix: prix,
      label: `Manuel: ${nbF}x ${taille} ${couleur === 'oui' ? 'Couleur' : 'N&B'}`
    });

    if (window.$ && typeof $('#modal-add-manual').modal === 'function') {
      $('#modal-add-manual').modal('hide');
    }

    updateBottomBar();
  };

  // ── Suppression ───────────────────────────────────────────────────────────

  window.tapisDeleteSingleJob = async function (jobId) {
    if (!confirm("Voulez-vous supprimer cette impression du tapis de tri ?")) return;

    try {
      const response = await fetch('?tapis_tri&action=delete_jobs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ job_ids: [jobId] })
      });
      const result = await response.json();
      if (result.success) {
        selectedJobIds.delete(jobId);
        loadJobs();
      }
    } catch (e) {
      console.error('[TapisTri] Erreur suppression job:', e);
    }
  };

  window.tapisDeleteSelected = async function () {
    const count = selectedJobIds.size;
    if (count === 0) return;

    if (!confirm(`Voulez-vous vraiment supprimer les ${count} impression(s) sélectionnée(s) ?`)) return;

    try {
      const response = await fetch('?tapis_tri&action=delete_jobs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ job_ids: Array.from(selectedJobIds) })
      });
      const result = await response.json();
      if (result.success) {
        selectedJobIds.clear();
        loadJobs();
      }
    } catch (e) {
      console.error('[TapisTri] Erreur suppression multiple:', e);
    }
  };

  // ── Aperçu Miniature ──────────────────────────────────────────────────────

  window.tapisPreviewThumb = function (url, title) {
    const modalImg = document.getElementById('tapis-preview-img');
    const modalTitle = document.getElementById('tapis-preview-title');
    if (modalImg) modalImg.src = url;
    if (modalTitle) modalTitle.textContent = title;

    if (window.$ && typeof $('#modal-tapis-preview').modal === 'function') {
      $('#modal-tapis-preview').modal('show');
    }
  };

  // ── Événements globaux ────────────────────────────────────────────────────

  function initEventListeners() {
    const selectAll = document.getElementById('tapis-select-all');
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        if (selectAll.checked) {
          allJobs.forEach(j => selectedJobIds.add(j.id));
        } else {
          selectedJobIds.clear();
        }
        updateCardSelectionVisuals();
        updateBottomBar();
      });
    }

    const inputReceived = document.getElementById('checkout-amount-received');
    if (inputReceived) {
      inputReceived.addEventListener('input', window.tapisComputeChange);
      inputReceived.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          window.tapisSubmitCheckout();
        }
      });
    }
  }

  function formatTime(timestamp) {
    if (!timestamp) return '';
    try {
      const d = new Date(timestamp);
      if (isNaN(d.getTime())) return timestamp;
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return timestamp;
    }
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

})();
