// Mock electron before anything else
jest.mock('electron', () => ({
    app: {
        disableHardwareAcceleration: jest.fn(),
        whenReady: jest.fn(() => Promise.resolve()),
        on: jest.fn(),
        quit: jest.fn(),
        isPackaged: false,
        getPath: jest.fn(),
        getName: jest.fn(() => 'dupli-electron-beta'),
        getAppPath: jest.fn(() => '/mock/app/path'),
        getAppId: jest.fn(() => 'com.dupli.beta'),
        setName: jest.fn(),
        isReady: jest.fn(() => true),
        commandLine: {
            appendSwitch: jest.fn()
        }
    }
}), { virtual: true });

const mainCaddy = require('../../main-caddy');
const { app } = require('electron');

// Mock electron-updater
jest.mock('electron-updater', () => ({
    autoUpdater: {
        setFeedURL: jest.fn(),
        on: jest.fn(),
        checkForUpdates: jest.fn(),
        channel: 'latest',
        allowPrerelease: false,
        logger: console
    }
}));

const { autoUpdater } = require('electron-updater');

describe('Auto-Updater Logic Tests', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('setupAutoUpdater should use "latest" channel for standard app', () => {
        app.getName.mockReturnValue('Duplicator');
        app.getAppPath.mockReturnValue('/opt/Duplicator');
        
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
