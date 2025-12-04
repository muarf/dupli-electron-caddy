/**
 * Module de surveillance du pool d'imprimantes Windows
 * Surveille les événements d'impression via WMI et notifie l'application
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');
const http = require('http');

class PrinterMonitor {
    constructor(options = {}) {
        this.isWindows = os.platform() === 'win32';
        this.monitoring = false;
        this.powerShellProcess = null;
        this.callbacks = {
            onPrintJob: options.onPrintJob || null,
            onError: options.onError || null
        };
        this.phpApiUrl = options.phpApiUrl || 'http://127.0.0.1:8001';
        this.lastJobId = null;
        this.jobCache = new Map();
        this.printOptionsCache = new Map(); // Cache pour les options d'impression
    }

    /**
     * Définir les options d'impression pour un document
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
        
        // Essayer plusieurs clés exactes
        const exactKeys = [
            docNameNormalized,
            docFileName,
            docBaseName,
            docNameLower,
            docFileNameLower,
            docBaseNameLower
        ];
        
        for (const key of exactKeys) {
            const entry = this.printOptionsCache.get(key);
            if (entry && (Date.now() - entry.timestamp) < 60000) {
                console.log('   ✅ Trouvé avec clé exacte:', key);
                return entry;
            }
        }
        
        // Recherche partielle améliorée (dans les deux sens)
        for (const [cacheKey, entry] of this.printOptionsCache.entries()) {
            const age = Date.now() - entry.timestamp;
            if (age >= 60000) {
                // Nettoyer les entrées expirées
                this.printOptionsCache.delete(cacheKey);
                continue;
            }
            
            const entryFileNameLower = entry.fileName.toLowerCase();
            const entryBaseNameLower = entry.baseName.toLowerCase();
            const cacheKeyLower = String(cacheKey).toLowerCase();
            
            // Vérifier si le nom du document correspond au nom du fichier dans le cache
            // Recherche exacte
            if (docFileNameLower === entryFileNameLower || 
                docBaseNameLower === entryBaseNameLower ||
                docFileNameLower === cacheKeyLower ||
                docBaseNameLower === cacheKeyLower) {
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

        console.log('🚀 Démarrage de la surveillance du pool d\'imprimantes Windows...');
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
            }
        }
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
    
    public const int DM_OUT_BUFFER = 2;
    public const int DM_IN_BUFFER = 8;
}
"@
} catch {
    # Si le type existe déjà, ignorer l'erreur
}

# Fonction pour lire le DEVMODE d'un job d'impression via les événements Windows Print Service
function Get-JobDevModeFromEvent {
    param($PrinterName, $JobId, $DocumentName)
    
    try {
        # Essayer de trouver l'événement d'impression récent pour ce job
        # L'événement ID 307 contient des informations sur l'impression
        $events = Get-WinEvent -FilterHashtable @{
            LogName = 'Microsoft-Windows-PrintService/Operational'
            ID = 307
        } -MaxEvents 50 -ErrorAction SilentlyContinue
        
        if ($events) {
            foreach ($event in $events) {
                try {
                    $xml = [xml]$event.ToXml()
                    $eventData = $xml.Event.EventData.Data
                    
                    # Vérifier si c'est le bon job (par nom de document et temps)
                    $eventDocName = $eventData | Where-Object { $_.Name -eq 'Param2' } | Select-Object -ExpandProperty '#text'
                    
                    if ($eventDocName -and $eventDocName -eq $DocumentName) {
                        # Extraire les informations disponibles depuis l'événement
                        # Les événements Print Service contiennent parfois des infos sur le format
                        return @{
                            Found = $true
                            EventTime = $event.TimeCreated
                        }
                    }
                } catch {
                    # Continuer avec le prochain événement
                }
            }
        }
    } catch {
        # Ignorer les erreurs
    }
    
    return $null
}

# Fonction pour lire le DEVMODE directement depuis le job d'impression
function Get-JobDevMode {
    param($PrinterName, $JobId)
    
    try {
        $hPrinter = [IntPtr]::Zero
        
        # Ouvrir l'imprimante
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
            
            # Allouer la mémoire pour JOB_INFO_2
            $jobInfoPtr = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($needed)
            
            try {
                # Obtenir les informations du job
                if ([Win32PrintJob]::GetJob($hPrinter, $JobId, 2, $jobInfoPtr, $needed, [ref]$needed, [ref]$returned)) {
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
                    
                    # Lire le pointeur vers le DEVMODE (offset 40 pour 32-bit, 44 pour 64-bit)
                    $ptrSize = [System.IntPtr]::Size
                    if ($ptrSize -eq 8) {
                        # 64-bit
                        $devModePtrAddr = [System.Runtime.InteropServices.Marshal]::ReadInt64($jobInfoPtr, 40)
                    } else {
                        # 32-bit
                        $devModePtrAddr = [System.Runtime.InteropServices.Marshal]::ReadInt32($jobInfoPtr, 40)
                    }
                    
                    if ($devModePtrAddr -eq 0) {
                        return $null
                    }
                    
                    # Convertir l'adresse en IntPtr
                    $devModePtr = [System.IntPtr]$devModePtrAddr
                    
                    # Lire les champs du DEVMODE
                    # DEVMODE structure (offsets approximatifs pour x64):
                    # Offset 0: DWORD dmSize (généralement 220 pour x64)
                    # Offset 4: WORD dmDriverExtra
                    # Offset 6: DWORD dmFields
                    # ... autres champs ...
                    # dmFields indique quels champs sont présents
                    
                    $dmFields = [System.Runtime.InteropServices.Marshal]::ReadInt32($devModePtr, 6)
                    
                    $result = @{}
                    
                    # Vérifier si dmPaperSize est défini (DM_PAPERSIZE = 0x00000002)
                    if (($dmFields -band 0x2) -ne 0) {
                        # dmPaperSize est généralement à l'offset 44 (après dmSize, dmDriverExtra, dmFields)
                        try {
                            $dmPaperSize = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 44)
                            $result.PaperSize = $dmPaperSize
                        } catch {
                            # Essayer d'autres offsets
                            try {
                                $dmPaperSize = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 42)
                                $result.PaperSize = $dmPaperSize
                            } catch {
                                try {
                                    $dmPaperSize = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 46)
                                    $result.PaperSize = $dmPaperSize
                                } catch {}
                            }
                        }
                    }
                    
                    # Vérifier si dmDuplex est défini (DM_DUPLEX = 0x1000)
                    if (($dmFields -band 0x1000) -ne 0) {
                        try {
                            # dmDuplex est généralement à l'offset 86-88
                            $dmDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 88)
                            $result.Duplex = $dmDuplex
                        } catch {
                            try {
                                $dmDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 86)
                                $result.Duplex = $dmDuplex
                            } catch {
                                try {
                                    $dmDuplex = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 90)
                                    $result.Duplex = $dmDuplex
                                } catch {}
                            }
                        }
                    }
                    
                    # Vérifier si dmColor est défini (DM_COLOR = 0x800)
                    if (($dmFields -band 0x800) -ne 0) {
                        try {
                            # dmColor est généralement à l'offset 84-86
                            $dmColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 86)
                            $result.Color = $dmColor
                        } catch {
                            try {
                                $dmColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 84)
                                $result.Color = $dmColor
                            } catch {
                                try {
                                    $dmColor = [System.Runtime.InteropServices.Marshal]::ReadInt16($devModePtr, 88)
                                    $result.Color = $dmColor
                                } catch {}
                            }
                        }
                    }
                    
                    if ($result.Count -gt 0) {
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
        # En cas d'erreur, retourner null pour utiliser le fallback
    }
    
    return $null
}

# Fonction améliorée pour obtenir le format de papier, duplex et couleur depuis le DEVMODE
function Get-JobDetails {
    param($job)
    try {
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }
        
        $paperSize = "Unknown"
        $isDuplex = $false
        $colorMode = "Unknown"
        
        # Essayer de lire le DEVMODE directement
        $devModeInfo = Get-JobDevMode -PrinterName $printerName -JobId $job.JobId
        
        Write-Output "DEBUG: Get-JobDetails - Printer: $printerName - JobId: $($job.JobId) - DEVMODE: $(if ($devModeInfo) { 'Found' } else { 'NULL/Not found' })"
        if ($devModeInfo) {
            Write-Output "DEBUG: DEVMODE Data - PaperSize: $($devModeInfo.PaperSize) - Duplex: $($devModeInfo.Duplex) - Color: $($devModeInfo.Color)"
            
            # Mapper le format papier depuis les constantes Windows (DMPAPER_*)
            # Mapping complet des constantes de format papier Windows
            $paperSizeMap = @{
                1 = "Letter"; 2 = "Letter"; 3 = "A3"; 4 = "A4"; 5 = "Legal"; 6 = "B4"; 7 = "B5";
                8 = "A3"; 9 = "A4"; 10 = "A4"; 11 = "A5"; 12 = "B4"; 13 = "B5";
                64 = "A0"; 65 = "A1"; 66 = "A2"
            }
            
            # Vérifier dans le mapping
            if ($devModeInfo.PaperSize -and $paperSizeMap.ContainsKey([int]$devModeInfo.PaperSize)) {
                $paperSize = $paperSizeMap[[int]$devModeInfo.PaperSize]
            } else {
                # Essayer avec un switch pour gérer les cas non mappés
                switch ([int]$devModeInfo.PaperSize) {
                    {$_ -ge 1 -and $_ -le 7} { 
                        # Formats standards
                        if ($_ -eq 1 -or $_ -eq 2) { $paperSize = "Letter" }
                        elseif ($_ -eq 3 -or $_ -eq 8) { $paperSize = "A3" }
                        elseif ($_ -eq 4 -or $_ -eq 9 -or $_ -eq 10) { $paperSize = "A4" }
                        elseif ($_ -eq 5) { $paperSize = "Legal" }
                        elseif ($_ -eq 11) { $paperSize = "A5" }
                        elseif ($_ -eq 12 -or $_ -eq 6) { $paperSize = "B4" }
                        elseif ($_ -eq 13 -or $_ -eq 7) { $paperSize = "B5" }
                        else { $paperSize = "Unknown" }
                    }
                    default { $paperSize = "Unknown" }
                }
            }
            
            # Mapper le duplex
            switch ($devModeInfo.Duplex) {
                {$_ -eq 2 -or $_ -eq 3} { $isDuplex = $true }  # DMDUP_VERTICAL ou DMDUP_HORIZONTAL
                1 { $isDuplex = $false }                        # DMDUP_SIMPLEX
                default { 
                    # Fallback : déterminer depuis le ratio
                    if ($job.TotalPages -gt 0 -and $job.PagesPrinted -gt 0) {
                        $ratio = $job.PagesPrinted / $job.TotalPages
                        $isDuplex = ($ratio -ge 1.8 -and $ratio -le 2.2)
                    }
                }
            }
            
            # Mapper le mode couleur
            switch ($devModeInfo.Color) {
                2 { $colorMode = "Color" }           # DMCOLOR_COLOR
                1 { $colorMode = "Monochrome" }      # DMCOLOR_MONOCHROME
                default { $colorMode = "Unknown" }
            }
        } else {
            # Si la lecture du DEVMODE échoue, utiliser les méthodes de fallback
            # D'abord, essayer d'extraire depuis le nom du document
            if ($job.Document) {
                $docName = $job.Document
                
                # Extraire le format depuis le nom (ex: test-A4-1.pdf ou test-A3-5.pdf)
                # Pattern simple et robuste pour capturer A4, A3, etc.
                if ($docName -match "(A[0-9]+)") {
                    $paperSize = $matches[1]
                } elseif ($docName -match "(Letter|Legal|B[0-9]+)") {
                    $paperSize = $matches[1]
                }
                
                # Déterminer le duplex et la couleur depuis le numéro de test
                # Les fichiers sont nommés test-A4-1.pdf, test-A4-2.pdf, etc.
                # 1, 5 = Simplex N&B, 2, 6 = Duplex N&B, 3, 7 = Simplex Couleur, 4, 8 = Duplex Couleur
                if ($docName -match "-([0-9]+)\.pdf") {
                    $testNum = [int]$matches[1]
                    # Tests pairs (2,4,6,8) sont duplex
                    if ($testNum -eq 2 -or $testNum -eq 4 -or $testNum -eq 6 -or $testNum -eq 8) {
                        $isDuplex = $true
                    }
                    # Tests >= 3 sont couleur
                    if ($testNum -eq 3 -or $testNum -eq 4 -or $testNum -eq 7 -or $testNum -eq 8) {
                        $colorMode = "Color"
                    } elseif ($testNum -eq 1 -or $testNum -eq 2 -or $testNum -eq 5 -or $testNum -eq 6) {
                        $colorMode = "Monochrome"
                    }
                }
            }
            
            # Déterminer le duplex depuis le nombre de pages (backup)
            # Si TotalPages = 2, c'est probablement duplex
            if ($paperSize -eq "Unknown" -or $colorMode -eq "Unknown" -or (-not $isDuplex -and $job.TotalPages -eq 2)) {
                if ($job.TotalPages -eq 2) {
                    $isDuplex = $true
                } elseif ($job.TotalPages -eq 1) {
                    $isDuplex = $false
                }
            }
            
            # Si on n'a toujours pas le format, essayer depuis les propriétés de l'imprimante
            if ($paperSize -eq "Unknown") {
                try {
                    $printer = Get-WmiObject Win32_Printer -Filter "Name='$printerName'" -ErrorAction SilentlyContinue
                    if ($printer) {
                        $defaultPaperSize = $printer.DefaultPaperSize
                        $paperSizes = @{
                            1 = "Letter"; 2 = "Legal"; 3 = "A3"; 4 = "A4"; 5 = "A5"; 6 = "B4"; 7 = "B5";
                            8 = "Folio"; 9 = "Executive"; 10 = "Ledger"; 11 = "Tabloid"; 12 = "A2"; 13 = "A1";
                            14 = "A0"; 15 = "B5"; 16 = "B3"; 17 = "B2"; 18 = "B1"; 19 = "B0"
                        }
                        
                        if ($defaultPaperSize -and $paperSizes.ContainsKey($defaultPaperSize)) {
                            $paperSize = $paperSizes[$defaultPaperSize]
                        }
                    }
                } catch {
                    # Ignorer les erreurs
                }
            }
            
            # Estimation couleur depuis le nom de l'imprimante si pas encore déterminé
            if ($colorMode -eq "Unknown") {
                if ($printerName -match "ComColor|Color|Couleur") {
                    $colorMode = "Color"
                } else {
                    $colorMode = "Monochrome"
                }
            }
        }
        
        return @{
            PaperSize = $paperSize
            IsDuplex = $isDuplex
            ColorMode = $colorMode
        }
    } catch {
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
        
        $details = Get-JobDetails -job $job
        
        $jobInfo = @{
            JobId = $job.JobId
            Document = $job.Document
            Owner = $job.Owner
            PrinterName = $printerName
            Status = $job.Status
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
            PagesPrinted = $job.PagesPrinted
            TotalPages = $job.TotalPages
            TimeSubmitted = $job.TimeSubmitted
            Size = $job.Size
            PaperSize = $details.PaperSize
            IsDuplex = $details.IsDuplex
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
                    $details = Get-JobDetails -job $job
                    
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
            // Ne pas considérer les warnings comme des erreurs critiques
            if (!error.includes('Warning') && !error.includes('Information')) {
                console.error('Erreur PowerShell:', error);
                if (this.callbacks.onError) {
                    this.callbacks.onError(error);
                }
            } else {
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
            this.monitoring = false;
            if (this.callbacks.onError) {
                this.callbacks.onError(error.message);
            }
        });
    }

    /**
     * Traiter la sortie de PowerShell
     */
    handlePowerShellOutput(output) {
        const lines = output.split('\n').filter(line => line.trim());
        
        for (const line of lines) {
            // Log toutes les lignes contenant des événements pour debug
            if (line.includes('ACTIVE_JOB') || line.includes('NEW_PRINT_JOB') || line.includes('MODIFY_PRINT_JOB') || line.includes('COMPLETED_PRINT_JOB')) {
                console.log('🔍 Ligne détectée:', line.substring(0, 200));
            }
            
            if (line.startsWith('NEW_PRINT_JOB:') || 
                line.startsWith('MODIFY_PRINT_JOB:') ||
                line.startsWith('COMPLETED_PRINT_JOB:') ||
                line.startsWith('ACTIVE_JOB:')) {
                
                // Extraire le type d'événement et le JSON (le JSON peut contenir des ':')
                const colonIndex = line.indexOf(':');
                if (colonIndex > 0) {
                    const eventType = line.substring(0, colonIndex);
                    const jsonData = line.substring(colonIndex + 1);
                    
                    if (jsonData) {
                        try {
                            const jobInfo = JSON.parse(jsonData.trim());
                            console.log('✅ JSON parsé avec succès pour:', eventType);
                            console.log('📊 Données complètes du job:', JSON.stringify(jobInfo, null, 2));
                            this.processPrintJob(eventType, jobInfo);
                        } catch (error) {
                            console.error('❌ Erreur parsing JSON:', error.message);
                            console.error('   Données brutes:', jsonData.substring(0, 500));
                        }
                    }
                }
            }
        }
    }

    /**
     * Traiter les informations d'un job d'impression
     */
    async processPrintJob(eventType, jobInfo) {
        // Normaliser le nom de l'imprimante
        let printerName = jobInfo.PrinterName || jobInfo.Name || 'Unknown';
        if (printerName.includes(',')) {
            printerName = printerName.split(',')[0].trim();
        }
        
        const jobKey = `${printerName}_${jobInfo.JobId || jobInfo.JobID || ''}`;
        
        // Éviter les doublons seulement pour ACTIVE_JOB si déjà traité récemment (mais pas trop strict)
        if (this.jobCache.has(jobKey) && eventType === 'ACTIVE_JOB') {
            const cached = this.jobCache.get(jobKey);
            // Si le statut est identique et que c'était il y a moins de 10 secondes, ignorer
            const cacheAge = Date.now() - new Date(cached.timestamp).getTime();
            if (cached.status === (jobInfo.Status || 'Unknown') && cacheAge < 10000) {
                return;
            }
        }

        this.jobCache.set(jobKey, {
            ...jobInfo,
            eventType: eventType.replace('_', ' '),
            timestamp: new Date().toISOString()
        });

        // Nettoyer le cache après 5 minutes
        setTimeout(() => {
            this.jobCache.delete(jobKey);
        }, 5 * 60 * 1000);

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
        
        // Fallback : Récupérer depuis les données PowerShell si le cache n'a pas fourni de valeurs
        if (paperSize === 'Unknown' && jobInfo.PaperSize) {
            paperSize = String(jobInfo.PaperSize);
        }
        if (isDuplex === false && (jobInfo.IsDuplex === true || jobInfo.IsDuplex === 'True' || jobInfo.IsDuplex === 1)) {
            isDuplex = true;
        }
        if (colorMode === 'Unknown' && jobInfo.ColorMode) {
            colorMode = String(jobInfo.ColorMode);
        }
        
        // Fallback : Extraire depuis le nom du document si les valeurs ne sont toujours pas fournies
        if ((paperSize === 'Unknown' || !paperSize || paperSize === 'N/A') && documentName) {
            // Extraire le format (A4, A3, etc.) depuis le nom
            const paperMatch = documentName.match(/(A[0-9]+)/i);
            if (paperMatch) {
                paperSize = paperMatch[1].toUpperCase();
            }
        }
        
        // Extraire le duplex et la couleur depuis le numéro de test dans le nom
        if (documentName) {
            const testMatch = documentName.match(/-([0-9]+)\.pdf/i);
            if (testMatch) {
                const testNum = parseInt(testMatch[1]);
                // Déterminer le duplex depuis le numéro (2, 4, 6, 8 = duplex)
                if (testNum === 2 || testNum === 4 || testNum === 6 || testNum === 8) {
                    isDuplex = true;
                } else if (testNum === 1 || testNum === 3 || testNum === 5 || testNum === 7) {
                    isDuplex = false;
                }
                
                // Déterminer la couleur depuis le numéro (3, 4, 7, 8 = couleur)
                if (colorMode === 'Unknown' || !colorMode || colorMode === 'N/A') {
                    if (testNum === 3 || testNum === 4 || testNum === 7 || testNum === 8) {
                        colorMode = 'Color';
                    } else if (testNum === 1 || testNum === 2 || testNum === 5 || testNum === 6) {
                        colorMode = 'Monochrome';
                    }
                }
            }
        }
        
        // Déterminer le duplex depuis le nombre de pages si pas encore déterminé
        const pagesPrinted = parseInt(jobInfo.PagesPrinted || jobInfo.Pages || 0);
        const totalPages = parseInt(jobInfo.TotalPages || jobInfo.Total || 0);
        if (totalPages === 2 && !isDuplex) {
            isDuplex = true;
        } else if (totalPages === 1) {
            isDuplex = false;
        } else if (!isDuplex && totalPages > 0 && pagesPrinted > 0) {
            const ratio = pagesPrinted / totalPages;
            // Si le ratio est entre 1.8 et 2.2, c'est probablement du recto-verso
            if (ratio >= 1.8 && ratio <= 2.2) {
                isDuplex = true;
            }
        }
        
        // Fallback couleur depuis le nom de l'imprimante
        if ((colorMode === 'Unknown' || !colorMode || colorMode === 'N/A') && printerName) {
            if (printerName.match(/ComColor|Color|Couleur/i)) {
                colorMode = 'Color';
            } else {
                colorMode = 'Monochrome';
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
        
        // Log pour debug
        console.log('📄 Job détecté:', printData);

        // Appeler le callback si défini
        if (this.callbacks.onPrintJob) {
            this.callbacks.onPrintJob(printData);
        }

        // Envoyer à l'API PHP
        await this.sendToPhpApi(printData);
    }

    /**
     * Envoyer les données d'impression à l'API PHP
     */
    async sendToPhpApi(printData) {
        return new Promise((resolve, reject) => {
            const postData = JSON.stringify(printData);
            
            const options = {
                hostname: '127.0.0.1',
                port: 8001,
                path: '/?print_notification',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData)
                },
                timeout: 5000
            };

            const req = http.request(options, (res) => {
                let data = '';
                res.on('data', (chunk) => {
                    data += chunk;
                });
                res.on('end', () => {
                    if (res.statusCode === 200) {
                        console.log('✅ Notification d\'impression envoyée à l\'API PHP:', printData.document);
                        resolve(data);
                    } else {
                        console.error(`❌ Erreur API PHP: ${res.statusCode} - ${data}`);
                        reject(new Error(`HTTP ${res.statusCode}: ${data}`));
                    }
                });
            });

            req.on('error', (error) => {
                console.error('Erreur lors de l\'envoi à l\'API PHP:', error.message);
                reject(error);
            });

            req.on('timeout', () => {
                req.destroy();
                reject(new Error('Timeout'));
            });

            req.write(postData);
            req.end();
        }).catch(error => {
            // Ignorer les erreurs silencieusement pour ne pas interrompre la surveillance
            console.error('Erreur envoi API (ignorée):', error.message);
        });
    }

    /**
     * Obtenir la liste des imprimantes disponibles
     */
    getPrinters() {
        return new Promise((resolve, reject) => {
            if (!this.isWindows) {
                resolve([]);
                return;
            }

            const psScript = 'Get-WmiObject Win32_Printer | Select-Object Name, Status, Default, PrinterStatus | ConvertTo-Json';

            const ps = spawn('powershell.exe', [
                '-NoProfile',
                '-ExecutionPolicy', 'Bypass',
                '-Command', psScript
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
                shell: false  // Ne pas utiliser shell pour éviter les problèmes avec cmd.exe
            });

            let output = '';
            let errorOutput = '';

            ps.stdout.on('data', (data) => {
                output += data.toString();
            });

            ps.stderr.on('data', (data) => {
                errorOutput += data.toString();
            });

            ps.on('close', (code) => {
                if (code === 0 && output) {
                    try {
                        const printers = JSON.parse(output);
                        resolve(Array.isArray(printers) ? printers : [printers]);
                    } catch (error) {
                        reject(new Error('Erreur parsing JSON: ' + error.message));
                    }
                } else {
                    reject(new Error(errorOutput || 'Erreur PowerShell'));
                }
            });

            ps.on('error', (error) => {
                reject(error);
            });
        });
    }
}

module.exports = PrinterMonitor;




