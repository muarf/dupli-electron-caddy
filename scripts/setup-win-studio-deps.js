/**
 * scripts/setup-win-studio-deps.js
 *
 * Prépare automatiquement les dépendances Windows pour le Studio Duplicator.
 * Exécuté UNIQUEMENT sur un runner Windows (windows-2022) avant le build Tauri.
 *
 * Installe dans bin/win-x64/ :
 *   - Python 3.11 embeddable + pip + pdf2docx + python-docx + ocrmypdf
 *   - tesseract.exe + DLLs (via Chocolatey) + tessdata/fra.traineddata
 *   - pdftotext.exe (via Chocolatey / poppler)
 *   - exiftool.exe (via Chocolatey)
 *   - unpaper.exe (via Chocolatey)
 *
 * Utilisation : node scripts/setup-win-studio-deps.js
 */

const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');
const { execSync, spawnSync } = require('child_process');

// ─── Configuration des versions ─────────────────────────────────────────────

const PYTHON_VERSION   = '3.11.9';
const PYTHON_URL       = `https://www.python.org/ftp/python/${PYTHON_VERSION}/python-${PYTHON_VERSION}-embed-amd64.zip`;
const GET_PIP_URL      = 'https://bootstrap.pypa.io/get-pip.py';

// tessdata : uniquement le français et l'anglais (base ocrmypdf)
const TESSDATA_BASE    = 'https://github.com/tesseract-ocr/tessdata/raw/main';
const TESSDATA_LANGS   = ['fra', 'eng'];

// poppler-windows : fournit pdftotext.exe
const POPPLER_VERSION  = '24.08.0-0';
const POPPLER_URL      = `https://github.com/oschwartz10612/poppler-windows/releases/download/v${POPPLER_VERSION}/Release-${POPPLER_VERSION}.zip`;

// Packages pip Studio
const PIP_PACKAGES     = ['pdf2docx', 'python-docx', 'ocrmypdf'];

// ─── Chemins ────────────────────────────────────────────────────────────────

const ROOT      = path.join(__dirname, '..');
const WIN_BIN   = path.join(ROOT, 'bin', 'win-x64');
const PYTHON_DIR = path.join(WIN_BIN, 'python');
const TESSDATA  = path.join(WIN_BIN, 'tessdata');
const TMP       = path.join(ROOT, 'tmp_studio_deps_win');

// ─── Utilitaires ─────────────────────────────────────────────────────────────

function ensureDir(dir) {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

function downloadFile(url, dest) {
    return new Promise((resolve, reject) => {
        const proto = url.startsWith('https') ? https : http;
        const file  = fs.createWriteStream(dest);
        proto.get(url, (res) => {
            if (res.statusCode === 301 || res.statusCode === 302) {
                file.close();
                fs.unlinkSync(dest);
                return downloadFile(res.headers.location, dest).then(resolve).catch(reject);
            }
            if (res.statusCode !== 200) {
                file.close();
                if (fs.existsSync(dest)) fs.unlinkSync(dest);
                return reject(new Error(`HTTP ${res.statusCode} → ${url}`));
            }
            res.pipe(file);
            file.on('finish', () => { file.close(); resolve(); });
        }).on('error', err => {
            file.close();
            if (fs.existsSync(dest)) fs.unlinkSync(dest);
            reject(err);
        });
    });
}

function extractZip(zipPath, destDir) {
    ensureDir(destDir);
    execSync(
        `powershell -Command "Expand-Archive -Path '${zipPath}' -DestinationPath '${destDir}' -Force"`,
        { stdio: 'pipe' }
    );
}

function findFile(dir, filename) {
    if (!fs.existsSync(dir)) return null;
    for (const entry of fs.readdirSync(dir)) {
        const full = path.join(dir, entry);
        try {
            const stat = fs.statSync(full);
            if (entry.toLowerCase() === filename.toLowerCase()) return full;
            if (stat.isDirectory()) {
                const found = findFile(full, filename);
                if (found) return found;
            }
        } catch (_) { /* skip inaccessible */ }
    }
    return null;
}

function copyFilesFlat(srcDir, destDir, extensions) {
    if (!fs.existsSync(srcDir)) return;
    for (const entry of fs.readdirSync(srcDir)) {
        try {
            const src  = path.join(srcDir, entry);
            const stat = fs.statSync(src);
            if (stat.isFile()) {
                const ext = path.extname(entry).toLowerCase();
                if (!extensions || extensions.includes(ext)) {
                    fs.copyFileSync(src, path.join(destDir, entry));
                }
            }
        } catch (_) { /* skip */ }
    }
}

function log(section, msg) {
    console.log(`[${section}] ${msg}`);
}

// ─── Python embeddable ───────────────────────────────────────────────────────

async function setupPython() {
    const pythonExe = path.join(PYTHON_DIR, 'python.exe');

    // Vérifier si Python est déjà opérationnel avec les bons packages
    if (fs.existsSync(pythonExe)) {
        const check = spawnSync(pythonExe, ['-c', 'import pdf2docx, docx, ocrmypdf; print("ok")'], { encoding: 'utf8' });
        if (check.stdout && check.stdout.includes('ok')) {
            log('Python', `✅ Déjà installé avec les packages Studio. Skip.`);
            return;
        }
    }

    ensureDir(TMP);
    const zipPath = path.join(TMP, 'python-embed.zip');

    log('Python', `Téléchargement Python ${PYTHON_VERSION} embeddable...`);
    await downloadFile(PYTHON_URL, zipPath);

    log('Python', 'Extraction...');
    ensureDir(PYTHON_DIR);
    extractZip(zipPath, PYTHON_DIR);

    // Activer site.py : décommenter "#import site" dans le fichier ._pth
    // Sans ça, pip n'est pas importable dans l'embeddable
    const pthFile = fs.readdirSync(PYTHON_DIR).find(f => f.endsWith('._pth'));
    if (pthFile) {
        const pthPath = path.join(PYTHON_DIR, pthFile);
        let content   = fs.readFileSync(pthPath, 'utf8');
        content = content.replace('#import site', 'import site');
        fs.writeFileSync(pthPath, content, 'utf8');
        log('Python', `Activé site.py dans ${pthFile}`);
    } else {
        log('Python', '⚠️ Fichier ._pth non trouvé — pip risque de ne pas fonctionner.');
    }

    // Installer pip via get-pip.py
    const getPipPath = path.join(TMP, 'get-pip.py');
    log('Python', 'Téléchargement get-pip.py...');
    await downloadFile(GET_PIP_URL, getPipPath);

    log('Python', 'Installation de pip...');
    execSync(`"${pythonExe}" "${getPipPath}"`, { stdio: 'inherit' });

    // Installer les packages Studio
    log('Python', `Installation des packages : ${PIP_PACKAGES.join(', ')}...`);
    execSync(
        `"${pythonExe}" -m pip install --no-warn-script-location ${PIP_PACKAGES.join(' ')}`,
        { stdio: 'inherit' }
    );

    log('Python', `✅ Python + packages installés dans bin/win-x64/python/`);
}

// ─── Tesseract (via Chocolatey) ───────────────────────────────────────────────

async function setupTesseract() {
    const tesseractExe = path.join(WIN_BIN, 'tesseract.exe');
    const chocoDir     = 'C:\\Program Files\\Tesseract-OCR';

    if (fs.existsSync(tesseractExe) && fs.statSync(tesseractExe).size > 10000) {
        log('Tesseract', '✅ tesseract.exe déjà présent. Skip.');
    } else {
        log('Tesseract', 'Installation via Chocolatey...');
        execSync('choco install tesseract -y --no-progress', { stdio: 'inherit' });

        if (!fs.existsSync(path.join(chocoDir, 'tesseract.exe'))) {
            throw new Error(`Tesseract non trouvé dans ${chocoDir} après installation.`);
        }

        // Copier tesseract.exe + toutes les DLLs nécessaires dans bin/win-x64/
        log('Tesseract', `Copie de tesseract.exe + DLLs vers bin/win-x64/...`);
        copyFilesFlat(chocoDir, WIN_BIN, ['.exe', '.dll']);
        log('Tesseract', '✅ tesseract.exe + DLLs copiés.');
    }

    // Télécharger les fichiers tessdata (fra + eng)
    ensureDir(TESSDATA);
    for (const lang of TESSDATA_LANGS) {
        const destFile = path.join(TESSDATA, `${lang}.traineddata`);
        if (fs.existsSync(destFile) && fs.statSync(destFile).size > 100000) {
            log('Tesseract', `✅ tessdata/${lang}.traineddata déjà présent.`);
            continue;
        }
        log('Tesseract', `Téléchargement tessdata/${lang}.traineddata...`);
        await downloadFile(`${TESSDATA_BASE}/${lang}.traineddata`, destFile);
        const sizeMb = (fs.statSync(destFile).size / 1024 / 1024).toFixed(1);
        log('Tesseract', `✅ ${lang}.traineddata (${sizeMb} Mo)`);
    }
}

// ─── pdftotext (via Chocolatey / poppler) ────────────────────────────────────

async function setupPdftotext() {
    const pdfToTextExe = path.join(WIN_BIN, 'pdftotext.exe');

    if (fs.existsSync(pdfToTextExe) && fs.statSync(pdfToTextExe).size > 10000) {
        log('pdftotext', '✅ pdftotext.exe déjà présent. Skip.');
        return;
    }

    // Essayer via Chocolatey d'abord (poppler)
    try {
        log('pdftotext', 'Installation via Chocolatey (poppler)...');
        execSync('choco install poppler -y --no-progress', { stdio: 'inherit' });

        // Chercher pdftotext.exe dans les dossiers courants de choco
        const chocoBin = findFile('C:\\ProgramData\\chocolatey\\lib\\poppler', 'pdftotext.exe')
                      || findFile('C:\\tools\\poppler', 'pdftotext.exe');
        if (chocoBin) {
            fs.copyFileSync(chocoBin, pdfToTextExe);
            log('pdftotext', `✅ pdftotext.exe copié depuis ${chocoBin}`);
            return;
        }
    } catch (e) {
        log('pdftotext', `Choco échoué (${e.message}), fallback ZIP...`);
    }

    // Fallback : téléchargement direct du ZIP poppler-windows
    ensureDir(TMP);
    const zipPath    = path.join(TMP, 'poppler.zip');
    const extractDir = path.join(TMP, 'poppler');

    log('pdftotext', 'Téléchargement poppler-windows...');
    await downloadFile(POPPLER_URL, zipPath);
    extractZip(zipPath, extractDir);

    const found = findFile(extractDir, 'pdftotext.exe');
    if (found) {
        fs.copyFileSync(found, pdfToTextExe);
        log('pdftotext', `✅ pdftotext.exe copié depuis ${found}`);
    } else {
        throw new Error('pdftotext.exe introuvable dans l\'archive poppler.');
    }
}

// ─── ExifTool (via Chocolatey) ────────────────────────────────────────────────

async function setupExiftool() {
    const exiftoolExe = path.join(WIN_BIN, 'exiftool.exe');

    if (fs.existsSync(exiftoolExe) && fs.statSync(exiftoolExe).size > 10000) {
        log('ExifTool', '✅ exiftool.exe déjà présent. Skip.');
        return;
    }

    log('ExifTool', 'Installation via Chocolatey...');
    execSync('choco install exiftool -y --no-progress', { stdio: 'inherit' });

    // Localiser le VRAI exécutable installé par choco (et non le shim de 75Ko dans \\bin\\)
    let src = null;
    const chocoLibDir = 'C:\\ProgramData\\chocolatey\\lib\\exiftool\\tools';
    
    if (fs.existsSync(chocoLibDir)) {
        src = findFile(chocoLibDir, 'exiftool.exe') || findFile(chocoLibDir, 'exiftool(-k).exe');
    }

    if (!src) {
        src = findFile('C:\\tools\\exiftool', 'exiftool.exe') || findFile('C:\\tools\\exiftool', 'exiftool(-k).exe');
    }

    if (src) {
        // Sécurité : vérifier que ce n'est pas un shim Chocolatey (qui fait ~75Ko)
        const size = fs.statSync(src).size;
        if (size < 150000) {
            log('ExifTool', `⚠️ Attention : le fichier trouvé (${size} octets) ressemble toujours à un shim !`);
        }
        fs.copyFileSync(src, exiftoolExe);
        log('ExifTool', `✅ exiftool.exe copié depuis ${src}`);
    } else {
        throw new Error('Le vrai exécutable exiftool.exe est introuvable après installation choco.');
    }
}

// ─── Unpaper (via Chocolatey) ────────────────────────────────────────────────

async function setupUnpaper() {
    const unpaperExe = path.join(WIN_BIN, 'unpaper.exe');

    if (fs.existsSync(unpaperExe) && fs.statSync(unpaperExe).size > 10000) {
        log('Unpaper', '✅ unpaper.exe déjà présent. Skip.');
        return;
    }

    log('Unpaper', 'Installation via Chocolatey...');
    try {
        execSync('choco install unpaper -y --no-progress', { stdio: 'inherit' });
    } catch (e) {
        log('Unpaper', `⚠️ Échec de choco install: ${e.message}`);
    }

    // Localiser le VRAI exécutable installé par choco (et non le shim dans \\bin\\)
    let src = null;
    const chocoLibDir = 'C:\\ProgramData\\chocolatey\\lib\\unpaper\\tools';
    
    if (fs.existsSync(chocoLibDir)) {
        src = findFile(chocoLibDir, 'unpaper.exe');
    }

    if (!src) {
        src = findFile('C:\\tools\\unpaper', 'unpaper.exe');
    }

    if (!src) {
        // En dernier recours, regarder dans bin si c'est le vrai binaire
        const binCandidate = 'C:\\ProgramData\\chocolatey\\bin\\unpaper.exe';
        if (fs.existsSync(binCandidate) && fs.statSync(binCandidate).size > 150000) {
            src = binCandidate;
        }
    }

    if (src) {
        const size = fs.statSync(src).size;
        if (size < 150000) {
            log('Unpaper', `⚠️ Attention : le fichier trouvé (${size} octets) ressemble à un shim !`);
        }
        fs.copyFileSync(src, unpaperExe);
        log('Unpaper', `✅ unpaper.exe copié depuis ${src}`);
    } else {
        log('Unpaper', '⚠️ unpaper.exe introuvable via choco. Téléchargement direct (Fallback Github)...');
        
        const UNPAPER_BASE_URL = 'https://raw.githubusercontent.com/Inc44/unpaper_windows/main';
        const UNPAPER_FILES = ['unpaper.exe', 'LIBBZ2-1.DLL', 'LIBWINPTHREAD-1.DLL', 'ZLIB1.DLL'];
        
        for (const file of UNPAPER_FILES) {
            log('Unpaper', `Téléchargement ${file}...`);
            await downloadFile(`${UNPAPER_BASE_URL}/${file}`, path.join(WIN_BIN, file));
        }
        
        if (fs.existsSync(unpaperExe) && fs.statSync(unpaperExe).size > 10000) {
            log('Unpaper', `✅ unpaper.exe et DLLs installés via fallback Github.`);
        } else {
            throw new Error('Échec du téléchargement fallback de unpaper.exe');
        }
    }
}

// ─── Nettoyage ───────────────────────────────────────────────────────────────

function cleanup() {
    if (fs.existsSync(TMP)) {
        fs.rmSync(TMP, { recursive: true, force: true });
        log('Cleanup', 'Dossier temporaire supprimé.');
    }
}

// ─── Main ────────────────────────────────────────────────────────────────────

async function main() {
    if (process.platform !== 'win32') {
        console.log('[setup-win-studio-deps] Script uniquement pour Windows → skip.');
        return;
    }

    console.log('\n### Préparation des dépendances Studio Windows ###\n');
    ensureDir(WIN_BIN);
    ensureDir(TMP);

    try {
        await setupPython();
        await setupTesseract();
        await setupPdftotext();
        await setupExiftool();
        await setupUnpaper();
    } finally {
        cleanup();
    }

    console.log('\n### ✅ Toutes les dépendances Studio Windows sont prêtes. ###\n');
}

main().catch(err => {
    console.error('\n[ERREUR FATALE]', err.message);
    cleanup();
    process.exit(1);
});
