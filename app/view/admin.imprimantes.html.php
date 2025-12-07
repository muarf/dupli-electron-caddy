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
                        <div id="print-jobs-list">
                            <p><i class="fa fa-spinner fa-spin"></i> Chargement des impressions...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Vérifier si l'API Electron est disponible
    const hasElectronAPI = typeof window.electronAPI !== 'undefined';

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
                    const name = (printer.Name || '').toLowerCase();
                    const status = (printer.Status || '').toLowerCase();
                    // Exclure les imprimantes avec statut "Error" ou noms contenant "photocopilleuse" (faute d'orthographe)
                    return status !== 'error' && !name.includes('photocopilleuse');
                });

                let html = '<table class="table table-striped"><thead><tr><th>Nom</th><th>Statut</th><th>Par défaut</th><th>Actions</th></tr></thead><tbody>';
                result.printers.forEach(printer => {
                    const isDefault = printer.Default ? '<span class="label label-success">Oui</span>' : '<span class="label label-default">Non</span>';
                    const status = (printer.Status || '').toLowerCase();
                    const name = (printer.Name || '').toLowerCase();
                    const isError = status === 'error' || name.includes('photocopilleuse');
                    const statusClass = isError ? 'danger' : status === 'ok' ? 'success' : 'warning';
                    const deleteBtn = isError ? `<button class="btn btn-xs btn-danger" onclick="deletePrinter('${printer.Name.replace(/'/g, "\\'")}')" title="Supprimer cette imprimante"><i class="fa fa-trash"></i></button>` : '';
                    html += `<tr class="${isError ? 'danger' : ''}">
                    <td>${printer.Name || 'N/A'}</td>
                    <td><span class="label label-${statusClass}">${printer.Status || 'N/A'}</span></td>
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

    // Fonction pour charger les jobs d'impression
    async function loadPrintJobs() {
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
                let html = '<table class="table table-striped table-hover"><thead><tr><th>Date</th><th>Document</th><th>Format</th><th>Recto-verso</th><th>Couleur</th><th>Statut</th><th>Pages</th></tr></thead><tbody>';
                data.jobs.slice(0, 20).forEach(job => {
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

                    html += `<tr>
                    <td>${date}</td>
                    <td>${job.document || 'N/A'}</td>
                    <td>${paperSize}</td>
                    <td>${duplex}</td>
                    <td>${colorMode}</td>
                    <td><span class="label label-${statusClass}">${job.status || 'N/A'}</span></td>
                    <td>${pages}</td>
                </tr>`;
                });
                html += '</tbody></table>';
                if (data.jobs.length > 20) {
                    html += '<p class="text-muted">Affichage des 20 dernières impressions sur ' + data.total_jobs + ' total.</p>';
                }
                jobsDiv.innerHTML = html;
            } else {
                jobsDiv.innerHTML = '<p class="text-muted">' + (data.message || 'Aucune impression enregistrée pour le moment. Lancez une impression pour tester le système.') + '</p>';
            }
        } catch (error) {
            jobsDiv.innerHTML = '<div class="alert alert-danger">Erreur: ' + error.message + '</div>';
        }
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
        refreshStatus();
        loadPrinters();
        loadStats();
        loadPrintJobs();

        // Actualiser toutes les 30 secondes
        setInterval(() => {
            loadPrintJobs();
            loadStats();
        }, 30000);
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
</script>