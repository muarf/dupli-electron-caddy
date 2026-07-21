<style>
/* Modale globale flottante pour le suivi des tâches */
#global-task-manager {
    position: fixed;
    bottom: 60px; /* Au-dessus du footer */
    right: 20px;
    width: 350px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    z-index: 1050;
    display: none; /* Masqué par défaut */
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s ease;
}

#global-task-manager.minimized {
    height: 40px;
}

#global-task-manager.minimized .task-manager-body,
#global-task-manager.minimized .task-manager-logs {
    display: none;
}

.task-manager-header {
    background: #f8f9fa;
    padding: 10px 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    color: #333;
}

.task-manager-header i {
    color: #555;
}

.task-manager-body {
    padding: 15px;
    font-size: 13px;
    flex-grow: 1;
}

.task-item {
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.task-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.task-title {
    font-weight: bold;
    margin-bottom: 5px;
    word-break: break-all;
}

.task-status {
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.task-logs-container {
    background: #1e1e1e;
    color: #00ff00;
    font-family: monospace;
    font-size: 11px;
    padding: 10px;
    height: 150px;
    overflow-y: auto;
    border-top: 1px solid #333;
    white-space: pre-wrap;
}

.task-download-btn {
    margin-top: 10px;
}
</style>

<div id="global-task-manager">
    <div class="task-manager-header" onclick="toggleTaskManager()">
        <span><i class="fa fa-tasks"></i> Tâches en arrière-plan (<span id="task-manager-count">0</span>)</span>
        <i id="task-manager-toggle-icon" class="fa fa-chevron-down"></i>
    </div>
    <div class="task-manager-body" id="task-manager-body">
        <!-- Les tâches seront injectées ici par JS -->
    </div>
    <div class="task-logs-container" id="task-manager-logs" style="display: none;">
        <!-- Les logs seront injectés ici -->
    </div>
</div>

<script>
let globalTaskInterval = null;
let currentTrackingJobId = null; // ID du job dont on affiche les logs
let taskManagerMinimized = true; // Minimisé par défaut

function toggleTaskManager() {
    const tm = document.getElementById('global-task-manager');
    const icon = document.getElementById('task-manager-toggle-icon');
    if (taskManagerMinimized) {
        tm.classList.remove('minimized');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        tm.classList.add('minimized');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    }
    taskManagerMinimized = !taskManagerMinimized;
}

function startGlobalTaskTracking() {
    if (globalTaskInterval) return;
    
    // Fermer par défaut au démarrage
    document.getElementById('global-task-manager').classList.add('minimized');
    document.getElementById('task-manager-toggle-icon').className = 'fa fa-chevron-up';
    taskManagerMinimized = true;

    globalTaskInterval = setInterval(fetchActiveJobs, 3000);
    fetchActiveJobs(); // Premier appel immédiat
}

async function fetchActiveJobs() {
    try {
        const fd = new FormData();
        fd.append('action', 'get_active_jobs');
        const res = await fetch('index.php?studio_process', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success && data.jobs) {
            renderTaskManager(data.jobs);
        }
    } catch (e) {
        console.error("Erreur Task Manager:", e);
    }
}

function renderTaskManager(jobs) {
    const tm = document.getElementById('global-task-manager');
    const tmBody = document.getElementById('task-manager-body');
    const tmCount = document.getElementById('task-manager-count');
    const tmLogs = document.getElementById('task-manager-logs');
    
    if (jobs.length === 0) {
        tm.style.display = 'none';
        currentTrackingJobId = null;
        return;
    }
    
    tm.style.display = 'flex';
    tmCount.innerText = jobs.length;
    
    let html = '';
    let trackingJobExists = false;

    jobs.forEach(job => {
        let statusHtml = '';
        let actionHtml = '';
        
        if (job.status === 'processing' || job.status === 'pending') {
            statusHtml = '<i class="fa fa-spinner fa-spin text-primary"></i> Traitement en cours...';
        } else if (job.status === 'done') {
            statusHtml = '<i class="fa fa-check-circle text-success"></i> Terminé';
            if (job.download_url && job.download_url !== 'null' && job.download_url !== 'undefined') {
                actionHtml += `<a href="${job.download_url}" class="btn btn-success btn-xs task-download-btn" target="_blank" onclick="setTimeout(fetchActiveJobs, 1000)"><i class="fa fa-download"></i> Télécharger</a>`;
                try {
                    const fileParam = new URLSearchParams(job.download_url.split('?')[1]).get('file');
                    if (fileParam && fileParam.toLowerCase().endsWith('.pdf')) {
                        actionHtml += ` <a href="?studio&file=${encodeURIComponent(fileParam)}" class="btn btn-primary btn-xs task-download-btn"><i class="fa fa-edit"></i> Ouvrir dans le Studio</a>`;
                    }
                } catch(e) {}
            }
            actionHtml += ` <button type="button" class="btn btn-default btn-xs task-download-btn" onclick="deleteJob('${job.job_id}'); event.stopPropagation();"><i class="fa fa-times"></i> Fermer</button>`;
        } else if (job.status === 'error') {
            statusHtml = '<i class="fa fa-times-circle text-danger"></i> Erreur';
            actionHtml += ` <button type="button" class="btn btn-default btn-xs task-download-btn" onclick="deleteJob('${job.job_id}'); event.stopPropagation();"><i class="fa fa-times"></i> Fermer</button>`;
        }

        let displayName = job.filename || job.originalName || job.job_id || 'Tâche';
        
        html += `
            <div class="task-item">
                <div class="task-title" title="${displayName}">${displayName.length > 30 ? displayName.substring(0,27)+'...' : displayName}</div>
                <div class="task-status">${statusHtml}</div>
                ${actionHtml}
            </div>
        `;
        
        if (currentTrackingJobId === job.job_id) {
            trackingJobExists = true;
        }
    });
    
    tmBody.innerHTML = html;
    
    // Si on ne traque pas de job précis mais qu'il y en a un en cours, on traque le premier
    if (!trackingJobExists && jobs.length > 0) {
        // Traquer le premier job en cours, sinon le premier job
        const pendingJob = jobs.find(j => j.status === 'processing' || j.status === 'pending');
        currentTrackingJobId = pendingJob ? pendingJob.job_id : jobs[0].job_id;
    }
    
    // Mettre à jour les logs du job en cours de traquage
    if (currentTrackingJobId && !taskManagerMinimized) {
        fetchJobLogs(currentTrackingJobId);
    }
}

async function fetchJobLogs(jobId) {
    try {
        const fd = new FormData();
        fd.append('action', 'ocr_status'); // Utilisation de l'endpoint enrichi
        fd.append('job_id', jobId);
        const res = await fetch('index.php?studio_process', { method: 'POST', body: fd });
        const data = await res.json();
        
        const tmLogs = document.getElementById('task-manager-logs');
        if (data.success && data.job && data.job.logs) {
            tmLogs.style.display = 'block';
            tmLogs.innerText = data.job.logs;
            tmLogs.scrollTop = tmLogs.scrollHeight;
        } else {
            tmLogs.style.display = 'none';
        }
    } catch (e) {
        // Ignore errors
    }
}

async function deleteJob(jobId) {
    try {
        const fd = new FormData();
        fd.append('action', 'delete_job');
        fd.append('job_id', jobId);
        await fetch('index.php?studio_process', { method: 'POST', body: fd });
        
        // Si c'était le job qu'on affichait en logs, on ferme les logs
        if (currentTrackingJobId === jobId) {
            currentTrackingJobId = null;
            document.getElementById('task-manager-logs').style.display = 'none';
        }
        fetchActiveJobs();
    } catch(e) {
        console.error("Erreur deleteJob", e);
    }
}

// Initialiser le gestionnaire au chargement
document.addEventListener('DOMContentLoaded', startGlobalTaskTracking);
</script>
