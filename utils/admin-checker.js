/**
 * Module pour vérifier si l'application est lancée avec des droits administrateur
 * Nécessaire pour accéder aux fichiers SPL et calculer le fill rate
 */

const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

/**
 * Vérifie si l'application est lancée avec des droits administrateur
 * @returns {Promise<boolean>} true si admin, false sinon
 */
async function isRunningAsAdmin() {
    // Sur Linux, on vérifie l'accès au dossier spool CUPS (traversée uniquement)
    if (process.platform === 'linux') {
        try {
            const fs = require('fs');
            // On tente de vérifier le droit d'exécution (traversée) pour le groupe lp
            await fs.promises.access('/var/spool/cups', fs.constants.X_OK);
            return true;
        } catch (error) {
            console.log('Linux: Accès refusé à /var/spool/cups (Traversée impossible)');
            return false;
        }
    }

    if (process.platform !== 'win32') {
        return false; // Autres OS non supportés pour le moment
    }

    try {
        // Méthode 1: Vérifier via PowerShell
        const { stdout } = await execAsync('powershell -Command "([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)"', {
            timeout: 2000
        });

        const isAdmin = stdout.trim().toLowerCase() === 'true';
        console.log(`Admin check via PowerShell: ${isAdmin}`);
        return isAdmin;
    } catch (error) {
        console.error('Erreur lors de la vérification des droits admin:', error.message);

        // Méthode 2 (fallback): Essayer d'accéder au dossier spool
        try {
            const fs = require('fs');
            const spoolPath = 'C:\\Windows\\System32\\spool\\PRINTERS';
            await fs.promises.access(spoolPath, fs.constants.R_OK);
            console.log('Accès au dossier spool réussi');
            return true;
        } catch (accessError) {
            console.log('Accès au dossier spool refusé - pas admin');
            return false;
        }
    }
}

/**
 * Redémarre l'application avec des droits administrateur
 * @param {string} appPath - Chemin de l'exécutable de l'application
 * @returns {Promise<void>}
 */
async function restartAsAdmin(appPath) {
    if (process.platform !== 'win32') {
        throw new Error('Fonction disponible uniquement sur Windows');
    }

    try {
        const { app } = require('electron');
        const path = require('path');

        // Obtenir le chemin de l'exécutable
        const exePath = appPath || app.getPath('exe');

        // Script PowerShell pour relancer l'app en admin
        const psScript = `Start-Process -FilePath "${exePath}" -Verb RunAs`;

        await execAsync(`powershell -Command "${psScript}"`, {
            timeout: 5000
        });

        // Quitter l'application actuelle (non-admin)
        app.quit();
    } catch (error) {
        console.error('Erreur lors du redémarrage en admin:', error);
        throw error;
    }
}

module.exports = {
    isRunningAsAdmin,
    restartAsAdmin
};
