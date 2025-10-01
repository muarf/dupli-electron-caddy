const request = require('supertest');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

describe('Intégration Caddy + PHP', () => {
    let caddyProcess;
    let phpFpmProcess;
    const testPort = 8001;

    beforeAll(async () => {
        // Démarrer PHP-FPM pour les tests
        await startPhpFpm();
        
        // Démarrer Caddy pour les tests
        await startCaddy();
        
        // Attendre que les serveurs soient prêts
        await waitForServer(`http://localhost:${testPort}`);
    }, 30000);

    afterAll(async () => {
        // Arrêter les processus
        if (caddyProcess) {
            caddyProcess.kill();
        }
        if (phpFpmProcess) {
            phpFpmProcess.kill();
        }
    });

    async function startPhpFpm() {
        return new Promise((resolve, reject) => {
            const phpFpmPath = 'php-fpm';
            const configPath = path.join(__dirname, '..', '..', 'php-fpm.conf');
            
            phpFpmProcess = spawn(phpFpmPath, [
                '--fpm-config', configPath
            ], {
                stdio: ['pipe', 'pipe', 'pipe']
            });
            
            phpFpmProcess.on('error', (error) => {
                console.warn('PHP-FPM non disponible pour les tests:', error.message);
                resolve(); // Continuer sans PHP-FPM pour les tests
            });
            
            phpFpmProcess.on('spawn', () => {
                console.log('PHP-FPM démarré pour les tests');
                resolve();
            });
            
            // Timeout après 5 secondes
            setTimeout(() => {
                console.warn('Timeout PHP-FPM');
                resolve();
            }, 5000);
        });
    }

    async function startCaddy() {
        return new Promise((resolve, reject) => {
            const caddyPath = 'caddy';
            const configPath = path.join(__dirname, '..', '..', 'Caddyfile');
            
            // Modifier le port dans la configuration pour les tests
            const testCaddyfile = createTestCaddyfile();
            
            caddyProcess = spawn(caddyPath, [
                'run',
                '--config', testCaddyfile,
                '--adapter', 'caddyfile'
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
                env: {
                    ...process.env,
                    CADDY_ROOT: path.join(__dirname, '..', '..', 'app', 'public')
                }
            });
            
            caddyProcess.on('error', (error) => {
                console.warn('Caddy non disponible pour les tests:', error.message);
                resolve(); // Continuer sans Caddy pour les tests
            });
            
            caddyProcess.on('spawn', () => {
                console.log('Caddy démarré pour les tests');
                resolve();
            });
            
            // Timeout après 10 secondes
            setTimeout(() => {
                console.warn('Timeout Caddy');
                resolve();
            }, 10000);
        });
    }

    function createTestCaddyfile() {
        const testCaddyfile = path.join(__dirname, '..', '..', 'Caddyfile.test');
        const content = `:${testPort}

# Configuration de test simplifiée
handle {
    file_server {
        root /app/public
        index index.html
    }
}`;
        
        fs.writeFileSync(testCaddyfile, content);
        return testCaddyfile;
    }

    async function waitForServer(url, maxAttempts = 30) {
        for (let i = 0; i < maxAttempts; i++) {
            try {
                const response = await request(url).get('/');
                if (response.status === 200 || response.status === 404) {
                    return true;
                }
            } catch (error) {
                // Attendre 1 seconde avant de réessayer
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
        return false;
    }

    describe('Serveur Caddy', () => {
        test('devrait répondre sur le port de test', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/')
                .expect(200);
            
            expect(response).toBeDefined();
        });

        test('devrait servir les fichiers statiques', async () => {
            // Créer un fichier de test
            const testFile = path.join(__dirname, '..', '..', 'app', 'public', 'test.txt');
            fs.writeFileSync(testFile, 'Test content');
            
            try {
                const response = await request(`http://localhost:${testPort}`)
                    .get('/test.txt')
                    .expect(200);
                
                expect(response.text).toBe('Test content');
            } finally {
                // Nettoyer le fichier de test
                if (fs.existsSync(testFile)) {
                    fs.unlinkSync(testFile);
                }
            }
        });

        test('devrait gérer les erreurs 404', async () => {
            await request(`http://localhost:${testPort}`)
                .get('/nonexistent')
                .expect(404);
        });
    });

    describe('Intégration PHP', () => {
        test('devrait exécuter les scripts PHP', async () => {
            // Créer un script PHP de test
            const testPhpFile = path.join(__dirname, '..', '..', 'app', 'public', 'test.php');
            fs.writeFileSync(testPhpFile, '<?php echo "Hello from PHP!"; ?>');
            
            try {
                const response = await request(`http://localhost:${testPort}`)
                    .get('/test.php')
                    .expect(200);
                
                expect(response.text).toContain('Hello from PHP!');
            } finally {
                // Nettoyer le fichier de test
                if (fs.existsSync(testPhpFile)) {
                    fs.unlinkSync(testPhpFile);
                }
            }
        });

        test('devrait gérer les erreurs PHP', async () => {
            // Créer un script PHP avec erreur
            const testPhpFile = path.join(__dirname, '..', '..', 'app', 'public', 'error.php');
            fs.writeFileSync(testPhpFile, '<?php echo $undefined_variable; ?>');
            
            try {
                const response = await request(`http://localhost:${testPort}`)
                    .get('/error.php');
                
                // Peut retourner 200 avec erreur ou 500 selon la configuration
                expect([200, 500]).toContain(response.status);
            } finally {
                // Nettoyer le fichier de test
                if (fs.existsSync(testPhpFile)) {
                    fs.unlinkSync(testPhpFile);
                }
            }
        });
    });

    describe('Configuration Caddy', () => {
        test('devrait appliquer les headers de sécurité', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/')
                .expect(200);
            
            // Vérifier les headers de sécurité
            expect(response.headers['x-content-type-options']).toBe('nosniff');
            expect(response.headers['x-frame-options']).toBe('DENY');
            expect(response.headers['x-xss-protection']).toBe('1; mode=block');
        });

        test('devrait gérer les requêtes CORS', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .options('/')
                .expect(200);
            
            // Vérifier les headers CORS
            expect(response.headers['access-control-allow-origin']).toBe('*');
            expect(response.headers['access-control-allow-methods']).toContain('GET');
            expect(response.headers['access-control-allow-methods']).toContain('POST');
        });
    });

    describe('Performance', () => {
        test('devrait répondre rapidement', async () => {
            const startTime = Date.now();
            
            await request(`http://localhost:${testPort}`)
                .get('/')
                .expect(200);
            
            const responseTime = Date.now() - startTime;
            expect(responseTime).toBeLessThan(1000); // Moins d'1 seconde
        });

        test('devrait gérer les requêtes simultanées', async () => {
            const requests = Array(10).fill().map(() => 
                request(`http://localhost:${testPort}`).get('/')
            );
            
            const responses = await Promise.all(requests);
            
            responses.forEach(response => {
                expect(response.status).toBe(200);
            });
        });
    });
});

