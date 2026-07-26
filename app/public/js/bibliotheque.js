/**
 * bibliotheque.js
 *
 * Logique de la page Bibliothèque (rescan, chat IA, filtres, upload, édition, viewer PDF).
 * Extrait de app/view/bibliotheque.html.php
 *
 * Dépendances :
 *   - jQuery ($, $.ajax, $.getJSON)
 *   - window.electronAPI (Tauri bridge, optionnel)
 *   - pdfjsLib (chargé via js/build/pdf.js avant ce fichier)
 */
(function () {
  'use strict';

  // =========================================================================
  // RESCAN / INDEXING
  // =========================================================================

  window.rescanLibrary = function (mode) {
    const btn = document.getElementById('btnRescanLibrary');
    if (btn) btn.disabled = true;

    fetch('?bibliotheque_maintenance', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'rescan', params: { mode: mode } })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success && data.job_id) {
          monitorIndexing(data.job_id);
        } else {
          const msg = (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations.error)
            ? window.CONFIG.translations.error + ': ' + (data.error || 'Erreur inconnue')
            : 'Erreur: ' + (data.error || 'Erreur inconnue');
          if (window.showAppModal) window.showAppModal(msg); else alert(msg);
          if (btn) btn.disabled = false;
        }
      })
      .catch(err => {
        console.error(err);
        if (btn) btn.disabled = false;
      });
  };

  function checkActiveJob() {
    fetch('?get_indexing_status&job_id=latest')
      .then(res => res.json())
      .then(data => {
        if (data && (data.status === 'indexing' || data.status === 'scanning')) {
          const btn = document.getElementById('btnRescanLibrary');
          if (btn) btn.disabled = true;
          monitorIndexing(data.job_id);
        }
      })
      .catch(e => console.error("Erreur checkActiveJob:", e));
  }

  function monitorIndexing(jobId) {
    const btn = document.getElementById('btnRescanLibrary');
    const progress = document.getElementById('indexProgress');
    const progressBar = progress ? progress.querySelector('.progress-bar') : null;

    if (progress) progress.style.display = 'flex';
    if (progressBar) {
      progressBar.classList.add('progress-bar-animated', 'progress-bar-striped');
      progressBar.classList.remove('bg-success');
    }

    const pollInterval = setInterval(async () => {
      try {
        const statusRes = await fetch('?get_indexing_status&job_id=' + jobId);
        const statusData = await statusRes.json();

        if (statusData.percent && progressBar) {
          progressBar.style.width = statusData.percent + '%';
          progressBar.textContent = statusData.percent + '%';
        }

        if (statusData.status === 'scanning' && progressBar) {
          progressBar.textContent = 'Scan... (' + (statusData.scanned_count || 0) + ')';
        }

        if (statusData.status === 'completed') {
          clearInterval(pollInterval);
          if (progressBar) {
            progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
            progressBar.classList.add('bg-success');
            progressBar.textContent = 'Terminé !';
          }
          if (btn) btn.disabled = false;
          setTimeout(() => { if (progress) progress.style.display = 'none'; }, 3000);
          if (typeof window.loadLibrary === 'function') window.loadLibrary(1);
        } else if (statusData.status === 'error' || statusData.status === 'fatal_error') {
          clearInterval(pollInterval);
          if (btn) btn.disabled = false;
          const msg = 'Erreur lors du scan: ' + (statusData.error_msg || 'Inconnue');
          if (window.showAppModal) window.showAppModal(msg); else alert(msg);
          if (progress) progress.style.display = 'none';
        } else if (statusData.status === 'none' || statusData.status === 'unknown') {
          clearInterval(pollInterval);
          if (btn) btn.disabled = false;
          if (progress) progress.style.display = 'none';
        }
      } catch (e) {
        console.error("Erreur polling:", e);
      }
    }, 1000);
  }

  document.addEventListener('DOMContentLoaded', checkActiveJob);

  // =========================================================================
  // CHAT IA — SIDEBAR
  // =========================================================================

  let currentAiMode = 'fast';
  let aiAbortController = null;
  let isAiGenerating = false;

  window.toggleAiChat = function () {
    document.getElementById('aiChatSidebar').classList.toggle('active');
  };

  window.setAiMode = function (mode) {
    if (isAiGenerating) return;
    currentAiMode = mode;
    document.getElementById('modeFastLabel').classList.toggle('active', mode === 'fast');
    document.getElementById('modeProLabel').classList.toggle('active', mode === 'pro');
  };

  function updateAiStatus(text, show = true) {
    const statusDiv = document.getElementById('aiChatStatus');
    const statusText = document.getElementById('aiStatusText');
    if (!statusDiv || !statusText) return;

    let icon = '<i class="fa fa-circle-notch fa-spin"></i> ';
    if (text.includes("Analyse")) icon = '<i class="fa fa-search fa-pulse"></i> ';
    if (text.includes("Sources")) icon = '<i class="fa fa-book"></i> ';
    if (text.includes("Lecture")) icon = '<i class="fa fa-glasses fa-fade"></i> ';
    if (text.includes("Rédaction")) icon = '<i class="fa fa-pen-nib fa-bounce"></i> ';
    if (text.includes("Connexion")) icon = '<i class="fa fa-wifi"></i> ';

    statusText.innerHTML = icon + text;
    statusDiv.style.display = show ? 'block' : 'none';
  }

  function updateAiChatBtn(generating = false) {
    const btn = document.getElementById('aiChatBtn');
    const icon = document.getElementById('aiChatIcon');
    isAiGenerating = generating;

    if (generating) {
      btn.classList.add('btn-danger');
      icon.className = 'fa fa-stop';
    } else {
      btn.classList.remove('btn-danger');
      icon.className = 'fa fa-paper-plane';
    }
  }

  window.sendAiMessage = async function () {
    const input = document.getElementById('aiChatInput');
    if (isAiGenerating) {
      if (aiAbortController) aiAbortController.abort();
      return;
    }

    const question = input.value.trim();
    if (!question) return;

    addChatMessage('user', question);
    input.value = '';

    const contextArea = document.getElementById('aiContextArea');
    const contextDetails = document.getElementById('aiContextDetails');
    const thoughtArea = document.getElementById('aiThoughtArea');
    const thoughtContent = document.getElementById('aiThoughtContent');

    contextArea.style.display = 'none';
    contextDetails.innerHTML = '';
    thoughtArea.style.display = 'none';
    thoughtContent.innerHTML = '';

    updateAiStatus("Recherche...");
    updateAiChatBtn(true);

    aiAbortController = new AbortController();

    try {
      const response = await fetch('?chat_rag', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question: question,
          mode: currentAiMode,
          tags: activeTags.join(','),
          selected_files: selectedPdfIds
        }),
        signal: aiAbortController.signal
      });

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let aiMsgDiv = null;
      let fullContent = "";
      let isThinking = true;

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        const chunk = decoder.decode(value);
        const lines = chunk.split("\n");

        for (const line of lines) {
          if (line.startsWith("data: ")) {
            try {
              const data = JSON.parse(line.substring(6));
              if (data.type === 'status') {
                updateAiStatus(data.message);
                if (data.sources && data.sources.length > 0) {
                  const contextAreaEl = document.getElementById('aiContextArea');
                  const contextDetailsEl = document.getElementById('aiContextDetails');
                  contextAreaEl.style.display = 'block';
                  contextDetailsEl.style.display = 'block';
                  contextDetailsEl.innerHTML = '';
                  data.sources.forEach((src, idx) => {
                    const sourceId = `src_${Date.now()}_${idx}`;
                    const topBadge = src.is_top ? '<span class="badge badge-success mr-2" style="font-size:0.6rem; vertical-align:middle;">TOP</span>' : '';
                    const scoreInfo = src.score ? `<small class="text-muted ml-2">(Score: ${src.score})</small>` : '';
                    const borderStyle = src.is_top ? 'border-left: 3px solid #28a745 !important;' : 'border-left: 3px solid #ddd !important; opacity: 0.8;';

                    contextDetailsEl.innerHTML += `
                        <div class="mb-2 p-2 border rounded bg-white shadow-sm" style="font-size: 0.85rem; ${borderStyle}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    ${topBadge}
                                    <strong onclick="openPdfViewer(${src.id}, '${src.title.replace(/'/g, "\\'")}')" style="cursor:pointer; color:var(--primary)">${src.title}</strong>
                                    ${scoreInfo}
                                </div>
                                <button class="btn btn-xs btn-link p-0 text-muted" onclick="$('#${sourceId}').toggle()">Afficher l'extrait</button>
                            </div>
                            <div id="${sourceId}" style="display:none; margin-top:5px; color:#64748b; border-top:1px dashed #eee; padding-top:5px; font-size: 0.8rem;">
                                ${src.content.replace(/\n/g, '<br>')}
                            </div>
                        </div>`;
                  });
                }
              }
              if (data.type === 'content') {
                let text = data.content;
                if (isThinking && currentAiMode === 'pro') {
                  if (fullContent === "" && !text.includes("<think>") && !text.startsWith("<")) {
                    isThinking = false;
                  } else {
                    thoughtArea.style.display = 'block';
                    if (text.includes("</think>")) {
                      const parts = text.split("</think>");
                      thoughtContent.innerHTML += parts[0].replace(/\n/g, '<br>');
                      isThinking = false;
                      updateAiStatus("Rédaction...");
                      text = parts[1] || "";
                      if (text.trim() === "") return;
                    } else {
                      thoughtContent.innerHTML += text.replace(/\n/g, '<br>');
                      return;
                    }
                  }
                }

                if (!aiMsgDiv) aiMsgDiv = addChatMessage('ai', '');
                fullContent += text;
                aiMsgDiv.innerHTML = fullContent.replace(/\n/g, '<br>');
                document.getElementById('aiChatBody').scrollTop = document.getElementById('aiChatBody').scrollHeight;
              }
              if (data.type === 'done') {
                updateAiStatus("Terminé", false);
                updateAiChatBtn(false);
              }
              if (data.type === 'error') {
                addChatMessage('ai', 'Erreur : ' + data.message);
                updateAiChatBtn(false);
              }
            } catch (e) { /* parse error */ }
          }
        }
      }
    } catch (err) {
      updateAiChatBtn(false);
      updateAiStatus("", false);
    }
  };

  function addChatMessage(role, text) {
    const body = document.getElementById('aiChatBody');
    const msgDiv = document.createElement('div');
    msgDiv.className = `chat-message ${role}`;
    msgDiv.innerHTML = text;
    body.appendChild(msgDiv);
    body.scrollTop = body.scrollHeight;
    return msgDiv;
  }

  // =========================================================================
  // FILTRES / TAGS / CHARGEMENT BIBLIOTHÈQUE
  // =========================================================================

  let searchTimeout = null;
  let currentSort = 'created_at';
  let currentOrder = 'DESC';
  let activeTags = [];
  let allTagsList = [];
  let selectedPdfIds = [];

  window.toggleAllPdfs = function (checkbox) {
    const isChecked = $(checkbox).prop('checked');
    $('.pdf-select-cb').prop('checked', isChecked);
    updatePdfSelection();
  };

  function updatePdfSelection() {
    selectedPdfIds = [];
    $('.pdf-select-cb:checked').each(function () {
      selectedPdfIds.push($(this).val());
    });

    const count = selectedPdfIds.length;
    if (count > 0) {
      $('#ai-chat-badge').text(count).show();
      $('#ai-selection-info').html(`<i class="fa fa-filter"></i> Filtre actif : ${count} document${count > 1 ? 's' : ''} sélectionné${count > 1 ? 's' : ''}`).show();
    } else {
      $('#ai-chat-badge').hide();
      $('#ai-selection-info').hide();
    }
  }

  function restorePdfSelection() {
    if (selectedPdfIds.length === 0) return;
    $('.pdf-select-cb').each(function () {
      if (selectedPdfIds.includes($(this).val())) {
        $(this).prop('checked', true);
      }
    });

    const total = $('.pdf-select-cb').length;
    const checked = $('.pdf-select-cb:checked').length;
    if (total > 0 && total === checked) {
      $('#selectAllPdfs').prop('checked', true);
    }
  }

  window.loadLibrary = function (page = 1, sort = null, order = null) {
    if (sort) currentSort = sort;
    if (order) currentOrder = order;

    const query = $('#search_query').val();
    const format = $('#filter_format').val();
    const color = $('#filter_color').val();
    const tag = activeTags.join(',');

    console.log("--- loadLibrary ---");
    console.log("Params:", { query, format, color, tag, page, sort_by: currentSort, sort_order: currentOrder });

    $('#library_content').css('opacity', '0.5');

    $.ajax({
      url: '?bibliotheque_list',
      method: 'GET',
      data: {
        query,
        format,
        color,
        tag,
        page,
        sort_by: currentSort,
        sort_order: currentOrder
      },
      success: function (html) {
        console.log("Success: HTML reçu (" + html.length + " caractères)");
        $('#library_content').html(html).css('opacity', '1');
        restorePdfSelection();
        if (page > 1) {
          $('html, body').animate({ scrollTop: $('#library_content').offset().top - 100 }, 200);
        }
      },
      error: function (xhr, status, error) {
        console.error("Erreur AJAX:", status, error);
        console.log("Response text:", xhr.responseText);
        $('#library_content').html('<div class="alert alert-danger">Erreur lors du chargement (voir console).</div>').css('opacity', '1');
      }
    });
  };

  window.filterByTag = function (tag) {
    addTagFilter(tag);
  };

  function loadTags() {
    $.getJSON('?get_bibliotheque_tags', function (tags) {
      allTagsList = tags;
    });
  }

  window.addTagFilter = function (tag) {
    if (!activeTags.includes(tag)) {
      activeTags.push(tag);
      updateTagUI();
      $('#tag_filter_input').val('');
      $('#tag_autocomplete').hide();
      window.loadLibrary(1);
    }
  };

  window.removeTagFilter = function (tag) {
    activeTags = activeTags.filter(t => t !== tag);
    updateTagUI();
    window.loadLibrary(1);
  };

  function updateTagUI() {
    const container = $('#active_tags');
    container.empty();
    activeTags.forEach(tag => {
      const isExclude = tag.startsWith('-');
      const tagName = isExclude ? tag.substring(1) : tag;
      const badge = $(`
          <div class="tag-badge ${isExclude ? 'tag-exclude' : ''}">
              ${isExclude ? '<i class="fa fa-minus-circle mr-1"></i>' : ''}
              ${tagName}
              <span class="remove-tag" onclick="removeTagFilter('${tag}')">&times;</span>
          </div>
      `);
      container.append(badge);
    });
  }

  $(document).ready(function () {
    window.loadLibrary();

    $('#search_query').on('input', function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => window.loadLibrary(1), 300);
    });

    $('#filter_format, #filter_color').on('change', function () {
      window.loadLibrary(1);
    });

    $('#tag_filter_input').on('input', function () {
      const val = $(this).val().toLowerCase();

      if (val.length > 1 && (val.endsWith(',') || val.endsWith(' '))) {
        const tag = val.substring(0, val.length - 1).trim();
        if (tag) addTagFilter(tag);
        return;
      }

      const isExclude = val.startsWith('-');
      const searchVal = isExclude ? val.substring(1) : val;

      if (searchVal.length < 1) {
        $('#tag_autocomplete').hide();
        return;
      }

      const matches = allTagsList.filter(t => String(t).toLowerCase().includes(searchVal));
      if (matches.length > 0) {
        let html = '';
        matches.forEach(m => {
          const sM = String(m);
          const display = isExclude ? `Exclure : <strong>${sM}</strong>` : `Inclure : <strong>${sM}</strong>`;
          const tagVal = isExclude ? `-${sM}` : sM;
          html += `<div class="autocomplete-item" onclick="addTagFilter('${tagVal.replace(/'/g, "\\'")}')">${display}</div>`;
        });
        $('#tag_autocomplete').html(html).show();
      } else {
        $('#tag_autocomplete').hide();
      }
    });

    $('#tag_filter_input').on('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        const val = $(this).val().trim();
        if (val) {
          addTagFilter(val);
        }
        return false;
      }
      if (e.key === 'Backspace' && $(this).val() === '' && activeTags.length > 0) {
        removeTagFilter(activeTags[activeTags.length - 1]);
      }
    });

    $(document).on('click', function (e) {
      if (!$(e.target).closest('.position-relative').length) {
        $('#tag_autocomplete').hide();
      }
    });

    loadTags();
  });

  // =========================================================================
  // FICHIERS — OUVERTURE, SUPPRESSION, ÉDITION
  // =========================================================================

  window.openLibraryFile = async function (id) {
    if (window.electronAPI && window.electronAPI.openFile) {
      try {
        const res = await fetch('?get_bibliotheque_file_info&id=' + id);
        const data = await res.json();
        if (data.success && data.file && data.file.filepath) {
          window.electronAPI.openFile(data.file.filepath);
        }
      } catch (e) {
        console.error('Erreur ouverture fichier:', e);
      }
    } else {
      window.open('?get_bibliotheque_file&id=' + id, '_blank');
    }
  };

  window.openDeleteModal = function (id, filename) {
    $('#delete_file_id').val(id);
    $('#delete_filename_display').text(filename);
    $('#delete_from_disk_check').prop('checked', true);
    $('#deleteFileModal').modal('show');
  };

  window.confirmDeleteFile = function () {
    const id = $('#delete_file_id').val();
    const fromDisk = $('#delete_from_disk_check').is(':checked');

    const btn = $('#deleteFileModal .btn-danger');
    const oldHtml = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-circle-notch fa-spin"></i> Suppression...');

    $.ajax({
      url: '?delete_bibliotheque_file',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ id: id, delete_from_disk: fromDisk }),
      success: function () {
        $('#deleteFileModal').modal('hide');
        window.loadLibrary();
      },
      error: function (xhr) {
        const msg = "Erreur lors de la suppression : " + (xhr.responseJSON?.error || "Erreur inconnue");
        if (window.showAppModal) window.showAppModal(msg); else alert(msg);
      },
      complete: function () {
        btn.prop('disabled', false).html(oldHtml);
      }
    });
  };

  window.editFile = function (id) {
    $.ajax({
      url: '?get_bibliotheque_file_info',
      method: 'GET',
      data: { id: id },
      success: function (response) {
        if (response.success) {
          const file = response.file;
          $('#edit_file_id').val(file.id);
          $('#edit_filename').val(file.filename);
          $('#edit_page_count').val(file.page_count);
          $('#edit_tags').val(file.tags || '');

          const meta = file.metadata || {};
          $('#edit_is_color').val(meta.is_color ? '1' : '0');
          $('#edit_imposition').val(meta.imposition || 'ppp');

          $('#editFileModal').modal('show');
        } else {
          const msg = 'Erreur: ' + response.error;
          if (window.showAppModal) window.showAppModal(msg); else alert(msg);
        }
      },
      error: function () {
        const msg = 'Erreur lors de la récupération des informations du fichier.';
        if (window.showAppModal) window.showAppModal(msg); else alert(msg);
      }
    });
  };

  window.saveMetadata = function () {
    const id = $('#edit_file_id').val();
    const data = {
      id: id,
      filename: $('#edit_filename').val(),
      page_count: $('#edit_page_count').val(),
      tags: $('#edit_tags').val(),
      is_color: $('#edit_is_color').val() === '1',
      imposition: $('#edit_imposition').val()
    };

    const btn = $('#btnSaveMetadata');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enregistrement...');

    $.ajax({
      url: '?update_bibliotheque_metadata',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(data),
      success: function (response) {
        if (response.success) {
          $('#editFileModal').modal('hide');
          window.loadLibrary(1);
        } else {
          const msg = 'Erreur: ' + response.message;
          if (window.showAppModal) window.showAppModal(msg); else alert(msg);
        }
      },
      error: function (xhr) {
        const msg = 'Erreur lors de l\'enregistrement : ' + (xhr.responseJSON ? xhr.responseJSON.error : 'Erreur inconnue');
        if (window.showAppModal) window.showAppModal(msg); else alert(msg);
      },
      complete: function () {
        btn.prop('disabled', false).html(originalText);
      }
    });
  };

  // =========================================================================
  // UPLOAD
  // =========================================================================

  window.handleFiles = function (files) {
    Array.from(files).forEach(file => uploadFile(file));
  };

  function uploadFile(file) {
    const progress = document.getElementById('uploadProgress');
    const id = 'up_' + Date.now();
    progress.insertAdjacentHTML('beforeend',
      `<div id="${id}" class="alert alert-info py-1 px-2 mt-1" style="font-size:0.85rem;">
          <i class="fa fa-spinner fa-spin"></i> <strong>${file.name}</strong> — Téléchargement en cours...
      </div>`
    );
    const formData = new FormData();
    formData.append('file', file);

    fetch('?upload_bibliotheque', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        const el = document.getElementById(id);
        if (data.success) {
          el.className = 'alert alert-success py-1 px-2 mt-1';
          el.innerHTML = `<i class="fa fa-check"></i> <strong>${file.name}</strong> — Ajouté.`;
          window.loadLibrary(1);
        } else {
          el.className = 'alert alert-danger py-1 px-2 mt-1';
          el.innerHTML = `<i class="fa fa-times"></i> <strong>${file.name}</strong> — Erreur : ${data.error || 'Inconnue'}`;
        }
        setTimeout(() => el.remove(), 5000);
      })
      .catch(() => {
        const el = document.getElementById(id);
        el.className = 'alert alert-danger py-1 px-2 mt-1';
        el.innerHTML = `<i class="fa fa-times"></i> <strong>${file.name}</strong> — Erreur réseau.`;
      });
  }

  // =========================================================================
  // RECHERCHE IA (OVERVIEW)
  // =========================================================================

  window.triggerAiSearch = async function (query) {
    if (!query) {
      query = document.getElementById('search_query').value;
    }
    if (!query) return;

    window.loadLibrary(1);

    if (query.length < 3) return;

    const modelSelect = document.getElementById('ai_model');
    const container = document.getElementById('aiOverviewContainer');

    if (!modelSelect || !container) return;

    const model = modelSelect.value;
    container.style.display = 'block';
    container.innerHTML = `
        <div class="ai-overview-header d-flex justify-content-between align-items-center">
            <div class="ai-overview-title"><i class="fa fa-magic"></i> Analyse de la Bibliothèque</div>
            <div class="d-flex align-items-center">
                <span id="aiOverviewStatus" style="font-size: 0.8rem; color: #64748b; margin-right: 15px;"></span>
                <button id="aiOverviewStopBtn" class="btn btn-sm btn-outline-danger mr-3" onclick="if(aiAbortController) { aiAbortController.abort(); this.innerHTML='<i class=\\\'fa fa-ban\\\'></i> Arrêté'; this.classList.replace('btn-outline-danger', 'btn-secondary'); this.disabled=true; const loader = document.getElementById('aiOverviewLoading'); if(loader) loader.style.display='none'; }">
                    <i class="fa fa-stop"></i> Stop
                </button>
                <button class="btn btn-sm btn-link text-muted p-0" onclick="if(aiAbortController) aiAbortController.abort(); document.getElementById('aiOverviewContainer').style.display='none'"><i class="fa fa-times" style="font-size: 1.2rem;"></i></button>
            </div>
        </div>
        <div class="row">
            <div class="col-md-7">
                <div class="mb-3" style="font-size: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                    <strong>QUESTION :</strong> <span style="color: #1e293b;">${query}</span>
                </div>
                <div id="aiOverviewThought" class="ai-thought-box" style="display:none;"></div>
                <div id="aiOverviewResponse" style="font-size: 1.6rem !important; line-height: 1.5; background: #f0f7ff; padding: 20px; border-radius: 12px; border-left: 5px solid #007bff; color: #1e293b; display:none;">
                    <strong>RÉPONSE :</strong> <span id="aiStreamingContent"></span>
                </div>
            </div>
            <div class="col-md-5">
                <div id="aiOverviewSources" class="p-3 bg-white border rounded shadow-sm" style="max-height: 600px; overflow-y: auto;">
                    <div class="text-muted italic"><i class="fa fa-circle-notch fa-spin"></i> Recherche des sources...</div>
                </div>
            </div>
        </div>
        <div class="mt-3 text-right">
            <span id="aiOverviewLoading" class="spinner-border spinner-border-sm text-primary" role="status"></span>
        </div>
    `;

    if (aiAbortController) aiAbortController.abort();
    aiAbortController = new AbortController();

    try {
      const response = await fetch('?chat_rag', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question: query,
          mode: model,
          tags: activeTags.join(','),
          selected_files: selectedPdfIds
        }),
        signal: aiAbortController.signal
      });

      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let fullContent = "";
      let buffer = "";

      while (true) {
        const { done, value } = await reader.read();

        let chunk = "";
        if (value) {
          chunk = decoder.decode(value, { stream: true });
        }

        const combined = buffer + chunk;
        const lines = combined.split("\n");
        buffer = lines.pop();

        for (const line of lines) {
          if (line.trim().startsWith("data: ")) {
            try {
              const jsonStr = line.trim().substring(6);
              if (!jsonStr) continue;
              const data = JSON.parse(jsonStr);

              const sDiv = document.getElementById('aiOverviewSources');
              const stDiv = document.getElementById('aiOverviewStatus');
              const tDiv = document.getElementById('aiOverviewThought');
              const rDiv = document.getElementById('aiOverviewResponse');
              const scSpan = document.getElementById('aiStreamingContent');

              if (data.type === 'status') {
                if (stDiv) stDiv.textContent = data.message;
                if (data.sources && data.sources.length > 0 && sDiv) {
                  let html = '<div class="mb-3 font-weight-bold text-success" style="font-size:1.1rem;"><i class="fa fa-database"></i> Sources identifiées :</div>';
                  data.sources.forEach((src) => {
                    const extractsHtml = (src.contents && src.contents.length > 0)
                      ? src.contents.map(c => `<div class="p-2 mb-2 bg-light rounded shadow-sm" style="font-size:1.1rem; border-left:4px solid #6366f1; line-height:1.4;">${c}</div>`).join('')
                      : `<div class="p-2 mb-1 bg-light rounded small italic text-muted" style="font-size:0.9rem;">Extrait non disponible</div>`;

                    html += `
                        <div class="mb-4 pb-2 border-bottom">
                            <a href="javascript:void(0)" onclick="openPdfViewer(${src.id}, '${src.title.replace(/'/g, "\\'")}')" class="font-weight-bold text-primary d-block mb-2" style="font-size: 1.2rem;">
                                <i class="fa fa-file-pdf-o"></i> ${src.title}
                            </a>
                            ${extractsHtml}
                        </div>
                    `;
                  });
                  sDiv.innerHTML = html;
                }
              }

              if (data.type === 'content') {
                fullContent += data.content;

                let displayContent = fullContent;
                if (fullContent.includes('<think>')) {
                  let parts = fullContent.split('</think>');
                  if (parts.length > 1) {
                    let thoughtText = parts[0].replace('<think>', '').trim();
                    if (tDiv) {
                      tDiv.innerHTML = thoughtText.replace(/\n/g, '<br>');
                      tDiv.style.display = 'block';
                    }
                    displayContent = parts[1].trim();
                  } else {
                    let thoughtText = fullContent.replace('<think>', '').trim();
                    if (tDiv) {
                      tDiv.innerHTML = thoughtText.replace(/\n/g, '<br>');
                      tDiv.style.display = 'block';
                    }
                    displayContent = "";
                  }
                }

                if (displayContent && scSpan) {
                  if (rDiv) rDiv.style.display = 'block';
                  scSpan.innerHTML = displayContent.replace(/\n/g, '<br>');
                }
              }

              if (data.type === 'done') {
                const lSpan = document.getElementById('aiOverviewLoading');
                if (lSpan) lSpan.style.display = 'none';
              }
              if (data.type === 'error') {
                if (stDiv) stDiv.innerHTML = `<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> ${data.message}</span>`;
                const lSpan = document.getElementById('aiOverviewLoading');
                if (lSpan) lSpan.style.display = 'none';
                break;
              }
            } catch (e) { console.error("Parse error", e, line); }
          }
        }

        if (done) break;
      }
    } catch (err) {
      if (err.name !== 'AbortError') {
        const scSpan = document.getElementById('aiStreamingContent');
        const lSpan = document.getElementById('aiOverviewLoading');
        if (scSpan) scSpan.innerHTML = '<span class="text-danger">Erreur lors de la génération.</span>';
        if (lSpan) lSpan.style.display = 'none';
      }
    }
  };

  // =========================================================================
  // IMPRESSION
  // =========================================================================

  window.printLibraryFile = function (id) {
    const url = '?get_bibliotheque_file&id=' + id;

    let iframe = document.getElementById('printIframe');
    if (!iframe) {
      iframe = document.createElement('iframe');
      iframe.id = 'printIframe';
      iframe.style.position = 'fixed';
      iframe.style.right = '0';
      iframe.style.bottom = '0';
      iframe.style.width = '0';
      iframe.style.height = '0';
      iframe.style.border = '0';
      document.body.appendChild(iframe);
    }

    iframe.src = url;
    iframe.onload = function () {
      try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      } catch (e) {
        console.error("Erreur lors de l'impression système:", e);
        window.open(url + '&print=1', '_blank');
      }
    };
  };

  // =========================================================================
  // VIEWER PDF (PDF.js)
  // =========================================================================

  var pdfDoc = null,
    pageNum = 1,
    pageRendering = false,
    pageNumPending = null,
    scale = 1.5,
    canvas = null,
    ctx = null;

  function renderPage(num) {
    pageRendering = true;
    pdfDoc.getPage(num).then(function (page) {
      var viewport = page.getViewport({ scale: scale });
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      var renderContext = {
        canvasContext: ctx,
        viewport: viewport
      };
      var renderTask = page.render(renderContext);

      renderTask.promise.then(function () {
        pageRendering = false;
        if (pageNumPending !== null) {
          renderPage(pageNumPending);
          pageNumPending = null;
        }
        $('#pdfLoading').fadeOut();
      });
    });
    document.getElementById('currentPage').textContent = num;
  }

  window.changePage = function (delta) {
    if (!pdfDoc) return;
    var newPage = pageNum + delta;
    if (newPage < 1 || newPage > pdfDoc.numPages) return;
    pageNum = newPage;
    renderPage(pageNum);
  };

  window.openPdfViewer = function (id, filename) {
    canvas = document.getElementById('pdfCanvas');
    ctx = canvas.getContext('2d');

    $('#pdfViewerTitle').html('<i class="fa fa-eye"></i> ' + filename);
    $('#pdfLoading').show();
    $('#pdfCanvas').hide();
    $('#pdfViewerModal').modal('show');

    const actionsHtml = `
        <div class="btn-group">
            <button class="btn btn-primary" onclick="openLibraryFile(${id})"><i class="fa fa-external-link"></i> Ouvrir</button>
            <button class="btn btn-info" onclick="printLibraryFile(${id})"><i class="fa fa-print"></i> Imprimer</button>
            <button class="btn btn-warning" onclick="window.location.href='?studio&file_id=${id}'">
                <i class="fa fa-magic"></i> Éditer dans le Studio
            </button>
        </div>
    `;
    $('#pdfModalActions').html(actionsHtml);

    var url = '?get_bibliotheque_file&id=' + id;
    pdfjsLib.getDocument(url).promise.then(function (pdfDoc_) {
      pdfDoc = pdfDoc_;
      document.getElementById('totalPages').textContent = pdfDoc.numPages;
      pageNum = 1;
      $('#pdfCanvas').show();
      renderPage(pageNum);
    }).catch(() => {
      $('#pdfLoading').html('<i class="fa fa-exclamation-triangle fa-2x text-warning"></i><p class="mt-2">Erreur : Impossible de charger le PDF</p>');
    });
  };

})();
