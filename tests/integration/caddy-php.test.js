const request = require('supertest');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

describe('Intégration Caddy + PHP', () => {
    let caddyProcess;
    let phpProcess;
    const testProxyPort = 8002; // Port Caddy
    const testPhpPort = 8003;   // Port PHP
    const appRoot = path.join(__dirname, '..', '..');
    const publicRoot = path.join(appRoot, 'app', 'public');

    beforeAll(async () => {
        // S'assurer que le dossier public existe
        if (!fs.existsSync(publicRoot)) {
            fs.mkdirSync(publicRoot, { recursive: true });
        }

        // Démarrer PHP intégré pour les tests
        await startPhp();
        
        // Démarrer Caddy pour les tests
        await startCaddy();
        
        // Attendre que les serveurs soient prêts
        await waitForServer(`http://localhost:${testProxyPort}`);
    }, 30000);

    afterAll(async () => {
        // Arrêter les processus
        if (caddyProcess) {
            caddyProcess.kill();
        }
        if (phpProcess) {
            phpProcess.kill();
        }
        
        // Nettoyer le Caddyfile de test
        const testCaddyfile = path.join(appRoot, 'Caddyfile.test');
        if (fs.existsSync(testCaddyfile)) {
            fs.unlinkSync(testCaddyfile);
        }
    });

    async function startPhp() {
        return new Promise((resolve) => {
            const phpPath = 'php';
            
            phpProcess = spawn(phpPath, [
                '-S', `127.0.0.1:${testPhpPort}`,
                '-t', publicRoot,
                '-d', 'display_errors=1'
            ], {
                stdio: ['pipe', 'pipe', 'pipe']
            });
            
            phpProcess.on('error', (error) => {
                console.warn('PHP non disponible pour les tests:', error.message);
                resolve();
            });
            
            phpProcess.on('spawn', () => {
                console.log(`PHP started on port ${testPhpPort}`);
                resolve();
            });
            
            setTimeout(resolve, 3000);
        });
    }

    async function startCaddy() {
        return new Promise((resolve) => {
            // Chercher le binaire caddy local
            let caddyPath = path.join(appRoot, 'caddy', 'caddy');
            if (!fs.existsSync(caddyPath)) {
                caddyPath = 'caddy'; // Fallback système
            }
            
            const testCaddyfile = createTestCaddyfile();
            
            console.log(`Starting Caddy from ${caddyPath} with config ${testCaddyfile}`);
            
            caddyProcess = spawn(caddyPath, [
                'run',
                '--config', testCaddyfile,
                '--adapter', 'caddyfile'
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
                env: {
                    ...process.env,
                    CADDY_ROOT: publicRoot
                }
            });

            caddyProcess.stdout.on('data', (data) => console.log(`[Caddy STDOUT] ${data}`));
            caddyProcess.stderr.on('data', (data) => console.error(`[Caddy STDERR] ${data}`));
            
            caddyProcess.on('error', (error) => {
                console.warn('Caddy non disponible pour les tests:', error.message);
                resolve();
            });
            
            caddyProcess.on('spawn', () => {
                console.log(`Caddy process spawned`);
                resolve();
            });
            
            setTimeout(resolve, 5000);
        });
    }

    function createTestCaddyfile() {
        const testCaddyfile = path.join(appRoot, 'Caddyfile.test');
        const content = `:${testProxyPort} {
    log {
        output file /tmp/caddy_test.log
    }
    reverse_proxy 127.0.0.1:${testPhpPort}
}`;
        
        fs.writeFileSync(testCaddyfile, content);
        return testCaddyfile;
    }

    async function waitForServer(url, maxAttempts = 15) {
        for (let i = 0; i < maxAttempts; i++) {
            try {
                const response = await request(url).get('/');
                // 200, 404 (si index.php absent) ou 403 sont acceptables pour dire que le serveur répond
                if (response.status < 500) {
                    return true;
                }
            } catch (error) {
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
        return false;
    }

    describe('Serveur Caddy + PHP', () => {
        test('devrait répondre via le proxy Caddy', async () => {
            const response = await request(`http://localhost:${testProxyPort}`)
                .get('/');
            
            expect(response.status).toBeLessThan(500);
        });

        test('devrait servir un fichier statique via Caddy/PHP', async () => {
            const testFile = path.join(publicRoot, 'integration_test.txt');
            fs.writeFileSync(testFile, 'Integration OK');
            
            try {
                const response = await request(`http://localhost:${testProxyPort}`)
                    .get('/integration_test.txt')
                    .expect(200);
                
                expect(response.text).toBe('Integration OK');
            } finally {
                if (fs.existsSync(testFile)) fs.unlinkSync(testFile);
            }
        });

        test('devrait exécuter du PHP via le proxy', async () => {
            const testPhpFile = path.join(publicRoot, 'test_proxy.php');
            fs.writeFileSync(testPhpFile, '<?php echo "PHP_PROXY_WORKING"; ?>');
            
            try {
                const response = await request(`http://localhost:${testProxyPort}`)
                    .get('/test_proxy.php')
                    .expect(200);
                
                expect(response.text).toContain('PHP_PROXY_WORKING');
            } finally {
                if (fs.existsSync(testPhpFile)) fs.unlinkSync(testPhpFile);
            }
        });
    });
});
