const { app, BrowserWindow, ipcMain, shell } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

// Gestion des erreurs non capturées côté Electron
process.on('uncaughtException', (error) => {
    console.error('Erreur non capturée:', error);
    // Optionnel : redémarrer l'application
    // app.relaunch();
    // app.exit(1);
});

// Gestion des erreurs de promesses non résolues
process.on('unhandledRejection', (reason, promise) => {
    console.error('Promesse rejetée non gérée:', reason);
});

let mainWindow;
let phpServer;

// Nettoyer les fichiers temporaires
function cleanupTmpFiles() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    let tmpPath;
    
    if (isAppImage) {
        tmpPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public', 'tmp');
    } else {
        tmpPath = path.join(__dirname, 'app', 'public', 'tmp');
    }
    
    if (fs.existsSync(tmpPath)) {
        const files = fs.readdirSync(tmpPath);
        files.forEach(file => {
            const filePath = path.join(tmpPath, file);
            try {
                fs.unlinkSync(filePath);
            } catch (error) {
                console.error('Erreur lors de la suppression du fichier temporaire:', error);
            }
        });
    }
}

// Démarrer le serveur PHP
function startPhpServer() {
    // Détecter l'environnement d'exécution
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    let phpPath, appPath, phpIniPath;
    
    if (isAppImage) {
        // AppImage : utiliser le PHP système
        phpPath = '/usr/bin/php';
        appPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
        phpIniPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'php-appimage.ini');
    } else if (isWindows) {
        // Windows portable : utiliser les chemins unpacked
        phpPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php.exe');
        appPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
        phpIniPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'php.ini');
    } else {
        // Développement : utiliser le PHP système
        phpPath = '/usr/bin/php';
        appPath = path.join(__dirname, 'app', 'public');
        phpIniPath = path.join(__dirname, 'app', 'php.ini');
    }
    
    console.log('Démarrage du serveur PHP...');
    console.log('PHP Path:', phpPath);
    console.log('App Path:', appPath);
    console.log('PHP Ini Path:', phpIniPath);
    
    phpServer = spawn(phpPath, [
        '-c', phpIniPath,
        '-S', '127.0.0.1:8000',
        '-t', appPath
    ], {
        stdio: ['pipe', 'pipe', 'pipe']
    });
    
    phpServer.stdout.on('data', (data) => {
        console.log('PHP Server:', data.toString());
    });
    
    phpServer.stderr.on('data', (data) => {
        console.error('PHP Error:', data.toString());
    });
    
    phpServer.on('close', (code) => {
        console.log(`Serveur PHP fermé avec le code ${code}`);
    });
    
    phpServer.on('error', (error) => {
        console.error('Erreur serveur PHP:', error);
    });
}

// Arrêter le serveur PHP
function stopPhpServer() {
    if (phpServer) {
        phpServer.kill();
        phpServer = null;
    }
}

// Créer la fenêtre principale
function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            enableRemoteModule: false
        },
        icon: path.join(__dirname, 'icons', 'icon.png'),
        title: 'Duplicator - Gestion de Comptabilité'
    });

    // Charger l'application web
    mainWindow.loadURL('http://127.0.0.1:8000');

    // Ouvrir les liens externes dans le navigateur par défaut
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        shell.openExternal(url);
        return { action: 'deny' };
    });

    // Gestion des erreurs de chargement
    mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL) => {
        console.error('Erreur de chargement:', errorCode, errorDescription);
        mainWindow.loadURL('data:text/html,<h1>Erreur de connexion</h1><p>Impossible de se connecter au serveur PHP.</p><button onclick="location.reload()">Recharger</button>');
    });

    // Ouvrir les DevTools en mode développement
    if (process.env.NODE_ENV === 'development') {
        mainWindow.webContents.openDevTools();
    }
}

// Gestionnaires IPC
ipcMain.handle('open-file', async (event, filePath) => {
    try {
        await shell.openPath(filePath);
    } catch (error) {
        console.error('Erreur ouverture fichier:', error);
        throw error;
    }
});

// Événements de l'application
app.whenReady().then(() => {
    cleanupTmpFiles();
    startPhpServer();
    
    // Attendre que le serveur PHP soit prêt
    setTimeout(() => {
        createWindow();
    }, 2000);

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createWindow();
        }
    });
});

app.on('window-all-closed', () => {
    stopPhpServer();
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('before-quit', () => {
    stopPhpServer();
    cleanupTmpFiles();
});

// Gestion des erreurs de l'application
app.on('render-process-gone', (event, webContents, details) => {
    console.error('Processus de rendu fermé:', details);
});

app.on('child-process-gone', (event, details) => {
    console.error('Processus enfant fermé:', details);
});