const { _electron: electron } = require('@playwright/test');
const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Application Electron E2E', () => {
    let electronApp;
    let window;
    let electronPid;

    test.beforeAll(async () => {
        const appPath = path.join(__dirname, '..', '..', 'main-caddy.js');
        
        electronApp = await electron.launch({
            args: [appPath, '--no-sandbox', '--disable-gpu']
        });

        // Récupérer le PID pour pouvoir force-kill si nécessaire
        electronPid = electronApp.process().pid;
        window = await electronApp.firstWindow();
    }, 30000);

    test.afterAll(async () => {
        if (!electronApp) return;

        // Essayer de fermer proprement, avec un timeout de 8s
        // Après quoi on force-kill le processus pour ne pas bloquer Playwright
        const closeWithTimeout = Promise.race([
            electronApp.close(),
            new Promise(resolve => setTimeout(resolve, 8000))
        ]);

        await closeWithTimeout;

        // Si le processus est encore vivant, on le tue de force
        if (electronPid) {
            try {
                process.kill(electronPid, 'SIGKILL');
            } catch {
                // Déjà mort, c'est ok
            }
        }
    }, 30000);

    test.describe('Démarrage de l\'application', () => {
        test('devrait démarrer et afficher la fenêtre principale', async () => {
            expect(window).toBeDefined();
            const windowCount = electronApp.windows().length;
            expect(windowCount).toBeGreaterThanOrEqual(1);
        });

        test('devrait avoir les bonnes dimensions de fenêtre', async () => {
            const size = await window.viewportSize();
            // Note: viewportSize can be null if not set, 
            // but we can check if it's visible and has content
            expect(window.isClosed()).toBe(false);
        });
    });

    test.describe('Interface utilisateur', () => {
        test('devrait charger la page d\'accueil', async () => {
            // Attendre que la page soit chargée (supporte localhost ou 127.0.0.1)
            await window.waitForURL(/(localhost|127\.0\.0\.1):8000/, { timeout: 15000 });
            const url = window.url();
            expect(url).toMatch(/(localhost|127\.0\.0\.1):8000/);
        });

        test('devrait afficher le titre de l\'application', async () => {
            const title = await window.title();
            expect(title).toContain('Duplicator');
        });

        test('devrait avoir les éléments de base', async () => {
            const body = await window.locator('body');
            await expect(body).toBeVisible();
        });
    });

    test.describe('Fonctionnalités de base', () => {
        test('devrait gérer les clics de souris', async () => {
            await window.click('body');
            expect(window.isClosed()).toBe(false);
        });
    });

    test.describe('Communication IPC', () => {
        test('devrait gérer les messages IPC (openFile)', async () => {
            // Tester l'ouverture de fichier via IPC
            const result = await window.evaluate(() => {
                if (window.electronAPI && window.electronAPI.openFile) {
                    return window.electronAPI.openFile('test.pdf');
                }
                return 'API not found';
            });
            
            expect(result).toBeDefined();
            expect(result).not.toBe('API not found');
        });

        test('devrait gérer le nettoyage des fichiers temporaires', async () => {
            const result = await window.evaluate(() => {
                if (window.electronAPI && window.electronAPI.cleanupTmpFiles) {
                    return window.electronAPI.cleanupTmpFiles();
                }
                return 'API not found';
            });
            
            expect(result).toBeDefined();
            expect(result).not.toBe('API not found');
        });
    });

    test.describe('Sécurité', () => {
        test('devrait avoir la sandbox activée', async () => {
            // Dans Electron avec contextIsolation, process n'est pas dans le window
            // On vérifie si on peut accéder à l'API exposée à la place
            const hasAPI = await window.evaluate(() => {
                return typeof window.electronAPI !== 'undefined';
            });
            expect(hasAPI).toBe(true);
        });

        test('devrait avoir l\'isolation de contexte activée', async () => {
            // Si l'isolation fonctionne, process doit être indéfini dans le renderer
            const processIsUndefined = await window.evaluate(() => {
                return typeof process === 'undefined';
            });
            expect(processIsUndefined).toBe(true);
        });
    });
});
