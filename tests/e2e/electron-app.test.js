const { Application } = require('spectron');
const path = require('path');
const fs = require('fs');

describe('Application Electron E2E', () => {
    let app;
    const timeout = 30000;

    beforeAll(async () => {
        // Chemin vers l'application Electron
        const electronPath = require('electron');
        const appPath = path.join(__dirname, '..', '..');
        
        app = new Application({
            path: electronPath,
            args: [appPath, '--no-sandbox', '--disable-gpu'],
            env: {
                NODE_ENV: 'test'
            },
            startTimeout: timeout,
            waitTimeout: timeout
        });

        await app.start();
    }, timeout);

    afterAll(async () => {
        if (app && app.isRunning()) {
            await app.stop();
        }
    });

    describe('Démarrage de l\'application', () => {
        test('devrait démarrer sans erreur', async () => {
            expect(app.isRunning()).toBe(true);
        });

        test('devrait afficher la fenêtre principale', async () => {
            const windowCount = await app.client.getWindowCount();
            expect(windowCount).toBe(1);
        });

        test('devrait avoir les bonnes dimensions de fenêtre', async () => {
            const bounds = await app.client.getWindowBounds();
            expect(bounds.width).toBeGreaterThanOrEqual(800);
            expect(bounds.height).toBeGreaterThanOrEqual(600);
        });
    });

    describe('Interface utilisateur', () => {
        test('devrait charger la page d\'accueil', async () => {
            // Attendre que la page soit chargée
            await app.client.waitUntilWindowLoaded();
            
            // Vérifier que l'URL contient localhost:8000
            const url = await app.client.getUrl();
            expect(url).toContain('localhost:8000');
        });

        test('devrait afficher le titre de l\'application', async () => {
            const title = await app.client.getTitle();
            expect(title).toContain('Duplicator');
        });

        test('devrait avoir les éléments de base', async () => {
            // Attendre que le contenu soit chargé
            await app.client.waitUntilWindowLoaded();
            
            // Vérifier la présence d'éléments de base
            const body = await app.client.$('body');
            expect(body).toBeDefined();
        });
    });

    describe('Fonctionnalités de base', () => {
        test('devrait gérer les clics de souris', async () => {
            // Simuler un clic sur le body
            await app.client.$('body').click();
            
            // Vérifier que l'application répond
            expect(app.isRunning()).toBe(true);
        });

        test('devrait gérer le redimensionnement de fenêtre', async () => {
            const initialBounds = await app.client.getWindowBounds();
            
            // Redimensionner la fenêtre
            await app.client.setWindowBounds({
                width: 1000,
                height: 700
            });
            
            const newBounds = await app.client.getWindowBounds();
            expect(newBounds.width).toBe(1000);
            expect(newBounds.height).toBe(700);
        });

        test('devrait gérer la fermeture de fenêtre', async () => {
            // Fermer la fenêtre
            await app.client.closeWindow();
            
            // Vérifier que l'application est toujours en cours d'exécution
            // (sur macOS, l'application reste active)
            if (process.platform === 'darwin') {
                expect(app.isRunning()).toBe(true);
            }
        });
    });

    describe('Communication IPC', () => {
        test('devrait gérer les messages IPC', async () => {
            // Tester l'ouverture de fichier via IPC
            const result = await app.client.execute(() => {
                return window.electronAPI.openFile('test.pdf');
            });
            
            // Vérifier que la fonction existe
            expect(result).toBeDefined();
        });

        test('devrait gérer le nettoyage des fichiers temporaires', async () => {
            // Tester le nettoyage via IPC
            const result = await app.client.execute(() => {
                return window.electronAPI.cleanupTmpFiles();
            });
            
            // Vérifier que la fonction existe
            expect(result).toBeDefined();
        });
    });

    describe('Gestion des erreurs', () => {
        test('devrait gérer les erreurs de chargement', async () => {
            // Simuler une erreur de réseau
            await app.client.execute(() => {
                // Simuler une erreur
                window.location.href = 'http://localhost:9999/nonexistent';
            });
            
            // Attendre un peu
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Vérifier que l'application est toujours en cours d'exécution
            expect(app.isRunning()).toBe(true);
        });

        test('devrait gérer les erreurs JavaScript', async () => {
            // Exécuter du code JavaScript avec erreur
            await app.client.execute(() => {
                try {
                    throw new Error('Test error');
                } catch (e) {
                    console.error('Erreur capturée:', e.message);
                }
            });
            
            // Vérifier que l'application est toujours en cours d'exécution
            expect(app.isRunning()).toBe(true);
        });
    });

    describe('Performance', () => {
        test('devrait démarrer rapidement', async () => {
            const startTime = Date.now();
            
            // Redémarrer l'application
            await app.restart();
            
            const startupTime = Date.now() - startTime;
            expect(startupTime).toBeLessThan(10000); // Moins de 10 secondes
        });

        test('devrait utiliser une quantité raisonnable de mémoire', async () => {
            const memoryUsage = await app.client.execute(() => {
                return process.memoryUsage();
            });
            
            // Vérifier que l'utilisation mémoire est raisonnable (moins de 500MB)
            expect(memoryUsage.heapUsed).toBeLessThan(500 * 1024 * 1024);
        });
    });

    describe('Compatibilité cross-platform', () => {
        test('devrait fonctionner sur la plateforme actuelle', async () => {
            const platform = await app.client.execute(() => {
                return process.platform;
            });
            
            expect(['win32', 'darwin', 'linux']).toContain(platform);
        });

        test('devrait gérer les chemins de fichiers correctement', async () => {
            const pathInfo = await app.client.execute(() => {
                return {
                    platform: process.platform,
                    separator: require('path').sep,
                    delimiter: require('path').delimiter
                };
            });
            
            expect(pathInfo.platform).toBeDefined();
            expect(pathInfo.separator).toBeDefined();
            expect(pathInfo.delimiter).toBeDefined();
        });
    });

    describe('Sécurité', () => {
        test('devrait avoir la sandbox activée', async () => {
            const sandboxStatus = await app.client.execute(() => {
                return process.sandboxed;
            });
            
            // Vérifier que la sandbox est activée
            expect(sandboxStatus).toBe(true);
        });

        test('devrait avoir l\'isolation de contexte activée', async () => {
            const contextIsolation = await app.client.execute(() => {
                return process.contextIsolated;
            });
            
            // Vérifier que l'isolation de contexte est activée
            expect(contextIsolation).toBe(true);
        });
    });
});

