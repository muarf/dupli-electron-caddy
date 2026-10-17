// ============================================================
// admin-imprimantes-list.js -- Moniteur d'Imprimantes (Admin)
// Extrait de admin_imprimantes.html.php
// ============================================================

/* global window, document, alert, confirm, fetch */

const T = (key, fb) => (window.CONFIG && window.CONFIG.translations && window.CONFIG.translations[key]) || fb;
const hasElectronAPI = typeof window.electronAPI !== 'undefined';

window.refreshStatus = async function () {
    const statusDiv = document.getElementById('monitor-status');
    const startBtn = document.getElementById('btn-start-monitor');
    const stopBtn = document.getElementById('btn-stop-monitor');
    
    if (!hasElectronAPI) {
        if (statusDiv) statusDiv.innerHTML = '<span class="label label-warning">' + T('js.admin_imprimantes_list.mode_web_api_non_dispo', 'Mode Web - API Electron non disponible') + '</span>';
        if (startBtn) startBtn.disabled = true;
        if (stopBtn) stopBtn.disabled = true;
        return;
    }
    
    try {
        const result = await window.electronAPI.getPrinterMonitorStatus();
        if (result.available) {
            if (result.status === 'active') {
                if (statusDiv) statusDiv.innerHTML = '<span class="label label-success"><i class="fa fa-check-circle"></i> ' + T('js.admin_imprimantes_list.actif_en_cours_d_execution', 'Actif (En cours d\'exécution)') + '</span>';
                if (startBtn) startBtn.disabled = true;
                if (stopBtn) stopBtn.disabled = false;
            } else {
                if (statusDiv) statusDiv.innerHTML = '<span class="label label-danger"><i class="fa fa-times-circle"></i> ' + T('js.admin_imprimantes_list.inactif_arrete', 'Inactif (Arrêté)') + '</span>';
                if (startBtn) startBtn.disabled = false;
                if (stopBtn) stopBtn.disabled = true;
            }
        } else {
            if (statusDiv) statusDiv.innerHTML = '<span class="label label-default">' + T('js.admin_imprimantes_list.non_disponible_binaire_absent', 'Non disponible (Binaire SumatraPDF absent)') + '</span>';
            if (startBtn) startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = true;
        }
    } catch (error) {
        if (statusDiv) statusDiv.innerHTML = '<span class="label label-danger">' + T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message + '</span>';
    }
};

window.toggleMonitor = async function (enable) {
    if (!hasElectronAPI) {
        const msg = T('js.admin_imprimantes_list.api_electron_non_disponible', 'API Electron non disponible');
        if (window.showAppModal) window.showAppModal(msg); else alert(msg);
        return;
    }
    
    try {
        let result;
        if (enable) {
            result = await window.electronAPI.startPrinterMonitor();
        } else {
            result = await window.electronAPI.stopPrinterMonitor();
        }
        
        if (result.success) {
            const msg = enable ? T('js.admin_imprimantes_list.moniteur_demarre', 'Moniteur démarré avec succès') : T('js.admin_imprimantes_list.moniteur_arrete', 'Moniteur arrêté avec succès');
            if (window.showAppModal) window.showAppModal({ message: msg, type: 'success' }); else alert(msg);
            window.refreshStatus();
            window.loadPrinters();
        } else {
            const msg = T('js.admin_imprimantes_list.erreur', 'Erreur: ') + result.error;
            if (window.showAppModal) window.showAppModal({ message: msg, type: 'danger' }); else alert(msg);
        }
    } catch (error) {
        const msg = T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message;
        if (window.showAppModal) window.showAppModal({ message: msg, type: 'danger' }); else alert(msg);
    }
};

window.loadPrinters = async function () {
    const printersDiv = document.getElementById('printers-list');
    if (!printersDiv) return;
    
    if (!hasElectronAPI) {
        printersDiv.innerHTML = '<p class="text-muted">' + T('js.admin_imprimantes_list.api_electron_non_disponible', 'API Electron non disponible') + '</p>';
        return;
    }
    
    try {
        const status = await window.electronAPI.getPrinterMonitorStatus();
        if (!status.available || status.status !== 'active') {
            printersDiv.innerHTML = '<p class="text-muted">' + T('js.admin_imprimantes_list.moniteur_doit_demarrer', 'Le moniteur doit être démarré pour lister les imprimantes.') + ' <button class="btn btn-sm btn-success" onclick="toggleMonitor(true)">' + T('js.admin_imprimantes_list.demarrer', 'Démarrer') + '</button></p>';
            return;
        }
        
        const result = await window.electronAPI.getPrinters();
        if (result.success && result.printers && result.printers.length > 0) {
            let html = '<table class="table table-striped"><thead><tr><th>' + T('admin_machines.name', 'Nom') + '</th><th>' + T('js.admin_imprimantes_list.statut', 'Statut') + '</th><th>' + T('js.admin_imprimantes_list.par_defaut', 'Par défaut') + '</th><th>' + T('admin_machines.actions', 'Actions') + '</th></tr></thead><tbody>';
            result.printers.forEach(printer => {
                const isDefault = printer.Default ? '<span class="label label-success">' + T('common.yes', 'Oui') + '</span>' : '<span class="label label-default">' + T('common.no', 'Non') + '</span>';
                const statusName = (printer.Status || '').toLowerCase();
                const name = (printer.Name || '').toLowerCase();
                const isError = statusName === 'error' || name.includes('photocopilleuse');
                const statusClass = isError ? 'danger' : statusName === 'ok' ? 'success' : 'warning';
                const deleteBtn = isError ? '<button class="btn btn-xs btn-danger" onclick="deletePrinter(\'' + printer.Name.replace(/'/g, "\\'") + '\')" title="' + T('js.admin_imprimantes_list.supprimer_cette_imprimante', 'Supprimer cette imprimante') + '"><i class="fa fa-trash"></i></button>' : '';
                html += '<tr class="' + (isError ? 'danger' : '') + '">' +
                    '<td>' + (printer.Name || 'N/A') + '</td>' +
                    '<td><span class="label label-' + statusClass + '">' + (printer.Status || 'N/A') + '</span></td>' +
                    '<td>' + isDefault + '</td>' +
                    '<td>' + deleteBtn + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            printersDiv.innerHTML = html;
        } else {
            printersDiv.innerHTML = '<p class="text-muted">' + T('js.admin_imprimantes_list.aucune_imprimante_trouvee', 'Aucune imprimante trouvée ou erreur: ') + (result.error || T('common.unknown', 'Inconnu')) + '</p>';
        }
    } catch (error) {
        printersDiv.innerHTML = '<div class="alert alert-danger">' + T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message + '</div>';
    }
};

window.loadStats = async function () {
    const statsDiv = document.getElementById('stats-container');
    if (!statsDiv) return;
    
    try {
        const response = await fetch('?check_print_jobs');
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            statsDiv.innerHTML = '<div class="alert alert-danger">' + T('js.admin_imprimantes_list.erreur_reponse_json', 'Erreur: La réponse n\'est pas du JSON valide.') + '</div>';
            return;
        }
        
        if (data.success) {
            let html = '<div class="row">';
            html += '<div class="col-md-4"><div class="well text-center"><h3>' + data.total_jobs + '</h3><p>' + T('js.admin_imprimantes_list.total_impressions', 'Total d\'impressions') + '</p></div></div>';
            
            if (data.stats && data.stats.by_printer && data.stats.by_printer.length > 0) {
                html += '<div class="col-md-8"><h4>' + T('js.admin_imprimantes_list.par_imprimante', 'Par imprimante:') + '</h4><ul>';
                data.stats.by_printer.forEach(stat => {
                    html += '<li><strong>' + stat.printer_name + '</strong>: ' + stat.total_jobs + ' jobs, ' + (stat.total_pages || 0) + ' pages</li>';
                });
                html += '</ul></div>';
            }
            html += '</div>';
            statsDiv.innerHTML = html;
        } else {
            statsDiv.innerHTML = '<p class="text-muted">' + (data.message || data.error || T('js.admin_imprimantes_list.aucune_statistique', 'Aucune statistique disponible')) + '</p>';
        }
    } catch (error) {
        statsDiv.innerHTML = '<div class="alert alert-danger">' + T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message + '</div>';
    }
};

window.loadPrintJobs = async function () {
    const jobsDiv = document.getElementById('print-jobs-list');
    if (!jobsDiv) return;
    
    try {
        const response = await fetch('?check_print_jobs');
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            jobsDiv.innerHTML = '<div class="alert alert-danger">' + T('js.admin_imprimantes_list.erreur_reponse_json', 'Erreur: La réponse n\'est pas du JSON valide.') + '</div>';
            return;
        }
        
        if (data.success && data.jobs && data.jobs.length > 0) {
            let html = '<table class="table table-striped table-hover"><thead><tr><th>' + T('admin_tirage.date', 'Date') + '</th><th>' + T('js.admin_imprimantes_list.document', 'Document') + '</th><th>' + T('js.admin_imprimantes_list.imprimante', 'Imprimante') + '</th><th>' + T('js.admin_imprimantes_list.utilisateur', 'Utilisateur') + '</th><th>' + T('js.admin_imprimantes_list.statut', 'Statut') + '</th><th>' + T('js.admin_imprimantes_list.pages', 'Pages') + '</th></tr></thead><tbody>';
            data.jobs.slice(0, 20).forEach(job => {
                const date = new Date(job.timestamp).toLocaleString('fr-FR');
                const pages = (job.pages_printed || 0) + ' / ' + (job.total_pages || 0);
                const statusClass = job.status === 'Completed' ? 'success' : job.status === 'Printing' ? 'info' : 'warning';
                html += '<tr>' +
                    '<td>' + date + '</td>' +
                    '<td>' + (job.document || 'N/A') + '</td>' +
                    '<td>' + (job.printer_name || 'N/A') + '</td>' +
                    '<td>' + (job.owner || 'N/A') + '</td>' +
                    '<td><span class="label label-' + statusClass + '">' + (job.status || 'N/A') + '</span></td>' +
                    '<td>' + pages + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            if (data.jobs.length > 20) {
                html += '<p class="text-muted">' + T('js.admin_imprimantes_list.affichage_20_dernieres', 'Affichage des 20 dernières impressions sur ') + data.total_jobs + ' total.</p>';
            }
            jobsDiv.innerHTML = html;
        } else {
            jobsDiv.innerHTML = '<p class="text-muted">' + (data.message || T('js.admin_imprimantes_list.aucune_impression_enregistree', 'Aucune impression enregistrée pour le moment.')) + '</p>';
        }
    } catch (error) {
        jobsDiv.innerHTML = '<div class="alert alert-danger">' + T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message + '</div>';
    }
};

window.deletePrinter = async function (printerName) {
    const doDelete = async () => {
        if (!hasElectronAPI) {
            const msg = T('js.admin_imprimantes_list.api_electron_non_disponible', 'API Electron non disponible');
            if (window.showAppModal) window.showAppModal(msg); else alert(msg);
            return;
        }
        
        try {
            const result = await window.electronAPI.deletePrinter(printerName);
            if (result.success) {
                const msg = T('js.admin_imprimantes_list.imprimante_supprimee', 'Imprimante supprimée avec succès');
                if (window.showAppModal) window.showAppModal({ message: msg, type: 'success' }); else alert(msg);
                window.loadPrinters();
            } else {
                const msg = T('js.admin_imprimantes_list.erreur_suppression', 'Erreur lors de la suppression: ') + result.error;
                if (window.showAppModal) window.showAppModal({ message: msg, type: 'danger' }); else alert(msg);
            }
        } catch (error) {
            const msg = T('js.admin_imprimantes_list.erreur', 'Erreur: ') + error.message;
            if (window.showAppModal) window.showAppModal({ message: msg, type: 'danger' }); else alert(msg);
        }
    };

    if (window.showAppModal) {
        window.showAppModal({
            title: T('js.admin_imprimantes_list.suppression_imprimante', 'Suppression d\'imprimante'),
            message: T('js.admin_imprimantes_list.confirmer_suppression_imprimante', 'Êtes-vous sûr de vouloir supprimer l\'imprimante "${printerName}" ?\n\nCette action nécessite des droits administrateur.').replace('${printerName}', printerName),
            confirm: true,
            type: 'warning',
            onConfirm: function() { doDelete(); }
        });
    } else if (confirm(T('js.admin_imprimantes_list.confirmer_suppression_imprimante', 'Êtes-vous sûr de vouloir supprimer l\'imprimante "${printerName}" ?').replace('${printerName}', printerName))) {
        doDelete();
    }
};

if (hasElectronAPI) {
    window.electronAPI.onPrintJobDetected((printData) => {
        console.log('Impression détectée:', printData);
        window.loadPrintJobs();
        window.loadStats();
        
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible';
        notification.innerHTML =
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            '<strong>' + T('js.admin_imprimantes_list.nouvelle_impression', 'Nouvelle impression détectée!') + '</strong> ' + printData.document + ' ' + T('common.sur', 'sur') + ' ' + printData.printerName;
        const container = document.querySelector('.container');
        if (container) container.insertBefore(notification, container.firstChild);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    window.refreshStatus();
    window.loadPrinters();
    window.loadStats();
    window.loadPrintJobs();
    
    setInterval(() => {
        window.loadPrintJobs();
        window.loadStats();
    }, 30000);
});
