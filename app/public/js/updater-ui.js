/**
 * Interface utilisateur pour les mises à jour automatiques (Electron)
 * 
 * Ce script gère l'affichage des notifications de mise à jour
 * uniquement si l'application tourne dans Electron.
 * Si l'application tourne en mode PHP standalone, ce script ne fait rien.
 */

(function() {
    'use strict';
    
    // Vérifier si on est dans Electron
    const isElectron = typeof window !== 'undefined' && window.electronAPI;
    
    if (!isElectron) {
        console.log('[Updater] Mode PHP standalone détecté - Auto-update désactivé');
        return; // Sortir immédiatement si pas dans Electron
    }
    
    console.log('[Updater] Mode Electron détecté - Initialisation de l\'auto-update');
    
    // État de la mise à jour
    let updateInfo = null;
    let isDownloading = false;
    
    /**
     * Créer le conteneur de notification
     */
    function createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'electron-update-notification';
        container.style.cssText = `
            position: fixed;
            top: 60px;
            right: 20px;
            width: 400px;
            max-width: calc(100vw - 40px);
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 99999;
            display: none;
            animation: slideInRight 0.3s ease-out;
        `;
        document.body.appendChild(container);
        return container;
    }
    
    /**
     * Afficher une notification de mise à jour disponible
     */
    function showUpdateAvailable(info) {
        updateInfo = info;
        const container = document.getElementById('electron-update-notification') || createNotificationContainer();
        
        container.innerHTML = `
            <div style="padding: 20px; border-left: 5px solid #007bff;">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <i class="fa fa-download" style="font-size: 24px; color: #007bff; margin-right: 12px;"></i>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: bold;">
                            Mise à jour disponible
                        </h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                            Version ${info.version}
                        </p>
                    </div>
                    <button onclick="window.updaterUI.closeNotification()" 
                            style="background: none; border: none; color: #999; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; line-height: 1;">
                        &times;
                    </button>
                </div>
                <p style="margin: 0 0 15px 0; color: #555; font-size: 14px;">
                    Une nouvelle version de Duplicator est disponible.
                </p>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.updaterUI.downloadUpdate()" 
                            class="btn btn-primary btn-sm" 
                            style="flex: 1; border-radius: 4px;">
                        <i class="fa fa-download"></i> Télécharger
                    </button>
                    <button onclick="window.updaterUI.closeNotification()" 
                            class="btn btn-default btn-sm" 
                            style="border-radius: 4px;">
                        Plus tard
                    </button>
                </div>
            </div>
        `;
        
        container.style.display = 'block';
    }
    
    /**
     * Afficher la progression du téléchargement
     */
    function showDownloadProgress(progress) {
        const container = document.getElementById('electron-update-notification') || createNotificationContainer();
        const percent = Math.round(progress.percent);
        
        container.innerHTML = `
            <div style="padding: 20px; border-left: 5px solid #28a745;">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <i class="fa fa-spinner fa-spin" style="font-size: 24px; color: #28a745; margin-right: 12px;"></i>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: bold;">
                            Téléchargement en cours
                        </h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                            ${percent}% - ${formatBytes(progress.transferred)} / ${formatBytes(progress.total)}
                        </p>
                    </div>
                </div>
                <div style="width: 100%; background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden; margin-top: 10px;">
                    <div style="width: ${percent}%; background: linear-gradient(90deg, #28a745, #20c997); height: 100%; transition: width 0.3s ease;"></div>
                </div>
            </div>
        `;
        
        container.style.display = 'block';
    }
    
    /**
     * Afficher la notification de téléchargement terminé
     */
    function showUpdateDownloaded(info) {
        const container = document.getElementById('electron-update-notification') || createNotificationContainer();
        
        container.innerHTML = `
            <div style="padding: 20px; border-left: 5px solid #28a745;">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <i class="fa fa-check-circle" style="font-size: 24px; color: #28a745; margin-right: 12px;"></i>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: bold;">
                            Mise à jour prête
                        </h4>
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">
                            Version ${info.version} téléchargée
                        </p>
                    </div>
                    <button onclick="window.updaterUI.closeNotification()" 
                            style="background: none; border: none; color: #999; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; line-height: 1;">
                        &times;
                    </button>
                </div>
                <p style="margin: 0 0 15px 0; color: #555; font-size: 14px;">
                    La mise à jour sera installée au redémarrage de l'application.
                </p>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.updaterUI.installUpdate()" 
                            class="btn btn-success btn-sm" 
                            style="flex: 1; border-radius: 4px;">
                        <i class="fa fa-refresh"></i> Redémarrer maintenant
                    </button>
                    <button onclick="window.updaterUI.closeNotification()" 
                            class="btn btn-default btn-sm" 
                            style="border-radius: 4px;">
                        Plus tard
                    </button>
                </div>
            </div>
        `;
        
        container.style.display = 'block';
    }
    
    /**
     * Afficher une erreur
     */
    function showError(error) {
        const container = document.getElementById('electron-update-notification') || createNotificationContainer();
        
        container.innerHTML = `
            <div style="padding: 20px; border-left: 5px solid #dc3545;">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <i class="fa fa-exclamation-triangle" style="font-size: 24px; color: #dc3545; margin-right: 12px;"></i>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: #333; font-size: 16px; font-weight: bold;">
                            Erreur de mise à jour
                        </h4>
                    </div>
                    <button onclick="window.updaterUI.closeNotification()" 
                            style="background: none; border: none; color: #999; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; line-height: 1;">
                        &times;
                    </button>
                </div>
                <p style="margin: 0 0 15px 0; color: #555; font-size: 14px;">
                    ${error.message || 'Une erreur est survenue lors de la vérification des mises à jour.'}
                </p>
                <button onclick="window.updaterUI.closeNotification()" 
                        class="btn btn-default btn-sm" 
                        style="border-radius: 4px;">
                    Fermer
                </button>
            </div>
        `;
        
        container.style.display = 'block';
        
        // Auto-fermer après 10 secondes
        setTimeout(() => {
            closeNotification();
        }, 10000);
    }
    
    /**
     * Fermer la notification
     */
    function closeNotification() {
        const container = document.getElementById('electron-update-notification');
        if (container) {
            container.style.display = 'none';
        }
    }
    
    /**
     * Télécharger la mise à jour
     */
    async function downloadUpdate() {
        if (isDownloading) return;
        
        isDownloading = true;
        try {
            await window.electronAPI.downloadUpdate();
        } catch (error) {
            console.error('[Updater] Erreur téléchargement:', error);
            showError(error);
            isDownloading = false;
        }
    }
    
    /**
     * Installer la mise à jour (redémarre l'app)
     */
    async function installUpdate() {
        try {
            await window.electronAPI.installUpdate();
        } catch (error) {
            console.error('[Updater] Erreur installation:', error);
            showError(error);
        }
    }
    
    /**
     * Formater les octets en unité lisible
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    /**
     * Ajouter les animations CSS
     */
    function injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            #electron-update-notification {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            
            #electron-update-notification button:hover {
                opacity: 0.9;
            }
            
            #electron-update-notification button:active {
                transform: scale(0.98);
            }
        `;
        document.head.appendChild(style);
    }
    
    /**
     * Initialisation
     */
    function init() {
        console.log('[Updater] Initialisation de l\'interface utilisateur...');
        
        // Injecter les styles
        injectStyles();
        
        // Écouter les événements de mise à jour
        window.electronAPI.onUpdateAvailable((info) => {
            console.log('[Updater] Mise à jour disponible:', info.version);
            showUpdateAvailable(info);
        });
        
        window.electronAPI.onUpdateNotAvailable((info) => {
            console.log('[Updater] Aucune mise à jour disponible');
        });
        
        window.electronAPI.onDownloadProgress((progress) => {
            console.log('[Updater] Progression:', progress.percent.toFixed(2) + '%');
            showDownloadProgress(progress);
        });
        
        window.electronAPI.onUpdateDownloaded((info) => {
            console.log('[Updater] Mise à jour téléchargée:', info.version);
            showUpdateDownloaded(info);
            isDownloading = false;
        });
        
        window.electronAPI.onUpdateError((error) => {
            console.error('[Updater] Erreur:', error);
            showError(error);
            isDownloading = false;
        });
        
        // Exposer les fonctions globalement
        window.updaterUI = {
            closeNotification,
            downloadUpdate,
            installUpdate,
            checkForUpdates: async () => {
                try {
                    const result = await window.electronAPI.checkForUpdates();
                    console.log('[Updater] Vérification manuelle:', result);
                } catch (error) {
                    console.error('[Updater] Erreur vérification:', error);
                    showError(error);
                }
            }
        };
        
        console.log('[Updater] Interface utilisateur initialisée avec succès');
    }
    
    // Initialiser quand le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();

