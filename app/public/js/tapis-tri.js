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

  function resolveThumbUrl(url) {
    if (!url) return '';
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    const origin = (window.location && window.location.origin && window.location.origin.includes('8000'))
      ? ''
      : 'http://127.0.0.1:8000';
    return `${origin}${url.startsWith('/') ? '' : '/'}${url}`;
  }

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
        const wasEmpty = (allJobs.length === 0);
        allJobs = data.jobs;

        // Présélectionner automatiquement les documents à l'arrivée pour que le bouton d'encaissement soit immédiatement visible
        if (wasEmpty && allJobs.length > 0 && selectedJobIds.size === 0) {
          allJobs.forEach(j => selectedJobIds.add(j.id));
        }

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
    const resolvedThumb = resolveThumbUrl(job.thumbnail_url);
    if (resolvedThumb) {
      thumbHtml = `<div class="tapis-card-thumb-wrapper">
        <img src="${resolvedThumb}" alt="Aperçu" class="tapis-card-thumb" onerror="this.onerror=null;this.parentElement.innerHTML='<div class=\\'tapis-card-thumb-placeholder\\'><i class=\\'fa fa-file-pdf-o\\'></i></div>';">
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
          ${resolvedThumb ? `<button class="tapis-btn-icon" title="Agrandir l'aperçu" onclick="window.tapisPreviewThumb('${resolvedThumb}', '${escapeHtml(docName)}')"><i class="fa fa-search-plus"></i></button>` : ''}
          <button class="tapis-btn-icon text-primary" title="Éditer le tirage" onclick="window.tapisOpenEditModal(${job.id})"><i class="fa fa-pencil"></i></button>
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
    const bar = document.getElementById('tapis-bottom-bar') || document.getElementById('bottom-action-bar');
    if (!bar) return;

    const count = selectedJobIds.size + manualItems.length;
    if (count > 0) {
      bar.classList.add('visible');
    } else {
      bar.classList.remove('visible');
    }

    const countEl = document.getElementById('tapis-selected-count') || document.getElementById('bar-selected-count');
    const totalEl = document.getElementById('tapis-selected-total') || document.getElementById('bar-total-amount');
    const btnPay = document.getElementById('tapis-btn-checkout') || document.getElementById('btn-open-save');

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

  // ── Actions d'en-tête (Tout sélectionner, Actualiser, etc.) ────────────────

  window.tapisToggleSelectAll = function () {
    const shouldSelectAll = (selectedJobIds.size < allJobs.length);
    if (shouldSelectAll) {
      allJobs.forEach(j => selectedJobIds.add(j.id));
    } else {
      selectedJobIds.clear();
    }
    const selectAllChk = document.getElementById('tapis-select-all');
    if (selectAllChk) {
      selectAllChk.checked = shouldSelectAll;
      selectAllChk.indeterminate = false;
    }
    updateCardSelectionVisuals();
    updateBottomBar();
  };

  window.tapisRefresh = function () {
    const refreshBtn = document.getElementById('btn-tapis-refresh');
    if (refreshBtn) refreshBtn.classList.add('fa-spin');
    return loadJobs().finally(() => {
      if (refreshBtn) refreshBtn.classList.remove('fa-spin');
    });
  };

  window.tapisOpenSaveModal = function () {
    window.tapisOpenCheckoutModal();
  };

  window.tapisConfirmSave = function () {
    window.tapisSubmitCheckout();
  };

  // ── Modale Encaissement Direct (Alignée sur Tirage Multi-Machines) ─────────

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
        const isDuplex = (j.duplex == 1 || j.duplex === true || j.duplex === '1');
        const duplexLabel = isDuplex ? 'R/V' : 'Recto';
        const isColor = (String(j.color_mode || '').toLowerCase().includes('color') || String(j.color_mode) === '2');
        const colorLabel = isColor ? 'Couleur' : 'N&B';

        itemsSummary.push({
          id: j.id,
          title: j.document_display_name || j.document || 'Document',
          detail: `${j.paper_size || 'A4'} • ${j.total_pages || 1} pages ${j.copies > 1 ? 'x' + j.copies : ''} • ${colorLabel} • ${duplexLabel}`,
          price: p.toFixed(2)
        });
      }
    });

    manualItems.forEach(m => {
      const p = parseFloat(m.prix || 0);
      total += p;
      itemsSummary.push({
        id: null,
        title: m.label || 'Tirage manuel',
        detail: `${m.taille || 'A4'} • ${m.nb_f || 1} f. • ${m.couleur === 'oui' ? 'Couleur' : 'N&B'}`,
        price: p.toFixed(2)
      });
    });

    total = Math.round(total * 100) / 100;

    // Remplir le total à régler
    const totalDisplay = document.getElementById('checkout-modal-total');
    if (totalDisplay) totalDisplay.textContent = total.toFixed(2) + ' €';

    // Remplir la liste des documents avec bouton d'édition
    const listContainer = document.getElementById('checkout-modal-items-list');
    if (listContainer) {
      listContainer.innerHTML = itemsSummary.map(item => `
        <div class="checkout-item-row" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 12px; border-bottom: 1px solid #edf2f7;">
          <div style="flex: 1; min-width: 0; padding-right: 10px;">
            <strong style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(item.title)}</strong>
            <small class="text-muted d-block">${escapeHtml(item.detail)}</small>
          </div>
          <div style="display:flex; align-items:center; gap: 8px; flex-shrink: 0;">
            <span class="badge badge-light border font-weight-bold" style="font-size: 0.95rem;">${item.price} €</span>
            ${item.id ? `<button type="button" class="btn btn-sm btn-outline-primary p-1" style="line-height:1; border-radius:4px;" title="Éditer le tirage" onclick="$('#modal-checkout-direct').modal('hide'); window.tapisOpenEditModal(${item.id});"><i class="fa fa-pencil"></i></button>` : ''}
          </div>
        </div>
      `).join('');
    }

    // Réinitialiser les champs selon tirage_multimachine
    const payeOui = document.getElementById('checkout-paye-oui');
    if (payeOui) payeOui.checked = true;

    const amountPaidInput = document.getElementById('checkout-amount-paid');
    if (amountPaidInput) {
      amountPaidInput.value = total.toFixed(2);
    }

    const motInput = document.getElementById('checkout-mot');
    if (motInput) motInput.value = '';

    const contactInput = document.getElementById('checkout-client-name');
    if (contactInput && !contactInput.value) {
      contactInput.value = 'Passage';
    }

    if (window.$ && typeof $('#modal-checkout-direct').modal === 'function') {
      $('#modal-checkout-direct').modal('show');
      setTimeout(() => {
        if (amountPaidInput) amountPaidInput.select();
      }, 300);
    }
  };

  window.tapisUpdatePaymentAmount = function () {
    const payeOui = document.getElementById('checkout-paye-oui');
    const amountPaidInput = document.getElementById('checkout-amount-paid');
    if (!amountPaidInput) return;

    if (payeOui && payeOui.checked) {
      const total = getSelectedTotal();
      amountPaidInput.value = total.toFixed(2);
    } else {
      amountPaidInput.value = '0.00';
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

  window.tapisSubmitCheckout = async function () {
    const btnSubmit = document.getElementById('btn-submit-checkout');
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Validation...';
    }

    const contactInput = document.getElementById('checkout-client-name');
    const contact = (contactInput && contactInput.value.trim()) ? contactInput.value.trim() : 'Passage';

    const payeOui = document.getElementById('checkout-paye-oui');
    const isPaid = (payeOui && payeOui.checked) ? 'oui' : 'non';

    const amountPaidInput = document.getElementById('checkout-amount-paid');
    const amountPaid = parseFloat(amountPaidInput ? amountPaidInput.value.replace(',', '.') : 0) || 0;

    const motInput = document.getElementById('checkout-mot');
    const mot = motInput ? motInput.value.trim() : '';

    const payload = {
      job_ids: Array.from(selectedJobIds),
      contact: contact,
      paye: isPaid,
      amount_paid: (isPaid === 'oui' ? amountPaid : 0),
      amount_received: (isPaid === 'oui' ? amountPaid : 0),
      payment_method: 'especes',
      mot: mot,
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

        let successMsg = `✅ <strong>Règlement validé (${result.total_price.toFixed(2)} €)</strong>`;
        if (isPaid === 'oui') {
          successMsg += `<br><span class="text-success font-weight-bold">Payé : ${amountPaid.toFixed(2)} € (Espèces)</span>`;
        } else {
          successMsg += `<br><span class="text-danger font-weight-bold">Non payé (À régler : ${result.total_price.toFixed(2)} €)</span>`;
        }
        if (mot) {
          successMsg += `<br><small class="text-muted">Mot : ${escapeHtml(mot)}</small>`;
        }

        if (typeof showAppModal === 'function') {
          showAppModal({
            title: "Règlement effectué",
            message: successMsg,
            type: "success"
          });
        }

        // Rafraîchir les jobs immédiatement
        await loadJobs();
      } else {
        const err = (result && result.error) ? result.error : "Erreur inconnue lors du règlement.";
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
        btnSubmit.innerHTML = '<i class="fa fa-check"></i> ' + translate('auto_tirage.validate_payment', 'Valider le règlement') + ' (Entrée)';
      }
    }
  };

  // ── Modale Édition d'un Tirage ────────────────────────────────────────────

  window.tapisOpenEditModal = function (jobId) {
    const job = allJobs.find(j => j.id === jobId);
    if (!job) return;

    document.getElementById('edit-job-id').value = job.id;
    document.getElementById('edit-job-docname').value = job.document_display_name || job.document || '';

    // Machine select
    const machineSelect = document.getElementById('edit-job-machine');
    if (machineSelect) {
      let matched = false;
      for (let i = 0; i < machineSelect.options.length; i++) {
        const opt = machineSelect.options[i];
        if (job.machine_id && opt.getAttribute('data-id') == job.machine_id && opt.getAttribute('data-type') === job.machine_type) {
          machineSelect.selectedIndex = i;
          matched = true;
          break;
        }
      }
      if (!matched && (job.machine_name || job.printer_name)) {
        const targetName = (job.machine_name || job.printer_name).toLowerCase();
        for (let i = 0; i < machineSelect.options.length; i++) {
          const opt = machineSelect.options[i];
          if (opt.textContent.toLowerCase().includes(targetName)) {
            machineSelect.selectedIndex = i;
            break;
          }
        }
      }
    }

    // Format
    const sizeSelect = document.getElementById('edit-job-taille');
    if (sizeSelect) {
      sizeSelect.value = (String(job.paper_size || 'A4').toUpperCase().includes('A3')) ? 'A3' : 'A4';
    }

    // Couleur
    const colorSelect = document.getElementById('edit-job-couleur');
    if (colorSelect) {
      const isColor = (String(job.color_mode || '').toLowerCase().includes('color') || String(job.color_mode) === '2');
      colorSelect.value = isColor ? 'oui' : 'non';
    }

    // Duplex
    const rvSelect = document.getElementById('edit-job-rv');
    if (rvSelect) {
      const isDuplex = (job.duplex == 1 || job.duplex === true || job.duplex === '1');
      rvSelect.value = isDuplex ? 'oui' : 'non';
    }

    // Copies & Pages
    document.getElementById('edit-job-copies').value = job.copies || 1;
    document.getElementById('edit-job-pages').value = job.total_pages || 1;

    // Prix forcé (laissé vide par défaut pour permettre le calcul auto, ou prix actuel)
    document.getElementById('edit-job-prix').value = '';

    if (window.$ && typeof $('#modal-edit-job').modal === 'function') {
      $('#modal-edit-job').modal('show');
    }
  };

  window.tapisSubmitEditJob = async function () {
    const jobId = parseInt(document.getElementById('edit-job-id').value, 10);
    if (!jobId) return;

    const machineSelect = document.getElementById('edit-job-machine');
    const selectedOpt = machineSelect ? machineSelect.options[machineSelect.selectedIndex] : null;
    const machineType = selectedOpt ? selectedOpt.getAttribute('data-type') : 'photocop';
    const machineId = selectedOpt ? parseInt(selectedOpt.getAttribute('data-id'), 10) : 1;
    const machineName = selectedOpt ? selectedOpt.getAttribute('data-name') : '';

    const priceVal = document.getElementById('edit-job-prix').value.trim();
    const calculatedPrice = priceVal !== '' ? parseFloat(priceVal.replace(',', '.')) : null;

    const payload = {
      job_id: jobId,
      document_name: document.getElementById('edit-job-docname').value.trim(),
      copies: parseInt(document.getElementById('edit-job-copies').value, 10) || 1,
      total_pages: parseInt(document.getElementById('edit-job-pages').value, 10) || 1,
      paper_size: document.getElementById('edit-job-taille').value,
      color_mode: document.getElementById('edit-job-couleur').value,
      duplex: document.getElementById('edit-job-rv').value,
      machine_type: machineType,
      machine_id: machineId,
      machine_name: machineName,
      calculated_price: calculatedPrice
    };

    try {
      const response = await fetch('?tapis_tri&action=update_job', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (result && result.success) {
        if (window.$ && typeof $('#modal-edit-job').modal === 'function') {
          $('#modal-edit-job').modal('hide');
        }
        await loadJobs();
      } else {
        alert((result && result.error) || "Erreur lors de la modification du tirage.");
      }
    } catch (err) {
      console.error('[TapisTri] Erreur update_job:', err);
      alert("Erreur réseau lors de la modification.");
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

    const amountPaid = document.getElementById('checkout-amount-paid');
    if (amountPaid) {
      amountPaid.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          window.tapisSubmitCheckout();
        }
      });
    }

    const clientName = document.getElementById('checkout-client-name');
    if (clientName) {
      clientName.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          window.tapisSubmitCheckout();
        }
      });
    }

    const modalEdit = document.getElementById('modal-edit-job');
    if (modalEdit) {
      modalEdit.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && e.target && e.target.tagName !== 'TEXTAREA') {
          e.preventDefault();
          window.tapisSubmitEditJob();
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
