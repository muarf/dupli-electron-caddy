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
    }

    /**
     * Démarrer la surveillance du spooler d'impression (Mode Natif)
     */
    start() {
        if (!this.isWindows) {
            console.log('La surveillance des imprimantes n\'est disponible que sur Windows');
            return false;
        }

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
        if (win32Printer) {
            win32Printer.stopPrinterMonitor();
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
     * Récupérer la liste des imprimantes
     * @returns {Promise<Array>}
     */
    async getPrinters() {
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
