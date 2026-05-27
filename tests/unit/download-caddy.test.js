const { downloadCaddy, CADDY_VERSIONS } = require('../../scripts/download-caddy');
const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Mock des modules
jest.mock('https');
jest.mock('fs');
jest.mock('child_process');

describe('download-caddy.js Unit Tests', () => {
    const originalExit = process.exit;
    const originalPlatform = process.platform;

    beforeEach(() => {
        jest.clearAllMocks();
        process.exit = jest.fn();
        jest.spyOn(console, 'error').mockImplementation(() => {});
        jest.spyOn(console, 'log').mockImplementation(() => {});
        Object.defineProperty(process, 'platform', { value: 'linux', configurable: true });
        
        // Mock fs.existsSync by default
        fs.existsSync.mockReturnValue(true);
    });

    afterAll(() => {
        process.exit = originalExit;
        Object.defineProperty(process, 'platform', { value: originalPlatform, configurable: true });
    });

    describe('CADDY_VERSIONS', () => {
        test('should have configurations for main platforms', () => {
            expect(CADDY_VERSIONS).toHaveProperty('linux-x64');
            expect(CADDY_VERSIONS).toHaveProperty('windows-x64');
            expect(CADDY_VERSIONS).toHaveProperty('darwin-x64');
        });
    });

    describe('downloadCaddy', () => {
        test('should handle successful download and extraction', async () => {
            // Mock https.get to simulate successful response
            const mockResponse = {
                statusCode: 200,
                pipe: jest.fn(),
                on: jest.fn((event, cb) => {
                    if (event === 'end') cb();
                })
            };
            https.get.mockImplementation((url, cb) => {
                cb(mockResponse);
                return { on: jest.fn() };
            });

            // Mock fs.createWriteStream
            const mockFile = {
                on: jest.fn((event, cb) => {
                    if (event === 'finish') cb();
                }),
                close: jest.fn()
            };
            fs.createWriteStream.mockReturnValue(mockFile);
            
            // Mock execution
            execSync.mockReturnValue(Buffer.from('extract ok'));

            await downloadCaddy();

            expect(https.get).toHaveBeenCalled();
            expect(fs.createWriteStream).toHaveBeenCalled();
            expect(execSync).toHaveBeenCalled(); // For extraction
            expect(fs.chmodSync).toHaveBeenCalled(); // For linux executable
        });

        test('should handle HTTP errors and exit', async () => {
            const mockResponse = {
                statusCode: 404,
                statusMessage: 'Not Found'
            };
            https.get.mockImplementation((url, cb) => {
                cb(mockResponse);
                return { on: jest.fn() };
            });

            await downloadCaddy();
            expect(process.exit).toHaveBeenCalledWith(1);
        });

        test('should handle redirection (301/302)', async () => {
            const mockRedirectResponse = {
                statusCode: 301,
                headers: { location: 'https://newurl.com' }
            };
            const mockFinalResponse = {
                statusCode: 200,
                pipe: jest.fn(),
                on: jest.fn((event, cb) => {
                    if (event === 'end') cb();
                })
            };

            https.get
                .mockImplementationOnce((url, cb) => {
                    cb(mockRedirectResponse);
                    return { on: jest.fn() };
                })
                .mockImplementationOnce((url, cb) => {
                    cb(mockFinalResponse);
                    return { on: jest.fn() };
                });

            const mockFile = {
                on: jest.fn((event, cb) => {
                    if (event === 'finish') cb();
                }),
                close: jest.fn()
            };
            fs.createWriteStream.mockReturnValue(mockFile);
            fs.unlink.mockImplementation((p, cb) => cb());

            await downloadCaddy();
            expect(https.get).toHaveBeenCalledTimes(2);
        });
    });
});
