const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Configuration des téléchargements Caddy
const CADDY_VERSIONS = {
    'linux': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_linux_amd64.tar.gz',
        filename: 'caddy_linux.tar.gz',
        binary: 'caddy'
    },
    'windows': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_windows_amd64.zip',
        filename: 'caddy_windows.zip',
        binary: 'caddy.exe'
    },
    'darwin': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_mac_amd64.tar.gz',
        filename: 'caddy_macos.tar.gz',
        binary: 'caddy'
    }
};

// Configuration des téléchargements PHP
const PHP_VERSIONS = {
    'linux': {
        url: 'https://github.com/muarf/php-static-builder/releases/download/v8.2.14-static/php-static-amd64-linux.tar.gz',
        filename: 'php_linux.tar.gz',
        binary: 'php',
        fpm: 'php-fpm',
        useStatic: true // Utiliser les binaires statiques
    },
    'windows': {
        url: 'https://windows.php.net/downloads/releases/php-8.4.13-nts-Win32-vs17-x64.zip',
        filename: 'php_windows.zip',
        binary: 'php.exe',
        fpm: 'php-fpm.exe'
    },
    'darwin': {
        url: '',
        filename: '',
        binary: 'php',
        fpm: 'php-fpm',
        useSystem: true // Utiliser le PHP système sur macOS (GitHub Actions)
    }
};

// Fonction pour télécharger un fichier
function downloadFile(url, filepath) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filepath);
        
        const request = https.get(url, (response) => {
            // Suivre les redirections
            if (response.statusCode === 301 || response.statusCode === 302) {
                const redirectUrl = response.headers.location;
                if (redirectUrl) {
                    file.close();
                    fs.unlink(filepath, () => {}); // Supprimer le fichier partiel
                    return downloadFile(redirectUrl, filepath).then(resolve).catch(reject);
                }
            }
            
            if (response.statusCode !== 200) {
                reject(new Error(`HTTP ${response.statusCode}: ${response.statusMessage}`));
                return;
            }
            
            response.pipe(file);
            
            file.on('finish', () => {
                file.close();
                resolve();
            });
            
            file.on('error', (err) => {
                fs.unlink(filepath, () => {}); // Supprimer le fichier partiel
                reject(err);
            });
        });
        
        request.on('error', (err) => {
            reject(err);
        });
    });
}

// Fonction pour extraire un fichier
function extractFile(filepath, extractPath) {
    try {
        if (filepath.endsWith('.tar.gz')) {
            execSync(`tar -xzf "${filepath}" -C "${extractPath}"`, { stdio: 'inherit' });
        } else if (filepath.endsWith('.zip')) {
            execSync(`unzip -o "${filepath}" -d "${extractPath}"`, { stdio: 'inherit' });
        }
        return true;
    } catch (error) {
        console.error('Erreur lors de l\'extraction:', error.message);
        return false;
    }
}

// Fonction pour télécharger PHP
async function downloadPhp() {
    const platform = process.platform;
    // Mapper win32 vers windows pour la compatibilité
    const platformKey = platform === 'win32' ? 'windows' : platform;
    const config = PHP_VERSIONS[platformKey];
    
    if (!config) {
        console.error(`Plateforme non supportée: ${platform} (mappé vers ${platformKey})`);
        process.exit(1);
    }
    
    console.log(`Téléchargement de PHP pour ${platform}...`);
    
    const phpDir = path.join(__dirname, '..', 'php');
    const downloadPath = path.join(phpDir, config.filename);
    const binaryPath = path.join(phpDir, config.binary);
    const fpmPath = path.join(phpDir, config.fpm);
    
    // Créer le dossier php s'il n'existe pas
    if (!fs.existsSync(phpDir)) {
        fs.mkdirSync(phpDir, { recursive: true });
    }
    
    try {
        // Si useStatic est true, utiliser les binaires statiques
        if (config.useStatic) {
            console.log(`Utilisation des binaires statiques pour ${platform}`);
            
            // Télécharger les binaires statiques
            console.log(`Téléchargement depuis: ${config.url}`);
            await downloadFile(config.url, downloadPath);
            console.log('Téléchargement terminé');
            
            // Extraire le fichier
            console.log('Extraction en cours...');
            if (extractFile(downloadPath, phpDir)) {
                console.log('Extraction terminée');
                
                // Déplacer les binaires vers la bonne location
                if (platform !== 'win32') {
                    const extractedPhpPath = path.join(phpDir, 'bin', 'php');
                    
                    if (fs.existsSync(extractedPhpPath)) {
                        // Supprimer le fichier/dossier cible s'il existe
                        if (fs.existsSync(binaryPath)) {
                            if (fs.statSync(binaryPath).isDirectory()) {
                                fs.rmSync(binaryPath, { recursive: true, force: true });
                            } else {
                                fs.unlinkSync(binaryPath);
                            }
                        }
                        fs.copyFileSync(extractedPhpPath, binaryPath);
                        fs.chmodSync(binaryPath, '755');
                        console.log(`PHP copié vers: ${binaryPath}`);
                    }
                    
                    // PHP-FPM n'est pas inclus dans les binaires statiques
                    console.log('PHP-FPM non disponible dans les binaires statiques, utilisation du serveur PHP intégré');
                }
                
                // Supprimer le fichier d'archive
                fs.unlinkSync(downloadPath);
                
                console.log(`PHP statique installé avec succès: ${binaryPath}`);
            } else {
                throw new Error('Échec de l\'extraction');
            }
        } else if (config.useSystem) {
            console.log(`Utilisation du PHP système pour ${platform}`);
            
            // Vérifier que PHP est disponible
            const { execSync } = require('child_process');
            try {
                execSync('php --version', { stdio: 'pipe' });
                console.log('PHP système détecté et fonctionnel');
            } catch (error) {
                console.error('PHP système non disponible');
                throw new Error('PHP système non disponible');
            }
            
            // Créer des liens symboliques vers le PHP système
            if (platform === 'win32') {
                // Sur Windows, on garde les binaires existants
                console.log('Binaires Windows déjà présents');
            } else {
                // Sur Unix, créer des liens symboliques
                if (!fs.existsSync(binaryPath)) {
                    // Essayer plusieurs chemins pour PHP selon l'installation
                    const possiblePaths = ['/opt/homebrew/bin/php', '/usr/bin/php', '/usr/local/bin/php'];
                    let phpPath = null;
                    
                    for (const path of possiblePaths) {
                        if (fs.existsSync(path)) {
                            phpPath = path;
                            break;
                        }
                    }
                    
                    if (phpPath) {
                        fs.symlinkSync(phpPath, binaryPath);
                        console.log(`PHP lié depuis: ${phpPath} -> ${binaryPath}`);
                    } else {
                        throw new Error('PHP non trouvé dans les chemins standards');
                    }
                }
                if (!fs.existsSync(fpmPath)) {
                    try {
                        // Essayer plusieurs chemins pour php-fpm selon l'installation
                        const possibleFpmPaths = ['/opt/homebrew/bin/php-fpm', '/usr/bin/php-fpm', '/usr/local/bin/php-fpm'];
                        let phpFpmPath = null;
                        
                        for (const path of possibleFpmPaths) {
                            if (fs.existsSync(path)) {
                                phpFpmPath = path;
                                break;
                            }
                        }
                        
                        if (phpFpmPath) {
                            fs.symlinkSync(phpFpmPath, fpmPath);
                            console.log(`php-fpm lié depuis: ${phpFpmPath} -> ${fmpPath}`);
                            console.log('php-fpm lié avec succès');
                        } else {
                            console.log('php-fpm non disponible, utilisation du serveur PHP intégré');
                        }
                    } catch (error) {
                        // php-fpm peut ne pas être disponible, on continue sans
                        console.log('php-fpm non disponible, utilisation du serveur PHP intégré');
                    }
                }
            }
            
            console.log(`PHP système configuré: ${binaryPath}`);
        } else {
            // Télécharger PHP
            console.log(`Téléchargement depuis: ${config.url}`);
            await downloadFile(config.url, downloadPath);
            console.log('Téléchargement terminé');
            
            // Extraire le fichier
            console.log('Extraction en cours...');
            if (extractFile(downloadPath, phpDir)) {
                console.log('Extraction terminée');
                
                // Rendre les binaires exécutables sur Unix
                if (platform !== 'win32') {
                    if (fs.existsSync(binaryPath)) {
                        fs.chmodSync(binaryPath, '755');
                    }
                    if (fs.existsSync(fpmPath)) {
                        fs.chmodSync(fpmPath, '755');
                    }
                }
                
                // Supprimer le fichier d'archive
                fs.unlinkSync(downloadPath);
                
                console.log(`PHP installé avec succès: ${binaryPath}`);
            } else {
                throw new Error('Échec de l\'extraction');
            }
        }
        
    } catch (error) {
        console.error('Erreur lors du téléchargement de PHP:', error.message);
        process.exit(1);
    }
}

// Fonction principale
async function downloadCaddy() {
    const platform = process.platform;
    // Mapper win32 vers windows pour la compatibilité
    const platformKey = platform === 'win32' ? 'windows' : platform;
    const config = CADDY_VERSIONS[platformKey];
    
    if (!config) {
        console.error(`Plateforme non supportée: ${platform} (mappé vers ${platformKey})`);
        process.exit(1);
    }
    
    console.log(`Téléchargement de Caddy pour ${platform}...`);
    
    const caddyDir = path.join(__dirname, '..', 'caddy');
    const downloadPath = path.join(caddyDir, config.filename);
    const binaryPath = path.join(caddyDir, config.binary);
    
    // Créer le dossier caddy s'il n'existe pas
    if (!fs.existsSync(caddyDir)) {
        fs.mkdirSync(caddyDir, { recursive: true });
    }
    
    try {
        // Télécharger Caddy
        console.log(`Téléchargement depuis: ${config.url}`);
        await downloadFile(config.url, downloadPath);
        console.log('Téléchargement terminé');
        
        // Extraire le fichier
        console.log('Extraction en cours...');
        if (extractFile(downloadPath, caddyDir)) {
            console.log('Extraction terminée');
            
            // Rendre le binaire exécutable sur Unix
            if (platform !== 'win32') {
                fs.chmodSync(binaryPath, '755');
            }
            
            // Supprimer le fichier d'archive
            fs.unlinkSync(downloadPath);
            
            console.log(`Caddy installé avec succès: ${binaryPath}`);
        } else {
            throw new Error('Échec de l\'extraction');
        }
        
    } catch (error) {
        console.error('Erreur lors du téléchargement de Caddy:', error.message);
        process.exit(1);
    }
}

// Fonction pour télécharger tout
async function downloadAll() {
    console.log('Téléchargement de tous les composants...');
    await downloadCaddy();
    await downloadPhp();
    console.log('Tous les composants téléchargés avec succès!');
}

// Exécuter si appelé directement
if (require.main === module) {
    downloadAll();
}

module.exports = { downloadCaddy, downloadPhp, downloadAll, CADDY_VERSIONS, PHP_VERSIONS };
