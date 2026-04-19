Object.defineProperty(process, 'resourcesPath', {
    value: '/mock/resources',
    writable: true
});

const mockApp = {
    disableHardwareAcceleration: jest.fn(),
    whenReady: jest.fn(() => Promise.resolve()),
    on: jest.fn(),
    quit: jest.fn(),
    isPackaged: false,
    getPath: jest.fn(),
    getName: jest.fn().mockReturnValue('Duplicator Beta'),
    getAppPath: jest.fn().mockReturnValue('/mock/app/path'),
    getAppId: jest.fn().mockReturnValue('com.dupli.beta'),
    getVersion: jest.fn().mockReturnValue('2.0.1-beta.local'),
    setName: jest.fn(),
    isReady: jest.fn(() => true),
    commandLine: {
        appendSwitch: jest.fn()
    }
};

const mockAutoUpdater = {
    setFeedURL: jest.fn(),
    on: jest.fn(),
    checkForUpdates: jest.fn(),
    channel: 'latest',
    allowPrerelease: false,
    logger: console
};

jest.mock('electron', () => ({
    app: mockApp,
    ipcMain: {
        handle: jest.fn(),
        on: jest.fn(),
        once: jest.fn(),
        send: jest.fn()
    },
    BrowserWindow: jest.fn(() => ({
        loadURL: jest.fn(),
        loadFile: jest.fn(),
        webContents: { send: jest.fn() },
        on: jest.fn(),
        close: jest.fn(),
        show: jest.fn()
    })),
    session: {
        defaultSession: {
            setPermissionRequestHandler: jest.fn()
        }
    }
}), { virtual: true });

jest.mock('electron-updater', () => ({
    autoUpdater: mockAutoUpdater
}));

const mainCaddy = require('../../main-caddy');

global.mockApp = mockApp;
global.mockAutoUpdater = mockAutoUpdater;

describe('Auto-Updater Logic Tests', () => {
    let app, autoUpdater;
    
    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();
        
        app = global.mockApp;
        autoUpdater = global.mockAutoUpdater;
        
        app.getName.mockReturnValue('Duplicator Beta');
        app.getAppPath.mockReturnValue('/mock/app/path');
        app.getAppId.mockReturnValue('com.dupli.beta');
        app.getVersion.mockReturnValue('2.0.1-beta.local');
        autoUpdater.channel = 'latest';
        autoUpdater.allowPrerelease = false;
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('setupAutoUpdater should use "latest" channel for standard app', () => {
        app.getName.mockReturnValue('Duplicator');
        app.getAppPath.mockReturnValue('/opt/Duplicator');
        app.getVersion.mockReturnValue('2.0.1');
        app.getAppId.mockReturnValue('com.dupli.prod');
        
        mainCaddy.setupAutoUpdater();
        
        expect(autoUpdater.channel).toBe('latest');
        expect(autoUpdater.allowPrerelease).toBe(false);
    });

    test('setupAutoUpdater should use "beta" channel if app name contains beta', () => {
        app.getName.mockReturnValue('Duplicator Beta');
        app.getAppPath.mockReturnValue('/opt/Duplicator-Beta');
        
        mainCaddy.setupAutoUpdater();
        
        expect(autoUpdater.channel).toBe('beta');
        expect(autoUpdater.allowPrerelease).toBe(true);
    });

    test('setupAutoUpdater should configure GitHub provider', () => {
        mainCaddy.setupAutoUpdater();
        
        expect(autoUpdater.setFeedURL).toHaveBeenCalledWith(expect.objectContaining({
            provider: 'github',
            owner: 'muarf',
            repo: 'dupli-electron-caddy'
        }));
    });
});
