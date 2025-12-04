/**
 * Module d'impression unifié pour Electron
 * Fournit une API uniforme pour Windows et Linux
 */

const os = require('os');
const path = require('path');

let platformImpl = null;

// Charger l'implémentation selon la plateforme
function loadPlatformImplementation() {
    if (platformImpl) {
        return platformImpl;
    }

    const platform = os.platform();
    
    if (platform === 'win32') {
        try {
            platformImpl = require('./windows/win32-printer');
        } catch (error) {
            console.error('Erreur chargement module Windows:', error);
            throw new Error('Module d\'impression Windows non disponible: ' + error.message);
        }
    } else if (platform === 'linux') {
        try {
            platformImpl = require('./linux/cups-printer');
        } catch (error) {
            console.error('Erreur chargement module Linux:', error);
            throw new Error('Module d\'impression Linux non disponible: ' + error.message);
        }
    } else if (platform === 'darwin') {
        // macOS - pour l'instant, utiliser CUPS aussi (similaire à Linux)
        try {
            platformImpl = require('./linux/cups-printer');
        } catch (error) {
            console.error('Erreur chargement module macOS:', error);
            throw new Error('Module d\'impression macOS non disponible: ' + error.message);
        }
    } else {
        throw new Error(`Plateforme non supportée: ${platform}`);
    }

    return platformImpl;
}

/**
 * Obtenir la liste des imprimantes disponibles
 * @returns {Promise<Array>} Liste des imprimantes avec leurs propriétés
 */
async function getPrinters() {
    const impl = loadPlatformImplementation();
    return await impl.getPrinters();
}

/**
 * Obtenir les capacités d'une imprimante
 * @param {string} printerName - Nom de l'imprimante
 * @returns {Promise<Object>} Objet contenant les capacités (bacs, formats, duplex, etc.)
 */
async function getPrinterCapabilities(printerName) {
    if (!printerName) {
        throw new Error('Le nom de l\'imprimante est requis');
    }
    
    const impl = loadPlatformImplementation();
    return await impl.getPrinterCapabilities(printerName);
}

/**
 * Lancer un job d'impression
 * @param {string} pdfPath - Chemin absolu vers le fichier PDF
 * @param {Object} options - Options d'impression
 * @param {string} options.printer - Nom de l'imprimante
 * @param {number} [options.copies=1] - Nombre de copies
 * @param {string} [options.inputSlot] - Bac à papier
 * @param {string} [options.pageSize] - Format papier (A4, A3, etc.)
 * @param {string} [options.colorMode] - Mode couleur (Color, Monochrome)
 * @param {string} [options.duplex] - Mode recto-verso (Simplex, DuplexNoTumble, DuplexTumble)
 * @param {string} [options.resolution] - Résolution (300dpi, 600dpi, etc.)
 * @returns {Promise<Object>} Résultat de l'impression
 */
async function printJob(pdfPath, options = {}) {
    if (!pdfPath) {
        throw new Error('Le chemin du PDF est requis');
    }

    if (!options.printer) {
        throw new Error('Le nom de l\'imprimante est requis');
    }

    // Vérifier que le fichier existe
    const fs = require('fs');
    if (!fs.existsSync(pdfPath)) {
        throw new Error(`Le fichier PDF n'existe pas: ${pdfPath}`);
    }

    // Normaliser le chemin (absolu)
    const absolutePath = path.isAbsolute(pdfPath) ? pdfPath : path.resolve(pdfPath);

    const impl = loadPlatformImplementation();
    return await impl.printJob(absolutePath, options);
}

/**
 * Vérifier si le module d'impression est disponible sur cette plateforme
 * @returns {boolean}
 */
function isAvailable() {
    try {
        loadPlatformImplementation();
        return true;
    } catch (error) {
        return false;
    }
}

/**
 * Obtenir la plateforme actuelle
 * @returns {string}
 */
function getPlatform() {
    return os.platform();
}

module.exports = {
    getPrinters,
    getPrinterCapabilities,
    printJob,
    isAvailable,
    getPlatform
};

