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
 * Lancer un job d'impression via SumatraPDF (copies natives Windows)
 * @param {string} pdfPath - Chemin du fichier PDF à imprimer
 * @param {Object} options - Options d'impression
 * @param {string} [options.printer] - Nom de l'imprimante
 * @param {number} [options.copies=1] - Nombre de copies (natives)
 * @param {string} [options.paperSize='A4'] - Format papier (A2, A3, A4, A5, A6, letter, legal, tabloid, statement)
 * @param {string} [options.orientation='portrait'] - Orientation (portrait, landscape)
 * @param {string} [options.duplex='simplex'] - Recto-verso (simplex, duplex, tumble)
 * @param {string} [options.colorMode='color'] - Mode couleur (color, monochrome)
 * @param {string} [options.pageSubset='all'] - Pages à imprimer (all, odd, even)
 * @param {string} [options.pageRange] - Plage de pages (ex: "1-5,8,10-12")
 * @param {string} [options.scaling='fit'] - Mise à l'échelle (fit, shrink, noscale)
 * @param {string} [options.paperTray] - Bac papier (nom ou numéro)
 * @param {string} [options.fileName] - Nom du document pour l'affichage
 * @returns {Promise<Object>}
 */
async function printJob(pdfPath, options = {}) {
    const { spawn } = require('child_process');
    const fs = require('fs');

    // Trouver SumatraPDF
    const sumatraPath = path.join(__dirname, '..', '..', '..', 'sumatra', 'SumatraPDF.exe');
    if (!fs.existsSync(sumatraPath)) {
        throw new Error(`SumatraPDF non trouvé: ${sumatraPath}`);
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

    // Construire les paramètres d'impression SumatraPDF
    const settings = [];

    // Copies natives (ex: "4x" pour 4 copies)
    const copies = parseInt(options.copies) || 1;
    if (copies > 1) {
        settings.push(`${copies}x`);
    }

    // Format papier
    const paperSize = options.paperSize || 'A4';
    const paperSizeMap = {
        'A2': 'A2', 'A3': 'A3', 'A4': 'A4', 'A5': 'A5', 'A6': 'A6',
        'Letter': 'letter', 'Legal': 'legal', 'Tabloid': 'tabloid', 'Statement': 'statement'
    };
    const sumatraPaperSize = paperSizeMap[paperSize] || paperSize.toLowerCase();
    settings.push(`paper=${sumatraPaperSize}`);

    // Orientation
    const orientation = options.orientation || 'portrait';
    settings.push(orientation);

    // Duplex (recto-verso)
    const duplexMode = options.duplex || 'simplex';
    const duplexMap = {
        'simplex': 'simplex',
        'duplex': 'duplexlong',
        'tumble': 'duplexshort'
    };
    settings.push(duplexMap[duplexMode] || 'simplex');

    // Mode couleur
    const colorMode = options.colorMode || 'color';
    settings.push(colorMode === 'monochrome' ? 'monochrome' : 'color');

    // Mise à l'échelle
    const scaling = options.scaling || 'fit';
    if (['fit', 'shrink', 'noscale'].includes(scaling)) {
        settings.push(scaling);
    }

    // Bac papier
    if (options.paperTray) {
        settings.push(`bin=${options.paperTray}`);
    }

    // Pages paires/impaires
    const pageSubset = options.pageSubset || 'all';
    if (pageSubset === 'odd') {
        settings.push('odd');
    } else if (pageSubset === 'even') {
        settings.push('even');
    }

    // Plage de pages spécifique
    if (options.pageRange && options.pageRange.trim()) {
        settings.push(options.pageRange.trim());
    }

    const jobName = options.fileName || 'Dupli-Print';

    // Log détaillé des options
    const logData = {
        timestamp: new Date().toISOString(),
        pdfPath: pdfPath,
        printer: printerName,
        engine: 'SumatraPDF',
        options: {
            copies: copies,
            paperSize: sumatraPaperSize,
            orientation: orientation,
            duplex: duplexMode,
            colorMode: colorMode,
            scaling: scaling,
            paperTray: options.paperTray || 'Auto',
            pageSubset: pageSubset,
            pageRange: options.pageRange || 'all'
        }
    };
    console.log('🖨️ [SUMATRA] Options d\'impression:', JSON.stringify(logData, null, 2));

    // Arguments SumatraPDF
    const args = [
        '-print-to', printerName,
        '-print-settings', settings.join(','),
        '-silent',
        pdfPath
    ];

    console.log(`🖨️ [SUMATRA] Commande: ${sumatraPath} ${args.map(a => `"${a}"`).join(' ')}`);

    return new Promise((resolve, reject) => {
        const child = spawn(sumatraPath, args, {
            windowsHide: true,
            detached: false
        });

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (data) => { stdout += data.toString(); });
        child.stderr.on('data', (data) => { stderr += data.toString(); });

        child.on('close', (code) => {
            if (code === 0) {
                console.log('✅ [SUMATRA] Impression envoyée avec succès');
                resolve({
                    success: true,
                    jobId: Date.now() % 100000,
                    message: `Impression envoyée (${copies} copie${copies > 1 ? 's' : ''})`,
                    printer: printerName,
                    engine: 'SumatraPDF'
                });
            } else {
                console.error('❌ [SUMATRA] Erreur SumatraPDF code:', code, stderr);
                reject(new Error(`SumatraPDF erreur code ${code}: ${stderr || stdout || 'Erreur inconnue'}`));
            }
        });

        child.on('error', (err) => {
            console.error('❌ [SUMATRA] Erreur lancement:', err.message);
            reject(new Error(`Erreur lancement SumatraPDF: ${err.message}`));
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

/**
 * Réanalyser un job d'impression (forcer la génération de thumbnail et calcul fill rate)
 * @param {number} jobId - ID du job Windows
 * @returns {Object} { success, isGrayscale, fillRate, thumbnailUrl }
 */
function reanalyzeJob(jobId) {
    try {
        const addon = loadNativeAddon();
        if (addon.reanalyzeJob) {
            return addon.reanalyzeJob(jobId);
        }
        console.warn('⚠️ L\'addon natif ne supporte pas reanalyzeJob');
        return { success: false, error: 'reanalyzeJob not supported' };
    } catch (error) {
        console.error('❌ Erreur lors de la réanalyse:', error);
        return { success: false, error: error.message };
    }
}

module.exports = {
    getPrinters,
    getPrinterCapabilities,
    printJob,
    startPrinterMonitor,
    stopPrinterMonitor,
    reanalyzeJob
};
