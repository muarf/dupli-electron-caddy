/**
 * Module de surveillance du pool d'imprimantes Windows
 * Utilise le module natif C++ pour une surveillance performante et fiable
 */

const os = require('os');
const path = require('path');

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

        // const paperSizeMap = {
        //     1: "Letter", 2: "Letter", 3: "A3", 4: "A4", 5: "Legal", 
        //     8: "A3", 9: "A4", 11: "A5", 12: "B4", 13: "B5"
        //     // TODO: Compléter si le C++ renvoie des IDs bruts non gérés
        // };

        const paperSizeStr = mappingPaperSize(jobData.paperSize);
        const duplexBool = jobData.duplex === 2 || jobData.duplex === 3; // 2=Vertical, 3=Horizontal
        const colorModeStr = jobData.color === 2 ? "Color" : "Monochrome";

        const jobInfo = {
            JobId: jobData.jobId,
            PrinterName: jobData.printerName,
            Document: jobData.documentName,
            Status: jobData.status,
            TotalPages: jobData.totalPages,
            PaperSize: paperSizeStr,
            IsDuplex: duplexBool,
            ColorMode: colorModeStr,
            TimeSubmitted: new Date().toISOString()
        };

        console.log(`🖨️ [NATIVE MONITOR] Job #${jobInfo.JobId}: ${jobInfo.Document} (${jobInfo.TotalPages}p) [${jobInfo.PaperSize}, ${jobInfo.ColorMode}, Duplex:${jobInfo.IsDuplex}]`);

        // Notifier l'application
        if (this.callbacks.onPrintJob) {
            this.callbacks.onPrintJob(jobInfo);
        }
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
<<<<<<< HEAD
        // Cache pour éviter de spammer les mêmes jobs si le natif envoie des doublons
        this.processedJobs = new Set();
    }

    /**
     * Démarrer la surveillance du spooler d'impression (Mode Natif)
=======
        this.phpApiUrl = options.phpApiUrl || 'http://127.0.0.1:8001';
        this.lastJobId = null;
        this.jobCache = new Map();
        this.printOptionsCache = new Map(); // Cache pour les options d'impression
        
        // Charger le cache depuis le fichier JSON (créé par print-hook.ps1)
        this.loadPrintParamsCache();
        
        // Surveiller le fichier de cache pour les mises à jour
        this.watchPrintParamsCache();
    }
    
    /**
     * Charger le cache des paramètres d'impression depuis le fichier JSON
     */
    loadPrintParamsCache() {
        try {
            const cacheFile = path.join(os.homedir(), 'AppData', 'Roaming', 'dupli-electron', 'print-params-cache.json');
            if (fs.existsSync(cacheFile)) {
                let cacheContent = fs.readFileSync(cacheFile, 'utf8');

                // Supprimer le BOM UTF-8 s'il existe (´╗┐)
                if (cacheContent.charCodeAt(0) === 0xFEFF) {
                    cacheContent = cacheContent.slice(1);
                }

                // Supprimer les espaces en début/fin
                cacheContent = cacheContent.trim();

                if (!cacheContent) {
                    return;
                }

                const cache = JSON.parse(cacheContent);

                // Convertir le cache JSON en entrées pour printOptionsCache
                for (const [key, entry] of Object.entries(cache)) {
                    if (entry && entry.timestamp && entry.options) {
                        const entryTime = new Date(entry.timestamp);
                        const age = Date.now() - entryTime.getTime();

                        // Ne charger que les entrées de moins de 2 minutes
                        if (age < 120000) {
                            const cacheEntry = {
                                timestamp: entryTime.getTime(),
                                fileName: entry.fileName || key,
                                baseName: entry.baseName || key,
                                fullPath: entry.fullPath || key,
                                options: entry.options
                            };

                            this.printOptionsCache.set(key, cacheEntry);
                        }
                    }
                }

                console.log(`✅ [PRINT_CACHE] Cache chargé depuis fichier: ${Object.keys(cache).length} entrées`);
                // Afficher les clés chargées pour debug
                const loadedKeys = Object.keys(cache).slice(0, 5);
                console.log(`   Clés chargées: ${loadedKeys.join(', ')}${Object.keys(cache).length > 5 ? '...' : ''}`);
            }
        } catch (error) {
            console.warn('⚠️ [PRINT_CACHE] Erreur chargement cache:', error.message);
        }
    }

    /**
     * Surveiller le fichier de cache pour les mises à jour
     */
    watchPrintParamsCache() {
        try {
            const cacheFile = path.join(os.homedir(), 'AppData', 'Roaming', 'dupli-electron', 'print-params-cache.json');
            const cacheDir = path.dirname(cacheFile);

            // Créer le répertoire s'il n'existe pas
            if (!fs.existsSync(cacheDir)) {
                fs.mkdirSync(cacheDir, { recursive: true });
            }

            // Surveiller le fichier (vérifier toutes les 2 secondes)
            setInterval(() => {
                if (fs.existsSync(cacheFile)) {
                    const stats = fs.statSync(cacheFile);
                    const cacheKey = `_cache_mtime_${cacheFile}`;
                    const lastMtime = this.printOptionsCache.get(cacheKey);

                    if (!lastMtime || stats.mtime.getTime() !== lastMtime) {
                        this.printOptionsCache.set(cacheKey, stats.mtime.getTime());
                        this.loadPrintParamsCache();
                    }
                }
            }, 2000);
        } catch (error) {
            console.warn('⚠️ [PRINT_CACHE] Erreur surveillance cache:', error.message);
        }
    }

    /**
     * Enregistrer les paramètres d'impression pour un document (appelé AVANT l'impression)
     * Cette fonction doit être appelée juste avant de lancer une impression pour capturer les paramètres
     * @param {string} filePath - Chemin complet du fichier à imprimer
     * @param {Object} options - Options d'impression (paperSize, duplex, colorMode, copies, etc.)
     */
    registerPrintJob(filePath, options = {}) {
        if (!filePath) {
            console.warn('⚠️ [PRINT_CACHE] registerPrintJob appelé sans filePath');
            return;
        }

        const fileName = path.basename(filePath);
        const baseName = path.basename(filePath, path.extname(filePath));
        const fullPath = path.resolve(filePath);

        // Normaliser les options
        const normalizedOptions = {
            pageSize: options.pageSize || options.paperSize || null,
            duplex: options.duplex !== undefined ? options.duplex : (options.isDuplex !== undefined ? options.isDuplex : null),
            colorMode: options.colorMode || options.color || null,
            copies: options.copies || 1,
            orientation: options.orientation || null,
            resolution: options.resolution || null,
            inputSlot: options.inputSlot || null
        };

        // Normaliser duplex
        if (normalizedOptions.duplex === true || normalizedOptions.duplex === 'Duplex' || normalizedOptions.duplex === 'duplex') {
            normalizedOptions.duplex = 'Duplex';
        } else if (normalizedOptions.duplex === false || normalizedOptions.duplex === 'Simplex' || normalizedOptions.duplex === 'simplex') {
            normalizedOptions.duplex = 'Simplex';
        }

        // Normaliser colorMode
        if (normalizedOptions.colorMode === true || normalizedOptions.colorMode === 'Color' || normalizedOptions.colorMode === 'color' || normalizedOptions.colorMode === 2) {
            normalizedOptions.colorMode = 'Color';
        } else if (normalizedOptions.colorMode === false || normalizedOptions.colorMode === 'Monochrome' || normalizedOptions.colorMode === 'monochrome' || normalizedOptions.colorMode === 1) {
            normalizedOptions.colorMode = 'Monochrome';
        }

        const cacheEntry = {
            timestamp: Date.now(),
            fileName: fileName,
            baseName: baseName,
            fullPath: fullPath,
            options: normalizedOptions
        };

        // Stocker avec plusieurs clés pour faciliter la recherche
        const keys = [
            fileName,
            baseName,
            fullPath,
            fileName.toLowerCase(),
            baseName.toLowerCase(),
            fullPath.toLowerCase()
        ];

        keys.forEach(key => {
            this.printOptionsCache.set(key, cacheEntry);
        });

        console.log('✅ [PRINT_CACHE] Paramètres enregistrés pour:', fileName);
        console.log('   Options:', JSON.stringify(normalizedOptions, null, 2));
        console.log('   Clés enregistrées:', keys.slice(0, 3).join(', '), '...');

        // Nettoyer après 120 secondes (plus long pour laisser le temps à la détection)
        setTimeout(() => {
            keys.forEach(key => {
                if (this.printOptionsCache.get(key) === cacheEntry) {
                    this.printOptionsCache.delete(key);
                }
            });
        }, 120000);
    }

    /**
     * Définir les options d'impression pour un document (méthode interne)
     * @param {string} documentKey - Clé du document (nom de fichier ou chemin)
     * @param {Object} optionsEntry - Entrée du cache avec timestamp et options
     */
    setPrintOptions(documentKey, optionsEntry) {
        this.printOptionsCache.set(documentKey, optionsEntry);
        // Nettoyer après 60 secondes
        setTimeout(() => {
            this.printOptionsCache.delete(documentKey);
        }, 60000);
    }

    /**
     * Récupérer les options d'impression pour un document
     * @param {string} documentName - Nom du document
     * @returns {Object|null} Options d'impression ou null si non trouvées
     */
    getPrintOptions(documentName) {
        if (!documentName) return null;

        const docNameNormalized = String(documentName).trim();
        const docNameLower = docNameNormalized.toLowerCase();

        // Extraire différentes variantes du nom de document
        const docFileName = path.basename(docNameNormalized);
        const docBaseName = path.basename(docNameNormalized, path.extname(docNameNormalized));
        const docFileNameLower = docFileName.toLowerCase();
        const docBaseNameLower = docBaseName.toLowerCase();

        console.log('🔍 [PRINT_CACHE] Recherche options pour:', docNameNormalized);
        console.log('   Variantes:', { docFileName, docBaseName });
        console.log('   Taille du cache:', this.printOptionsCache.size);

        // Lister toutes les clés dans le cache pour debug
        if (this.printOptionsCache.size > 0) {
            console.log('   Clés dans le cache:', Array.from(this.printOptionsCache.keys()).slice(0, 5));
        }

        // Normaliser le chemin complet pour la recherche
        let docFullPath = docNameNormalized;
        try {
            docFullPath = path.resolve(docNameNormalized);
        } catch (e) {
            // Ignorer les erreurs de résolution de chemin
        }

        // Essayer plusieurs clés exactes
        const exactKeys = [
            docNameNormalized,
            docFullPath,
            docFileName,
            docBaseName,
            docNameLower,
            docFullPath.toLowerCase(),
            docFileNameLower,
            docBaseNameLower
        ];

        for (const key of exactKeys) {
            const entry = this.printOptionsCache.get(key);
            if (entry) {
                const age = Date.now() - entry.timestamp;
                if (age < 120000) { // 2 minutes pour correspondre au temps de nettoyage
                    console.log('   ✅ Trouvé avec clé exacte:', key);
                    return entry;
                } else {
                    // Nettoyer les entrées expirées
                    this.printOptionsCache.delete(key);
                }
            }
        }

        // Recherche partielle améliorée (dans les deux sens)
        for (const [cacheKey, entry] of this.printOptionsCache.entries()) {
            // Ignorer les clés de métadonnées (comme _cache_mtime_...)
            if (String(cacheKey).startsWith('_cache_')) {
                continue;
            }

            if (!entry || typeof entry !== 'object') {
                continue;
            }

            const age = Date.now() - (entry.timestamp || 0);
            if (age >= 120000) { // 2 minutes pour correspondre au temps de nettoyage
                // Nettoyer les entrées expirées
                this.printOptionsCache.delete(cacheKey);
                continue;
            }

            // Vérifier que les propriétés existent avant d'y accéder
            if (!entry.fileName || !entry.baseName) {
                continue;
            }

            const entryFileNameLower = String(entry.fileName).toLowerCase();
            const entryBaseNameLower = String(entry.baseName).toLowerCase();
            const entryFullPathLower = entry.fullPath ? String(entry.fullPath).toLowerCase() : '';
            const cacheKeyLower = String(cacheKey).toLowerCase();

            // Vérifier si le nom du document correspond au nom du fichier dans le cache
            // Recherche exacte
            if (docFileNameLower === entryFileNameLower ||
                docBaseNameLower === entryBaseNameLower ||
                docFileNameLower === cacheKeyLower ||
                docBaseNameLower === cacheKeyLower ||
                (docFullPath && entryFullPathLower && docFullPath.toLowerCase() === entryFullPathLower) ||
                (docNameLower && entryFullPathLower && docNameLower === entryFullPathLower)) {
                console.log('   ✅ Trouvé avec correspondance exacte:', cacheKey, '->', entry.fileName);
                return entry;
            }

            // Recherche partielle (le nom du document contient le nom de base ou vice versa)
            if (docFileNameLower.includes(entryBaseNameLower) ||
                entryBaseNameLower.includes(docBaseNameLower) ||
                docNameLower.includes(entryBaseNameLower) ||
                entryBaseNameLower.includes(docNameLower)) {
                console.log('   ✅ Trouvé avec recherche partielle:', cacheKey, '->', entry.fileName);
                return entry;
            }

            // Recherche par correspondance de fin (si le nom se termine par le même nom de fichier)
            if (docFileNameLower.endsWith(entryFileNameLower) || entryFileNameLower.endsWith(docFileNameLower)) {
                console.log('   ✅ Trouvé avec correspondance de fin:', cacheKey, '->', entry.fileName);
                return entry;
            }
        }

        console.log('   ❌ Aucune option trouvée dans le cache');
        console.log('   Cache actuel:', Array.from(this.printOptionsCache.keys()).slice(0, 5));
        return null;
    }

    /**
     * Démarrer la surveillance du spooler d'impression
>>>>>>> origin/feature/impression-complete
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

<<<<<<< HEAD
        if (!win32Printer) {
            console.error('❌ Impossible de démarrer : Module natif win32-printer introuvable');
            if (this.callbacks.onError) {
                this.callbacks.onError(new Error('Module natif imprimante manquant'));
=======
        console.log('🚀 Démarrage de la surveillance du pool d\'imprimantes Windows...');
        console.log(`📦 [PRINT_CACHE] Taille du cache au démarrage: ${this.printOptionsCache.size} entrées`);
        if (this.printOptionsCache.size > 0) {
            const sampleKeys = Array.from(this.printOptionsCache.keys()).slice(0, 3);
            console.log(`   Exemples de clés: ${sampleKeys.join(', ')}`);
        }
        this.monitoring = true;
        try {
            this.startPowerShellMonitor();
            console.log('✅ Script PowerShell de surveillance lancé');
            return true;
        } catch (error) {
            console.error('❌ Erreur lors du lancement du script PowerShell:', error);
            this.monitoring = false;
            return false;
        }
    }

    /**
     * Arrêter la surveillance
     */
    stop() {
        if (!this.monitoring) {
            return;
        }

        console.log('Arrêt de la surveillance du pool d\'imprimantes...');
        this.monitoring = false;

        if (this.powerShellProcess) {
            this.powerShellProcess.kill();
            this.powerShellProcess = null;
        }

        // Nettoyer le fichier temporaire si il existe
        if (this.tempScriptPath && fs.existsSync(this.tempScriptPath)) {
            try {
                fs.unlinkSync(this.tempScriptPath);
                this.tempScriptPath = null;
            } catch (error) {
                // Ignorer les erreurs de suppression
>>>>>>> origin/feature/impression-complete
            }
            return false;
        }
<<<<<<< HEAD

        console.log('🚀 Démarrage de la surveillance NATIVE du pool d\'imprimantes...');
        this.monitoring = true;

        try {
            const success = win32Printer.startPrinterMonitor((event, data) => {
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
=======
    }

    /**
     * Démarrer le script PowerShell de surveillance
     */
    startPowerShellMonitor() {
        console.log('📝 Préparation du script PowerShell...');
        // Script PowerShell qui surveille les événements d'impression via WMI
        const psScript = `
$ErrorActionPreference = "Continue"

# Définir les types et APIs Windows nécessaires pour lire le DEVMODE
try {
    Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
using System.Text;

public class Win32PrintJob {
    // Constantes Windows
    public const int JOB_INFO_2 = 2;
    public const int DMPAPER_LETTER = 1;
    public const int DMPAPER_LEGAL = 5;
    public const int DMPAPER_A3 = 8;
    public const int DMPAPER_A4 = 9;
    public const int DMPAPER_A5 = 11;
    public const int DMPAPER_B4 = 12;
    public const int DMPAPER_B5 = 13;
    public const int DMPAPER_A2 = 66;
    public const int DMPAPER_A1 = 65;
    public const int DMPAPER_A0 = 64;

    public const int DMDUP_SIMPLEX = 1;
    public const int DMDUP_VERTICAL = 2;
    public const int DMDUP_HORIZONTAL = 3;

    public const int DMCOLOR_COLOR = 2;
    public const int DMCOLOR_MONOCHROME = 1;

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool GetJob(IntPtr hPrinter, uint JobId, int Level, IntPtr pJob, int cbBuf, out int pcbNeeded, out int pcReturned);

    [DllImport("winspool.drv", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern int DocumentProperties(IntPtr hWnd, IntPtr hPrinter, string pDeviceName, IntPtr pDevModeOutput, IntPtr pDevModeInput, int fMode);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern int DeviceCapabilities(string pDevice, string pPort, int fwCapability, IntPtr pOutput, IntPtr pDevMode);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool GetPrinter(IntPtr hPrinter, int Level, IntPtr pPrinter, int cbBuf, out int pcbNeeded);

    public const int DM_OUT_BUFFER = 2;
    public const int DM_IN_BUFFER = 8;
    public const int DC_COLORDEVICE = 4;
    public const int DC_DUPLEX = 7;
    public const int PRINTER_INFO_2 = 2;
}
"@
} catch {
    # Si le type existe déjà, ignorer l'erreur
}

# Fonction pour lire Color et Duplex depuis les événements Windows Print Service
function Get-JobInfoFromPrintServiceEvents {
    param($PrinterName, $JobId, $DocumentName, $TimeSubmitted)

    try {
        [Console]::Error.WriteLine("DEBUG: Get-JobInfoFromPrintServiceEvents pour Printer: $PrinterName, JobId: $JobId, Document: $DocumentName")

        $result = @{}

        # Chercher dans plusieurs types d'événements Windows Print Service
        $eventIds = @(215, 307, 800, 801, 802)  # Différents événements d'impression

        foreach ($eventId in $eventIds) {
            try {
                $events = Get-WinEvent -FilterHashtable @{
                    LogName = 'Microsoft-Windows-PrintService/Operational'
                    ID = $eventId
                } -MaxEvents 100 -ErrorAction SilentlyContinue

                if ($events) {
                    foreach ($event in $events) {
                        try {
                            $xml = [xml]$event.ToXml()
                            $eventData = $xml.Event.EventData.Data

                            # Chercher le job correspondant (par JobId, Document, ou TimeSubmitted)
                            $eventJobId = $null
                            $eventDocName = $null
                            $eventPrinter = $null

                            # Extraire les données de l'événement
                            foreach ($data in $eventData) {
                                $name = $data.Name
                                $value = $data.'#text'

                                if ($name -eq 'JobId' -or $name -eq 'Param1') {
                                    $eventJobId = $value
                                }
                                if ($name -eq 'DocumentName' -or $name -eq 'Param2' -or $name -eq 'Param3') {
                                    $eventDocName = $value
                                }
                                if ($name -eq 'PrinterName' -or $name -eq 'Param4') {
                                    $eventPrinter = $value
                                }

                                # Chercher des indices de Color et Duplex dans les données
                                if ($value -and ($name -match 'color|Color|COLOR' -or $value -match 'color|Color|COLOR')) {
                                    [Console]::Error.WriteLine("DEBUG: Event $eventId - Indice Color trouvé: $name = $value")
                                    if ($value -match 'Color|Couleur|2') {
                                        $result.Color = 2
                                    } elseif ($value -match 'Monochrome|Mono|1') {
                                        $result.Color = 1
                                    }
                                }
                                if ($value -and ($name -match 'duplex|Duplex|DUPLEX' -or $value -match 'duplex|Duplex|DUPLEX')) {
                                    [Console]::Error.WriteLine("DEBUG: Event $eventId - Indice Duplex trouvé: $name = $value")
                                    if ($value -match 'Duplex|Recto|2|3') {
                                        $result.Duplex = 2
                                    } elseif ($value -match 'Simplex|1') {
                                        $result.Duplex = 1
                                    }
                                }
                            }

                            # Vérifier si c'est le bon job
                            $isMatch = $false
                            if ($JobId -and $eventJobId -and [int]$eventJobId -eq [int]$JobId) {
                                $isMatch = $true
                            } elseif ($DocumentName -and $eventDocName -and $eventDocName -like "*$DocumentName*") {
                                $isMatch = $true
                            } elseif ($PrinterName -and $eventPrinter -and $eventPrinter -like "*$PrinterName*") {
                                # Vérifier aussi le temps (dans les 30 secondes)
                                $timeDiff = [Math]::Abs(($event.TimeCreated - $TimeSubmitted).TotalSeconds)
                                if ($timeDiff -lt 30) {
                                    $isMatch = $true
                                }
                            }

                            if ($isMatch) {
                                [Console]::Error.WriteLine("DEBUG: Event $eventId correspond au job - JobId: $eventJobId, Doc: $eventDocName, Printer: $eventPrinter")

                                # Afficher toutes les données de l'événement pour debug
                                [Console]::Error.WriteLine("DEBUG: Toutes les données de l'événement $eventId :")
                                foreach ($data in $eventData) {
                                    [Console]::Error.WriteLine("  $($data.Name) = $($data.'#text')")
                                }

                                # Si on a trouvé Color ou Duplex, on peut retourner
                                if ($result.Color -or $result.Duplex) {
                                    return $result
                                }
                            }
                        } catch {
                            [Console]::Error.WriteLine("DEBUG: Erreur parsing event $eventId : $($_.Exception.Message)")
                        }
                    }
                }
            } catch {
                [Console]::Error.WriteLine("DEBUG: Erreur lecture events $eventId : $($_.Exception.Message)")
            }
        }

        # Si rien trouvé, essayer de chercher dans les événements récents par document
        if (-not $result.Color -and -not $result.Duplex) {
            [Console]::Error.WriteLine("DEBUG: Aucune info trouvée dans les événements, recherche par document...")

            # Chercher les événements récents (dernières 2 minutes) qui mentionnent le document
            $recentEvents = Get-WinEvent -FilterHashtable @{
                LogName = 'Microsoft-Windows-PrintService/Operational'
                StartTime = (Get-Date).AddMinutes(-2)
            } -MaxEvents 200 -ErrorAction SilentlyContinue

            if ($recentEvents) {
                foreach ($event in $recentEvents) {
                    try {
                        $xml = [xml]$event.ToXml()
                        $eventXml = $xml.OuterXml

                        # Chercher le nom du document dans le XML
                        if ($DocumentName -and $eventXml -like "*$DocumentName*") {
                            [Console]::Error.WriteLine("DEBUG: Event ID $($event.Id) mentionne le document: $DocumentName")

                            # Chercher Color et Duplex dans le XML brut
                            if ($eventXml -match 'color|Color|COLOR|Couleur') {
                                [Console]::Error.WriteLine("DEBUG: Indice Color trouvé dans XML de l'événement $($event.Id)")
                                if ($eventXml -match 'Color|Couleur|2') {
                                    $result.Color = 2
                                } elseif ($eventXml -match 'Monochrome|Mono|1') {
                                    $result.Color = 1
                                }
                            }
                            if ($eventXml -match 'duplex|Duplex|DUPLEX|Recto') {
                                [Console]::Error.WriteLine("DEBUG: Indice Duplex trouvé dans XML de l'événement $($event.Id)")
                                if ($eventXml -match 'Duplex|Recto|2|3') {
                                    $result.Duplex = 2
                                } elseif ($eventXml -match 'Simplex|1') {
                                    $result.Duplex = 1
                                }
                            }

                            if ($result.Color -or $result.Duplex) {
                                return $result
                            }
                        }
                    } catch {
                        # Ignorer
                    }
                }
            }
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Erreur Get-JobInfoFromPrintServiceEvents: $($_.Exception.Message)")
    }

    if ($result.Count -gt 0) {
        return $result
    }

    return $null
}

# Fonction pour obtenir les capacités de l'imprimante via DeviceCapabilities
function Get-PrinterCapabilities {
    param($PrinterName)

    try {
        [Console]::Error.WriteLine("DEBUG: Get-PrinterCapabilities pour: $PrinterName")

        # Vérifier le support couleur
        # DeviceCapabilities retourne -1 en cas d'erreur, 0 si non supporté, 1 si supporté
        $colorSupport = [Win32PrintJob]::DeviceCapabilities($PrinterName, $null, [Win32PrintJob]::DC_COLORDEVICE, [IntPtr]::Zero, [IntPtr]::Zero)
        [Console]::Error.WriteLine("DEBUG: DeviceCapabilities DC_COLORDEVICE: $colorSupport")

        # Filtrer les valeurs aberrantes (si > 1, c'est probablement une erreur)
        $supportsColor = ($colorSupport -eq 1)
        if ($colorSupport -gt 1 -or $colorSupport -lt 0) {
            [Console]::Error.WriteLine("DEBUG: Valeur aberrante pour DC_COLORDEVICE, utilisation valeur par défaut (False)")
            $supportsColor = $false
        }

        # Vérifier le support duplex
        $duplexSupport = [Win32PrintJob]::DeviceCapabilities($PrinterName, $null, [Win32PrintJob]::DC_DUPLEX, [IntPtr]::Zero, [IntPtr]::Zero)
        [Console]::Error.WriteLine("DEBUG: DeviceCapabilities DC_DUPLEX: $duplexSupport")

        # Filtrer les valeurs aberrantes
        $supportsDuplex = ($duplexSupport -eq 1)
        if ($duplexSupport -gt 1 -or $duplexSupport -lt 0) {
            [Console]::Error.WriteLine("DEBUG: Valeur aberrante pour DC_DUPLEX, utilisation valeur par défaut (False)")
            $supportsDuplex = $false
        }

        return @{
            SupportsColor = $supportsColor
            SupportsDuplex = $supportsDuplex
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Erreur Get-PrinterCapabilities: $($_.Exception.Message)")
        return $null
    }
}

# Fonction pour obtenir Color et Duplex depuis les propriétés WMI du job
function Get-JobPropertiesFromWMI {
    param($PrinterName, $JobId)

    try {
        [Console]::Error.WriteLine("DEBUG: Get-JobPropertiesFromWMI pour Printer: $PrinterName, JobId: $JobId")

        # Chercher le job dans WMI
        $wmiQuery = "SELECT * FROM Win32_PrintJob WHERE JobId = $JobId AND Name LIKE '%$PrinterName%'"
        $job = Get-WmiObject -Query $wmiQuery -ErrorAction SilentlyContinue

        if ($job) {
            [Console]::Error.WriteLine("DEBUG: Job WMI trouve: $($job.Name)")

            $result = @{}

            # Chercher dans les propriétés du job
            # Certaines imprimantes stockent ces infos dans des propriétés personnalisées
            $jobProperties = $job | Get-Member -MemberType Property
            foreach ($prop in $jobProperties) {
                $propName = $prop.Name
                $propValue = $job.$propName
                [Console]::Error.WriteLine("DEBUG: Propriete WMI: $propName = $propValue")

                # Chercher des indices de couleur ou duplex dans les propriétés
                if ($propName -match 'color|Color|COLOR' -and $propValue) {
                    $result.ColorHint = $propValue
                }
                if ($propName -match 'duplex|Duplex|DUPLEX|TwoSided|RectoVerso' -and $propValue) {
                    $result.DuplexHint = $propValue
                    # Tenter une valeur booléenne
                    if ($propValue -match 'Duplex|TwoSided|RectoVerso|RectoVerso|LongEdge|ShortEdge|True|true|1|2|3') {
                        $result.Duplex = $true
                        [Console]::Error.WriteLine("DEBUG: Duplex detecte depuis WMI: $propName = $propValue")
                    } elseif ($propValue -match 'Simplex|OneSided|False|false|0') {
                        $result.Duplex = $false
                        [Console]::Error.WriteLine("DEBUG: Simplex detecte depuis WMI: $propName = $propValue")
                    }
                }
            }

            # Color direct si présent
            if ($job.Color) {
                if ($job.Color -match 'Mono') { $result.Color = 'Monochrome' }
                elseif ($job.Color -match 'Color|Couleur') { $result.Color = 'Color' }
            }

            # Pages si disponibles
            if ($job.TotalPages -and $job.TotalPages -gt 0) {
                $result.TotalPages = [int]$job.TotalPages
            }
            if ($job.PagesPrinted -and $job.PagesPrinted -gt 0) {
                $result.PagesPrinted = [int]$job.PagesPrinted
            }

            # Extraire le format papier depuis PaperLength/PaperWidth (en 1/10 mm)
            # A4: 2970 x 2100 (297mm x 210mm)
            # A3: 4200 x 2970 (420mm x 297mm)
            # Letter: 2794 x 2159 (279.4mm x 215.9mm)
            # Legal: 3556 x 2159 (355.6mm x 215.9mm)
            if ($job.PaperLength -and $job.PaperWidth) {
                $length = [int]$job.PaperLength
                $width = [int]$job.PaperWidth

                # Normaliser (le plus grand côté est la longueur)
                if ($width -gt $length) {
                    $temp = $length
                    $length = $width
                    $width = $temp
                }

                [Console]::Error.WriteLine("DEBUG: WMI PaperLength: $length, PaperWidth: $width")

                # Détecter le format
                if ($length -eq 4200 -and $width -eq 2970) {
                    $result.PaperSize = "A3"
                    [Console]::Error.WriteLine("DEBUG: Format detecte depuis WMI: A3 (4200x2970)")
                } elseif ($length -eq 2970 -and $width -eq 2100) {
                    $result.PaperSize = "A4"
                    [Console]::Error.WriteLine("DEBUG: Format detecte depuis WMI: A4 (2970x2100)")
                } elseif ($length -eq 2794 -and $width -eq 2159) {
                    $result.PaperSize = "Letter"
                    [Console]::Error.WriteLine("DEBUG: Format detecte depuis WMI: Letter (2794x2159)")
                } elseif ($length -eq 3556 -and $width -eq 2159) {
                    $result.PaperSize = "Legal"
                    [Console]::Error.WriteLine("DEBUG: Format detecte depuis WMI: Legal (3556x2159)")
                } else {
                    # Essayer aussi PaperSize si disponible (format texte)
                    if ($job.PaperSize) {
                        $paperSizeStr = $job.PaperSize.ToString()
                        if ($paperSizeStr -match 'A3') {
                            $result.PaperSize = "A3"
                        } elseif ($paperSizeStr -match 'A4') {
                            $result.PaperSize = "A4"
                        } elseif ($paperSizeStr -match 'Letter') {
                            $result.PaperSize = "Letter"
                        } elseif ($paperSizeStr -match 'Legal') {
                            $result.PaperSize = "Legal"
                        }
                        [Console]::Error.WriteLine("DEBUG: Format depuis WMI PaperSize string: $($result.PaperSize)")
                    }
                }
            } elseif ($job.PaperSize) {
                # Utiliser PaperSize si disponible
                $paperSizeStr = $job.PaperSize.ToString()
                if ($paperSizeStr -match 'A3') {
                    $result.PaperSize = "A3"
                } elseif ($paperSizeStr -match 'A4') {
                    $result.PaperSize = "A4"
                } elseif ($paperSizeStr -match 'Letter') {
                    $result.PaperSize = "Letter"
                } elseif ($paperSizeStr -match 'Legal') {
                    $result.PaperSize = "Legal"
                }
                [Console]::Error.WriteLine("DEBUG: Format depuis WMI PaperSize: $($result.PaperSize)")
            }

            # Déterminer le format via PaperLength/PaperWidth (unités 0.1 mm)
            $paperLength = $job.PaperLength
            $paperWidth  = $job.PaperWidth
            if ($paperLength -and $paperWidth) {
                $len = [int]$paperLength
                $wid = [int]$paperWidth
                # A3: 4200 x 2970 (0.1mm)
                if (($len -eq 4200 -and $wid -eq 2970) -or ($len -eq 2970 -and $wid -eq 4200)) {
                    $result.PaperSize = 'A3'
                }
                # A4: 2970 x 2100 (0.1mm)
                elseif (($len -eq 2970 -and $wid -eq 2100) -or ($len -eq 2100 -and $wid -eq 2970)) {
                    $result.PaperSize = 'A4'
                }
                # A5: 2100 x 1480 (0.1mm)
                elseif (($len -eq 2100 -and $wid -eq 1480) -or ($len -eq 1480 -and $wid -eq 2100)) {
                    $result.PaperSize = 'A5'
                }
            }

            return $result
        } else {
            [Console]::Error.WriteLine("DEBUG: Job WMI non trouve")
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Erreur Get-JobPropertiesFromWMI: $($_.Exception.Message)")
    }

    return $null
}

# Fonction pour lire les paramètres personnalisés du job depuis pParameters
function Get-JobParameters {
    param($PrinterName, $JobId)

    try {
        [Console]::Error.WriteLine("DEBUG: Get-JobParameters pour Printer: $PrinterName, JobId: $JobId")

        $hPrinter = [IntPtr]::Zero
        if (-not [Win32PrintJob]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
            return $null
        }

        try {
            # Obtenir la taille nécessaire pour JOB_INFO_2
            $needed = 0
            $returned = 0
            [Win32PrintJob]::GetJob($hPrinter, $JobId, 2, [IntPtr]::Zero, 0, [ref]$needed, [ref]$returned) | Out-Null

            if ($needed -eq 0) {
                return $null
            }

            # Allouer la mémoire
            $jobInfoPtr = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($needed)

            try {
                if ([Win32PrintJob]::GetJob($hPrinter, $JobId, 2, $jobInfoPtr, $needed, [ref]$needed, [ref]$returned)) {
                    $ptrSize = [System.IntPtr]::Size

                    # JOB_INFO_2 structure pour 64-bit:
                    # Offset 32: LPTSTR pParameters (pointeur vers chaîne de paramètres)
                    $paramsOffset = if ($ptrSize -eq 8) { 32 } else { 16 }

                    # Lire le pointeur vers pParameters
                    $paramsPtrAddr = if ($ptrSize -eq 8) {
                        [System.Runtime.InteropServices.Marshal]::ReadInt64($jobInfoPtr, $paramsOffset)
                    } else {
                        [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, $paramsOffset)
                    }

                    if ($paramsPtrAddr -ne 0) {
                        $paramsStr = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.IntPtr]$paramsPtrAddr)
                        [Console]::Error.WriteLine("DEBUG: Parametres du job: $paramsStr")

                        if ($paramsStr) {
                            $result = @{}

                            # Chercher Color et Duplex dans les paramètres
                            if ($paramsStr -match 'color[=:](Color|Couleur|2)', 'IgnoreCase') {
                                $result.Color = 2
                                [Console]::Error.WriteLine("DEBUG: Color trouve dans parametres: Color")
                            } elseif ($paramsStr -match 'color[=:](Monochrome|Mono|1)', 'IgnoreCase') {
                                $result.Color = 1
                                [Console]::Error.WriteLine("DEBUG: Color trouve dans parametres: Monochrome")
                            }

                            if ($paramsStr -match 'duplex[=:](Duplex|Recto|2|3)', 'IgnoreCase') {
                                $result.Duplex = 2
                                [Console]::Error.WriteLine("DEBUG: Duplex trouve dans parametres: Duplex")
                            } elseif ($paramsStr -match 'duplex[=:](Simplex|1)', 'IgnoreCase') {
                                $result.Duplex = 1
                                [Console]::Error.WriteLine("DEBUG: Duplex trouve dans parametres: Simplex")
                            }

                            if ($result.Count -gt 0) {
                                return $result
                            }
                        }
                    }
                }
            } finally {
                [System.Runtime.InteropServices.Marshal]::FreeHGlobal($jobInfoPtr)
            }
        } finally {
            [Win32PrintJob]::ClosePrinter($hPrinter)
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Erreur Get-JobParameters: $($_.Exception.Message)")
    }

    return $null
}

# Fonction pour lire les propriétés étendues du job depuis JOB_INFO_2
function Get-JobExtendedProperties {
    param($PrinterName, $JobId)

    try {
        [Console]::Error.WriteLine("DEBUG: Get-JobExtendedProperties pour Printer: $PrinterName, JobId: $JobId")

        $hPrinter = [IntPtr]::Zero
        if (-not [Win32PrintJob]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
            return $null
        }

        try {
            # Obtenir la taille nécessaire pour JOB_INFO_2
            $needed = 0
            $returned = 0
            [Win32PrintJob]::GetJob($hPrinter, $JobId, 2, [IntPtr]::Zero, 0, [ref]$needed, [ref]$returned) | Out-Null

            if ($needed -eq 0) {
                return $null
            }

            # Allouer la mémoire
            $jobInfoPtr = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($needed)

            try {
                if ([Win32PrintJob]::GetJob($hPrinter, $JobId, 2, $jobInfoPtr, $needed, [ref]$needed, [ref]$returned)) {
                    $ptrSize = [System.IntPtr]::Size

                    # JOB_INFO_2 structure pour 64-bit:
                    # Offset 80: PDEVMODE pDevMode (pointeur)
                    # Offset 88: LPTSTR pStatus (pointeur)
                    # Offset 96: PSECURITY_DESCRIPTOR pSecurityDescriptor (pointeur)
                    # Offset 104: DWORD Status
                    # Offset 108: DWORD Priority
                    # Offset 112: DWORD Position
                    # Offset 116: DWORD StartTime
                    # Offset 120: DWORD UntilTime
                    # Offset 124: DWORD TotalPages
                    # Offset 128: DWORD Size
                    # Offset 132: SYSTEMTIME Submitted
                    # Offset 148: DWORD PagesPrinted
                    # Offset 152: DWORD SizeHigh

                    # Lire le pointeur DEVMODE
                    $devModeOffset = if ($ptrSize -eq 8) { 80 } else { 40 }
                    $devModePtrAddr = if ($ptrSize -eq 8) {
                        [System.Runtime.InteropServices.Marshal]::ReadInt64($jobInfoPtr, $devModeOffset)
                    } else {
                        [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, $devModeOffset)
                    }

                    if ($devModePtrAddr -ne 0) {
                        $devModePtr = [System.IntPtr]$devModePtrAddr

                        # Lire dmFields pour voir quels champs sont présents
                        $dmFields = [System.Runtime.InteropServices.Marshal]::ReadInt32($devModePtr, 6)
                        [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE dmFields: $dmFields (hex: 0x$($dmFields.ToString('X')))")

                        $result = @{}

                        # Vérifier DM_COLOR (0x800)
                        if (($dmFields -band 0x800) -ne 0) {
                            $dmColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 26)
                            $result.Color = $dmColor
                            [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Color lu: $dmColor")
                        } else {
                            [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE - DM_COLOR bit non present")
                        }

                        # Vérifier DM_DUPLEX (0x1000)
                        if (($dmFields -band 0x1000) -ne 0) {
                            $dmDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 28)
                            $result.Duplex = $dmDuplex
                            [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Duplex lu: $dmDuplex")
                        } else {
                            [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE - DM_DUPLEX bit non present")
                        }

                        # TOUJOURS lire Color et Duplex aux offsets standards, même si les bits ne sont pas présents
                        # Certains drivers (comme RISO) ne définissent pas les bits mais utilisent quand même les champs
                        if (-not $result.Color) {
                            try {
                                $testColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 26)
                                [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Color lu à offset 26 (sans vérification bit): $testColor")
                                if ($testColor -eq 1 -or $testColor -eq 2) {
                                    $result.Color = $testColor
                                    [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Color accepté: $testColor")
                                } else {
                                    [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Color valeur invalide: $testColor")
                                }
                            } catch {
                                [Console]::Error.WriteLine("DEBUG: Erreur lecture Color à offset 26: $($_.Exception.Message)")
                            }
                        }

                        if (-not $result.Duplex) {
                            try {
                                $testDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 28)
                                [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Duplex lu à offset 28 (sans vérification bit): $testDuplex")
                                if ($testDuplex -ge 1 -and $testDuplex -le 3) {
                                    $result.Duplex = $testDuplex
                                    [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Duplex accepté: $testDuplex")
                                } else {
                                    [Console]::Error.WriteLine("DEBUG: JOB_INFO_2 DEVMODE Duplex valeur invalide: $testDuplex")
                                }
                            } catch {
                                [Console]::Error.WriteLine("DEBUG: Erreur lecture Duplex à offset 28: $($_.Exception.Message)")
                            }
                        }

                        return $result
                    }
                }
            } finally {
                [System.Runtime.InteropServices.Marshal]::FreeHGlobal($jobInfoPtr)
            }
        } finally {
            [Win32PrintJob]::ClosePrinter($hPrinter)
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Erreur Get-JobExtendedProperties: $($_.Exception.Message)")
    }

    return $null
}

# Fonction pour obtenir le DEVMODE de l'imprimante via DocumentProperties
function Get-PrinterDevMode {
    param($PrinterName)

    try {
        $hPrinter = [IntPtr]::Zero
        if (-not [Win32PrintJob]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
            return $null
        }

        try {
            # Obtenir la taille nécessaire pour le DEVMODE
            $devModeSize = [Win32PrintJob]::DocumentProperties([IntPtr]::Zero, $hPrinter, $PrinterName, [IntPtr]::Zero, [IntPtr]::Zero, 0)
            if ($devModeSize -le 0) {
                return $null
            }

            # Allouer la mémoire pour le DEVMODE
            $devModePtr = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($devModeSize)
            try {
                # Obtenir le DEVMODE
                $result = [Win32PrintJob]::DocumentProperties([IntPtr]::Zero, $hPrinter, $PrinterName, $devModePtr, [IntPtr]::Zero, [Win32PrintJob]::DM_OUT_BUFFER)
                if ($result -ge 0) {
                    $dmFields = [System.Runtime.InteropServices.Marshal]::ReadInt32($devModePtr, 6)
                    $result = @{}

                    if (($dmFields -band 0x800) -ne 0) {
                        $result.Color = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 26)
                    }
                    if (($dmFields -band 0x1000) -ne 0) {
                        $result.Duplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 28)
                    }

                    return $result
                }
            } finally {
                [System.Runtime.InteropServices.Marshal]::FreeHGlobal($devModePtr)
            }
        } finally {
            [Win32PrintJob]::ClosePrinter($hPrinter)
        }
    } catch {
        # Ignorer les erreurs
    }

    return $null
}

# Fonction pour lire le DEVMODE directement depuis le job d'impression
function Get-JobDevMode {
    param($PrinterName, $JobId)

    [Console]::Error.WriteLine("DEBUG: Get-JobDevMode appelée pour Printer: $PrinterName, JobId: $JobId")

    try {
        $hPrinter = [IntPtr]::Zero

        # Ouvrir l'imprimante
        [Console]::Error.WriteLine("DEBUG: Tentative d'ouverture de l'imprimante: $PrinterName")
        if (-not [Win32PrintJob]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
            $lastError = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
            [Console]::Error.WriteLine("DEBUG: OpenPrinter échoué avec erreur: $lastError")
            return $null
        }
        [Console]::Error.WriteLine("DEBUG: OpenPrinter réussi, handle: $hPrinter")

        try {
            # Obtenir la taille nécessaire pour JOB_INFO_2
            $needed = 0
            $returned = 0
            [Win32PrintJob]::GetJob($hPrinter, $JobId, 2, [IntPtr]::Zero, 0, [ref]$needed, [ref]$returned) | Out-Null

            if ($needed -eq 0) {
                return $null
            }

            # Allouer la mémoire pour JOB_INFO_2
            $jobInfoPtr = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($needed)

            try {
                    # Obtenir les informations du job
                [Console]::Error.WriteLine("DEBUG: Tentative GetJob pour JobId: $JobId, taille nécessaire: $needed")
                if ([Win32PrintJob]::GetJob($hPrinter, $JobId, 2, $jobInfoPtr, $needed, [ref]$needed, [ref]$returned)) {
                    [Console]::Error.WriteLine("DEBUG: GetJob réussi, returned: $returned")
                    # JOB_INFO_2 structure:
                    # Offset 0: DWORD JobId
                    # Offset 4: LPTSTR pPrinterName (pointeur)
                    # Offset 8: LPTSTR pMachineName (pointeur)
                    # Offset 12: LPTSTR pUserName (pointeur)
                    # Offset 16: LPTSTR pDocument (pointeur)
                    # Offset 20: LPTSTR pNotifyName (pointeur)
                    # Offset 24: LPTSTR pDatatype (pointeur)
                    # Offset 28: LPTSTR pPrintProcessor (pointeur)
                    # Offset 32: LPTSTR pParameters (pointeur)
                    # Offset 36: LPTSTR pDriverName (pointeur)
                    # Offset 40: PDEVMODE pDevMode (pointeur vers DEVMODE)
                    # Offset 44: LPTSTR pStatus (pointeur)
                    # Offset 48: PSECURITY_DESCRIPTOR pSecurityDescriptor
                    # Offset 52: DWORD Status
                    # Offset 56: DWORD Priority
                    # Offset 60: DWORD Position
                    # Offset 64: DWORD StartTime
                    # Offset 68: DWORD UntilTime
                    # Offset 72: DWORD TotalPages
                    # Offset 76: DWORD Size
                    # Offset 80: SYSTEMTIME Time
                    # Offset 96: DWORD PagesPrinted

                    $result = @{}

                    # Lire TotalPages et PagesPrinted directement depuis JOB_INFO_2
                    # Les offsets dépendent de l'architecture (32-bit vs 64-bit)
                    # Structure JOB_INFO_2 pour 64-bit:
                    # - Offset 0-3: JobId (DWORD)
                    # - Offset 4-7: padding
                    # - Offset 8-15: pPrinterName (pointer 8 bytes)
                    # - Offset 16-23: pMachineName
                    # - Offset 24-31: pUserName
                    # - Offset 32-39: pDocument
                    # - Offset 40-47: pNotifyName
                    # - Offset 48-55: pDatatype
                    # - Offset 56-63: pPrintProcessor
                    # - Offset 64-71: pParameters
                    # - Offset 72-79: pDriverName
                    # - Offset 80-87: pDevMode
                    # - Offset 88-95: pStatus
                    # - Offset 96-103: pSecurityDescriptor
                    # - Offset 104-107: Status (DWORD)
                    # - Offset 108-111: Priority (DWORD)
                    # - Offset 112-115: Position (DWORD)
                    # - Offset 116-119: StartTime (DWORD)
                    # - Offset 120-123: UntilTime (DWORD)
                    # - Offset 124-127: TotalPages (DWORD)
                    # - Offset 128-131: Size (DWORD)
                    # - Offset 132-147: Time (SYSTEMTIME, 16 bytes)
                    # - Offset 148-151: PagesPrinted (DWORD)
                    $ptrSize = [System.IntPtr]::Size
                    try {
                        if ($ptrSize -eq 8) {
                            # 64-bit: TotalPages à offset 124
                            $totalPages = [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, 124)
                            # PagesPrinted à offset 148, mais cette valeur est souvent incorrecte/aberrante
                            # On ne l'utilise pas, on utilisera WMI à la place
                        } else {
                            # 32-bit: TotalPages à offset 72
                            $totalPages = [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, 72)
                        }
                        [Console]::Error.WriteLine("DEBUG: TotalPages lu depuis JOB_INFO_2: $totalPages (ptrSize: $ptrSize)")
                        if ($totalPages -gt 0 -and $totalPages -lt 1000000) {  # Filtrer les valeurs aberrantes
                            $result.TotalPages = $totalPages
                        }
                        # PagesPrinted n'est pas lu depuis JOB_INFO_2 car l'offset donne des valeurs aberrantes
                        # On utilisera WMI (job.PagesPrinted) à la place
                    } catch {
                        [Console]::Error.WriteLine("DEBUG: Erreur lecture pages depuis JOB_INFO_2: $($_.Exception.Message)")
                    }

                    # Lire le pointeur vers le DEVMODE
                    # 64-bit: offset 80, 32-bit: offset 40
                    $devModeOffset = if ($ptrSize -eq 8) { 80 } else { 40 }

                    try {
                        if ($ptrSize -eq 8) {
                            # 64-bit
                            $devModePtrAddr = [System.Runtime.InteropServices.Marshal]::ReadInt64($jobInfoPtr, $devModeOffset)
                        } else {
                            # 32-bit
                            $devModePtrAddr = [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, $devModeOffset)
                        }

                        if ($devModePtrAddr -ne 0) {
                            [Console]::Error.WriteLine("DEBUG: Pointeur DEVMODE valide: $devModePtrAddr")
                            # Convertir l'adresse en IntPtr
                            $devModePtr = [System.IntPtr]$devModePtrAddr

                            # Lire les champs du DEVMODE
                            # DEVMODE structure (offsets pour x64):
                            # Offset 0: DWORD dmSize (généralement 220 pour x64)
                            # Offset 4: WORD dmDriverExtra
                            # Offset 6: DWORD dmFields
                            # Offset 10: SHORT dmOrientation
                            # Offset 12: SHORT dmPaperSize
                            # Offset 14: SHORT dmPaperLength
                            # Offset 16: SHORT dmPaperWidth
                            # Offset 18: SHORT dmScale
                            # Offset 20: SHORT dmCopies
                            # Offset 22: SHORT dmDefaultSource
                            # Offset 24: SHORT dmPrintQuality
                            # Offset 26: SHORT dmColor
                            # Offset 28: SHORT dmDuplex

                            try {
                                # Lire la taille du DEVMODE pour vérifier
                                $dmSize = [System.Runtime.InteropServices.Marshal]::ReadInt32($devModePtr, 0)
                                [Console]::Error.WriteLine("DEBUG: DEVMODE Size: $dmSize")

                                $dmFields = [System.Runtime.InteropServices.Marshal]::ReadInt32($devModePtr, 6)
                                [Console]::Error.WriteLine("DEBUG: dmFields lu: $dmFields (hex: 0x$($dmFields.ToString('X')))")

                                # Vérifier les bits présents
                                $hasPaperSize = (($dmFields -band 0x2) -ne 0)
                                $hasColor = (($dmFields -band 0x800) -ne 0)
                                $hasDuplex = (($dmFields -band 0x1000) -ne 0)
                                [Console]::Error.WriteLine("DEBUG: Bits présents - PaperSize: $hasPaperSize, Color: $hasColor, Duplex: $hasDuplex")

                                # Vérifier si dmPaperSize est défini (DM_PAPERSIZE = 0x00000002)
                                if ($hasPaperSize) {
                                    try {
                                        $dmPaperSize = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 12)
                                        $result.PaperSize = $dmPaperSize
                                        [Console]::Error.WriteLine("DEBUG: DEVMODE PaperSize lu: $dmPaperSize")
                                    } catch {
                                        [Console]::Error.WriteLine("DEBUG: Erreur lecture PaperSize: $($_.Exception.Message)")
                                    }
                                }

                                # TOUJOURS lire Color et Duplex aux offsets standards (26 et 28)
                                # Même si les bits ne sont pas présents dans dmFields, les valeurs peuvent être là
                                # (certains drivers comme RISO ne définissent pas les bits mais utilisent les champs)
                                try {
                                    $dmColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 26)
                                    [Console]::Error.WriteLine("DEBUG: DEVMODE Color lu à offset 26: $dmColor (bit présent: $hasColor)")
                                    if ($dmColor -eq 1 -or $dmColor -eq 2) {
                                        $result.Color = $dmColor
                                        [Console]::Error.WriteLine("DEBUG: DEVMODE Color accepté: $dmColor")
                                    } else {
                                        [Console]::Error.WriteLine("DEBUG: DEVMODE Color valeur invalide: $dmColor")
                                    }
                                } catch {
                                    [Console]::Error.WriteLine("DEBUG: Erreur lecture Color à offset 26: $($_.Exception.Message)")
                                }

                                try {
                                    $dmDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 28)
                                    [Console]::Error.WriteLine("DEBUG: DEVMODE Duplex lu à offset 28: $dmDuplex (bit présent: $hasDuplex)")
                                    if ($dmDuplex -ge 1 -and $dmDuplex -le 3) {
                                        $result.Duplex = $dmDuplex
                                        [Console]::Error.WriteLine("DEBUG: DEVMODE Duplex accepté: $dmDuplex")
                                    } else {
                                        [Console]::Error.WriteLine("DEBUG: DEVMODE Duplex valeur invalide: $dmDuplex")
                                    }
                                } catch {
                                    [Console]::Error.WriteLine("DEBUG: Erreur lecture Duplex à offset 28: $($_.Exception.Message)")
                                }

                                # Si Color ou Duplex ne sont pas trouvés, essayer de lire le DEVMODE complet
                                # et chercher ces valeurs à différents offsets (peut-être que la structure est différente)
                                if (-not $result.Color -or -not $result.Duplex) {
                                    [Console]::Error.WriteLine("DEBUG: Recherche approfondie Color/Duplex dans DEVMODE...")

                                    # Lire le DEVMODE complet jusqu'à dmSize
                                    if ($dmSize -gt 0 -and $dmSize -lt 1000) {
                                        [Console]::Error.WriteLine("DEBUG: Lecture DEVMODE complet (taille: $dmSize bytes)")

                                        # Essayer plusieurs offsets possibles pour Color et Duplex
                                        # Structure DEVMODE peut varier selon la version Windows et le driver
                                        $testOffsets = @(
                                            @{name="Color"; offsets=@(26, 86, 88, 90, 92, 94, 96, 98, 100)},
                                            @{name="Duplex"; offsets=@(28, 88, 90, 92, 94, 96, 98, 100, 102)}
                                        )

                                        foreach ($test in $testOffsets) {
                                            $fieldName = $test.name
                                            foreach ($offset in $test.offsets) {
                                                if ($offset -lt $dmSize) {
                                                    try {
                                                        $val = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, $offset)
                                                        if ($fieldName -eq "Color" -and ($val -eq 1 -or $val -eq 2)) {
                                                            if (-not $result.Color) {
                                                                $result.Color = $val
                                                                [Console]::Error.WriteLine("DEBUG: $fieldName trouvé à l'offset $offset : $val")
                                                            }
                                                        } elseif ($fieldName -eq "Duplex" -and ($val -ge 1 -and $val -le 3)) {
                                                            if (-not $result.Duplex) {
                                                                $result.Duplex = $val
                                                                [Console]::Error.WriteLine("DEBUG: $fieldName trouvé à l'offset $offset : $val")
                                                            }
                                                        }
                                                    } catch {
                                                        # Ignorer les erreurs de lecture
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch {
                                [Console]::Error.WriteLine("DEBUG: Erreur lecture dmFields: $($_.Exception.Message)")
                            }
                        } else {
                            [Console]::Error.WriteLine("DEBUG: DEVMODE pointeur est NULL")
                        }
                    } catch {
                        [Console]::Error.WriteLine("DEBUG: Erreur lecture pointeur DEVMODE: $($_.Exception.Message)")
                    }

                    if ($result.Count -gt 0) {
                        return $result
                    }
                } else {
                    $lastError = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
                    [Console]::Error.WriteLine("DEBUG: GetJob échoué avec erreur: $lastError")
                }
            } finally {
                [System.Runtime.InteropServices.Marshal]::FreeHGlobal($jobInfoPtr)
            }
        } finally {
            [Win32PrintJob]::ClosePrinter($hPrinter)
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: Get-JobDevMode exception: $($_.Exception.Message)")
        [Console]::Error.WriteLine("DEBUG: Get-JobDevMode stack: $($_.ScriptStackTrace)")
    }

    [Console]::Error.WriteLine("DEBUG: Get-JobDevMode retourne null pour Printer: $PrinterName, JobId: $JobId")
    return $null
}

# Fonction améliorée pour obtenir le format de papier, duplex et couleur depuis le DEVMODE
function Get-JobDetails {
    param($job)
    [Console]::Error.WriteLine("DEBUG: ===== Get-JobDetails DÉBUT pour JobId: $($job.JobId) =====")
    try {
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }

        $paperSize = "Unknown"
        $isDuplex = $false
        $colorMode = "Unknown"
        $printerCaps = $null  # Initialiser pour la portée

        # Essayer de lire le DEVMODE directement depuis le job
        [Console]::Error.WriteLine("DEBUG: Get-JobDetails - Tentative lecture DEVMODE pour Printer: $printerName, JobId: $($job.JobId)")
        $devModeInfo = Get-JobDevMode -PrinterName $printerName -JobId $job.JobId

        # Si Color ou Duplex ne sont pas trouvés dans le DEVMODE du job, essayer les méthodes alternatives
        if ($devModeInfo -and (-not $devModeInfo.Color -or -not $devModeInfo.Duplex)) {
            [Console]::Error.WriteLine("DEBUG: Color ou Duplex manquants dans DEVMODE du job, recherche alternatives...")

            # Méthode 1: Lire directement depuis JOB_INFO_2 DEVMODE (même sans bits dans dmFields)
            $extendedProps = Get-JobExtendedProperties -PrinterName $printerName -JobId $job.JobId
            if ($extendedProps) {
                [Console]::Error.WriteLine("DEBUG: Propriétés étendues JOB_INFO_2: $($extendedProps | ConvertTo-Json -Compress)")
                if ($extendedProps.Color -and -not $devModeInfo.Color) {
                    $devModeInfo.Color = $extendedProps.Color
                    [Console]::Error.WriteLine("DEBUG: Color obtenu depuis JOB_INFO_2: $($extendedProps.Color)")
                }
                if ($extendedProps.Duplex -and -not $devModeInfo.Duplex) {
                    $devModeInfo.Duplex = $extendedProps.Duplex
                    [Console]::Error.WriteLine("DEBUG: Duplex obtenu depuis JOB_INFO_2: $($extendedProps.Duplex)")
                }
            }

            # Méthode 2: Chercher dans les paramètres personnalisés du job (pParameters)
            $jobParams = Get-JobParameters -PrinterName $printerName -JobId $job.JobId
            if ($jobParams) {
                [Console]::Error.WriteLine("DEBUG: Parametres personnalises du job: $($jobParams | ConvertTo-Json -Compress)")
                if ($jobParams.Color -and -not $devModeInfo.Color) {
                    $devModeInfo.Color = $jobParams.Color
                    [Console]::Error.WriteLine("DEBUG: Color obtenu depuis parametres du job: $($jobParams.Color)")
                }
                if ($jobParams.Duplex -and -not $devModeInfo.Duplex) {
                    $devModeInfo.Duplex = $jobParams.Duplex
                    [Console]::Error.WriteLine("DEBUG: Duplex obtenu depuis parametres du job: $($jobParams.Duplex)")
                }
            }

            # Méthode 3: Chercher dans les événements Windows Print Service
            # Convertir TimeSubmitted en DateTime si c'est une chaîne
            $timeSubmitted = $null
            if ($job.TimeSubmitted) {
                try {
                    if ($job.TimeSubmitted -is [DateTime]) {
                        $timeSubmitted = $job.TimeSubmitted
                    } else {
                        # Essayer de parser le format Windows (YYYYMMDDHHmmss.ffffff+offset)
                        $timeStr = $job.TimeSubmitted.ToString()
                        if ($timeStr -match '^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})') {
                            $year = [int]$matches[1]
                            $month = [int]$matches[2]
                            $day = [int]$matches[3]
                            $hour = [int]$matches[4]
                            $minute = [int]$matches[5]
                            $second = [int]$matches[6]
                            $timeSubmitted = Get-Date -Year $year -Month $month -Day $day -Hour $hour -Minute $minute -Second $second
                        } else {
                            $timeSubmitted = [DateTime]::Parse($timeStr)
                        }
                    }
                } catch {
                    [Console]::Error.WriteLine("DEBUG: Erreur conversion TimeSubmitted: $($_.Exception.Message)")
                    $timeSubmitted = Get-Date  # Utiliser maintenant comme fallback
                }
            } else {
                $timeSubmitted = Get-Date  # Utiliser maintenant comme fallback
            }

            $eventInfo = Get-JobInfoFromPrintServiceEvents -PrinterName $printerName -JobId $job.JobId -DocumentName $job.Document -TimeSubmitted $timeSubmitted
            if ($eventInfo) {
                [Console]::Error.WriteLine("DEBUG: Informations depuis événements Print Service: $($eventInfo | ConvertTo-Json -Compress)")
                if ($eventInfo.Color -and -not $devModeInfo.Color) {
                    $devModeInfo.Color = $eventInfo.Color
                    [Console]::Error.WriteLine("DEBUG: Color obtenu depuis événements Print Service: $($eventInfo.Color)")
                }
                if ($eventInfo.Duplex -and -not $devModeInfo.Duplex) {
                    $devModeInfo.Duplex = $eventInfo.Duplex
                    [Console]::Error.WriteLine("DEBUG: Duplex obtenu depuis événements Print Service: $($eventInfo.Duplex)")
                }
            }

            # Méthode 4: Obtenir les capacités de l'imprimante via DeviceCapabilities
            $printerCaps = Get-PrinterCapabilities -PrinterName $printerName
            if ($printerCaps) {
                [Console]::Error.WriteLine("DEBUG: Capacités imprimante - SupportsColor: $($printerCaps.SupportsColor), SupportsDuplex: $($printerCaps.SupportsDuplex)")
            }

            # Méthode 5: Chercher dans les propriétés WMI du job (PRIORITÉ pour PaperSize car plus fiable)
            $wmiJobProps = Get-JobPropertiesFromWMI -PrinterName $printerName -JobId $job.JobId
            if ($wmiJobProps) {
                [Console]::Error.WriteLine("DEBUG: Propriétés WMI job trouvées: $($wmiJobProps | ConvertTo-Json -Compress)")

                # PRIORITÉ WMI pour PaperSize (PaperLength/PaperWidth plus fiable que DEVMODE)
                if ($wmiJobProps.PaperSize) {
                    $paperSize = $wmiJobProps.PaperSize
                    [Console]::Error.WriteLine("DEBUG: PaperSize mis à jour depuis WMI (PRIORITÉ): $paperSize")
                }
                if ($wmiJobProps.Color) {
                    $colorMode = $wmiJobProps.Color
                    [Console]::Error.WriteLine("DEBUG: ColorMode mis à jour depuis WMI: $colorMode")
                } elseif ($wmiJobProps.ColorHint) {
                    $colorMode = $wmiJobProps.ColorHint
                    [Console]::Error.WriteLine("DEBUG: ColorMode mis à jour depuis WMI hint: $colorMode")
                }

                # Duplex depuis WMI
                if ($wmiJobProps.Duplex -ne $null) {
                    $isDuplex = $wmiJobProps.Duplex
                    if ($devModeInfo) {
                        $devModeInfo.Duplex = if ($isDuplex) { 2 } else { 1 }
                    }
                    [Console]::Error.WriteLine("DEBUG: Duplex mis à jour depuis WMI: $isDuplex")
                } elseif ($wmiJobProps.DuplexHint) {
                    $duplexHint = $wmiJobProps.DuplexHint
                    if ($duplexHint -match 'Duplex|TwoSided|RectoVerso|True|true|1|2|3') {
                        $isDuplex = $true
                        if ($devModeInfo) {
                            $devModeInfo.Duplex = 2
                        }
                        [Console]::Error.WriteLine("DEBUG: Duplex mis à jour depuis WMI hint: $duplexHint -> true")
                    } elseif ($duplexHint -match 'Simplex|OneSided|False|false|0') {
                        $isDuplex = $false
                        if ($devModeInfo) {
                            $devModeInfo.Duplex = 1
                        }
                        [Console]::Error.WriteLine("DEBUG: Duplex mis à jour depuis WMI hint: $duplexHint -> false")
                    }
                }

                if ($wmiJobProps.TotalPages -and $wmiJobProps.TotalPages -gt 0) {
                    $job.TotalPages = $wmiJobProps.TotalPages
                    [Console]::Error.WriteLine("DEBUG: TotalPages mis à jour depuis WMI: $($job.TotalPages)")
                }
                if ($wmiJobProps.PagesPrinted -and $wmiJobProps.PagesPrinted -gt 0) {
                    $job.PagesPrinted = $wmiJobProps.PagesPrinted
                    [Console]::Error.WriteLine("DEBUG: PagesPrinted mis à jour depuis WMI: $($job.PagesPrinted)")
                }
            }

            # Méthode 6: Vérifier le DEVMODE de l'imprimante (référence)
            $printerDevMode = Get-PrinterDevMode -PrinterName $printerName
            if ($printerDevMode) {
                [Console]::Error.WriteLine("DEBUG: DEVMODE imprimante - Color: $($printerDevMode.Color), Duplex: $($printerDevMode.Duplex)")
            }
        }

        if ($devModeInfo) {
            [Console]::Error.WriteLine("DEBUG: Get-JobDetails - DEVMODE trouvé: $($devModeInfo | ConvertTo-Json -Compress)")
        } else {
            [Console]::Error.WriteLine("DEBUG: Get-JobDetails - DEVMODE NULL/Not found")
        }
        if ($devModeInfo) {
            Write-Output "DEBUG: DEVMODE Data - PaperSize: $($devModeInfo.PaperSize) - Duplex: $($devModeInfo.Duplex) - Color: $($devModeInfo.Color) - TotalPages: $($devModeInfo.TotalPages) - PagesPrinted: $($devModeInfo.PagesPrinted)"

            # Utiliser TotalPages depuis DEVMODE si disponible et valide
            # PagesPrinted doit venir uniquement de WMI (plus fiable)
            if ($devModeInfo.TotalPages -and $devModeInfo.TotalPages -gt 0 -and $devModeInfo.TotalPages -lt 1000000) {
                $job.TotalPages = $devModeInfo.TotalPages
                [Console]::Error.WriteLine("DEBUG: TotalPages depuis DEVMODE: $($devModeInfo.TotalPages)")
            }
            # PagesPrinted n'est PAS lu depuis DEVMODE car les valeurs sont aberrantes
            # On utilisera uniquement WMI pour PagesPrinted

            # Mapper le format papier depuis les constantes Windows (DMPAPER_*)
            # Mapping complet des constantes de format papier Windows
            # Note: 111 pourrait être un format personnalisé RISO ou un format non standard
            $paperSizeMap = @{
                1 = "Letter"; 2 = "Letter"; 3 = "A3"; 4 = "A4"; 5 = "Legal"; 6 = "B4"; 7 = "B5";
                8 = "A3"; 9 = "A4"; 10 = "A4"; 11 = "A5"; 12 = "B4"; 13 = "B5";
                64 = "A0"; 65 = "A1"; 66 = "A2";
                # Formats personnalisés possibles (à vérifier selon l'imprimante)
                111 = "A4"  # Format personnalisé RISO - supposé A4 par défaut
            }

            [Console]::Error.WriteLine("DEBUG: PaperSize depuis DEVMODE: $($devModeInfo.PaperSize)")

            # Vérifier dans le mapping SEULEMENT si WMI n'a pas déjà fourni un format
            if ($paperSize -eq "Unknown" -and $devModeInfo.PaperSize -and $paperSizeMap.ContainsKey([int]$devModeInfo.PaperSize)) {
                $paperSize = $paperSizeMap[[int]$devModeInfo.PaperSize]
                [Console]::Error.WriteLine("DEBUG: PaperSize mappé depuis DEVMODE à: $paperSize")
            } elseif ($paperSize -eq "Unknown") {
                [Console]::Error.WriteLine("DEBUG: PaperSize $($devModeInfo.PaperSize) non trouvé dans le mapping")
                # Essayer avec un switch pour gérer les cas non mappés SEULEMENT si WMI n'a pas fourni de format
                switch ([int]$devModeInfo.PaperSize) {
                    {$_ -ge 1 -and $_ -le 13} {
                        # Formats standards
                        if ($_ -eq 1 -or $_ -eq 2) { $paperSize = "Letter" }
                        elseif ($_ -eq 3 -or $_ -eq 8) { $paperSize = "A3" }
                        elseif ($_ -eq 4 -or $_ -eq 9 -or $_ -eq 10) { $paperSize = "A4" }
                        elseif ($_ -eq 5) { $paperSize = "Legal" }
                        elseif ($_ -eq 11) { $paperSize = "A5" }
                        elseif ($_ -eq 12 -or $_ -eq 6) { $paperSize = "B4" }
                        elseif ($_ -eq 13 -or $_ -eq 7) { $paperSize = "B5" }
                        else {
                            $paperSize = "Unknown"
                            [Console]::Error.WriteLine("DEBUG: PaperSize $($devModeInfo.PaperSize) non reconnu dans switch")
                        }
                    }
                    default {
                        $paperSize = "Unknown"
                        [Console]::Error.WriteLine("DEBUG: PaperSize $($devModeInfo.PaperSize) non reconnu (default)")
                    }
                }
            }

            # Mapper le duplex SEULEMENT si WMI n'a pas déjà fourni une valeur
            # (WMI est plus fiable car il peut avoir des propriétés spécifiques)
            [Console]::Error.WriteLine("DEBUG: Duplex depuis DEVMODE: $($devModeInfo.Duplex)")
            if ($devModeInfo.Duplex -ne $null -and -not ($wmiJobProps -and $wmiJobProps.Duplex -ne $null)) {
                switch ($devModeInfo.Duplex) {
                    {$_ -eq 2 -or $_ -eq 3} {
                        $isDuplex = $true
                        [Console]::Error.WriteLine("DEBUG: Duplex mappé depuis DEVMODE à: true (Vertical/Horizontal)")
                    }
                    1 {
                        $isDuplex = $false
                        [Console]::Error.WriteLine("DEBUG: Duplex mappé depuis DEVMODE à: false (Simplex)")
                    }
                    default {
                        $isDuplex = $false
                        [Console]::Error.WriteLine("DEBUG: Duplex valeur inconnue: $($devModeInfo.Duplex), défaut: false")
                    }
                }
            } else {
                [Console]::Error.WriteLine("DEBUG: Duplex non défini dans DEVMODE")
                # Si Duplex n'est pas dans DEVMODE, on garde Unknown (pas de fallback)
                # Mais on peut utiliser les capacités de l'imprimante comme indication
                if ($printerCaps -and $printerCaps.SupportsDuplex) {
                    [Console]::Error.WriteLine("DEBUG: Imprimante supporte duplex, mais valeur non disponible dans DEVMODE du job")
                }
            }

            # Mapper le mode couleur
            [Console]::Error.WriteLine("DEBUG: Color depuis DEVMODE: $($devModeInfo.Color)")
            if ($devModeInfo.Color -ne $null) {
                switch ($devModeInfo.Color) {
                    2 {
                        $colorMode = "Color"
                        [Console]::Error.WriteLine("DEBUG: Color mappé à: Color")
                    }
                    1 {
                        $colorMode = "Monochrome"
                        [Console]::Error.WriteLine("DEBUG: Color mappé à: Monochrome")
                    }
                    default {
                        $colorMode = "Unknown"
                        [Console]::Error.WriteLine("DEBUG: Color valeur inconnue: $($devModeInfo.Color)")
                    }
                }
            } else {
                [Console]::Error.WriteLine("DEBUG: Color non défini dans DEVMODE")
                # Si Color n'est pas dans DEVMODE, on garde Unknown (pas de fallback)
                # Mais on peut utiliser les capacités de l'imprimante comme indication
                if ($printerCaps -and $printerCaps.SupportsColor) {
                    [Console]::Error.WriteLine("DEBUG: Imprimante supporte couleur, mais valeur non disponible dans DEVMODE du job")
                    # Note: On ne fait pas de fallback, on garde Unknown comme demandé par l'utilisateur
                }
            }
        } else {
            # Si la lecture du DEVMODE échoue, on garde les valeurs par défaut (Unknown/false)
            # PAS DE FALLBACK - Les valeurs restent Unknown si le DEVMODE n'est pas accessible
        }

        [Console]::Error.WriteLine("DEBUG: ===== Get-JobDetails FIN - Retour: PaperSize=$paperSize, IsDuplex=$isDuplex, ColorMode=$colorMode =====")
        return @{
            PaperSize = $paperSize
            IsDuplex = $isDuplex
            ColorMode = $colorMode
        }
    } catch {
        [Console]::Error.WriteLine("DEBUG: ===== Get-JobDetails EXCEPTION: $($_.Exception.Message) =====")
        return @{
            PaperSize = "Unknown"
            IsDuplex = $false
            ColorMode = "Unknown"
        }
    }
}

# Surveiller les nouveaux jobs
$newJobQuery = "SELECT * FROM __InstanceCreationEvent WITHIN 2 WHERE TargetInstance ISA 'Win32_PrintJob'"
$newJobWatcher = Register-WmiEvent -Query $newJobQuery -Action {
    try {
        $eventTime = Get-Date -Format "o"
        $job = $Event.SourceEventArgs.NewEvent.TargetInstance
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }

        # Log détaillé avec toutes les propriétés WMI brutes
        $wmiRawData = @{
            JobId = $job.JobId
            Name = $job.Name
            Document = $job.Document
            Owner = $job.Owner
            Status = $job.Status
            StatusMask = $job.StatusMask
            PagesPrinted = $job.PagesPrinted
            TotalPages = $job.TotalPages
            TimeSubmitted = $job.TimeSubmitted
            Size = $job.Size
            DataType = $job.DataType
            DriverName = $job.DriverName
            Location = $job.Location
            Notify = $job.Notify
            Priority = $job.Priority
            StartTime = $job.StartTime
            UntilTime = $job.UntilTime
            ElapsedTime = $job.ElapsedTime
        }

        Write-Output "DEBUG: NEW_PRINT_JOB Event - Time: $eventTime - JobId: $($job.JobId) - Document: $($job.Document) - Status: $($job.Status)"
        Write-Output "DEBUG: Avant appel Get-JobDetails pour JobId: $($job.JobId)"
        $details = Get-JobDetails -job $job
        Write-Output "DEBUG: Après appel Get-JobDetails - PaperSize: $($details.PaperSize), ColorMode: $($details.ColorMode), IsDuplex: $($details.IsDuplex)"

        $jobInfo = @{
            JobId = $job.JobId
            Document = $job.Document
            Owner = $job.Owner
            PrinterName = $printerName
            Status = $job.Status
            PagesPrinted = if ($job.PagesPrinted) { [int]$job.PagesPrinted } else { 0 }
            TotalPages = if ($job.TotalPages) { [int]$job.TotalPages } else { 0 }
            TimeSubmitted = $job.TimeSubmitted
            Size = if ($job.Size) { [int]$job.Size } else { 0 }
            PaperSize = $details.PaperSize
            IsDuplex = [bool]$details.IsDuplex
            ColorMode = $details.ColorMode
            EventTime = $eventTime
            WMI_RawData = $wmiRawData
        }
        $json = $jobInfo | ConvertTo-Json -Compress -Depth 10
        Write-Output "NEW_PRINT_JOB:$json"
    } catch {
        $errorMsg = $_.Exception.Message
        Write-Output "ERROR: NEW_PRINT_JOB failed - $errorMsg"
    }
} -ErrorAction SilentlyContinue

# Surveiller les modifications de jobs (changement de statut)
$modifyJobQuery = "SELECT * FROM __InstanceModificationEvent WITHIN 2 WHERE TargetInstance ISA 'Win32_PrintJob'"
$modifyJobWatcher = Register-WmiEvent -Query $modifyJobQuery -Action {
    try {
        $eventTime = Get-Date -Format "o"
        $job = $Event.SourceEventArgs.NewEvent.TargetInstance
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }

        # Log détaillé avec toutes les propriétés WMI brutes
        $wmiRawData = @{
            JobId = $job.JobId
            Name = $job.Name
            Document = $job.Document
            Owner = $job.Owner
            Status = $job.Status
            StatusMask = $job.StatusMask
            PagesPrinted = $job.PagesPrinted
            TotalPages = $job.TotalPages
            TimeSubmitted = $job.TimeSubmitted
            Size = $job.Size
            DataType = $job.DataType
            DriverName = $job.DriverName
        }

        Write-Output "DEBUG: MODIFY_PRINT_JOB Event - Time: $eventTime - JobId: $($job.JobId) - Document: $($job.Document) - Status: $($job.Status) - Pages: $($job.PagesPrinted)/$($job.TotalPages)"

        $details = Get-JobDetails -job $job

        $jobInfo = @{
            JobId = $job.JobId
            Document = $job.Document
            Owner = $job.Owner
            PrinterName = $printerName
            Status = $job.Status
            PagesPrinted = if ($job.PagesPrinted) { [int]$job.PagesPrinted } else { 0 }
            TotalPages = if ($job.TotalPages) { [int]$job.TotalPages } else { 0 }
            TimeSubmitted = $job.TimeSubmitted
            Size = if ($job.Size) { [int]$job.Size } else { 0 }
            PaperSize = $details.PaperSize
            IsDuplex = [bool]$details.IsDuplex
            ColorMode = $details.ColorMode
            EventTime = $eventTime
            WMI_RawData = $wmiRawData
        }
        $json = $jobInfo | ConvertTo-Json -Compress -Depth 10
        Write-Output "MODIFY_PRINT_JOB:$json"
    } catch {
        $errorMsg = $_.Exception.Message
        Write-Output "ERROR: MODIFY_PRINT_JOB failed - $errorMsg"
    }
} -ErrorAction SilentlyContinue

# Surveiller les jobs terminés
$completedQuery = "SELECT * FROM __InstanceDeletionEvent WITHIN 2 WHERE TargetInstance ISA 'Win32_PrintJob'"
$completedWatcher = Register-WmiEvent -Query $completedQuery -Action {
    try {
        $job = $Event.SourceEventArgs.NewEvent.TargetInstance
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }

        $details = Get-JobDetails -job $job

        $jobInfo = @{
            JobId = $job.JobId
            Document = $job.Document
            Owner = $job.Owner
            PrinterName = $printerName
            Status = "Completed"
            PagesPrinted = if ($job.PagesPrinted) { [int]$job.PagesPrinted } else { 0 }
            TotalPages = if ($job.TotalPages) { [int]$job.TotalPages } else { 0 }
            TimeSubmitted = $job.TimeSubmitted
            Size = if ($job.Size) { [int]$job.Size } else { 0 }
            PaperSize = $details.PaperSize
            IsDuplex = [bool]$details.IsDuplex
            ColorMode = $details.ColorMode
        }
        $json = $jobInfo | ConvertTo-Json -Compress
        Write-Output "COMPLETED_PRINT_JOB:$json"
    } catch {
        # Ignorer les erreurs silencieusement
    }
} -ErrorAction SilentlyContinue

# Garder le script actif et vérifier périodiquement les jobs actifs
$processedJobs = @{}
$iteration = 0
try {
    while ($true) {
        Start-Sleep -Seconds 3
        $iteration++
        # Log toutes les 10 itérations pour debug (toutes les 30 secondes)
        if ($iteration % 10 -eq 0) {
            Write-Output "DEBUG: Iteration $iteration - Script actif"
        }

        # Vérifier périodiquement les jobs actifs
        $activeJobs = Get-WmiObject Win32_PrintJob -ErrorAction SilentlyContinue
        if ($activeJobs) {
            if ($iteration % 10 -eq 0) {
                Write-Output "DEBUG: $($activeJobs.Count) job(s) trouve(s)"
            }
            foreach ($job in $activeJobs) {
                $jobKey = "$($job.Name)_$($job.JobId)"
                $status = $job.Status

                # Traiter TOUS les jobs trouvés (pas de filtre sur le statut pour ne rien manquer)
                # Ne traiter que si on ne l'a pas déjà traité récemment (éviter les doublons)
                if (-not $processedJobs.ContainsKey($jobKey) -or $processedJobs[$jobKey] -ne $status) {
                    $printerName = $job.Name
                    if ($printerName -match ',') {
                        $printerName = $printerName.Split(',')[0].Trim()
                    }
                    $eventTime = Get-Date -Format "o"
                    Write-Output "DEBUG: ACTIVE_JOB - Avant Get-JobDetails pour JobId: $($job.JobId), Printer: $printerName"
                    $details = Get-JobDetails -job $job
                    Write-Output "DEBUG: ACTIVE_JOB - Après Get-JobDetails - PaperSize: $($details.PaperSize), ColorMode: $($details.ColorMode)"

                    # Log détaillé avec toutes les propriétés WMI brutes
                    $wmiRawData = @{
                        JobId = $job.JobId
                        Name = $job.Name
                        Document = $job.Document
                        Owner = $job.Owner
                        Status = $status
                        StatusMask = $job.StatusMask
                        PagesPrinted = $job.PagesPrinted
                        TotalPages = $job.TotalPages
                        TimeSubmitted = $job.TimeSubmitted
                        Size = $job.Size
                        DataType = $job.DataType
                        DriverName = $job.DriverName
                    }

                    $jobInfo = @{
                        JobId = $job.JobId
                        Document = $job.Document
                        Owner = $job.Owner
                        PrinterName = $printerName
                        Status = $status
                        PagesPrinted = $job.PagesPrinted
                        TotalPages = $job.TotalPages
                        TimeSubmitted = $job.TimeSubmitted
                        Size = $job.Size
                        PaperSize = $details.PaperSize
                        IsDuplex = $details.IsDuplex
                        ColorMode = $details.ColorMode
                        EventTime = $eventTime
                        WMI_RawData = $wmiRawData
                    }
                    $json = $jobInfo | ConvertTo-Json -Compress -Depth 10
                    Write-Output "ACTIVE_JOB:$json"
                    $processedJobs[$jobKey] = $status
                    Write-Output "DEBUG: ACTIVE_JOB - Time: $eventTime - JobId: $($job.JobId) - Document: $($job.Document) - Status: $status - Pages: $($job.PagesPrinted)/$($job.TotalPages) - PaperSize: $($details.PaperSize) - Duplex: $($details.IsDuplex) - Color: $($details.ColorMode)"
                }
            }
        }

        # Nettoyer le cache des jobs traités (garder seulement les 100 derniers)
        if ($processedJobs.Count -gt 100) {
            $keysToRemove = $processedJobs.Keys | Select-Object -First ($processedJobs.Count - 100)
            foreach ($key in $keysToRemove) {
                $processedJobs.Remove($key)
            }
        }
    }
} catch {
    Write-Error $_.Exception.Message
}
`;

        // Exécuter PowerShell avec le script
        // Utiliser un fichier temporaire pour éviter les problèmes de parsing avec les scripts multilignes
        const tempScriptPath = path.join(os.tmpdir(), `printer-monitor-${Date.now()}.ps1`);

        // Écrire le script dans un fichier temporaire
        try {
            fs.writeFileSync(tempScriptPath, psScript, 'utf8');
        } catch (error) {
            console.error('❌ Erreur lors de l\'écriture du script temporaire:', error);
            if (this.callbacks.onError) {
                this.callbacks.onError('Impossible de créer le script temporaire: ' + error.message);
            }
            return;
        }

        this.powerShellProcess = spawn('powershell.exe', [
            '-NoProfile',
            '-ExecutionPolicy', 'Bypass',
            '-File', tempScriptPath
        ], {
            stdio: ['pipe', 'pipe', 'pipe'],
            shell: false
        });

        // Stocker le chemin du fichier temporaire pour le nettoyer plus tard
        this.tempScriptPath = tempScriptPath;

        this.powerShellProcess.stdout.on('data', (data) => {
            const output = data.toString();
            // Log toutes les sorties pour debug
            if (output.trim()) {
                if (output.includes('DEBUG:')) {
                    console.log('🔍 [DEBUG]', output.trim());
                } else if (output.includes('ERROR:')) {
                    console.error('❌ [ERROR]', output.trim());
                } else if (output.includes('ACTIVE_JOB') || output.includes('NEW_PRINT_JOB') || output.includes('MODIFY_PRINT_JOB') || output.includes('COMPLETED_PRINT_JOB')) {
                    console.log('📥 [EVENT]', output.substring(0, 500));
                }
            }
            this.handlePowerShellOutput(output);
        });

        this.powerShellProcess.stderr.on('data', (data) => {
            const error = data.toString();
            // Capturer aussi les logs DEBUG depuis stderr
            if (error.includes('DEBUG:')) {
                console.log('🔍 [DEBUG stderr]', error.trim());
                // Enregistrer aussi dans un fichier pour analyse
                try {
                    const fs = require('fs');
                    const path = require('path');
                    const os = require('os');
                    const logDir = path.join(os.homedir(), 'AppData', 'Roaming', 'dupli-electron');
                    if (!fs.existsSync(logDir)) {
                        fs.mkdirSync(logDir, { recursive: true });
                    }
                    const logFile = path.join(logDir, 'printer-monitor-debug.log');
                    const timestamp = new Date().toISOString();
                    fs.appendFileSync(logFile, `[${timestamp}] ${error.trim()}\n`, 'utf8');
                } catch (e) {
                    // Ignorer les erreurs d'écriture de log
                }
            }
            // Ne pas considérer les warnings comme des erreurs critiques
            if (!error.includes('Warning') && !error.includes('Information') && !error.includes('DEBUG:')) {
                console.error('Erreur PowerShell:', error);
                if (this.callbacks.onError) {
                    this.callbacks.onError(error);
                }
            } else if (!error.includes('DEBUG:')) {
                console.log('PowerShell info:', error);
            }
        });

        this.powerShellProcess.on('close', (code) => {
            console.log(`⚠️ Processus PowerShell fermé avec le code ${code}`);

            // Nettoyer le fichier temporaire
            if (this.tempScriptPath && fs.existsSync(this.tempScriptPath)) {
                try {
                    fs.unlinkSync(this.tempScriptPath);
                    this.tempScriptPath = null;
                } catch (error) {
                    // Ignorer les erreurs de suppression
                }
            }

            if (this.monitoring && code !== 0) {
                // Redémarrer après un délai si la surveillance est toujours active
                console.log('🔄 Redémarrage de la surveillance dans 5 secondes...');
                setTimeout(() => {
                    if (this.monitoring) {
                        console.log('🔄 Redémarrage de la surveillance...');
                        this.startPowerShellMonitor();
                    }
                }, 5000);
            }
        });

        this.powerShellProcess.on('error', (error) => {
            console.error('❌ Erreur lors du démarrage de PowerShell:', error);
>>>>>>> origin/feature/impression-complete
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
<<<<<<< HEAD
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
        const colorModeStr = jobData.color === 2 ? "Color" : "Monochrome";

        const jobInfo = {
            JobId: jobData.jobId,
            PrinterName: jobData.printerName,
            Document: jobData.documentName,
            Status: jobData.status,
            TotalPages: jobData.totalPages,
            PaperSize: paperSizeStr,
            IsDuplex: duplexBool,
            ColorMode: colorModeStr,
            TimeSubmitted: new Date().toISOString()
        };
=======
    async processPrintJob(eventType, jobInfo) {
        // Normaliser le nom de l'imprimante
        let printerName = jobInfo.PrinterName || jobInfo.Name || 'Unknown';
        if (printerName.includes(',')) {
            printerName = printerName.split(',')[0].trim();
        }

        const jobKey = `${printerName}_${jobInfo.JobId || jobInfo.JobID || ''}`;

        // Pour ACTIVE_JOB : juste mettre en cache, ne pas envoyer à la base de données
        // On attendra COMPLETED_PRINT_JOB ou MODIFY_PRINT_JOB pour avoir les informations complètes
        // Fallback: si après 10 secondes on n'a pas reçu COMPLETED/MODIFY, traiter quand même
        if (eventType === 'ACTIVE_JOB') {
            console.log(`📋 [CACHE] Job ${jobInfo.JobId || jobInfo.JobID} mis en cache (ACTIVE_JOB), attente de COMPLETED ou MODIFY...`);
            this.jobCache.set(jobKey, {
                ...jobInfo,
                eventType: eventType.replace('_', ' '),
                timestamp: new Date().toISOString()
            });

            // Fallback: si après 10 secondes on n'a pas reçu COMPLETED/MODIFY, récupérer les infos depuis WMI et traiter
            const jobId = jobInfo.JobId || jobInfo.JobID;
            const self = this; // Capturer this pour le setTimeout
            setTimeout(async () => {
                console.log(`⏰ [FALLBACK] Vérification fallback pour JobId ${jobId}, jobKey: ${jobKey}`);

                // Vérifier si le job est toujours dans le cache (pas encore traité)
                if (self.jobCache.has(jobKey)) {
                    const cachedJob = self.jobCache.get(jobKey);
                    console.log(`⏰ [FALLBACK] Job ${jobId} toujours en cache après 10s, récupération des infos depuis WMI...`);

                    // Récupérer les informations mises à jour depuis WMI
                    try {
                        const updatedInfo = await self.getJobInfoFromWMI(jobId, printerName);
                        if (updatedInfo) {
                            console.log(`⏰ [FALLBACK] Informations mises à jour depuis WMI:`, JSON.stringify(updatedInfo, null, 2));
                            // Fusionner avec le cache (ignorer les valeurs 0)
                            const mergedInfo = {
                                ...cachedJob,
                                ...updatedInfo,
                                PagesPrinted: (updatedInfo.PagesPrinted !== undefined && updatedInfo.PagesPrinted > 0) ? updatedInfo.PagesPrinted : (cachedJob.PagesPrinted || 0),
                                TotalPages: (updatedInfo.TotalPages !== undefined && updatedInfo.TotalPages > 0) ? updatedInfo.TotalPages : (cachedJob.TotalPages || 0)
                            };
                            // Traiter le job avec les informations mises à jour
                            await self.processPrintJob('MODIFY_PRINT_JOB', mergedInfo);
                        } else {
                            console.log(`⏰ [FALLBACK] Job ${jobId} non trouvé dans WMI, traitement avec informations du cache...`);
                            await self.processPrintJob('MODIFY_PRINT_JOB', cachedJob);
                        }
                    } catch (error) {
                        console.error(`❌ [FALLBACK] Erreur récupération WMI:`, error.message);
                        // Traiter quand même avec le cache
                        await self.processPrintJob('MODIFY_PRINT_JOB', cachedJob);
                    }
                } else {
                    console.log(`⏰ [FALLBACK] Job ${jobId} n'est plus dans le cache (déjà traité par COMPLETED/MODIFY)`);
                }
            }, 10000); // 10 secondes

            // Nettoyer le cache après 5 minutes
            setTimeout(() => {
                this.jobCache.delete(jobKey);
            }, 5 * 60 * 1000);

            // Ne pas traiter plus loin pour ACTIVE_JOB
            return;
        }

        // Pour COMPLETED_PRINT_JOB ou MODIFY_PRINT_JOB : traiter et envoyer à la base de données
        // Récupérer les informations du cache si disponible
        let cachedJobInfo = null;
        if (this.jobCache.has(jobKey)) {
            cachedJobInfo = this.jobCache.get(jobKey);
            console.log(`✅ [CACHE] Informations récupérées depuis le cache pour JobId ${jobInfo.JobId || jobInfo.JobID}`);
        }

        // Fusionner les informations du cache avec les nouvelles informations
        const mergedJobInfo = {
            ...cachedJobInfo,
            ...jobInfo,
            // Les nouvelles informations (COMPLETED/MODIFY) ont priorité, mais ignorer les valeurs 0
            Status: jobInfo.Status || cachedJobInfo?.Status,
            PagesPrinted: (jobInfo.PagesPrinted !== undefined && jobInfo.PagesPrinted > 0) ? jobInfo.PagesPrinted : (cachedJobInfo?.PagesPrinted || 0),
            TotalPages: (jobInfo.TotalPages !== undefined && jobInfo.TotalPages > 0) ? jobInfo.TotalPages : (cachedJobInfo?.TotalPages || 0)
        };

        // Mettre à jour le cache
        this.jobCache.set(jobKey, {
            ...mergedJobInfo,
            eventType: eventType.replace('_', ' '),
            timestamp: new Date().toISOString()
        });

        // Utiliser mergedJobInfo pour le traitement
        jobInfo = mergedJobInfo;

        // Récupérer les options depuis le cache (PRIORITÉ ABSOLUE - données fiables)
        const documentName = String(jobInfo.Document || '');
        const cachedOptions = this.getPrintOptions(documentName);

        // Initialiser les valeurs depuis le cache si disponible
        let paperSize = 'Unknown';
        let isDuplex = false;
        let colorMode = 'Unknown';
        let copies = 1;
        let orientation = null;
        let resolution = null;
        let inputSlot = null;

        if (cachedOptions && cachedOptions.options) {
            const opts = cachedOptions.options;
            console.log('✅ [PRINT_CACHE] Options récupérées depuis le cache pour:', documentName);
            console.log('   Options:', JSON.stringify(opts, null, 2));

            // Format papier
            if (opts.pageSize) {
                paperSize = String(opts.pageSize);
                // Normaliser les noms de formats
                if (paperSize === 'A4' || paperSize === 'iso-a4') paperSize = 'A4';
                else if (paperSize === 'A3' || paperSize === 'iso-a3') paperSize = 'A3';
                else if (paperSize === 'Letter' || paperSize === 'na-letter') paperSize = 'Letter';
                else if (paperSize === 'Legal' || paperSize === 'na-legal') paperSize = 'Legal';
            }

            // Duplex
            if (opts.duplex) {
                isDuplex = opts.duplex !== 'Simplex' && opts.duplex !== 'simplex';
            }

            // Couleur
            if (opts.colorMode) {
                colorMode = String(opts.colorMode);
                if (colorMode === 'Monochrome' || colorMode === 'monochrome') {
                    colorMode = 'Monochrome';
                } else if (colorMode === 'Color' || colorMode === 'color') {
                    colorMode = 'Color';
                }
            }

            // Autres options
            copies = opts.copies || 1;
            resolution = opts.resolution || null;
            inputSlot = opts.inputSlot || null;
        }

        // Utiliser les données PowerShell SEULEMENT si elles ne sont pas "Unknown"
        // PAS DE FALLBACK - Si le DEVMODE n'est pas accessible, on garde Unknown/null
        if (paperSize === 'Unknown' && jobInfo.PaperSize && jobInfo.PaperSize !== 'Unknown') {
            const psPaperSize = String(jobInfo.PaperSize).trim();
            // Mapper les valeurs numériques aux formats (uniquement si c'est un nombre)
            if (psPaperSize.match(/^\d+$/)) {
                const paperSizeMap = {
                    '1': 'Letter', '2': 'Letter', '3': 'A3', '4': 'A4', '5': 'Legal',
                    '8': 'A3', '9': 'A4', '10': 'A4', '11': 'A5', '12': 'B4', '13': 'B5',
                    '64': 'A0', '65': 'A1', '66': 'A2'
                };
                if (paperSizeMap[psPaperSize]) {
                    paperSize = paperSizeMap[psPaperSize];
                }
            } else if (psPaperSize.match(/^[A-Z][0-9]+$/i)) {
                // Format déjà correct (A4, A3, etc.)
                paperSize = psPaperSize.toUpperCase();
            }
        }

        // Duplex depuis PowerShell SEULEMENT si la valeur est définie et n'est pas "Unknown"
        if (isDuplex === false && jobInfo.IsDuplex !== undefined && jobInfo.IsDuplex !== null && jobInfo.IsDuplex !== 'Unknown') {
            const psDuplex = jobInfo.IsDuplex;
            if (psDuplex === true || psDuplex === 'True' || psDuplex === 1 || psDuplex === '1' || psDuplex === 2 || psDuplex === 3) {
                isDuplex = true;
            } else if (psDuplex === false || psDuplex === 'False' || psDuplex === 0 || psDuplex === '0') {
                isDuplex = false;
            }
        }

        // Couleur depuis PowerShell SEULEMENT si la valeur n'est pas "Unknown"
        if (colorMode === 'Unknown' && jobInfo.ColorMode && jobInfo.ColorMode !== 'Unknown') {
            const psColor = String(jobInfo.ColorMode).trim();
            if (psColor === '2' || psColor === 'Color' || psColor.toLowerCase() === 'color') {
                colorMode = 'Color';
            } else if (psColor === '1' || psColor === 'Monochrome' || psColor.toLowerCase() === 'monochrome') {
                colorMode = 'Monochrome';
            } else if (psColor.match(/^[A-Za-z]+$/)) {
                // Format texte valide (Color, Monochrome, etc.)
                colorMode = psColor;
            }
        }

        // Extraire les pages depuis WMI (ces valeurs sont disponibles directement)
        // WMI fournit PagesPrinted (nombre de feuilles) et TotalPages (nombre de pages total, peut inclure copies)
        let pagesPrinted = 0;
        let totalPages = 0;

        // Extraire les pages depuis WMI UNIQUEMENT (plus fiable que JOB_INFO_2)
        // Filtrer les valeurs aberrantes (> 1 million)
        if (jobInfo.PagesPrinted !== undefined && jobInfo.PagesPrinted !== null) {
            const wmiPagesPrinted = parseInt(jobInfo.PagesPrinted) || 0;
            if (wmiPagesPrinted >= 0 && wmiPagesPrinted < 1000000) {
                pagesPrinted = wmiPagesPrinted;
                console.log(`📊 [PAGES] PagesPrinted extrait depuis jobInfo.PagesPrinted: ${pagesPrinted}`);
            }
        }
        if (jobInfo.TotalPages !== undefined && jobInfo.TotalPages !== null) {
            const wmiTotalPages = parseInt(jobInfo.TotalPages) || 0;
            if (wmiTotalPages >= 0 && wmiTotalPages < 1000000) {
                totalPages = wmiTotalPages;
                console.log(`📊 [PAGES] TotalPages extrait depuis jobInfo.TotalPages: ${totalPages}`);
            }
        }

        // Essayer aussi depuis WMI_RawData si disponible (avec filtre)
        // WMI_RawData est généralement plus fiable car il vient directement de WMI après le spooling
        if (jobInfo.WMI_RawData) {
            if (jobInfo.WMI_RawData.TotalPages) {
                const wmiTotalPages = parseInt(jobInfo.WMI_RawData.TotalPages) || 0;
                if (wmiTotalPages >= 0 && wmiTotalPages < 1000000) {
                    // Utiliser WMI_RawData seulement si totalPages n'est pas déjà défini ou si WMI_RawData est meilleur (plus grand)
                    if (totalPages === 0 || wmiTotalPages > totalPages) {
                        totalPages = wmiTotalPages;
                        console.log(`📊 [PAGES] TotalPages extrait depuis WMI_RawData: ${totalPages}`);
                    }
                }
            }
            if (jobInfo.WMI_RawData.PagesPrinted) {
                const wmiPagesPrinted = parseInt(jobInfo.WMI_RawData.PagesPrinted) || 0;
                if (wmiPagesPrinted >= 0 && wmiPagesPrinted < 1000000) {
                    // Utiliser WMI_RawData seulement si pagesPrinted n'est pas déjà défini ou si WMI_RawData est meilleur (plus grand)
                    if (pagesPrinted === 0 || wmiPagesPrinted > pagesPrinted) {
                        pagesPrinted = wmiPagesPrinted;
                        console.log(`📊 [PAGES] PagesPrinted extrait depuis WMI_RawData: ${pagesPrinted}`);
                    }
                }
            }
        }

        // Si TotalPages vient du DEVMODE (dans jobInfo depuis PowerShell), l'utiliser seulement si meilleur que WMI_RawData
        // WMI_RawData est généralement plus fiable car il vient directement de WMI après le spooling
        if (jobInfo.TotalPages && parseInt(jobInfo.TotalPages) > 0 && parseInt(jobInfo.TotalPages) < 1000000) {
            const devModeTotalPages = parseInt(jobInfo.TotalPages);
            // Utiliser DEVMODE seulement si totalPages n'est pas déjà défini ou si DEVMODE est meilleur (plus grand)
            if (totalPages === 0 || devModeTotalPages > totalPages) {
                totalPages = devModeTotalPages;
                console.log(`📊 [PAGES] TotalPages mis à jour depuis DEVMODE: ${totalPages}`);
            } else {
                console.log(`📊 [PAGES] TotalPages depuis DEVMODE (${devModeTotalPages}) ignoré, WMI_RawData (${totalPages}) est meilleur`);
            }
        }

        // Vérifier si totalPages semble suspect (trop petit)
        // WMI peut parfois retourner 2 au lieu du vrai nombre de pages (ex: 56)
        if (totalPages > 0 && totalPages < 4) {
            console.log(`⚠️  [PAGES] totalPages semble suspect (${totalPages}), peut être incorrect. Ne pas utiliser pour détecter duplex.`);
        }

        // PAS DE LECTURE PDF - On utilise uniquement le nombre de feuilles depuis WMI/DEVMODE
        // totalPages = nombre de pages du document (peut inclure les copies multiples)
        // pagesPrinted = nombre de feuilles physiques imprimées

        // Si pagesPrinted est 0 mais que totalPages est disponible, calculer le nombre de feuilles
        // (pour les imprimantes virtuelles où PagesPrinted peut rester à 0)
        if (pagesPrinted === 0 && totalPages > 0) {
            // Si on a les paramètres d'impression dans le cache, les utiliser
            if (cachedOptions && cachedOptions.options) {
                const opts = cachedOptions.options;
                if (opts.duplex && opts.duplex !== 'Simplex' && opts.duplex !== 'simplex') {
                    // Duplex : 2 pages par feuille
                    pagesPrinted = Math.ceil(totalPages / 2);
                    console.log(`📊 [PAGES] PagesPrinted calculé depuis cache (duplex): ${totalPages} pages / 2 = ${pagesPrinted} feuilles`);
                } else {
                    // Simplex : 1 page par feuille
                    pagesPrinted = totalPages;
                    console.log(`📊 [PAGES] PagesPrinted calculé depuis cache (simplex): ${totalPages} pages = ${pagesPrinted} feuilles`);
                }
            } else {
                // Pas de paramètres dans le cache, utiliser une heuristique basée sur totalPages
                // MAIS: Ne pas utiliser si totalPages est suspect (< 4) car c'est probablement incorrect
                if (totalPages >= 4) {
                    // Si totalPages est pair, supposer duplex (2 pages par feuille)
                    // Sinon, supposer simplex (1 page par feuille)
                    if (totalPages % 2 === 0) {
                        pagesPrinted = totalPages / 2;
                        console.log(`📊 [PAGES] PagesPrinted calculé (heuristic duplex): ${totalPages} pages / 2 = ${pagesPrinted} feuilles`);
                    } else {
                        pagesPrinted = totalPages;
                        console.log(`📊 [PAGES] PagesPrinted calculé (heuristic simplex): ${totalPages} pages = ${pagesPrinted} feuilles`);
                    }
                } else {
                    // totalPages est suspect (< 4), ne pas calculer pagesPrinted
                    console.log(`⚠️  [PAGES] totalPages suspect (${totalPages}), ne pas calculer pagesPrinted depuis heuristique`);
                }
            }
        }

        // PAS D'ESTIMATION - On garde 0 si les pages ne sont pas disponibles depuis WMI ou DEVMODE

        // PAS DE FALLBACK - Si le DEVMODE n'est pas accessible, on garde les valeurs Unknown/null

        // Calculer le duplex en comparant le nombre de pages total avec le nombre de feuilles imprimées
        // totalPages peut inclure les copies multiples (ex: 2 copies de 4 pages = 8)
        // pagesPrinted = nombre de feuilles physiques imprimées
        // Si pagesPrinted est 0, on peut quand même utiliser totalPages pour détecter le duplex
        // si le document a un nombre pair de pages (probablement duplex)
        if (!isDuplex && totalPages > 0) {
            // Si totalPages est pair et >= 2, c'est probablement duplex (2 pages par feuille)
            // Sauf si pagesPrinted = totalPages (simplex)
            // MAIS: Ne pas détecter duplex si totalPages est trop petit (< 4) car c'est probablement incorrect
            // (WMI peut retourner 2 au lieu du vrai nombre de pages)
            if (totalPages % 2 === 0 && totalPages >= 4) {
                if (pagesPrinted === 0 || pagesPrinted === totalPages / 2) {
                    // Si pagesPrinted est 0, on suppose duplex pour les documents pairs (seulement si totalPages >= 4)
                    // Si pagesPrinted = totalPages / 2, c'est confirmé duplex
                    isDuplex = true;
                    console.log(`✅ [DUPLEX_CALC] Duplex détecté: ${totalPages} pages (pair) = probablement ${totalPages / 2} feuilles duplex`);
                } else if (pagesPrinted === totalPages) {
                    // Si pagesPrinted = totalPages, c'est simplex
                    isDuplex = false;
                    console.log(`✅ [DUPLEX_CALC] Simplex détecté: ${totalPages} pages = ${pagesPrinted} feuilles (simplex)`);
                }
            } else if (totalPages < 4 && pagesPrinted === totalPages) {
                // Si totalPages est petit (< 4) et pagesPrinted = totalPages, c'est simplex
                // (Ne pas détecter duplex pour les petits nombres qui sont probablement incorrects)
                isDuplex = false;
                console.log(`✅ [DUPLEX_CALC] Simplex détecté (totalPages petit): ${totalPages} pages = ${pagesPrinted} feuilles (simplex)`);
            }
        }

        // Calculer le duplex avec pagesPrinted si disponible
        // Logique principale: ratio = pagesPrinted / totalPages
        // - totalPages = nombre de pages du document (peut inclure les copies multiples, ex: 2 copies de 4 pages = 8)
        // - pagesPrinted = nombre de feuilles physiques imprimées
        // Si ratio ≈ 0.5 → duplex (2 pages par feuille)
        // Si ratio ≈ 1.0 → simplex (1 page par feuille)
        // Exemples avec copies multiples:
        //   - 2 copies de 4 pages en duplex: totalPages=8, pagesPrinted=4 → ratio=0.5 → duplex ✓
        //   - 2 copies de 4 pages en simplex: totalPages=8, pagesPrinted=8 → ratio=1.0 → simplex ✓
        // ATTENTION: Pour ACTIVE_JOB, pagesPrinted peut être 0 car le job vient juste d'être créé
        // On attendra un peu avant de faire le calcul final si pagesPrinted est 0 (même si totalPages est disponible)
        const needsDelayedCheck = (eventType === 'ACTIVE_JOB' && pagesPrinted === 0 && totalPages > 0);

        if (!isDuplex && totalPages > 0 && pagesPrinted > 0) {
            const ratio = pagesPrinted / totalPages;
            console.log(`📊 [DUPLEX_CALC] Calcul ratio: ${pagesPrinted} feuilles imprimées / ${totalPages} pages (total) = ${ratio.toFixed(3)}`);

            // Ratio exact de 0.5 = duplex (ex: 4 feuilles / 8 pages total = 0.5, peut être 2 copies de 4 pages en duplex)
            // MAIS: Ne pas détecter duplex si totalPages est trop petit (< 4) car c'est probablement incorrect
            if (Math.abs(ratio - 0.5) < 0.01 && totalPages >= 4) {
                isDuplex = true;
                console.log(`✅ [DUPLEX_CALC] Duplex détecté: ratio = ${ratio.toFixed(3)} (≈ 0.5)`);
            }
            // Ratio proche de 0.5 (tolérance 0.1) = probablement duplex
            // MAIS: Seulement si totalPages >= 4 pour éviter les faux positifs
            else if (ratio > 0.4 && ratio < 0.6 && totalPages >= 4) {
                isDuplex = true;
                console.log(`✅ [DUPLEX_CALC] Duplex détecté: ratio = ${ratio.toFixed(3)} (proche de 0.5)`);
            }
            // Ratio exact de 1.0 = simplex (ex: 8 feuilles / 8 pages total = 1.0, peut être 2 copies de 4 pages en simplex)
            else if (Math.abs(ratio - 1.0) < 0.01) {
                isDuplex = false;
                console.log(`✅ [DUPLEX_CALC] Simplex détecté: ratio = ${ratio.toFixed(3)} (≈ 1.0)`);
            }
            // Si totalPages est pair et pagesPrinted = totalPages / 2 exactement, c'est duplex
            // MAIS: Seulement si totalPages >= 4 pour éviter les faux positifs
            else if (totalPages % 2 === 0 && pagesPrinted === totalPages / 2 && totalPages >= 4) {
                isDuplex = true;
                console.log(`✅ [DUPLEX_CALC] Duplex détecté: ${totalPages} pages (total) = ${pagesPrinted} feuilles (ratio exact 0.5)`);
            }
            // Si totalPages est impair et pagesPrinted = Math.ceil(totalPages / 2), c'est probablement duplex
            // Exemple: 5 pages total, 3 feuilles imprimées = 3 feuilles duplex (ratio = 0.6)
            // MAIS: Seulement si totalPages >= 4 pour éviter les faux positifs
            else if (totalPages % 2 === 1 && pagesPrinted === Math.ceil(totalPages / 2) && totalPages >= 4) {
                isDuplex = true;
                console.log(`✅ [DUPLEX_CALC] Duplex détecté: ${totalPages} pages (total) = ${pagesPrinted} feuilles (page impaire, ratio = ${ratio.toFixed(3)})`);
            }
        } else if (needsDelayedCheck) {
            console.log(`⏳ [DUPLEX_CALC] Informations incomplètes (pagesPrinted=${pagesPrinted}, totalPages=${totalPages}), vérification différée programmée...`);
            console.log(`⏳ [DUPLEX_CALC] EventType: ${eventType}, JobId: ${jobInfo.JobId || jobInfo.JobID}`);
        }

        // Corriger pagesPrinted si isDuplex est détecté et que le ratio indique duplex
        // Si isDuplex = true et ratio ≈ 0.5, alors pagesPrinted devrait être totalPages / 2
        // Mais si totalPages semble incorrect (doublé), on peut le corriger si on a copies=1
        if (isDuplex && totalPages > 0 && pagesPrinted > 0) {
            const ratio = pagesPrinted / totalPages;
            // Si ratio est exactement 0.5 (duplex parfait)
            if (Math.abs(ratio - 0.5) < 0.01) {
                const numCopies = (cachedOptions && cachedOptions.options && cachedOptions.options.copies) ? cachedOptions.options.copies : 1;

                if (numCopies === 1) {
                    // 1 copie: si totalPages semble doublé (pair et > 2), essayer de le corriger
                    // Exemple: document de 2 pages, WMI retourne totalPages=4, pagesPrinted=2
                    // On peut essayer de diviser totalPages par 2 pour obtenir le vrai nombre de pages
                    // Puis recalculer pagesPrinted = (totalPages/2) / 2 = totalPages / 4
                    // Mais seulement si pagesPrinted = totalPages / 2 (ce qui est le cas ici)
                    if (totalPages % 2 === 0 && totalPages >= 4 && pagesPrinted === totalPages / 2) {
                        // totalPages semble doublé, corriger
                        const correctedTotalPages = totalPages / 2;
                        const correctedPagesPrinted = Math.ceil(correctedTotalPages / 2);
                        console.log(`📊 [PAGES_CORRECTION] Duplex détecté, totalPages semble doublé (${totalPages} → ${correctedTotalPages}), correction pagesPrinted: ${pagesPrinted} → ${correctedPagesPrinted}`);
                        totalPages = correctedTotalPages;
                        pagesPrinted = correctedPagesPrinted;
                    } else {
                        // Utiliser totalPages / 2 pour pagesPrinted si ce n'est pas déjà le cas
                        const expectedPagesPrinted = Math.ceil(totalPages / 2);
                        if (pagesPrinted !== expectedPagesPrinted) {
                            console.log(`📊 [PAGES_CORRECTION] Duplex détecté (ratio=${ratio.toFixed(3)}), correction: ${pagesPrinted} → ${expectedPagesPrinted} feuilles`);
                            pagesPrinted = expectedPagesPrinted;
                        }
                    }
                } else {
                    // Copies multiples: pagesPrinted est correct tel quel (total pour toutes les copies)
                    console.log(`📊 [PAGES_CORRECTION] Copies multiples (${numCopies}), pagesPrinted=${pagesPrinted} est correct (total pour toutes les copies)`);
                }
            }
        }

        // Préparer les données pour l'API PHP
        const printData = {
            jobId: String(jobInfo.JobId || jobInfo.JobID || ''),
            document: String(jobInfo.Document || ''),
            owner: String(jobInfo.Owner || ''),
            printerName: printerName,
            status: String(jobInfo.Status || 'Unknown'),
            pagesPrinted: pagesPrinted,
            totalPages: totalPages,
            timeSubmitted: String(jobInfo.TimeSubmitted || ''),
            size: parseInt(jobInfo.Size || 0),
            paperSize: paperSize !== 'Unknown' ? paperSize : null,
            duplex: isDuplex,
            colorMode: colorMode !== 'Unknown' ? colorMode : null,
            copies: copies,
            orientation: orientation,
            resolution: resolution,
            inputSlot: inputSlot,
            eventType: eventType.replace(/_/g, ' '),
            timestamp: new Date().toISOString()
        };

        // Log détaillé pour debug
        console.log('📄 Job détecté:', {
            document: printData.document,
            printer: printData.printerName,
            status: printData.status,
            pages: `${printData.pagesPrinted}/${printData.totalPages}`,
            paperSize: printData.paperSize || 'N/A',
            duplex: printData.duplex ? 'Oui' : 'Non',
            colorMode: printData.colorMode || 'N/A'
        });
        console.log('🔍 Données brutes WMI:', {
            PaperSize: jobInfo.PaperSize,
            IsDuplex: jobInfo.IsDuplex,
            ColorMode: jobInfo.ColorMode,
            PagesPrinted: jobInfo.PagesPrinted,
            TotalPages: jobInfo.TotalPages,
            Size: jobInfo.Size
        });
>>>>>>> origin/feature/impression-complete

        console.log(`🖨️ [NATIVE MONITOR] Job #${jobInfo.JobId}: ${jobInfo.Document} (${jobInfo.TotalPages}p) [${jobInfo.PaperSize}, ${jobInfo.ColorMode}, Duplex:${jobInfo.IsDuplex}]`);

        // Notifier l'application
        if (this.callbacks.onPrintJob) {
            this.callbacks.onPrintJob(jobInfo);
        }
<<<<<<< HEAD
=======

        // Envoyer à l'API PHP
        // Note: On n'envoie que pour COMPLETED_PRINT_JOB ou MODIFY_PRINT_JOB
        // ACTIVE_JOB est mis en cache mais pas envoyé à la base de données
        console.log(`💾 [DB] Envoi à la base de données pour ${eventType} (JobId: ${printData.jobId})`);
        await this.sendToPhpApi(printData);
>>>>>>> origin/feature/impression-complete
    }

    /**
     * Récupérer les informations d'un job depuis WMI
     */
    async getJobInfoFromWMI(jobId, printerName) {
        return new Promise((resolve, reject) => {
            try {
                const psScript = `
                    $job = Get-WmiObject Win32_PrintJob -Filter "JobId = $jobId AND Name LIKE '%${printerName}%'" -ErrorAction SilentlyContinue
                    if ($job) {
                        $result = @{
                            JobId = $job.JobId
                            Document = $job.Document
                            PagesPrinted = $job.PagesPrinted
                            TotalPages = $job.TotalPages
                            Status = $job.JobStatus
                            Owner = $job.Owner
                            Size = $job.Size
                        }
                        $result | ConvertTo-Json -Compress
                    }
                `;

                const { spawn } = require('child_process');
                const ps = spawn('powershell.exe', ['-Command', psScript], {
                    stdio: ['ignore', 'pipe', 'pipe']
                });

                let output = '';
                let error = '';

                ps.stdout.on('data', (data) => {
                    output += data.toString();
                });

                ps.stderr.on('data', (data) => {
                    error += data.toString();
                });

                ps.on('close', (code) => {
                    if (code === 0 && output.trim()) {
                        try {
                            const jobInfo = JSON.parse(output.trim());
                            resolve(jobInfo);
                        } catch (parseError) {
                            reject(new Error(`Erreur parsing: ${parseError.message}`));
                        }
                    } else if (error) {
                        reject(new Error(`Erreur PowerShell: ${error}`));
                    } else {
                        resolve(null); // Job non trouvé
                    }
                });
            } catch (error) {
                reject(error);
            }
        });
    }

    /**
     * Vérification différée d'un job pour récupérer les informations complètes
     */
    async checkJobDelayed(jobId, printerName, documentName) {
        try {
            console.log(`🔍 [DELAYED_CHECK] Vérification différée pour JobId ${jobId}...`);

            // Utiliser PowerShell pour récupérer les informations mises à jour du job
            const psScript = `
                $job = Get-WmiObject Win32_PrintJob -Filter "JobId = $jobId AND Name LIKE '%${printerName}%'" -ErrorAction SilentlyContinue
                if ($job) {
                    $result = @{
                        JobId = $job.JobId
                        Document = $job.Document
                        PagesPrinted = $job.PagesPrinted
                        TotalPages = $job.TotalPages
                        Status = $job.JobStatus
                    }
                    $result | ConvertTo-Json -Compress
                }
            `;

            const { spawn } = require('child_process');
            const ps = spawn('powershell.exe', ['-Command', psScript], {
                stdio: ['ignore', 'pipe', 'pipe']
            });

            let output = '';
            let error = '';

            ps.stdout.on('data', (data) => {
                output += data.toString();
            });

            ps.stderr.on('data', (data) => {
                error += data.toString();
            });

            ps.on('close', async (code) => {
                if (code === 0 && output.trim()) {
                    try {
                        const updatedJobInfo = JSON.parse(output.trim());
                        console.log(`✅ [DELAYED_CHECK] Informations mises à jour:`, updatedJobInfo);

                        // Si on a maintenant pagesPrinted > 0, mettre à jour
                        // (totalPages peut déjà être disponible depuis la première détection)
                        if (updatedJobInfo.PagesPrinted > 0 || (updatedJobInfo.TotalPages > 0 && updatedJobInfo.PagesPrinted === 0)) {
                            console.log(`📊 [DELAYED_CHECK] PagesPrinted: ${updatedJobInfo.PagesPrinted}, TotalPages: ${updatedJobInfo.TotalPages}`);
                            await this.updateJobInDatabase(jobId, updatedJobInfo);
                        } else {
                            console.log(`⚠️ [DELAYED_CHECK] PagesPrinted toujours 0, le job est peut-être terminé ou en cours de traitement`);
                        }
                    } catch (parseError) {
                        console.error(`❌ [DELAYED_CHECK] Erreur parsing:`, parseError.message);
                        console.error(`   Output:`, output.substring(0, 200));
                    }
                } else {
                    if (error) {
                        console.error(`❌ [DELAYED_CHECK] Erreur PowerShell:`, error);
                    } else if (!output.trim()) {
                        console.log(`⚠️ [DELAYED_CHECK] Job ${jobId} non trouvé dans WMI (peut-être terminé ou supprimé)`);
                    }
                }
            });
        } catch (error) {
            console.error(`❌ [DELAYED_CHECK] Erreur:`, error.message);
        }
    }

    /**
     * Mettre à jour un job existant dans la base de données
     */
    async updateJobInDatabase(jobId, updatedInfo) {
        try {
            // Préparer les données de mise à jour
            const updateData = {
                jobId: String(jobId),
                pagesPrinted: updatedInfo.PagesPrinted || 0,
                totalPages: updatedInfo.TotalPages || 0,
                status: updatedInfo.Status || 'Unknown'
            };

            // Recalculer le duplex si on a maintenant les informations
            if (updateData.totalPages > 0 && updateData.pagesPrinted > 0) {
                const ratio = updateData.pagesPrinted / updateData.totalPages;
                let isDuplex = false;

                if (Math.abs(ratio - 0.5) < 0.01 || (ratio > 0.4 && ratio < 0.6)) {
                    isDuplex = true;
                } else if (Math.abs(ratio - 1.0) < 0.01) {
                    isDuplex = false;
                }

                updateData.duplex = isDuplex;
                console.log(`✅ [DELAYED_CHECK] Duplex recalculé: ${updateData.pagesPrinted}/${updateData.totalPages} = ratio ${ratio.toFixed(3)} → ${isDuplex ? 'duplex' : 'simplex'}`);
            }

            // Envoyer la mise à jour à l'API PHP
            await this.sendToPhpApi(updateData);
        } catch (error) {
            console.error(`❌ [DELAYED_CHECK] Erreur mise à jour DB:`, error.message);
        }
    }

    // méthodes legacy (cache) gardées vides pour compatibilité si appelées ailleurs
    setPrintOptions() { }
    getPrintOptions() { return null; }

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
