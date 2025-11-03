const { app, BrowserWindow, ipcMain, shell, Menu } = require('electron');
const { autoUpdater } = require('electron-updater');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { checkWindowsCompatibility, applyCompatibilitySettings } = require('./utils/windows-compatibility');

// Vérifier la compatibilité Windows avant tout
checkWindowsCompatibility();

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
    } else {
        // Linux/macOS : utiliser le PHP système
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
    
    // Créer le répertoire de sessions s'il n'existe pas (cross-platform)
    const sessionPath = path.join(os.tmpdir(), 'duplicator_sessions');
    console.log('Session Path:', sessionPath);
    if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
    }
    
    // Préparer les arguments PHP selon la plateforme
    let phpArgs;
    
    if (isAppImage) {
        // AppImage Linux : utiliser PHP système sans php.ini personnalisé
        // Le PHP système a déjà ses extensions configurées
        console.log('Configuration PHP pour AppImage (PHP système)');
        phpArgs = [
            '-S', '127.0.0.1:8001',
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
            '-S', '127.0.0.1:8001',
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
                '-S', '127.0.0.1:8001',
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
                '-S', '127.0.0.1:8001',
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
    
    console.log('=== Démarrage PHP ===');
    console.log('Commande:', phpPath);
    console.log('Arguments:', phpArgs.join(' '));
    console.log('Environnement DB_PATH:', getDatabasePath());
    
    phpFpmProcess = spawn(phpPath, phpArgs, {
        stdio: ['pipe', 'pipe', 'pipe'],
        env: {
            ...process.env,
            DUPLICATOR_DB_PATH: getDatabasePath()
        }
    });
    
    phpFpmProcess.stdout.on('data', (data) => {
        const output = data.toString();
        console.log('📥 PHP stdout:', output.trim());
        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_php.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    phpFpmProcess.stderr.on('data', (data) => {
        const output = data.toString();
        console.error('❌ PHP stderr:', output.trim());

        // Envoyer les erreurs PHP à la console Electron pour visibilité
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.executeJavaScript(`
                console.error('[PHP ERROR]', ${JSON.stringify(output.trim())});
            `).catch(() => {
                // Ignore les erreurs d'exécution JavaScript
            });
        }

        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_php_errors.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    phpFpmProcess.on('close', (code) => {
        console.log(`⚠️ Serveur PHP fermé avec le code ${code}`);
    });
    
    phpFpmProcess.on('error', (error) => {
        console.error('❌ Erreur serveur PHP:', error.message);
        console.error('   Code:', error.code);
        console.error('   Stack:', error.stack);
    });
    
    // Vérifier si le processus démarre correctement
    setTimeout(() => {
        if (phpFpmProcess && phpFpmProcess.pid) {
            console.log('✅ PHP démarré avec PID:', phpFpmProcess.pid);
        } else {
            console.error('❌ PHP n\'a pas démarré - pas de PID');
        }
    }, 1000);
    
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
    
    // Pas de php.ini pour éviter les erreurs d'extensions
    phpFpmProcess = spawn(phpPath, [
        '-S', '127.0.0.1:8001',
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
    
    console.log('=== Démarrage PHP (fallback) ===');
    console.log('Commande:', phpPath);
    console.log('Arguments:', '-S 127.0.0.1:8001 -t', appPath);
    
    phpFpmProcess.stdout.on('data', (data) => {
        const output = data.toString();
        console.log('📥 PHP stdout (fallback):', output.trim());
        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_php.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    phpFpmProcess.stderr.on('data', (data) => {
        const output = data.toString();
        console.error('❌ PHP stderr (fallback):', output.trim());
        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_php_errors.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    phpFpmProcess.on('close', (code) => {
        console.log(`⚠️ Serveur PHP (fallback) fermé avec le code ${code}`);
    });
    
    phpFpmProcess.on('error', (error) => {
        console.error('❌ Erreur serveur PHP (fallback):', error.message);
        console.error('   Code:', error.code);
        console.error('   Stack:', error.stack);
    });
    
    // Vérifier si le processus démarre correctement
    setTimeout(() => {
        if (phpFpmProcess && phpFpmProcess.pid) {
            console.log('✅ PHP (fallback) démarré avec PID:', phpFpmProcess.pid);
        } else {
            console.error('❌ PHP (fallback) n\'a pas démarré - pas de PID');
        }
    }, 1000);
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
    
    console.log('=== Démarrage Caddy ===');
    console.log('Commande:', caddyPath);
    console.log('Arguments: run --config', caddyfile, '--adapter caddyfile');
    console.log('Environnement CADDY_ROOT:', appPath);
    
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
        const output = data.toString();
        console.log('📥 Caddy stdout:', output.trim());
        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_caddy.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    caddyProcess.stderr.on('data', (data) => {
        const output = data.toString();
        console.error('❌ Caddy stderr:', output.trim());

        // Envoyer les erreurs Caddy à la console Electron pour visibilité
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.executeJavaScript(`
                console.error('[CADDY ERROR]', ${JSON.stringify(output.trim())});
            `).catch(() => {
                // Ignore les erreurs d'exécution JavaScript
            });
        }

        // Logger aussi dans un fichier pour debug
        if (process.platform === 'win32') {
            const logFile = path.join(os.tmpdir(), 'duplicator_caddy_errors.log');
            fs.appendFileSync(logFile, `[${new Date().toISOString()}] ${output}`);
        }
    });
    
    caddyProcess.on('close', (code) => {
        console.log(`⚠️ Caddy fermé avec le code ${code}`);
    });
    
    caddyProcess.on('error', (error) => {
        console.error('❌ Erreur Caddy:', error.message);
        console.error('   Code:', error.code);
        console.error('   Stack:', error.stack);
    });
    
    // Vérifier si le processus démarre correctement
    setTimeout(() => {
        if (caddyProcess && caddyProcess.pid) {
            console.log('✅ Caddy démarré avec PID:', caddyProcess.pid);
        } else {
            console.error('❌ Caddy n\'a pas démarré - pas de PID');
        }
    }, 1000);
    
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

// Créer le menu avec la version
function createMenu() {
    const template = [
        {
            label: 'Aide',
            submenu: [
                {
                    label: 'À propos de Duplicator',
                    click: () => {
                        const { dialog } = require('electron');
                        dialog.showMessageBox(mainWindow, {
                            type: 'info',
                            title: 'À propos de Duplicator',
                            message: 'Duplicator',
                            detail: `Version ${app.getVersion()}\n\nApplication de duplication de documents\n\n© Collectif Duplicator`
                        });
                    }
                },
                {
                    label: 'Vérifier les mises à jour',
                    click: async () => {
                        const { dialog } = require('electron');
                        try {
                            dialog.showMessageBox(mainWindow, {
                                type: 'info',
                                title: 'Vérification des mises à jour',
                                message: 'Vérification en cours...',
                                detail: 'Recherche de mises à jour disponibles...'
                            });

                            const result = await autoUpdater.checkForUpdates();

                            if (result && result.updateInfo) {
                                const updateInfo = result.updateInfo;
                                const currentVersion = app.getVersion();

                                if (updateInfo.version !== currentVersion) {
                                    const choice = await dialog.showMessageBox(mainWindow, {
                                        type: 'question',
                                        buttons: ['Télécharger', 'Plus tard'],
                                        defaultId: 0,
                                        title: 'Mise à jour disponible',
                                        message: `Version ${updateInfo.version} disponible`,
                                        detail: `Vous utilisez la version ${currentVersion}.\nVoulez-vous télécharger la mise à jour ?`
                                    });

                                    if (choice.response === 0) {
                                        autoUpdater.downloadUpdate();
                                        dialog.showMessageBox(mainWindow, {
                                            type: 'info',
                                            title: 'Téléchargement',
                                            message: 'Téléchargement en cours...',
                                            detail: 'Vous serez notifié quand le téléchargement sera terminé.'
                                        });
                                    }
                                } else {
                                    dialog.showMessageBox(mainWindow, {
                                        type: 'info',
                                        title: 'À jour',
                                        message: 'Vous utilisez la dernière version',
                                        detail: `Version ${currentVersion}`
                                    });
                                }
                            } else {
                                dialog.showMessageBox(mainWindow, {
                                    type: 'info',
                                    title: 'Vérification terminée',
                                    message: 'Impossible de vérifier les mises à jour',
                                    detail: 'Vérifiez votre connexion internet ou réessayez plus tard.'
                                });
                            }
                        } catch (error) {
                            console.error('Erreur vérification mise à jour:', error);
                            dialog.showMessageBox(mainWindow, {
                                type: 'error',
                                title: 'Erreur',
                                message: 'Erreur lors de la vérification',
                                detail: error.message
                            });
                        }
                    }
                },
                { type: 'separator' },
                {
                    label: 'Visiter le site GitHub',
                    click: () => {
                        shell.openExternal('https://github.com/muarf/dupli-electron-caddy');
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
        }
    ];
    
    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
}

function createWindow() {
    // Nettoyer les fichiers temporaires au démarrage
    cleanupTmpFiles();
    
    // Déterminer le chemin du preload selon la plateforme
    let preloadPath;
    if (process.platform === 'win32') {
        // Windows : vérifier plusieurs chemins possibles
        const possiblePaths = [
            path.join(process.resourcesPath, 'app', 'preload.js'),
            path.join(process.resourcesPath, 'preload.js'),
            path.join(__dirname, 'preload.js')
        ];
        
        for (const p of possiblePaths) {
            if (fs.existsSync(p)) {
                preloadPath = p;
                console.log('Preload trouvé:', preloadPath);
                break;
            }
        }
        
        if (!preloadPath) {
            console.error('⚠️ Preload.js non trouvé, utilisation du chemin par défaut');
            preloadPath = path.join(__dirname, 'preload.js');
        }
    } else {
        preloadPath = path.join(__dirname, 'preload.js');
    }
    
    console.log('Chemin preload utilisé:', preloadPath);
    console.log('Preload existe:', fs.existsSync(preloadPath));
    
    // Créer la fenêtre du navigateur
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        minWidth: 800,
        minHeight: 600,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: preloadPath,
            sandbox: false,
            offscreen: false,
            // Désactiver la politique de sécurité de contenu pour debug
            webSecurity: false, // TEMPORAIRE pour debug - à réactiver après
            allowRunningInsecureContent: true
        },
        show: false,
        autoHideMenuBar: true  // Cacher le menu par défaut mais accessible avec Alt
    });
    
    // Ouvrir les DevTools en développement uniquement
    if (process.env.NODE_ENV === 'development') {
        mainWindow.webContents.openDevTools();
    }
    
    // Maximiser la fenêtre au démarrage
    mainWindow.maximize();
    
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
            
            // Attendre un peu pour que Caddy soit bien prêt
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Charger l'application
            console.log('Chargement de http://127.0.0.1:8000/');
            mainWindow.loadURL('http://127.0.0.1:8000/');
            mainWindow.show();
            
            console.log('Serveurs démarrés avec succès');
            
            // Ajouter des listeners de debug pour comprendre les erreurs
            mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame) => {
                console.error('❌ Erreur de chargement page principale:', errorCode, errorDescription, validatedURL);
                if (isMainFrame) {
                    console.error('Page principale en échec, tentative fallback...');
                    setTimeout(() => {
                        mainWindow.loadURL('http://127.0.0.1:8001/');
                    }, 2000);
                }
            });
            
            // Debug pour voir le contenu de la page chargée
            mainWindow.webContents.on('did-finish-load', async () => {
                const url = mainWindow.webContents.getURL();
                console.log('✅ Page chargée avec succès:', url);
                
                // Attendre un peu pour que le DOM soit stable
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Capturer le title de la page
                const title = await mainWindow.webContents.executeJavaScript('document.title').catch(() => 'ERREUR TITLE');
                console.log('Title de la page:', title);
                
                // Vérifier si le body existe et a du contenu
                const bodyInfo = await mainWindow.webContents.executeJavaScript(`
                    (() => {
                        if (!document.body) return {exists: false, length: 0, html: 'NO BODY'};
                        const html = document.body.innerHTML || '';
                        return {
                            exists: true,
                            length: html.length,
                            html: html.substring(0, 300)
                        };
                    })()
                `).catch(() => ({exists: false, length: 0, html: 'ERREUR BODY'}));
                
                console.log('Body existe:', bodyInfo.exists, '| Longueur HTML:', bodyInfo.length);
                if (bodyInfo.length > 0) {
                    console.log('Début du body HTML:', bodyInfo.html);
                } else {
                    console.error('⚠️ Body HTML VIDE !');
                }
                
                // Vérifier les erreurs réseau
                const errors = await mainWindow.webContents.executeJavaScript(`
                    (() => {
                        const resources = performance.getEntriesByType('resource');
                        const failed = resources.filter(r => !r.fromCache && (r.transferSize === 0 || r.decodedBodySize === 0));
                        return {
                            failed: failed.map(r => ({name: r.name, status: r.responseStatus, size: r.transferSize})),
                            all: resources.map(r => ({name: r.name, status: r.responseStatus || 'OK', size: r.transferSize}))
                        };
                    })()
                `).catch(() => ({failed: [], all: []}));
                
                if (errors.failed.length > 0) {
                    console.error('Ressources en échec:', JSON.stringify(errors.failed, null, 2));
                }
                
                // Vérifier les erreurs JavaScript
                const jsErrors = await mainWindow.webContents.executeJavaScript(`
                    (() => {
                        const errors = [];
                        if (window.onerror) {
                            window.addEventListener('error', (e) => {
                                errors.push({message: e.message, filename: e.filename, lineno: e.lineno});
                            });
                        }
                        return errors;
                    })()
                `).catch(() => []);
                
                if (jsErrors.length > 0) {
                    console.error('Erreurs JavaScript:', JSON.stringify(jsErrors, null, 2));
                }
                
                // Capturer le HTML source brut
                try {
                    const source = await mainWindow.webContents.executeJavaScript('document.documentElement.outerHTML').catch(() => '');
                    const sourceLength = source ? source.length : 0;
                    console.log('📄 HTML source brut longueur:', sourceLength);
                    if (sourceLength > 0 && sourceLength < 500) {
                        console.log('📄 HTML source (premiers 500 chars):', source.substring(0, 500));
                    } else if (sourceLength === 0) {
                        console.error('⚠️ HTML source complètement vide !');
                    }
                } catch (err) {
                    console.error('Erreur capture HTML source:', err);
                }
            });
            
            mainWindow.webContents.on('did-start-loading', () => {
                console.log('🔄 Début de chargement');
                
                // Injecter le script de debug AVANT que la page charge son JavaScript
                mainWindow.webContents.executeJavaScript(`
                    (function() {
                        // Capturer le HTML dès DOMContentLoaded (AVANT tout autre script)
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', function() {
                                const htmlBefore = document.body ? document.body.innerHTML : '';
                                const htmlLength = htmlBefore.length;
                                window.__htmlBefore = htmlBefore;
                                window.__htmlLength = htmlLength;
                                console.log('[DEBUG] HTML capturé au DOMContentLoaded - Longueur:', htmlLength);
                                if (htmlLength > 0) {
                                    console.log('[DEBUG] Début HTML:', htmlBefore.substring(0, 300));
                                } else {
                                    console.error('[DEBUG] ⚠️ HTML déjà vide au DOMContentLoaded !');
                                }
                            }, {once: true, capture: true});
                        }
                        
                        // Observer les mutations du body pour détecter quand il est vidé
                        function setupObserver() {
                            if (document.body) {
                                const observer = new MutationObserver(function(mutations) {
                                    mutations.forEach(function(mutation) {
                                        if (mutation.type === 'childList') {
                                            const currentLength = document.body.innerHTML.length;
                                            if (currentLength === 0 && window.__htmlLength > 0) {
                                                console.error('[DEBUG] ⚠️ BODY VIDÉ !');
                                                console.error('[DEBUG] HTML avant (premiers 500 chars):', window.__htmlBefore ? window.__htmlBefore.substring(0, 500) : 'N/A');
                                                // Essayer de capturer la stack trace
                                                try {
                                                    throw new Error('Body vidé');
                                                } catch (e) {
                                                    console.error('[DEBUG] Stack:', e.stack);
                                                }
                                            }
                                        }
                                    });
                                });
                                observer.observe(document.body, { childList: true, subtree: true });
                                console.log('[DEBUG] MutationObserver installé sur body');
                            } else {
                                // Retry si body n'existe pas encore
                                setTimeout(setupObserver, 100);
                            }
                        }
                        
                        if (document.body) {
                            setupObserver();
                        } else {
                            // Si le body n'existe pas encore, attendre
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', setupObserver);
                            } else {
                                setTimeout(setupObserver, 100);
                            }
                        }
                    })();
                `).catch(err => console.error('Erreur injection script debug:', err));
            });
            
            // Debug pour les ressources bloquées
            mainWindow.webContents.on('console-message', (event, level, message, line, sourceId) => {
                if (level >= 2) { // warning ou error
                    const prefix = level === 3 ? '❌ Console ERROR' : '⚠️ Console WARNING';
                    console.log(`${prefix}:`, message, `[${sourceId}:${line}]`);
                }
            });
            
            // Debug pour voir ce qui est bloqué par la politique de sécurité
            mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame, frameProcessId, frameRoutingId) => {
                console.error('❌ did-fail-load:', {
                    errorCode,
                    errorDescription,
                    validatedURL,
                    isMainFrame
                });
            });
            
            // Debug pour les requêtes réseau
            mainWindow.webContents.session.webRequest.onBeforeRequest((details, callback) => {
                if (details.resourceType === 'mainFrame') {
                    console.log('📥 Requête principale:', details.url);
                }
                callback({});
            });
            
            // Intercepter les réponses pour voir les headers
            mainWindow.webContents.session.webRequest.onHeadersReceived((details, callback) => {
                if (details.resourceType === 'mainFrame') {
                    console.log('📥 Headers reçus pour:', details.url);
                    console.log('   Status:', details.statusCode, details.statusLine);
                    console.log('   Content-Type:', details.responseHeaders['content-type'] || details.responseHeaders['Content-Type']);
                    console.log('   Tous headers:', JSON.stringify(details.responseHeaders, null, 2));
                }
                callback({});
            });
            
            // Le listener did-finish-load est déjà défini plus haut, on n'en ajoute pas un deuxième
        } catch (error) {
            console.error('Erreur lors du démarrage des serveurs:', error);
            console.error('Stack:', error.stack);
            // Fallback : utiliser le serveur PHP intégré uniquement
            console.log('Tentative de démarrage avec le serveur PHP intégré uniquement...');
            try {
                startPhpServer();
                console.log('Chargement fallback vers http://127.0.0.1:8001/');
                mainWindow.loadURL('http://127.0.0.1:8001/');
                mainWindow.show();
                console.log('Serveur PHP intégré démarré avec succès');
            } catch (fallbackError) {
                console.error('Erreur serveur PHP intégré:', fallbackError);
                console.error('Stack:', fallbackError.stack);
                // Afficher une page d'erreur
                mainWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>Erreur de démarrage</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
                            h1 { color: #e53e3e; }
                            p { color: #4a5568; }
                            code { background: #2d3748; color: #a0ff00; padding: 10px; display: block; margin: 10px 0; }
                        </style>
                    </head>
                    <body>
                        <h1>❌ Erreur de démarrage</h1>
                        <p>Impossible de démarrer les serveurs nécessaires.</p>
                        <code>${error.message}</code>
                        <p>Veuillez vérifier les logs dans la console.</p>
                    </body>
                    </html>
                `));
                mainWindow.show();
            }
        }
    }
    
    startServers();
    
    // Créer le menu avec la version
    createMenu();
    
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
    
    console.log('🔧 Configuration autoUpdater:');
    console.log('  - Version actuelle:', app.getVersion());
    console.log('  - AutoDownload:', autoUpdater.autoDownload);
    console.log('  - AutoInstallOnAppQuit:', autoUpdater.autoInstallOnAppQuit);
    console.log('  - Channel:', autoUpdater.channel || 'default');
    
    // Événements de mise à jour
    autoUpdater.on('checking-for-update', () => {
        console.log('🔄 Vérification des mises à jour...');
    });
    
    autoUpdater.on('update-available', (info) => {
        console.log('✅ Mise à jour disponible:', info.version);
        console.log('   Détails:', JSON.stringify(info, null, 2));
        
        // Envoyer une notification à l'interface
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-available', info);
        }
    });
    
    autoUpdater.on('update-not-available', (info) => {
        console.log('ℹ️  Aucune mise à jour disponible');
        console.log('   Version demandée:', info.version || 'N/A');
        console.log('   Version actuelle:', app.getVersion());
        
        if (mainWindow && mainWindow.webContents) {
            mainWindow.webContents.send('update-not-available', info);
        }
    });
    
    autoUpdater.on('error', (err) => {
        // Détecter si c'est une erreur critique ou juste informatif
        const isNetworkError = err.message && (
            err.message.includes('net::') ||
            err.message.includes('ENOTFOUND') ||
            err.message.includes('ECONNREFUSED') ||
            err.message.includes('ETIMEDOUT') ||
            err.message.includes('Cannot find') ||
            err.message.includes('404')
        );
        
        if (!isNetworkError) {
            // Erreur critique - afficher dans la console ET dans l'interface
            console.error('❌ ERREUR CRITIQUE lors de la mise à jour:', err);
            console.error('   Message:', err.message);
            console.error('   Stack:', err.stack);
            
            if (mainWindow && mainWindow.webContents) {
                mainWindow.webContents.send('update-error', err);
            }
        } else {
            // Erreur non-critique (réseau/release inexistant) - juste dans la console
            console.log('⚠️  Vérification des mises à jour ignorée (pas de connexion internet ou release non disponible)');
            console.log('   Raison:', err.message);
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
        console.log('🚀 Lancement de la vérification des mises à jour initiale...');
        autoUpdater.checkForUpdates().then(result => {
            console.log('✅ checkForUpdates terminé:', result);
        }).catch(err => {
            // Erreur silencieuse si pas de connexion
            if (err.message && (err.message.includes('net::') || err.message.includes('ENOTFOUND'))) {
                console.log('⚠️  Pas de connexion internet, vérification des mises à jour ignorée');
            } else {
                console.error('❌ Erreur vérification mise à jour:', err.message);
                console.error('   Stack:', err.stack);
            }
        });
    }, 10000);
    
    // Vérifier toutes les 4 heures
    setInterval(() => {
        console.log('🔄 Vérification périodique des mises à jour...');
        autoUpdater.checkForUpdates().catch(err => {
            // Erreur silencieuse si pas de connexion
            if (err.message && (err.message.includes('net::') || err.message.includes('ENOTFOUND'))) {
                console.log('⚠️  Pas de connexion internet, vérification des mises à jour ignorée');
            } else {
                console.error('❌ Erreur vérification mise à jour:', err.message);
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
    console.log('🔍 IPC check-for-updates appelé manuellement');
    try {
        const result = await autoUpdater.checkForUpdates();
        console.log('✅ IPC check-for-updates résultat:', result);
        return { success: true, updateInfo: result ? result.updateInfo : null };
    } catch (error) {
        console.error('❌ IPC Erreur vérification mise à jour:', error);
        console.error('   Message:', error.message);
        console.error('   Stack:', error.stack);
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
