/**
 * Interface utilisateur pour les mises à jour automatiques
 * À inclure dans votre page HTML pour gérer les notifications de mise à jour
 */

// Vérifier si on est dans Electron
const isElectron = typeof window !== 'undefined' && window.electronAPI;

if (isElectron) {
    console.log('Module de mise à jour chargé');

    // Variables pour suivre l'état
    let updateNotification = null;
    let progressBar = null;

    // Créer une notification de mise à jour disponible
    function showUpdateAvailable(info) {
        console.log('Mise à jour disponible:', info.version);
        
        // Créer la notification si elle n'existe pas
        if (!updateNotification) {
            updateNotification = document.createElement('div');
            updateNotification.id = 'update-notification';
            updateNotification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4CAF50;
                color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 10000;
                max-width: 400px;
                font-family: Arial, sans-serif;
            `;
            document.body.appendChild(updateNotification);
        }

        updateNotification.innerHTML = `
            <h3 style="margin-top: 0;">📦 Mise à jour disponible</h3>
            <p>Une nouvelle version (${info.version}) est disponible.</p>
            <p style="font-size: 0.9em; opacity: 0.9;">Votre base de données sera préservée lors de la mise à jour.</p>
            <div style="margin-top: 15px;">
                <button id="download-update-btn" style="
                    background: white;
                    color: #4CAF50;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-weight: bold;
                    margin-right: 10px;
                ">Télécharger</button>
                <button id="dismiss-update-btn" style="
                    background: transparent;
                    color: white;
                    border: 1px solid white;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                ">Plus tard</button>
            </div>
        `;

        // Bouton télécharger
        document.getElementById('download-update-btn').addEventListener('click', async () => {
            console.log('Téléchargement de la mise à jour...');
            updateNotification.innerHTML = `
                <h3 style="margin-top: 0;">⏳ Téléchargement en cours...</h3>
                <div id="progress-container" style="
                    background: rgba(255,255,255,0.3);
                    border-radius: 4px;
                    overflow: hidden;
                    height: 20px;
                    margin-top: 10px;
                ">
                    <div id="progress-bar" style="
                        background: white;
                        height: 100%;
                        width: 0%;
                        transition: width 0.3s;
                    "></div>
                </div>
                <p id="progress-text" style="margin-top: 10px; font-size: 0.9em;">0%</p>
            `;
            progressBar = document.getElementById('progress-bar');
            
            // Demander le téléchargement
            const result = await window.electronAPI.downloadUpdate();
            if (!result.success) {
                alert('Erreur lors du téléchargement: ' + result.error);
                hideUpdateNotification();
            }
        });

        // Bouton fermer
        document.getElementById('dismiss-update-btn').addEventListener('click', () => {
            hideUpdateNotification();
        });
    }

    // Afficher la progression du téléchargement
    function showDownloadProgress(progress) {
        if (progressBar) {
            const percent = Math.round(progress.percent);
            progressBar.style.width = percent + '%';
            document.getElementById('progress-text').textContent = 
                `${percent}% - ${formatBytes(progress.transferred)} / ${formatBytes(progress.total)}`;
        }
    }

    // Afficher que la mise à jour est téléchargée
    function showUpdateDownloaded(info) {
        console.log('Mise à jour téléchargée');
        
        if (updateNotification) {
            updateNotification.style.background = '#2196F3';
            updateNotification.innerHTML = `
                <h3 style="margin-top: 0;">✅ Mise à jour prête</h3>
                <p>La version ${info.version} a été téléchargée avec succès.</p>
                <p style="font-size: 0.9em; opacity: 0.9;">La mise à jour sera installée au prochain redémarrage.</p>
                <div style="margin-top: 15px;">
                    <button id="restart-now-btn" style="
                        background: white;
                        color: #2196F3;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: bold;
                        margin-right: 10px;
                    ">Redémarrer maintenant</button>
                    <button id="restart-later-btn" style="
                        background: transparent;
                        color: white;
                        border: 1px solid white;
                        padding: 10px 20px;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Plus tard</button>
                </div>
            `;

            // Bouton redémarrer
            document.getElementById('restart-now-btn').addEventListener('click', async () => {
                await window.electronAPI.installUpdate();
            });

            // Bouton plus tard
            document.getElementById('restart-later-btn').addEventListener('click', () => {
                hideUpdateNotification();
            });
        }
    }

    // Cacher la notification
    function hideUpdateNotification() {
        if (updateNotification) {
            updateNotification.remove();
            updateNotification = null;
            progressBar = null;
        }
    }

    // Formater les octets
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Écouter les événements de mise à jour depuis le processus principal
    if (window.electronAPI) {
        // Mise à jour disponible
        window.electronAPI.onUpdateAvailable((info) => {
            showUpdateAvailable(info);
        });

        // Aucune mise à jour disponible
        window.electronAPI.onUpdateNotAvailable((info) => {
            console.log('Aucune mise à jour disponible');
        });

        // Progression du téléchargement
        window.electronAPI.onDownloadProgress((progress) => {
            showDownloadProgress(progress);
        });

        // Mise à jour téléchargée
        window.electronAPI.onUpdateDownloaded((info) => {
            showUpdateDownloaded(info);
        });

        // Erreur de mise à jour
        window.electronAPI.onUpdateError((error) => {
            console.error('Erreur de mise à jour:', error);
            alert('Erreur lors de la mise à jour: ' + error.message);
            hideUpdateNotification();
        });

        // Récupérer et afficher la version actuelle
        window.electronAPI.getAppVersion().then(result => {
            if (result.success) {
                console.log('Version actuelle:', result.version);
            }
        });
    }

    // Ajouter un bouton "Vérifier les mises à jour" dans le menu (optionnel)
    document.addEventListener('DOMContentLoaded', () => {
        // Vous pouvez ajouter un bouton dans votre interface si vous le souhaitez
        // Exemple:
        // const checkUpdateBtn = document.getElementById('check-updates-btn');
        // if (checkUpdateBtn) {
        //     checkUpdateBtn.addEventListener('click', async () => {
        //         const result = await window.electronAPI.checkForUpdates();
        //         if (result.success && !result.updateInfo) {
        //             alert('Vous avez déjà la dernière version !');
        //         }
        //     });
        // }
    });
}

