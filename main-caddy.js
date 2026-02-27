const { app, BrowserWindow, ipcMain, shell, Menu, dialog, screen } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');
const net = require('net');
const http = require('http');
const { checkWindowsCompatibility, applyCompatibilitySettings } = require('./utils/windows-compatibility');
const PrinterMonitor = require('./utils/printer-monitor');
const printEngine = require('./src/print-engine');
const { isRunningAsAdmin, restartAsAdmin } = require('./utils/admin-checker');

let autoUpdater;

// --- SECURE PURGE SCHEDULER ---
function scheduleSecurePurge() {
    console.log('[SECURE PURGE] Initializing scheduler...');

    // Fonction pour appeler l'API PHP
    const triggerPurge = () => {
        // On attend que les ports soient définis
        const port = PHP_SERVER_PORT || 8001;

        const options = {
            hostname: '127.0.0.1',
            port: port,
            path: '/?secure_purge',
            method: 'GET',
            timeout: 30000 // 30s timeout pour laisser le temps de shredder
        };

        const req = http.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    console.log('[SECURE PURGE] Result:', data);
                } catch (e) {
                    console.error('[SECURE PURGE] Error parsing result:', e.message);
                }
            });
        });

        req.on('error', (err) => {
            console.error('[SECURE PURGE] Request failed:', err.message);
        });

        req.end();
    };

    // 1. Exécuter au démarrage (après un court délai pour être sûr que PHP est prêt)
    setTimeout(() => {
        console.log('[SECURE PURGE] Running startup purge...');
        triggerPurge();
    }, 10000); // 10 secondes après le lancement

    // 2. Planifier toutes les heures (3600000 ms)
    setInterval(() => {
        console.log('[SECURE PURGE] Running scheduled hourly purge...');
        triggerPurge();
    }, 3600000);
}


// Vérifier si Ghostscript fonctionne (sous Windows uniquement)
function checkGhostscript(port = 8000) {
    return new Promise((resolve, reject) => {
        // Seulement sous Windows
        if (process.platform !== 'win32') {
            resolve();
            return;
        }

        const options = {
            hostname: '127.0.0.1',
            port: port,
            path: '/?check_ghostscript',
            method: 'GET',
            timeout: 5000
        };

        const req = http.request(options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const result = JSON.parse(data);
                    if (result.available) {
                        resolve();
                    } else {
                        const error = new Error(result.error || 'Ghostscript non disponible');
                        error.errorCode = result.error_code;
                        error.returnCode = result.return_code;
                        reject(error);
                    }
                } catch (e) {
                    reject(new Error(`Erreur parsing réponse Ghostscript: ${e.message}`));
                }
            });
        });

        req.on('error', (err) => {
            reject(new Error(`Erreur requête Ghostscript: ${err.message}`));
        });

        req.on('timeout', () => {
            req.destroy();
            reject(new Error('Timeout lors de la vérification Ghostscript.'));
        });

        req.end();
    });
}

// Vérifier la compatibilité Windows avant tout
checkWindowsCompatibility();

let mainWindow;
let caddyProcess;
let phpFpmProcess;
let printerMonitor = null; // Moniteur d'imprimantes Windows
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

    // Protéger contre les erreurs EPIPE lors de l'écriture dans console
    try {
        console[source === 'STDERR' ? 'error' : 'log'](`[PHP ${source}]`, message);
    } catch (error) {
        // Ignorer uniquement les erreurs EPIPE (flux fermé - normal lors de la fermeture)
        // Les autres erreurs doivent être affichées
        if (error.code && error.code !== 'EPIPE') {
            try {
                process.stderr.write(`[PHP ${source}] Erreur log: ${error.message}\n`);
            } catch (e) {
                // Si même ça échoue, ignorer silencieusement
            }
        }
        // Pour EPIPE, on ignore silencieusement (flux fermé normalement)
    }

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

    // Protéger contre les erreurs EPIPE lors de l'écriture dans console
    try {
        console.error(`[PHP FATAL - ${source}]`, message);
    } catch (error) {
        // Ignorer uniquement les erreurs EPIPE (flux fermé - normal lors de la fermeture)
        // Les autres erreurs doivent être affichées
        if (error.code && error.code !== 'EPIPE') {
            try {
                process.stderr.write(`[PHP FATAL - ${source}] ${message}\n`);
            } catch (e) {
                // Si même ça échoue, ignorer silencieusement
            }
        }
        // Pour EPIPE, on ignore silencieusement (flux fermé normalement)
    }
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
        }).catch(() => { });
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

    // Protéger contre les erreurs EPIPE lors de l'écriture dans console
    try {
        console.warn(exitInfo);
    } catch (error) {
        // Ignorer uniquement les erreurs EPIPE (flux fermé - normal lors de la fermeture)
        if (error.code && error.code !== 'EPIPE') {
            try {
                process.stderr.write(exitInfo + '\n');
            } catch (e) {
                // Si même ça échoue, ignorer silencieusement
            }
        }
    }

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
        // Pour AppImage, essayer d'abord app/app/public (structure réelle sans ASAR)
        const noAsarTmpPath = path.join(process.resourcesPath, 'app', 'app', 'public', 'tmp');
        const asarTmpPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public', 'tmp');
        tmpPath = fs.existsSync(noAsarTmpPath) ? noAsarTmpPath : asarTmpPath;
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
        // Avec ASAR désactivé, les fichiers sont dans resources/app/ (comme Windows)
        // Essayer d'abord sans ASAR (resources/app/caddy/caddy)
        const noAsarPath = path.join(process.resourcesPath, 'app', 'caddy', 'caddy');
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy');

        if (fs.existsSync(noAsarPath)) {
            console.log('Chemin Caddy AppImage (sans ASAR):', noAsarPath);
            console.log('Caddy existe:', fs.existsSync(noAsarPath));
            return noAsarPath;
        } else if (fs.existsSync(asarPath)) {
            console.log('Chemin Caddy AppImage (avec ASAR):', asarPath);
            console.log('Caddy existe:', fs.existsSync(asarPath));
            return asarPath;
        } else {
            console.error('Caddy non trouvé ni avec ASAR ni sans ASAR');
            console.log('Ressources path:', process.resourcesPath);
            console.log('Tentative noAsarPath:', noAsarPath);
            console.log('Tentative asarPath:', asarPath);
            return 'caddy'; // Fallback système
        }
    } else if (isWindows) {
        // Mode développement : utiliser le Caddy local
        if (!isPackaged) {
            const devCaddyPath = path.join(__dirname, 'caddy', 'caddy.exe');
            if (fs.existsSync(devCaddyPath)) {
                console.log('Caddy trouvé (développement):', devCaddyPath);
                return devCaddyPath;
            }
        }


        // Mode packagé : détecter si ASAR est utilisé ou non
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
            // Dernier recours : essayer le Caddy local même en mode packagé
            const devCaddyPath = path.join(__dirname, 'caddy', 'caddy.exe');
            if (fs.existsSync(devCaddyPath)) {
                console.log('Caddy trouvé (fallback local):', devCaddyPath);
                return devCaddyPath;
            }
            return 'caddy.exe'; // Fallback système
        }
    } else {
        // macOS ou Linux développement : utiliser le Caddy inclus ou système
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
    const isAppImage = process.env.APPIMAGE || (process.resourcesPath && process.resourcesPath.includes('.mount'));
    const isPackaged = app.isPackaged;
    const isWindows = process.platform === 'win32';
    const isMacOS = process.platform === 'darwin';

    if (isWindows) {
        // Mode développement : utiliser le PHP local
        if (!isPackaged) {
            const devPhpPath = path.join(__dirname, 'php', 'php.exe');
            if (fs.existsSync(devPhpPath)) {
                console.log('PHP trouvé (développement):', devPhpPath);
                return devPhpPath;
            }
        }

        // Mode packagé : détecter si ASAR est utilisé ou non
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
            // Dernier recours : essayer le PHP local même en mode packagé
            const devPhpPath = path.join(__dirname, 'php', 'php.exe');
            if (fs.existsSync(devPhpPath)) {
                console.log('PHP trouvé (fallback local):', devPhpPath);
                return devPhpPath;
            }
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
        // Dans l'AppImage ou macOS, le Caddyfile peut être avec ou sans ASAR
        const isLinux = process.platform === 'linux';
        if (isAppImage || (isLinux && isPackaged)) {
            // Linux avec ASAR désactivé : fichiers dans resources/app/
            const noAsarPath = path.join(process.resourcesPath, 'app', 'Caddyfile');
            const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'Caddyfile');
            if (fs.existsSync(noAsarPath)) {
                return noAsarPath;
            } else if (fs.existsSync(asarPath)) {
                return asarPath;
            }
            return noAsarPath; // Fallback
        }
        // macOS utilise toujours ASAR
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
        // Pour AppImage, essayer d'abord app/app/public (structure réelle sans ASAR)
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app');

        if (fs.existsSync(noAsarPath)) {
            appPath = noAsarPath;
            console.log('App Path trouvé (sans ASAR):', appPath);
        } else if (fs.existsSync(asarPath)) {
            appPath = asarPath;
            console.log('App Path trouvé (avec ASAR):', appPath);
        } else {
            console.error('App Path non trouvé ni avec ASAR ni sans ASAR');
            appPath = noAsarPath; // Fallback
        }
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app');

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
            appPath = path.join(__dirname, 'app'); // Fallback développement
        }
    } else {
        appPath = path.join(__dirname, 'app');
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
        // Pour que vendor/autoload.php soit accessible, on utilise le répertoire parent de public
        // car public est le document root mais vendor est au niveau de public
        const appBasePath = appPath; // Root is app/
        const vendorPath = path.join(appBasePath, 'vendor'); // Chemin vers vendor avec le bon séparateur
        console.log('Configuration PHP pour Linux packagé/AppImage (PHP système)');
        console.log('Document root (public):', appPath);
        console.log('App base path (pour vendor):', appBasePath);
        phpArgs = [
            '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
            '-t', appPath, // Document root = public
            '-d', `include_path=${appBasePath}:${vendorPath}:.`,
            '-d', 'display_errors=1',
            '-d', 'log_errors=1',
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_input_vars=10000',
            '-d', 'max_input_nesting_level=256',
            '-d', `session.save_path=${sessionPath}`
        ];
    } else if (isWindows) {
        // Windows : utiliser le PHP embarqué avec extensions
        const asarExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');
        const noAsarExtPath = path.join(process.resourcesPath, 'app', 'php', 'ext');
        const devExtPath = path.join(__dirname, 'php', 'ext'); // Mode développement
        const phpIniPath = path.join(appPath, '..', 'php.ini');

        // Déterminer le chemin des extensions : développement d'abord, puis packagé
        let phpExtPath;
        if (!isPackaged && fs.existsSync(devExtPath)) {
            phpExtPath = path.resolve(devExtPath); // Chemin absolu pour développement
        } else if (fs.existsSync(noAsarExtPath)) {
            phpExtPath = path.resolve(noAsarExtPath);
        } else {
            phpExtPath = path.resolve(asarExtPath);
        }

        // Ajouter le répertoire parent au include_path pour que vendor/autoload.php soit accessible
        const appBasePath = appPath; // Root is app/
        const vendorPath = path.join(appBasePath, 'vendor'); // Chemin vers vendor avec le bon séparateur

        console.log('Configuration PHP pour Windows');
        console.log('isPackaged:', isPackaged);
        console.log('PHP Ini Path:', phpIniPath);
        console.log('PHP Ini exists:', fs.existsSync(phpIniPath));
        console.log('PHP Ext Path:', phpExtPath);
        console.log('PHP Ext exists:', fs.existsSync(phpExtPath));
        console.log('App base path (pour vendor):', appBasePath);

        phpArgs = [
            '-c', phpIniPath,
            '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
            '-t', appPath,
            '-d', `extension_dir=${phpExtPath.replace(/\\/g, '/')}`, // Utiliser des slashes pour Windows
            '-d', 'extension=php_sqlite3.dll', // Charger explicitement SQLite3
            '-d', 'extension=php_pdo_sqlite.dll', // Charger explicitement PDO SQLite
            '-d', `include_path=${appBasePath};${vendorPath};.`,
            '-d', 'display_errors=1',
            '-d', 'log_errors=1',
            '-d', 'upload_max_filesize=50M',
            '-d', 'post_max_size=50M',
            '-d', 'max_input_vars=10000',
            '-d', 'max_input_nesting_level=256',
            '-d', `session.save_path=${sessionPath}`
        ];
    } else {
        // macOS ou développement : utiliser php.ini si disponible
        const phpIniPath = path.join(appPath, '..', 'php.ini');
        const devExtPath = path.join(__dirname, 'php', 'ext'); // Mode développement
        const packagedExtPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'ext');

        // Utiliser le chemin de développement si disponible, sinon le chemin packagé
        const phpExtPath = (!isPackaged && fs.existsSync(devExtPath))
            ? path.resolve(devExtPath)
            : path.resolve(packagedExtPath);

        console.log('Configuration PHP pour macOS/dev');
        console.log('isPackaged:', isPackaged);
        console.log('PHP Ext Path:', phpExtPath);

        // Ajouter le répertoire parent au include_path pour que vendor/autoload.php soit accessible
        const appBasePath = appPath; // Root is app/
        const vendorPath = path.join(appBasePath, 'vendor'); // Chemin vers vendor avec le bon séparateur

        if (fs.existsSync(phpIniPath)) {
            phpArgs = [
                '-c', phpIniPath,
                '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
                '-t', appPath,
                '-d', `extension_dir=${phpExtPath}`,  // extension_dir avant include_path pour cohérence
                '-d', `include_path=${appBasePath}:${vendorPath}:.`,
                '-d', 'display_errors=1',
                '-d', 'log_errors=1',
                '-d', 'upload_max_filesize=50M',
                '-d', 'post_max_size=50M',
                '-d', 'max_input_vars=10000',
                '-d', 'max_input_nesting_level=256',
                '-d', `session.save_path=${sessionPath}`
            ];
        } else {
            // Pour macOS/dev, ajouter aussi le répertoire parent au include_path
            const appBasePath = appPath; // Root is app/
            const vendorPath = path.join(appBasePath, 'vendor'); // Chemin vers vendor avec le bon séparateur
            phpArgs = [
                '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
                '-t', appPath,
                '-d', `include_path=${appBasePath}:${vendorPath}:.`,
                '-d', 'display_errors=1',
                '-d', 'log_errors=1',
                '-d', 'upload_max_filesize=50M',
                '-d', 'post_max_size=50M',
                '-d', 'max_input_vars=10000',
                '-d', 'max_input_nesting_level=256',
                '-d', `session.save_path=${sessionPath}`
            ];
        }
    }

    // Préparer l'environnement avec le PATH mis à jour pour Windows
    const phpDir = path.dirname(phpPath);
    const env = {
        ...process.env,
        DUPLICATOR_DB_PATH: getDatabasePath()
    };

    // Sur Windows, ajouter le répertoire PHP au PATH pour que les DLL soient accessibles
    if (process.platform === 'win32') {
        const pathSeparator = process.platform === 'win32' ? ';' : ':';
        env.PATH = `${phpDir}${pathSeparator}${env.PATH || ''}`;
        console.log('PATH mis à jour avec le répertoire PHP:', phpDir);
    }

    phpFpmProcess = spawn(phpPath, phpArgs, {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: env
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
        ? (fs.existsSync(path.join(process.resourcesPath, 'app', 'app')) ? path.join(process.resourcesPath, 'app', 'app') : path.join(process.resourcesPath, 'app.asar.unpacked', 'app'))
        : isWindows
            ? (fs.existsSync(path.join(process.resourcesPath, 'app', 'app')) ? path.join(process.resourcesPath, 'app', 'app') : path.join(process.resourcesPath, 'app.asar.unpacked', 'app'))
            : path.join(__dirname, 'app');

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
    // Ajouter le répertoire parent au include_path pour que vendor/autoload.php soit accessible
    const appBasePath = path.join(appPath, '..'); // Remonter de public/ vers app/
    const vendorPath = path.join(appBasePath, 'vendor'); // Chemin vers vendor avec le bon séparateur
    const pathSeparator = isWindows ? ';' : ':';
    phpFpmProcess = spawn(phpPath, [
        '-S', `127.0.0.1:${PHP_SERVER_PORT}`,
        '-t', appPath,
        '-d', `include_path=${appBasePath}${pathSeparator}${vendorPath}${pathSeparator}.`,
        '-d', 'display_errors=1',
        '-d', 'upload_max_filesize=50M',
        '-d', 'post_max_size=50M',
        '-d', 'max_input_vars=10000',
        '-d', 'max_input_nesting_level=256',
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
    // Sur Windows, trouver un port libre au hasard
    if (isWindows) {
        // Force port 8000 for debugging
        serverPort = 8000;
        console.log(`Port forcé sur Windows: ${serverPort}`);
        /*
        try {
            serverPort = await findFreePort();
            console.log(`Port libre trouvé sur Windows: ${serverPort}`);
        } catch (error) {
            console.error('Erreur lors de la recherche d\'un port libre, utilisation du port par défaut:', error);
            serverPort = 8000;
        }
        */
    }

    // Obtenir le Caddyfile original
    const originalCaddyfile = getCaddyfilePath();
    console.log('Caddyfile original:', originalCaddyfile);
    console.log('Caddyfile existe:', fs.existsSync(originalCaddyfile));

    // RÉPARATION: Vérifier et nettoyer le stockage Caddy corrompu (\x00 error)
    if (isWindows) {
        const caddyAppData = path.join(process.env.APPDATA, 'Caddy');
        const lastCleanPath = path.join(caddyAppData, 'last_clean.json');
        if (fs.existsSync(lastCleanPath)) {
            try {
                const content = fs.readFileSync(lastCleanPath, 'utf8');
                if (content.includes('\0') || content.trim() === '') {
                    console.log('Fichier last_clean.json corrompu détecté, suppression...');
                    fs.unlinkSync(lastCleanPath);
                }
            } catch (e) {
                console.log('Erreur lors de la vérification de last_clean.json:', e.message);
                try { fs.unlinkSync(lastCleanPath); } catch (e2) { }
            }
        }

        // Nettoyer aussi les vieux fichiers temporaires dans le dossier temp de l'utilisateur
        const tempDir = os.tmpdir();
        try {
            const files = fs.readdirSync(tempDir);
            files.forEach(file => {
                if (file.startsWith('caddyfile_') && file.endsWith('.tmp')) {
                    const filePath = path.join(tempDir, file);
                    const stats = fs.statSync(filePath);
                    // Supprimer si plus vieux de 1 heure
                    if (Date.now() - stats.mtimeMs > 3600000) {
                        fs.unlinkSync(filePath);
                    }
                }
            });
        } catch (e) {
            console.log('Erreur lors du nettoyage des vieux Caddyfiles:', e.message);
        }
    }

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
        // Pour AppImage, essayer d'abord app/app/public (structure réelle sans ASAR)
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app');
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app');

        if (fs.existsSync(noAsarPath)) {
            appPath = noAsarPath;
            console.log('Caddy App Path trouvé (sans ASAR):', appPath);
        } else if (fs.existsSync(asarPath)) {
            appPath = asarPath;
            console.log('Caddy App Path trouvé (avec ASAR):', appPath);
        } else {
            console.error('Caddy App Path non trouvé ni avec ASAR ni sans ASAR');
            appPath = noAsarPath; // Fallback
        }
    } else if (isWindows) {
        // Windows : détecter si ASAR est utilisé ou non
        // Même avec asar: false, les fichiers sont dans resources/app/
        const asarPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app');
        const noAsarPath = path.join(process.resourcesPath, 'app', 'app');

        // Essayer d'abord sans ASAR (configuration actuelle: resources/app/app/public)
        if (fs.existsSync(noAsarPath)) {
            appPath = noAsarPath;
        }
        // Fallback avec ASAR si nécessaire (resources/app.asar.unpacked/app/public)
        else if (fs.existsSync(asarPath)) {
            appPath = asarPath;
        }
        else {
            appPath = path.join(__dirname, 'app'); // Fallback développement
        }
    } else {
        appPath = path.join(__dirname, 'app');
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
        try {
            console.error('Caddy Error:', data.toString());
        } catch (error) {
            // Ignorer uniquement les erreurs EPIPE (flux fermé - normal lors de la fermeture)
            // Les autres erreurs doivent être affichées
            if (error.code && error.code !== 'EPIPE') {
                // Essayer d'afficher l'erreur via un autre moyen si console.error échoue
                try {
                    process.stderr.write('Erreur lors de l\'écriture du log Caddy: ' + error.message + '\n');
                } catch (e) {
                    // Si même ça échoue, ignorer silencieusement
                }
            }
            // Pour EPIPE, on ignore silencieusement (flux fermé normalement)
        }
    });

    caddyProcess.on('close', (code) => {
        console.log(`Caddy fermé avec le code ${code}`);
        // Nettoyer le fichier temporaire si créé
        if (tempCaddyfilePath && fs.existsSync(tempCaddyfilePath)) {
            try {
                // fs.unlinkSync(tempCaddyfilePath);
                console.log('Caddyfile temporaire conservé pour debug:', tempCaddyfilePath);
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
async function stopProcesses() {
    console.log('Fermeture des sessions actives...');
    try {
        // Appeler l'API PHP pour fermer toutes les sessions
        // On utilise http.get car c'est simple et synchrone-ish avec un petit timeout
        const request = new Promise((resolve) => {
            const req = http.get(`http://127.0.0.1:${PHP_SERVER_PORT}/?sessions&action=close_all`, (res) => {
                resolve();
            });
            req.on('error', () => resolve()); // On ignore les erreurs à la fermeture
            req.setTimeout(2000, () => {
                req.destroy();
                resolve();
            });
        });
        await request;
    } catch (e) {
        console.error('Erreur fermeture sessions:', e.message);
    }

    if (phpFpmProcess) {
        stopPhpFpmProcess().catch(error => {
            console.log(`Erreur lors de l'arrêt du processus PHP: ${error.message}`);
        });
    }

    stopPhpErrorLogWatcher();

    // Arrêter le moniteur d'imprimantes
    if (printerMonitor) {
        try {
            printerMonitor.stop();
            printerMonitor = null;
        } catch (error) {
            console.error('Erreur lors de l\'arrêt du moniteur d\'imprimantes:', error);
        }
    }

    if (caddyProcess) {
        caddyProcess.kill();
        caddyProcess = null;
    }

    // Nettoyer le fichier temporaire si créé
    if (tempCaddyfilePath && fs.existsSync(tempCaddyfilePath)) {
        try {
            // fs.unlinkSync(tempCaddyfilePath);
            console.log('Caddyfile temporaire conservé pour debug:', tempCaddyfilePath);
        } catch (error) {
            console.error('Erreur suppression Caddyfile temporaire:', error);
        }
        // tempCaddyfilePath = null;
    }
}

// Arrêt synchronisé de tous les processus enfants avant redémarrage/maj
async function stopAllChildrenGracefully() {
    try {
        await stopPhpFpmProcess().catch(() => { });
    } catch { }
    try {
        if (caddyProcess && !caddyProcess.killed) {
            caddyProcess.kill('SIGTERM');
            await new Promise((r) => setTimeout(r, 2000));
            if (caddyProcess && !caddyProcess.killed) {
                try { caddyProcess.kill('SIGKILL'); } catch { }
            }
        }
    } catch { }
    try { stopPhpErrorLogWatcher(); } catch { }
    try {
        if (printerMonitor) {
            printerMonitor.stop();
            printerMonitor = null;
        }
    } catch { }
}

// Démarrer le moniteur d'imprimantes (Windows + Linux)
function startPrinterMonitor() {
    if (process.platform !== 'win32' && process.platform !== 'linux') {
        console.log('Le moniteur d\'imprimantes n\'est pas supporté sur cet OS');
        return;
    }

    if (printerMonitor) {
        console.log('Le moniteur d\'imprimantes est déjà démarré');
        return;
    }

    try {
        printerMonitor = new PrinterMonitor({
            phpApiUrl: `http://127.0.0.1:${PHP_SERVER_PORT}`,
            onPrintJob: (printData) => {
                // PATCH: Si le nom est un nom générique de Ghostscript ou Electron, 
                // essayer de trouver le vrai nom dans le cache basé sur l'imprimante
                let docName = printData.Document;
                let bestMatch = null; // Déclaré ici pour être accessible pour l'enrichissement

                const genericNames = [
                    'ghostscript', 'ghostscript output', 'dupli-print', 'print job',
                    'untitled', 'gswin64c', 'gswin64', 'gs output', 'output', 'outp'
                ];

                const lowerDocName = (docName || '').toLowerCase().trim();
                const now = Date.now();
                const windowMs = 300000; // 5 minutes (augmenté pour les impressions longues)

                // Toujours chercher un match dans le cache (pour enrichir les options même si le nom n'est pas générique)
                let bestTime = 0;
                for (const [key, entry] of printOptionsCache.entries()) {
                    const monitorPrinter = (printData.PrinterName || '').toLowerCase().replace(/[^a-z0-9]/g, '');
                    const cachePrinter = (entry.options && entry.options.printer || '').toLowerCase().replace(/[^a-z0-9]/g, '');

                    const printerMatches = cachePrinter && (
                        monitorPrinter.includes(cachePrinter) ||
                        cachePrinter.includes(monitorPrinter) ||
                        monitorPrinter === cachePrinter
                    );

                    if (printerMatches) {
                        const age = now - entry.timestamp;
                        if (age < windowMs && entry.timestamp > bestTime) {
                            bestMatch = entry;
                            bestTime = entry.timestamp;
                        }
                    }
                }

                // FALLBACK ULTIME: Si aucun match par imprimante, prendre le job le plus récent du cache 
                if (!bestMatch && printOptionsCache.size > 0) {
                    const entries = Array.from(printOptionsCache.values()).sort((a, b) => b.timestamp - a.timestamp);
                    const mostRecent = entries[0];
                    if (now - mostRecent.timestamp < 20000) { // Si moins de 20s
                        bestMatch = mostRecent;
                        console.log(`⚠️ [PRINT_MONITOR] Match par proximité temporelle (20s)`);
                    }
                }

                // Si nom générique, remplacer par le nom du cache
                if (genericNames.some(g => lowerDocName.includes(g)) || !lowerDocName) {
                    const debugMsg = `🔍 [PRINT_MONITOR] Nom générique détecté ("${docName}"), recherche dans le cache (${printOptionsCache.size} entrées)...`;
                    console.log(debugMsg);
                    sendToRenderer('console-log', { message: debugMsg, type: 'info' });

                    if (bestMatch && bestMatch.options && (bestMatch.options.fileName || bestMatch.options.document)) {
                        const recoveredName = bestMatch.options.fileName || bestMatch.options.document;
                        const successMsg = `✅ [PRINT_MONITOR] Match trouvé ! Remplacement de "${docName}" par "${recoveredName}"`;
                        console.log(successMsg);
                        sendToRenderer('console-log', { message: successMsg, type: 'success' });
                        docName = recoveredName;
                    } else {
                        let cacheDump = [];
                        for (const [key, entry] of printOptionsCache.entries()) {
                            const cPrinter = (entry.options && entry.options.printer) || 'N/A';
                            const age = Math.round((now - entry.timestamp) / 1000);
                            cacheDump.push(`- Key: ${key.substring(0, 20)}... | Printer: "${cPrinter}" | Age: ${age}s`);
                        }

                        const failMsg = `❌ [PRINT_MONITOR] Aucun match pour "${printData.PrinterName}". \nContenu du cache (${printOptionsCache.size} clés) :\n${cacheDump.join('\n')}`;
                        console.log(failMsg);
                        sendToRenderer('console-log', { message: failMsg, type: 'warning' });
                    }
                }

                // Construire les données enrichies avec les options du cache si disponibles
                let enrichedPrintData = { ...printData, Document: docName };

                // Si on a trouvé un match dans le cache, utiliser les options stockées
                // car le moniteur natif ne peut pas lire ces valeurs depuis Ghostscript
                if (bestMatch && bestMatch.options) {
                    const cachedOpts = bestMatch.options;

                    // Enrichir avec duplex depuis le cache (le natif retourne toujours 0 pour GS)
                    if (cachedOpts.duplex) {
                        const isDuplex = cachedOpts.duplex === 'duplex' || cachedOpts.duplex === 'tumble';
                        enrichedPrintData.IsDuplex = isDuplex;
                        console.log(`📋 [CACHE] Duplex enrichi depuis cache: ${cachedOpts.duplex} → IsDuplex=${isDuplex}`);
                    }

                    // Enrichir avec paperSize depuis le cache
                    if (cachedOpts.paperSize && (!enrichedPrintData.PaperSize || enrichedPrintData.PaperSize === 'Unknown')) {
                        enrichedPrintData.PaperSize = cachedOpts.paperSize;
                        console.log(`📋 [CACHE] PaperSize enrichi depuis cache: ${cachedOpts.paperSize}`);
                    }

                    // Enrichir avec colorMode depuis le cache
                    if (cachedOpts.colorMode) {
                        const isColor = cachedOpts.colorMode === 'color';
                        enrichedPrintData.ColorMode = isColor ? 'Color' : 'Monochrome';
                        console.log(`📋 [CACHE] ColorMode enrichi depuis cache: ${cachedOpts.colorMode}`);
                    }

                    // Enrichir avec copies depuis le cache si pas défini
                    if (cachedOpts.copies && (!enrichedPrintData.Copies || enrichedPrintData.Copies <= 1)) {
                        enrichedPrintData.Copies = cachedOpts.copies;
                        console.log(`📋 [CACHE] Copies enrichi depuis cache: ${cachedOpts.copies}`);
                    }
                }

                // Envoyer la notification au renderer
                sendToRenderer('print-job-detected', enrichedPrintData);
                console.log('Impression détectée:', enrichedPrintData);

                // Envoyer les données à l'API PHP pour enregistrement en base
                const http = require('http');
                const postData = JSON.stringify({
                    jobId: String(enrichedPrintData.JobId),
                    document: enrichedPrintData.Document,
                    printerName: enrichedPrintData.PrinterName,
                    status: enrichedPrintData.Status,
                    totalPages: enrichedPrintData.TotalPages || 0,
                    paperSize: enrichedPrintData.PaperSize,
                    duplex: enrichedPrintData.IsDuplex,
                    colorMode: enrichedPrintData.ColorMode,
                    copies: enrichedPrintData.Copies || 1,
                    fillRate: enrichedPrintData.FillRate || 0,
                    thumbnailUrl: enrichedPrintData.ThumbnailUrl || '',
                    timestamp: enrichedPrintData.TimeSubmitted || new Date().toISOString(),
                    eventType: 'job_detected',
                    platform: process.platform
                });

                const options = {
                    hostname: '127.0.0.1',
                    port: PHP_SERVER_PORT,
                    path: '/index.php?print_notification',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Content-Length': Buffer.byteLength(postData)
                    }
                };

                const req = http.request(options, (res) => {
                    let data = '';
                    res.on('data', chunk => data += chunk);
                    res.on('end', () => {
                        if (res.statusCode === 200) {
                            console.log('✅ Notification PHP enregistrée:', data);
                        } else {
                            console.error('⚠️ Erreur API PHP:', res.statusCode, data);
                        }
                    });
                });

                req.on('error', (e) => {
                    console.error('❌ Erreur envoi notification PHP:', e.message);
                });

                req.write(postData);
                req.end();
            },
            onError: (error) => {
                console.error('Erreur moniteur d\'imprimantes:', error);
                sendToRenderer('print-monitor-error', { error: error });
            }
        });

        const started = printerMonitor.start();
        if (started) {
            console.log('✅ Moniteur d\'imprimantes Windows démarré avec succès');
            sendToRenderer('print-monitor-started', { status: 'active' });
            // Afficher aussi dans la console du renderer
            if (mainWindow && !mainWindow.isDestroyed()) {
                mainWindow.webContents.executeJavaScript(`
                    console.log('%c✅ Moniteur d\'imprimantes Windows démarré', 'color: green; font-weight: bold;');
                `).catch(() => { });
            }
        } else {
            console.error('❌ Échec du démarrage du moniteur d\'imprimantes');
        }
    } catch (error) {
        console.error('Erreur lors de l\'initialisation du moniteur d\'imprimantes:', error);
        sendToRenderer('print-monitor-error', { error: error.message });
    }
}

// Créer le menu personnalisé
function createMenu() {
    const template = [
        {
            label: 'Application',
            submenu: [
                {
                    label: 'À propos',
                    accelerator: 'F1',
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
                    accelerator: 'F3',
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
                    label: 'Accueil',
                    accelerator: 'Ctrl+H',
                    click: () => {
                        const targetPort = frontendPort || serverPort || PHP_SERVER_PORT;
                        const accueilUrl = `http://127.0.0.1:${targetPort}/?accueil`;
                        mainWindow.loadURL(accueilUrl).catch(error => {
                            console.log(`Échec du chargement de la page d'accueil: ${error.message}`);
                            try {
                                mainWindow.reload();
                            } catch (reloadError) {
                                console.log(`Échec du reload de la fenêtre: ${reloadError.message}`);
                            }
                        });
                    }
                },
                { type: 'separator' },
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
        title: 'Duplicator',
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js'),
            sandbox: false,
            offscreen: false
        },
        show: false
    });

    // Définir explicitement le titre pour Linux (aide à la correspondance WMClass)
    if (process.platform === 'linux') {
        mainWindow.setTitle('Duplicator');
        // S'assurer que le WMClass est cohérent (déjà défini via app.setName() plus haut)
        // Le WMClass par défaut d'Electron est basé sur app.getName()
    }

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

                // Vérifier Ghostscript avant de charger l'app (Windows uniquement)
                if (process.platform === 'win32') {
                    try {
                        await checkGhostscript(frontendPort);
                        mainWindow.loadURL(fallbackUrl);
                        mainWindow.show();
                    } catch (ghostscriptError) {
                        // S'assurer que la fenêtre est visible pour le dialog
                        if (!mainWindow.isVisible()) {
                            mainWindow.show();
                        }

                        dialog.showMessageBox(mainWindow, {
                            type: 'warning',
                            title: 'Avertissement - Ghostscript non disponible',
                            message: 'Ghostscript ne peut pas s\'exécuter',
                            detail: ghostscriptError.message + '\n\n' +
                                'L\'application peut continuer, mais certaines fonctionnalités de traitement PDF ne seront pas disponibles :\n' +
                                '• Génération de miniatures pour les fichiers PDF\n' +
                                '• Conversion PDF vers PNG\n\n' +
                                'Pour activer ces fonctionnalités, installez Visual C++ Redistributable.\n\n' +
                                'Souhaitez-vous télécharger Visual C++ Redistributable maintenant ?',
                            buttons: ['Télécharger Visual C++ Redistributable', 'Continuer sans Ghostscript'],
                            defaultId: 1,
                            cancelId: 1
                        }).then((result) => {
                            if (result.response === 0) {
                                shell.openExternal('https://aka.ms/vs/17/release/vc_redist.x64.exe');
                            }
                            // Dans tous les cas, continuer le chargement de l'application
                            mainWindow.loadURL(fallbackUrl);
                            mainWindow.show();
                        }).catch((dialogError) => {
                            console.error('Erreur lors de l\'affichage du dialog:', dialogError);
                            // Continuer même en cas d'erreur de dialog
                            mainWindow.loadURL(fallbackUrl);
                            mainWindow.show();
                        });
                        return; // Ne pas charger l'URL ici, c'est fait dans le dialog
                    }
                } else {
                    mainWindow.loadURL(fallbackUrl);
                    mainWindow.show();
                }
            } else {
                frontendPort = serverPort;
                sendToRenderer(PHP_STATUS_CHANNEL, {
                    status: 'proxy-ready',
                    timestamp: new Date().toISOString(),
                    port: frontendPort,
                    proxyPort: serverPort,
                    phpPort: PHP_SERVER_PORT
                });

                // Vérifier Ghostscript avant de charger l'app (Windows uniquement)
                if (process.platform === 'win32') {
                    try {
                        await checkGhostscript(frontendPort);
                        mainWindow.loadURL(appUrl);
                        mainWindow.show();
                        console.log(`Serveurs démarrés avec succès sur le port ${serverPort}`);
                    } catch (ghostscriptError) {
                        // S'assurer que la fenêtre est visible pour le dialog
                        if (!mainWindow.isVisible()) {
                            mainWindow.show();
                        }

                        dialog.showMessageBox(mainWindow, {
                            type: 'warning',
                            title: 'Avertissement - Ghostscript non disponible',
                            message: 'Ghostscript ne peut pas s\'exécuter',
                            detail: ghostscriptError.message + '\n\n' +
                                'L\'application peut continuer, mais certaines fonctionnalités de traitement PDF ne seront pas disponibles :\n' +
                                '• Génération de miniatures pour les fichiers PDF\n' +
                                '• Conversion PDF vers PNG\n\n' +
                                'Pour activer ces fonctionnalités, installez Visual C++ Redistributable.\n\n' +
                                'Souhaitez-vous télécharger Visual C++ Redistributable maintenant ?',
                            buttons: ['Télécharger Visual C++ Redistributable', 'Continuer sans Ghostscript'],
                            defaultId: 1,
                            cancelId: 1
                        }).then((result) => {
                            if (result.response === 0) {
                                shell.openExternal('https://aka.ms/vs/17/release/vc_redist.x64.exe');
                            }
                            // Dans tous les cas, continuer le chargement de l'application
                            mainWindow.loadURL(appUrl);
                            mainWindow.show();
                            console.log(`Serveurs démarrés avec succès sur le port ${serverPort}`);
                        }).catch((dialogError) => {
                            console.error('Erreur lors de l\'affichage du dialog:', dialogError);
                            // Continuer même en cas d'erreur de dialog
                            mainWindow.loadURL(appUrl);
                            mainWindow.show();
                            console.log(`Serveurs démarrés avec succès sur le port ${serverPort}`);
                        });
                        return; // Ne pas charger l'URL ici, c'est fait dans le dialog
                    }
                } else {
                    mainWindow.loadURL(appUrl);
                    mainWindow.show();
                    console.log(`Serveurs démarrés avec succès sur le port ${serverPort}`);
                }

                // Démarrer le moniteur d'imprimantes Windows après le démarrage des serveurs
                startPrinterMonitor();
            }
        } catch (error) {
            console.error('Erreur lors du démarrage des serveurs:', error);
            // Fallback : utiliser le serveur PHP intégré uniquement
            console.log('Tentative de démarrage avec le serveur PHP intégré uniquement...');
            try {
                startPhpServer();
                frontendPort = PHP_SERVER_PORT;

                // Attendre que le serveur soit prêt puis vérifier Ghostscript (Windows uniquement)
                if (process.platform === 'win32') {
                    setTimeout(async () => {
                        try {
                            await checkGhostscript(PHP_SERVER_PORT);
                            mainWindow.loadURL(`http://127.0.0.1:${PHP_SERVER_PORT}/`);
                            mainWindow.show();
                            console.log('Serveur PHP intégré démarré avec succès');
                        } catch (ghostscriptError) {
                            // S'assurer que la fenêtre est visible pour le dialog
                            if (!mainWindow.isVisible()) {
                                mainWindow.show();
                            }

                            dialog.showMessageBox(mainWindow, {
                                type: 'warning',
                                title: 'Avertissement - Ghostscript non disponible',
                                message: 'Ghostscript ne peut pas s\'exécuter',
                                detail: ghostscriptError.message + '\n\n' +
                                    'L\'application peut continuer, mais certaines fonctionnalités de traitement PDF ne seront pas disponibles :\n' +
                                    '• Génération de miniatures pour les fichiers PDF\n' +
                                    '• Conversion PDF vers PNG\n\n' +
                                    'Pour activer ces fonctionnalités, installez Visual C++ Redistributable.\n\n' +
                                    'Souhaitez-vous télécharger Visual C++ Redistributable maintenant ?',
                                buttons: ['Télécharger Visual C++ Redistributable', 'Continuer sans Ghostscript'],
                                defaultId: 1,
                                cancelId: 1
                            }).then((result) => {
                                if (result.response === 0) {
                                    shell.openExternal('https://aka.ms/vs/17/release/vc_redist.x64.exe');
                                }
                                // Dans tous les cas, continuer le chargement de l'application
                                mainWindow.loadURL(`http://127.0.0.1:${PHP_SERVER_PORT}/`);
                                mainWindow.show();
                                console.log('Serveur PHP intégré démarré avec succès');
                            }).catch((dialogError) => {
                                console.error('Erreur lors de l\'affichage du dialog:', dialogError);
                                // Continuer même en cas d'erreur de dialog
                                mainWindow.loadURL(`http://127.0.0.1:${PHP_SERVER_PORT}/`);
                                mainWindow.show();
                                console.log('Serveur PHP intégré démarré avec succès');
                            });
                        }
                    }, 2000);
                } else {
                    mainWindow.loadURL(`http://127.0.0.1:${PHP_SERVER_PORT}/`);
                    mainWindow.show();
                    console.log('Serveur PHP intégré démarré avec succès');
                }
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
    autoUpdater = require('electron-updater').autoUpdater;
    
    // Configuration
    autoUpdater.autoDownload = false; // Ne pas télécharger automatiquement (demander d'abord)
    autoUpdater.autoInstallOnAppQuit = true; // Installer automatiquement au redémarrage

    // Détecter si c'est la version beta (basé sur appId ou nom du dossier)
    const isBeta = app.getName() === 'dupli-electron-beta' || app.getAppPath().includes('beta') || app.getName().includes('beta');
    const channel = isBeta ? 'beta' : 'latest';

    console.log(`[AutoUpdater] Mode détecté: ${isBeta ? 'BETA (channel: beta)' : 'STABLE (channel: latest)'}`);

    // Détecter le format de l'application
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');

    // Configurer le provider selon le format
    // electron-updater détecte automatiquement AppImage vs deb
    // Le problème est que latest-linux.yml peut pointer vers le mauvais format
    // Solution: utiliser providerOptions.updateConfigPath pour pointer vers le bon fichier
    if (isAppImage) {
        console.log('AppImage détectée - configuration pour utiliser latest-linux-appimage.yml');
        // Configurer le provider GitHub pour utiliser le fichier AppImage
        // electron-updater cherchera latest-linux-appimage.yml au lieu de latest-linux.yml
        // Pour AppImage, configurer pour utiliser latest-linux-appimage.yml
        // electron-updater cherchera ce fichier au lieu de latest-linux.yml
        // Note: updateConfigPath peut être utilisé via providerOptions dans certaines versions
        // Pour l'instant, on va utiliser une approche de contournement :
        // - Le workflow génère latest-linux-appimage.yml
        // - On va intercepter checkForUpdates pour télécharger manuellement ce fichier
        // - Ou utiliser un provider personnalisé
        autoUpdater.setFeedURL({
            provider: 'github',
            owner: 'muarf',
            repo: 'dupli-electron-caddy',
            channel: channel,  // 'beta' ou 'latest'
            releaseType: isBeta ? 'prerelease' : 'release'
        });
    } else {
        console.log('Version .deb détectée - configuration du channel:', channel);
        // Configurer le channel pour .deb aussi (beta ou stable)
        autoUpdater.setFeedURL({
            provider: 'github',
            owner: 'muarf',
            repo: 'dupli-electron-caddy',
            channel: channel,
            releaseType: isBeta ? 'prerelease' : 'release'
        });
    }

    // Avant de quitter pour installer, arrêter proprement Caddy/PHP
    autoUpdater.on('before-quit-for-update', async () => {
        console.log('before-quit-for-update: arrêt des processus enfants...');
        await stopAllChildrenGracefully();
    });

    // Événements de mise à jour
    autoUpdater.on('checking-for-update', () => {
        console.log('Vérification des mises à jour...');
    });

    // Fonction helper pour envoyer des messages à la fenêtre de manière sécurisée
    const safeSendToWindow = (channel, data) => {
        try {
            if (mainWindow && !mainWindow.isDestroyed() && mainWindow.webContents && !mainWindow.webContents.isDestroyed()) {
                mainWindow.webContents.send(channel, data);
                return true;
            }
        } catch (e) {
            // Fenêtre déjà détruite, ignorer silencieusement
            console.log(`Fenêtre détruite, message ${channel} ignoré`);
        }
        return false;
    };

    autoUpdater.on('update-available', (info) => {
        console.log('Mise à jour disponible:', info.version);

        // Vérifier si on est en AppImage et si la mise à jour pointe vers un .deb
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
        if (isAppImage) {
            // Vérifier l'URL ou le chemin du fichier de mise à jour
            const updateUrl = info.url || info.path || '';
            const isDebFile = updateUrl.includes('.deb') || updateUrl.endsWith('.deb');

            if (isDebFile) {
                console.log('Conflit détecté : AppImage trouve un .deb dans les métadonnées');
                const userFriendlyError = {
                    message: 'Mise à jour non compatible',
                    detail: 'Les métadonnées pointent vers un fichier .deb au lieu d\'un AppImage.\n\nVeuillez télécharger manuellement la nouvelle version AppImage depuis GitHub :\nhttps://github.com/muarf/dupli-electron-caddy/releases\n\nOu utilisez la version .deb pour bénéficier des mises à jour automatiques.',
                    version: info.version
                };
                safeSendToWindow('update-error', userFriendlyError);
                return; // Ne pas envoyer update-available
            }
        }

        // Envoyer une notification à l'interface
        safeSendToWindow('update-available', info);
    });

    autoUpdater.on('update-not-available', (info) => {
        console.log('Aucune mise à jour disponible');

        safeSendToWindow('update-not-available', info);
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

        // Gestion spécifique pour AppImage qui trouve un .deb au lieu d'un AppImage
        const isAppImageDebConflict = err.message && (
            err.message.includes('Cannot read properties of undefined') ||
            err.message.includes('reading \'info\'')
        ) && (process.env.APPIMAGE || process.resourcesPath.includes('.mount'));

        // Gestion spécifique de l'erreur pkexec sur Linux (code 127 = commande non trouvée)
        const isPkexecError = err.message && (
            err.message.includes('pkexec') ||
            err.message.includes('exited with code 127')
        );

        if (isAppImageDebConflict) {
            // L'AppImage a trouvé un .deb dans les métadonnées
            // Cela peut arriver si latest-linux.yml pointe vers le .deb
            console.log('Conflit détecté : AppImage trouve un .deb dans les métadonnées');
            const userFriendlyError = {
                message: 'Erreur de mise à jour AppImage',
                detail: 'Les métadonnées pointent vers un fichier .deb au lieu d\'un AppImage.\n\nVeuillez télécharger manuellement la nouvelle version AppImage depuis GitHub :\nhttps://github.com/muarf/dupli-electron-caddy/releases\n\nOu utilisez la version .deb pour bénéficier des mises à jour automatiques.'
            };
            safeSendToWindow('update-error', userFriendlyError);
        } else if (isPkexecError && process.platform === 'linux') {
            // Sur Linux, si pkexec échoue, suggérer une installation manuelle
            console.log('Erreur pkexec détectée, installation automatique non disponible');
            const userFriendlyError = {
                message: 'Installation automatique non disponible',
                detail: 'Veuillez installer manuellement le package .deb téléchargé avec :\nsudo dpkg -i /path/to/Duplicator-*.deb\n\nOu téléchargez la nouvelle version depuis GitHub.'
            };
            safeSendToWindow('update-error', userFriendlyError);
        } else if (!isNetworkError) {
            // Afficher l'erreur uniquement si ce n'est pas un problème de réseau
            safeSendToWindow('update-error', err);
        } else {
            console.log('Vérification des mises à jour ignorée (pas de connexion internet ou release non disponible)');
        }
    });

    autoUpdater.on('download-progress', (progressObj) => {
        console.log(`Téléchargement: ${progressObj.percent.toFixed(2)}%`);

        // Envoyer la progression à l'interface
        safeSendToWindow('download-progress', progressObj);
    });

    autoUpdater.on('update-downloaded', (info) => {
        console.log('Mise à jour téléchargée, installation au redémarrage');

        // Notifier l'utilisateur
        safeSendToWindow('update-downloaded', info);
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

// Définir le nom de l'application pour Linux (WMClass) - doit correspondre au StartupWMClass dans .desktop
if (process.platform === 'linux') {
    app.setName('Duplicator');
}

// Cette méthode sera appelée quand Electron aura fini de s'initialiser
app.whenReady().then(() => {
    console.log('DEBUG: app.whenReady started');
    // Appliquer les paramètres de compatibilité Windows
    applyCompatibilitySettings();

    createWindow();

    // Initialiser la base de données dans userData
    getDatabasePath();

    // Configurer l'auto-updater uniquement en production
    if (process.env.NODE_ENV !== 'development') {
        setupAutoUpdater();
    }

    // Configurer les imprimantes (KeepPrintedJobs)
    configurePrinters();

    // Planifier la purge sécurisée (7 jours)
    scheduleSecurePurge();
});


// Configurer les imprimantes au démarrage (KeepPrintedJobs = 1)
function configurePrinters() {
    console.log('Configuration des imprimantes (KeepPrintedJobs)...');

    // Chemin vers le script PowerShell
    const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
    let scriptPath;

    if (isAppImage) {
        scriptPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'scripts', 'configure-printers.ps1');
    } else {
        scriptPath = path.join(__dirname, 'scripts', 'configure-printers.ps1');
    }

    console.log('Script Path:', scriptPath);

    if (!fs.existsSync(scriptPath)) {
        console.error('ERROR: PowerShell script not found at:', scriptPath);
        return;
    }

    // Exécuter directement avec spawn
    const child = spawn('powershell', ['-ExecutionPolicy', 'Bypass', '-File', scriptPath]);

    child.stdout.on('data', (data) => {
        console.log('Printer Config Wrapper:', data.toString());
    });

    child.stderr.on('data', (data) => {
        console.error('Printer Config Wrapper Error:', data.toString());
    });

    child.on('error', (err) => {
        console.error('FAILED to spawn PowerShell:', err);
    });

    child.on('close', (code) => {
        console.log(`Configuration imprimantes terminée (wrapper code ${code})`);
    });
}

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
    } catch { }
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
            fullPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', filePath);
        } else {
            fullPath = path.join(__dirname, 'app', filePath);
        }

        await shell.openPath(fullPath);
        return { success: true };
    } catch (error) {
        console.error('Erreur ouverture fichier:', error);
        return { success: false, error: error.message };
    }
});

// Gérer la sélection de dossiers/fichiers
ipcMain.handle('show-open-dialog', async (event, options) => {
    try {
        const result = await dialog.showOpenDialog(mainWindow, options);
        return result;
    } catch (error) {
        console.error('Erreur dialog:', error);
        return { canceled: true, filePaths: [] };
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
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');

        // Pour .deb, on doit utiliser latest-linux-deb.yml au lieu de latest-linux.yml
        // car latest-linux.yml pointe maintenant vers AppImage (construit en dernier)
        if (!isAppImage) {
            // Pour .deb, télécharger latest-linux-deb.yml et utiliser autoUpdater avec ces données
            const https = require('https');
            const yaml = require('js-yaml');
            const os = require('os');
            const path = require('path');
            const fs = require('fs');

            return new Promise((resolve, reject) => {
                const releaseUrl = 'https://api.github.com/repos/muarf/dupli-electron-caddy/releases/latest';
                https.get(releaseUrl, {
                    headers: {
                        'User-Agent': 'Duplicator-Updater'
                    }
                }, (res) => {
                    let data = '';
                    res.on('data', (chunk) => { data += chunk; });
                    res.on('end', () => {
                        try {
                            const release = JSON.parse(data);
                            // Chercher latest-linux-deb.yml dans les assets
                            const debYml = release.assets.find(asset =>
                                asset.name === 'latest-linux-deb.yml'
                            );

                            if (debYml) {
                                // Télécharger le fichier YML
                                https.get(debYml.browser_download_url, (ymlRes) => {
                                    let ymlData = '';
                                    ymlRes.on('data', (chunk) => { ymlData += chunk; });
                                    ymlRes.on('end', () => {
                                        try {
                                            // Parser le YML
                                            const updateConfig = yaml.load(ymlData);

                                            // Créer un fichier temporaire latest-linux.yml avec le contenu de latest-linux-deb.yml
                                            const tempDir = path.join(os.tmpdir(), 'duplicator-updater');
                                            if (!fs.existsSync(tempDir)) {
                                                fs.mkdirSync(tempDir, { recursive: true });
                                            }
                                            const tempYmlPath = path.join(tempDir, 'latest-linux.yml');
                                            fs.writeFileSync(tempYmlPath, ymlData);

                                            // Configurer autoUpdater pour utiliser ce fichier temporaire
                                            // Note: autoUpdater ne supporte pas directement les fichiers locaux
                                            // On va utiliser setFeedURL avec les données du YML
                                            const updateInfo = {
                                                version: updateConfig.version || release.tag_name.replace('v', ''),
                                                path: updateConfig.path || '',
                                                url: updateConfig.url || debYml.browser_download_url.replace('latest-linux-deb.yml', updateConfig.path || ''),
                                                sha512: updateConfig.sha512 || '',
                                                releaseDate: updateConfig.releaseDate || release.published_at
                                            };

                                            resolve({ success: true, updateInfo: updateInfo });
                                        } catch (yamlErr) {
                                            console.error('Erreur parsing YML:', yamlErr);
                                            reject(yamlErr);
                                        }
                                    });
                                }).on('error', reject);
                            } else {
                                // Si latest-linux-deb.yml n'existe pas, utiliser la méthode standard
                                autoUpdater.checkForUpdates()
                                    .then(result => resolve({ success: true, updateInfo: result ? result.updateInfo : null }))
                                    .catch(err => reject(err));
                            }
                        } catch (err) {
                            reject(err);
                        }
                    });
                }).on('error', reject);
            });
        } else {
            // Pour AppImage, utiliser la méthode standard (latest-linux.yml pointe vers AppImage)
            const result = await autoUpdater.checkForUpdates();
            return { success: true, updateInfo: result ? result.updateInfo : null };
        }
    } catch (error) {
        console.error('Erreur vérification mise à jour:', error);
        return { success: false, error: error.message };
    }
});

// Télécharger une mise à jour
ipcMain.handle('download-update', async () => {
    try {
        const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');

        if (isAppImage) {
            // Pour AppImage, utiliser autoUpdater normalement (latest-linux.yml pointe vers AppImage)
            await autoUpdater.downloadUpdate();
            return { success: true };
        } else {
            // Pour .deb, on a déjà les infos de latest-linux-deb.yml dans checkForUpdates
            // autoUpdater va utiliser latest-linux.yml qui pointe vers AppImage
            // On doit utiliser les données de latest-linux-deb.yml
            // Pour l'instant, on utilise autoUpdater normalement et on détectera le conflit
            // Une solution complète nécessiterait de télécharger manuellement le .deb
            // mais autoUpdater gère déjà le téléchargement, donc on l'utilise
            // Le problème est qu'il cherchera latest-linux.yml qui pointe vers AppImage
            // Solution: utiliser setFeedURL pour pointer vers latest-linux-deb.yml temporairement
            // ou télécharger manuellement

            // Pour l'instant, on utilise la méthode standard
            // et on détectera le conflit dans update-available
            await autoUpdater.downloadUpdate();
            return { success: true };
        }
    } catch (error) {
        console.error('Erreur téléchargement mise à jour:', error);
        return { success: false, error: error.message };
    }
});

// Installer la mise à jour (redémarre l'application)
ipcMain.handle('install-update', () => {
    try {
        // Sur Linux, vérifier si pkexec est disponible avant d'essayer l'installation
        if (process.platform === 'linux') {
            const { execSync } = require('child_process');
            try {
                execSync('which pkexec', { stdio: 'ignore', timeout: 2000 });
            } catch (e) {
                // pkexec non disponible, retourner une erreur explicite
                return {
                    success: false,
                    error: 'Installation automatique non disponible',
                    detail: 'pkexec n\'est pas installé. Veuillez installer manuellement le package .deb téléchargé avec : sudo dpkg -i /path/to/Duplicator-*.deb'
                };
            }
        }

        // Arrêt propre puis installation avec relance forcée
        Promise.resolve()
            .then(() => stopAllChildrenGracefully())
            .then(() => {
                // isSilent=false, isForceRunAfter=true pour forcer la relance de l'app
                autoUpdater.quitAndInstall(false, true);
            }).catch(err => {
                console.error('Erreur lors de quitAndInstall:', err);
                // Si l'installation échoue, ne pas planter l'app
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

// Vérifier si l'application est lancée en administrateur
ipcMain.handle('check-admin-status', async () => {
    try {
        const isAdmin = await isRunningAsAdmin();
        return {
            success: true,
            isAdmin,
            platform: process.platform,
            user: process.env.USER || process.env.USERNAME || 'utilisateur'
        };
    } catch (error) {
        console.error('Erreur lors de la vérification admin:', error);
        return { success: false, error: error.message, isAdmin: false };
    }
});

// Redémarrer l'application en administrateur
ipcMain.handle('restart-as-admin', async () => {
    try {
        await restartAsAdmin();
        return { success: true };
    } catch (error) {
        console.error('Erreur lors du redémarrage en admin:', error);
        return { success: false, error: error.message };
    }
});

// ============ Handlers pour le moniteur d'imprimantes ============

// Obtenir la liste des imprimantes
// Obtenir la liste des imprimantes (via Electron native API)
ipcMain.handle('get-printers', async () => {
    try {
        if (!mainWindow || mainWindow.isDestroyed()) {
            return { success: false, error: 'Fenêtre principale non disponible' };
        }

        // Utiliser l'API native d'Electron qui est beaucoup plus fiable pour la liste
        const printers = await mainWindow.webContents.getPrintersAsync();

        // Normaliser les données pour correspondre à ce que l'interface attend
        // Electron retourne: { name, displayName, status, isDefault, ... }
        // Notre interface attend parfois Name/Status avec majuscule, mais on a géré ça dans le frontend.
        // On retourne l'objet tel quel, le frontend est maintenant robuste.

        return { success: true, printers: printers };
    } catch (error) {
        console.error('Erreur lors de la récupération des imprimantes via Electron:', error);

        // Fallback sur le moniteur si Electron échoue (cas rare)
        if (process.platform === 'win32' || process.platform === 'linux') {
            try {
                let monitorToUse = printerMonitor;
                if (!monitorToUse) {
                    monitorToUse = new PrinterMonitor({
                        phpApiUrl: `http://127.0.0.1:${PHP_SERVER_PORT}`
                    });
                }
                const printers = await monitorToUse.getPrinters();
                return { success: true, printers: printers };
            } catch (e) {
                return { success: false, error: error.message };
            }
        }

        return { success: false, error: error.message };
    }
});

// Démarrer/Arrêter le moniteur d'imprimantes
ipcMain.handle('toggle-printer-monitor', async (event, start) => {
    if (process.platform !== 'win32' && process.platform !== 'linux') {
        return { success: false, error: 'Disponible uniquement sur Windows/Linux' };
    }

    try {
        if (start) {
            if (!printerMonitor) {
                startPrinterMonitor();
            }
            return { success: true, status: 'started' };
        } else {
            if (printerMonitor) {
                printerMonitor.stop();
                printerMonitor = null;
            }
            return { success: true, status: 'stopped' };
        }
    } catch (error) {
        console.error('Erreur lors du changement d\'état du moniteur:', error);
        return { success: false, error: error.message };
    }
});

// Obtenir le statut du moniteur
ipcMain.handle('get-printer-monitor-status', () => {
    if (process.platform !== 'win32' && process.platform !== 'linux') {
        return { available: false, status: 'not_supported' };
    }

    return {
        available: true,
        status: printerMonitor && printerMonitor.monitoring ? 'active' : 'inactive'
    };
});

// Supprimer une imprimante Windows
ipcMain.handle('delete-printer', async (event, printerName) => {
    if (process.platform !== 'win32') {
        return { success: false, error: 'Disponible uniquement sur Windows' };
    }

    if (!printerName) {
        return { success: false, error: 'Nom d\'imprimante non fourni' };
    }

    return new Promise((resolve) => {
        // Échapper les caractères spéciaux pour PowerShell
        const escapedName = printerName.replace(/'/g, "''").replace(/"/g, '\\"');

        // Script PowerShell pour supprimer l'imprimante
        const psScript = `$printer = Get-WmiObject Win32_Printer -Filter "Name='${escapedName.replace(/'/g, "''")}'" -ErrorAction SilentlyContinue; if ($printer) { $result = $printer.Delete(); if ($result.ReturnValue -eq 0) { Write-Output "SUCCESS" } else { try { Remove-Printer -Name "${escapedName.replace(/"/g, '\\"')}" -ErrorAction Stop; Write-Output "SUCCESS" } catch { Write-Output "ERROR: $($_.Exception.Message)" } } } else { Write-Output "ERROR: Imprimante non trouvée" }`;

        const ps = spawn('powershell.exe', [
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-Command', psScript
        ], {
            stdio: ['pipe', 'pipe', 'pipe'],
            shell: false
        });

        let stdout = '';
        let stderr = '';

        ps.stdout.on('data', (data) => {
            stdout += data.toString();
        });

        ps.stderr.on('data', (data) => {
            stderr += data.toString();
        });

        ps.on('close', (code) => {
            if (code === 0 && stdout.trim() === 'SUCCESS') {
                resolve({ success: true });
            } else {
                const errorMsg = stderr || stdout || 'Erreur inconnue';
                resolve({ success: false, error: errorMsg.trim() });
            }
        });

        ps.on('error', (error) => {
            resolve({ success: false, error: error.message });
        });
    });
});


// Supprimer un job d'impression du spooler Windows
ipcMain.handle('delete-print-job', async (event, printerName, jobId) => {
    if (process.platform !== 'win32') {
        return { success: false, error: 'Disponible uniquement sur Windows' };
    }

    if (!jobId) {
        return { success: false, error: 'Paramètres manquants (jobId requis)' };
    }

    // Convertir jobId en entier si c'est une chaîne
    const id = parseInt(jobId);
    if (isNaN(id)) {
        return { success: false, error: 'ID de job invalide' };
    }

    console.log(`[IPC] delete-print-job: Suppression du job ${id}...`);

    // Fonction helper pour exécuter PowerShell
    const runPowerShell = (psScript) => {
        return new Promise((resolve) => {
            const ps = spawn('powershell.exe', [
                '-NoProfile',
                '-ExecutionPolicy', 'Bypass',
                '-Command', psScript
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
                shell: false
            });

            let stdout = '';
            let stderr = '';

            ps.stdout.on('data', (data) => stdout += data.toString());
            ps.stderr.on('data', (data) => stderr += data.toString());

            ps.on('close', (code) => {
                resolve({ code, stdout, stderr });
            });

            ps.on('error', (err) => resolve({ code: -1, stdout: '', stderr: err.message }));
        });
    };

    // Fonction pour vérifier si le job existe encore
    const jobExists = async () => {
        const checkScript = `@(Get-Printer | Get-PrintJob | Where-Object { $_.Id -eq ${id} }).Count`;
        const result = await runPowerShell(checkScript);
        const count = parseInt(result.stdout.trim()) || 0;
        console.log(`[IPC] Vérification job ${id}: count=${count}`);
        return count > 0;
    };

    // Fonction pour supprimer les fichiers SPL/SHD directement (fallback robuste)
    const deleteSpoolFiles = async () => {
        const spoolDir = 'C:\\Windows\\System32\\spool\\PRINTERS';
        // Formatage du jobId en 5 chiffres (ex: 00233, FP00233.SPL)
        const paddedId = String(id).padStart(5, '0');
        const patterns = [`FP${paddedId}.SPL`, `${paddedId}.SPL`, `FP${paddedId}.SHD`, `${paddedId}.SHD`];

        for (const pattern of patterns) {
            const filePath = path.join(spoolDir, pattern);
            try {
                if (require('fs').existsSync(filePath)) {
                    require('fs').unlinkSync(filePath);
                    console.log(`[IPC] Fichier spool supprimé: ${filePath}`);
                }
            } catch (e) {
                console.log(`[IPC] Impossible de supprimer ${filePath}: ${e.message}`);
            }
        }
    };

    try {
        const maxAttempts = 3;

        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            // Vérifier si le job existe
            const exists = await jobExists();
            if (!exists) {
                console.log(`[IPC] Job ${id} déjà supprimé (avant tentative ${attempt})`);
                return { success: true };
            }

            console.log(`[IPC] Tentative ${attempt}/${maxAttempts} de suppression du job ${id}...`);

            // ÉTAPE 1: Annuler le job d'abord (important pour les jobs "Printed" ou "Retained")
            const cancelScript = `
                Get-Printer | ForEach-Object {
                    $job = Get-PrintJob -PrinterName $_.Name -Id ${id} -ErrorAction SilentlyContinue
                    if ($job) {
                        # Essayer d'annuler le job
                        try { Set-PrintJob -InputObject $job -PrinterName $_.Name -Id ${id} -Confirm:$false -ErrorAction SilentlyContinue } catch {}
                        # Puis le supprimer
                        Remove-PrintJob -PrinterName $_.Name -Id ${id} -ErrorAction SilentlyContinue
                    }
                }
            `;
            await runPowerShell(cancelScript);

            // Attendre que Windows traite
            await new Promise(resolve => setTimeout(resolve, 500));

            // Vérifier si supprimé
            const stillExists = await jobExists();
            if (!stillExists) {
                console.log(`[IPC] Job ${id} supprimé avec succès à la tentative ${attempt}`);
                return { success: true };
            }

            console.log(`[IPC] Job ${id} existe encore après tentative ${attempt}`);
        }

        // FALLBACK: Supprimer les fichiers SPL/SHD directement du dossier spool
        console.log(`[IPC] Fallback: suppression directe des fichiers spool pour job ${id}...`);
        await deleteSpoolFiles();

        // Vérification finale
        await new Promise(resolve => setTimeout(resolve, 300));
        const finalCheck = await jobExists();
        if (!finalCheck) {
            console.log(`[IPC] Job ${id} supprimé via fallback fichiers spool`);
            return { success: true };
        }

        console.error(`[IPC] Échec: Job ${id} persiste après toutes les tentatives`);
        return { success: false, error: `Job persiste (peut nécessiter redémarrage du spooler)` };

    } catch (err) {
        console.error(`[IPC] Erreur lors de la suppression du job ${id}:`, err);
        return { success: false, error: err.message };
    }
});

// Handler pour réanalyser un job d'impression (force re-calcul fill rate, couleur, thumbnail)
ipcMain.handle('reanalyze-print-job', async (event, jobId) => {
    console.log(`[IPC] reanalyze-print-job: Réanalyse du job ${jobId}...`);

    try {
        if (!printerMonitor) {
            return { success: false, error: 'PrinterMonitor non initialisé' };
        }

        const result = await printerMonitor.reanalyzeJob(jobId);

        if (result && result.success) {
            console.log(`[IPC] Job ${jobId} réanalysé: fillRate=${result.fillRate}%, isGrayscale=${result.isGrayscale}`);
            return {
                success: true,
                isGrayscale: result.isGrayscale,
                fillRate: result.fillRate,
                thumbnailUrl: result.thumbnailUrl
            };
        } else {
            console.log(`[IPC] Réanalyse échouée pour job ${jobId}`);
            return { success: false, error: 'Analyse échouée (fichier SPL introuvable ou format non supporté)' };
        }
    } catch (err) {
        console.error(`[IPC] Erreur lors de la réanalyse du job ${jobId}:`, err);
        return { success: false, error: err.message };
    }
});

// ============ Handlers pour le module d'impression ============

// Obtenir les capacités d'une imprimante
ipcMain.handle('get-printer-capabilities', async (event, printerName) => {
    try {
        if (!printEngine.isAvailable()) {
            return { success: false, error: 'Module d\'impression non disponible sur cette plateforme' };
        }

        const capabilities = await printEngine.getPrinterCapabilities(printerName);
        return { success: true, capabilities: capabilities };
    } catch (error) {
        console.error('Erreur lors de la récupération des capacités:', error);
        return { success: false, error: error.message };
    }
});

// Cache pour stocker les options d'impression récentes (associées par nom de document)
const printOptionsCache = new Map();
const PRINT_OPTIONS_CACHE_TIMEOUT = 300000; // 5 minutes (pour les impressions longues)

// Nettoyer le cache périodiquement
setInterval(() => {
    const now = Date.now();
    for (const [key, value] of printOptionsCache.entries()) {
        if (now - value.timestamp > PRINT_OPTIONS_CACHE_TIMEOUT) {
            printOptionsCache.delete(key);
        }
    }
}, 30000); // Vérifier toutes les 30 secondes

// Fonction pour stocker les options d'impression
function storePrintOptions(pdfPath, options) {
    const fileName = path.basename(pdfPath);
    const timestamp = Date.now();

    // Extraire le nom de base du fichier (sans extension) pour meilleur matching
    const baseName = path.basename(pdfPath, path.extname(pdfPath));

    // Stocker les options avec toutes les informations disponibles
    const cacheEntry = {
        timestamp: timestamp,
        pdfPath: pdfPath,
        fileName: fileName,
        baseName: baseName,
        options: {
            ...options, // Copier toutes les options (incluant printer, fileName, etc.)
            printer: options.printer || null // S'assurer que printer est présent
        }
    };

    // Stocker avec plusieurs clés pour faciliter la recherche
    // Important: stocker avec le nom de fichier (ce que Windows connaît) comme clé principale
    printOptionsCache.set(fileName, cacheEntry);
    printOptionsCache.set(baseName, cacheEntry);
    printOptionsCache.set(pdfPath, cacheEntry);

    // Stocker aussi avec le nom normalisé (sans espaces/caractères spéciaux)
    const normalizedFileName = fileName.replace(/[^\w.-]/g, '_').toLowerCase();
    printOptionsCache.set(normalizedFileName, cacheEntry);

    console.log('📦 [PRINT_CACHE] Options stockées pour:', fileName);
    console.log('   Clés utilisées:', [fileName, baseName, normalizedFileName].join(', '));
    console.log('   Options:', JSON.stringify(cacheEntry.options, null, 2));

    // Passer au moniteur si disponible
    if (printerMonitor && printerMonitor.setPrintOptions) {
        printerMonitor.setPrintOptions(fileName, cacheEntry);
    }

    return cacheEntry;
}

// Fonction pour récupérer les options d'impression
function getPrintOptions(documentName) {
    // Essayer plusieurs clés pour trouver les options
    const keys = [
        documentName,
        path.basename(documentName),
        path.basename(documentName, path.extname(documentName))
    ];

    for (const key of keys) {
        const entry = printOptionsCache.get(key);
        if (entry && (Date.now() - entry.timestamp) < PRINT_OPTIONS_CACHE_TIMEOUT) {
            console.log('✅ [PRINT_CACHE] Options trouvées pour:', key);
            return entry;
        }
    }

    // Recherche partielle (si le nom du document contient le nom de base)
    for (const [key, entry] of printOptionsCache.entries()) {
        if (documentName.includes(entry.baseName) || entry.fileName.includes(documentName)) {
            if ((Date.now() - entry.timestamp) < PRINT_OPTIONS_CACHE_TIMEOUT) {
                console.log('✅ [PRINT_CACHE] Options trouvées (recherche partielle) pour:', key);
                return entry;
            }
        }
    }

    console.log('❌ [PRINT_CACHE] Aucune option trouvée pour:', documentName);
    return null;
}

// Lancer un job d'impression
ipcMain.handle('print-job', async (event, pdfPath, options) => {
    try {
        if (!printEngine.isAvailable()) {
            return { success: false, error: 'Module d\'impression non disponible sur cette plateforme' };
        }

        // Stocker les options dans le cache AVANT de lancer l'impression
        storePrintOptions(pdfPath, options);

        const result = await printEngine.printJob(pdfPath, options);
        return { success: true, result: result };
    } catch (error) {
        console.error('Erreur lors de l\'impression:', error);
        return { success: false, error: error.message };
    }
});

// Impression de fichiers via le moteur natif (plus fiable)
ipcMain.handle('print-file', async (event, fileUrl, printOptions) => {
    return new Promise((resolve, reject) => {
        try {
            console.log(`🖨️ [PRINT-FILE] Impression demandée pour: ${fileUrl}`);
            console.log(`🖨️ [PRINT-FILE] Options reçues:`, JSON.stringify(printOptions, null, 2));

            const http = require('http');
            const https = require('https');
            const fs = require('fs');
            const path = require('path');
            const os = require('os');

            // 1. Validation et normalization de l'URL
            let targetUrl = fileUrl;
            if (!targetUrl.startsWith('http')) {
                // Si c'est un chemin relatif ou incomplet, on suppose que c'est sur le serveur local
                if (!targetUrl.startsWith('/')) {
                    targetUrl = '/' + targetUrl;
                }
                targetUrl = 'http://127.0.0.1:8001' + targetUrl;
            }

            console.log(`URL cible normalisée: ${targetUrl}`);

            // 2. Extraire le nom de fichier des options pour le titre du job
            let displayFileName = 'document';
            if (typeof printOptions === 'object' && printOptions.fileName) {
                displayFileName = printOptions.fileName;
            }

            // Nettoyer le nom pour le système de fichiers
            const safeFileName = displayFileName.replace(/[^a-z0-9.-]/gi, '_').substring(0, 50);
            const tempFileName = safeFileName + '_' + Date.now() + '.pdf';
            const tempFilePath = path.join(os.tmpdir(), tempFileName);
            const file = fs.createWriteStream(tempFilePath);

            const protocol = targetUrl.startsWith('https') ? https : http;

            // Gestion des erreurs d'URL invalide avant la requête
            try {
                new URL(targetUrl);
            } catch (e) {
                return resolve({ success: false, error: 'URL invalide: ' + targetUrl });
            }

            protocol.get(targetUrl, (response) => {
                if (response.statusCode !== 200) {
                    fs.unlink(tempFilePath, () => { });
                    resolve({ success: false, error: `Erreur HTTP ${response.statusCode}` });
                    return;
                }

                response.pipe(file);

                file.on('finish', async () => {
                    file.close();
                    console.log('Fichier téléchargé pour impression:', tempFilePath);

                    // 2. Imprimer avec le moteur natif
                    try {
                        if (!printEngine.isAvailable()) {
                            throw new Error('Moteur d\'impression non disponible');
                        }

                        // Préparer les options - printOptions est déjà un objet
                        let options = {};
                        if (typeof printOptions === 'object' && printOptions !== null) {
                            options = { ...printOptions }; // Copie
                        } else if (typeof printOptions === 'string') {
                            // Compatibilité avec l'ancien format (juste le nom d'imprimante)
                            options = { printer: printOptions };
                        }

                        // Si aucun nom d'imprimante n'est fourni, prendre la par défaut
                        if (!options.printer) {
                            const printers = await printEngine.getPrinters();
                            const defaultPrinter = printers.find(p => p.isDefault);
                            if (defaultPrinter) {
                                options.printer = defaultPrinter.name;
                            } else if (printers.length > 0) {
                                options.printer = printers[0].name;
                            } else {
                                throw new Error('Aucune imprimante trouvée');
                            }
                        }

                        console.log(`🖨️ [PRINT-FILE] Lancement impression sur: ${options.printer}`);
                        console.log(`🖨️ [PRINT-FILE] Options finales:`, JSON.stringify({
                            copies: options.copies || 1,
                            paperSize: options.paperSize || 'A4',
                            orientation: options.orientation || 'portrait',
                            pageSubset: options.pageSubset || 'all',
                            duplex: options.duplex || 'simplex',
                            colorMode: options.colorMode || 'color'
                        }, null, 2));

                        // IMPORTANT: Stocker les options dans le cache pour le mécanisme de secours du moniteur
                        storePrintOptions(tempFilePath, options);

                        // Fire and Forget : on lance l'impression mais on rend la main tout de suite à l'UI
                        printEngine.printJob(tempFilePath, options)
                            .then(result => {
                                console.log('Succès impression arrière-plan:', result);
                                // Nettoyage du fichier temporaire après succès
                                fs.unlink(tempFilePath, () => { });
                            })
                            .catch(err => {
                                console.error('Erreur impression arrière-plan:', err);
                                // Nettoyage du fichier temporaire même en cas d'erreur
                                fs.unlink(tempFilePath, () => { });
                            });

                        // On répond immédiatement à l'interface
                        resolve({ success: true, message: 'Impression lancée en arrière-plan' });

                    } catch (printError) {
                        console.error('Erreur initialisation impression:', printError);
                        resolve({ success: false, error: printError.message });
                    }
                });
            }).on('error', (err) => {
                fs.unlink(tempFilePath, () => { });
                console.error('Erreur de téléchargement:', err.message);
                resolve({ success: false, error: err.message });
            });

        } catch (error) {
            console.error('Erreur lors de la préparation de l\'impression:', error);
            resolve({ success: false, error: error.message });
        }
    });
});

// Ouvrir un fichier avec l'application système par défaut
ipcMain.handle('open-external-file', async (event, fileUrl) => {
    try {
        console.log('Ouverture externe demandée pour:', fileUrl);

        // Si c'est une URL HTTP, on doit d'abord télécharger le fichier
        if (fileUrl.startsWith('http')) {
            const http = require('http');
            const https = require('https');
            const fs = require('fs');
            const path = require('path');
            const os = require('os');

            return new Promise((resolve, reject) => {
                const protocol = fileUrl.startsWith('https') ? https : http;

                protocol.get(fileUrl, (response) => {
                    // Extract Content-Type to compute correct extension
                    const contentType = response.headers['content-type'] || '';
                    let ext = '.pdf'; // Default fallback
                    if (contentType.includes('image/png')) ext = '.png';
                    else if (contentType.includes('image/jpeg') || contentType.includes('image/jpg')) ext = '.jpg';
                    else if (contentType.includes('image/gif')) ext = '.gif';
                    else if (contentType.includes('image/webp')) ext = '.webp';

                    // Create temporary file with accurate extension
                    const tempFileName = 'temp_' + Date.now() + ext;
                    const tempFilePath = path.join(os.tmpdir(), tempFileName);
                    const file = fs.createWriteStream(tempFilePath);

                    response.pipe(file);

                    file.on('finish', () => {
                        file.close();
                        console.log('Fichier téléchargé vers:', tempFilePath);

                        // Ouvrir le fichier avec l'application par défaut
                        shell.openPath(tempFilePath).then(error => {
                            if (error) {
                                console.error('Erreur lors de l\'ouverture:', error);
                                resolve({ success: false, error: error });
                            } else {
                                console.log('Fichier ouvert avec succès');
                                resolve({ success: true });
                            }
                        });
                    });
                }).on('error', (err) => {
                    fs.unlink(tempFilePath, () => { }); // Supprimer le fichier en cas d'erreur
                    console.error('Erreur de téléchargement:', err.message);
                    reject({ success: false, error: err.message });
                });
            });
        } else {
            // Ouvrir directement le fichier local
            const error = await shell.openPath(fileUrl);
            if (error) {
                console.error('Erreur lors de l\'ouverture:', error);
                return { success: false, error: error };
            }
            console.log('Fichier ouvert avec succès');
            return { success: true };
        }
    } catch (error) {
        console.error('Erreur lors de l\'ouverture externe:', error);
        return { success: false, error: error.message };
    }
});



// Gérer l'arrêt propre de l'application
let isStopping = false;
async function stopProcessesGracefully() {
    if (isStopping) return;
    isStopping = true;
    console.log('Arrêt de l\'application...');
    await stopProcesses();
    app.quit();
}

app.on('before-quit', (event) => {
    if (!isStopping) {
        event.preventDefault();
        stopProcessesGracefully();
    }
});

process.on('SIGINT', () => {
    stopProcessesGracefully();
});

process.on('SIGTERM', () => {
    stopProcessesGracefully();
});