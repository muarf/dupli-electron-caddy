// ============================================================
// task-manager.js -- Composant Gestionnaire de Tâches Globale
// Extrait de components/global-task-manager.html.php
// ============================================================

/* global window, document, fetch, FormData */

(function () {
    let globalTaskInterval = null;
    let currentTrackingJobId = null;
    let taskManagerMinimized = true;

    window.toggleTaskManager = function () {
        const tm = document.getElementById('global-task-manager');
        const icon = document.getElementById('task-manager-toggle-icon');
        if (!tm || !icon) return;

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
    };

    function startGlobalTaskTracking() {
        if (globalTaskInterval) return;
        
        const tm = document.getElementById('global-task-manager');
        const icon = document.getElementById('task-manager-toggle-icon');
        if (tm) tm.classList.add('minimized');
        if (icon) icon.className = 'fa fa-chevron-up';
        taskManagerMinimized = true;

        globalTaskInterval = setInterval(fetchActiveJobs, 3000);
        fetchActiveJobs();
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
        
        if (!tm || !tmBody || !tmCount) return;

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
                    actionHtml += `<a href="${job.download_url}" class="btn btn-success btn-xs task-download-btn" target="_blank" onclick="setTimeout(window.fetchActiveJobs, 1000)"><i class="fa fa-download"></i> Télécharger</a>`;
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
        
        if (!trackingJobExists && jobs.length > 0) {
            const pendingJob = jobs.find(j => j.status === 'processing' || j.status === 'pending');
            currentTrackingJobId = pendingJob ? pendingJob.job_id : jobs[0].job_id;
        }
        
        if (currentTrackingJobId && !taskManagerMinimized) {
            fetchJobLogs(currentTrackingJobId);
        }
    }

    async function fetchJobLogs(jobId) {
        try {
            const fd = new FormData();
            fd.append('action', 'ocr_status');
            fd.append('job_id', jobId);
            const res = await fetch('index.php?studio_process', { method: 'POST', body: fd });
            const data = await res.json();
            
            const tmLogs = document.getElementById('task-manager-logs');
            if (data.success && data.job && data.job.logs) {
                tmLogs.style.display = 'block';
                tmLogs.innerText = data.job.logs;
                tmLogs.scrollTop = tmLogs.scrollHeight;
            } else if (tmLogs) {
                tmLogs.style.display = 'none';
            }
        } catch (e) {
            // Ignore errors
        }
    }

    window.deleteJob = async function (jobId) {
        try {
            const fd = new FormData();
            fd.append('action', 'delete_job');
            fd.append('job_id', jobId);
            await fetch('index.php?studio_process', { method: 'POST', body: fd });
            
            if (currentTrackingJobId === jobId) {
                currentTrackingJobId = null;
                const tmLogs = document.getElementById('task-manager-logs');
                if (tmLogs) tmLogs.style.display = 'none';
            }
            fetchActiveJobs();
        } catch(e) {
            console.error("Erreur deleteJob", e);
        }
    };

    window.fetchActiveJobs = fetchActiveJobs;

    document.addEventListener('DOMContentLoaded', startGlobalTaskTracking);
})();
