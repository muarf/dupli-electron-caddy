const { app, BrowserWindow, ipcMain, shell, Menu, dialog } = require('electron');
const { autoUpdater } = require('electron-updater');
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
            if (fs.statSync(filePath).isFile()) {
                fs.unlinkSync(filePath);
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
        phpIniPath = path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'php.ini');
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
    // Variables anti-log pour PHP Dev Server
    const phpEnv = {
        ...process.env,
        PHP_CLI_SERVER_WORKERS: 1,
        PHP_CLI_SERVER_LOG_LEVEL: 0
    };

    // Solution finale Windows: Lancer PHP au travers de cmd.exe pour forcer la redirection
    // de toutes les sorties (HTTP logs incluses) vers les abysses du système (NUL),
    // car Node.js est incapable de bufferiser correctement php.exe sous cmd.
    phpServer = spawn('cmd.exe', [
        '/c',
        `"${phpPath}" -c "${phpIniPath}" -S 127.0.0.1:8000 -t "${appPath}" > NUL 2>&1`
    ], {
        env: phpEnv,
        windowsHide: true,
        stdio: 'ignore'
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
                            detail: `Version ${app.getVersion()}\n\nApplication de duplication de documents\n\nGitHub: https://github.com/votre-repo/duplicator`,
                            buttons: ['OK', 'Ouvrir GitHub'],
                            defaultId: 0,
                            cancelId: 0
                        }).then(result => {
                            if (result.response === 1) {
                                shell.openExternal('https://github.com/votre-repo/duplicator');
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
        // Icône à la racine des resources
        path.join(process.resourcesPath, 'icons', 'icon.png'),
        // Icône dans l'archive asar (souvent non résolue par les libs natives)
        path.join(process.resourcesPath, 'app.asar', 'icons', 'icon.png'),
        // Icône en développement
        path.join(__dirname, 'icons', 'icon.png'),
    ];
    const iconPath = candidateIconPaths.find(p => {
        try { return fs.existsSync(p); } catch { return false; }
    }) || candidateIconPaths[0];
    console.log('Chemin icône sélectionné:', iconPath, ' (AppImage:', !!isAppImage, ')');

    // Créer la fenêtre du navigateur
    mainWindow = new BrowserWindow({
        width: 1200,
        height: 800,
        minWidth: 800,
        minHeight: 600,
        fullscreen: true,
        fullscreenable: true,
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

    // Créer le menu personnalisé
    createMenu();

    // Configurer les mises à jour automatiques
    autoUpdater.checkForUpdatesAndNotify();

    // Gestionnaires d'événements pour les mises à jour
    autoUpdater.on('checking-for-update', () => {
        console.log('Vérification des mises à jour...');
    });

    autoUpdater.on('update-available', (info) => {
        console.log('Mise à jour disponible:', info.version);
        dialog.showMessageBox(mainWindow, {
            type: 'info',
            title: 'Mise à jour disponible',
            message: 'Une nouvelle version est disponible !',
            detail: `Version ${info.version} est maintenant disponible. Elle sera téléchargée en arrière-plan.`
        });
    });

    autoUpdater.on('update-not-available', () => {
        console.log('Aucune mise à jour disponible');
    });

    autoUpdater.on('error', (err) => {
        console.error('Erreur lors de la vérification des mises à jour:', err);
    });

    autoUpdater.on('download-progress', (progressObj) => {
        let log_message = "Téléchargement: " + progressObj.percent + '%';
        console.log(log_message);
    });

    autoUpdater.on('update-downloaded', (info) => {
        console.log('Mise à jour téléchargée:', info.version);
        dialog.showMessageBox(mainWindow, {
            type: 'info',
            title: 'Mise à jour prête',
            message: 'La mise à jour a été téléchargée',
            detail: `Version ${info.version} est prête à être installée. L'application va redémarrer.`,
            buttons: ['Redémarrer maintenant'],
            defaultId: 0
        }).then(() => {
            autoUpdater.quitAndInstall();
        });
    });

    // Démarrer le serveur PHP
    startPhpServer();

    // Attendre que le serveur soit prêt puis charger l'application
    setTimeout(() => {
        mainWindow.loadURL('http://127.0.0.1:8000/');
        mainWindow.show();
    }, 2000);

    // Gestion des erreurs de chargement
    mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL) => {
        console.error('Erreur de chargement:', errorCode, errorDescription);
        mainWindow.loadURL('data:text/html,<h1>Erreur de connexion</h1><p>Impossible de se connecter au serveur PHP.</p><button onclick="location.reload()">Recharger</button>');
    });

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

    // Arrêter le serveur PHP
    stopPhpServer();

    // Sur macOS, il est courant pour les applications et leur barre de menu
    // de rester actives jusqu'à ce que l'utilisateur quitte explicitement avec Cmd + Q
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

// S'assurer d'arrêter proprement les processus avant de quitter (mises à jour incluses)
app.on('before-quit', () => {
    try {
        stopPhpServer();
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

// Impression de fichiers avec boîte de dialogue système
ipcMain.handle('print-file', async (event, fileUrl) => {
    return new Promise((resolve, reject) => {
        try {
            console.log('Impression demandée pour:', fileUrl);

            // Créer une fenêtre invisible pour charger le fichier
            const printWindow = new BrowserWindow({
                show: false,
                webPreferences: {
                    nodeIntegration: false,
                    contextIsolation: true,
                    sandbox: true
                }
            });

            // Charger le fichier
            printWindow.loadURL(fileUrl);

            // Attendre que le contenu soit chargé
            printWindow.webContents.on('did-finish-load', () => {
                console.log('Fichier chargé, ouverture de la boîte de dialogue d\'impression');

                // Ouvrir la boîte de dialogue d'impression système
                printWindow.webContents.print({
                    silent: false,  // Afficher la boîte de dialogue
                    printBackground: true,
                    color: true,
                    margins: {
                        marginType: 'printableArea'
                    }
                }, (success, errorType) => {
                    // Fermer la fenêtre après l'impression
                    printWindow.close();

                    if (success) {
                        console.log('Impression réussie ou annulée par l\'utilisateur');
                        resolve({ success: true });
                    } else {
                        console.error('Erreur d\'impression:', errorType);
                        resolve({ success: false, error: errorType });
                    }
                });
            });

            // Gérer les erreurs de chargement
            printWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
                console.error('Erreur de chargement du fichier:', errorDescription);
                printWindow.close();
                reject({ success: false, error: errorDescription });
            });

        } catch (error) {
            console.error('Erreur lors de la préparation de l\'impression:', error);
            reject({ success: false, error: error.message });
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
            const os = require('os');

            return new Promise((resolve, reject) => {
                // Créer un nom de fichier temporaire
                const tempFileName = 'temp_' + Date.now() + '.pdf';
                const tempFilePath = path.join(os.tmpdir(), tempFileName);
                const file = fs.createWriteStream(tempFilePath);

                const protocol = fileUrl.startsWith('https') ? https : http;

                protocol.get(fileUrl, (response) => {
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