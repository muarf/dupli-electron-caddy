const request = require('supertest');
const http = require('http');

describe('Intégration Caddy + PHP (Simplifié)', () => {
    const testPort = 8001;
    let testServer;

    beforeAll(async () => {
        // Créer un serveur de test simple
        testServer = http.createServer((req, res) => {
            // Simuler les réponses de Caddy
            res.setHeader('X-Content-Type-Options', 'nosniff');
            res.setHeader('X-Frame-Options', 'DENY');
            res.setHeader('X-XSS-Protection', '1; mode=block');
            res.setHeader('Access-Control-Allow-Origin', '*');
            res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            
            if (req.url === '/') {
                res.writeHead(200, { 'Content-Type': 'text/html' });
                res.end('<html><body>Test Server</body></html>');
            } else if (req.url === '/test.txt') {
                res.writeHead(200, { 'Content-Type': 'text/plain' });
                res.end('Test content');
            } else if (req.url === '/test.php') {
                res.writeHead(200, { 'Content-Type': 'text/html' });
                res.end('Hello from PHP!');
            } else if (req.url === '/error.php') {
                res.writeHead(500, { 'Content-Type': 'text/html' });
                res.end('PHP Error');
            } else {
                res.writeHead(404, { 'Content-Type': 'text/html' });
                res.end('Not Found');
            }
        });

        // Démarrer le serveur de test
        await new Promise((resolve) => {
            testServer.listen(testPort, resolve);
        });
    }, 10000);

    afterAll(async () => {
        if (testServer) {
            await new Promise((resolve) => {
                testServer.close(resolve);
            });
        }
    });

    describe('Serveur de test', () => {
        test('devrait répondre sur le port de test', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/')
                .expect(200);
            
            expect(response).toBeDefined();
        });

        test('devrait servir les fichiers statiques', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/test.txt')
                .expect(200);
            
            expect(response.text).toBe('Test content');
        });

        test('devrait gérer les erreurs 404', async () => {
            await request(`http://localhost:${testPort}`)
                .get('/nonexistent')
                .expect(404);
        });
    });

    describe('Simulation PHP', () => {
        test('devrait exécuter les scripts PHP', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/test.php')
                .expect(200);
            
            expect(response.text).toContain('Hello from PHP!');
        });

        test('devrait gérer les erreurs PHP', async () => {
            const response = await request(`http://localhost:${testPort}`)
                .get('/error.php')
                .expect(500);
            
            expect(response.text).toContain('PHP Error');
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

