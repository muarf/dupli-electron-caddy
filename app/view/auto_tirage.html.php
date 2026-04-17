<style>
    .main-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        margin: 1rem auto;
        overflow: hidden;
    }

    .header-section {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        color: #424242;
        padding: 1.5rem;
        text-align: center;
        border-bottom: 1px solid #e0e0e0;
    }

    .header-section h1 {
        margin: 0;
        font-weight: 400;
        font-size: 2.2rem;
        color: #616161;
    }

    .form-section {
        padding: 1.5rem;
    }

    .btn-modern {
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, #81c784, #a5d6a7);
        border: none;
        color: white;
    }

    .btn-success-modern {
        background: linear-gradient(135deg, #a5d6a7, #c8e6c9);
        border: none;
        color: #2e7d32;
    }

    /* Styles pour les onglets de session */
    .session-tabs-container {
        display: flex;
        align-items: center;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #eee;
        padding: 0 10px;
        background: #f8f9fa;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        flex-wrap: wrap;
    }

    .session-tab {
        padding: 10px 15px;
        margin-right: 5px;
        cursor: pointer;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        background: #e9ecef;
        color: #495057;
        font-weight: 500;
        transition: all 0.2s;
        border: 1px solid transparent;
        border-bottom: none;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: -2px;
    }

    .session-tab:hover {
        background: #dee2e6;
    }

    .session-tab.active {
        background: white;
        color: #007bff;
        border-color: #eee;
        border-bottom: 2px solid white;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
    }

    .session-tab .close-tab {
        font-size: 14px;
        opacity: 0.5;
        transition: opacity 0.2s;
        padding: 2px 5px;
        border-radius: 4px;
    }

    .session-tab .close-tab:hover {
        opacity: 1;
        background: rgba(255, 0, 0, 0.1);
        color: #dc3545;
    }

    .add-session-tab {
        padding: 10px 15px;
        cursor: pointer;
        color: #28a745;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        margin-bottom: -2px;
    }

    .add-session-tab:hover {
        color: #218838;
        transform: scale(1.1);
    }

    /* Fancy Creation Card */
    .fancy-creation-card {
        background: white;
        border-radius: 15px;
        padding: 3rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid #f0f0f0;
        max-width: 500px;
        margin: 2rem auto;
        text-align: center;
    }

    .fancy-creation-card .icon-header {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 30px;
        color: #007bff;
    }

    .fancy-creation-card h4 {
        margin-bottom: 1.5rem;
        font-weight: 600;
        color: #333;
    }

    .fancy-input {
        border: 2px solid #eee;
        border-radius: 10px;
        padding: 12px 15px;
        font-size: 16px;
        transition: border-color 0.3s;
        margin-bottom: 1rem;
    }

    .fancy-input:focus {
        border-color: #80bdff;
        box-shadow: none;
    }

    /* Styles pour les lignes de session modifiables */
    .editable-job-row {
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .editable-job-row:hover {
        background-color: #f0f8ff !important;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
        transform: translateY(-1px);
    }

</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="main-container">
                <div class="header-section">
                    <h1><i class="fa fa-magic"></i> <?php _e('auto_tirage.title'); ?></h1>
                    <p><?php _e('auto_tirage.subtitle'); ?></p>
                </div>

                <div class="form-section">
                    <!-- Interface par onglets de Session -->
                    <div id="session-tabs-container" class="session-tabs-container">
                        <!-- Les onglets seront injectés ici par JS -->
                        <button class="add-session-tab" onclick="createNewSessionClick()" title="<?php echo __('auto_tirage.new_session'); ?>">
                            <i class="fa fa-plus-circle"></i>
                        </button>
                    </div>

                    <!-- Étape 1: Identification (Formulaire Fancy) -->
                    <div id="step-identity" style="display:none;">
                        <div class="fancy-creation-card">
                            <div class="icon-header">
                                <i class="fa fa-user-plus"></i>
                            </div>
                            <h4><?php _e('auto_tirage.start_new_session'); ?></h4>
                            <div class="form-group">
                                <input type="text" id="pseudo-input" class="form-control fancy-input"
                                    placeholder="<?php echo __('auto_tirage.who_are_you'); ?>" onkeypress="if(event.key === 'Enter') startSession()">
                                <input type="text" id="session-name-input" class="form-control fancy-input"
                                    placeholder="<?php echo __('auto_tirage.session_name_optional'); ?>"
                                    onkeypress="if(event.key === 'Enter') startSession()">
                            </div>
                            <button class="btn btn-primary-modern btn-block btn-lg" onclick="startSession()">
                                <?php _e('auto_tirage.lets_go'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Étape 2: Écoute active -->
                    <div id="step-listening" style="display:none;">
                        <!-- BUFFER ZONE: Impressions en attente -->
                        <div id="buffer-zone" class="card mb-4"
                            style="display:none; border: 2px dashed #3498db; background: #f8fbff; margin-bottom: 30px; padding: 15px;">
                            <div class="card-header bg-primary text-white">
                                <h4 class="mb-0"><i class="fa fa-inbox"></i> <?php _e('auto_tirage.pending_jobs'); ?></h4>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="text-muted mb-0"><?php _e('auto_tirage.buffer_description'); ?></p>
                                    <div id="buffer-bulk-actions" style="display: none;">
                                        <button class="btn btn-primary btn-sm mr-2" onclick="bulkMoveBufferToSession()">
                                            <i class="fa fa-plus"></i> <?php _e('auto_tirage.add_selected'); ?>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="bulkDeleteBufferJob()">
                                            <i class="fa fa-trash"></i> <?php _e('auto_tirage.delete_selected'); ?>
                                        </button>
                                    </div>
                                </div>
                                <table class="table table-striped table-hover" id="buffer-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="select-all-buffer"
                                                    onclick="toggleAllBuffer(this)"></th>
                                            <th><?php _e('auto_tirage.preview'); ?></th>
                                            <th><?php _e('auto_tirage.date'); ?></th>
                                            <th><?php _e('auto_tirage.machine'); ?></th>
                                            <th><?php _e('auto_tirage.document'); ?></th>
                                            <th><?php _e('auto_tirage.details'); ?></th>
                                            <th><?php _e('auto_tirage.ink_coverage'); ?></th>
                                            <th><?php _e('auto_tirage.action'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Jobs will be injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Session Actuelle -->
                        <div id="session-zone">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <small class="text-muted"><i class="fa fa-clock-o"></i> <span
                                        id="session-status-text"><?php _e('auto_tirage.waiting_jobs'); ?></span></small>
                                <button class="btn btn-link btn-sm text-muted" type="button" onclick="toggleLogs()">
                                    <i class="fa fa-list"></i> <?php _e('auto_tirage.view_activity'); ?>
                                </button>
                            </div>

                            <!-- Zone de logs / Status -->
                            <div id="activity-log" class="mb-4" style="display: none;">
                                <!-- Les cartes de détection apparaîtront ici -->
                            </div>

                            <!-- Liste des jobs en attente de validation -->
                            <div id="pending-list-container" style="display:none;">
                                <h5 class="border-bottom pb-2 mb-3"><i class="fa fa-list"></i> <?php _e('auto_tirage.active_jobs'); ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th><?php _e('auto_tirage.machine'); ?></th>
                                                <th><?php _e('auto_tirage.document'); ?></th>
                                                <th><?php _e('auto_tirage.details'); ?></th>
                                                <th><?php _e('tirage_multimachines.paper'); ?></th>
                                                <th><?php _e('tirage_multimachines.ink_toner'); ?></th>
                                                <th><?php _e('auto_tirage.total_price'); ?></th>
                                                <th><?php _e('auto_tirage.paper_paid'); ?></th>
                                                <th><?php _e('auto_tirage.action'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="pending-jobs-body">
                                            <!-- Rows generated by JS -->
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <td colspan="5" class="text-right"><strong><?php _e('auto_tirage.session_total'); ?></strong></td>
                                                <td colspan="3"><strong><span id="session-total">0.00</span> €</strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="text-right mt-4">
                                    <button class="btn btn-success-modern btn-lg" onclick="finishSession()">
                                        <i class="fa fa-check"></i> <?php _e('auto_tirage.finish_validate'); ?> <span id="finish-badge"
                                            class="badge badge-light">0</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale pour l'aperçu de la vignette -->
    <div class="modal fade" id="thumbnail-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="thumbnail-modal-title"><?php _e('auto_tirage.document_preview'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="modal-thumbnail-img" src=""
                        style="max-width: 100%; max-height: 80vh; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>


    <!-- Inclure la modale de sélection de session -->
    <?php include __DIR__ . '/components/session-modal.html.php'; ?>
    <!-- Inclure la modale d'édition de job -->
    <?php include __DIR__ . '/components/edit-job-modal.html.php'; ?>

    <script>
        let sessionUser = "";
        let processedJobIds = new Set();
        let pendingJobs = new Map(); // For stabilization
        let sessionJobs = []; // List of validated jobs ready for checkout
        const STABILIZATION_DELAY = 10000;
        // Initialiser à 24h en arrière pour récupérer les jobs en attente
        let lastCheckTime = Date.now() - (24 * 60 * 60 * 1000);
        let pollingInterval = null;

        // Variables multi-session
        let currentSessionId = null;
        let activeSessions = [];

        // Au chargement, vérifier s'il y a un pseudo dans l'URL ou localStorage
        document.addEventListener('DOMContentLoaded', () => {
            // Toujours charger les sessions actives en premier
            // loadActiveSessions() gère automatiquement la sélection de la dernière session
            loadActiveSessions();
        });

        async function startSession() {
            const pseudo = document.getElementById('pseudo-input').value.trim();
            if (!pseudo) return showAppModal({ message: "Merci d'entrer un nom", type: "warning" });

            sessionUser = pseudo;
            const sessionName = document.getElementById('session-name-input').value.trim();
            sessionStorage.setItem('auto_tirage_session_user', sessionUser);
            localStorage.setItem('auto_tirage_user', sessionUser);

            // Créer une nouvelle session via API
            try {
                const response = await fetch('?sessions&action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        contact: sessionUser,
                        session_name: sessionName,
                        force_new: true // On force la création d'une nouvelle session si on passe par ici
                    })
                });
                const data = await response.json();
                if (data.success) {
                    currentSessionId = data.session_id;
                    console.log('[AutoTirage] Session créée:', currentSessionId);
                    await loadActiveSessions();
                    // On laisse loadActiveSessions activer le bon onglet
                }
            } catch (error) {
                console.error('[AutoTirage] Erreur création session:', error);
            }

            document.getElementById('step-identity').style.display = 'none';
            document.getElementById('step-listening').style.display = 'block';

            // Démarrer le polling
            startPolling();
        }



        window.suspendSession = function () {
            // Juste vider l'état local et retourner au début sans fermer
            currentSessionId = null;
            sessionUser = "";
            sessionJobs = [];
            processedJobIds.clear();
            bufferJobs.clear();

            sessionStorage.removeItem('auto_tirage_session_jobs');
            sessionStorage.removeItem('auto_tirage_session_user');

            document.getElementById('step-identity').style.display = 'block';
            document.getElementById('step-listening').style.display = 'none';

            loadActiveSessions();
        };


        function startPolling() {
            pollingInterval = setInterval(checkPrintJobs, 3000);
            addLog('info', "✅ <?php _ejs('auto_tirage.system_ready'); ?>");
        }

        async function checkPrintJobs() {
            try {
                const response = await fetch(`?check_print_jobs&after=${lastCheckTime}`);
                const data = await response.json();

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

                // Check for updates on ALREADY PROCESSED jobs (Live Update)
                if (data.jobs && data.jobs.length > 0) {
                    data.jobs.forEach(job => {
                        // Use String() to handle type mismatch (string vs number)
                        if (processedJobIds.has(job.job_id) || processedJobIds.has(String(job.job_id))) {
                            checkForUpdate(job);
                        }
                    });
                }

                // Sync Buffer Jobs: remove jobs that are no longer in the API response
                if (data.success && Array.isArray(data.jobs)) {
                    const apiJobIds = new Set(data.jobs.map(j => String(j.job_id)));
                    bufferJobs.forEach((job, jobId) => {
                        if (!apiJobIds.has(String(jobId))) {
                            // Job is gone from server pool (either added to a session or deleted)
                            bufferJobs.delete(jobId);
                            const row = document.getElementById(`buffer-row-${jobId}`);
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
                console.error("Erreur polling:", error);
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
                addLog('info', `⏳ <?php _ejs('auto_tirage.job_detected'); ?>: ${job.document} (${job.total_pages} pages). <?php _ejs('auto_tirage.stabilizing'); ?>`);
                
                // NOUVEAU: Affichage immédiat dans le pool avec indicateur de stabilisation
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
                        addLog('info', `... <?php _ejs('auto_tirage.page_update'); ?> : ${job.total_pages}`);
                    }
                    // Mettre à jour la ligne dans le buffer
                    renderBufferRow(candidate.job);
                }
            }
        }



        function checkForUpdate(apiJob) {
            // 1. Check Session Jobs
            const existingIndex = sessionJobs.findIndex(j => String(j.originalJobId) === String(apiJob.job_id));
            if (existingIndex !== -1) {
                const currentJob = sessionJobs[existingIndex];
                const newFillRate = parseFloat(apiJob.fill_rate || 0);
                const oldFillRate = parseFloat(currentJob.raw_fill_rate || 0);
                const thumbnailUpdate = !currentJob.thumbnail_url && apiJob.thumbnail_url;

                if (currentJob.raw_total_pages !== apiJob.total_pages || thumbnailUpdate) {
                    simulateJob(apiJob, existingIndex);
                }
                return;
            }

            // 2. Check Buffer Jobs
            const jobIdKey = String(apiJob.job_id);
            if (bufferJobs.has(jobIdKey)) {
                const existing = bufferJobs.get(jobIdKey);
                // Only update if something relevant changed (pages, thumbnail, or ID)
                if (existing.total_pages !== apiJob.total_pages ||
                    existing.thumbnail_url !== apiJob.thumbnail_url ||
                    String(existing.id) !== String(apiJob.id)) {
                    addToBuffer(apiJob);
                }
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
                        addLog('success', `📥 <?php _ejs('auto_tirage.job_assigned'); ?> : ${candidate.job.document}`);

                        // Retirer du pool (buffer) puisqu'il passe en session
                        bufferJobs.delete(jobId);
                        const row = document.getElementById(`buffer-row-${jobId}`);
                        if (row) row.remove();
                        if (bufferJobs.size === 0) {
                            document.getElementById('buffer-zone').style.display = 'none';
                        }

                        simulateJob(candidate.job);
                    } else {
                        addLog('info', `⏸️ <?php _ejs('auto_tirage.job_waiting'); ?> : ${candidate.job.document}`);
                        // Mettre à jour la ligne pour afficher les boutons d'action
                        renderBufferRow(candidate.job);
                    }
                }
            });
        }

        // --- BUFFER ZONE LOGIC ---
        let bufferJobs = new Map();

        function addToBuffer(job) {
            bufferJobs.set(job.job_id, job);
            renderBufferRow(job);
            document.getElementById('buffer-zone').style.display = 'block';
        }

        function renderBufferRow(job) {
            const tbody = document.querySelector('#buffer-table tbody');
            let row = document.getElementById(`buffer-row-${job.job_id}`);

            // NEW: Preserve checked state if row exists
            let isChecked = false;
            if (row) {
                const cb = row.querySelector('.buffer-checkbox');
                if (cb) isChecked = cb.checked;
            }

            if (!row) {
                row = document.createElement('tr');
                row.id = `buffer-row-${job.job_id}`;
                tbody.appendChild(row);
            }

            const date = new Date(job.timestamp).toLocaleTimeString();
            const pages = job.total_pages * (job.copies || 1);
            
            // Format technical details
            const isDuplex = (job.duplex == 1 || job.duplex == '1' || job.duplex === true || String(job.duplex).toLowerCase() === 'oui');
            const colorMode = (String(job.color_mode).toLowerCase().includes('color') || String(job.color_mode) === '1') ? '<?php echo __js('tirage_multimachines.color'); ?>' : 'N&B';
            const duplexLabel = isDuplex ? 'R/V' : 'Recto';
            const rawFillValue = parseFloat(job.fill_rate || 0);
            const fillPct = rawFillValue.toFixed(1) + '%';

            const actions = job.stabilizing ? `
                <div class="text-center">
                    <i class="fa fa-spinner fa-spin text-primary"></i>
                    <div style="font-size: 10px;" class="text-muted"><?php _ejs('auto_tirage.stabilization'); ?></div>
                </div>
            ` : `
                <button class="btn btn-info btn-sm" onclick="refreshJobAnalysis('${job.job_id}', this)" title="<?php echo __js('common.refresh'); ?>">
                    <i class="fa fa-refresh"></i>
                </button>
                <button class="btn btn-primary btn-sm" onclick="moveBufferToSession('${job.job_id}')" title="<?php echo __js('auto_tirage.add_selected'); ?>">
                    <i class="fa fa-plus"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteBufferJob('${job.id}', '${job.job_id}')" title="<?php echo __js('auto_tirage.delete_selected'); ?>">
                    <i class="fa fa-trash"></i>
                </button>
            `;

            row.innerHTML = `
            <td><input type="checkbox" class="buffer-checkbox" data-id="${job.id}" data-job-id="${job.job_id}" onchange="updateBulkActionsVisibility()" ${isChecked ? 'checked' : ''} ${job.stabilizing ? 'disabled' : ''}></td>
            <td>${job.thumbnail_url ? `<img src="${job.thumbnail_url}" height="30" style="cursor: pointer; border-radius: 3px;" onclick="showThumbnailModal('${job.thumbnail_url}', '${job.document.replace(/'/g, "\\'")}')">` : '<i class="fa fa-file-o"></i>'}</td>
            <td><small>${date}</small></td>
            <td><div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${job.printer_name}">${job.printer_name}</div></td>
            <td>
                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                     title="${job.document_full_path || job.document}">
                    <strong>${job.document_display_name || job.document}</strong>
                </div>
            </td>
            <td>
                <span class="badge badge-secondary">${job.paper_size || 'A4'}</span>
                <span class="badge badge-info">${colorMode}</span>
                <span class="badge badge-light border">${duplexLabel}</span>
                <div class="mt-1"><small>${pages} pages</small></div>
            </td>
            <td>
                <div class="progress" style="height: 10px; width: 60px;" title="<?php echo __js('auto_tirage.fill_rate'); ?>: ${fillPct}">
                    <div class="progress-bar bg-info" role="progressbar" style="width: ${fillPct}" aria-valuenow="${rawFillValue}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small>${fillPct}</small>
            </td>
            <td class="align-middle">
                ${actions}
            </td>
        `;
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
                // Optimistic UI: Hide row immediately
                const row = document.getElementById(`buffer-row-${jobId}`);
                if (row) row.style.display = 'none';

                // Pass a callback to know if save succeeded
                simulateJob(job, null, jobId).then(success => {
                    if (success) {
                        // Only delete from pool if safely saved in session
                        /*
                        fetch('?check_print_jobs', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'delete_jobs',
                                ids: [dbId]
                            })
                        }).catch(e => console.error("Erreur suppression job du pool:", e));
                        */

                        bufferJobs.delete(jobId);
                        if (row) row.remove();
                        if (bufferJobs.size === 0) {
                            document.getElementById('buffer-zone').style.display = 'none';
                        }
                    } else {
                        // Revert UI if failed
                        if (row) row.style.display = '';
                        showAppModal({ message: "Erreur lors de l'ajout à la session. Veuillez réessayer.", type: "danger" });
                    }
                });
            }
        };

        window.deleteBufferJob = async function (dbId, spoolJobId) {
            showAppModal({
                message:  "<?php echo __js('auto_tirage.confirm_delete'); ?>" ,
                confirm: true,
                type: "warning"
            }, async (confirmed) => {
                if (!confirmed) return;

                // Optimistic UI: Masquer la ligne immédiatement
                const rowToHide = document.getElementById(`buffer-row-${spoolJobId}`);
                if (rowToHide) rowToHide.style.opacity = '0.5';

                try {
                    // 1. Supprimer de Windows via Electron IPC (si disponible)
                    if (window.electronAPI && window.electronAPI.deletePrintJob) {
                        console.log('[DELETE] Appel IPC deletePrintJob pour job Windows:', spoolJobId);
                        const ipcResult = await window.electronAPI.deletePrintJob(null, spoolJobId);
                        console.log('[DELETE] Résultat IPC:', ipcResult);
                    }

                    // 2. Supprimer de la base de données via PHP (par DB id)
                    const response = await fetch('?check_print_jobs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete_jobs',
                            ids: [dbId]
                        })
                    });

                    const result = await response.json();
                    
                    // 3. Nettoyage final: supprimer tout job avec ce spoolJobId 
                    // (au cas où il aurait été réinséré pendant la suppression Windows)
                    await fetch('?check_print_jobs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete_by_job_id',
                            job_id: spoolJobId
                        })
                    });
                    console.log('[DELETE] Nettoyage final par job_id:', spoolJobId);

                    if (result.success) {
                        addLog('info', `🗑️ <?php _ejs('auto_tirage.job_deleted'); ?>`);
                        bufferJobs.delete(spoolJobId);
                        const row = document.getElementById(`buffer-row-${spoolJobId}`);
                        if (row) row.remove();

                        if (bufferJobs.size === 0) {
                            document.getElementById('buffer-zone').style.display = 'none';
                        }
                    } else {
                        showAppModal({ message: "<?php _ejs('auto_tirage.delete_error'); ?>: " + result.error, type: "danger" });
                    }
                } catch (error) {
                    console.error("Erreur suppression job:", error);
                    // Rétablir l'affichage en cas d'erreur
                    if (rowToHide) rowToHide.style.opacity = '1';
                    showAppModal({ message:  "<?php echo __js('auto_tirage.communication_error'); ?>" , type: "danger" });
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
                // Update text of buttons if many
                const btnAdd = bulkActions.querySelector('button:first-child');
                const btnDel = bulkActions.querySelector('button:last-child');
                if (btnAdd) btnAdd.innerHTML = `<i class="fa fa-plus"></i> <?php _ejs('auto_tirage.add_selected'); ?> (${selected.length})`;
                if (btnDel) btnDel.innerHTML = `<i class="fa fa-trash"></i> <?php _ejs('auto_tirage.delete_selected'); ?> (${selected.length})`;
            } else {
                bulkActions.style.display = 'none';
                const selectAll = document.getElementById('select-all-buffer');
                if (selectAll) selectAll.checked = false;
            }
        };

        window.bulkMoveBufferToSession = async function () {
            const selected = document.querySelectorAll('.buffer-checkbox:checked');
            if (selected.length === 0) return;

            addLog('process', `🚀 <?php _ejs('auto_tirage.adding_jobs', ['count' => '${selected.length}']); ?>`);

            // On traite séquentiellement pour éviter de surcharger/doublons
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
                message:  `<?php echo __js('auto_tirage.confirm_delete_many', ['count' => '${selected.length}']); ?>` ,
                confirm: true,
                type: "warning"
            }, async (confirmed) => {
                if (!confirmed) return;

                // Optimistic UI: Masquer les lignes
                spoolJobIds.forEach(id => {
                    const r = document.getElementById(`buffer-row-${id}`);
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
                        addLog('info', `🗑️ <?php _ejs('auto_tirage.jobs_deleted', ['count' => '${selected.length}']); ?>`);
                        spoolJobIds.forEach(spoolJobId => {
                            bufferJobs.delete(spoolJobId);
                            const row = document.getElementById(`buffer-row-${spoolJobId}`);
                            if (row) row.remove();
                        });

                        if (bufferJobs.size === 0) {
                            document.getElementById('buffer-zone').style.display = 'none';
                        }
                        const selectAll = document.getElementById('select-all-buffer');
                        if (selectAll) selectAll.checked = false;
                        updateBulkActionsVisibility();
                    } else {
                        showAppModal({ message: "<?php _ejs('auto_tirage.delete_error'); ?>: " + result.error, type: "danger" });
                    }
                } catch (error) {
                    console.error("Erreur suppression jobs:", error);
                    // Rétablir l'affichage en cas d'erreur
                    spoolJobIds.forEach(id => {
                        const r = document.getElementById(`buffer-row-${id}`);
                        if (r) r.style.opacity = '1';
                    });
                    showAppModal({ message:  "<?php echo __js('auto_tirage.communication_error'); ?>" , type: "danger" });
                }
            });
        };

        async function simulateJob(job, updateIndex = null, bufferJobId = null, isSimulation = false) {
            addLog('process', `⚙️ <?php _ejs('auto_tirage.analyzing_job'); ?> : ${job.document}...`);

            try {
                // job.total_pages from DB is DOCUMENT pages (per copy)
                // We must multiply by copies to get global total, matching admin monitor logic.
                const copies = job.copies || 1;
                const pagesPerCopy = job.total_pages;
                const globalTotalPages = job.total_pages * copies;

                // Force robust boolean conversion for duplex
                const isDuplex = (job.duplex == 1 || job.duplex == '1' || job.duplex === true || String(job.duplex).toLowerCase() === 'oui');

                // Fill rate handling: Default to 0.5 (50%) to match DB reference point
                let rawFillRate = job.fill_rate;
                let parsedFillRate = (rawFillRate !== undefined && rawFillRate !== null) ? parseFloat(rawFillRate) : 0.5;
                
                // Normalization: if coming from a field where it's 0-100, convert to 0-1
                if (parsedFillRate > 1.0) parsedFillRate = parsedFillRate / 100.0;



                const payload = {
                    printerName: job.printer_name,
                    pages: pagesPerCopy,
                    contact: sessionUser,
                    document: job.document,
                    copies: copies,
                    total_pages: globalTotalPages,
                    duplex: isDuplex,
                    color_mode: job.color_mode,
                    paper_size: job.paper_size,
                    fill_rate: parsedFillRate,
                    thumbnail_url: job.thumbnail_url, // SEND TO BACKEND
                    timestamp: job.timestamp,

                    job_id: job.job_id, // Send original Job ID for deletion
                    internal_id: job.id, // ID unique de la table print_jobs
                    session_id: currentSessionId, // Send active session ID
                    simulate: isSimulation
                };

                // Pass the RAW total pages to the details for future comparison
                const rawTotalPages = job.total_pages;

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
                    if (!result.details) {
                        console.error('CRITICAL: result.details is null/undefined', result);
                        addLog('error', '❌ <?php _ejs('auto_tirage.internal_error'); ?>');
                        return false;
                    }
                    result.details.raw_total_pages = job.total_pages;
                    result.details.raw_fill_rate = parsedFillRate; // Store the parsed fill rate we used
                    result.details.document_name = job.document || job.document_name;
                    result.details.thumbnail_url = job.thumbnail_url; // ENSURE PERSISTENCE
                    
                    console.log("SIMULATE_RESULT:", result.details);

                    // Preserve DB ID if simulation returned null but we are updating an existing job
                    if (updateIndex !== null && sessionJobs[updateIndex] && sessionJobs[updateIndex].id && !result.details.id) {
                        result.details.id = sessionJobs[updateIndex].id;
                    }

                    if (updateIndex !== undefined && updateIndex !== null) {
                        addLog('info', `🔄 <?php _ejs('auto_tirage.updating_job'); ?> : ${job.total_pages} pages`);
                        updateJobInSession(result.details, updateIndex, job.printer_name);
                        return true;
                    } else {
                        addLog('success', `✅ ${job.document} : ${result.details.price} €`);
                        addJobToSession(result.details, job.job_id, job.printer_name);

                        // NEW: Try to delete from Windows Spooler to avoid "blocking" the queue
                        // Requires Electron context
                        if (window.electronAPI && window.electronAPI.deletePrintJob) {
                            addLog('info', '🗑️ <?php _ejs('auto_tirage.delete_spooler'); ?>');
                            window.electronAPI.deletePrintJob(job.printer_name, job.job_id)
                                .then(res => {
                                    if (res.success) addLog('success', '🗑️ <?php _ejs('auto_tirage.spooler_cleaned'); ?>');
                                    else console.warn('Erreur suppression spool:', res.error);
                                })
                                .catch(err => console.error(err));
                        }

                        // Nettoyer les logs après 10 secondes (sauf erreur)
                        setTimeout(() => {
                            cleanLogs();
                        }, 10000);

                        return true; // Success
                    }
                } else {
                    addLog('error', `❌ Erreur: ${result.error}`);
                    return false; // Failed
                }

            } catch (e) {
                console.error("Erreur lors de la simulation/sauvegarde du job:", e);
                addLog('error', "❌ <?php _ejs('auto_tirage.communication_error'); ?>");
                // Si la sauvegarde échoue, retirer le job de la session locale pour éviter les "fantômes"
                if (bufferJobId) {
                    // Si c'était un job du buffer, il garde son état dans le buffer
                    console.log("Échec sauvegarde job du buffer, annulation mouvement.");
                } else {
                    // Si c'était une détection directe, on retire l'entrée incomplète
                    // Find and remove the job from sessionJobs if it was added locally but failed to save
                    const indexToRemove = sessionJobs.findIndex(sj => sj.originalJobId == job.job_id && !sj.id); // Check for local job without DB ID
                    if (indexToRemove !== -1) {
                        sessionJobs.splice(indexToRemove, 1);
                        renderSessionTable();
                        console.log(`Job ${job.job_id} removed from local session due to save failure.`);
                    }
                }
                return false;
            }
        }

        function addJobToSession(details, jobId, printerName) {
            // ANTI-DOUBLON LOCAL : Clé composite job_id + printer_name (cross-plateforme)
            // job_id seul suffit sur Linux (compteur CUPS global monotone)
            // mais peut recycler sur Windows → on ajoute printer_name
            if (sessionJobs.some(existingJob =>
                existingJob.originalJobId == jobId &&
                (existingJob.printerName || existingJob.machine) === (printerName || details.machine)
            )) {
                console.warn(`[AUTO_TIRAGE] Job (${jobId}, ${printerName}) déjà présent dans la session, ignoré.`);
                return;
            }

            details.localId = Date.now() + Math.random().toString(36).substr(2, 9);
            details.originalJobId = jobId;
            details.printerName = printerName;
            details.feuilles_payees = false;

            // Initialiser les valeurs unitaires pour recalcul JS
            if (details.type === 'duplicopieur') {
                details.unit_master = details.nb_masters > 0 ? (details.cout_masters / details.nb_masters) : 0;
                // Pour le passage c'est plus subtil à cause de A3/A4 déjà appliqué.
                // On peut déduire depuis le total:
                // Si A4, prix_passage est la moitié du A3.
                // Mais on a le total.

                // On a besoin des prix unitaires bruts pour recalculer.
                // On peut tenter de deviner ou les demander à l'API.
                // Simplification : on utilise cout / nombre.

                details.unit_passage = details.nb_passages > 0 ? (details.cout_passages / details.nb_passages) : 0;
                details.unit_papier = details.nb_feuilles > 0 ? (details.cout_papier / details.nb_feuilles) : 0;

                // CHAINAGE DES COMPTEURS - LOGIC V2
                // Vérifier s'il y a déjà des jobs pour CETTE machine dans la session
                // Si oui, on prend le compteur APRES du dernier job comme compteur AVANT du nouveau

                // Trouver le dernier job pour cette machine (en excluant soi-même qui n'est pas encore ajouté)
                let lastJob = null;
                for (let i = sessionJobs.length - 1; i >= 0; i--) {
                    if (sessionJobs[i].machine === details.machine) {
                        lastJob = sessionJobs[i];
                        break;
                    }
                }

                if (lastJob) {
                    // On enchaine !
                    details.master_av = lastJob.master_ap;
                    details.passage_av = lastJob.passage_ap;

                    // Recalculer les AP en conséquence
                    // Note: details.nb_masters et nb_passages viennent de l'API (basé sur simulation)
                    // Ils restent valables. On décale juste les compteurs.
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

            // Preserve ALL existing properties not provided or falsy in the new details
            // This ensures thumbnail_url, document_name, etc. are never lost
            for (let key in oldJob) {
                if (!newDetails[key] && oldJob[key]) {
                    newDetails[key] = oldJob[key];
                }
            }

            // Forced overrides
            newDetails.localId = oldJob.localId;
            newDetails.originalJobId = oldJob.originalJobId;
            newDetails.printerName = printerName || oldJob.printerName;
            
            // Si Duplicopieur, on doit recalculer les unitaires si on met à jour
            if (newDetails.type === 'duplicopieur') {
                newDetails.unit_master = newDetails.nb_masters > 0 ? (newDetails.cout_masters / newDetails.nb_masters) : 0;
                newDetails.unit_passage = newDetails.nb_passages > 0 ? (newDetails.cout_passages / newDetails.nb_passages) : 0;
                newDetails.unit_papier = newDetails.nb_feuilles > 0 ? (newDetails.cout_papier / newDetails.nb_feuilles) : 0;
            }

            sessionJobs[index] = newDetails;
            saveSession();
            renderSessionTable();

            // Highlight effect
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

        function renderSessionTable() {
            const container = document.getElementById('pending-list-container');
            const tbody = document.getElementById('pending-jobs-body');
            const totalSpan = document.getElementById('session-total');
            const badge = document.getElementById('finish-badge');

            container.style.display = 'block';
            tbody.innerHTML = '';

            let globalTotal = 0;

            sessionJobs.forEach((job, index) => {
                // Defensive coding: Ensure numeric values
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
                const machineName = job.type === 'photocop' ?  "<?php echo __js('tirage_multimachines.photocopieur'); ?>"  :  "<?php echo __js('tirage_multimachines.duplicopieur'); ?>" ;

                // Fix: Use job.document_name which comes from the API details, backup with job.document if raw job
                const docName = job.document_name || job.document ||  "<?php echo __js('library.file'); ?>" ;

                // Thumbnail handling
                let thumbHtml = '';
                // Use local thumbnail_url if available, else standard fallback
                const thumbUrl = job.thumbnail_url;
                if (thumbUrl) {
                    thumbHtml = `<img src="${thumbUrl}" alt="<?php echo __js('auto_tirage.preview'); ?>" class="img-thumbnail rounded mr-2" style="width: 50px; height: 50px; object-fit: contain; cursor: pointer;" onclick="event.stopPropagation(); showThumbnailModal('${thumbUrl}', '${docName.replace(/'/g, "\\'")}')">`;
                } else {
                    thumbHtml = `<div class="d-inline-flex align-items-center justify-content-center bg-light text-muted border rounded mr-2" style="width: 50px; height: 50px;"><i class="fa fa-file-o fa-lg"></i></div>`;
                }

                // Make the ROW clickable (add a style class + onclick)
                tr.classList.add('editable-job-row');
                tr.style.position = 'relative'; // Pour le badge
                
                // Prevent if clicking on interactive elements or thumbnails
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


                const colMachine = `
                <td class="align-middle">
                    <span class="badge ${badgeClass}">${machineName}</span><br>
                    <small class="text-muted" style="font-size: 0.8em;">${job.machine || ''}</small>
                </td>
            `;

                const colDoc = `
                <td class="align-middle" style="max-width: 250px;">
                    <div class="d-flex align-items-center" style="max-width: 100%;">
                        ${thumbHtml}
                        <div style="line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">
                            <strong style="font-size: 0.95em;" title="${docName.replace(/"/g, '&quot;')}">${docName}</strong><br>
                            <small class="text-muted">${Math.round(job.pages / job.copies)} Pg ${job.copies > 1 ? `× ${job.copies} Ex` : ''}</small>
                        </div>
                    </div>
                </td>
            `;

                let colDetails = '';
                let colPaidInDetails = ''; // For Duplicopieur only

                if (job.type === 'photocop') {
                    // sheetsPerCopy = global sheets / copies
                    const pPerEx = Math.round(job.pages / job.copies);
                    colDetails = `
                    <div style="font-size: 0.9em;">
                        ${job.copies} ex × ${pPerEx} pg.<br>
                        <small>${job.duplex ? '<?php echo addslashes(__('common.duplex')); ?>' : '<?php echo addslashes(__('common.simplex')); ?>'} - ${job.taille}</small>
                        ${job.color && job.fill_rate_percent ? '<br><small class="text-muted"><?php echo addslashes(__('auto_tirage.fill_rate')); ?>: ' + job.fill_rate_percent + '%</small>' : ''}
                    </div>

                `;
                } else {
                    // Duplicopieur: Compact Grid
                    const masterAv = job.master_av !== null ? job.master_av : 0;
                    const masterAp = job.master_ap !== null ? job.master_ap : 0;
                    const passageAv = job.passage_av !== null ? job.passage_av : 0;
                    const passageAp = job.passage_ap !== null ? job.passage_ap : 0;

                    // Tambours dropdown
                    let tambourSelect = '';
                    if (job.tambours && job.tambours.length > 0) {
                        let options = job.tambours.map(t =>
                            `<option value="${t.value}" ${t.value === (job.selected_tambour || 'tambour_noir') ? 'selected' : ''} data-price="${t.price}">${t.label}</option>`
                        ).join('');
                        tambourSelect = `
                        <select class="form-control form-control-sm py-0 border-secondary mr-2" style="height: 24px; font-size: 11px; width: auto; min-width: 80px;" onchange="updateTambour(${index}, this)">
                            ${options}
                        </select>
                    `;

                        if (!job.selected_tambour) {
                            const noir = job.tambours.find(t => t.value === 'tambour_noir');
                            if (noir) job.selected_tambour = 'tambour_noir';
                            else job.selected_tambour = job.tambours[0].value;
                        }
                    }

                    // FIX: FORCE SINGLE LINE using inline styles
                    colDetails = `
                    <div style="min-width: 320px;">
                        <div style="display: flex; align-items: center; white-space: nowrap; margin-bottom: 5px; padding: 4px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;">
                            <span class="mr-1" style="font-size: 11px; font-weight:bold;"><?php echo __js('tirage_multimachines.tambour_used'); ?>:</span>
                            ${tambourSelect}
                            
                            <div class="custom-control custom-checkbox mr-3" style="display: inline-flex; align-items: center;">
                                <input type="checkbox" class="custom-control-input" id="duplex-${index}" ${job.duplex ? 'checked' : ''} onchange="toggleDuplex(${index})">
                                <label class="custom-control-label" for="duplex-${index}" style="font-size: 11px; padding-top: 2px; margin-bottom: 0;"><?php echo __js('common.duplex'); ?></label>
                            </div>

                            <div class="custom-control custom-checkbox" style="display: inline-flex; align-items: center;">
                                <input type="checkbox" class="custom-control-input" id="paid-details-${index}" ${job.feuilles_payees ? 'checked' : ''} onchange="togglePaid(${index})">
                                <label class="custom-control-label" for="paid-details-${index}" style="font-size: 11px; padding-top: 2px; margin-bottom: 0;"><?php echo __js('auto_tirage.paper_paid'); ?></label>
                            </div>
                        </div>
                        
                        <div class="card p-1 border bg-light" style="border-radius: 4px;">
                            <table class="table table-borderless table-sm mb-0" style="font-size: 11px;">
                                <thead>
                                    <tr class="text-muted text-center" style="line-height: 1;">
                                        <th class="py-0 px-1 text-left"><?php echo __js('admin_machines.counter'); ?></th>
                                        <th class="py-0 px-1"><?php echo __js('common.before'); ?></th>
                                        <th class="py-0 px-1"><?php echo __js('common.after'); ?></th>
                                        <th class="py-0 px-1 text-right"><?php echo __js('common.total'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-1 px-1 align-middle text-left"><strong>Master</strong></td>
                                        <td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center" style="height: 20px; font-size: 11px;" value="${masterAv}" onchange="updateCounterAndCalc('master_av', ${index}, this.value)"></td>
                                        <td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center font-weight-bold" style="height: 20px; font-size: 11px;" value="${masterAp}" onchange="updateCounterAndCalc('master_ap', ${index}, this.value)"></td>
                                        <td class="py-1 px-1 text-right align-middle"><span class="badge badge-light border text-dark" id="diff-master-${index}">${job.nb_masters}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 px-1 align-middle text-left"><strong>Passage</strong></td>
                                        <td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center" style="height: 20px; font-size: 11px;" value="${passageAv}" onchange="updateCounterAndCalc('passage_av', ${index}, this.value)"></td>
                                        <td class="py-1 px-1"><input type="number" class="form-control form-control-sm py-0 px-1 text-center font-weight-bold" style="height: 20px; font-size: 11px;" value="${passageAp}" onchange="updateCounterAndCalc('passage_ap', ${index}, this.value)"></td>
                                        <td class="py-1 px-1 text-right align-middle"><span class="badge badge-light border text-dark" id="diff-passage-${index}">${job.nb_passages}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                }

                const colPapier = `<td class="align-middle text-right"><small id="cout-papier-${index}" class="text-muted">${job.cout_papier ? job.cout_papier.toFixed(2) + ' €' : '-'}</small></td>`;

                let inkPriceDisplay = '-';
                if (job.type === 'photocop') {
                    inkPriceDisplay = job.cout_encre ? job.cout_encre.toFixed(2) + ' €' : '-';
                } else {
                    inkPriceDisplay = (job.cout_masters + job.cout_passages).toFixed(2) + ' €';
                }
                const colEncre = `<td class="align-middle text-right"><small id="cout-encre-${index}" class="text-muted">${inkPriceDisplay}</small></td>`;
                const colTotal = `<td class="align-middle text-right"><strong id="total-price-${index}" class="text-dark" style="font-size: 1.1em;">${currentPrice.toFixed(2)} €</strong></td>`;

                // Should column "Papier Payé" exist for Duplicopieur if it's in details?
                // Yes, user asked for it in details "on the same line".
                // I will keep the column empty to avoid breaking table layout.
                // OR render it ONLY if photocop. But header is fixed. So I must render an empty cell or "-" for dupli.

                let colPaid = '';
                if (job.type === 'photocop') {
                    colPaid = `
                    <td class="align-middle text-center">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="paid-${index}" ${job.feuilles_payees ? 'checked' : ''} onchange="togglePaid(${index})">
                            <label class="custom-control-label" for="paid-${index}"></label>
                        </div>
                    </td>
                `;
                } else {
                    colPaid = `<td class="align-middle text-center"><small class="text-muted">-</small></td>`;
                }

                const colAction = `
                <td class="align-middle text-center" style="white-space: nowrap; width: 80px;">
                    <div class="d-flex justify-content-center align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-info shadow-sm mr-1" 
                                style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" 
                                onclick="refreshJobAnalysis('${job.originalJobId}', this)" title="<?php echo __js('common.refresh', [], false); ?>">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary shadow-sm mr-1" 
                                style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" 
                                onclick="openEditJobModal(${index})" title="<?php echo __js('common.edit', [], false); ?>">
                            <i class="fa fa-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger shadow-sm" 
                                style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;" 
                                onclick="removeJob(${index})" title="<?php echo __js('common.delete', [], false); ?>">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
                tr.innerHTML = colMachine + colDoc + `<td class="p-2 align-middle">${colDetails}</td>` + colPapier + colEncre + colTotal + colPaid + colAction;
                tbody.appendChild(tr);
            });

            totalSpan.textContent = globalTotal.toFixed(2);
            badge.textContent = sessionJobs.length;
        }

        // --- Logic for Duplicopieur Counters ---
        window.updateCounterAndCalc = function (field, index, value) {
            let job = sessionJobs[index];
            const val = parseInt(value) || 0;

            // Update model
            job[field] = val;

            // Recalculate Deltas
            if (field === 'master_av' || field === 'master_ap') {
                const av = job.master_av || 0;
                const ap = job.master_ap || 0;
                job.nb_masters = Math.max(0, ap - av);

                // Recalculate Cost
                job.cout_masters = job.nb_masters * (job.unit_master || 0); // Need unit price! 
            }

            if (field === 'passage_av' || field === 'passage_ap') {
                const av = job.passage_av || 0;
                const ap = job.passage_ap || 0;
                job.nb_passages = Math.max(0, ap - av);

                // Recalculate Cost
                job.cout_passages = job.nb_passages * (job.unit_passage || 0);

                // Recalculate Paper
                recalcPaper(job);
            }

            recalcTotal(index);

            // Update UI (Partial to avoid full redraw losing focus)
            // Actually, just save session and specific fields
            saveSession();

            // Update badges
            const mBadge = document.getElementById(`diff-master-${index}`);
            if (mBadge) mBadge.textContent = `${job.nb_masters} M`;

            const pBadge = document.getElementById(`diff-passage-${index}`);
            if (pBadge) pBadge.textContent = `${job.nb_passages} P`;
        };

        window.updateTambour = function (index, selectElement) {
            let job = sessionJobs[index];
            const selectedValue = selectElement.value;
            const selectedPrice = parseFloat(selectElement.options[selectElement.selectedIndex].dataset.price || 0);

            job.selected_tambour = selectedValue;

            // Update unit price for passage!
            // NOTE: If A4 is selected, price might be halved? 
            // Backend 'unit' price logic is: "prix par passage sans papier"
            // In tirage_multimachines: if size == A4, price = price / 2
            // We should replicate this logic.

            let effectivePrice = selectedPrice;
            if (job.taille === 'A4') {
                effectivePrice = effectivePrice / 2;
            }

            job.unit_passage = effectivePrice;

            // Recalulate passage cost
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
                sheets = Math.ceil(job.nb_passages / 2); // Assuming simple division for manual duplex logic
            }
            job.nb_feuilles = sheets;
            job.cout_papier = sheets * (job.unit_papier || 0);
        }

        function recalcTotal(index) {
            let job = sessionJobs[index];

            // Total Base
            // FIX: Include cout_encre for photocopiers
            let total = (job.cout_masters || 0) + (job.cout_passages || 0) + (job.cout_papier || 0) + (job.cout_encre || 0);
            job.price = parseFloat(total.toFixed(2)); // Store rounded

            let finalPrice = job.price;
            if (job.feuilles_payees) {
                finalPrice = Math.max(0, finalPrice - (job.cout_papier || 0));
            }

            // Update UI
            const elPapier = document.getElementById(`cout-papier-${index}`);
            if (elPapier) elPapier.textContent = job.cout_papier.toFixed(2) + ' €';

            const elEncre = document.getElementById(`cout-encre-${index}`);
            if (elEncre) {
                // FIX: Display cout_encre for photocop, else masters+passages
                const ink = (job.type === 'photocop') ? (job.cout_encre || 0) : ((job.cout_masters || 0) + (job.cout_passages || 0));
                elEncre.textContent = ink.toFixed(2) + ' €';
            }

            const elTotal = document.getElementById(`total-price-${index}`);
            if (elTotal) elTotal.textContent = finalPrice.toFixed(2) + ' €';

            // Update Global Total
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

            // Recalc total for this row immediately (in case price changed)
            recalcTotal(index);

            saveSession();
        };

        window.refreshJobAnalysis = async function (jobId, btn) {
            if (!window.electronAPI || !window.electronAPI.reanalyzePrintJob) {
                return showAppModal({ message: "API Electron non disponible", type: "warning" });
            }

            const icon = btn.querySelector('i');
            icon.classList.add('fa-spin');
            btn.disabled = true;

            try {
                addLog('process', `🔄 Ré-analyse forcée pour le job ${jobId}...`);
                const result = await window.electronAPI.reanalyzePrintJob(jobId);
                
                if (result.success) {
                    addLog('success', `✅ Ré-analyse terminée pour le job ${jobId}`);
                    // Le polling naturel mettra à jour l'UI, mais on peut forcer un check_print_jobs immédiat
                    await checkPrintJobs();
                } else {
                    addLog('error', `❌ Échec de la ré-analyse: ${result.error}`);
                }
            } catch (e) {
                console.error("Erreur ré-analyse:", e);
                addLog('error', `❌ Erreur lors de l'appel à la ré-analyse`);
            } finally {
                icon.classList.remove('fa-spin');
                btn.disabled = false;
            }
        };

        window.removeJob = async function (index) {
            const job = sessionJobs[index];
            showAppModal({
                message: "<?php echo __js('common.delete'); ?> ?",
                confirm: true,
                type: 'warning'
            }, async (confirmed) => {
                if (!confirmed) return;

                // 1. Supprimer du spooler Windows via Electron IPC (si job_id disponible)
                if (job.job_id && window.electronAPI && window.electronAPI.deletePrintJob) {
                    try {
                        console.log('[DELETE SESSION] Appel IPC deletePrintJob pour job Windows:', job.job_id);
                        const ipcResult = await window.electronAPI.deletePrintJob(job.printer_name || null, job.job_id);
                        console.log('[DELETE SESSION] Résultat IPC:', ipcResult);
                    } catch (ipcError) {
                        console.warn('[DELETE SESSION] Erreur IPC (non bloquante):', ipcError);
                    }
                }

                // 2. Si le job a un ID de base de données, on le supprime côté serveur
                if (job.id) {
                    try {
                        // Si le job est 'staged' (dans print_jobs), on force le type à 'print_jobs'
                        // Sinon on utilise le type normal (photocop/duplicopieur -> dupli)
                        let apiType = 'print_jobs';
                        if (!job.staged) {
                            apiType = job.type === 'duplicopieur' ? 'dupli' : job.type;
                        }
                        
                        const resp = await fetch(`?delete_session_job&id=${job.id}&type=${apiType}`);
                        const result = await resp.json();
                        if (!result.success) {
                            console.error("Erreur serveur lors de la suppression:", result.error);
                        }
                    } catch (e) {
                        console.error("Erreur réseau lors de la suppression:", e);
                    }
                }

                sessionJobs.splice(index, 1);
                saveSession();
                renderSessionTable();
                if (sessionJobs.length === 0) {
                    document.getElementById('pending-list-container').style.display = 'none';
                    // Nettoyer aussi le cache spécifique à la session
                    if (currentSessionId) {
                        sessionStorage.removeItem('auto_tirage_session_jobs_' + currentSessionId);
                    }
                }
            });
        };

        function addLog(type, message) {
            const logContainer = document.getElementById('activity-log');
            const div = document.createElement('div');
            const alertType = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'process' ? 'warning' : 'info'));
            div.className = `alert alert-${alertType} py-2 mb-2`;

            // Marquer les logs pour nettoyage facile
            div.dataset.type = type;

            const time = new Date().toLocaleTimeString();
            div.innerHTML = `<strong>[${time}]</strong> ${message}`;
            logContainer.prepend(div);

            if (logContainer.children.length > 8) {
                logContainer.lastChild.remove();
            }
        }

        function cleanLogs() {
            // Enlève TOUS les logs
            const logContainer = document.getElementById('activity-log');
            logContainer.innerHTML = '';
            // Réinsère "Système prêt"
            addLog('info', "✅ <?php echo __js('auto_tirage.system_ready'); ?>");
        }

        window.toggleLogs = function () {
            const el = document.getElementById('activity-log');
            const isHidden = el.style.display === 'none';
            el.style.display = isHidden ? 'block' : 'none';

            // Modifier le texte du bouton si nécessaire
            const btn = document.querySelector('button[onclick="toggleLogs()"]');
            if (btn) {
                btn.innerHTML = isHidden ? '<i class="fa fa-list"></i> Masquer l\'activité' : '<i class="fa fa-list"></i> Voir l\'activité';
            }
        };

        window.quitSession = function (idToClose) {
            const id = idToClose || currentSessionId;
            
            showAppModal({
                title: "Clôturer la session",
                message: "Voulez-vous CLÔTURER définitivement cette session ?<br><br>Cela la retirera de la liste des sessions actives.",
                type: "warning",
                confirm: true
            }, async function(confirmed) {
                if (!confirmed) return;

                if (id) {
                    try {
                        // Clôturer la session via API
                        await fetch(`?sessions&action=close&id=${id}`);
                    } catch (e) {
                        console.error('Erreur fermeture session:', e);
                    }
                }

                if (id == currentSessionId) {
                    sessionStorage.removeItem('auto_tirage_session_user');
                    localStorage.removeItem('auto_tirage_user');
                    localStorage.removeItem('auto_tirage_last_session_id');
                    window.location.reload();
                } else {
                    // Juste rafraîchir la liste si on ferme une autre session
                    loadActiveSessions();
                }
            });
        };

        window.finishSession = async function () {
            if (sessionJobs.length === 0) return showAppModal({ message: "<?php echo __js('admin_tirage.no_prints_selected'); ?>", type: "warning" });

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?tirage_multimachines';

            addHidden(form, 'contact', sessionUser);
            addHidden(form, 'ok', '1');
            
            // Transmettre l'ID de session à la page de confirmation
            if (currentSessionId) {
                addHidden(form, 'session_id', currentSessionId);
            }

            sessionJobs.forEach((job, index) => {
                addHidden(form, `machines[${index}][type]`, job.type === 'photocop' ? 'photocopieur' : 'duplicopieur');

                if (job.type === 'photocop') {
                    addHidden(form, `machines[${index}][machine]`, job.machine);
                    if (job.originalJobId) {
                        addHidden(form, `machines[${index}][job_id]`, job.originalJobId);
                        addHidden(form, `machines[${index}][printer_name]`, job.printerName || job.machine); // Use printerName if available
                    }
                    if (job.raw_fill_rate !== undefined) {
                        addHidden(form, `machines[${index}][fill_rate]`, job.raw_fill_rate);
                    }
                    if (job.thumbnail_url) {
                        addHidden(form, `machines[${index}][thumbnail_url]`, job.thumbnail_url);
                    }
                    if (job.document_name || job.document) {
                        addHidden(form, `machines[${index}][document_name]`, job.document_name || job.document);
                    }

                    const bPrefix = `machines[${index}][brochures][0]`;
                    // Use nb_feuilles from backend if available (handles duplex correctly), else fallback
                    const sheetsPerCopy = job.nb_feuilles
                        ? Math.ceil(job.nb_feuilles / job.copies)
                        : Math.ceil((job.pages / job.copies) / (job.duplex ? 2 : 1));

                    addHidden(form, `${bPrefix}[nb_exemplaires]`, job.copies);
                    addHidden(form, `${bPrefix}[nb_feuilles]`, sheetsPerCopy);
                    addHidden(form, `${bPrefix}[nb_pages]`, job.pages / job.copies);
                    addHidden(form, `${bPrefix}[taille]`, job.taille);
                    addHidden(form, `${bPrefix}[rv]`, job.duplex ? 'oui' : 'non');
                    addHidden(form, `${bPrefix}[couleur]`, job.color ? 'oui' : 'non');
                    addHidden(form, `${bPrefix}[feuilles_payees]`, job.feuilles_payees ? 'oui' : 'non');
                } else {
                    addHidden(form, `machines[${index}][duplicopieur_id]`, job.machine_id);
                    if (job.originalJobId) {
                        addHidden(form, `machines[${index}][job_id]`, job.originalJobId);
                        addHidden(form, `machines[${index}][printer_name]`, job.printerName || job.machine);
                    }
                    addHidden(form, `machines[${index}][nb_masters]`, job.nb_masters);
                    addHidden(form, `machines[${index}][nb_passages]`, job.nb_passages);
                    addHidden(form, `machines[${index}][rv]`, job.duplex ? 'oui' : 'non');
                    addHidden(form, `machines[${index}][feuilles_payees]`, job.feuilles_payees ? 'oui' : 'non');
                    addHidden(form, `machines[${index}][A4]`, job.taille === 'A4' ? 'A4' : 'A3');
                    addHidden(form, `machines[${index}][tambour]`, job.selected_tambour || 'tambour_noir');

                    // --- AJOUTS COMPTEURS ---
                    addHidden(form, `machines[${index}][master_av]`, job.master_av !== undefined ? job.master_av : 0);
                    addHidden(form, `machines[${index}][master_ap]`, job.master_ap !== undefined ? job.master_ap : 0);
                    addHidden(form, `machines[${index}][passage_av]`, job.passage_av !== undefined ? job.passage_av : 0);
                    addHidden(form, `machines[${index}][passage_ap]`, job.passage_ap !== undefined ? job.passage_ap : 0);

                    // Ajouter session_id si disponible
                    if (currentSessionId) {
                        addHidden(form, `machines[${index}][session_id]`, currentSessionId);
                    }
                    if (job.thumbnail_url) {
                        addHidden(form, `machines[${index}][thumbnail_url]`, job.thumbnail_url);
                    }
                    if (job.document_name || job.document) {
                        addHidden(form, `machines[${index}][document_name]`, job.document_name || job.document);
                    }
                    if (job.raw_fill_rate !== undefined) {
                        addHidden(form, `machines[${index}][fill_rate]`, job.raw_fill_rate);
                    }
                }

                // FIX: Envoyer l'ID de base de données pour permettre la mise à jour (évite les doublons)
                if (job.id) {
                    addHidden(form, `machines[${index}][db_id]`, job.id);
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

        // ===== FONCTIONS MULTI-SESSION =====

        // Charger les sessions actives
        async function loadActiveSessions() {
            try {
                const response = await fetch('?sessions&action=list');
                const data = await response.json();
                activeSessions = data.sessions || [];

                renderSessionTabs();

                console.log('[AutoTirage] Sessions chargées:', activeSessions.length);

                // Auto-sélectionner une session si aucune n'est active
                if (activeSessions.length === 0 && !currentSessionId) {
                    createNewSessionClick();
                } else if (!currentSessionId && activeSessions.length > 0) {
                    // Tenter de restaurer la dernière session utilisée, sinon prendre la plus récente
                    const lastId = localStorage.getItem('auto_tirage_last_session_id');
                    const sessionToSelect = (lastId && activeSessions.some(s => s.id == lastId))
                        ? parseInt(lastId)
                        : activeSessions[0].id;
                    console.log('[AutoTirage] Auto-sélection session:', sessionToSelect);
                    switchSession(sessionToSelect);
                }
            } catch (error) {
                console.error('[AutoTirage] Erreur chargement sessions:', error);
            }
        }

        // Rendu des onglets
        function renderSessionTabs() {
            const container = document.getElementById('session-tabs-container');
            const addButton = container.querySelector('.add-session-tab');

            // Supprimer les anciens onglets (tout sauf le bouton +)
            const oldTabs = container.querySelectorAll('.session-tab');
            oldTabs.forEach(tab => tab.remove());

            activeSessions.forEach(session => {
                const tab = document.createElement('div');
                tab.className = `session-tab ${currentSessionId == session.id ? 'active' : ''}`;
                tab.onclick = (e) => {
                    // Ne pas switcher si on clique sur la petite croix
                    if (!e.target.classList.contains('close-tab') && !e.target.parentElement.classList.contains('close-tab')) {
                        switchSession(session.id);
                    }
                };

                const name = document.createElement('span');
                name.textContent = `${session.contact}${session.session_name ? ' (' + session.session_name + ')' : ''}`;

                const closeBtn = document.createElement('span');
                closeBtn.className = 'close-tab';
                closeBtn.innerHTML = '<i class="fa fa-times"></i>';
                closeBtn.onclick = (e) => {
                    e.stopPropagation();
                    quitSession(session.id);
                };

                tab.appendChild(name);
                tab.appendChild(closeBtn);

                // Insérer avant le bouton +
                container.insertBefore(tab, addButton);
            });
        }

        function createNewSessionClick() {
            currentSessionId = null;
            sessionUser = "";
            sessionJobs = [];
            processedJobIds.clear();

            document.getElementById('step-identity').style.display = 'block';
            document.getElementById('step-listening').style.display = 'none';

            // Désélectionner tous les onglets
            document.querySelectorAll('.session-tab').forEach(t => t.classList.remove('active'));
        }

        // Changer de session
        async function switchSession(sessionId) {
            if (sessionId) {
                // Utiliser == pour comparer (sessionId peut être string ou number)
                const session = activeSessions.find(s => s.id == sessionId);
                if (session) {
                    // Sauvegarder la session actuelle avant de switcher
                    saveSession();

                    currentSessionId = sessionId;
                    sessionUser = session.contact;

                    // Mettre à jour l'UI + Passer en mode écoute
                    document.getElementById('pseudo-input').value = sessionUser;
                    document.getElementById('session-name-input').value = session.session_name || '';

                    sessionStorage.setItem('auto_tirage_session_user', sessionUser);
                    localStorage.setItem('auto_tirage_user', sessionUser);

                    document.getElementById('step-identity').style.display = 'none';
                    document.getElementById('step-listening').style.display = 'block';

                    console.log('[AutoTirage] Session sélectionnée:', session.contact);

                    // Re-render pour activer le bon onglet
                    renderSessionTabs();

                    // Charger les jobs (Local Storage d'abord, puis Serveur)
                    sessionJobs = [];
                    const saved = sessionStorage.getItem('auto_tirage_session_jobs_' + sessionId);
                    if (saved) {
                        try {
                            sessionJobs = JSON.parse(saved);
                        } catch (e) { sessionJobs = []; }
                    }

                    // Charger les jobs EXISTANTS de cette session depuis la DB
                    await loadSessionJobs(sessionId);

                    // Mémoriser pour le prochain refresh
                    localStorage.setItem('auto_tirage_last_session_id', sessionId);

                    // Démarrer le polling si pas déjà fait
                    if (!pollingInterval) startPolling();
                }
            } else {
                // Retour à "Nouvelle session"
                suspendSession();
            }
        }

        // Charger les jobs d'une session depuis le serveur
        async function loadSessionJobs(sessionId) {
            try {
                const response = await fetch(`?get_session_jobs&session_id=${sessionId}`);
                const data = await response.json();

                if (data.jobs && data.jobs.length > 0) {
                    // Convertir au format attendu par sessionJobs (en évitant les doublons avec ce qu'on a en local)
                    // Clé composite job_id + printer_name (cross-plateforme) :
                    //   - Linux CUPS : job_id est un compteur global monotone → très stable
                    //   - Windows   : job_id can recycle after spooler restart → printer_name seals the gap
                    // Fallback sur id+type si pas de job_id (jobs ajoutés manuellement).
                    data.jobs.forEach(job => {
                        const exists = sessionJobs.some(sj => {
                            // Clé primaire : job_id + printer_name
                            if (sj.originalJobId != null && job.job_id != null) {
                                return String(sj.originalJobId) === String(job.job_id) &&
                                       (sj.printerName || sj.machine) === (job.printer_name || job.printerName);
                            }
                            // Fallback pour jobs sans spooler_id (manual)
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
                    addLog('info', `📥 ${data.jobs.length} jobs chargés de la session`);
                } else if (data.jobs && data.jobs.length === 0) {
                    // Si le serveur dit 0 job, et qu'on n'a rien en local, on s'assure que c'est vide
                    if (sessionJobs.length === 0) renderSessionTable();
                }
            } catch (error) {
                console.error('[AutoTirage] Erreur chargement jobs session:', error);
            }
        }

        // --- Fonctions UI additionnelles ---
        // --- Fonctions UI additionnelles ---
        window.showThumbnailModal = function (url, title) {
            const modal = $('#thumbnail-modal');
            $('#modal-thumbnail-img').attr('src', url);
            $('#thumbnail-modal-title').text(title || "<?php echo __js('auto_tirage.document_preview'); ?>");
            modal.modal('show');
        };

        let currentEditingIndex = -1;

        window.openEditJobModal = function(index) {
          const job = sessionJobs[index];
          if (!job) return;

          currentEditingIndex = index;

          // Populate common fields
          document.getElementById('edit-document-name').value = job.document_name || job.document || 'Document';

          // Reset visibility
          document.getElementById('edit-photocop-fields').style.display = 'none';
          document.getElementById('edit-dupli-fields').style.display = 'none';

          if (job.type === 'photocop') {
              document.getElementById('edit-photocop-fields').style.display = 'block';

              const copies = parseInt(job.copies) || 1;
              document.getElementById('edit-copies').value = copies;
              
              // Direct page count per specimen
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

              // Populate Tambours
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
                  // Fallback
                  const opt = document.createElement('option');
                  opt.value = 'tambour_noir';
                  opt.text = 'Noir';
                  tambourSelect.appendChild(opt);
              }
          }

          $('#edit-job-modal').modal('show');
        };

        window.saveEditedJob = function() {
            if (currentEditingIndex === -1) return;
            let job = sessionJobs[currentEditingIndex];

            // Update Job Object based on inputs
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
                    job_id: job.originalJobId, // Reuse ID
                    document: document.getElementById('edit-document-name').value, // FROM INPUT
                    document_name: document.getElementById('edit-document-name').value, // FROM INPUT
                    thumbnail_url: job.thumbnail_url, // PRESERVE THUMBNAIL
                    timestamp: Date.now(),
                    printer_name: job.printerName || job.machine,
                    
                    // EDITED VALUES:
                    copies: copies, 
                    duplex: duplex,
                    color_mode: color ? 'Color' : 'Monochrome',
                    paper_size: size,
                    fill_rate: job.raw_fill_rate,
                    
                    // Important: simulateJob will multiply this by job.copies
                    total_pages: parseInt(document.getElementById('edit-pages').value) || 1
                };
                
                // Call simulateJob with updateIndex and IS_SIMULATION=true
                simulateJob(candidate, currentEditingIndex, null, true).then(success => {
                    if(success) $('#edit-job-modal').modal('hide');
                });
                return; // Early return as simulateJob handles UI
                
            } else if (job.type === 'duplicopieur') {
                job.nb_masters = parseInt(document.getElementById('edit-masters').value) || 0;
                job.nb_passages = parseInt(document.getElementById('edit-passages').value) || 0;
                job.duplex = document.getElementById('edit-dupli-duplex').checked;
                
                // Tambour
                const select = document.getElementById('edit-tambour');
                job.selected_tambour = select.value;
                const price = parseFloat(select.options[select.selectedIndex].dataset.price || 0);

                // Tambour logic for unit price (same as updateTambour)
                let effectivePrice = price;
                 if (job.taille === 'A4') effectivePrice = effectivePrice / 2;
                job.unit_passage = effectivePrice;

                // Recalc
                job.cout_masters = job.nb_masters * (job.unit_master || 0);
                job.cout_passages = job.nb_passages * job.unit_passage;
                
                // Recalc Paper
                recalcPaper(job);
                recalcTotal(currentEditingIndex);
                
                // Update specific row logic that simulateJob might not handle well for Dupli (manual counters)
                updateJobInSession(job, currentEditingIndex); 
                $('#edit-job-modal').modal('hide');
            }
        };

    </script>