const { app, BrowserWindow, ipcMain, shell, Menu, dialog, screen } = require('electron');
const { autoUpdater } = require('electron-updater');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');
const net = require('net');
const http = require('http');
const { checkWindowsCompatibility, applyCompatibilitySettings } = require('./utils/windows-compatibility');

// Vérifier la compatibilité Windows avant tout
checkWindowsCompatibility();

let mainWindow;
let caddyProcess;
let phpFpmProcess;
let serverPort = 8000; // Port par défaut
const PHP_SERVER_PORT = 8001;
let frontendPort = serverPort;
let tempCaddyfilePath = null; // Chemin du Caddyfile temporaire si créé
const PHP_LOG_CHANNEL = 'php-log';
const PHP_FATAL_CHANNEL = 'php-fatal';
const PHP_STATUS_CHANNEL = 'php-process-status';
let phpFatalNotified = false;
let phpRestartInProgress = false;
let phpRestartAttempts = 0;
let phpRestartTimeout = null;
let phpStopRequested = false;
const MAX_PHP_RESTART_ATTEMPTS = 3;
const PHP_RESTART_DELAY = 3000;
const PHP_FATAL_PATTERNS = [
    /PHP Fatal error/i,
    /Uncaught Exception/i,
    /Call to undefined function/i,
    /Uncaught Error/i
];
const PHP_LOG_MONITOR_PATTERNS = [
    { pattern: /PHP Fatal error/i, severity: 'fatal' },
    { pattern: /Uncaught Exception/i, severity: 'fatal' },
    { pattern: /Exception non captur[ée]e?/i, severity: 'fatal' },
    { pattern: /Call to undefined function/i, severity: 'fatal' },
    { pattern: /Uncaught Error/i, severity: 'fatal' }
];
const PHP_WARNING_PATTERNS = [
    { pattern: /PHP Warning/i, severity: 'warning' },
    { pattern: /Undefined variable/i, severity: 'warning' },
    { pattern: /PHP Notice/i, severity: 'warning' }
];
const PHP_ERROR_LOG_CANDIDATES = ['duplicator_errors.log', 'duplicator_error.log'];
const PHP_ERROR_LOG_DIRECTORY_HINTS = ['dupli', 'duplicator'];
let phpErrorLogWatcher = null;
let phpErrorLogPath = null;
let phpErrorLogLastSize = 0;
let phpErrorLogRetryTimeout = null;

function sendToRenderer(channel, payload) {
    try {
        if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send(channel, payload);
        }
    } catch (error) {
        console.log(`Erreur en envoyant un message au renderer (${channel}): ${error.message}`);
    }
}

function normalizePhpMessage(message) {
    if (!message) {
        return '';
    }
    if (Buffer.isBuffer(message)) {
        return message.toString('utf8');
    }
    return String(message);
}

function handlePhpOutput(source, data) {
    const rawMessage = normalizePhpMessage(data);
    const message = rawMessage.trim();
    
    if (!message) {
        return;
    }
    
    const timestamp = new Date().toISOString();
    const logLine = `[${timestamp}] [PHP ${source}] ${message}`;
    console[source === 'STDERR' ? 'error' : 'log'](`[PHP ${source}]`, message);
    
    sendToRenderer(PHP_LOG_CHANNEL, {
        source,
        message,
        timestamp
    });
    
    if (PHP_FATAL_PATTERNS.some(pattern => pattern.test(message))) {
        handlePhpFatal(message, source);
    }
}

function getPhpTempDir() {
    try {
        return os.tmpdir();
    } catch (error) {
        console.log(`Impossible de déterminer le répertoire temporaire système: ${error.message}`);
        return null;
    }
}

function getCandidateLogDirectories() {
    const directories = new Set();
    
    const safelyAdd = (dir) => {
        if (!dir) {
            return;
        }
        try {
            const normalized = path.resolve(dir);
            directories.add(normalized);
        } catch (error) {
            console.log(`Chemin invalide ignoré (${dir}): ${error.message}`);
        }
    };
    
    safelyAdd(getPhpTempDir());
    
    try {
        safelyAdd(app.getPath('temp'));
    } catch (error) {
        console.log(`Impossible de récupérer app.getPath('temp'): ${error.message}`);
    }
    
    try {
        const userData = app.getPath('userData');
        safelyAdd(userData);
        safelyAdd(path.join(userData, 'temp'));
    } catch (error) {
        console.log(`Impossible de récupérer app.getPath('userData'): ${error.message}`);
    }
    
    try {
        const appData = app.getPath('appData');
        PHP_ERROR_LOG_DIRECTORY_HINTS.forEach((hint) => {
            safelyAdd(path.join(appData, hint));
            safelyAdd(path.join(appData, hint, 'temp'));
        });
    } catch (error) {
        console.log(`Impossible de récupérer app.getPath('appData'): ${error.message}`);
    }
    
    const envCandidates = [
        process.env.APPDATA,
        process.env.LOCALAPPDATA,
        process.env.TEMP,
        process.env.TMP
    ];
    
    envCandidates.forEach((base) => {
        if (!base) {
            return;
        }
        safelyAdd(base);
        PHP_ERROR_LOG_DIRECTORY_HINTS.forEach((hint) => {
            safelyAdd(path.join(base, hint));
            safelyAdd(path.join(base, hint, 'temp'));
        });
    });
    
    return Array.from(directories).filter((dir) => {
        try {
            return fs.existsSync(dir);
        } catch (error) {
            console.log(`Impossible de vérifier ${dir}: ${error.message}`);
            return false;
        }
    });
}

function resolvePhpErrorLogPath() {
    const directories = getCandidateLogDirectories();
    
    for (const dir of directories) {
        for (const name of PHP_ERROR_LOG_CANDIDATES) {
            const candidatePath = path.join(dir, name);
            try {
                if (fs.existsSync(candidatePath)) {
                    return candidatePath;
                }
            } catch (error) {
                console.log(`Erreur lors de la vérification du journal PHP (${candidatePath}): ${error.message}`);
            }
        }
    }
    
    return null;
}

function stopPhpErrorLogWatcher() {
    if (phpErrorLogWatcher) {
        try {
            phpErrorLogWatcher.close();
        } catch (error) {
            console.log(`Erreur lors de l'arrêt du watcher de log PHP: ${error.message}`);
        }
        phpErrorLogWatcher = null;
    }
    
    if (phpErrorLogRetryTimeout) {
        clearTimeout(phpErrorLogRetryTimeout);
        phpErrorLogRetryTimeout = null;
    }
    
    phpErrorLogPath = null;
    phpErrorLogLastSize = 0;
}

function analysePhpLogLine(line) {
    if (!line) {
        return;
    }
    
    const trimmed = line.trim();
    if (!trimmed) {
        return;
    }
    
    const fatalMatch = PHP_LOG_MONITOR_PATTERNS.find(entry => entry.pattern.test(trimmed));
    if (fatalMatch) {
        handlePhpFatal(trimmed, 'LOG');
        return;
    }
    
    const warningMatch = PHP_WARNING_PATTERNS.find(entry => entry.pattern.test(trimmed));
    if (warningMatch) {
        sendToRenderer(PHP_LOG_CHANNEL, {
            source: 'LOG',
            message: trimmed,
            timestamp: new Date().toISOString()
        });
    }
}

function readPhpErrorLogUpdates(initialRead = false) {
    if (!phpErrorLogPath) {
        return;
    }
    
    fs.stat(phpErrorLogPath, (err, stats) => {
        if (err) {
            if (err.code === 'ENOENT' && !initialRead) {
                schedulePhpErrorLogMonitorRestart();
            }
            return;
        }
        
        const currentSize = stats.size;
        let readFrom = phpErrorLogLastSize;
        
        if (currentSize < phpErrorLogLastSize) {
            readFrom = 0;
        }
        
        if (!initialRead && currentSize === readFrom) {
            return;
        }
        
        const stream = fs.createReadStream(phpErrorLogPath, {
            start: initialRead ? Math.max(0, currentSize - 32768) : readFrom,
            end: currentSize,
            encoding: 'utf8'
        });
        
        let buffer = '';
        
        stream.on('data', (chunk) => {
            buffer += chunk;
        });
        
        stream.on('end', () => {
            phpErrorLogLastSize = currentSize;
            if (!buffer) {
                return;
            }
            
            const lines = buffer.split(/\r?\n/).filter(Boolean);
            if (initialRead) {
                return;
            }
            lines.forEach(analysePhpLogLine);
        });
        
        stream.on('error', (streamErr) => {
            console.log(`Erreur de lecture du log PHP: ${streamErr.message}`);
        });
    });
}

function schedulePhpErrorLogMonitorRestart(delay = 5000) {
    if (phpErrorLogRetryTimeout) {
        return;
    }
    
    phpErrorLogRetryTimeout = setTimeout(() => {
        phpErrorLogRetryTimeout = null;
        startPhpErrorLogWatcher();
    }, delay);
}

function startPhpErrorLogWatcher() {
    stopPhpErrorLogWatcher();
    
    const logPath = resolvePhpErrorLogPath();
    if (!logPath) {
        console.log('Journal PHP introuvable pour l’instant, nouvelle tentative bientôt.');
        schedulePhpErrorLogMonitorRestart(2000);
        return;
    }
    
    phpErrorLogPath = logPath;
    
    try {
        const stats = fs.statSync(logPath);
        phpErrorLogLastSize = stats.size;
    } catch (error) {
        phpErrorLogLastSize = 0;
    }
    
    readPhpErrorLogUpdates(true);
    
    try {
        phpErrorLogWatcher = fs.watch(logPath, { persistent: false }, () => {
            readPhpErrorLogUpdates();
        });
        phpErrorLogWatcher.on('error', (error) => {
            console.log(`Erreur du watcher de log PHP: ${error.message}`);
            schedulePhpErrorLogMonitorRestart();
        });
        console.log(`Surveillance du journal PHP: ${logPath}`);
    } catch (error) {
        console.log(`Impossible de surveiller le journal PHP (${logPath}): ${error.message}`);
        schedulePhpErrorLogMonitorRestart();
    }
}

function handlePhpFatal(message, source = 'STDERR') {
    if (phpFatalNotified) {
        return;
    }
    
    phpFatalNotified = true;
    const timestamp = new Date().toISOString();
    console.error(`[PHP FATAL - ${source}]`, message);
    
    sendToRenderer(PHP_FATAL_CHANNEL, {
        message,
        source,
        timestamp
    });
    
    if (mainWindow && !mainWindow.isDestroyed()) {
        dialog.showMessageBox(mainWindow, {
            type: 'error',
            title: 'Erreur critique PHP',
            message: 'Le moteur PHP a rencontré une erreur critique.',
            detail: message,
            defaultId: 0,
            cancelId: 0,
            buttons: ['Retour à l’accueil', 'Ignorer']
        }).then(result => {
            if (result.response === 0) {
                attemptRendererRecovery();
            }
        }).catch(() => {});
    } else {
        dialog.showErrorBox('Erreur critique PHP', message);
    }
    
    schedulePhpRestart('fatal-detected');
}

function attemptRendererRecovery() {
    if (!mainWindow || mainWindow.isDestroyed()) {
        return;
    }
    
    const targetPort = frontendPort || serverPort || PHP_SERVER_PORT;
    const accueilUrl = `http://127.0.0.1:${targetPort}/?accueil`;
    mainWindow.loadURL(accueilUrl).catch(error => {
        console.log(`Échec du rechargement de la page d’accueil: ${error.message}`);
        try {
            mainWindow.reload();
        } catch (reloadError) {
            console.log(`Échec du reload de la fenêtre: ${reloadError.message}`);
        }
    });
}

function handlePhpProcessExit(code, signal) {
    const exitInfo = `Processus PHP terminé (code: ${code !== null ? code : 'null'}, signal: ${signal || 'aucun'})`;
    console.warn(exitInfo);
    
    stopPhpErrorLogWatcher();
    
    sendToRenderer(PHP_STATUS_CHANNEL, {
        status: 'stopped',
        code,
        signal,
        timestamp: new Date().toISOString(),
        expected: phpStopRequested,
        port: frontendPort,
        proxyPort: serverPort,
        phpPort: PHP_SERVER_PORT
    });
    
    const wasExpected = phpStopRequested;
    phpStopRequested = false;
    phpFpmProcess = null;
    
    if (!wasExpected) {
        schedulePhpRestart('unexpected-exit');
    }
}

function schedulePhpRestart(reason) {
    if (phpRestartInProgress) {
        return;
    }
    
    if (phpRestartAttempts >= MAX_PHP_RESTART_ATTEMPTS) {
        sendToRenderer(PHP_STATUS_CHANNEL, {
            status: 'failed',
            reason: 'max-retries',
            timestamp: new Date().toISOString(),
            port: frontendPort,
            proxyPort: serverPort,
            phpPort: PHP_SERVER_PORT
        });
        return;
    }
    
    if (phpRestartTimeout) {
        return;
    }
    
    phpRestartTimeout = setTimeout(() => {
        phpRestartTimeout = null;
        restartPhpProcess(reason).catch(error => {
            console.log(`Erreur lors du redémarrage planifié PHP: ${error.message}`);
        });
    }, PHP_RESTART_DELAY);
}

function stopPhpFpmProcess(signal = 'SIGTERM') {
    if (!phpFpmProcess) {
        return Promise.resolve();
    }
    
    phpStopRequested = true;
    
    return new Promise((resolve) => {
        const processToStop = phpFpmProcess;
        let resolved = false;
        
        const cleanup = () => {
            if (!resolved) {
                resolved = true;
                resolve();
            }
        };
        
        const killTimeout = setTimeout(() => {
            if (processToStop && !processToStop.killed) {
                try {
                    processToStop.kill('SIGKILL');
                } catch (error) {
                    console.log(`Impossible de tuer le processus PHP avec SIGKILL: ${error.message}`);
                }
            }
            cleanup();
        }, 5000);
        
        processToStop.once('close', () => {
            clearTimeout(killTimeout);
            cleanup();
        });
        
        try {
            processToStop.kill(signal);
        } catch (error) {
            clearTimeout(killTimeout);
            console.log(`Erreur lors de l’envoi du signal ${signal} au processus PHP: ${error.message}`);
            cleanup();
        }
    });
}

async function restartPhpProcess(reason = 'manual') {
    if (phpRestartInProgress) {
        return;
    }
    
    if (phpRestartTimeout) {
        clearTimeout(phpRestartTimeout);
        phpRestartTimeout = null;
    }
    
    phpRestartInProgress = true;
    phpRestartAttempts += 1;
    
    const timestamp = new Date().toISOString();
    sendToRenderer(PHP_STATUS_CHANNEL, {
        status: 'restarting',
        reason,
        attempt: phpRestartAttempts,
        timestamp,
        port: frontendPort,
        proxyPort: serverPort,
        phpPort: PHP_SERVER_PORT
    });
    
    try {
        await stopPhpFpmProcess();
        phpFatalNotified = false;
        await startPhpFpm();
        phpRestartAttempts = 0;
        sendToRenderer(PHP_STATUS_CHANNEL, {
            status: 'running',
            reason: 'restart-success',
            timestamp: new Date().toISOString(),
            port: frontendPort,
            proxyPort: serverPort,
            phpPort: PHP_SERVER_PORT
        });
        console.log('Redémarrage du processus PHP réussi.');
    } catch (error) {
        console.log(`Échec du redémarrage du processus PHP: ${error.message}`);
        sendToRenderer(PHP_STATUS_CHANNEL, {
            status: 'error',
            reason: 'restart-failed',
            message: error.message,
            timestamp: new Date().toISOString(),
            port: frontendPort,
            proxyPort: serverPort,
            phpPort: PHP_SERVER_PORT
        });
        
        if (phpRestartAttempts < MAX_PHP_RESTART_ATTEMPTS) {
            schedulePhpRestart('retry-after-failure');
        }
    } finally {
        phpRestartInProgress = false;
    }
}

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
    const isPackaged = app.isPackaged;
    let tmpPath;
    
    if (isAppImage || isPackaged) {
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
    const isPackaged = app.isPackaged;
    const isLinux = process.platform === 'linux';
    const isWindows = process.platform === 'win32';
    
    if (isAppImage || (isLinux && isPackaged)) {
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
    const isPackaged = app.isPackaged;
    const isLinux = process.platform === 'linux';
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || (isLinux && isPackaged) || isMacOS) {
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

// Vérifier si PHP est installé sur le système
function checkPhpInstalled() {
    return new Promise((resolve) => {
        const { exec } = require('child_process');
        exec('php --version', (error, stdout, stderr) => {
            if (error) {
                console.error('PHP n\'est pas installé ou non accessible:', error.message);
                resolve(false);
            } else {
                console.log('PHP détecté:', stdout.split('\n')[0]);
                resolve(true);
            }
        });
    });
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
    } else if (isMacOS) {
        // macOS : chercher PHP dans l'app packagée
        const phpPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php');
        if (fs.existsSync(phpPath)) {
            console.log('PHP trouvé dans l\'app packagée:', phpPath);
            return phpPath;
        } else {
            console.warn('PHP non trouvé dans l\'app packagée, utilisation du PHP système');
            return 'php'; // Fallback système
        }
    } else {
        // Linux : utiliser le PHP système
        return 'php';
    }
}

// Obtenir le chemin de la configuration
function getConfigPath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isPackaged = app.isPackaged;
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || isMacOS || isPackaged) {
        return process.resourcesPath;
    } else if (isWindows) {
        // Windows portable : utiliser resources/
        return process.resourcesPath;
    } else {
        return __dirname;
    }
}

// Trouver un port libre
function findFreePort() {
    return new Promise((resolve, reject) => {
        const server = net.createServer();
        
        server.listen(0, () => {
            const port = server.address().port;
            server.close(() => {
                resolve(port);
            });
        });
        
        server.on('error', (err) => {
            reject(err);
        });
    });
}

function waitForServer(url, timeout = 5000, interval = 250) {
    return new Promise((resolve) => {
        const deadline = Date.now() + timeout;
        
        const attempt = () => {
            let handled = false;
            
            const finalize = (success) => {
                if (handled) {
                    return;
                }
                handled = true;
                
                if (success) {
                    resolve(true);
                } else if (Date.now() >= deadline) {
                    resolve(false);
                } else {
                    setTimeout(attempt, interval);
                }
            };
            
            try {
                const request = http.get(url, { timeout: Math.min(interval, 2000) }, (response) => {
                    response.destroy();
                    finalize(true);
                });
                
                request.on('timeout', () => {
                    request.destroy();
                    finalize(false);
                });
                
                request.on('error', () => {
                    finalize(false);
                });
            } catch (error) {
                finalize(false);
            }
        };
        
        attempt();
    });
}

// Obtenir le chemin du Caddyfile
function getCaddyfilePath() {
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isPackaged = app.isPackaged;
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    if (isAppImage || isMacOS || isPackaged) {
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
    const isPackaged = app.isPackaged;
    const isLinux = process.platform === 'linux';
    
    // Le chemin de l'app dépend si on est en AppImage, Windows, macOS ou développement
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';
    
    let appPath;
    if (isAppImage || isMacOS || (isLinux && isPackaged)) {
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
    
    // Créer le répertoire de sessions s'il n'existe pas (cross-platform)
    const sessionPath = path.join(os.tmpdir(), 'duplicator_sessions');
    console.log('Session Path:', sessionPath);
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }
    
    phpFatalNotified = false;
    
    sendToRenderer(PHP_STATUS_CHANNEL, {
        status: 'starting',
        timestamp: new Date().toISOString(),
        port: frontendPort,
        proxyPort: serverPort,
        phpPort: PHP_SERVER_PORT
    });
    
    // Préparer les arguments PHP selon la plateforme
    let phpArgs;
    
    if (isAppImage || (isLinux && isPackaged)) {
        // AppImage ou Linux packagé (.deb) : utiliser PHP système sans php.ini personnalisé
        // Le PHP système a déjà ses extensions configurées
        console.log('Configuration PHP pour Linux packagé/AppImage (PHP système)');
        phpArgs = [
            '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
            '-t', appPath,
            '-d', 'display_errors=1',
            '-d', 'log_errors=1',
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_execution_time=300',
            '-d', 'memory_limit=256M',
            '-d', `session.save_path=${sessionPath}`
        ];
    } else if (isWindows) {
        // Windows : utiliser le PHP embarqué avec extensions
        const asarExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
        const noAsarExtPath = path.join(process.resourcesPath, 'app', 'php', 'ext');
        const phpIniPath = path.join(appPath, '..', 'php.ini');
        const phpExtPath = fs.existsSync(noAsarExtPath) ? noAsarExtPath : asarExtPath;
        
        console.log('Configuration PHP pour Windows');
        console.log('PHP Ini Path:', phpIniPath);
        console.log('PHP Ini exists:', fs.existsSync(phpIniPath));
        console.log('PHP Ext Path:', phpExtPath);
        console.log('PHP Ext exists:', fs.existsSync(phpExtPath));
        
        phpArgs = [
            '-c', phpIniPath,
            '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
            '-t', appPath,
            '-d', 'display_errors=1',
            '-d', 'log_errors=1',
            '-d', `extension_dir=${phpExtPath}`,
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_execution_time=300',
            '-d', 'memory_limit=256M',
            '-d', `session.save_path=${sessionPath}`
        ];
    } else {
        // macOS ou développement : utiliser php.ini si disponible
        const phpIniPath = path.join(appPath, '..', 'php.ini');
        const phpExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
        
        console.log('Configuration PHP pour macOS/dev');
        
        if (fs.existsSync(phpIniPath)) {
            phpArgs = [
                '-c', phpIniPath,
                '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
                '-t', appPath,
                '-d', 'display_errors=1',
                '-d', 'log_errors=1',
                '-d', `extension_dir=${phpExtPath}`,
                '-d', 'upload_max_filesize=50M',
                '-d', 'post_max_size=50M',
                '-d', 'max_execution_time=300',
                '-d', 'memory_limit=256M',
                '-d', `session.save_path=${sessionPath}`
            ];
        } else {
            phpArgs = [
                '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
                '-t', appPath,
                '-d', 'display_errors=1',
                '-d', 'log_errors=1',
                '-d', 'upload_max_filesize=50M',
                '-d', 'post_max_size=50M',
                '-d', 'max_execution_time=300',
                '-d', 'memory_limit=256M',
                '-d', `session.save_path=${sessionPath}`
            ];
        }
    }
    
    phpFpmProcess = spawn(phpPath, phpArgs, {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: {
            ...process.env,
            DUPLICATOR_DB_PATH: getDatabasePath()
        }
    });
    
    phpFpmProcess.stdout.on('data', (data) => handlePhpOutput('STDOUT', data));
    phpFpmProcess.stderr.on('data', (data) => handlePhpOutput('STDERR', data));
    
    phpFpmProcess.on('close', handlePhpProcessExit);
    
    phpFpmProcess.on('error', (error) => {
        console.error('Erreur serveur PHP:', error.message);
    });
    
    phpFpmProcess.on('spawn', () => {
        console.log(`✅ Processus PHP lancé avec succès (PID: ${phpFpmProcess.pid})`);
        startPhpErrorLogWatcher();
        sendToRenderer(PHP_STATUS_CHANNEL, {
            status: 'running',
            timestamp: new Date().toISOString(),
            pid: phpFpmProcess.pid,
            port: frontendPort,
            proxyPort: serverPort,
            phpPort: PHP_SERVER_PORT
        });
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
    
    // Créer le répertoire de sessions s'il n'existe pas (cross-platform)
    const sessionPath = path.join(os.tmpdir(), 'duplicator_sessions');
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }
    
    phpFatalNotified = false;
    frontendPort = PHP_SERVER_PORT;
    
    sendToRenderer(PHP_STATUS_CHANNEL, {
        status: 'starting',
        context: 'fallback',
        timestamp: new Date().toISOString(),
        port: frontendPort,
        proxyPort: null,
        phpPort: PHP_SERVER_PORT
    });
    
    // Pas de php.ini pour éviter les erreurs d'extensions
    phpFpmProcess = spawn(phpPath, [
        '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
        '-t', appPath,
        '-d', 'display_errors=1',
        '-d', 'upload_max_filesize=50M',
        '-d', 'post_max_size=50M',
        '-d', 'max_execution_time=300',
        '-d', 'memory_limit=256M',
        '-d', `session.save_path=${sessionPath}`
    ], {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: {
            ...process.env,
            DUPLICATOR_DB_PATH: getDatabasePath()
        }
    });
    
    phpFpmProcess.stdout.on('data', (data) => handlePhpOutput('STDOUT', data));
    phpFpmProcess.stderr.on('data', (data) => handlePhpOutput('STDERR', data));
    
    phpFpmProcess.on('close', handlePhpProcessExit);
    
    phpFpmProcess.on('spawn', () => {
        console.log(`✅ Processus PHP (fallback) lancé avec succès (PID: ${phpFpmProcess.pid})`);
        startPhpErrorLogWatcher();
        sendToRenderer(PHP_STATUS_CHANNEL, {
            status: 'running',
            context: 'fallback',
            timestamp: new Date().toISOString(),
            pid: phpFpmProcess.pid,
            port: frontendPort,
            proxyPort: null,
            phpPort: PHP_SERVER_PORT
        });
    });
}

// Démarrer Caddy
async function startCaddy() {
    const caddyPath = getCaddyPath();
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const isPackaged = app.isPackaged;
    const isLinux = process.platform === 'linux';
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
    
    // Sur Windows, trouver un port libre au hasard
    if (isWindows) {
        try {
            serverPort = await findFreePort();
            console.log(`Port libre trouvé sur Windows: ${serverPort}`);
        } catch (error) {
            console.error('Erreur lors de la recherche d\'un port libre, utilisation du port par défaut:', error);
            serverPort = 8000;
        }
    }
    
    // Obtenir le Caddyfile original
    const originalCaddyfile = getCaddyfilePath();
    console.log('Caddyfile original:', originalCaddyfile);
    console.log('Caddyfile existe:', fs.existsSync(originalCaddyfile));
    
    // Lire le contenu du Caddyfile original
    let caddyfileContent = fs.readFileSync(originalCaddyfile, 'utf8');
    
    // Remplacer le port dans le contenu (remplacer :8000 par le port choisi)
    caddyfileContent = caddyfileContent.replace(/:8000/g, `:${serverPort}`);
    
    // Si on Windows, créer un Caddyfile temporaire avec le bon port et les bons chemins
    let caddyfile = originalCaddyfile;
    if (isWindows) {
        // Remplacer le chemin de log /tmp/ par un chemin Windows temporaire
        const tempDir = os.tmpdir();
        const logPath = path.join(tempDir, 'caddy_duplicator.log').replace(/\\/g, '/');
        caddyfileContent = caddyfileContent.replace(/\/tmp\/caddy_duplicator\.log/g, logPath);
        
        tempCaddyfilePath = path.join(tempDir, `caddyfile_${Date.now()}.tmp`);
        fs.writeFileSync(tempCaddyfilePath, caddyfileContent, 'utf8');
        caddyfile = tempCaddyfilePath;
        console.log('Caddyfile temporaire créé:', caddyfile);
    }
    
    // Obtenir le bon appPath pour Caddy
    let appPath;
    if (isAppImage || isMacOS || (isLinux && isPackaged)) {
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
    console.log('Port d\'écoute:', serverPort);
    
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
        // Nettoyer le fichier temporaire si créé
        if (tempCaddyfilePath && fs.existsSync(tempCaddyfilePath)) {
            try {
                fs.unlinkSync(tempCaddyfilePath);
                console.log('Caddyfile temporaire supprimé');
            } catch (error) {
                console.error('Erreur suppression Caddyfile temporaire:', error);
            }
        }
    });
    
    // Attendre que Caddy soit prêt
    return new Promise((resolve) => {
        setTimeout(resolve, 3000);
    });
}

// Arrêter les processus
function stopProcesses() {
    if (phpFpmProcess) {
        stopPhpFpmProcess().catch(error => {
            console.log(`Erreur lors de l'arrêt du processus PHP: ${error.message}`);
        });
    }
    
    stopPhpErrorLogWatcher();
    
    if (caddyProcess) {
        caddyProcess.kill();
        caddyProcess = null;
    }
    
    // Nettoyer le fichier temporaire si créé
    if (tempCaddyfilePath && fs.existsSync(tempCaddyfilePath)) {
        try {
            fs.unlinkSync(tempCaddyfilePath);
            console.log('Caddyfile temporaire supprimé');
        } catch (error) {
            console.error('Erreur suppression Caddyfile temporaire:', error);
        }
        tempCaddyfilePath = null;
    }
}

// Arrêt synchronisé de tous les processus enfants avant redémarrage/maj
async function stopAllChildrenGracefully() {
    try {
        await stopPhpFpmProcess().catch(() => {});
    } catch {}
    try {
        if (caddyProcess && !caddyProcess.killed) {
            caddyProcess.kill('SIGTERM');
            await new Promise((r) => setTimeout(r, 2000));
            if (caddyProcess && !caddyProcess.killed) {
                try { caddyProcess.kill('SIGKILL'); } catch {}
            }
        }
    } catch {}
    try { stopPhpErrorLogWatcher(); } catch {}
}
// Créer le menu personnalisé
function createMenu() {
    const template = [
        {
            label: 'Application',
            submenu: [
                {
                    label: 'À propos',
                    click: () => {
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'À propos de Duplicator',
                            message: 'Duplicator',
                            detail: `Version ${app.getVersion()}\n\nApplication de duplication de documents\n\nGitHub: https://github.com/muarf/dupli-electron-caddy`,
                            buttons: ['OK', 'Ouvrir GitHub'],
                            defaultId: 0,
                            cancelId: 0
                        }).then(result => {
                            if (result.response === 1) {
                                shell.openExternal('https://github.com/muarf/dupli-electron-caddy');
                            }
                        });
                    }
                },
                {
                    label: 'Rechercher des mises à jour',
                    click: () => {
                        autoUpdater.checkForUpdatesAndNotify();
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'Recherche de mises à jour',
                            message: 'Recherche en cours...',
                            detail: 'La recherche de mises à jour peut prendre quelques instants.'
                        });
                    }
                },
                { type: 'separator' },
                {
                    label: 'Quitter',
                    accelerator: process.platform === 'darwin' ? 'Cmd+Q' : 'Ctrl+Q',
                    click: () => {
                        app.quit();
                    }
                }
            ]
        },
        {
            label: 'Affichage',
            submenu: [
                {
                    label: 'Recharger',
                    accelerator: 'F5',
                    click: () => {
                        mainWindow.reload();
                    }
                },
                {
                    label: 'Forcer le rechargement',
                    accelerator: 'Ctrl+F5',
                    click: () => {
                        mainWindow.webContents.reloadIgnoringCache();
                    }
                },
                {
                    label: 'Outils de développement',
                    accelerator: 'F12',
                    click: () => {
                        mainWindow.webContents.openDevTools();
                    }
                }
            ]
        }
    ];

    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
}

function createWindow() {
    // Nettoyer les fichiers temporaires au démarrage
    cleanupTmpFiles();
    
    // Résoudre de manière robuste le chemin de l'icône (Linux a besoin d'une icône explicite)
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    const candidateIconPaths = [
        // Icône décompressée lors du build
        path.join(process.resourcesPath, 'app.asar.unpacked', 'icons', 'icon.png'),
        // Icône à la racine des resources (via extraResources)
        path.join(process.resourcesPath, 'icons', 'icon.png'),
        // Icône dans l'archive asar (fallback)
        path.join(process.resourcesPath, 'app.asar', 'icons', 'icon.png'),
        // Icône en développement
        path.join(__dirname, 'icons', 'icon.png'),
    ];
    const iconPath = candidateIconPaths.find(p => {
        try { return fs.existsSync(p); } catch { return false; }
    }) || candidateIconPaths[0];
    console.log('Chemin icône sélectionné:', iconPath, ' (AppImage:', !!isAppImage, ')');
    
    // Obtenir les dimensions de l'écran principal
    const primaryDisplay = screen.getPrimaryDisplay();
    const { width, height } = primaryDisplay.workAreaSize;
    
    // Créer la fenêtre du navigateur
    mainWindow = new BrowserWindow({
        width: width,
        height: height,
        x: 0,
        y: 0,
        minWidth: 800,
        minHeight: 600,
        icon: iconPath,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js'),
            sandbox: false,
            offscreen: false
        },
        show: false
    });
    
    // Maximiser la fenêtre pour prendre tout l'écran disponible
    mainWindow.maximize();

    // Écouter les mises à jour du titre de la page pour synchroniser le titre de la fenêtre
    mainWindow.webContents.on('page-title-updated', (event, title) => {
        // Mettre à jour le titre de la fenêtre avec le titre de la page
        mainWindow.setTitle(title);
    });

    // Créer le menu personnalisé
    createMenu();
    
    // Démarrer les serveurs
    async function startServers() {
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
        const isLinux = process.platform === 'linux';
        
        // Pour Linux AppImage, vérifier si PHP est installé
        if (isLinux && isAppImage) {
            const phpInstalled = await checkPhpInstalled();
            if (!phpInstalled) {
                console.error('PHP non installé sur le système Linux');
                // Afficher la page d'aide pour installer PHP
                const guidePath = path.join(__dirname, 'php-install-guide.html');
                if (fs.existsSync(guidePath)) {
                    mainWindow.loadFile(guidePath);
                } else {
                    // Si le fichier n'existe pas en dev, chercher dans resources
                    const resourceGuidePath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php-install-guide.html');
                    if (fs.existsSync(resourceGuidePath)) {
                        mainWindow.loadFile(resourceGuidePath);
                    } else {
                        // Créer une page d'erreur simple
                        mainWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <meta charset="UTF-8">
                                <title>PHP non installé</title>
                                <style>
                                    body { font-family: Arial, sans-serif; padding: 40px; text-align: center; background: #f5f5f5; }
                                    h1 { color: #e53e3e; }
                                    p { color: #4a5568; margin: 20px 0; }
                                    code { background: #2d3748; color: #a0ff00; padding: 10px; display: block; margin: 20px auto; max-width: 600px; border-radius: 5px; }
                                </style>
                            </head>
                            <body>
                                <h1>⚠️ PHP n'est pas installé</h1>
                                <p>Duplicator nécessite PHP pour fonctionner.</p>
                                <p>Veuillez installer PHP avec les commandes suivantes :</p>
                                <code>sudo apt update<br>sudo apt install php php-cli php-gd php-sqlite3 php-mbstring php-xml</code>
                                <p>Puis redémarrez l'application.</p>
                            </body>
                            </html>
                        `));
                    }
                }
                mainWindow.show();
                return;
            }
        }
        
        try {
            await startPhpFpm();
            await startCaddy();
            
            const appUrl = `http://127.0.0.1:${serverPort}/`;
            console.log(`Chargement de l'URL principale: ${appUrl}`);
            
            const proxyReady = await waitForServer(appUrl, 5000);
            
            if (!proxyReady) {
                const fallbackUrl = `http://127.0.0.1:${PHP_SERVER_PORT}/`;
                console.warn(`Caddy n'a pas répondu à temps sur ${appUrl}, fallback vers ${fallbackUrl}`);
                frontendPort = PHP_SERVER_PORT;
                sendToRenderer(PHP_STATUS_CHANNEL, {
                    status: 'proxy-timeout',
                    timestamp: new Date().toISOString(),
                    port: frontendPort,
                    proxyPort: serverPort,
                    phpPort: PHP_SERVER_PORT
                });
                mainWindow.loadURL(fallbackUrl);
                mainWindow.show();
            } else {
                frontendPort = serverPort;
                sendToRenderer(PHP_STATUS_CHANNEL, {
                    status: 'proxy-ready',
                    timestamp: new Date().toISOString(),
                    port: frontendPort,
                    proxyPort: serverPort,
                    phpPort: PHP_SERVER_PORT
                });
                mainWindow.loadURL(appUrl);
                mainWindow.show();
                console.log(`Serveurs démarrés avec succès sur le port ${serverPort}`);
            }
        } catch (error) {
            console.error('Erreur lors du démarrage des serveurs:', error);
            // Fallback : utiliser le serveur PHP intégré uniquement
            console.log('Tentative de démarrage avec le serveur PHP intégré uniquement...');
            try {
                startPhpServer();
                frontendPort = PHP_SERVER_PORT;
                mainWindow.loadURL(`http://127.0.0.1:${PHP_SERVER_PORT}/`);
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
    
    // Avant de quitter pour installer, arrêter proprement Caddy/PHP
    autoUpdater.on('before-quit-for-update', async () => {
        console.log('before-quit-for-update: arrêt des processus enfants...');
        await stopAllChildrenGracefully();
    });

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
        
        // Ne pas afficher d'erreur si c'est un problème de réseau (pas d'internet)
        const isNetworkError = err.message && (
            err.message.includes('net::') ||
            err.message.includes('ENOTFOUND') ||
            err.message.includes('ECONNREFUSED') ||
            err.message.includes('ETIMEDOUT') ||
            err.message.includes('Cannot find') ||
            err.message.includes('404')
        );
        
        if (!isNetworkError && mainWindow && mainWindow.webContents) {
            // Afficher l'erreur uniquement si ce n'est pas un problème de réseau
            mainWindow.webContents.send('update-error', err);
        } else {
            console.log('Vérification des mises à jour ignorée (pas de connexion internet ou release non disponible)');
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
            // Erreur silencieuse si pas de connexion
            if (err.message && (err.message.includes('net::') || err.message.includes('ENOTFOUND'))) {
                console.log('Pas de connexion internet, vérification des mises à jour ignorée');
            } else {
                console.error('Erreur vérification mise à jour:', err.message);
            }
        });
    }, 10000);
    
    // Vérifier toutes les 4 heures
    setInterval(() => {
        console.log('Vérification périodique des mises à jour...');
        autoUpdater.checkForUpdates().catch(err => {
            // Erreur silencieuse si pas de connexion
            if (err.message && (err.message.includes('net::') || err.message.includes('ENOTFOUND'))) {
                console.log('Pas de connexion internet, vérification des mises à jour ignorée');
            } else {
                console.error('Erreur vérification mise à jour:', err.message);
            }
        });
    }, 4 * 60 * 60 * 1000);
}

// Désactiver l'accélération GPU pour éviter les erreurs GLX
app.disableHardwareAcceleration();

// Cette méthode sera appelée quand Electron aura fini de s'initialiser
app.whenReady().then(() => {
    // Appliquer les paramètres de compatibilité Windows
    applyCompatibilitySettings();
    
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

// Toujours tenter un arrêt propre avant de quitter (y compris via maj)
app.on('before-quit', async (event) => {
    try {
        await stopAllChildrenGracefully();
    } catch {}
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
        const isPackaged = app.isPackaged;
        let fullPath;
        
        if (isAppImage || isPackaged) {
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

ipcMain.handle('restart-php', async () => {
    await restartPhpProcess('renderer-request');
    return { success: true };
});

ipcMain.handle('restart-app', () => {
    try {
        app.relaunch();
        app.exit(0);
        return { success: true };
    } catch (error) {
        console.error('Erreur lors du redémarrage de l’application:', error);
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
        // Arrêt propre puis installation avec relance forcée
        Promise.resolve()
            .then(() => stopAllChildrenGracefully())
            .then(() => {
                // isSilent=false, isForceRunAfter=true pour forcer la relance de l'app
                autoUpdater.quitAndInstall(false, true);
            });
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
