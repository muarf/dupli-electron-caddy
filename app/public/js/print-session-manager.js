/**
 * Print Session Manager Global
 * Gère les sessions d'impression multi-contacts sur toutes les pages
 * Compatible Electron + Standalone PHP
 */

class PrintSessionManager {
    constructor() {
        this.activeSessions = [];
        this.currentSessionId = null;
        this.isElectron = typeof window.electronAPI !== 'undefined';
        this.toastContainer = null;
        this.processedJobIds = new Set(); // Pour éviter les doublons de notifications
        this.lastJobData = new Map(); // Stocker dernières données pour comparaison

        console.log('[PrintSessionManager] Initialized', {
            mode: this.isElectron ? 'Electron' : 'Standalone PHP',
            hasElectronAPI: typeof window.electronAPI !== 'undefined'
        });

        // Créer conteneur de toasts
        this.createToastContainer();

        // Setup listeners selon mode
        if (this.isElectron) {
            this.setupElectronListeners();
        } else {
            console.log('[PrintSessionManager] Mode standalone - Détection auto désactivée');
        }

        // Charger sessions actives
        this.loadActiveSessions();

        // Démarrer le polling de régénération des thumbnails (Electron uniquement)
        if (this.isElectron) {
            this.startThumbnailPolling();
        }
    }

    /**
     * Démarrer le polling pour régénérer les thumbnails manquantes
     */
    startThumbnailPolling() {
        // Polling toutes les 10 secondes
        this.thumbnailPollingInterval = setInterval(() => {
            this.regenerateMissingThumbnails();
        }, 10000);

        // Premier appel après 3 secondes
        setTimeout(() => this.regenerateMissingThumbnails(), 3000);

        console.log('[PrintSessionManager] Thumbnail polling démarré (10s)');
    }

    /**
     * Programmer une réanalyse après délai pour mettre à jour les valeurs analysées
     * [MODIFIÉ] : on passe maintenant toutes les métadonnées du job au lieu du seul jobId.
     * Le main process (printer-monitor.js) a besoin de splPath + format pour relancer l'analyse.
     */
    scheduleReanalysis(jobId) {
        const delayMs = 3000; // 3 secondes après détection

        setTimeout(async () => {
            try {
                const numericJobId = parseInt(jobId, 10);
                console.log('[PrintSessionManager] Réanalyse scheduled pour job:', numericJobId);

                if (!window.electronAPI || !window.electronAPI.reanalyzePrintJob) return;

                // Récupérer les métadonnées stockées lors de la détection
                // (splPath et format sont maintenant dans l'objet jobData)
                const jobData = this.lastJobData.get(jobId) || {};

                const result = await window.electronAPI.reanalyzePrintJob(
                    numericJobId,
                    jobData.Document      || jobData.documentName || '',
                    jobData.Format        || jobData.format        || 'unknown',
                    jobData.SplPath       || jobData.splPath       || '',
                    jobData.color         || 0   // driverColor brut (1=mono, 2=color)
                );

                if (result && result.success) {
                    console.log('[PrintSessionManager] Réanalyse result:', result);

                    const printerName = jobData.PrinterName || jobData.printerName || '';

                    await fetch('?check_print_jobs', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action:        'update_job_analysis',
                            job_id:        numericJobId,
                            printer_name:  printerName,
                            thumbnail_url: result.thumbnailUrl || '',
                            fill_rate:     result.fillRate     || 0,
                            is_grayscale:  result.isGrayscale,
                            total_pages:   result.totalPages   || 0
                        })
                    });

                    console.log('[PrintSessionManager] DB mise à jour pour job:', numericJobId, result.totalPages, 'pages');
                } else {
                    console.warn('[PrintSessionManager] Réanalyse échouée pour job:', numericJobId, result);
                }
            } catch (e) {
                console.error('[PrintSessionManager] Erreur réanalyse scheduled:', e);
            }
        }, delayMs);
    }

    /**
     * Régénérer les thumbnails manquantes via réanalyse IPC
     * [MODIFIÉ] : le polling doit aussi passer les métadonnées.
     */
    async regenerateMissingThumbnails() {
        try {
            const response = await fetch('?check_print_jobs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'regenerate_thumbnails' })
            });

            const result = await response.json();

            if (!result.success || !result.jobs || result.jobs.length === 0) {
                return; // Aucun job à traiter
            }

            console.log(`[PrintSessionManager] ${result.jobs.length} job(s) sans thumbnail détecté(s)`);

            let regenerated = 0;

            for (const job of result.jobs) {
                const jobId = parseInt(job.job_id);
                if (!jobId || jobId <= 0) continue;

                try {
                    if (!window.electronAPI || !window.electronAPI.reanalyzePrintJob) {
                        console.log('[PrintSessionManager] electronAPI.reanalyzePrintJob non disponible');
                        break;
                    }

                    console.log(`[PrintSessionManager] Appel IPC reanalyzePrintJob pour job ${jobId}...`);

                    const analysisResult = await window.electronAPI.reanalyzePrintJob(
                        jobId,
                        job.document_name || '',
                        job.format        || 'unknown',
                        job.spl_path      || '',
                        job.driver_color  || 0
                    );

                    console.log(`[PrintSessionManager] Résultat IPC pour job ${jobId}:`, analysisResult);

                    if (analysisResult && analysisResult.success) {
                        await fetch('?check_print_jobs', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action:        'update_job_analysis',
                                id:            job.id,
                                thumbnail_url: analysisResult.thumbnailUrl,
                                fill_rate:     analysisResult.fillRate,
                                is_grayscale:  analysisResult.isGrayscale,
                                total_pages:   analysisResult.totalPages
                            })
                        });
                        regenerated++;
                        console.log(`[PrintSessionManager] Job ${jobId} réanalysé: fillRate=${analysisResult.fillRate?.toFixed(1)}%`);
                    } else {
                        console.log(`[PrintSessionManager] Réanalyse échouée pour job ${jobId}:`, analysisResult?.error || 'Pas de résultat');
                    }
                } catch (e) {
                    console.warn(`[PrintSessionManager] Erreur réanalyse job ${jobId}:`, e);
                }
            }

            if (regenerated > 0) {
                window.dispatchEvent(new CustomEvent('thumbnails-updated', {
                    detail: { count: regenerated }
                }));
            }
        } catch (error) {
            // Erreur silencieuse
        }
    }

    /**
     * Créer le conteneur pour les notifications toast
     */
    createToastContainer() {
        if (document.getElementById('print-toast-container')) {
            this.toastContainer = document.getElementById('print-toast-container');
            return;
        }

        this.toastContainer = document.createElement('div');
        this.toastContainer.id = 'print-toast-container';
        this.toastContainer.className = 'print-toast-container';
        document.body.appendChild(this.toastContainer);
    }

    /**
     * Setup listeners Electron IPC
     */
    setupElectronListeners() {
        if (!window.electronAPI || !window.electronAPI.onPrintJobDetected) {
            console.warn('[PrintSessionManager] electronAPI.onPrintJobDetected non disponible');
            return;
        }

        window.electronAPI.onPrintJobDetected((jobData) => {
            console.log('[PrintSessionManager] Print job detected:', jobData);
            this.handlePrintJobDetected(jobData);
        });

        console.log('[PrintSessionManager] Electron listeners configurés');
    }

    /**
     * Gérer détection d'impression
     */
    async handlePrintJobDetected(jobData) {
        console.log('[PrintSessionManager] Handling print job', jobData);

        const jobId = jobData.jobId || jobData.id || jobData.JobId;

        if (jobId && this.processedJobIds.has(jobId)) {
            console.log('[PrintSessionManager] Job déjà notifié, ignoré:', jobId);
            return;
        }

        if (jobId) {
            this.processedJobIds.add(jobId);
            setTimeout(() => {
                this.processedJobIds.delete(jobId);
                this.lastJobData.delete(jobId);
            }, 60000);

            this.lastJobData.set(jobId, { ...jobData });
            this.scheduleReanalysis(jobId);
        }

        try {
            await fetch('?print_notification', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    jobId:        jobData.JobId       || jobData.jobId,
                    document:     jobData.Document     || jobData.documentName,
                    printerName:  jobData.PrinterName  || jobData.printerName,
                    status:       jobData.Status       || jobData.status,
                    totalPages:   jobData.TotalPages   || jobData.totalPages,
                    paperSize:    jobData.PaperSize    || jobData.paperSize,
                    duplex:       jobData.IsDuplex     || jobData.duplex,
                    colorMode:    jobData.ColorMode    || (jobData.isGrayscale !== undefined
                                    ? (jobData.isGrayscale ? 'Monochrome' : 'Color')
                                    : 'unknown'),
                    isGrayscale:  jobData.isGrayscale,
                    copies:       jobData.Copies       || jobData.copies    || 1,
                    fillRate:     jobData.FillRate     || jobData.fillRate  || 0,
                    thumbnailUrl: jobData.ThumbnailUrl || jobData.thumbnailUrl || '',
                    timestamp:    jobData.TimeSubmitted || jobData.timestamp || new Date().toISOString(),
                    eventType:    'job_detected',
                    platform:     navigator.platform || 'unknown'
                })
            });
        } catch (e) {
            console.error('[PrintSessionManager] Error sending notification:', e);
        }

        this.showToast('Impression détectée', jobData, true);
    }

    /**
     * Afficher notification toast
     */
    showToast(message, jobData, success = true) {
        const toast = document.createElement('div');
        toast.className = 'print-toast ' + (success ? 'print-toast-success' : 'print-toast-error');

        const icon = success ? '✓' : '✗';
        const details = jobData ? `${jobData.Document || jobData.documentName} - ${jobData.TotalPages || jobData.totalPages} pages` : '';

        toast.innerHTML = `
            <div class="print-toast-icon">${icon}</div>
            <div class="print-toast-body">
                <strong>${message}</strong>
                ${details ? `<p>${details}</p>` : ''}
                ${success ? '<a href="?auto_tirage">Voir dans Auto Tirage →</a>' : ''}
            </div>
            <button class="print-toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        this.toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 8000);
    }

    /**
     * Charger les sessions actives depuis l'API
     */
    async loadActiveSessions() {
        try {
            const response = await fetch('?sessions&action=list');
            const data = await response.json();
            this.activeSessions = data.sessions || [];
            
            const lastSessionId = localStorage.getItem('last_active_session');
            if (lastSessionId) {
                const sessionId = parseInt(lastSessionId);
                if (this.activeSessions.find(s => s.id === sessionId)) {
                    this.currentSessionId = sessionId;
                }
            }

            window.dispatchEvent(new CustomEvent('sessions-loaded', {
                detail: { sessions: this.activeSessions }
            }));
        } catch (error) {
            console.error('[PrintSessionManager] Erreur chargement sessions:', error);
        }
    }

    /**
     * Créer une nouvelle session
     */
    async createSession(contact, sessionName) {
        try {
            const response = await fetch('?sessions&action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contact, session_name: sessionName || '' })
            });
            const data = await response.json();
            if (data.success) {
                this.currentSessionId = data.session_id;
                localStorage.setItem('last_active_session', data.session_id);
                await this.loadActiveSessions();
                return data;
            }
            return null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Changer la session active
     */
    switchToSession(sessionId) {
        this.currentSessionId = sessionId;
        if (sessionId) {
            localStorage.setItem('last_active_session', sessionId);
        } else {
            localStorage.removeItem('last_active_session');
        }
        window.dispatchEvent(new CustomEvent('session-switched', {
            detail: { sessionId }
        }));
    }
}

// Initialiser le manager global au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    window.printSessionManager = new PrintSessionManager();
    console.log('[PrintSessionManager] Manager global initialisé');
});
