const fs = require('fs');
const path = require('path');

class TestUtils {
    static createTempFile(content, extension = '.tmp') {
        const tempDir = path.join(__dirname, '..', '..', 'temp');
        
        if (!fs.existsSync(tempDir)) {
            fs.mkdirSync(tempDir, { recursive: true });
        }
        
        const filePath = path.join(tempDir, `test-${Date.now()}${extension}`);
        fs.writeFileSync(filePath, content);
        return filePath;
    }

    static createTempDir() {
        const tempDir = path.join(__dirname, '..', '..', 'temp');
        
        if (!fs.existsSync(tempDir)) {
            fs.mkdirSync(tempDir, { recursive: true });
        }
        
        const dirPath = path.join(tempDir, `test-dir-${Date.now()}`);
        fs.mkdirSync(dirPath, { recursive: true });
        return dirPath;
    }

    static cleanupTempFiles() {
        const tempDir = path.join(__dirname, '..', '..', 'temp');
        
        if (fs.existsSync(tempDir)) {
            const files = fs.readdirSync(tempDir);
            files.forEach(file => {
                const filePath = path.join(tempDir, file);
                try {
                    if (fs.statSync(filePath).isDirectory()) {
                        fs.rmSync(filePath, { recursive: true, force: true });
                    } else {
                        fs.unlinkSync(filePath);
                    }
                } catch (error) {
                    console.warn(`Impossible de supprimer ${filePath}:`, error.message);
                }
            });
        }
    }

    static createTestPhpFile(content) {
        return this.createTempFile(content, '.php');
    }

    static createTestHtmlFile(content) {
        return this.createTempFile(content, '.html');
    }

    static createTestJsonFile(content) {
        return this.createTempFile(JSON.stringify(content, null, 2), '.json');
    }

    static createTestConfigFile(config) {
        return this.createTempFile(JSON.stringify(config, null, 2), '.json');
    }

    static waitForFile(filePath, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const startTime = Date.now();
            
            const checkFile = () => {
                if (fs.existsSync(filePath)) {
                    resolve();
                } else if (Date.now() - startTime > timeout) {
                    reject(new Error(`Timeout waiting for file: ${filePath}`));
                } else {
                    setTimeout(checkFile, 100);
                }
            };
            
            checkFile();
        });
    }

    static waitForPort(port, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const net = require('net');
            const startTime = Date.now();
            
            const checkPort = () => {
                const socket = new net.Socket();
                
                socket.on('connect', () => {
                    socket.destroy();
                    resolve();
                });
                
                socket.on('error', () => {
                    if (Date.now() - startTime > timeout) {
                        reject(new Error(`Timeout waiting for port: ${port}`));
                    } else {
                        setTimeout(checkPort, 100);
                    }
                });
                
                socket.connect(port, 'localhost');
            };
            
            checkPort();
        });
    }

    static createMockElectronApp() {
        return {
            isRunning: jest.fn(() => true),
            start: jest.fn(() => Promise.resolve()),
            stop: jest.fn(() => Promise.resolve()),
            restart: jest.fn(() => Promise.resolve()),
            client: {
                getWindowCount: jest.fn(() => Promise.resolve(1)),
                getWindowBounds: jest.fn(() => Promise.resolve({ width: 1200, height: 800 })),
                setWindowBounds: jest.fn(() => Promise.resolve()),
                getTitle: jest.fn(() => Promise.resolve('Duplicator')),
                getUrl: jest.fn(() => Promise.resolve('http://localhost:8000/')),
                waitUntilWindowLoaded: jest.fn(() => Promise.resolve()),
                execute: jest.fn(() => Promise.resolve()),
                $: jest.fn(() => ({
                    click: jest.fn(() => Promise.resolve())
                })),
                closeWindow: jest.fn(() => Promise.resolve())
            }
        };
    }

    static createMockProcess() {
        return {
            stdout: {
                on: jest.fn()
            },
            stderr: {
                on: jest.fn()
            },
            on: jest.fn(),
            kill: jest.fn(),
            killed: false
        };
    }

    static createMockSpawn() {
        return jest.fn(() => this.createMockProcess());
    }

    static createMockFs() {
        return {
            existsSync: jest.fn(),
            mkdirSync: jest.fn(),
            readdirSync: jest.fn(() => []),
            statSync: jest.fn(() => ({ isFile: () => true })),
            unlinkSync: jest.fn(),
            chmodSync: jest.fn(),
            writeFileSync: jest.fn(),
            readFileSync: jest.fn(),
            createWriteStream: jest.fn(() => ({
                on: jest.fn(),
                close: jest.fn(),
                write: jest.fn()
            }))
        };
    }

    static createMockHttps() {
        return {
            get: jest.fn()
        };
    }

    static createMockChildProcess() {
        return {
            spawn: this.createMockSpawn(),
            execSync: jest.fn()
        };
    }

    static createMockPath() {
        return {
            join: jest.fn((...args) => args.join('/')),
            resolve: jest.fn((...args) => args.join('/')),
            dirname: jest.fn((p) => p.split('/').slice(0, -1).join('/')),
            basename: jest.fn((p) => p.split('/').pop()),
            extname: jest.fn((p) => {
                const parts = p.split('.');
                return parts.length > 1 ? '.' + parts.pop() : '';
            }),
            sep: '/',
            delimiter: ':'
        };
    }

    static createMockElectron() {
        return {
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
        };
    }

    static createTestEnvironment() {
        return {
            NODE_ENV: 'test',
            APPIMAGE: undefined,
            resourcesPath: '/path/to/resources'
        };
    }

    static mockProcessPlatform(platform) {
        Object.defineProperty(process, 'platform', {
            value: platform,
            configurable: true
        });
    }

    static mockProcessEnv(env) {
        Object.keys(env).forEach(key => {
            process.env[key] = env[key];
        });
    }

    static restoreProcessPlatform() {
        Object.defineProperty(process, 'platform', {
            value: 'linux',
            configurable: true
        });
    }

    static restoreProcessEnv() {
        delete process.env.APPIMAGE;
        delete process.env.NODE_ENV;
    }

    static createTestData() {
        return {
            user: {
                id: 1,
                name: 'Test User',
                email: 'test@example.com'
            },
            machine: {
                id: 1,
                name: 'Test Machine',
                type: 'duplicopieur',
                price: 0.05
            },
            tirage: {
                id: 1,
                copies: 100,
                price: 5.00,
                date: new Date().toISOString()
            }
        };
    }

    static createTestResponse(statusCode = 200, body = {}) {
        return {
            statusCode,
            headers: {
                'content-type': 'application/json'
            },
            body: JSON.stringify(body)
        };
    }

    static createTestError(message = 'Test error', code = 'TEST_ERROR') {
        const error = new Error(message);
        error.code = code;
        return error;
    }
}

module.exports = TestUtils;

