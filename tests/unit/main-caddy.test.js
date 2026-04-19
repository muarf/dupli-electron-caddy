// Mock electron before anything else
jest.mock('electron', () => ({
    app: {
        disableHardwareAcceleration: jest.fn(),
        whenReady: jest.fn(() => Promise.resolve()),
        on: jest.fn(),
        quit: jest.fn(),
        isPackaged: false,
        getPath: jest.fn((name) => {
            if (name === 'userData') return '/mock/user/data';
            if (name === 'temp') return '/mock/temp';
            if (name === 'appData') return '/mock/app/data';
            return `/mock/${name}`;
        }),
        getName: jest.fn(() => 'dupli-electron-beta'),
        getAppPath: jest.fn(() => '/mock/app/path'),
        setName: jest.fn(),
        isReady: jest.fn(() => true),
        commandLine: {
            appendSwitch: jest.fn()
        }
    },
    BrowserWindow: jest.fn(() => ({
        loadURL: jest.fn().mockResolvedValue(true),
        loadFile: jest.fn().mockResolvedValue(true),
        show: jest.fn(),
        on: jest.fn(),
        setTitle: jest.fn(),
        maximize: jest.fn(),
        hide: jest.fn(),
        isDestroyed: jest.fn(() => false),
        webContents: {
            openDevTools: jest.fn(),
            send: jest.fn(),
            on: jest.fn()
        }
    })),
    ipcMain: {
        handle: jest.fn(),
        on: jest.fn()
    },
    shell: {
        openPath: jest.fn(() => Promise.resolve())
    },
    Menu: {
        setApplicationMenu: jest.fn(),
        buildFromTemplate: jest.fn()
    },
    dialog: {
        showMessageBox: jest.fn().mockResolvedValue({ response: 0 }),
        showErrorBox: jest.fn()
    },
    screen: {
        getPrimaryDisplay: jest.fn(() => ({ workAreaSize: { width: 1024, height: 768 } }))
    }
}), { virtual: true });

const path = require('path');
const fs = require('fs');

jest.mock('child_process', () => ({
    spawn: jest.fn(() => ({
        stdout: { on: jest.fn() },
        stderr: { on: jest.fn() },
        on: jest.fn(),
        kill: jest.fn()
    })),
    exec: jest.fn()
}));

// Mock fs to control existence checks
jest.mock('fs', () => ({
    ...jest.requireActual('fs'),
    existsSync: jest.fn(),
    mkdirSync: jest.fn(),
    readdirSync: jest.fn(),
    statSync: jest.fn(),
    unlinkSync: jest.fn(),
    copyFileSync: jest.fn(),
    watch: jest.fn(() => ({ close: jest.fn(), on: jest.fn() })),
    readFileSync: jest.fn(),
    writeFileSync: jest.fn()
}));

// Set NODE_ENV to test so main-caddy exports its functions
process.env.NODE_ENV = 'test';
process.resourcesPath = '/mock/resources';

describe('main-caddy.js Unit Tests', () => {
    let mainCaddy;

    beforeAll(() => {
        // Redéfinir process.platform si nécessaire pour certains tests
        // Mais ici on commence par charger le module
        mainCaddy = require('../../main-caddy');
    });

    beforeEach(() => {
        jest.clearAllMocks();
        // Default fs.existsSync behavior
        fs.existsSync.mockReturnValue(true);
        fs.statSync.mockReturnValue({ isFile: () => true, isDirectory: () => true, size: 100 });
        fs.readdirSync.mockReturnValue([]);
    });

    describe('getPath logic', () => {
        test('getCaddyPath should return local path in dev linux', () => {
            Object.defineProperty(process, 'platform', { value: 'linux' });
            process.env.APPIMAGE = undefined;
            const { app } = require('electron');
            app.isPackaged = false;

            const caddyPath = mainCaddy.getCaddyPath();
            expect(caddyPath).toContain(path.join('caddy', 'caddy'));
        });

        test('getPhpPath should return "php" on linux', () => {
            Object.defineProperty(process, 'platform', { value: 'linux' });
            const phpPath = mainCaddy.getPhpPath();
            expect(phpPath).toBe('php');
        });

        test('getPhpPath should return .exe on Windows', () => {
            Object.defineProperty(process, 'platform', { value: 'win32' });
            const { app } = require('electron');
            app.isPackaged = false;
            
            const phpPath = mainCaddy.getPhpPath();
            expect(phpPath).toContain('php.exe');
        });
    });

    describe('Environment detection', () => {
        test('getCaddyfilePath returns local Caddyfile in dev', () => {
            const path = mainCaddy.getCaddyfilePath();
            expect(path).toContain('Caddyfile');
        });
    });

    describe('cleanupTmpFiles', () => {
        test('should call unlinkSync for files in tmp directory', () => {
            fs.readdirSync.mockReturnValue(['test1.tmp', 'test2.tmp']);
            fs.statSync.mockReturnValue({ isFile: () => true });
            
            mainCaddy.cleanupTmpFiles();
            
            expect(fs.unlinkSync).toHaveBeenCalledTimes(2);
        });
    });

    describe('Secure Purge logic', () => {
        let http;
        
        beforeEach(() => {
            http = require('http');
            jest.mock('http', () => ({
                request: jest.fn(() => ({
                    on: jest.fn(),
                    end: jest.fn()
                }))
            }));
        });

        test('scheduleSecurePurge triggers http request to /?secure_purge', () => {
            const http = require('http');
            const mockReq = { 
                on: jest.fn().mockReturnThis(), 
                end: jest.fn() 
            };
            http.request.mockReturnValue(mockReq);

            // Access internal function (exported for test)
            mainCaddy.scheduleSecurePurge();
            
            // The triggerPurge inside scheduleSecurePurge is wrapped in a setTimeout (line 59)
            // We need to run timers
            jest.useFakeTimers();
            mainCaddy.scheduleSecurePurge();
            jest.advanceTimersByTime(11000);
            
            expect(http.request).toHaveBeenCalledWith(
                expect.objectContaining({ path: '/?secure_purge' }),
                expect.any(Function)
            );
            jest.useRealTimers();
        });
    });
});
