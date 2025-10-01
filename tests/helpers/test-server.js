const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

class TestServer {
    constructor(port = 8001) {
        this.port = port;
        this.processes = [];
        this.isRunning = false;
    }

    async start() {
        try {
            // Démarrer PHP-FPM
            await this.startPhpFpm();
            
            // Démarrer Caddy
            await this.startCaddy();
            
            // Attendre que les serveurs soient prêts
            await this.waitForServer();
            
            this.isRunning = true;
            console.log(`Serveur de test démarré sur le port ${this.port}`);
        } catch (error) {
            console.error('Erreur lors du démarrage du serveur de test:', error);
            throw error;
        }
    }

    async stop() {
        console.log('Arrêt du serveur de test...');
        
        // Arrêter tous les processus
        for (const process of this.processes) {
            if (process && !process.killed) {
                process.kill();
            }
        }
        
        this.processes = [];
        this.isRunning = false;
        
        // Nettoyer les fichiers temporaires
        this.cleanup();
        
        console.log('Serveur de test arrêté');
    }

    async startPhpFpm() {
        return new Promise((resolve, reject) => {
            const phpFpmPath = 'php-fpm';
            const configPath = this.createPhpFpmConfig();
            
            const phpFpmProcess = spawn(phpFpmPath, [
                '--fpm-config', configPath
            ], {
                stdio: ['pipe', 'pipe', 'pipe']
            });
            
            phpFpmProcess.on('error', (error) => {
                console.warn('PHP-FPM non disponible:', error.message);
                resolve(); // Continuer sans PHP-FPM
            });
            
            phpFpmProcess.on('spawn', () => {
                console.log('PHP-FPM démarré');
                this.processes.push(phpFpmProcess);
                resolve();
            });
            
            // Timeout après 5 secondes
            setTimeout(() => {
                console.warn('Timeout PHP-FPM');
                resolve();
            }, 5000);
        });
    }

    async startCaddy() {
        return new Promise((resolve, reject) => {
            const caddyPath = 'caddy';
            const configPath = this.createCaddyConfig();
            
            const caddyProcess = spawn(caddyPath, [
                'run',
                '--config', configPath,
                '--adapter', 'caddyfile'
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
                env: {
                    ...process.env,
                    CADDY_ROOT: path.join(__dirname, '..', '..', 'app', 'public')
                }
            });
            
            caddyProcess.on('error', (error) => {
                console.warn('Caddy non disponible:', error.message);
                resolve(); // Continuer sans Caddy
            });
            
            caddyProcess.on('spawn', () => {
                console.log('Caddy démarré');
                this.processes.push(caddyProcess);
                resolve();
            });
            
            // Timeout après 10 secondes
            setTimeout(() => {
                console.warn('Timeout Caddy');
                resolve();
            }, 10000);
        });
    }

    createPhpFpmConfig() {
        const configPath = path.join(__dirname, '..', '..', 'php-fpm.test.conf');
        const content = `[global]
pid = /tmp/php-fpm-test.pid
error_log = /tmp/php-fpm-test-error.log
log_level = notice
daemonize = no

[www]
user = www-data
group = www-data
listen = /tmp/php-fpm-test.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 2
pm.max_requests = 100

php_admin_value[error_log] = /tmp/duplicator_php_test_errors.log
php_admin_value[display_errors] = 1
php_admin_value[log_errors] = 1
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 10M
php_admin_value[max_execution_time] = 30
php_admin_value[memory_limit] = 128M

php_admin_value[session.save_path] = /tmp/duplicator_test_sessions
php_admin_value[session.gc_maxlifetime] = 1800`;
        
        fs.writeFileSync(configPath, content);
        return configPath;
    }

    createCaddyConfig() {
        const configPath = path.join(__dirname, '..', '..', 'Caddyfile.test');
        const content = `:${this.port}

# Configuration de test
errors {
    log {
        output file /tmp/duplicator_caddy_test_errors.log
        level ERROR
    }
}

log {
    output file /tmp/duplicator_caddy_test_access.log
    format json
}

header {
    X-Content-Type-Options nosniff
    X-Frame-Options DENY
    X-XSS-Protection "1; mode=block"
    Access-Control-Allow-Origin "*"
    Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Access-Control-Allow-Headers "Content-Type, Authorization"
}

# Gestion des fichiers statiques
handle /css/* {
    file_server {
        root /app/public
        hide .htaccess
    }
}

handle /js/* {
    file_server {
        root /app/public
        hide .htaccess
    }
}

handle /img/* {
    file_server {
        root /app/public
        hide .htaccess
    }
}

# Gestion des requêtes AJAX
handle /ajax_* {
    php_fastcgi unix//tmp/php-fpm-test.sock {
        root /app/public
        index index.php
        try_files {path} {path}/ /index.php?{query}
    }
}

# Route principale
handle {
    php_fastcgi unix//tmp/php-fpm-test.sock {
        root /app/public
        index index.php
        try_files {path} {path}/ /index.php?{query}
    }
}`;
        
        fs.writeFileSync(configPath, content);
        return configPath;
    }

    async waitForServer(maxAttempts = 30) {
        for (let i = 0; i < maxAttempts; i++) {
            try {
                const response = await this.makeRequest('GET', '/');
                if (response.statusCode === 200 || response.statusCode === 404) {
                    return true;
                }
            } catch (error) {
                // Attendre 1 seconde avant de réessayer
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        }
        return false;
    }

    makeRequest(method, path, data = null) {
        return new Promise((resolve, reject) => {
            const options = {
                hostname: 'localhost',
                port: this.port,
                path: path,
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (data) {
                const jsonData = JSON.stringify(data);
                options.headers['Content-Length'] = Buffer.byteLength(jsonData);
            }

            const req = http.request(options, (res) => {
                let body = '';
                res.on('data', (chunk) => {
                    body += chunk;
                });
                res.on('end', () => {
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        body: body
                    });
                });
            });

            req.on('error', (error) => {
                reject(error);
            });

            if (data) {
                req.write(JSON.stringify(data));
            }

            req.end();
        });
    }

    cleanup() {
        const filesToClean = [
            '/tmp/php-fpm-test.pid',
            '/tmp/php-fpm-test-error.log',
            '/tmp/php-fpm-test.sock',
            '/tmp/duplicator_php_test_errors.log',
            '/tmp/duplicator_caddy_test_errors.log',
            '/tmp/duplicator_caddy_test_access.log',
            path.join(__dirname, '..', '..', 'php-fpm.test.conf'),
            path.join(__dirname, '..', '..', 'Caddyfile.test')
        ];

        filesToClean.forEach(file => {
            try {
                if (fs.existsSync(file)) {
                    fs.unlinkSync(file);
                }
            } catch (error) {
                console.warn(`Impossible de supprimer ${file}:`, error.message);
            }
        });

        // Nettoyer le répertoire de sessions
        const sessionDir = '/tmp/duplicator_test_sessions';
        if (fs.existsSync(sessionDir)) {
            try {
                const files = fs.readdirSync(sessionDir);
                files.forEach(file => {
                    const filePath = path.join(sessionDir, file);
                    if (fs.statSync(filePath).isFile()) {
                        fs.unlinkSync(filePath);
                    }
                });
            } catch (error) {
                console.warn(`Impossible de nettoyer ${sessionDir}:`, error.message);
            }
        }
    }
}

module.exports = TestServer;

