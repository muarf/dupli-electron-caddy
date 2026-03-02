/**
 * afterPack hook pour electron-builder
 * Injecte --no-sandbox dans le script AppRun des AppImage Linux.
 *
 * Contexte : electron-builder produit un AppRun qui exécute le binaire via atexit().
 * Quand l'utilisateur lance l'AppImage sans arguments (ex: double-clic), la branche
 * `exec "$BIN"` est appelée sans aucun flag, ignorant les executableArgs du YAML.
 * Ce hook patche les deux branches exec pour garantir --no-sandbox sur tous les Linux.
 */

const fs = require('fs');
const path = require('path');

module.exports = async function afterPack(context) {
    if (context.electronPlatformName !== 'linux') {
        return;
    }

    const appRunPath = path.join(context.appOutDir, 'AppRun');

    if (!fs.existsSync(appRunPath)) {
        console.warn('[afterPack] AppRun introuvable à:', appRunPath);
        return;
    }

    let content = fs.readFileSync(appRunPath, 'utf8');
    const original = content;

    // Patcher la branche sans arguments (double-clic)
    // Note: les lignes exec sont indentées dans l'AppRun, d'où le flag multiline + capture des espaces
    content = content.replace(
        /^( *)exec "\$BIN"$/m,
        '$1exec "$BIN" --no-sandbox --disable-setuid-sandbox'
    );

    // Patcher la branche avec arguments (ligne de commande)
    content = content.replace(
        /^( *)exec "\$BIN" "\$\{args\[@\]\}"$/m,
        '$1exec "$BIN" --no-sandbox --disable-setuid-sandbox "${args[@]}"'
    );

    if (content === original) {
        console.warn('[afterPack] Aucun remplacement effectué — le format de AppRun a peut-être changé.');
    } else {
        fs.writeFileSync(appRunPath, content, 'utf8');
        console.log('[afterPack] --no-sandbox injecté avec succès dans AppRun');
    }
};
