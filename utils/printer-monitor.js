/**
 * Module de surveillance du pool d'imprimantes Windows
 * Utilise le module natif C++ pour une surveillance performante et fiable
 */

const os = require('os');
const path = require('path');

// Import spool analyzer for Ghostscript-based color detection
let spoolAnalyzer = null;
try {
    spoolAnalyzer = require('./spool-analyzer');
    console.log('✅ Spool analyzer loaded successfully');
} catch (e) {
    console.warn('⚠️ Spool analyzer not available:', e.message);
}

// Import dynamique du module d'impression natif
let win32Printer = null;
try {
    // Essayer d'importer depuis le chemin source standard
    win32Printer = require('../src/print-engine/windows/win32-printer');
} catch (e) {
    console.error('⚠️ Module d\'impression natif non trouvé:', e.message);
}

class PrinterMonitor {
    constructor(options = {}) {
        this.isWindows = os.platform() === 'win32';
        this.monitoring = false;
        this.callbacks = {
            onPrintJob: options.onPrintJob || null,
            onError: options.onError || null
        };
        // Cache pour éviter de spammer les mêmes jobs si le natif envoie des doublons
        this.processedJobs = new Set();
        // Jobs en cours de suppression pour éviter qu'ils ne réapparaissent
        this.deletingJobs = new Set();
    }

    /**
     * Démarrer la surveillance du spooler d'impression (Mode Natif)
     */
    start() {
        if (this.isWindows) {
            return this.startWindowsMonitor();
        } else if (process.platform === 'linux') {
            return this.startLinuxMonitor();
        } else {
            console.log('La surveillance des imprimantes n\'est pas supportée sur cet OS');
            return false;
        }
    }

    startLinuxMonitor() {
        if (this.monitoring) return false;

        try {
            const LinuxSpoolAnalyzer = require('./spool-analyzer-linux');
            this.linuxAnalyzer = new LinuxSpoolAnalyzer();

            this.linuxAnalyzer.on('job', (jobInfo) => {
                if (this.callbacks.onPrintJob) {
                    this.callbacks.onPrintJob(jobInfo);
                }
            });

            if (this.linuxAnalyzer.start()) {
                console.log('✅ Surveillance Linux active via CUPS Spool');
                this.monitoring = true;
                return true;
            }
            return false;
        } catch (e) {
            console.error('❌ Erreur démarrage moniteur Linux:', e);
            return false;
        }
    }

    startWindowsMonitor() {
        if (this.monitoring) {
            console.log('La surveillance est déjà active');
            return false;
        }

        if (!win32Printer) {
            console.error('❌ Impossible de démarrer : Module natif win32-printer introuvable');
            if (this.callbacks.onError) {
                this.callbacks.onError(new Error('Module natif imprimante manquant'));
            }
            return false;
        }

        console.log('🚀 Démarrage de la surveillance NATIVE du pool d\'imprimantes...');
        this.monitoring = true;

        try {
            const success = win32Printer.startPrinterMonitor((event, data) => {
                console.log(`[NATIVE-RAW-EVENT] Event: ${event}`, JSON.stringify(data));
                if (event === 'job') {
                    this.handleNativeJob(data);
                }
            });

            if (success) {
                console.log('✅ Surveillance native active via C++');
                return true;
            } else {
                console.error('❌ Échec du démarrage du moniteur natif (Code C++ a retourné false)');
                this.monitoring = false;
                return false;
            }
        } catch (error) {
            console.error('❌ Exception lors du lancement du moniteur natif:', error);
            this.monitoring = false;
            return false;
        }
    }

    /**
     * Arrêter la surveillance
     */
    stop() {
        if (!this.monitoring) return;

        console.log('Arrêt de la surveillance...');

        if (this.isWindows && win32Printer) {
            win32Printer.stopPrinterMonitor();
        } else if (this.linuxAnalyzer) {
            this.linuxAnalyzer.stop();
        }

        this.monitoring = false;
        this.processedJobs.clear();
    }

    /**
     * Traiter un job reçu du module natif C++
     * @param {Object} jobData - Données brutes du C++
     */
    handleNativeJob(jobData) {
        // Clé unique pour déduplication (JobId + Printer + Status + TotalPages)
        // Le C++ peut envoyer plusieurs notifs pour le même état
        const jobKey = `${jobData.printerName}_${jobData.jobId}_${jobData.status}_${jobData.totalPages}`;

        if (this.processedJobs.has(jobKey)) {
            return; // Ignorer doublon strict
        }

        // Ignorer si le job est marqué comme "en cours de suppression"
        if (this.deletingJobs.has(parseInt(jobData.jobId))) {
            console.log(`🚫 [NATIVE MONITOR] Job #${jobData.jobId} ignoré car en cours de suppression`);
            return;
        }

        // Nettoyer le cache périodiquement (simple stratégie)
        if (this.processedJobs.size > 1000) {
            this.processedJobs.clear();
        }
        this.processedJobs.add(jobKey);

        // Convertir les données du C++ vers le format attendu par l'application
        // Mapping des constantes Windows vers des chaînes lisibles si nécessaire

        const paperSizeStr = mappingPaperSize(jobData.paperSize);
        const duplexBool = jobData.duplex === 2 || jobData.duplex === 3; // 2=Vertical, 3=Horizontal

        // Color detection strategy:
        // C++ spool analysis reads actual file content and detects color patterns
        // The color detection WORKS even for RISO proprietary format
        // Fill rate is 0 because we can't calculate coverage, but color detection is accurate
        // 
        // ALWAYS trust C++ isGrayscale result - it reads the actual spool file content

        // DevMode color setting (only used as fallback if C++ analysis fails completely)
        const devModeIsColor = jobData.color === 2;

        // Spool analysis result - C++ always provides isGrayscale
        const spoolSaysGrayscale = jobData.isGrayscale;
        const fillRate = jobData.fillRate || 0;

        // Decision logic:
        // - Trust C++ color detection (it reads actual spool file patterns)
        // - FillRate=0 is OK, it just means we can't calculate coverage for RISO format
        let colorModeStr = spoolSaysGrayscale ? "Monochrome" : "Color";
        let colorSource = "Spool";

        console.log(`[DEBUG] Color detection: dmColor=${jobData.color}, spool.isGrayscale=${jobData.isGrayscale}, fillRate=${fillRate.toFixed(1)}%, source=${colorSource} → ${colorModeStr}`);

        const jobInfo = {
            JobId: jobData.jobId,
            PrinterName: jobData.printerName,
            Document: jobData.documentName,
            Status: jobData.status,
            TotalPages: jobData.totalPages,
            PaperSize: paperSizeStr,
            IsDuplex: duplexBool,
            ColorMode: colorModeStr,
            Copies: jobData.copies || 1,
            FillRate: fillRate,
            ThumbnailUrl: jobData.thumbnailUrl || "",
            TimeSubmitted: jobData.timeSubmitted || new Date().toISOString()
        };

        console.log(`🖨️ [NATIVE MONITOR] Job #${jobInfo.JobId}: ${jobInfo.Document} (${jobInfo.TotalPages}p x${jobInfo.Copies}) [${jobInfo.PaperSize}, ${jobInfo.ColorMode}, ${jobInfo.FillRate.toFixed(1)}% fill, Duplex:${jobInfo.IsDuplex}]`);

        // Notifier l'application avec les données initiales
        if (this.callbacks.onPrintJob) {
            this.callbacks.onPrintJob(jobInfo);
        }

        // === GHOSTSCRIPT ANALYSIS DISABLED ===
        // RISO spool files use a proprietary format that Ghostscript cannot render.
        // Fill rate analysis requires rendering to images, which isn't possible.
        // Color detection already uses C++ pattern matching which works correctly.
        // If you want accurate fill rate, the PDF must be analyzed before printing.
    }

    // méthodes legacy (cache) gardées vides pour compatibilité si appelées ailleurs
    setPrintOptions() { }
    getPrintOptions() { return null; }
    registerPrintJob() { } // Added for compatibility with impression-complete

    /**
     * Marquer un job comme étant en cours de suppression
     */
    addDeletingJob(jobId) {
        const id = parseInt(jobId);
        if (!isNaN(id)) {
            this.deletingJobs.add(id);
            console.log(`📍 [MONITOR] Job #${id} ajouté à la liste d'exclusion (suppression en cours)`);

            // Sécurité: retirer du cache après 30 secondes au cas où
            setTimeout(() => {
                this.deletingJobs.delete(id);
            }, 30000);
        }
    }

    /**
     * Retirer un job de la liste d'exclusion
     */
    removeDeletingJob(jobId) {
        const id = parseInt(jobId);
        if (!isNaN(id)) {
            this.deletingJobs.delete(id);
        }
    }

    /**
     * Force re-analysis of a specific job (bypasses cache)
     * @param {number} jobId - Print job ID
     * @returns {Object|null} { success, isGrayscale, fillRate, thumbnailUrl }
     */
    async reanalyzeJob(jobId) {
        if (this.isWindows) {
            // Windows: module natif C++
            if (!win32Printer || !win32Printer.reanalyzeJob) {
                console.error('❌ Module natif reanalyzeJob non disponible');
                return null;
            }
            try {
                const result = win32Printer.reanalyzeJob(jobId);
                console.log(`🔄 [ReanalyzeJob] Job #${jobId}:`, result);
                return result;
            } catch (e) {
                console.error('❌ Erreur reanalyzeJob:', e);
                return null;
            }
        } else if (process.platform === 'linux') {
            return this.reanalyzeJobLinux(jobId);
        }
        return null;
    }

    /**
     * Linux: réanalyse via Ghostscript ink_cov sur le fichier spool CUPS
     */
    async reanalyzeJobLinux(jobId) {
        const fs = require('fs');
        const pathMod = require('path');
        const { spawn, execSync } = require('child_process');

        const paddedId = jobId.toString().padStart(5, '0');
        const filename = `d${paddedId}-001`;
        const spoolPath = pathMod.join('/var/spool/cups', filename);
        const tmpPath = pathMod.join(os.tmpdir(), `reanalyze_${filename}`);

        console.log(`🔄 [ReanalyzeJob Linux] Job #${jobId} → ${spoolPath}`);

        try {
            fs.copyFileSync(spoolPath, tmpPath);
            console.log(`📋 Copié vers ${tmpPath} (${fs.statSync(tmpPath).size} bytes)`);
        } catch (err) {
            console.error(`⚠️ Fichier spool ${filename} non lisible:`, err.message);
            return { success: false, error: 'Fichier spool introuvable: ' + err.message };
        }

        try {
            // Détecter le format via magic bytes
            const header = Buffer.alloc(8);
            const fd = fs.openSync(tmpPath, 'r');
            fs.readSync(fd, header, 0, 8, 0);
            fs.closeSync(fd);

            let format = 'unknown';
            if (header.slice(0, 4).toString() === '%PDF') format = 'pdf';
            else if (header.slice(0, 4).toString() === '%!PS') format = 'ps';
            else if (header[0] === 0x89 && header.slice(1, 4).toString() === 'PNG') format = 'png';
            else if (header[0] === 0xFF && header[1] === 0xD8 && header[2] === 0xFF) format = 'jpeg';

            console.log(`📄 [ReanalyzeJob Linux] Format détecté: ${format}`);

            let fillRate = 0;
            let isColor = false;
            let thumbnailUrl = '';
            let totalPages = 1;

            if (format === 'pdf' || format === 'ps') {
                // === PDF/PS: Ghostscript ink_cov ===
                const inkResult = await this._gsInkCov(tmpPath);
                if (!inkResult) {
                    try { fs.unlinkSync(tmpPath); } catch (e) { }
                    return { success: false, error: 'Ghostscript ink_cov failed' };
                }
                fillRate = inkResult.fillRate;
                isColor = inkResult.isColor;
                totalPages = inkResult.pages || 1;
                thumbnailUrl = await this._gsThumbnail(tmpPath, jobId);

            } else if (format === 'png' || format === 'jpeg') {
                // === PNG/JPEG: ImageMagick analyse pixel ===
                const imgResult = await this._imageMagickAnalyze(tmpPath);
                fillRate = imgResult.fillRate;
                isColor = imgResult.isColor;
                thumbnailUrl = await this._imageMagickThumbnail(tmpPath, jobId);

            } else {
                console.warn(`⚠️ Format non supporté: ${format}`);
                try { fs.unlinkSync(tmpPath); } catch (e) { }
                return { success: false, error: `Format non supporté: ${format}` };
            }

            try { fs.unlinkSync(tmpPath); } catch (e) { }

            console.log(`✅ [ReanalyzeJob Linux] Job #${jobId}: fillRate=${fillRate.toFixed(2)}%, isColor=${isColor}, format=${format}, thumb=${thumbnailUrl ? 'yes' : 'no'}`);
            return {
                success: true,
                isGrayscale: !isColor,
                fillRate: fillRate,
                thumbnailUrl: thumbnailUrl,
                totalPages: totalPages
            };
        } catch (err) {
            console.error(`❌ [ReanalyzeJob Linux] Erreur inattendue:`, err);
            try { fs.unlinkSync(tmpPath); } catch (e) { }
            return { success: false, error: err.message };
        }
    }

    /** Ghostscript ink_cov pour PDF/PS */
    async _gsInkCov(filePath) {
        const { spawn } = require('child_process');
        return new Promise((resolve) => {
            const gs = spawn('gs', ['-dNOSAFER', '-dBATCH', '-dNOPAUSE', '-o', '-', '-sDEVICE=ink_cov', filePath]);
            let output = '', stderr = '';
            gs.stdout.on('data', d => output += d.toString());
            gs.stderr.on('data', d => stderr += d.toString());
            gs.on('error', (e) => { console.error('❌ GS spawn error:', e.message); resolve(null); });
            gs.on('close', code => {
                if (code !== 0) { console.error(`❌ GS ink_cov exit ${code}:`, stderr.slice(0, 300)); resolve(null); return; }
                const lines = output.split('\n').filter(l => l.trim().match(/^\s*\d+\.\d+/));
                let tC = 0, tM = 0, tY = 0, tK = 0, pages = 0;
                for (const line of lines) {
                    const p = line.trim().split(/\s+/).map(parseFloat);
                    if (p.length >= 4) { tC += p[0]; tM += p[1]; tY += p[2]; tK += p[3]; pages++; }
                }
                if (pages === 0) pages = 1;
                resolve({ isColor: (tC + tM + tY) > 0.5, fillRate: (tC + tM + tY + tK) / (pages * 4), pages });
            });
        });
    }

    /** Ghostscript thumbnail pour PDF/PS */
    async _gsThumbnail(filePath, jobId) {
        const fs = require('fs');
        const { spawn } = require('child_process');
        const thumbPath = require('path').join(os.tmpdir(), `thumb_${jobId}.png`);
        try {
            await new Promise((resolve) => {
                const p = spawn('gs', ['-dNOSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET',
                    '-dFirstPage=1', '-dLastPage=1', '-sDEVICE=png16m', '-r72',
                    '-dTextAlphaBits=4', '-dGraphicsAlphaBits=4', `-sOutputFile=${thumbPath}`, filePath]);
                p.on('error', () => resolve(-1));
                p.on('close', resolve);
            });
            if (fs.existsSync(thumbPath)) {
                const data = fs.readFileSync(thumbPath);
                try { fs.unlinkSync(thumbPath); } catch (e) { }
                return 'data:image/png;base64,' + data.toString('base64');
            }
        } catch (e) { console.warn('⚠️ GS thumbnail failed:', e.message); }
        return '';
    }

    /** ImageMagick analyse pixel pour PNG/JPEG */
    async _imageMagickAnalyze(filePath) {
        const { execSync } = require('child_process');
        try {
            const meanStr = execSync(`convert "${filePath}" -colorspace Gray -format "%[fx:mean]" info:`,
                { encoding: 'utf-8', timeout: 15000 }).trim();
            const mean = parseFloat(meanStr);
            const fillRate = (1 - mean) * 100;
            let isColor = false;
            try {
                const satStr = execSync(`convert "${filePath}" -colorspace HSL -channel G -separate -format "%[fx:mean]" info:`,
                    { encoding: 'utf-8', timeout: 15000 }).trim();
                isColor = parseFloat(satStr) > 0.02;
            } catch (e) { /* assume grayscale */ }
            console.log(`📊 [ImageMagick] mean=${mean.toFixed(4)}, fillRate=${fillRate.toFixed(2)}%, isColor=${isColor}`);
            return { fillRate, isColor };
        } catch (err) {
            console.error('❌ ImageMagick analysis failed:', err.message);
            return { fillRate: 0, isColor: false };
        }
    }

    /** ImageMagick thumbnail pour PNG/JPEG (resize 200px) */
    async _imageMagickThumbnail(filePath, jobId) {
        const fs = require('fs');
        const thumbPath = require('path').join(os.tmpdir(), `thumb_${jobId}.png`);
        try {
            const { execSync } = require('child_process');
            execSync(`convert "${filePath}" -resize 200x -quality 80 "${thumbPath}"`, { timeout: 10000 });
            if (fs.existsSync(thumbPath)) {
                const data = fs.readFileSync(thumbPath);
                try { fs.unlinkSync(thumbPath); } catch (e) { }
                return 'data:image/png;base64,' + data.toString('base64');
            }
        } catch (e) { console.warn('⚠️ ImageMagick thumbnail failed:', e.message); }
        return '';
    }

    /**
     * Récupérer la liste des imprimantes
     * @returns {Promise<Array>}
     */
    async getPrinters() {
        if (this.isWindows) {
            if (!win32Printer) {
                console.error('❌ Module natif non chargé');
                return [];
            }
            try {
                return await win32Printer.getPrinters();
            } catch (e) {
                console.error('❌ Erreur getPrinters:', e);
                return [];
            }
        } else if (process.platform === 'linux') {
            try {
                const cupsPrinter = require('../src/print-engine/linux/cups-printer');
                return await cupsPrinter.getPrinters();
            } catch (e) {
                console.error('❌ Erreur getPrinters Linux:', e);
                return [];
            }
        }
        return [];
    }
}

// Helper pour mapper le paperSize ID (Win32) vers string
function mappingPaperSize(id) {
    if (!id) return "Unknown";
    const map = {
        1: "Letter", 5: "Legal", 8: "A3", 9: "A4", 11: "A5", 12: "B4", 13: "B5",
        66: "A2", 65: "A1", 64: "A0"
    };
    return map[id] || `Raw(${id})`;
}

module.exports = PrinterMonitor;
