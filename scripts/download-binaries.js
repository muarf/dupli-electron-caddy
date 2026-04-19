const https = require('https');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Configuration des URLs (Exemple - À adapter vers des sources stables/miroirs)
const BINARIES = {
    'win-x64': {
        magick: 'https://imagemagick.org/archive/binaries/ImageMagick-7.1.1-43-portable-Q16-x64.zip',
        gs: 'https://github.com/ArtifexSoftware/ghostpdl-downloads/releases/download/gs10031/ghostscript-10.03.1-windows-x64.zip',
    },
    'linux-x64': {
        magick: 'https://imagemagick.org/archive/binaries/magick', // ImageMagick AppImage/Static
        gs: 'https://github.com/vnoel/ghostscript-static/releases/download/v10.02.1/gs-10.02.1-linux-x86_64.tar.gz'
    }
};

async function downloadFile(url, filepath) {
    console.log(`Téléchargement: ${url} -> ${filepath}`);
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(filepath);
        https.get(url, (response) => {
            if (response.statusCode === 301 || response.statusCode === 302) {
                return downloadFile(response.headers.location, filepath).then(resolve).catch(reject);
            }
            if (response.statusCode !== 200) {
                reject(new Error(`HTTP ${response.statusCode}`));
                return;
            }
            response.pipe(file);
            file.on('finish', () => { file.close(); resolve(); });
        }).on('error', (err) => {
            fs.unlink(filepath, () => {});
            reject(err);
        });
    });
}

function extract(filepath, targetDir) {
    console.log(`Extraction: ${filepath} -> ${targetDir}`);
    if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });
    
    if (filepath.endsWith('.zip')) {
        execSync(`unzip -o "${filepath}" -d "${targetDir}"`, { stdio: 'inherit' });
    } else if (filepath.endsWith('.tar.gz')) {
        execSync(`tar -xzf "${filepath}" -C "${targetDir}"`, { stdio: 'inherit' });
    }
}

async function main() {
    const platform = process.platform === 'win32' ? 'win' : process.platform;
    const arch = process.arch === 'x64' ? 'x64' : (process.arch === 'arm64' ? 'arm64' : process.arch);
    const key = `${platform}-${arch}`;

    console.log(`### Setup binaires pour ${key} ###`);

    const binDir = path.join(__dirname, '..', 'bin', key);
    if (!fs.existsSync(binDir)) fs.mkdirSync(binDir, { recursive: true });

    if (key === 'linux-arm64') {
        console.log("INFO: Pour Linux ARM64, nous recommandons d'utiliser les binaires système.");
        console.log("COMMANDE: sudo apt-get install ghostscript imagemagick");
        console.log("Le moteur de conversion utilisera automatiquement /usr/bin/gs et magick.");
        return;
    }

    const config = BINARIES[key];
    if (!config) {
        console.log(`WARN: Pas de configuration automatique pour ${key}. Fallback système.`);
        return;
    }

    // Ici on ajouterait la logique de téléchargement réelle pour x64/Win
    // Pour l'instant on prépare la structure.
    console.log("Structure bin/ préparée. Prêt pour les téléchargements.");
}

if (require.main === module) {
    main().catch(console.error);
}
