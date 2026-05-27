/**
 * afterPack hook pour electron-builder — Wrapper Binary Linux
 *
 * Contexte : afterPack s'exécute sur linux-unpacked/ AVANT que appimagetool
 * ne génère l'AppRun. Patcher AppRun ici est impossible car il n'existe pas encore.
 *
 * Solution : on renomme le binaire Electron (ex: duplicator-beta → duplicator-beta.bin)
 * et on crée un script shell wrapper à sa place qui injecte --no-sandbox.
 * L'AppRun appellera le wrapper, lequel appelle le vrai binaire avec les bons flags.
 */

const fs = require('fs');
const path = require('path');

module.exports = async function afterPack(context) {
    if (context.electronPlatformName !== 'linux') {
        return;
    }

    const { appOutDir } = context;
    console.log('[afterPack] appOutDir:', appOutDir);

    // Lister les fichiers pour debug
    const files = fs.readdirSync(appOutDir);
    console.log('[afterPack] Contenu appOutDir:', files.join(', '));

    // Trouver le binaire principal Electron :
    // - fichier exécutable à la racine de appOutDir
    // - sans extension
    // - pas un fichier .so ou autre lib
    // - taille > 10MB (binaire Electron est volumineux)
    let binaryPath = null;

    for (const file of files) {
        // Ignorer les fichiers avec extensions et les libs
        if (file.includes('.') && !file.endsWith('.bin')) continue;
        if (file.endsWith('.bin')) continue; // déjà wrappé

        const fp = path.join(appOutDir, file);
        try {
            const stat = fs.statSync(fp);
            if (stat.isFile() && (stat.mode & 0o111) && stat.size > 10 * 1024 * 1024) {
                console.log(`[afterPack] Candidat binaire: ${file} (${Math.round(stat.size / 1024 / 1024)}MB)`);
                binaryPath = fp;
                break;
            }
        } catch (e) {
            // ignorer
        }
    }

    if (!binaryPath) {
        console.warn('[afterPack] Binaire Electron introuvable dans appOutDir. Contenu:');
        files.forEach(f => {
            try {
                const s = fs.statSync(path.join(appOutDir, f));
                console.warn(`  ${f}: ${s.isFile() ? s.size + 'B' : 'dir'} mode=${s.mode.toString(8)}`);
            } catch (e) { }
        });
        return;
    }

    const binDir = path.dirname(binaryPath);
    const binName = path.basename(binaryPath);
    const realBinaryPath = path.join(binDir, binName + '.bin');

    // Eviter de wrapper deux fois
    if (fs.existsSync(realBinaryPath)) {
        console.log('[afterPack] Wrapper déjà en place, rien à faire.');
        return;
    }

    // 1. Renommer le binaire original
    fs.renameSync(binaryPath, realBinaryPath);
    console.log(`[afterPack] Binaire renommé: ${binName} → ${binName}.bin`);

    // 2. Créer le wrapper shell
    const wrapper = [
        '#!/bin/sh',
        '# Wrapper injecté par afterpack-linux.js pour --no-sandbox (Linux AppImage)',
        'exec "$(dirname "$0")/' + binName + '.bin" --no-sandbox --disable-setuid-sandbox "$@"',
        ''
    ].join('\n');

    fs.writeFileSync(binaryPath, wrapper, { encoding: 'utf8', mode: 0o755 });
    console.log(`[afterPack] Wrapper shell créé: ${binName} → injecte --no-sandbox`);
};
