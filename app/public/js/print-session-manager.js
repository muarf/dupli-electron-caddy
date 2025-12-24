/**
 * Print Session Manager Global
 * Gère les sessions d'impression multi-contacts sur toutes les pages
 * Compatible Electron + Standalone PHP
 */

class PrintSessionManager {
    constructor() {
        this.activeSessions = [];
        this.activeSessions = [];
        this.currentSessionId = null;
        this.isElectron = typeof window.electronAPI !== 'undefined';
        this.toastContainer = null;
        this.processedJobIds = new Set(); // Pour éviter les doublons de notifications

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

        // NOUVEAU WORKFLOW: Juste notifier, pas d'auto-assignation

        // Anti-spam: Vérifier si déjà notifié récemment
        const jobId = jobData.jobId || jobData.id || jobData.JobId;
        if (jobId && this.processedJobIds.has(jobId)) {
            console.log('[PrintSessionManager] Job déjà notifié, ignoré:', jobId);
            return;
        }
        if (jobId) {
            this.processedJobIds.add(jobId);
            setTimeout(() => this.processedJobIds.delete(jobId), 60000);
        }

        // L'utilisateur va sur auto_tirage pour assigner manuellement
        if (this.showSessionSelectionModal) {
            this.showSessionSelectionModal(jobData);
        }

        this.showToast('Impression détectée', jobData, true);

        /* ANCIEN WORKFLOW DÉSACTIVÉ - L'utilisateur assigne manuellement sur auto_tirage
        // Si session active → assigner automatiquement
        if (this.currentSessionId) {
            console.log('[PrintSessionManager] Session active détectée, auto-assignation');
            await this.assignJobToSession(jobData, this.currentSessionId);
            this.showToast('Impression assignée', jobData, true);
        } else {
            // Sinon → modal de choix de session
            console.log('[PrintSessionManager] Aucune session active, affichage modal');
            this.showSessionSelectionModal(jobData);
        }
        */
    }

    /**
     * Assigner un job à une session
     */
    async assignJobToSession(jobData, sessionId) {
        try {
            // Récupérer session pour avoir le contact
            const session = this.activeSessions.find(s => s.id === sessionId);
            if (!session) {
                console.error('[PrintSessionManager] Session non trouvée:', sessionId);
                return;
            }

            // Appeler save_auto_print avec session_id
            const response = await fetch('?save_auto_print', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    printerName: jobData.PrinterName,
                    pages: jobData.TotalPages,
                    contact: session.contact,
                    session_id: sessionId,
                    document: jobData.Document,
                    copies: jobData.Copies || 1,
                    total_pages: jobData.TotalPages,
                    duplex: jobData.IsDuplex || false,
                    color_mode: jobData.ColorMode || 'mono',
                    paper_size: jobData.PaperSize || 'A4',
                    fill_rate: jobData.FillRate || 0,
                    simulate: false
                })
            });

            const result = await response.json();
            console.log('[PrintSessionManager] Job assigné:', result);

            // Émettre événement pour rafraîchissement auto_tirage
            window.dispatchEvent(new CustomEvent('print-job-registered', {
                detail: { contact: session.contact, sessionId }
            }));

            return result;
        } catch (error) {
            console.error('[PrintSessionManager] Erreur assignation job:', error);
            this.showToast('Erreur lors de l\'assignation', jobData, false);
        }
    }

    /**
     * Afficher modal de sélection de session
     */
    showSessionSelectionModal(jobData) {
        const modal = document.getElementById('session-select-modal');
        if (!modal) {
            console.error('[PrintSessionManager] Modal session-select-modal non trouvé');
            // Fallback: créer session simple
            const contact = prompt('Impression détectée: ' + jobData.Document + '\n\nEntrez votre nom/pseudo:');
            if (contact) {
                this.createSession(contact, null).then(result => {
                    if (result && result.session_id) {
                        this.assignJobToSession(jobData, result.session_id);
                    }
                });
            }
            return;
        }

        // Remplir le modal avec les données du job
        const docSpan = modal.querySelector('#modal-doc');
        if (docSpan) docSpan.textContent = jobData.Document;

        // Remplir la liste des sessions existantes
        const sessionList = modal.querySelector('#session-list');
        if (sessionList) {
            sessionList.innerHTML = '';
            this.activeSessions.forEach(session => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `<strong>${session.contact}</strong> ${session.session_name ? '- ' + session.session_name : ''}`;
                item.onclick = (e) => {
                    e.preventDefault();
                    this.assignJobToSession(jobData, session.id);
                    $(modal).modal('hide');
                };
                sessionList.appendChild(item);
            });
        }

        // Handler pour création nouvelle session
        const submitBtn = modal.querySelector('.btn-primary');
        if (submitBtn) {
            submitBtn.onclick = async () => {
                const contact = modal.querySelector('#new-session-contact').value.trim();
                const sessionName = modal.querySelector('#new-session-name').value.trim();

                if (!contact) {
                    alert('Veuillez entrer un nom de contact');
                    return;
                }

                const result = await this.createSession(contact, sessionName);
                if (result && result.session_id) {
                    await this.assignJobToSession(jobData, result.session_id);
                    $(modal).modal('hide');
                }
            };
        }

        // Afficher le modal
        $(modal).modal('show');
    }

    /**
     * Afficher notification toast
     */
    showToast(message, jobData, success = true) {
        const toast = document.createElement('div');
        toast.className = 'print-toast ' + (success ? 'print-toast-success' : 'print-toast-error');

        const icon = success ? '✓' : '✗';
        const details = jobData ? `${jobData.Document} - ${jobData.TotalPages} pages` : '';

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

        // Animation entrée
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto-dismiss après 8 secondes
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 8000);

        // Notification système si Electron et préférence activée
        if (this.isElectron && this.systemNotificationsEnabled() && jobData) {
            try {
                new Notification('Duplicator - ' + message, {
                    body: details,
                    icon: '/icons/icon.png'
                });
            } catch (e) {
                console.warn('[PrintSessionManager] Notifications système non disponibles:', e);
            }
        }
    }

    /**
     * Vérifier si notifications système activées
     */
    systemNotificationsEnabled() {
        // Pour l'instant, désactivé par défaut
        // TODO: Ajouter préférence utilisateur
        return false;
    }

    /**
     * Charger les sessions actives depuis l'API
     */
    async loadActiveSessions() {
        try {
            const response = await fetch('?sessions&action=list');
            const data = await response.json();

            this.activeSessions = data.sessions || [];
            console.log('[PrintSessionManager] Sessions actives chargées:', this.activeSessions.length);

            // Restaurer dernière session active
            const lastSessionId = localStorage.getItem('last_active_session');
            if (lastSessionId) {
                const sessionId = parseInt(lastSessionId);
                if (this.activeSessions.find(s => s.id === sessionId)) {
                    this.currentSessionId = sessionId;
                    console.log('[PrintSessionManager] Session restaurée:', sessionId);
                } else {
                    localStorage.removeItem('last_active_session');
                }
            }

            // Émettre événement pour mise à jour UI
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

                console.log('[PrintSessionManager] Session créée:', data);
                return data;
            } else {
                console.error('[PrintSessionManager] Erreur création session:', data);
                return null;
            }
        } catch (error) {
            console.error('[PrintSessionManager] Erreur création session:', error);
            return null;
        }
    }

    /**
     * Fermer une session
     */
    async closeSession(sessionId) {
        try {
            const response = await fetch('?sessions&action=close', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            });

            const data = await response.json();

            if (data.success) {
                if (this.currentSessionId === sessionId) {
                    this.currentSessionId = null;
                    localStorage.removeItem('last_active_session');
                }

                await this.loadActiveSessions();
                console.log('[PrintSessionManager] Session fermée:', sessionId);

                // Émettre événement
                window.dispatchEvent(new CustomEvent('session-closed', {
                    detail: { sessionId }
                }));

                return data;
            }
        } catch (error) {
            console.error('[PrintSessionManager] Erreur fermeture session:', error);
        }
    }

    /**
     * Assigner un job à une session (update print_jobs)
     */
    async assignJobToSession(jobData, sessionId) {
        try {
            const jobId = jobData.jobId || jobData.id || jobData.job_id;
            if (!jobId) return;

            console.log(`[PrintSessionManager] Assignation job ${jobId} -> session ${sessionId}`);

            const response = await fetch('?sessions&action=reassign_job', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    job_id: jobId,
                    job_table: 'print_jobs',
                    to_session: sessionId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showToast('Job assigné à la session', jobData, true);
            } else {
                console.error('Erreur assignation:', data);
                this.showToast('Erreur assignation', jobData, false);
            }
        } catch (error) {
            console.error('[PrintSessionManager] Erreur assignation:', error);
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

        console.log('[PrintSessionManager] Session basculée:', sessionId);

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
