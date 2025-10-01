const { app, BrowserWindow, ipcMain, shell } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

let mainWindow;
let caddyProcess;
let phpFpmProcess;

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
            if (fs.statSync(filePath).isFile()) {
                fs.unlinkSync(filePath);
            }
        });
    }
}

// Obtenir le chemin de Caddy selon la plateforme
function getCaddyPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    
    if (isAppImage) {
        // AppImage : utiliser le Caddy inclus
        const caddyPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy');
        console.log('Chemin Caddy AppImage:', caddyPath);
        console.log('Caddy existe:', fs.existsSync(caddyPath));
        return caddyPath;
    } else if (isWindows) {
        // Windows portable : utiliser le Caddy inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy.exe');
    } else {
        // Développement : utiliser le Caddy inclus ou système
        const caddyPath = path.join(__dirname, 'caddy', 'caddy');
        return fs.existsSync(caddyPath) ? caddyPath : 'caddy';
    }
}

// Obtenir le chemin de PHP-FPM selon la plateforme
function getPhpFpmPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    
    if (isAppImage) {
        // AppImage : utiliser le PHP-FPM inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php-fpm');
    } else if (isWindows) {
        // Windows : utiliser le PHP-FPM inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php-fpm.exe');
    } else {
        // Développement : utiliser le PHP-FPM inclus
        const phpFpmPath = path.join(__dirname, 'php', 'php-fpm');
        return fs.existsSync(phpFpmPath) ? phpFpmPath : 'php-fpm';
    }
}

// Obtenir le chemin de PHP selon la plateforme
function getPhpPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    
    if (isAppImage) {
        // AppImage : utiliser le PHP inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php');
    } else if (isWindows) {
        // Windows : utiliser le PHP inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php.exe');
    } else {
        // Développement : utiliser le PHP inclus
        const phpPath = path.join(__dirname, 'php', 'php');
        return fs.existsSync(phpPath) ? phpPath : '/usr/bin/php';
    }
}

// Obtenir le chemin de la configuration
function getConfigPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    
    if (isAppImage) {
        return process.resourcesPath;
    } else {
        return __dirname;
    }
}

// Obtenir le chemin du Caddyfile
function getCaddyfilePath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    
    if (isAppImage) {
        // Dans l'AppImage, le Caddyfile est dans resources/
        return path.join(process.resourcesPath, 'Caddyfile');
    } else {
        return path.join(__dirname, 'Caddyfile');
    }
}

// Démarrer le serveur PHP intégré (plus simple et portable)
function startPhpFpm() {
    const phpPath = getPhpPath();
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    
    // Le chemin de l'app dépend si on est en AppImage ou non
    const appPath = isAppImage 
        ? path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public')
        : path.join(__dirname, 'app', 'public');
    
    console.log('Démarrage du serveur PHP intégré...');
    console.log('PHP Path:', phpPath);
    console.log('App Path:', appPath);
    console.log('App Path existe:', fs.existsSync(appPath));
    
    // Créer le répertoire de sessions s'il n'existe pas
    const sessionPath = '/tmp/duplicator_sessions';
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }
    
    // Pas de fichier php.ini pour éviter les erreurs d'extensions Windows
    phpFpmProcess = spawn(phpPath, [
        '-S', '127.0.0.1:8001',
        '-t', appPath,
        '-d', 'display_errors=1',
        '-d', 'log_errors=1',
        '-d', 'upload_max_filesize=50M',
        '-d', 'post_max_size=50M',
        '-d', 'max_execution_time=300',
        '-d', 'memory_limit=256M',
        '-d', 'session.save_path=/tmp/duplicator_sessions'
    ], {
        stdio: ['pipe', 'pipe', 'pipe']
    });
    
    phpFpmProcess.stdout.on('data', (data) => {
        console.log('PHP Server:', data.toString());
    });
    
    phpFpmProcess.stderr.on('data', (data) => {
        console.error('PHP Error:', data.toString());
    });
    
    phpFpmProcess.on('close', (code) => {
        console.log(`Serveur PHP fermé avec le code ${code}`);
    });
    
    phpFpmProcess.on('error', (error) => {
        console.error('Erreur serveur PHP:', error.message);
    });
    
    // Attendre que le serveur soit prêt
    return new Promise((resolve) => {
        setTimeout(resolve, 2000);
    });
}

// Fallback : démarrer le serveur PHP intégré
function startPhpServer() {
    const phpPath = getPhpPath();
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    
    // Le chemin de l'app dépend si on est en AppImage ou non
    const appPath = isAppImage 
        ? path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public')
        : path.join(__dirname, 'app', 'public');
    
    console.log('Démarrage du serveur PHP intégré (fallback)...');
    console.log('PHP Path:', phpPath);
    console.log('App Path:', appPath);
    console.log('App Path existe:', fs.existsSync(appPath));
    
    // Pas de php.ini pour éviter les erreurs d'extensions
    phpFpmProcess = spawn(phpPath, [
        '-S', '127.0.0.1:8001',
        '-t', appPath,
        '-d', 'display_errors=1',
        '-d', 'upload_max_filesize=50M',
        '-d', 'post_max_size=50M',
        '-d', 'max_execution_time=300',
        '-d', 'memory_limit=256M',
        '-d', 'session.save_path=/tmp/duplicator_sessions'
    ], {
        stdio: ['pipe', 'pipe', 'pipe']
    });
    
    phpFpmProcess.stdout.on('data', (data) => {
        console.log('PHP Server:', data.toString());
    });
    
    phpFpmProcess.stderr.on('data', (data) => {
        console.error('PHP Error:', data.toString());
    });
    
    phpFpmProcess.on('close', (code) => {
        console.log(`Serveur PHP fermé avec le code ${code}`);
    });
}

// Démarrer Caddy
function startCaddy() {
    const caddyPath = getCaddyPath();
    const caddyfile = getCaddyfilePath();
    
    console.log('Démarrage de Caddy...');
    console.log('Caddy Path:', caddyPath);
    console.log('Caddyfile:', caddyfile);
    console.log('Caddyfile existe:', fs.existsSync(caddyfile));
    
    const configPath = getConfigPath();
    
    caddyProcess = spawn(caddyPath, [
        'run',
        '--config', caddyfile,
        '--adapter', 'caddyfile'
    ], {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: {
            ...process.env,
            // Variables d'environnement pour Caddy
            CADDY_ROOT: path.join(configPath, 'app.asar.unpacked', 'app', 'public')
        }
    });
    
    caddyProcess.stdout.on('data', (data) => {
        console.log('Caddy:', data.toString());
    });
    
    caddyProcess.stderr.on('data', (data) => {
        console.error('Caddy Error:', data.toString());
    });
    
    caddyProcess.on('close', (code) => {
        console.log(`Caddy fermé avec le code ${code}`);
    });
    
    // Attendre que Caddy soit prêt
    return new Promise((resolve) => {
        setTimeout(resolve, 3000);
    });
}

// Arrêter les processus
function stopProcesses() {
    if (phpFpmProcess) {
        phpFpmProcess.kill();
        phpFpmProcess = null;
    }
    
    if (caddyProcess) {
        caddyProcess.kill();
        caddyProcess = null;
    }
}

function createWindow() {
    // Nettoyer les fichiers temporaires au démarrage
    cleanupTmpFiles();
    
    // Créer la fenêtre du navigateur
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        minWidth: 800,
        minHeight: 600,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js'),
            sandbox: false,
            offscreen: false
        },
        show: false
    });
    
    // Démarrer les serveurs
    async function startServers() {
        try {
            await startPhpFpm();
            await startCaddy();
            
            // Charger l'application
            mainWindow.loadURL('http://127.0.0.1:8000/');
            mainWindow.show();
            
            console.log('Serveurs démarrés avec succès');
        } catch (error) {
            console.error('Erreur lors du démarrage des serveurs:', error);
            // Fallback : utiliser le serveur PHP intégré uniquement
            console.log('Tentative de démarrage avec le serveur PHP intégré uniquement...');
            try {
                startPhpServer();
                mainWindow.loadURL('http://127.0.0.1:8001/');
                mainWindow.show();
                console.log('Serveur PHP intégré démarré avec succès');
            } catch (fallbackError) {
                console.error('Erreur serveur PHP intégré:', fallbackError);
                // Afficher une page d'erreur
                mainWindow.loadFile('error.html');
                mainWindow.show();
            }
        }
    }
    
    startServers();
    
    // Ouvrir les DevTools en développement
    if (process.env.NODE_ENV === 'development') {
        mainWindow.webContents.openDevTools();
    }
}

// Désactiver l'accélération GPU pour éviter les erreurs GLX
app.disableHardwareAcceleration();

// Cette méthode sera appelée quand Electron aura fini de s'initialiser
app.whenReady().then(createWindow);

// Quitter quand toutes les fenêtres sont fermées
app.on('window-all-closed', () => {
    // Nettoyer les fichiers temporaires à la fermeture
    cleanupTmpFiles();
    
    // Arrêter les processus
    stopProcesses();
    
    // Sur macOS, il est courant pour les applications et leur barre de menu
    // de rester actives jusqu'à ce que l'utilisateur quitte explicitement avec Cmd + Q
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    // Sur macOS, il est courant de recréer une fenêtre dans l'app quand l'icône
    // du dock est cliquée et qu'il n'y a pas d'autres fenêtres d'ouvertes
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});

// Gérer l'ouverture de fichiers (PDF, etc.)
ipcMain.handle('open-file', async (event, filePath) => {
    try {
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
        let fullPath;
        
        if (isAppImage) {
            fullPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public', filePath);
        } else {
            fullPath = path.join(__dirname, 'app', 'public', filePath);
        }
        
        await shell.openPath(fullPath);
        return { success: true };
    } catch (error) {
        console.error('Erreur ouverture fichier:', error);
        return { success: false, error: error.message };
    }
});

// Nettoyer les fichiers temporaires
ipcMain.handle('cleanup-tmp-files', async () => {
    try {
        cleanupTmpFiles();
        return { success: true };
    } catch (error) {
        console.error('Erreur nettoyage:', error);
        return { success: false, error: error.message };
    }
});

// Gérer l'arrêt propre de l'application
process.on('SIGINT', () => {
    console.log('Arrêt de l\'application...');
    stopProcesses();
    app.quit();
});

process.on('SIGTERM', () => {
    console.log('Arrêt de l\'application...');
    stopProcesses();
    app.quit();
});
