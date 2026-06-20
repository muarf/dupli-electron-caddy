const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Détection de la cible Rust courante pour la machine locale
function getTargetTriple() {
    const platform = process.platform;
    const arch = process.arch;

    if (platform === 'win32') {
        if (arch === 'x64') return 'x86_64-pc-windows-msvc';
        if (arch === 'ia32') return 'i686-pc-windows-msvc';
        if (arch === 'arm64') return 'aarch64-pc-windows-msvc';
    } else if (platform === 'linux') {
        if (arch === 'x64') return 'x86_64-unknown-linux-gnu';
        if (arch === 'arm64') return 'aarch64-unknown-linux-gnu';
        if (arch === 'arm') return 'armv7-unknown-linux-gnueabihf';
    } else if (platform === 'darwin') {
        if (arch === 'x64') return 'x86_64-apple-darwin';
        if (arch === 'arm64') return 'aarch64-apple-darwin';
    }
    throw new Error(`Plateforme non supportée : ${platform}-${arch}`);
}

// Configuration des URLs de téléchargement de Caddy par architecture
const CADDY_URLS = {
    'x86_64-pc-windows-msvc': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_windows_amd64.zip',
        archiveType: 'zip',
        binName: 'caddy.exe'
    },
    'x86_64-unknown-linux-gnu': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_linux_amd64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    },
    'aarch64-unknown-linux-gnu': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_linux_arm64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    },
    'x86_64-apple-darwin': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_macOS_amd64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    },
    'aarch64-apple-darwin': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_macOS_arm64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    }
};

// Configuration de PHP par architecture
const PHP_CONFIG = {
    'x86_64-pc-windows-msvc': {
        url: 'https://windows.php.net/downloads/releases/php-8.4.13-nts-Win32-vs17-x64.zip',
        archiveType: 'zip',
        binName: 'php.exe'
    },
    // Pour Linux et macOS, on copie le binaire PHP système local
    'x86_64-unknown-linux-gnu': { useSystem: true, binName: 'php' },
    'aarch64-unknown-linux-gnu': { useSystem: true, binName: 'php' },
    'x86_64-apple-darwin': { useSystem: true, binName: 'php' },
    'aarch64-apple-darwin': { useSystem: true, binName: 'php' }
};

// Télécharge un fichier de manière asynchrone avec support des redirections
function downloadFile(url, filepath) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filepath);
        https.get(url, (response) => {
            if (response.statusCode === 301 || response.statusCode === 302) {
                file.close();
                fs.unlink(filepath, () => {});
                return downloadFile(response.headers.location, filepath).then(resolve).catch(reject);
            }
            if (response.statusCode !== 200) {
                file.close();
                fs.unlink(filepath, () => {});
                return reject(new Error(`Erreur HTTP ${response.statusCode} pour l'URL : ${url}`));
            }
            response.pipe(file);
            file.on('finish', () => {
                file.close();
                resolve();
            });
        }).on('error', (err) => {
            file.close();
            fs.unlink(filepath, () => {});
            reject(err);
        });
    });
}

// Extrait une archive (.zip ou .tar.gz) vers un dossier temporaire
function extractArchive(archivePath, targetDir, type) {
    if (!fs.existsSync(targetDir)) {
        fs.mkdirSync(targetDir, { recursive: true });
    }
    if (type === 'zip') {
        execSync(`unzip -o "${archivePath}" -d "${targetDir}"`, { stdio: 'pipe' });
    } else if (type === 'tar.gz') {
        execSync(`tar -xzf "${archivePath}" -C "${targetDir}"`, { stdio: 'pipe' });
    }
}

async function setupCaddy(triple, binariesDir) {
    const config = CADDY_URLS[triple];
    if (!config) {
        console.warn(`[Caddy] Pas d'URL de téléchargement pour le triple : ${triple}`);
        return;
    }

    const outputName = `caddy-${triple}${triple.includes('windows') ? '.exe' : ''}`;
    const outputPath = path.join(binariesDir, outputName);
    
    // Éviter de retélécharger si le fichier existe déjà et fait une taille correcte
    if (fs.existsSync(outputPath) && fs.statSync(outputPath).size > 1024) {
        console.log(`[Caddy] Binaire déjà présent : ${outputName}`);
        return;
    }

    const tmpDir = path.join(binariesDir, 'tmp_caddy');
    if (!fs.existsSync(tmpDir)) fs.mkdirSync(tmpDir, { recursive: true });

    const archivePath = path.join(tmpDir, `caddy_${triple}.${config.archiveType}`);
    
    try {
        console.log(`[Caddy] Téléchargement pour la cible ${triple}...`);
        await downloadFile(config.url, archivePath);
        
        console.log(`[Caddy] Extraction de l'archive...`);
        extractArchive(archivePath, tmpDir, config.archiveType);

        const extractedBin = path.join(tmpDir, config.binName);
        fs.copyFileSync(extractedBin, outputPath);
        if (process.platform !== 'win32') {
            fs.chmodSync(outputPath, '755');
        }

        console.log(`[Caddy] Installé avec succès sous : ${outputName}`);
    } catch (e) {
        console.error(`[Caddy] Erreur :`, e.message);
    } finally {
        // Nettoyage du dossier temporaire
        if (fs.existsSync(tmpDir)) {
            fs.rmSync(tmpDir, { recursive: true, force: true });
        }
    }
}

async function setupPhp(triple, binariesDir) {
    const config = PHP_CONFIG[triple];
    if (!config) {
        console.warn(`[PHP] Pas de configuration pour le triple : ${triple}`);
        return;
    }

    const outputName = `php-${triple}${triple.includes('windows') ? '.exe' : ''}`;
    const outputPath = path.join(binariesDir, outputName);

    if (fs.existsSync(outputPath) && fs.statSync(outputPath).size > 1024) {
        console.log(`[PHP] Binaire déjà présent : ${outputName}`);
        return;
    }

    if (config.useSystem) {
        console.log(`[PHP] Utilisation du PHP système pour la cible ${triple}...`);
        try {
            // 1. Localiser le binaire php local
            const phpSystemPath = execSync('which php', { encoding: 'utf8' }).trim();
            
            // 2. Le copier vers le dossier de destination
            fs.copyFileSync(phpSystemPath, outputPath);
            fs.chmodSync(outputPath, '755');
            console.log(`[PHP] Copié depuis le système vers : ${outputName}`);
            
            // 3. Essayer de trouver et copier php-fpm (nécessaire sous Linux)
            const fpmOutputName = `php-fpm-${triple}`;
            const fpmOutputPath = path.join(binariesDir, fpmOutputName);
            if (!fs.existsSync(fpmOutputPath)) {
                try {
                    const phpFpmSystemPath = execSync('which php-fpm || which php-fpm8.2 || which php-fpm8.1 || which php-fpm8.3', { encoding: 'utf8' }).trim();
                    if (fs.existsSync(phpFpmSystemPath)) {
                        fs.copyFileSync(phpFpmSystemPath, fpmOutputPath);
                        fs.chmodSync(fpmOutputPath, '755');
                        console.log(`[PHP-FPM] Copié depuis le système vers : ${fpmOutputName}`);
                    }
                } catch (e) {
                    console.log(`[PHP-FPM] Non détecté sur le système hôte, seul le serveur PHP de base sera disponible.`);
                }
            }
        } catch (e) {
            console.error(`[PHP] Erreur de détection locale : PHP n'est pas installé sur la machine.`);
        }
    } else {
        // Téléchargement Windows
        const tmpDir = path.join(binariesDir, 'tmp_php');
        if (!fs.existsSync(tmpDir)) fs.mkdirSync(tmpDir, { recursive: true });

        const archivePath = path.join(tmpDir, `php_${triple}.${config.archiveType}`);

        try {
            console.log(`[PHP] Téléchargement de PHP Windows...`);
            await downloadFile(config.url, archivePath);
            
            console.log(`[PHP] Extraction de l'archive...`);
            extractArchive(archivePath, tmpDir, config.archiveType);

            const extractedBin = path.join(tmpDir, config.binName);
            fs.copyFileSync(extractedBin, outputPath);

            console.log(`[PHP] Installé avec succès sous : ${outputName}`);
        } catch (e) {
            console.error(`[PHP] Erreur de téléchargement :`, e.message);
        } finally {
            if (fs.existsSync(tmpDir)) {
                fs.rmSync(tmpDir, { recursive: true, force: true });
            }
        }
    }
}

async function main() {
    const triple = getTargetTriple();
    console.log(`### Configuration des Sidecars pour la cible : ${triple} ###`);

    const binariesDir = path.join(__dirname, '..', 'src-tauri', 'binaries');
    if (!fs.existsSync(binariesDir)) {
        fs.mkdirSync(binariesDir, { recursive: true });
    }

    await setupCaddy(triple, binariesDir);
    await setupPhp(triple, binariesDir);
    
    console.log(`### Sidecars configurés dans ${binariesDir} ###`);
}

main().catch(console.error);
