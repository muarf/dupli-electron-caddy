const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Détection de la cible Rust courante pour la machine locale
function getTargetTriple() {
    const args = process.argv.slice(2);
    if (args.length > 0 && args[0]) {
        return args[0];
    }
    
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
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_mac_amd64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    },
    'aarch64-apple-darwin': {
        url: 'https://github.com/caddyserver/caddy/releases/download/v2.7.6/caddy_2.7.6_mac_arm64.tar.gz',
        archiveType: 'tar.gz',
        binName: 'caddy'
    }
};

// Configuration de PHP par architecture
const PHP_CONFIG = {
    'x86_64-pc-windows-msvc': {
        url: 'https://windows.php.net/downloads/releases/archives/php-8.4.13-nts-Win32-vs17-x64.zip',
        archiveType: 'zip',
        binName: 'php.exe'
    },
    // Pour Linux et macOS, on copie le binaire PHP système local
    'x86_64-unknown-linux-gnu': { useSystem: true, binName: 'php' },
    'aarch64-unknown-linux-gnu': { useSystem: true, binName: 'php' },
    'x86_64-apple-darwin': { useSystem: true, binName: 'php' },
    'aarch64-apple-darwin': { useSystem: true, binName: 'php' },
    'universal-apple-darwin': { useSystem: true, binName: 'php' }
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
    if (triple === 'universal-apple-darwin') {
        const outputName = `caddy-${triple}`;
        const outputPath = path.join(binariesDir, outputName);
        if (fs.existsSync(outputPath) && fs.statSync(outputPath).size > 1024) {
            console.log(`[Caddy] Binaire déjà présent : ${outputName}`);
            return;
        }
        
        console.log(`[Caddy] Construction du binaire universel pour macOS...`);
        const tmpDir = path.join(binariesDir, 'tmp_caddy_universal');
        if (!fs.existsSync(tmpDir)) fs.mkdirSync(tmpDir, { recursive: true });

        try {
            const amd64Config = CADDY_URLS['x86_64-apple-darwin'];
            const arm64Config = CADDY_URLS['aarch64-apple-darwin'];
            
            const amd64Archive = path.join(tmpDir, 'caddy_amd64.tar.gz');
            const arm64Archive = path.join(tmpDir, 'caddy_arm64.tar.gz');
            
            await downloadFile(amd64Config.url, amd64Archive);
            await downloadFile(arm64Config.url, arm64Archive);
            
            const tmpAmd64Dir = path.join(tmpDir, 'amd64');
            const tmpArm64Dir = path.join(tmpDir, 'arm64');
            
            extractArchive(amd64Archive, tmpAmd64Dir, 'tar.gz');
            extractArchive(arm64Archive, tmpArm64Dir, 'tar.gz');
            
            const caddyAmd64 = path.join(tmpAmd64Dir, 'caddy');
            const caddyArm64 = path.join(tmpArm64Dir, 'caddy');
            
            console.log(`[Caddy] Fusion des binaires via lipo...`);
            execSync(`lipo -create "${caddyAmd64}" "${caddyArm64}" -output "${outputPath}"`, { stdio: 'pipe' });
            fs.chmodSync(outputPath, '755');
            console.log(`[Caddy] Installé avec succès sous : ${outputName}`);
        } catch (e) {
            console.error(`[Caddy] Erreur lors de la création du binaire universel :`, e.message);
        } finally {
            if (fs.existsSync(tmpDir)) fs.rmSync(tmpDir, { recursive: true, force: true });
        }
        return;
    }

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

    const outputName = `dupli-php-${triple}${triple.includes('windows') ? '.exe' : ''}`;
    const outputPath = path.join(binariesDir, outputName);

    if (fs.existsSync(outputPath) && fs.statSync(outputPath).size > 10) {
        console.log(`[PHP] Binaire déjà présent : ${outputName}`);
        return;
    }

    if (config.useSystem) {
        console.log(`[PHP] Génération du script wrapper PHP pour la cible ${triple}...`);
        try {
            // 1. Écrire le script wrapper pour php
            const phpWrapperContent = `#!/bin/sh\nexec php "$@"\n`;
            fs.writeFileSync(outputPath, phpWrapperContent);
            fs.chmodSync(outputPath, '755');
            console.log(`[PHP] Wrapper PHP créé avec succès : ${outputName}`);
            
            // 2. Écrire le script wrapper pour php-fpm (nécessaire sous Linux)
            const fpmOutputName = `php-fpm-${triple}`;
            const fpmOutputPath = path.join(binariesDir, fpmOutputName);
            if (!fs.existsSync(fpmOutputPath)) {
                console.log(`[PHP-FPM] Génération du script wrapper PHP-FPM pour la cible ${triple}...`);
                const fpmWrapperContent = `#!/bin/sh\nexec php-fpm "$@"\n`;
                fs.writeFileSync(fpmOutputPath, fpmWrapperContent);
                fs.chmodSync(fpmOutputPath, '755');
                console.log(`[PHP-FPM] Wrapper PHP-FPM créé avec succès : ${fpmOutputName}`);
            }
        } catch (e) {
            console.error(`[PHP] Erreur de génération des wrappers Unix :`, e.message);
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

            // Copier toutes les DLLs de support de PHP
            const files = fs.readdirSync(tmpDir);
            for (const file of files) {
                if (file.toLowerCase().endsWith('.dll')) {
                    fs.copyFileSync(path.join(tmpDir, file), path.join(binariesDir, file));
                    console.log(`[PHP] Dépendance copiée : ${file}`);
                }
            }

            // Copier également le dossier ext entier s'il existe
            const extSrc = path.join(tmpDir, 'ext');
            if (fs.existsSync(extSrc)) {
                fs.cpSync(extSrc, path.join(binariesDir, 'ext'), { recursive: true });
                console.log(`[PHP] Dossier des extensions 'ext' copié.`);
            }

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
