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
                <h1 class="text-center"><i class="fa fa-print"></i> Gestion du Moniteur d'Imprimantes</h1>
                <hr>

                <!-- Statut du moniteur -->
                <div class="panel panel-info" id="monitor-status-panel">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-info-circle"></i> Statut du Moniteur</h3>
                    </div>
                    <div class="panel-body">
                        <div id="monitor-status">
                            <p><i class="fa fa-spinner fa-spin"></i> Vérification du statut...</p>
                        </div>
                        <div id="monitor-actions" style="margin-top: 15px;">
                            <button class="btn btn-success" id="btn-start-monitor" onclick="toggleMonitor(true)"
                                style="display: none;">
                                <i class="fa fa-play"></i> Démarrer le moniteur
                            </button>
                            <button class="btn btn-warning" id="btn-stop-monitor" onclick="toggleMonitor(false)"
                                style="display: none;">
                                <i class="fa fa-stop"></i> Arrêter le moniteur
                            </button>
                            <button class="btn btn-info" onclick="refreshStatus()">
                                <i class="fa fa-refresh"></i> Actualiser
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Avertissement droits administrateur (Windows uniquement) -->
                <div class="panel panel-danger" id="admin-warning-panel" style="display: none;">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-exclamation-triangle"></i> Droits Administrateur Requis</h3>
                    </div>
                    <div class="panel-body">
                        <p><strong>L'application n'est pas lancée en mode administrateur.</strong></p>
                        <p>Pour que le système puisse calculer le <strong>taux de remplissage (fill rate)</strong> des impressions, 
                        l'application doit avoir les droits administrateur pour accéder aux fichiers spool de Windows.</p>
                        
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-6">
                                <h4><i class="fa fa-magic"></i> Solution Rapide</h4>
                                <button class="btn btn-warning bt

n-lg btn-block" id="btn-restart-admin" onclick="restartAsAdmin()">
                                    <i class="fa fa-refresh"></i> Relancer en Administrateur
                                </button>
                                <p class="text-muted" style="margin-top: 10px; font-size: 12px;">
                                    <i class="fa fa-info-circle"></i> Cette action va fermer et relancer l'application avec les droits requis.
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h4><i class="fa fa-graduation-cap"></i> Tutoriel Manuel</h4>
                                <ol style="font-size: 13px;">
                                    <li>Fermez cette application</li>
                                    <li>Trouvez l'icône de l'application sur votre bureau ou dans le menu Démarrer</li>
                                    <li><strong>Clic droit</strong> sur l'icône</li>
                                    <li>Cliquez sur <strong>"Exécuter en tant qu'administrateur"</strong></li>
                                </ol>
                                <p class="text-muted" style="font-size: 12px;">
                                    <i class="fa fa-lightbulb-o"></i> Vous pouvez configurer l'appli pour toujours démarrer en admin :
                                    <br>Clic droit sur l'icône → Propriétés → Compatibilité → 
                                    Cocher "Exécuter ce programme en tant qu'administrateur"
                                </p>
                            </div>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 0;">
                            <strong><i class="fa fa-info-circle"></i> Que se passe-t-il sans les droits admin ?</strong><br>
                            L'application fonctionnera normalement, mais le <strong>taux de remplissage</strong> sur les impressions 
                            sera affiché comme <code>N/A</code> ou <code>0%</code>.
                        </div>
                    </div>
                </div>

                <!-- Liste des imprimantes -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-list"></i> Imprimantes Disponibles</h3>
                    </div>
                    <div class="panel-body">
                        <div id="printers-list">
                            <p><i class="fa fa-spinner fa-spin"></i> Chargement des imprimantes...</p>
                        </div>
                    </div>
                </div>

                <!-- Configuration Mappings -->
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-link"></i> Configuration des Mappings (Auto-Tirage)</h3>
                    </div>
                    <div class="panel-body">
                        <p class="text-muted">Associez les imprimantes système aux machines de la base de données pour
                            l'enregistrement automatique.</p>
                        <div id="mappings-container">
                            <table class="table table-bordered" id="mappings-table">
                                <thead>
                                    <tr>
                                        <th>Imprimante Système</th>
                                        <th>Machine Associée</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i>
                                            Chargement...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="panel panel-success">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-bar-chart"></i> Statistiques d'Impression</h3>
                    </div>
                    <div class="panel-body">
                        <div id="stats-container">
                            <p><i class="fa fa-spinner fa-spin"></i> Chargement des statistiques...</p>
                        </div>
                    </div>
                </div>

                <!-- Liste des impressions récentes -->
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title"><i class="fa fa-history"></i> Impressions Récentes</h3>
                    </div>
                    <div class="panel-body">
                        <!-- Controls de pagination en haut -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-sm-6">
                                <label for="items-per-page">Afficher par page:</label>
                                <select id="items-per-page" class="form-control" style="width: auto; display: inline-block;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <div class="col-sm-6 text-right">
                                <span id="pagination-info" class="text-muted"></span>
                            </div>
                        </div>
                        
                        <div id="print-jobs-list">
                            <p><i class="fa fa-spinner fa-spin"></i> Chargement des impressions...</p>
                        </div>
                        
                        <!-- Pagination en bas -->
                        <div id="pagination-controls" class="text-center" style="margin-top: 15px; display: none;">
                            <nav>
                                <ul class="pagination" style="margin: 0;">
                                    <li id="btn-first-page"><a href="#" onclick="goToPage(1); return false;"><i class="fa fa-angle-double-left"></i></a></li>
                                    <li id="btn-prev-page"><a href="#" onclick="goToPreviousPage(); return false;"><i class="fa fa-angle-left"></i> Précédent</a></li>
                                    <li class="active"><a href="#" id="current-page-display">Page 1</a></li>
                                    <li id="btn-next-page"><a href="#" onclick="goToNextPage(); return false;">Suivant <i class="fa fa-angle-right"></i></a></li>
                                    <li id="btn-last-page"><a href="#" onclick="goToLastPage(); return false;"><i class="fa fa-angle-double-right"></i></a></li>
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
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="previewModalLabel">Aperçu du document</h4>
      </div>
      <div class="modal-body text-center" style="background-color: #f5f5f5; min-height: 400px; display: flex; align-items: center; justify-content: center;">
        <img id="previewImage" src="" class="img-responsive" style="max-height: 80vh; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
        <p id="previewError" class="text-danger" style="display:none;"><i class="fa fa-exclamation-triangle"></i> Impossible de charger l'image</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
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
        $('#previewImage').off('error').on('error', function() {
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
            alert('API Electron non disponible');
            return;
        }

        if (!confirm('L\'application va se fermer et redémarrer avec les droits administrateur.\n\nVous pourriez voir une fenêtre de contrôle de compte d\'utilisateur (UAC).\n\nContinuer ?')) {
            return;
        }

        try {
            const btn = document.getElementById('btn-restart-admin');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Redémarrage...';
            
            const result = await window.electronAPI.restartAsAdmin();
            if (!result.success) {
                alert('Erreur lors du redémarrage : ' + result.error);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-refresh"></i> Relancer en Administrateur';
            }
        } catch (error) {
            alert('Erreur : ' + error.message);
        }
    }

    // Fonction pour afficher le statut du moniteur
    async function refreshStatus() {
        const statusDiv = document.getElementById('monitor-status');
        const startBtn = document.getElementById('btn-start-monitor');
        const stopBtn = document.getElementById('btn-stop-monitor');

        if (!hasElectronAPI) {
            statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> API Electron non disponible. Cette fonctionnalité nécessite l\'application Electron.</div>';
            startBtn.style.display = 'none';
            stopBtn.style.display = 'none';
            return;
        }

        try {
            const status = await window.electronAPI.getPrinterMonitorStatus();

            if (!status.available) {
                statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Le moniteur d\'imprimantes n\'est disponible que sur Windows.</div>';
                startBtn.style.display = 'none';
                stopBtn.style.display = 'none';
            } else if (status.status === 'active') {
                statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Moniteur actif</strong> - Les impressions sont surveillées en temps réel.</div>';
                startBtn.style.display = 'none';
                stopBtn.style.display = 'inline-block';
            } else {
                statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fa fa-pause-circle"></i> <strong>Moniteur inactif</strong> - Aucune surveillance en cours.</div>';
                startBtn.style.display = 'inline-block';
                stopBtn.style.display = 'none';
            }
        } catch (error) {
            statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Erreur: ' + error.message + '</div>';
        }
    }

    // Fonction pour démarrer/arrêter le moniteur
    async function toggleMonitor(start) {
        if (!hasElectronAPI) {
            alert('API Electron non disponible');
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
                alert('Erreur: ' + result.error);
            }
        } catch (error) {
            alert('Erreur: ' + error.message);
        }
    }

    // Fonction pour charger la liste des imprimantes
    async function loadPrinters() {
        const printersDiv = document.getElementById('printers-list');

        if (!hasElectronAPI) {
            printersDiv.innerHTML = '<p class="text-muted">API Electron non disponible</p>';
            return;
        }

        try {
            // Vérifier d'abord le statut du moniteur
            const status = await window.electronAPI.getPrinterMonitorStatus();
            if (!status.available || status.status !== 'active') {
                printersDiv.innerHTML = '<p class="text-muted">Le moniteur doit être démarré pour lister les imprimantes. <button class="btn btn-sm btn-success" onclick="toggleMonitor(true)">Démarrer</button></p>';
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

                let html = '<table class="table table-striped"><thead><tr><th>Nom</th><th>Statut</th><th>Par défaut</th><th>Actions</th></tr></thead><tbody>';
                result.printers.forEach(printer => {
                    const pName = printer.name || printer.Name;
                    const pStatus = (printer.status || printer.Status || '').toString();
                    const pDefault = printer.isDefault || printer.Default;
                    
                    const isDefault = pDefault ? '<span class="label label-success">Oui</span>' : '<span class="label label-default">Non</span>';
                    const status = pStatus.toLowerCase();
                    const name = (pName || '').toLowerCase();
                    const isError = status === 'error' || name.includes('photocopilleuse');
                    const statusClass = isError ? 'danger' : status === '0' || status === 'ok' || status === 'idle' ? 'success' : 'warning';
                    // Note: status 0 often means idle/ready in Windows CUPS-like stats, or we display what we get.
                    
                    const deleteBtn = isError ? `<button class="btn btn-xs btn-danger" onclick="deletePrinter('${pName.replace(/'/g, "\\'")}')" title="Supprimer cette imprimante"><i class="fa fa-trash"></i></button>` : '';
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
                printersDiv.innerHTML = '<p class="text-muted">Aucune imprimante trouvée ou erreur: ' + (result.error || 'Inconnu') + '</p>';
            }
        } catch (error) {
            printersDiv.innerHTML = '<div class="alert alert-danger">Erreur: ' + error.message + '</div>';
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
                statsDiv.innerHTML = '<div class="alert alert-danger">Erreur: La réponse n\'est pas du JSON valide. Vérifiez la console pour plus de détails.</div>';
                return;
            }

            if (data.success) {
                let html = '<div class="row">';
                html += '<div class="col-md-4"><div class="well text-center"><h3>' + data.total_jobs + '</h3><p>Total d\'impressions</p></div></div>';

                if (data.stats && data.stats.by_printer && data.stats.by_printer.length > 0) {
                    html += '<div class="col-md-8"><h4>Par imprimante:</h4><ul>';
                    data.stats.by_printer.forEach(stat => {
                        html += `<li><strong>${stat.printer_name}</strong>: ${stat.total_jobs} jobs, ${stat.total_pages || 0} pages</li>`;
                    });
                    html += '</ul></div>';
                }
                html += '</div>';
                statsDiv.innerHTML = html;
            } else {
                statsDiv.innerHTML = '<p class="text-muted">' + (data.message || data.error || 'Aucune statistique disponible') + '</p>';
            }
        } catch (error) {
            statsDiv.innerHTML = '<div class="alert alert-danger">Erreur: ' + error.message + '</div>';
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
                let html = '<table class="table table-striped table-hover"><thead><tr><th>Aperçu</th><th>Date</th><th>Document</th><th>Format</th><th>Recto-verso</th><th>Couleur</th><th>Taux remplissage</th><th>Statut</th><th>Pages</th></tr></thead><tbody>';
                jobsToDisplay.forEach(job => {
                    const date = new Date(job.timestamp).toLocaleString('fr-FR');
                    const copies = job.copies || 1;
                    const totalDocPages = job.total_pages || 0;
                    const isDuplex = job.duplex === 1 || job.duplex === '1' || job.duplex === true;

                    // Calculer le total de pages (pages document × copies)
                    const totalPages = totalDocPages * copies;

                    // Calculer le nombre de feuilles
                    // En recto-verso : 1 feuille = 2 pages, sinon 1 feuille = 1 page
                    const sheets = isDuplex ? Math.ceil(totalDocPages / 2) * copies : totalDocPages * copies;

                    // Affichage : "X pages, Y feuilles"
                    const pages = totalPages + ' pages, ' + sheets + ' feuilles' + (copies > 1 ? ' (' + copies + ' copies)' : '');
                    const statusClass = job.status === 'Completed' ? 'success' : job.status === 'Printing' ? 'info' : 'warning';

                    // Format papier
                    const paperSize = job.paper_size || 'N/A';

                    // Recto-verso
                    const duplex = (job.duplex === 1 || job.duplex === '1' || job.duplex === true) ? 'Oui' : 'Non';

                    // Couleur
                    let colorMode = 'N/A';
                    if (job.color_mode) {
                        const colorValue = job.color_mode.toLowerCase();
                        if (colorValue === 'color' || colorValue.includes('color') || colorValue === '2') {
                            colorMode = 'Couleur';
                        } else if (colorValue === 'monochrome' || colorValue.includes('mono') || colorValue === '1') {
                            colorMode = 'N&B';
                        }
                    }

                    // Taux de remplissage (fill rate) - EMF detected ink coverage
                    const fillRate = job.fill_rate !== null && job.fill_rate !== undefined ? parseFloat(job.fill_rate).toFixed(1) + '%' : 'N/A';

                    // Thumbnail
                    let thumbnailHtml = '<span class="text-muted"><i class="fa fa-image"></i> N/A</span>';
                    if (job.thumbnail_url) {
                        thumbnailHtml = `<a href="#" onclick="showPreview('${job.thumbnail_url}', '${job.document.replace(/'/g, "\\'") + " - " + pages}'); return false;">
                            <img src="${job.thumbnail_url}" style="height: 40px; border: 1px solid #ddd; border-radius: 4px;" onerror="this.src=''; this.parentElement.innerHTML='<span class=\\'text-muted\\'><i class=\\'fa fa-exclamation-circle\\'></i> Err</span>'">
                        </a>`;
                    }

                    html += `<tr>
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
            } else {
                jobsDiv.innerHTML = '<p class="text-muted">' + (data.message || 'Aucune impression enregistrée pour le moment. Lancez une impression pour tester le système.') + '</p>';
                document.getElementById('pagination-controls').style.display = 'none';
                document.getElementById('pagination-info').textContent = '';
            }
        } catch (error) {
            jobsDiv.innerHTML = '<div class="alert alert-danger">Erreur: ' + error.message + '</div>';
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
        currentPageDisplay.textContent = `Page ${currentPage} / ${totalPages}`;
        
        // Mettre à jour les informations de pagination
        const startIndex = (currentPage - 1) * itemsPerPage + 1;
        const endIndex = Math.min(currentPage * itemsPerPage, totalJobs);
        paginationInfo.textContent = `Affichage de ${startIndex} à ${endIndex} sur ${totalJobs} impressions`;
        
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

    // Écouter les événements d'impression en temps réel
    if (hasElectronAPI) {
        window.electronAPI.onPrintJobDetected((printData) => {
            console.log('Impression détectée:', printData);
            // Recharger les données
            loadPrintJobs();
            loadStats();

            // Afficher une notification
            const notification = document.createElement('div');
            notification.className = 'alert alert-info alert-dismissible';
            notification.innerHTML = `
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>Nouvelle impression détectée!</strong> ${printData.document} sur ${printData.printerName}
        `;
            document.querySelector('.container').insertBefore(notification, document.querySelector('.container').firstChild);

            // Supprimer la notification après 5 secondes
            setTimeout(() => {
                notification.remove();
            }, 5000);
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
        document.getElementById('items-per-page').addEventListener('change', function() {
            itemsPerPage = parseInt(this.value);
            currentPage = 1; // Retour à la première page
            loadPrintJobs();
        });
    });

    // Fonction pour supprimer une imprimante
    async function deletePrinter(printerName) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer l'imprimante "${printerName}" ?\n\nCette action nécessite des droits administrateur.`)) {
            return;
        }

        if (!hasElectronAPI) {
            alert('API Electron non disponible');
            return;
        }

        try {
            const result = await window.electronAPI.deletePrinter(printerName);
            if (result.success) {
                alert('Imprimante supprimée avec succès');
                loadPrinters(); // Recharger la liste
            } else {
                alert('Erreur lors de la suppression: ' + result.error);
            }
        } catch (error) {
            alert('Erreur: ' + error.message);
        }
    }

    // --- LOGIQUE MAPPINGS ---

    // Données des machines injectées depuis PHP
    const photocopieurs = <?php echo json_encode($photocopieurs_list); ?>;
    const duplicopieurs = <?php echo json_encode($duplicopieurs_list); ?>;

    async function loadMappings() {
        if (!hasElectronAPI) {
            document.querySelector('#mappings-table tbody').innerHTML = '<tr><td colspan="3" class="text-center text-warning">API Electron requise</td></tr>';
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
                return status !== 'error' && !name.includes('onenote') && !name.includes('xps') && !name.includes('pdf');
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
                            <option value="">-- Non Assigné --</option>
                            <optgroup label="Photocopieurs">
                                ${photocopieurs.map(p => `
                                    <option value="photocop_${p.id}" ${currentMapping && currentMapping.type === 'photocop' && currentMapping.id == p.id ? 'selected' : ''}>
                                        ${p.marque} (${p.type_encre})
                                    </option>
                                `).join('')}
                            </optgroup>
                            <optgroup label="Duplicopieurs">
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
                            <i class="fa fa-save"></i> Enregistrer
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
            alert('Veuillez sélectionner une machine.');
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
                alert('Erreur: ' + result.error);
            }
        } catch (error) {
            alert('Erreur réseau: ' + error.message);
        }
    }

    // Charger les mappings aussi
    document.addEventListener('DOMContentLoaded', function () {
        loadMappings();
    });
</script>