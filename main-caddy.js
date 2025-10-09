const { app, BrowserWindow, ipcMain, shell } = require('electron');
const { autoUpdater } = require('electron-updater');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

let mainWindow;
let caddyProcess;
let phpFpmProcess;

// Obtenir le chemin de la base de données (dans userData pour la persistance lors des mises à jour)
function getDatabasePath() {
    const userDataPath = app.getPath('userData');
    const dbPath = path.join(userDataPath, 'duplinew.sqlite');
    
    // Si la base de données n'existe pas encore, copier le template depuis l'application
    if (!fs.existsSync(dbPath)) {
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
        const isWindows = process.platform === 'win32';
        
        let templatePath;
        if (isAppImage) {
            templatePath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'duplinew.sqlite');
        } else if (isWindows) {
            const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'duplinew.sqlite');
            const noAsarPath = path.join(process.resourcesPath, 'app', 'app', 'duplinew.sqlite');
            templatePath = fs.existsSync(noAsarPath) ? noAsarPath : asarPath;
        } else {
            templatePath = path.join(__dirname, 'app', 'duplinew.sqlite');
        }
        
        // Copier le template si il existe
        if (fs.existsSync(templatePath)) {
            console.log('Création de la base de données utilisateur depuis:', templatePath);
            fs.copyFileSync(templatePath, dbPath);
        } else {
            console.log('Aucun template de BDD trouvé, nouvelle BDD sera créée par l\'application');
        }
    }
    
    console.log('Chemin de la base de données:', dbPath);
    return dbPath;
}

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
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy.exe');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'caddy', 'caddy.exe');
        
        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/)
        if (fs.existsSync(noAsarPath)) {
            console.log('Chemin Caddy Windows (sans ASAR):', noAsarPath);
            console.log('Caddy Windows existe:', fs.existsSync(noAsarPath));
            return noAsarPath;
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/)
        else if (fs.existsSync(asarPath)) {
            console.log('Chemin Caddy Windows (avec ASAR):', asarPath);
            console.log('Caddy Windows existe:', fs.existsSync(asarPath));
            return asarPath;
        }
        else {
            console.error('Caddy.exe non trouvé ni avec ASAR ni sans ASAR');
            return 'caddy.exe'; // Fallback système
        }
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
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || isMacOS) {
        // AppImage ou macOS : vérifier si php-fpm existe, sinon retourner null
        const phpFpmPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php-fpm');
        return fs.existsSync(phpFpmPath) ? phpFpmPath : null;
    } else if (isWindows) {
        // Windows : utiliser le PHP-FPM inclus
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php-fpm.exe');
    } else {
        // Développement : utiliser le PHP-FPM inclus
        const phpFpmPath = path.join(__dirname, 'php', 'php-fpm');
        return fs.existsSync(phpFpmPath) ? phpFpmPath : null;
    }
}

// Obtenir le chemin de PHP selon la plateforme
function getPhpPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php.exe');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'php', 'php.exe');
        
        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/)
        if (fs.existsSync(noAsarPath)) {
            console.log('PHP trouvé (sans ASAR):', noAsarPath);
            return noAsarPath;
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/)
        else if (fs.existsSync(asarPath)) {
            console.log('PHP trouvé (avec ASAR):', asarPath);
            return asarPath;
        }
        else {
            console.error('PHP.exe non trouvé ni avec ASAR ni sans ASAR');
            return 'php.exe'; // Fallback système
        }
    } else {
        // Linux/macOS : utiliser le PHP système (pas de binaires embarqués pour l'instant)
        return 'php';
    }
}

// Obtenir le chemin de la configuration
function getConfigPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || isMacOS) {
        return process.resourcesPath;
    } else if (isWindows) {
        // Windows portable : utiliser resources/
        return process.resourcesPath;
    } else {
        return __dirname;
    }
}

// Obtenir le chemin du Caddyfile
function getCaddyfilePath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || isMacOS) {
        // Dans l'AppImage ou macOS, le Caddyfile est dans app.asar.unpacked/
        return path.join(process.resourcesPath, 'app.asar.unpacked', 'Caddyfile');
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'Caddyfile');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'Caddyfile');
        
        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/)
        if (fs.existsSync(noAsarPath)) {
            console.log('Caddyfile trouvé (sans ASAR):', noAsarPath);
            return noAsarPath;
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/)
        else if (fs.existsSync(asarPath)) {
            console.log('Caddyfile trouvé (avec ASAR):', asarPath);
            return asarPath;
        }
        else {
            console.error('Caddyfile non trouvé ni avec ASAR ni sans ASAR');
            return path.join(__dirname, 'Caddyfile'); // Fallback développement
        }
    } else {
        return path.join(__dirname, 'Caddyfile');
    }
}

// Démarrer le serveur PHP intégré (plus simple et portable)
function startPhpFpm() {
    const phpPath = getPhpPath();
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    
    // Le chemin de l'app dépend si on est en AppImage, Windows, macOS ou développement
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    let appPath;
    if (isAppImage || isMacOS) {
        appPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app', 'public');
        
        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/app/public)
        if (fs.existsSync(noAsarPath)) {
            appPath = noAsarPath;
            console.log('App Path trouvé (sans ASAR):', appPath);
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/app/public)
        else if (fs.existsSync(asarPath)) {
            appPath = asarPath;
            console.log('App Path trouvé (avec ASAR):', appPath);
        }
        else {
            console.error('App Path non trouvé ni avec ASAR ni sans ASAR');
            appPath = path.join(__dirname, 'app', 'public'); // Fallback développement
        }
    } else {
        appPath = path.join(__dirname, 'app', 'public');
    }
    
    console.log('Démarrage du serveur PHP intégré...');
    console.log('Platform:', process.platform);
    console.log('isAppImage:', isAppImage);
    console.log('isWindows:', isWindows);
    console.log('isMacOS:', isMacOS);
    console.log('process.resourcesPath:', process.resourcesPath);
    console.log('PHP Path:', phpPath);
    console.log('App Path:', appPath);
    console.log('App Path exists:', fs.existsSync(appPath));
    
    // Créer le répertoire de sessions s'il n'existe pas
    const sessionPath = '/tmp/duplicator_sessions';
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }
    
    // Utiliser le bon fichier php.ini selon la plateforme
    let phpIniPath, phpExtPath;
    if (isAppImage) {
        phpIniPath = path.join(appPath, '..', 'php-appimage.ini');  // AppImage Linux
        phpExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non pour les extensions
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
        const noAsarExtPath = path.join(process.resourcesPath, 'app', 'php', 'ext');
        
        phpIniPath = path.join(appPath, '..', 'php.ini');
        phpExtPath = fs.existsSync(noAsarExtPath) ? noAsarExtPath : asarExtPath;
    } else {
        phpIniPath = path.join(appPath, '..', 'php.ini');
        phpExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
    }
    console.log('PHP Ini Path:', phpIniPath);
    console.log('PHP Ini exists:', fs.existsSync(phpIniPath));
    console.log('PHP Ext Path:', phpExtPath);
    console.log('PHP Ext exists:', fs.existsSync(phpExtPath));
    
    phpFpmProcess = spawn(phpPath, [
        '-c', phpIniPath,
        '-S', '127.0.0.1:8001',
        '-t', appPath,
        '-d', 'display_errors=1',
        '-d', 'log_errors=1',
        '-d', `extension_dir=${phpExtPath}`,
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
    
    // Le chemin de l'app dépend si on est en AppImage, Windows ou développement
    const isWindows = process.platform === 'win32';
    const appPath = isAppImage 
        ? path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public')
        : isWindows
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
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    console.log('Démarrage de Caddy...');
    console.log('Platform:', process.platform);
    console.log('isAppImage:', isAppImage);
    console.log('isWindows:', isWindows);
    console.log('isMacOS:', isMacOS);
    console.log('process.resourcesPath:', process.resourcesPath);
    console.log('Caddy Path:', caddyPath);
    console.log('Caddy Path exists:', fs.existsSync(caddyPath));
    console.log('Caddyfile:', caddyfile);
    console.log('Caddyfile existe:', fs.existsSync(caddyfile));
    
    // Obtenir le bon appPath pour Caddy
    let appPath;
    if (isAppImage || isMacOS) {
        appPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app', 'public');
        
        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/app/public)
        if (fs.existsSync(noAsarPath)) {
            appPath = noAsarPath;
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/app/public)
        else if (fs.existsSync(asarPath)) {
            appPath = asarPath;
        }
        else {
            appPath = path.join(__dirname, 'app', 'public'); // Fallback développement
        }
    } else {
        appPath = path.join(__dirname, 'app', 'public');
    }
    
    console.log('Caddy App Path:', appPath);
    console.log('Caddy App Path exists:', fs.existsSync(appPath));
    
    caddyProcess = spawn(caddyPath, [
        'run',
        '--config', caddyfile,
        '--adapter', 'caddyfile'
    ], {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: {
            ...process.env,
            // Variables d'environnement pour Caddy
            CADDY_ROOT: appPath
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

// Configuration de l'auto-updater
function setupAutoUpdater() {
    // Configuration
    autoUpdater.autoDownload = false; // Ne pas télécharger automatiquement (demander d'abord)
    autoUpdater.autoInstallOnAppQuit = true; // Installer automatiquement au redémarrage
    
    // Événements de mise à jour
    autoUpdater.on('checking-for-update', () => {
        console.log('Vérification des mises à jour...');
    });
    
    autoUpdater.on('update-available', (info) => {
        console.log('Mise à jour disponible:', info.version);
        
        // Envoyer une notification à l'interface
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-available', info);
        }
    });
    
    autoUpdater.on('update-not-available', (info) => {
        console.log('Aucune mise à jour disponible');
        
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-not-available', info);
        }
    });
    
    autoUpdater.on('error', (err) => {
        console.error('Erreur lors de la mise à jour:', err);
        
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-error', err);
        }
    });
    
    autoUpdater.on('download-progress', (progressObj) => {
        console.log(`Téléchargement: ${progressObj.percent.toFixed(2)}%`);
        
        // Envoyer la progression à l'interface
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('download-progress', progressObj);
        }
    });
    
    autoUpdater.on('update-downloaded', (info) => {
        console.log('Mise à jour téléchargée, installation au redémarrage');
        
        // Notifier l'utilisateur
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-downloaded', info);
        }
    });
    
    // Vérifier les mises à jour au démarrage (après 10 secondes)
    setTimeout(() => {
        console.log('Lancement de la vérification des mises à jour...');
        autoUpdater.checkForUpdates().catch(err => {
            console.error('Erreur vérification mise à jour:', err);
        });
    }, 10000);
    
    // Vérifier toutes les 4 heures
    setInterval(() => {
        console.log('Vérification périodique des mises à jour...');
        autoUpdater.checkForUpdates().catch(err => {
            console.error('Erreur vérification mise à jour:', err);
        });
    }, 4 * 60 * 60 * 1000);
}

// Désactiver l'accélération GPU pour éviter les erreurs GLX
app.disableHardwareAcceleration();

// Cette méthode sera appelée quand Electron aura fini de s'initialiser
app.whenReady().then(() => {
    createWindow();
    
    // Initialiser la base de données dans userData
    getDatabasePath();
    
    // Configurer l'auto-updater uniquement en production
    if (process.env.NODE_ENV !== 'development') {
        setupAutoUpdater();
    }
});

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

// ============ Handlers pour les mises à jour ============

// Vérifier les mises à jour
ipcMain.handle('check-for-updates', async () => {
    try {
        const result = await autoUpdater.checkForUpdates();
        return { success: true, updateInfo: result ? result.updateInfo : null };
    } catch (error) {
        console.error('Erreur vérification mise à jour:', error);
        return { success: false, error: error.message };
    }
});

// Télécharger une mise à jour
ipcMain.handle('download-update', async () => {
    try {
        await autoUpdater.downloadUpdate();
        return { success: true };
    } catch (error) {
        console.error('Erreur téléchargement mise à jour:', error);
        return { success: false, error: error.message };
    }
});

// Installer la mise à jour (redémarre l'application)
ipcMain.handle('install-update', () => {
    try {
        autoUpdater.quitAndInstall();
        return { success: true };
    } catch (error) {
        console.error('Erreur installation mise à jour:', error);
        return { success: false, error: error.message };
    }
});

// Obtenir le chemin de la base de données
ipcMain.handle('get-database-path', () => {
    try {
        return { success: true, path: getDatabasePath() };
    } catch (error) {
        console.error('Erreur récupération chemin BDD:', error);
        return { success: false, error: error.message };
    }
});

// Obtenir la version actuelle de l'application
ipcMain.handle('get-app-version', () => {
    return { success: true, version: app.getVersion() };
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
