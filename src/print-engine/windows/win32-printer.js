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
 * Lancer un job d'impression
 * @param {string} pdfPath
 * @param {Object} options
 * @returns {Promise<Object>}
 */
async function printJob(pdfPath, options = {}) {
    try {
        const addon = loadNativeAddon();
        const result = addon.printJob(pdfPath, options);
        
        return {
            success: result.success || true,
            jobId: result.jobId || null,
            message: result.message || 'Impression lancée',
            printer: result.printer || options.printer
        };
    } catch (error) {
        throw new Error(`Erreur lors de l'impression: ${error.message}`);
    }
}

module.exports = {
    getPrinters,
    getPrinterCapabilities,
    printJob
};

