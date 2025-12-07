const { contextBridge, ipcRenderer } = require('electron');

// Exposer les fonctions sécurisées au contexte de rendu
contextBridge.exposeInMainWorld('electronAPI', {
    // Fonctions de fichiers
    openFile: (filePath) => ipcRenderer.invoke('open-file', filePath),
    cleanupTmpFiles: () => ipcRenderer.invoke('cleanup-tmp-files'),
    showOpenDialog: (options) => ipcRenderer.invoke('show-open-dialog', options),

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
    },

    // Supervision du backend PHP
    onPhpLog: (callback) => {
        ipcRenderer.on('php-log', (event, payload) => callback(payload));
    },
    onPhpFatal: (callback) => {
        ipcRenderer.on('php-fatal', (event, payload) => callback(payload));
    },
    onPhpStatus: (callback) => {
        ipcRenderer.on('php-process-status', (event, payload) => callback(payload));
    },
    restartPhp: () => ipcRenderer.invoke('restart-php'),
    restartApp: () => ipcRenderer.invoke('restart-app'),

    // Moniteur d'imprimantes Windows
    getPrinters: () => ipcRenderer.invoke('get-printers'),
    togglePrinterMonitor: (start) => ipcRenderer.invoke('toggle-printer-monitor', start),
    getPrinterMonitorStatus: () => ipcRenderer.invoke('get-printer-monitor-status'),
    deletePrinter: (printerName) => ipcRenderer.invoke('delete-printer', printerName),
    onPrintJobDetected: (callback) => {
        ipcRenderer.on('print-job-detected', (event, payload) => callback(payload));
    },
    onPrintMonitorError: (callback) => {
        ipcRenderer.on('print-monitor-error', (event, payload) => callback(payload));
    },
    onPrintMonitorStarted: (callback) => {
        ipcRenderer.on('print-monitor-started', (event, payload) => callback(payload));
    },

    // Module d'impression (cross-platform)
    getPrinterCapabilities: (printerName) => ipcRenderer.invoke('get-printer-capabilities', printerName),
    printJob: (pdfPath, options) => ipcRenderer.invoke('print-job', pdfPath, options),

    // Impression de fichiers
    printFile: (fileUrl) => ipcRenderer.invoke('print-file', fileUrl),

    // Ouvrir un fichier avec l'application système
    openExternalFile: (fileUrl) => ipcRenderer.invoke('open-external-file', fileUrl)
});



