/**
 * Implémentation Linux du module d'impression via CUPS/IPP
 */

const { spawn, exec } = require('child_process');
const { promisify } = require('util');
const fs = require('fs');
const path = require('path');

const execAsync = promisify(exec);

/**
 * Vérifier si CUPS est disponible
 */
async function checkCupsAvailable() {
    try {
        await execAsync('which lpstat');
        await execAsync('which lp');
        return true;
    } catch (error) {
        return false;
    }
}

/**
 * Obtenir la liste des imprimantes disponibles
 * @returns {Promise<Array>}
 */
async function getPrinters() {
    const cupsAvailable = await checkCupsAvailable();
    if (!cupsAvailable) {
        throw new Error('CUPS n\'est pas installé ou non accessible');
    }

    try {
        // Utiliser lpstat -p pour lister les imprimantes
        const { stdout } = await execAsync('lpstat -p 2>/dev/null || lpstat -a 2>/dev/null');
        
        const printers = [];
        const lines = stdout.split('\n').filter(line => line.trim());
        
        for (const line of lines) {
            // Format: "printer NomImprimante is idle.  enabled since ..."
            // ou "printer NomImprimante is idle"
            const match = line.match(/^printer\s+(\S+)/);
            if (match) {
                const name = match[1];
                // Vérifier si l'imprimante est en ligne
                const isOnline = !line.includes('offline') && !line.includes('unavailable');
                printers.push({
                    name: name,
                    displayName: name,
                    status: isOnline ? 'idle' : 'offline',
                    isDefault: false // On vérifiera après
                });
            } else {
                // Format alternatif: "NomImprimante accepting requests"
                const match2 = line.match(/^(\S+)\s+accepting/);
                if (match2 && !printers.find(p => p.name === match2[1])) {
                    printers.push({
                        name: match2[1],
                        displayName: match2[1],
                        status: 'idle',
                        isDefault: false
                    });
                }
            }
        }

        // Déterminer l'imprimante par défaut
        try {
            const { stdout: defaultPrinter } = await execAsync('lpstat -d 2>/dev/null');
            const defaultMatch = defaultPrinter.match(/system default destination:\s*(\S+)/);
            if (defaultMatch) {
                const defaultName = defaultMatch[1];
                printers.forEach(p => {
                    p.isDefault = (p.name === defaultName);
                });
            }
        } catch (error) {
            // Pas d'imprimante par défaut
        }

        return printers.length > 0 ? printers : [];
    } catch (error) {
        throw new Error(`Erreur lors de la récupération des imprimantes: ${error.message}`);
    }
}

/**
 * Parser les options CUPS depuis lpoptions -l
 * @param {string} output - Sortie de lpoptions -l
 * @returns {Object}
 */
function parseCupsOptions(output) {
    const capabilities = {
        inputSlots: [],
        pageSizes: [],
        duplex: false,
        color: false,
        colorModes: [],
        resolutions: []
    };

    const lines = output.split('\n');
    
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed) continue;

        // InputSlot (bacs)
        if (trimmed.startsWith('InputSlot/')) {
            const match = trimmed.match(/InputSlot\/([^:]+):\s*(.+)/);
            if (match) {
                const slotName = match[1].trim();
                const values = match[2].split(' ').filter(v => v && v !== '*');
                values.forEach(value => {
                    capabilities.inputSlots.push({
                        name: value,
                        value: value
                    });
                });
            }
        }

        // PageSize (formats papier)
        if (trimmed.startsWith('PageSize/') || trimmed.startsWith('media/')) {
            const match = trimmed.match(/(?:PageSize|media)\/([^:]+):\s*(.+)/);
            if (match) {
                const sizeName = match[1].trim();
                const values = match[2].split(' ').filter(v => v && v !== '*');
                
                // Tailles papier standard (en mm)
                const paperSizes = {
                    'A4': { width: 210, height: 297 },
                    'A3': { width: 297, height: 420 },
                    'A5': { width: 148, height: 210 },
                    'Letter': { width: 216, height: 279 },
                    'Legal': { width: 216, height: 356 },
                    'A0': { width: 841, height: 1189 },
                    'A1': { width: 594, height: 841 },
                    'A2': { width: 420, height: 594 }
                };

                values.forEach(value => {
                    const sizeInfo = paperSizes[value] || { width: 0, height: 0 };
                    if (!capabilities.pageSizes.find(p => p.value === value)) {
                        capabilities.pageSizes.push({
                            name: value,
                            value: value,
                            width: sizeInfo.width,
                            height: sizeInfo.height
                        });
                    }
                });
            }
        }

        // Duplex
        if (trimmed.startsWith('Duplex/') || trimmed.startsWith('sides/')) {
            capabilities.duplex = true;
        }

        // ColorModel (couleur)
        if (trimmed.startsWith('ColorModel/') || trimmed.startsWith('print-color-mode/')) {
            const match = trimmed.match(/(?:ColorModel|print-color-mode)\/([^:]+):\s*(.+)/);
            if (match) {
                const values = match[2].split(' ').filter(v => v && v !== '*');
                values.forEach(value => {
                    if (value.toLowerCase().includes('color') || value.toLowerCase().includes('rgb')) {
                        capabilities.color = true;
                        if (!capabilities.colorModes.includes('Color')) {
                            capabilities.colorModes.push('Color');
                        }
                    }
                    if (value.toLowerCase().includes('mono') || value.toLowerCase().includes('gray')) {
                        if (!capabilities.colorModes.includes('Monochrome')) {
                            capabilities.colorModes.push('Monochrome');
                        }
                    }
                });
            }
        }

        // Resolution
        if (trimmed.startsWith('Resolution/') || trimmed.startsWith('printer-resolution/')) {
            const match = trimmed.match(/(?:Resolution|printer-resolution)\/([^:]+):\s*(.+)/);
            if (match) {
                const values = match[2].split(' ').filter(v => v && v !== '*');
                values.forEach(value => {
                    // Extraire la résolution (ex: "300dpi" ou "600x600dpi")
                    const resMatch = value.match(/(\d+)(?:x\d+)?dpi/i);
                    if (resMatch) {
                        const dpi = resMatch[1] + 'dpi';
                        if (!capabilities.resolutions.includes(dpi)) {
                            capabilities.resolutions.push(dpi);
                        }
                    }
                });
            }
        }
    }

    // Valeurs par défaut si rien trouvé
    if (capabilities.inputSlots.length === 0) {
        capabilities.inputSlots.push({ name: 'Auto', value: 'Auto' });
    }
    if (capabilities.pageSizes.length === 0) {
        capabilities.pageSizes.push({ name: 'A4', value: 'A4', width: 210, height: 297 });
    }
    if (capabilities.colorModes.length === 0) {
        capabilities.colorModes.push('Monochrome');
    }

    return capabilities;
}

/**
 * Obtenir les capacités d'une imprimante
 * @param {string} printerName
 * @returns {Promise<Object>}
 */
async function getPrinterCapabilities(printerName) {
    const cupsAvailable = await checkCupsAvailable();
    if (!cupsAvailable) {
        throw new Error('CUPS n\'est pas installé ou non accessible');
    }

    try {
        // Utiliser lpoptions -l pour obtenir les options
        const { stdout } = await execAsync(`lpoptions -p "${printerName}" -l 2>/dev/null || lpoptions -d "${printerName}" -l 2>/dev/null`);
        
        const capabilities = parseCupsOptions(stdout);
        
        return capabilities;
    } catch (error) {
        // Si lpoptions échoue, retourner des capacités par défaut
        console.warn(`Impossible de récupérer les capacités pour ${printerName}: ${error.message}`);
        return {
            inputSlots: [{ name: 'Auto', value: 'Auto' }],
            pageSizes: [{ name: 'A4', value: 'A4', width: 210, height: 297 }],
            duplex: false,
            color: false,
            colorModes: ['Monochrome'],
            resolutions: []
        };
    }
}

/**
 * Construire les options pour la commande lp
 * @param {Object} options
 * @returns {Array<string>}
 */
function buildLpOptions(options) {
    const lpOptions = [];

    // Copies
    if (options.copies && options.copies > 1) {
        lpOptions.push('-n', String(options.copies));
    }

    // Bac à papier (InputSlot)
    if (options.inputSlot) {
        lpOptions.push('-o', `InputSlot=${options.inputSlot}`);
    }

    // Format papier
    if (options.pageSize) {
        lpOptions.push('-o', `media=${options.pageSize}`);
    }

    // Mode couleur
    if (options.colorMode) {
        if (options.colorMode === 'Monochrome') {
            lpOptions.push('-o', 'print-color-mode=monochrome');
        } else if (options.colorMode === 'Color') {
            lpOptions.push('-o', 'print-color-mode=color');
        }
    }

    // Recto-verso
    if (options.duplex) {
        if (options.duplex === 'DuplexNoTumble') {
            lpOptions.push('-o', 'sides=two-sided-long-edge');
        } else if (options.duplex === 'DuplexTumble') {
            lpOptions.push('-o', 'sides=two-sided-short-edge');
        } else if (options.duplex === 'Simplex') {
            lpOptions.push('-o', 'sides=one-sided');
        }
    }

    // Résolution
    if (options.resolution) {
        lpOptions.push('-o', `printer-resolution=${options.resolution}`);
    }

    return lpOptions;
}

/**
 * Lancer un job d'impression
 * @param {string} pdfPath
 * @param {Object} options
 * @returns {Promise<Object>}
 */
async function printJob(pdfPath, options = {}) {
    const cupsAvailable = await checkCupsAvailable();
    if (!cupsAvailable) {
        throw new Error('CUPS n\'est pas installé ou non accessible');
    }

    // Vérifier que le fichier existe
    if (!fs.existsSync(pdfPath)) {
        throw new Error(`Le fichier PDF n'existe pas: ${pdfPath}`);
    }

    const printerName = options.printer;
    if (!printerName) {
        throw new Error('Le nom de l\'imprimante est requis');
    }

    // Construire la commande lp
    const lpOptions = buildLpOptions(options);
    const args = [
        '-d', printerName,
        ...lpOptions,
        pdfPath
    ];

    return new Promise((resolve, reject) => {
        const lp = spawn('lp', args, {
            stdio: ['ignore', 'pipe', 'pipe']
        });

        let stdout = '';
        let stderr = '';

        lp.stdout.on('data', (data) => {
            stdout += data.toString();
        });

        lp.stderr.on('data', (data) => {
            stderr += data.toString();
        });

        lp.on('close', (code) => {
            if (code === 0) {
                // Extraire le job ID de la sortie (ex: "request id is HP-LaserJet-123 (1 file(s))")
                const jobIdMatch = stdout.match(/request id is (\S+)/);
                const jobId = jobIdMatch ? jobIdMatch[1] : null;

                resolve({
                    success: true,
                    jobId: jobId,
                    message: `Impression lancée sur ${printerName}`,
                    printer: printerName
                });
            } else {
                reject(new Error(`Erreur d'impression (code ${code}): ${stderr || stdout || 'Erreur inconnue'}`));
            }
        });

        lp.on('error', (error) => {
            reject(new Error(`Impossible de lancer l'impression: ${error.message}`));
        });
    });
}

module.exports = {
    getPrinters,
    getPrinterCapabilities,
    printJob
};

