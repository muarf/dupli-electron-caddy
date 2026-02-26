<?php
// Récupérer les machines pour le mapping
require_once __DIR__ . '/../controler/functions/database.php';
$db = pdo_connect();
$photocopieurs_list = $db->query("SELECT id, marque, type_encre FROM photocopieurs WHERE actif = 1 ORDER BY marque")->fetchAll(PDO::FETCH_ASSOC);
$duplicopieurs_list = $db->query("SELECT id, marque, modele FROM duplicopieurs WHERE actif = 1 ORDER BY marque, modele")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="section">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h1 class="text-center"><i class="fa fa-print"></i> <?php _e('admin_printers.title'); ?></h1>
                <hr>

                <!-- Statut du moniteur -->
                <div class="panel panel-info" id="monitor-status-panel">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-info-circle"></i> <?php _e('admin_printers.monitor_status'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="monitor-status">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.checking_status'); ?></p>
                        </div>
                        <div id="monitor-actions" style="margin-top: 15px;">
                            <button class="btn btn-success" id="btn-start-monitor" onclick="toggleMonitor(true)"
                                style="display: none;">
                                <i class="fa fa-play"></i> <?php _e('admin_printers.start_monitor'); ?>
                            </button>
                            <button class="btn btn-warning" id="btn-stop-monitor" onclick="toggleMonitor(false)"
                                style="display: none;">
                                <i class="fa fa-stop"></i> <?php _e('admin_printers.stop_monitor'); ?>
                            </button>
                            <button class="btn btn-info" onclick="refreshStatus()">
                                <i class="fa fa-refresh"></i> <?php _e('admin_printers.refresh'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Avertissement droits administrateur (Windows uniquement) -->
                <div class="panel panel-danger" id="admin-warning-panel" style="display: none;">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> <?php _e('admin_printers.admin_rights_required'); ?>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <p><strong><?php _e('admin_printers.not_admin_msg'); ?></strong></p>
                        <p><?php _e('admin_printers.admin_rights_desc'); ?></p>

                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-6">
                                <h4><i class="fa fa-magic"></i> <?php _e('admin_printers.quick_solution'); ?></h4>
                                <button class="btn btn-warning btn-lg btn-block" id="btn-restart-admin" onclick="restartAsAdmin()">
                                    <i class="fa fa-refresh"></i> <?php _e('admin_printers.restart_admin'); ?>
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> <?php _e('admin_printers.restart_admin_desc'); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h4><i class="fa fa-graduation-cap"></i> <?php _e('admin_printers.manual_tutorial'); ?></h4>
                                <ol style="font-size: 13px;">
                                    <li><?php _e('admin_printers.step1'); ?></li>
                                    <li><?php _e('admin_printers.step2'); ?></li>
                                    <li><?php _e('admin_printers.step3'); ?></li>
                                    <li><?php _e('admin_printers.step4'); ?></li>
                                </ol>
                                <p class="text-muted" style="font-size: 12px;">
                                    <i class="fa fa-lightbulb-o"></i> <?php _e('admin_printers.admin_tip'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0;">
                            <strong><i class="fa fa-info-circle"></i> <?php _e('admin_printers.what_if_no_admin'); ?></strong><br>
                            <?php _e('admin_printers.what_if_no_admin_desc'); ?>
                        </div>
                    </div>
                </div>

                <!-- Liste des imprimantes -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-list"></i> <?php _e('admin_printers.available_printers'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="printers-list">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_printers'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Configuration Mappings -->
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-link"></i> <?php _e('admin_printers.mappings_config'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <p class="text-muted"><?php _e('admin_printers.mappings_desc'); ?></p>
                        <div id="mappings-container">
                            <table class="table table-bordered" id="mappings-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('admin_printers.system_printer'); ?></th>
                                        <th><?php _e('admin_printers.associated_machine'); ?></th>
                                        <th><?php _e('admin_machines.actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i>
                                            <?php _e('common.loading'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-bar-chart"></i> <?php _e('admin_printers.stats_title'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <div id="stats-container">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_stats'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Liste des impressions récentes -->
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> <?php _e('admin_printers.recent_prints'); ?></h3>
                    </div>
                    <div class="panel-body">
                        <!-- Controls de pagination en haut -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-sm-6">
                                <label for="items-per-page"><?php _e('admin_printers.items_per_page'); ?></label>
                                <select id="items-per-page" class="form-control"
                                    style="width: auto; display: inline-block;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <div class="checkbox"
                                    style="display: inline-block; margin-left: 20px; vertical-align: middle; margin-top: 0;">
                                    <label>
                                        <input type="checkbox" id="show-history" onchange="loadPrintJobs(1)"> <?php _e('admin_printers.show_history'); ?>
                                    </label>
                                </div>
                            </div>

                            <div class="col-sm-6 text-right">
                                <span id="pagination-info" class="text-muted"></span>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-sm-12 text-right">
                                <button class="btn btn-danger" id="btn-delete-selection" onclick="deleteSelectedJobs()"
                                    disabled>
                                    <i class="fa fa-trash"></i> <?php _e('admin_printers.delete_selection'); ?>
                                </button>
                                <button class="btn btn-danger" onclick="purgeAllJobs()">
                                    <i class="fa fa-bomb"></i> <?php _e('admin_printers.purge_history'); ?>
                                </button>
                            </div>
                        </div>

                        <div id="print-jobs-list">
                            <p><i class="fa fa-spinner fa-spin"></i> <?php _e('admin_printers.loading_jobs'); ?></p>
                        </div>

                        <!-- Pagination en bas -->
                        <div id="pagination-controls" class="text-center" style="margin-top: 15px; display: none;">
                            <nav>
                                <ul class="pagination" style="margin: 0;">
                                    <li id="btn-first-page"><a href="#" onclick="goToPage(1); return false;"><i
                                                class="fa fa-angle-double-left"></i></a></li>
                                    <li id="btn-prev-page"><a href="#" onclick="goToPreviousPage(); return false;"><i
                                                class="fa fa-angle-left"></i> <?php _e('common.previous'); ?></a></li>
                                    <li class="active"><a href="#" id="current-page-display">Page 1</a></li>
                                    <li id="btn-next-page"><a href="#" onclick="goToNextPage(); return false;"><?php _e('common.next'); ?>
                                            <i class="fa fa-angle-right"></i></a></li>
                                    <li id="btn-last-page"><a href="#" onclick="goToLastPage(); return false;"><i
                                                class="fa fa-angle-double-right"></i></a></li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Aperçu -->
        <div class="modal fade" id="previewModal" tabindex="-1" role="dialog" aria-labelledby="previewModalLabel">
            <div class="modal-dialog modal-lg" role="document" style="width: 90%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="previewModalLabel"><?php _e('admin_printers.preview_doc'); ?></h4>
                    </div>
                    <div class="modal-body text-center"
                        style="background-color: #f5f5f5; min-height: 400px; display: flex; align-items: center; justify-content: center;">
                        <img id="previewImage" src="" class="img-responsive"
                            style="max-height: 80vh; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
                        <p id="previewError" class="text-danger" style="display:none;"><i
                                class="fa fa-exclamation-triangle"></i> Impossible de charger l'image</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('admin_printers.close'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function showPreview(url, title) {
                $('#previewModalLabel').text(title);
                $('#previewImage').attr('src', url);
                $('#previewError').hide();
                $('#previewImage').show();

                // Gestion erreur chargement
                $('#previewImage').off('error').on('error', function () {
                    $(this).hide();
                    $('#previewError').show();
                });

                $('#previewModal').modal('show');
            }
        </script>

        <script>
            // Vérifier si l'API Electron est disponible
            const hasElectronAPI = typeof window.electronAPI !== 'undefined';

            // Fonction pour vérifier le statut admin
            async function checkAdminStatus() {
                if (!hasElectronAPI) {
                    return; // Pas d'API Electron, pas besoin de vérifier
                }

                try {
                    const result = await window.electronAPI.checkAdminStatus();
                    if (result.success && !result.isAdmin) {
                        // Afficher le panneau d'avertissement
                        document.getElementById('admin-warning-panel').style.display = 'block';
                    }
                } catch (error) {
                    console.error('Erreur lors de la vérification admin:', error);
                }
            }

            // Fonction pour relancer en admin
            async function restartAsAdmin() {
                if (!hasElectronAPI) {
                    showAppModal({ type: 'warning', message: '<?php echo __js('admin_printers.electron_api_unavailable'); ?>' });
                    return;
                }

                // Utiliser showAppModal avec une Promesse pour gérer la confirmation asynchrone
                const confirmed = await new Promise(resolve => {
                    showAppModal({
                        type: 'warning',
                        title: '<?php echo __js('admin_printers.restart_required'); ?>',
                        message: '<?php echo __js('admin_printers.restart_admin_confirm'); ?>',
                        confirm: true,
                        onConfirm: () => resolve(true),
                        onClose: () => resolve(false) // Si fermé sans confirmer
                    });
                });

                if (!confirmed) {
                    return;
                }

                try {
                    const btn = document.getElementById('btn-restart-admin');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> <?php echo __js('admin_printers.restarting'); ?>';

                    const result = await window.electronAPI.restartAsAdmin();
                    if (!result.success) {
                        showAppModal({ type: 'danger', message: '<?php echo __js('admin_printers.restart_error'); ?>' + result.error });
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-refresh"></i> <?php echo __js('admin_printers.restart_admin'); ?>';
                    }
                } catch (error) {
                    showAppModal({ type: 'danger', message: '<?php echo __js('common.error'); ?> : ' + error.message });
                }
            }

            // Fonction pour afficher le statut du moniteur
            async function refreshStatus() {
                const statusDiv = document.getElementById('monitor-status');
                const startBtn = document.getElementById('btn-start-monitor');
                const stopBtn = document.getElementById('btn-stop-monitor');

                if (!hasElectronAPI) {
                    statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo __js('admin_printers.electron_api_unavailable'); ?></div>';
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'none';
                    return;
                }

                try {
                    const status = await window.electronAPI.getPrinterMonitorStatus();

                    if (!status.available) {
                        statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo __js('admin_printers.windows_only'); ?></div>';
                        startBtn.style.display = 'none';
                        stopBtn.style.display = 'none';
                    } else if (status.status === 'active') {
                        statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo __js('admin_printers.monitor_active_desc'); ?></div>';
                        startBtn.style.display = 'none';
                        stopBtn.style.display = 'inline-block';
                    } else {
                        statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-pause-circle"></i> <?php echo __js('admin_printers.monitor_inactive_desc'); ?></div>';
                        startBtn.style.display = 'inline-block';
                        stopBtn.style.display = 'none';
                    }
                } catch (error) {
                    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?php echo __js('common.error'); ?> : ' + error.message + '</div>';
                }
            }

            // Fonction pour démarrer/arrêter le moniteur
            async function toggleMonitor(start) {
                if (!hasElectronAPI) {
                    showAppModal({ type: 'warning', message: '<?php echo __js('admin_printers.electron_api_unavailable'); ?>' });
                    return;
                }

                try {
                    const result = await window.electronAPI.togglePrinterMonitor(start);
                    if (result.success) {
                        setTimeout(() => {
                            refreshStatus();
                            if (start) {
                                // Recharger les imprimantes après le démarrage
                                setTimeout(loadPrinters, 1000);
                            }
                        }, 500);
                        loadPrintJobs();
                    } else {
                        showAppModal({ type: 'danger', message: '<?php echo __js('common.error'); ?> : ' + result.error });
                    }
                } catch (error) {
                    showAppModal({ type: 'danger', message: '<?php echo __js('common.error'); ?> : ' + error.message });
                }
            }

            // Fonction pour charger la liste des imprimantes
            async function loadPrinters() {
                const printersDiv = document.getElementById('printers-list');

                if (!hasElectronAPI) {
                    printersDiv.innerHTML = '<p class="text-muted"><?php echo __js('admin_printers.electron_api_unavailable'); ?></p>';
                    return;
                }

                try {
                    // Vérifier d'abord le statut du moniteur
                    const status = await window.electronAPI.getPrinterMonitorStatus();
                    if (!status.available || status.status !== 'active') {
                        printersDiv.innerHTML = '<p class="text-muted"><?php echo __js('admin_printers.no_printers_found'); ?>. <button class="btn btn-sm btn-success" onclick="toggleMonitor(true)"><?php echo __js('admin_printers.start'); ?></button></p>';
                        return;
                    }

                    const result = await window.electronAPI.getPrinters();
                    if (result.success && result.printers && result.printers.length > 0) {
                        // Filtrer les imprimantes avec statut "Error" ou noms suspects
                        const validPrinters = result.printers.filter(printer => {
                            const name = (printer.name || printer.Name || '').toLowerCase();
                            const status = (printer.status || printer.Status || '').toString().toLowerCase();
                            // Exclure les imprimantes avec statut "Error" ou noms contenant "photocopilleuse" (faute d'orthographe)
                            return status !== 'error' && !name.includes('photocopilleuse');
                        });

                        let html = '<table class="table table-striped"><thead><tr><th><?php echo __js('admin_printers.name'); ?></th><th><?php echo __js('admin_printers.status'); ?></th><th><?php echo __js('admin_printers.is_default'); ?></th><th><?php echo __js('admin_printers.actions'); ?></th></tr></thead><tbody>';
                        result.printers.forEach(printer => {
                            const pName = printer.name || printer.Name;
                            const pStatus = (printer.status || printer.Status || '').toString();
                            const pDefault = printer.isDefault || printer.Default;

                            const isDefault = pDefault ? '<span class="label label-success"><?php echo __js('admin_printers.yes'); ?></span>' : '<span class="label label-default"><?php echo __js('admin_printers.no'); ?></span>';
                            const status = pStatus.toLowerCase();
                            const name = (pName || '').toLowerCase();
                            const isError = status === 'error' || name.includes('photocopilleuse');
                            const statusClass = isError ? 'danger' : status === '0' || status === 'ok' || status === 'idle' ? 'success' : 'warning';
                            // Note: status 0 often means idle/ready in Windows CUPS-like stats, or we display what we get.

                            const deleteBtn = isError ? `<button class="btn btn-xs btn-danger" onclick="deletePrinter('${pName.replace(/'/g, "\\'")}')" title="<?php echo __js('common.delete'); ?>"><i class="fa fa-trash"></i></button>` : '';
                            html += `<tr class="${isError ? 'danger' : ''}">
                    <td>${pName || 'N/A'}</td>
                    <td><span class="label label-${statusClass}">${pStatus || 'N/A'}</span></td>
                    <td>${isDefault}</td>
                    <td>${deleteBtn}</td>
                </tr>`;
                        });
                        html += '</tbody></table>';
                        printersDiv.innerHTML = html;
                    } else {
                        printersDiv.innerHTML = '<p class="text-muted"><?php echo __js('admin_printers.no_printers_found'); ?>: ' + (result.error || 'Inconnu') + '</p>';
                    }
                } catch (error) {
                    printersDiv.innerHTML = '<div class="alert alert-danger"><?php echo __js('common.error'); ?> : ' + error.message + '</div>';
                }
            }

            // Fonction pour charger les statistiques
            async function loadStats() {
                const statsDiv = document.getElementById('stats-container');

                try {
                    // Utiliser la syntaxe ?check_print_jobs (sans page=) pour correspondre au système de routage
                    const response = await fetch('?check_print_jobs');
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Erreur parsing JSON:', text);
                        statsDiv.innerHTML = '<div class="alert alert-danger"><?php echo __js('common.error'); ?> : <?php echo __js('admin_printers.invalid_json'); ?></div>';
                        return;
                    }

                    if (data.success) {
                        let html = '<div class="row">';
                        html += '<div class="col-md-4"><div class="well text-center"><h3>' + data.total_jobs + '</h3><p><?php echo __js('admin_tirage.total_prints'); ?></p></div></div>';

                        if (data.stats && data.stats.by_printer && data.stats.by_printer.length > 0) {
                            html += '<div class="col-md-8"><h4><?php echo __js('admin_printers.associated_machine'); ?>:</h4><ul>';
                            data.stats.by_printer.forEach(stat => {
                                html += `<li><strong>${stat.printer_name}</strong>: ${stat.total_jobs} jobs, ${stat.total_pages || 0} <?php echo __js('common.pages'); ?></li>`;
                            });
                            html += '</ul></div>';
                        }
                        html += '</div>';
                        statsDiv.innerHTML = html;
                    } else {
                        statsDiv.innerHTML = '<p class="text-muted">' + (data.message || data.error || '<?php echo __js('stats.no_data'); ?>') + '</p>';
                    }
                } catch (error) {
                    statsDiv.innerHTML = '<div class="alert alert-danger"><?php echo __js('common.error'); ?> : ' + error.message + '</div>';
                }
            }

            // Variables de pagination
            let currentPage = 1;
            let itemsPerPage = 20;
            let totalJobs = 0;
            let allJobs = [];

            // Fonction pour charger les jobs d'impression
            async function loadPrintJobs(page = null) {
                if (page !== null) {
                    currentPage = page;
                }

                const jobsDiv = document.getElementById('print-jobs-list');

                try {
                    // Check history parameter
                    const showHistory = document.getElementById('show-history') ? document.getElementById('show-history').checked : false;

                    // Utiliser la syntaxe ?check_print_jobs (sans page=) pour correspondre au système de routage
                    const response = await fetch('?check_print_jobs&history=' + showHistory);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Erreur parsing JSON:', text);
                        jobsDiv.innerHTML = '<div class="alert alert-danger">Erreur: La réponse n\'est pas du JSON valide. Vérifiez la console pour plus de détails.</div>';
                        return;
                    }

                    if (data.success && data.jobs && data.jobs.length > 0) {
                        allJobs = data.jobs;
                        totalJobs = data.total_jobs || data.jobs.length;

                        // Calculer la pagination
                        const totalPages = Math.ceil(totalJobs / itemsPerPage);
                        const startIndex = (currentPage - 1) * itemsPerPage;
                        const endIndex = Math.min(startIndex + itemsPerPage, totalJobs);
                        const jobsToDisplay = allJobs.slice(startIndex, endIndex);

                        // Construire le tableau
                        let html = '<table class="table table-striped table-hover"><thead><tr><th><input type="checkbox" id="select-all-jobs" onclick="toggleSelectAll(this)"></th><th><?php echo __js('admin_printers.preview_doc'); ?></th><th><?php echo __js('common.date'); ?></th><th><?php echo __js('common.document'); ?></th><th><?php echo __js('common.format'); ?></th><th><?php echo __js('common.duplex'); ?></th><th><?php echo __js('common.color'); ?></th><th><?php echo __js('admin_printers.ink_coverage'); ?></th><th><?php echo __js('common.status'); ?></th><th><?php echo __js('common.pages'); ?></th></tr></thead><tbody>';
                        jobsToDisplay.forEach(job => {
                            const date = new Date(job.timestamp).toLocaleString('<?php echo $lang == 'fr' ? 'fr-FR' : 'en-US'; ?>');
                            const copies = job.copies || 1;
                            const totalDocPages = job.total_pages || 0;
                            const isDuplex = job.duplex === 1 || job.duplex === '1' || job.duplex === true;

                            // Calculer le total de pages (pages document × copies)
                            const totalPages = totalDocPages * copies;

                            // Calculer le nombre de feuilles
                            // En recto-verso : 1 feuille = 2 pages, sinon 1 feuille = 1 page
                            const sheets = isDuplex ? Math.ceil(totalDocPages / 2) * copies : totalDocPages * copies;

                            // Affichage : "X pages, Y feuilles"
                            const pages = totalPages + ' <?php echo __js('common.pages'); ?>, ' + sheets + ' <?php echo __js('tirage_multimachines.sheets'); ?>' + (copies > 1 ? ' (' + copies + ' copies)' : '');
                            const statusClass = job.status === 'Completed' ? 'success' : job.status === 'Printing' ? 'info' : 'warning';

                            // Format papier
                            const paperSize = job.paper_size || 'N/A';

                            // Recto-verso
                            const duplex = (job.duplex === 1 || job.duplex === '1' || job.duplex === true) ? '<?php echo __js('admin_printers.yes'); ?>' : '<?php echo __js('admin_printers.no'); ?>';

                            // Couleur
                            let colorMode = 'N/A';
                            if (job.color_mode) {
                                const colorValue = job.color_mode.toLowerCase();
                                if (colorValue === 'color' || colorValue.includes('color') || colorValue === '2') {
                                    colorMode = '<?php echo __js('common.color'); ?>';
                                } else if (colorValue === 'monochrome' || colorValue.includes('mono') || colorValue === '1') {
                                    colorMode = '<?php echo __js('common.bw'); ?>';
                                }
                            }

                            // Taux de remplissage (fill rate) - EMF detected ink coverage
                            const fillRate = job.fill_rate !== null && job.fill_rate !== undefined ? parseFloat(job.fill_rate).toFixed(1) + '%' : 'N/A';

                            // Thumbnail
                            let thumbnailHtml = '<span class="text-muted"><i class="fa fa-image"></i> N/A</span>';
                            if (job.thumbnail_url) {
                                thumbnailHtml = `<a href="#" onclick="showPreview('${job.thumbnail_url}', '${job.document.replace(/'/g, "\\'") + " - " + pages}'); return false;">
                            <img src="${job.thumbnail_url}" style="height: 40px; border: 1px solid #ddd; border-radius: 4px;" onerror="this.onerror=null; this.src=''; this.parentElement.innerHTML='<span class=\\'text-muted\\'><i class=\\'fa fa-exclamation-circle\\'></i> Err</span>'">
                        </a>`;
                            }

                            html += `<tr>
                    <td><input type="checkbox" class="job-checkbox" value="${job.id}" onclick="updateDeleteButton()"></td>
                    <td>${thumbnailHtml}</td>
                    <td>${date}</td>
                    <td>${job.document || 'N/A'}</td>
                    <td>${paperSize}</td>
                    <td>${duplex}</td>
                    <td>${colorMode}</td>
                    <td>${fillRate}</td>
                    <td><span class="label label-${statusClass}">${job.status || 'N/A'}</span></td>
                    <td>${pages}</td>
                </tr>`;
                        });
                        html += '</tbody></table>';
                        jobsDiv.innerHTML = html;

                        // Mettre à jour les contrôles de pagination
                        updatePaginationControls(totalPages);
                        // Reset select all checkbox
                        const selectAll = document.getElementById('select-all-jobs');
                        if (selectAll) selectAll.checked = false;
                        updateDeleteButton();
                    } else {
                        jobsDiv.innerHTML = '<p class="text-muted">' + (data.message || '<?php echo __js('admin_printers.no_prints_found'); ?>') + '</p>';
                        document.getElementById('pagination-controls').style.display = 'none';
                        document.getElementById('pagination-info').textContent = '';
                    }
                } catch (error) {
                    jobsDiv.innerHTML = '<div class="alert alert-danger"><?php echo __js('common.error'); ?> : ' + error.message + '</div>';
                }
            }

            // Fonction pour mettre à jour les contrôles de pagination
            function updatePaginationControls(totalPages) {
                const paginationControls = document.getElementById('pagination-controls');
                const paginationInfo = document.getElementById('pagination-info');
                const currentPageDisplay = document.getElementById('current-page-display');
                const btnFirstPage = document.getElementById('btn-first-page');
                const btnPrevPage = document.getElementById('btn-prev-page');
                const btnNextPage = document.getElementById('btn-next-page');
                const btnLastPage = document.getElementById('btn-last-page');

                if (totalPages <= 1) {
                    paginationControls.style.display = 'none';
                } else {
                    paginationControls.style.display = 'block';
                }

                // Mettre à jour l'affichage de la page courante
                currentPageDisplay.textContent = `<?php echo __js('admin_tirage.page'); ?> ${currentPage} <?php echo __js('admin_tirage.of'); ?> ${totalPages}`;

                // Mettre à jour les informations de pagination
                const startIndex = (currentPage - 1) * itemsPerPage + 1;
                const endIndex = Math.min(currentPage * itemsPerPage, totalJobs);
                
                let infoText = '<?php echo __js('admin_printers.pagination_info'); ?>';
                infoText = infoText.replace(':start', startIndex).replace(':end', endIndex).replace(':total', totalJobs);
                paginationInfo.textContent = infoText;

                // Désactiver/activer les boutons selon la page courante
                if (currentPage === 1) {
                    btnFirstPage.classList.add('disabled');
                    btnPrevPage.classList.add('disabled');
                } else {
                    btnFirstPage.classList.remove('disabled');
                    btnPrevPage.classList.remove('disabled');
                }

                if (currentPage === totalPages) {
                    btnNextPage.classList.add('disabled');
                    btnLastPage.classList.add('disabled');
                } else {
                    btnNextPage.classList.remove('disabled');
                    btnLastPage.classList.remove('disabled');
                }
            }

            // Fonctions de navigation
            function goToPage(page) {
                const totalPages = Math.ceil(totalJobs / itemsPerPage);
                if (page >= 1 && page <= totalPages) {
                    loadPrintJobs(page);
                }
            }

            function goToPreviousPage() {
                if (currentPage > 1) {
                    loadPrintJobs(currentPage - 1);
                }
            }

            function goToNextPage() {
                const totalPages = Math.ceil(totalJobs / itemsPerPage);
                if (currentPage < totalPages) {
                    loadPrintJobs(currentPage + 1);
                }
            }

            function goToLastPage() {
                const totalPages = Math.ceil(totalJobs / itemsPerPage);
                loadPrintJobs(totalPages);
            }

            // Gestion de la sélection et suppression
            function toggleSelectAll(source) {
                const checkboxes = document.querySelectorAll('.job-checkbox');
                for (let i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = source.checked;
                }
                updateDeleteButton();
            }

            function updateDeleteButton() {
                const checkboxes = document.querySelectorAll('.job-checkbox:checked');
                const btn = document.getElementById('btn-delete-selection');
                if (btn) {
                    btn.disabled = checkboxes.length === 0;
                    btn.innerHTML = `<i class="fa fa-trash"></i> <?php echo __js('admin_printers.delete_selection_count'); ?>`.replace(':count', checkboxes.length);
                }
            }

            function deleteSelectedJobs() {
                const checkboxes = document.querySelectorAll('.job-checkbox:checked');
                if (checkboxes.length === 0) return;

                showAppModal({
                    type: 'warning',
                    title: '<?php echo __js('admin_printers.delete_selection'); ?>',
                    message: `<?php echo __js('admin_printers.confirm_delete_count'); ?>`.replace(':count', checkboxes.length),
                    confirm: true,
                    onConfirm: async function () {
                        const ids = Array.from(checkboxes).map(cb => cb.value);

                        try {
                            const response = await fetch('?check_print_jobs', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'delete_jobs',
                                    ids: ids
                                })
                            });

                            const result = await response.json();

                            if (result.success) {
                                // Recharger les données
                                loadPrintJobs();
                                loadStats();
                                // Reset header checkbox
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
            }

            function purgeAllJobs() {
                showAppModal({
                    type: 'danger',
                    title: 'Purger l\'historique',
                    message: 'ATTENTION: Cette action est irréversible !<br><br>Êtes-vous sûr de vouloir supprimer TOUT l\'historique des impressions ?',
                    confirm: true,
                    onConfirm: async function () {
                        try {
                            const response = await fetch('?check_print_jobs', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'purge_all'
                                })
                            });

                            const result = await response.json();

                            if (result.success) {
                                // Recharger les données
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
            }

            // Écouter les événements d'impression en temps réel
            if (hasElectronAPI) {
                window.electronAPI.onPrintJobDetected((printData) => {
                    console.log('Impression détectée:', printData);
                    // Recharger les données
                    loadPrintJobs();
                    loadStats();
                });
            }

            // Charger les données au démarrage
            document.addEventListener('DOMContentLoaded', function () {
                // Vérifier le statut admin en premier
                checkAdminStatus();

                refreshStatus();
                loadPrinters();
                loadStats();
                loadPrintJobs();

                // Actualiser toutes les 30 secondes
                setInterval(() => {
                    loadPrintJobs();
                    loadStats();
                }, 30000);

                // Event listener pour le changement de nombre d'éléments par page
                document.getElementById('items-per-page').addEventListener('change', function () {
                    itemsPerPage = parseInt(this.value);
                    currentPage = 1; // Retour à la première page
                    loadPrintJobs();
                });
            });

            // Fonction pour supprimer une imprimante
            function deletePrinter(printerName) {
                showAppModal({
                    type: 'warning',
                    title: '<?php echo __js('admin_printers.delete_printer'); ?>',
                    message: `<?php echo __js('admin_printers.delete_printer_confirm'); ?>`.replace(':name', printerName),
                    confirm: true,
                    onConfirm: async function () {
                        if (!hasElectronAPI) {
                            showAppModal({ type: 'warning', message: '<?php echo __js('admin_printers.electron_api_unavailable'); ?>' });
                            return;
                        }

                        try {
                            const result = await window.electronAPI.deletePrinter(printerName);
                            if (result.success) {
                                showAppModal({ type: 'success', message: '<?php echo __js('admin_printers.delete_printer_success'); ?>' });
                                loadPrinters(); // Recharger la liste
                            } else {
                                showAppModal({ type: 'danger', message: '<?php echo __js('admin_printers.restart_error'); ?>' + result.error });
                            }
                        } catch (error) {
                            showAppModal({ type: 'danger', message: '<?php echo __js('common.error'); ?> : ' + error.message });
                        }
                    }
                });
            }

            // --- LOGIQUE MAPPINGS ---

            // Données des machines injectées depuis PHP
            const photocopieurs = <?php echo json_encode($photocopieurs_list); ?>;
            const duplicopieurs = <?php echo json_encode($duplicopieurs_list); ?>;

            async function loadMappings() {
                if (!hasElectronAPI) {
                    document.querySelector('#mappings-table tbody').innerHTML = '<tr><td colspan="3" class="text-center text-warning"><?php echo __js('admin_printers.electron_api_required'); ?></td></tr>';
                    return;
                }

                try {
                    // 1. Récupérer les imprimantes système
                    const printersResult = await window.electronAPI.getPrinters();
                    const systemPrinters = printersResult.success ? printersResult.printers : [];

                    // 2. Récupérer les mappings existants
                    const response = await fetch('?manage_mappings');
                    const data = await response.json();
                    const mappings = data.success ? data.mappings : [];
                    const mappingsMap = {}; // Map system_printer -> {type, id}
                    mappings.forEach(m => {
                        mappingsMap[m.system_printer_name] = { type: m.machine_type, id: m.machine_id };
                    });

                    // 3. Construire le tableau
                    let html = '';

                    // Filtrer les imprimantes "inutiles"
                    const validPrinters = systemPrinters.filter(p => {
                        // Ensure name exists
                        if (!p.name && !p.Name) return false;
                        const name = (p.name || p.Name).toLowerCase();
                        const status = (p.status || p.Status || '').toString().toLowerCase();
                        // Basic filtering
                        return status !== 'error' && !name.includes('onenote') && !name.includes('pdf');
                    });

                    validPrinters.forEach(printer => {
                        // Handle both casing commonly returned by Electron
                        const pName = printer.name || printer.Name;
                        if (!pName) return;

                        const currentMapping = mappingsMap[pName];

                        html += `<tr>
                    <td style="vertical-align: middle;"><strong>${pName}</strong></td>
                    <td>
                        <select class="form-control input-sm mapping-select" data-printer="${pName}">
                            <option value=""><?php _ejs('admin_printers.not_assigned'); ?></option>
                            <optgroup label="<?php _ejs('tirage_multimachines.photocopieur'); ?>s">
                                ${photocopieurs.map(p => `
                                    <option value="photocop_${p.id}" ${currentMapping && currentMapping.type === 'photocop' && currentMapping.id == p.id ? 'selected' : ''}>
                                        ${p.marque} (${p.type_encre})
                                    </option>
                                `).join('')}
                            </optgroup>
                            <optgroup label="<?php _ejs('tirage_multimachines.duplicopieur'); ?>s">
                                ${duplicopieurs.map(d => `
                                    <option value="dupli_${d.id}" ${currentMapping && currentMapping.type === 'dupli' && currentMapping.id == d.id ? 'selected' : ''}>
                                        ${d.marque} ${d.modele}
                                    </option>
                                `).join('')}
                            </optgroup>
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm btn-save-mapping" onclick="saveMapping('${pName.replace(/'/g, "\\'")}')">
                            <i class="fa fa-save"></i> <?php _ejs('admin_printers.save'); ?>
                        </button>
                    </td>
                </tr>`;
                    });

                    if (validPrinters.length === 0) {
                        html = '<tr><td colspan="3" class="text-center">Aucune imprimante détectée</td></tr>';
                    }

                    document.querySelector('#mappings-table tbody').innerHTML = html;

                } catch (error) {
                    console.error(error);
                    document.querySelector('#mappings-table tbody').innerHTML = `<tr><td colspan="3" class="text-center text-danger">Erreur: ${error.message}</td></tr>`;
                }
            }

            async function saveMapping(printerName) {
                const select = document.querySelector(`select[data-printer="${printerName}"]`);
                const value = select.value;

                if (!value) {
                    showAppModal('Veuillez sélectionner une machine.');
                    return;
                }

                const [type, id] = value.split('_');

                try {
                    const response = await fetch('?manage_mappings', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            system_printer_name: printerName,
                            machine_type: type,
                            machine_id: id
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Petit effet visuel
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
            }

            // Charger les mappings aussi
            document.addEventListener('DOMContentLoaded', function () {
                loadMappings();
            });
        </script>
    </div>
</div>
