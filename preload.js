const { contextBridge, ipcRenderer } = require('electron');

// Exposer les fonctions sécurisées au contexte de rendu
contextBridge.exposeInMainWorld('electronAPI', {
    // Fonctions de fichiers
    openFile: (filePath) => ipcRenderer.invoke('open-file', filePath),
    cleanupTmpFiles: () => ipcRenderer.invoke('cleanup-tmp-files'),
    
    // Fonctions de mise à jour
    checkForUpdates: () => ipcRenderer.invoke('check-for-updates'),
    downloadUpdate: () => ipcRenderer.invoke('download-update'),
    installUpdate: () => ipcRenderer.invoke('install-update'),
    getDatabasePath: () => ipcRenderer.invoke('get-database-path'),
    getAppVersion: () => ipcRenderer.invoke('get-app-version'),
    
    // Écouteurs d'événements de mise à jour
    onUpdateAvailable: (callback) => {
        ipcRenderer.on('update-available', (event, info) => callback(info));
    },
    onUpdateNotAvailable: (callback) => {
        ipcRenderer.on('update-not-available', (event, info) => callback(info));
    },
    onDownloadProgress: (callback) => {
        ipcRenderer.on('download-progress', (event, progress) => callback(progress));
    },
    onUpdateDownloaded: (callback) => {
        ipcRenderer.on('update-downloaded', (event, info) => callback(info));
    },
    onUpdateError: (callback) => {
        ipcRenderer.on('update-error', (event, error) => callback(error));
    }
});



