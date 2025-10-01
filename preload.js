const { contextBridge, ipcRenderer } = require('electron');

// Exposer les fonctions sécurisées au contexte de rendu
contextBridge.exposeInMainWorld('electronAPI', {
    openFile: (filePath) => ipcRenderer.invoke('open-file', filePath),
    cleanupTmpFiles: () => ipcRenderer.invoke('cleanup-tmp-files')
});



