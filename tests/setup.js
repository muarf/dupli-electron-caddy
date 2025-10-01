// Configuration globale des tests
const path = require('path');

// Configuration des chemins pour les tests
global.testPaths = {
    app: path.join(__dirname, '..', 'app'),
    caddy: path.join(__dirname, '..', 'caddy'),
    scripts: path.join(__dirname, '..', 'scripts'),
    config: path.join(__dirname, '..', 'Caddyfile')
};

// Configuration des ports de test
global.testPorts = {
    caddy: 8001,
    php: 8002
};

// Configuration des timeouts
jest.setTimeout(30000);

// Mock des modules Electron pour les tests unitaires
jest.mock('electron', () => ({
    app: {
        disableHardwareAcceleration: jest.fn(),
        whenReady: jest.fn(() => Promise.resolve()),
        on: jest.fn(),
        quit: jest.fn()
    },
    BrowserWindow: jest.fn(() => ({
        loadURL: jest.fn(),
        loadFile: jest.fn(),
        show: jest.fn(),
        webContents: {
            openDevTools: jest.fn()
        }
    })),
    ipcMain: {
        handle: jest.fn()
    },
    shell: {
        openPath: jest.fn(() => Promise.resolve())
    }
}), { virtual: true });

// Mock du module child_process
jest.mock('child_process', () => ({
    spawn: jest.fn(() => ({
        stdout: {
            on: jest.fn()
        },
        stderr: {
            on: jest.fn()
        },
        on: jest.fn(),
        kill: jest.fn()
    }))
}));

// Mock du module fs
jest.mock('fs', () => ({
    existsSync: jest.fn(),
    mkdirSync: jest.fn(),
    readdirSync: jest.fn(() => []),
    statSync: jest.fn(() => ({ isFile: () => true })),
    unlinkSync: jest.fn(),
    chmodSync: jest.fn(),
    createWriteStream: jest.fn(() => ({
        on: jest.fn(),
        close: jest.fn(),
        write: jest.fn()
    }))
}));

// Fonction utilitaire pour créer des fichiers temporaires de test
global.createTestFile = (content) => {
    const fs = require('fs');
    const path = require('path');
    const tempDir = path.join(__dirname, 'temp');
    
    if (!fs.existsSync(tempDir)) {
        fs.mkdirSync(tempDir, { recursive: true });
    }
    
    const filePath = path.join(tempDir, `test-${Date.now()}.tmp`);
    fs.writeFileSync(filePath, content);
    return filePath;
};

// Fonction utilitaire pour nettoyer les fichiers de test
global.cleanupTestFiles = () => {
    const fs = require('fs');
    const path = require('path');
    const tempDir = path.join(__dirname, 'temp');
    
    if (fs.existsSync(tempDir)) {
        const files = fs.readdirSync(tempDir);
        files.forEach(file => {
            const filePath = path.join(tempDir, file);
            if (fs.statSync(filePath).isFile()) {
                fs.unlinkSync(filePath);
            }
        });
    }
};

// Nettoyer les fichiers de test après chaque test
afterEach(() => {
    global.cleanupTestFiles();
});
