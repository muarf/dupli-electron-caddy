/**
 * Wrapper JavaScript pour l'addon natif Windows
 */

const path = require('path');
const os = require('os');

let nativeAddon = null;

// Charger l'addon natif
function loadNativeAddon() {
    if (nativeAddon) {
        return nativeAddon;
    }

    try {
        // Essayer de charger l'addon compilé
        const addonPath = path.join(__dirname, 'build', 'Release', 'win32-printer.node');
        nativeAddon = require(addonPath);
    } catch (error) {
        // Si l'addon n'est pas compilé, essayer de le compiler
        console.warn('Addon natif non trouvé, tentative de compilation...');
        try {
            const { execSync } = require('child_process');
            const nodeGyp = require.resolve('node-gyp/bin/node-gyp.js');
            execSync(`node "${nodeGyp}" rebuild`, {
                cwd: __dirname,
                stdio: 'inherit'
            });
            const addonPath = path.join(__dirname, 'build', 'Release', 'win32-printer.node');
            nativeAddon = require(addonPath);
        } catch (compileError) {
            throw new Error(`Impossible de charger ou compiler l'addon natif: ${compileError.message}`);
        }
    }

    return nativeAddon;
}

/**
 * Obtenir la liste des imprimantes
 * @returns {Promise<Array>}
 */
async function getPrinters() {
    try {
        const addon = loadNativeAddon();
        const printers = addon.getPrinters();

        // Convertir en tableau JavaScript standard
        const result = [];
        for (let i = 0; i < printers.length; i++) {
            result.push({
                name: printers[i].name,
                displayName: printers[i].displayName || printers[i].name,
                status: printers[i].status === 0 ? 'idle' : 'offline',
                isDefault: printers[i].isDefault || false
            });
        }

        return result;
    } catch (error) {
        throw new Error(`Erreur lors de la récupération des imprimantes: ${error.message}`);
    }
}

/**
 * Obtenir les capacités d'une imprimante
 * @param {string} printerName
 * @returns {Promise<Object>}
 */
async function getPrinterCapabilities(printerName) {
    try {
        const addon = loadNativeAddon();
        const capabilities = addon.getPrinterCapabilities(printerName);

        // Convertir en objet JavaScript standard
        const result = {
            inputSlots: [],
            pageSizes: [],
            duplex: capabilities.duplex || false,
            color: capabilities.color || false,
            colorModes: [],
            resolutions: []
        };

        // Convertir les bacs
        if (capabilities.inputSlots) {
            for (let i = 0; i < capabilities.inputSlots.length; i++) {
                result.inputSlots.push({
                    name: capabilities.inputSlots[i].name,
                    value: capabilities.inputSlots[i].value
                });
            }
        }

        // Convertir les formats papier
        if (capabilities.pageSizes) {
            for (let i = 0; i < capabilities.pageSizes.length; i++) {
                result.pageSizes.push({
                    name: capabilities.pageSizes[i].name,
                    value: capabilities.pageSizes[i].value,
                    width: capabilities.pageSizes[i].width || 0,
                    height: capabilities.pageSizes[i].height || 0
                });
            }
        }

        // Convertir les modes couleur
        if (capabilities.colorModes) {
            for (let i = 0; i < capabilities.colorModes.length; i++) {
                result.colorModes.push(capabilities.colorModes[i]);
            }
        }

        // Valeurs par défaut si vides
        if (result.inputSlots.length === 0) {
            result.inputSlots.push({ name: 'Auto', value: 'Auto' });
        }
        if (result.pageSizes.length === 0) {
            result.pageSizes.push({ name: 'A4', value: 'A4', width: 210, height: 297 });
        }
        if (result.colorModes.length === 0) {
            result.colorModes.push('Monochrome');
        }

        return result;
    } catch (error) {
        throw new Error(`Erreur lors de la récupération des capacités: ${error.message}`);
    }
}

/**
 * Lancer un job d'impression via Ghostscript (Windows GDI)
 * @param {string} pdfPath
 * @param {Object} options
 * @returns {Promise<Object>}
 */
async function printJob(pdfPath, options = {}) {
    const { spawn } = require('child_process');
    const fs = require('fs');

    // Trouver Ghostscript
    const gsPath = path.join(__dirname, '..', '..', '..', 'ghostscript', 'gswin64c.exe');
    if (!fs.existsSync(gsPath)) {
        throw new Error(`Ghostscript non trouvé: ${gsPath}`);
    }

    // Vérifier que le PDF existe
    if (!fs.existsSync(pdfPath)) {
        throw new Error(`Fichier PDF non trouvé: ${pdfPath}`);
    }

    // Déterminer l'imprimante
    let printerName = options.printer;
    if (!printerName) {
        const addon = loadNativeAddon();
        const printers = addon.getPrinters();
        const defaultPrinter = printers.find(p => p.isDefault);
        printerName = defaultPrinter ? defaultPrinter.name : (printers[0]?.name || '');
    }

    if (!printerName) {
        throw new Error('Aucune imprimante disponible');
    }

    // Log détaillé des options
    const logData = {
        timestamp: new Date().toISOString(),
        pdfPath: pdfPath,
        printer: printerName,
        options: {
            copies: options.copies || 1,
            pageSize: options.pageSize || 'Default',
            colorMode: options.colorMode || 'Default',
            duplex: options.duplex || 'Default'
        }
    };
    console.log('🖨️ [PRINT_ENGINE] Options d\'impression via Ghostscript:', JSON.stringify(logData, null, 2));

    // Nombre de copies
    const copies = parseInt(options.copies) || 1;
    const jobName = options.fileName || 'Dupli-Print';

    return new Promise((resolve, reject) => {
        // Arguments Ghostscript
        // -sJobName permet de définir le nom du document dans la file d'attente
        // -c "<< /NumCopies X >> setpagedevice" permet (parfois) de gérer les copies en un seul job
        const safeJobName = jobName.replace(/[()]/g, '');
        const gsArgs = [
            '-dBATCH',
            '-dNOPAUSE',
            '-dNOSAFER',
            '-dQUIET',
            `-sJobName=${safeJobName}`,
            '-sDEVICE=mswinpr2',
            `-sOutputFile=%printer%${printerName}`
        ];

        // Gestion du format papier
        const paperSize = options.paperSize || 'A4';
        const paperSizeMap = {
            'A4': 'a4',
            'A3': 'a3',
            'A5': 'a5',
            'Letter': 'letter',
            'Legal': 'legal'
        };
        const gsPaperSize = paperSizeMap[paperSize] || 'a4';
        gsArgs.push(`-sPAPERSIZE=${gsPaperSize}`);

        // Gestion de l'orientation
        const orientation = options.orientation || 'portrait';
        if (orientation === 'landscape') {
            gsArgs.push('-dAutoRotatePages=/None');
            gsArgs.push('-c', '<< /Orientation 3 >> setpagedevice');
        }

        // Gestion des copies et du titre au sein d'un seul flux
        // Note: mswinpr2 utilise aussi souvent le titre du document PostScript
        const psCommands = [
            `/NumCopies ${copies}`,
            `/Title (${safeJobName})`,
            `/JobName (${safeJobName})`
        ];

        // Gestion du recto-verso (duplex)
        // Duplex values: false (simplex), true (long edge), /Tumble (short edge)
        const duplexMode = options.duplex || 'simplex';
        if (duplexMode === 'duplex') {
            // Duplex bord long (flip sur le côté long)
            psCommands.push('/Duplex true');
            psCommands.push('/Tumble false');
        } else if (duplexMode === 'tumble') {
            // Duplex bord court (flip sur le côté court)
            psCommands.push('/Duplex true');
            psCommands.push('/Tumble true');
        } else {
            // Simplex (recto seul)
            psCommands.push('/Duplex false');
        }

        // Gestion des pages paires/impaires
        const pageSubset = options.pageSubset || 'all';
        if (pageSubset === 'odd') {
            gsArgs.push('-sPageList=odd');
        } else if (pageSubset === 'even') {
            gsArgs.push('-sPageList=even');
        }

        gsArgs.push('-c', `<< ${psCommands.join(' ')} >> setpagedevice`, '-f');

        gsArgs.push(pdfPath);

        console.log(`🖨️ [PRINT_ENGINE] Lancement impression (Job: ${jobName}, Copies: ${copies}, Paper: ${paperSize}, Orientation: ${orientation}, Duplex: ${duplexMode}, Pages: ${pageSubset}):`, gsPath, gsArgs.join(' '));

        const child = spawn(gsPath, gsArgs, {
            windowsHide: true,
            detached: false
        });

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (data) => { stdout += data.toString(); });
        child.stderr.on('data', (data) => { stderr += data.toString(); });

        child.on('close', (code) => {
            if (code === 0) {
                console.log('✅ [PRINT_ENGINE] Impression envoyée avec succès');
                resolve({
                    success: true,
                    jobId: Date.now() % 100000,
                    message: `Impression envoyée (${copies} copie${copies > 1 ? 's' : ''})`,
                    printer: printerName
                });
            } else {
                console.error('❌ [PRINT_ENGINE] Erreur Ghostscript code:', code, stderr);
                reject(new Error(`Ghostscript erreur code ${code}: ${stderr || stdout || 'Unknown error'}`));
            }
        });

        child.on('error', (err) => {
            console.error('❌ [PRINT_ENGINE] Erreur lancement:', err.message);
            reject(new Error(`Erreur lancement Ghostscript: ${err.message}`));
        });
    });
}

/**
 * Démarrer la surveillance des imprimantes
 * @param {Function} callback - Fonction appelée à chaque changement (job data)
 * @returns {boolean} Succès
 */
function startPrinterMonitor(callback) {
    try {
        const addon = loadNativeAddon();
        if (addon.startPrinterMonitor) {
            return addon.startPrinterMonitor(callback);
        }
        console.warn('⚠️ L\'addon natif ne supporte pas startPrinterMonitor');
        return false;
    } catch (error) {
        console.error('❌ Erreur lors du démarrage du moniteur:', error);
        return false;
    }
}

/**
 * Arrêter la surveillance des imprimantes
 * @returns {boolean} Succès
 */
function stopPrinterMonitor() {
    try {
        const addon = loadNativeAddon();
        if (addon.stopPrinterMonitor) {
            return addon.stopPrinterMonitor();
        }
        return false;
    } catch (error) {
        console.error('❌ Erreur lors de l\'arrêt du moniteur:', error);
        return false;
    }
}

module.exports = {
    getPrinters,
    getPrinterCapabilities,
    printJob,
    startPrinterMonitor,
    stopPrinterMonitor
};
