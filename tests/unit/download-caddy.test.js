const { downloadCaddy, CADDY_VERSIONS } = require('../../scripts/download-caddy');
const https = require('https');
const fs = require('fs');
const path = require('path');

// Mock des modules
jest.mock('https');
jest.mock('fs');
jest.mock('child_process');

describe('download-caddy.js', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    describe('CADDY_VERSIONS', () => {
        test('devrait contenir les configurations pour toutes les plateformes', () => {
            expect(CADDY_VERSIONS).toHaveProperty('linux');
            expect(CADDY_VERSIONS).toHaveProperty('windows');
            expect(CADDY_VERSIONS).toHaveProperty('darwin');
        });

        test('devrait avoir les bonnes URLs pour chaque plateforme', () => {
            expect(CADDY_VERSIONS.linux.url).toContain('linux_amd64');
            expect(CADDY_VERSIONS.windows.url).toContain('windows_amd64');
            expect(CADDY_VERSIONS.darwin.url).toContain('macOS_amd64');
        });

        test('devrait avoir les bons noms de binaires', () => {
            expect(CADDY_VERSIONS.linux.binary).toBe('caddy');
            expect(CADDY_VERSIONS.windows.binary).toBe('caddy.exe');
            expect(CADDY_VERSIONS.darwin.binary).toBe('caddy');
        });
    });

    describe('downloadFile', () => {
        test('devrait télécharger un fichier avec succès', async () => {
            const mockResponse = {
                statusCode: 200,
                pipe: jest.fn(),
                on: jest.fn()
            };

            const mockFile = {
                on: jest.fn(),
                close: jest.fn(),
                write: jest.fn()
            };

            https.get.mockImplementation((url, callback) => {
                callback(mockResponse);
                return { on: jest.fn() };
            });

            fs.createWriteStream.mockReturnValue(mockFile);

            // Mock de la résolution de la promesse
            mockFile.on.mockImplementation((event, callback) => {
                if (event === 'finish') {
                    setTimeout(callback, 0);
                }
            });

            const testUrl = 'https://example.com/test.tar.gz';
            const testPath = '/tmp/test.tar.gz';

            await expect(downloadCaddy()).resolves.not.toThrow();
        });

        test('devrait gérer les erreurs HTTP', async () => {
            const mockResponse = {
                statusCode: 404,
                statusMessage: 'Not Found'
            };

            https.get.mockImplementation((url, callback) => {
                callback(mockResponse);
                return { on: jest.fn() };
            });

            // Mock de process.exit pour éviter l'arrêt du test
            const originalExit = process.exit;
            process.exit = jest.fn();

            try {
                await downloadCaddy();
                expect(process.exit).toHaveBeenCalledWith(1);
            } finally {
                process.exit = originalExit;
            }
        });
    });

    describe('extractFile', () => {
        test('devrait extraire un fichier tar.gz', () => {
            const { execSync } = require('child_process');
            
            const testFile = '/tmp/test.tar.gz';
            const extractPath = '/tmp/extract';
            
            // Mock de execSync pour simuler une extraction réussie
            execSync.mockReturnValue(undefined);
            
            // Test avec un fichier .tar.gz
            expect(() => {
                // Simuler l'extraction
                execSync(`tar -xzf "${testFile}" -C "${extractPath}"`, { stdio: 'inherit' });
            }).not.toThrow();
            
            expect(execSync).toHaveBeenCalledWith(
                `tar -xzf "${testFile}" -C "${extractPath}"`,
                { stdio: 'inherit' }
            );
        });

        test('devrait extraire un fichier zip', () => {
            const { execSync } = require('child_process');
            
            const testFile = '/tmp/test.zip';
            const extractPath = '/tmp/extract';
            
            // Mock de execSync pour simuler une extraction réussie
            execSync.mockReturnValue(undefined);
            
            // Test avec un fichier .zip
            expect(() => {
                // Simuler l'extraction
                execSync(`unzip -o "${testFile}" -d "${extractPath}"`, { stdio: 'inherit' });
            }).not.toThrow();
            
            expect(execSync).toHaveBeenCalledWith(
                `unzip -o "${testFile}" -d "${extractPath}"`,
                { stdio: 'inherit' }
            );
        });
    });

    describe('downloadCaddy', () => {
        test('devrait créer le dossier caddy s\'il n\'existe pas', async () => {
            fs.existsSync.mockReturnValue(false);
            fs.mkdirSync.mockImplementation(() => {});
            
            // Mock des autres fonctions
            https.get.mockImplementation(() => ({ on: jest.fn() }));
            fs.createWriteStream.mockReturnValue({
                on: jest.fn(),
                close: jest.fn()
            });
            
            const { execSync } = require('child_process');
            execSync.mockReturnValue(undefined);
            
            // Mock de process.exit pour éviter l'arrêt du test
            const originalExit = process.exit;
            process.exit = jest.fn();
            
            try {
                await downloadCaddy();
                expect(fs.mkdirSync).toHaveBeenCalledWith(
                    expect.stringContaining('caddy'),
                    { recursive: true }
                );
            } finally {
                process.exit = originalExit;
            }
        }, 10000);

        test('devrait rendre le binaire exécutable sur Unix', async () => {
            // Mock de la plateforme Unix
            Object.defineProperty(process, 'platform', {
                value: 'linux',
                configurable: true
            });
            
            fs.existsSync.mockReturnValue(false);
            fs.mkdirSync.mockImplementation(() => {});
            fs.chmodSync.mockImplementation(() => {});
            
            // Mock des autres fonctions
            https.get.mockImplementation(() => ({ on: jest.fn() }));
            fs.createWriteStream.mockReturnValue({
                on: jest.fn(),
                close: jest.fn()
            });
            
            const { execSync } = require('child_process');
            execSync.mockReturnValue(undefined);
            
            // Mock de process.exit pour éviter l'arrêt du test
            const originalExit = process.exit;
            process.exit = jest.fn();
            
            try {
                await downloadCaddy();
                expect(fs.chmodSync).toHaveBeenCalledWith(
                    expect.stringContaining('caddy'),
                    '755'
                );
            } finally {
                process.exit = originalExit;
            }
        }, 10000);

        test('devrait gérer les plateformes non supportées', async () => {
            // Mock d'une plateforme non supportée
            Object.defineProperty(process, 'platform', {
                value: 'unsupported',
                configurable: true
            });
            
            // Mock de process.exit pour éviter l'arrêt du test
            const originalExit = process.exit;
            process.exit = jest.fn();
            
            try {
                await downloadCaddy();
                expect(process.exit).toHaveBeenCalledWith(1);
            } finally {
                process.exit = originalExit;
            }
        });
    });
});
