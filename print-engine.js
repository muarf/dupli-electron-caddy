/**
 * Moteur d'impression utilisant l'API Windows Print (winspool.drv)
 *
 * Ce module garantit que les impressions passent par le spooler Windows
 * et sont donc détectées par le système de monitoring WMI.
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

class PrintEngine {
    constructor() {
        this.isWindows = os.platform() === 'win32';
    }

    /**
     * Imprimer un document avec des paramètres spécifiques
     * @param {string} filePath - Chemin vers le fichier à imprimer
     * @param {Object} options - Options d'impression
     */
    async printDocument(filePath, options = {}) {
        if (!this.isWindows) {
            throw new Error('Le moteur d\'impression n\'est disponible que sur Windows');
        }

        if (!fs.existsSync(filePath)) {
            throw new Error(`Fichier non trouvé: ${filePath}`);
        }

        const {
            printerName = null, // Imprimante par défaut si null
            paperSize = 'A4',
            duplex = false,
            color = false,
            copies = 1
        } = options;

        console.log(`🖨️ Impression de ${filePath} avec paramètres:`, { paperSize, duplex, color, copies });

        // Enregistrer les paramètres d'impression AVANT de lancer l'impression
        // Cela permet de capturer les paramètres même si le DEVMODE ne les contient pas
        await this.registerPrintParams(filePath, {
            printerName,
            paperSize,
            duplex,
            color,
            copies
        });

        // Utiliser PowerShell avec l'API winspool.drv pour garantir la détection WMI
        const psScript = this.generatePrintScript(filePath, {
            printerName,
            paperSize,
            duplex,
            color,
            copies
        });

        return this.executePrintScript(psScript);
    }

    /**
     * Enregistrer les paramètres d'impression dans le cache (appelé AVANT l'impression)
     * @param {string} filePath - Chemin du fichier à imprimer
     * @param {Object} options - Options d'impression
     */
    async registerPrintParams(filePath, options = {}) {
        try {
            const hookScript = path.join(__dirname, 'utils', 'print-hook.ps1');
            if (!fs.existsSync(hookScript)) {
                console.warn('⚠️ Script print-hook.ps1 non trouvé');
                return;
            }

            const {
                printerName = null,
                paperSize = 'A4',
                duplex = false,
                color = false,
                copies = 1
            } = options;

            // Appeler le script PowerShell pour enregistrer les paramètres
            // Utiliser des switch PowerShell au lieu de booléens
            const duplexSwitch = duplex ? '-Duplex' : '';
            const colorSwitch = color ? '-Color' : '';
            const psCommand = `& "${hookScript}" -FilePath "${filePath}" -PaperSize "${paperSize}" ${duplexSwitch} ${colorSwitch} -Copies ${copies} ${printerName ? `-PrinterName "${printerName}"` : ''}`;
            
            return new Promise((resolve, reject) => {
                const ps = spawn('powershell.exe', ['-ExecutionPolicy', 'Bypass', '-Command', psCommand], {
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
                    if (code === 0) {
                        console.log('✅ [PRINT_HOOK] Paramètres enregistrés:', output.trim());
                        resolve();
                    } else {
                        console.warn('⚠️ [PRINT_HOOK] Erreur enregistrement paramètres:', error.trim());
                        resolve(); // Ne pas bloquer l'impression en cas d'erreur
                    }
                });
            });
        } catch (error) {
            console.warn('⚠️ [PRINT_HOOK] Exception:', error.message);
            // Ne pas bloquer l'impression en cas d'erreur
        }
    }

    /**
     * Générer le script PowerShell qui utilise l'API Windows Print
     */
    generatePrintScript(filePath, options) {
        const {
            printerName = null,
            paperSize = 'A4',
            duplex = false,
            color = false,
            copies = 1
        } = options;

        // Définir les constantes Windows pour le format papier
        const paperSizeConstants = {
            'Letter': 1,
            'A4': 9,
            'A3': 8,
            'Legal': 5,
            'A5': 11,
            'B4': 12,
            'B5': 13
        };

        const paperSizeValue = paperSizeConstants[paperSize] || 9; // A4 par défaut

        // Générer le script PowerShell
        return `
# Script d'impression utilisant l'API Windows Print (winspool.drv)
# Cela garantit que l'impression passe par le spooler Windows et est détectée par WMI

$ErrorActionPreference = "Stop"

# Définir les types et APIs Windows nécessaires
try {
    Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
using System.Text;
using System.IO;

public class WindowsPrintAPI {
    // Constantes Windows
    public const uint DM_OUT_BUFFER = 2;
    public const uint DM_IN_BUFFER = 8;
    public const short DM_ORIENTATION = 0x1;
    public const short DM_PAPERSIZE = 0x2;
    public const short DM_COPIES = 0x100;
    public const short DM_DUPLEX = 0x1000;
    public const short DM_COLOR = 0x800;

    // Constantes de format papier
    public const short DMPAPER_LETTER = 1;
    public const short DMPAPER_A4 = 9;
    public const short DMPAPER_A3 = 8;
    public const short DMPAPER_LEGAL = 5;

    // Constantes duplex
    public const short DMDUP_SIMPLEX = 1;
    public const short DMDUP_VERTICAL = 2;
    public const short DMDUP_HORIZONTAL = 3;

    // Constantes couleur
    public const short DMCOLOR_COLOR = 2;
    public const short DMCOLOR_MONOCHROME = 1;

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
    public struct DEVMODE {
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
        public string dmDeviceName;
        public short dmSpecVersion;
        public short dmDriverVersion;
        public short dmSize;
        public short dmDriverExtra;
        public int dmFields;
        public short dmOrientation;
        public short dmPaperSize;
        public short dmPaperLength;
        public short dmPaperWidth;
        public short dmScale;
        public short dmCopies;
        public short dmDefaultSource;
        public short dmPrintQuality;
        public short dmColor;
        public short dmDuplex;
        public short dmYResolution;
        public short dmTTOption;
        public short dmCollate;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
        public string dmFormName;
        public short dmLogPixels;
        public int dmBitsPerPel;
        public int dmPelsWidth;
        public int dmPelsHeight;
        public int dmDisplayFlags;
        public int dmDisplayFrequency;
        public int dmICMMethod;
        public int dmICMIntent;
        public int dmMediaType;
        public int dmDitherType;
        public int dmReserved1;
        public int dmReserved2;
        public int dmPanningWidth;
        public int dmPanningHeight;
    }

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Auto)]
    public struct DOCINFOA {
        public int cbSize;
        public string lpszDocName;
        public string lpszOutput;
        public string lpszDatatype;
        public int fwType;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, ref DOCINFOA pDocInfo);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBuf, int cbBuf, out int pcWritten);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool GetPrinter(IntPtr hPrinter, int dwLevel, IntPtr pPrinter, int cbBuf, out int pcbNeeded);

    [DllImport("winspool.drv", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool DocumentProperties(IntPtr hwnd, IntPtr hPrinter, string pDeviceName, IntPtr pDevModeOutput, IntPtr pDevModeInput, uint fMode);

    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern IntPtr GlobalLock(IntPtr hMem);

    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern bool GlobalUnlock(IntPtr hMem);

    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern IntPtr GlobalAlloc(uint uFlags, IntPtr dwBytes);

    [DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
    public static extern IntPtr GlobalFree(IntPtr hMem);
}
"@ | Out-Null
} catch {
    Write-Error "Erreur lors de la définition des types Windows: $($_.Exception.Message)"
    exit 1
}

function Print-Document {
    param(
        [string]$FilePath,
        [string]$PrinterName = $null,
        [string]$PaperSize = "A4",
        [bool]$Duplex = $false,
        [bool]$Color = $false,
        [int]$Copies = 1
    )

    try {
        # Obtenir l'imprimante par défaut si non spécifiée
        if (-not $PrinterName) {
            $PrinterName = (Get-WmiObject Win32_Printer -Filter "Default = True" | Select-Object -First 1).Name
            if (-not $PrinterName) {
                $PrinterName = (Get-WmiObject Win32_Printer | Select-Object -First 1).Name
            }
        }

        Write-Host "Impression vers: $PrinterName"

        # Ouvrir l'imprimante
        $hPrinter = [IntPtr]::Zero
        if (-not [WindowsPrintAPI]::OpenPrinter($PrinterName, [ref]$hPrinter, [IntPtr]::Zero)) {
            throw "Impossible d'ouvrir l'imprimante $PrinterName"
        }

        try {
            # Créer la structure DEVMODE pour définir les paramètres d'impression
            $devMode = New-Object WindowsPrintAPI+DEVMODE
            $devMode.dmSize = [System.Runtime.InteropServices.Marshal]::SizeOf($devMode)
            $devMode.dmFields = 0

            # Définir le format papier
            $paperSizeMap = @{
                "A4" = [WindowsPrintAPI]::DMPAPER_A4
                "A3" = [WindowsPrintAPI]::DMPAPER_A3
                "Letter" = [WindowsPrintAPI]::DMPAPER_LETTER
                "Legal" = [WindowsPrintAPI]::DMPAPER_LEGAL
            }

            if ($paperSizeMap.ContainsKey($PaperSize)) {
                $devMode.dmPaperSize = $paperSizeMap[$PaperSize]
                $devMode.dmFields = $devMode.dmFields -bor [WindowsPrintAPI]::DM_PAPERSIZE
            }

            # Définir le duplex
            if ($Duplex) {
                $devMode.dmDuplex = [WindowsPrintAPI]::DMDUP_VERTICAL
                $devMode.dmFields = $devMode.dmFields -bor [WindowsPrintAPI]::DM_DUPLEX
            } else {
                $devMode.dmDuplex = [WindowsPrintAPI]::DMDUP_SIMPLEX
                $devMode.dmFields = $devMode.dmFields -bor [WindowsPrintAPI]::DM_DUPLEX
            }

            # Définir la couleur
            if ($Color) {
                $devMode.dmColor = [WindowsPrintAPI]::DMCOLOR_COLOR
            } else {
                $devMode.dmColor = [WindowsPrintAPI]::DMCOLOR_MONOCHROME
            }
            $devMode.dmFields = $devMode.dmFields -bor [WindowsPrintAPI]::DM_COLOR

            # Définir le nombre de copies
            $devMode.dmCopies = $Copies
            $devMode.dmFields = $devMode.dmFields -bor [WindowsPrintAPI]::DM_COPIES

            # Allouer la mémoire pour DEVMODE
            $devModeSize = [System.Runtime.InteropServices.Marshal]::SizeOf($devMode)
            $hDevMode = [WindowsPrintAPI]::GlobalAlloc(0x0040, [IntPtr]$devModeSize)

            if ($hDevMode -eq [IntPtr]::Zero) {
                throw "Impossible d'allouer la mémoire pour DEVMODE"
            }

            try {
                $pDevMode = [WindowsPrintAPI]::GlobalLock($hDevMode)
                [System.Runtime.InteropServices.Marshal]::StructureToPtr($devMode, $pDevMode, $false)

                # Créer la structure DOCINFO
                $docInfo = New-Object WindowsPrintAPI+DOCINFOA
                $docInfo.cbSize = [System.Runtime.InteropServices.Marshal]::SizeOf($docInfo)
                $docInfo.lpszDocName = [System.IO.Path]::GetFileName($FilePath)
                $docInfo.lpszOutput = $null
                $docInfo.lpszDatatype = "RAW"
                $docInfo.fwType = 0

                # Démarrer le document
                if (-not [WindowsPrintAPI]::StartDocPrinter($hPrinter, 1, [ref]$docInfo)) {
                    throw "Impossible de démarrer l'impression du document"
                }

                # Démarrer la page
                if (-not [WindowsPrintAPI]::StartPagePrinter($hPrinter)) {
                    throw "Impossible de démarrer la page"
                }

                # Lire et envoyer le contenu du fichier
                $fileStream = [System.IO.File]::OpenRead($FilePath)
                try {
                    $bufferSize = 8192
                    $buffer = New-Object byte[] $bufferSize
                    $bytesRead = 0

                    while (($bytesRead = $fileStream.Read($buffer, 0, $bufferSize)) -gt 0) {
                        $pBuffer = [System.Runtime.InteropServices.Marshal]::AllocHGlobal($bytesRead)
                        try {
                            [System.Runtime.InteropServices.Marshal]::Copy($buffer, 0, $pBuffer, $bytesRead)

                            $bytesWritten = 0
                            if (-not [WindowsPrintAPI]::WritePrinter($hPrinter, $pBuffer, $bytesRead, [ref]$bytesWritten)) {
                                throw "Erreur lors de l'écriture vers l'imprimante"
                            }
                        } finally {
                            [System.Runtime.InteropServices.Marshal]::FreeHGlobal($pBuffer)
                        }
                    }
                } finally {
                    $fileStream.Close()
                }

                # Terminer la page
                if (-not [WindowsPrintAPI]::EndPagePrinter($hPrinter)) {
                    throw "Impossible de terminer la page"
                }

                # Terminer le document
                if (-not [WindowsPrintAPI]::EndDocPrinter($hPrinter)) {
                    throw "Impossible de terminer le document"
                }

                Write-Host "Document envoyé à l'imprimante avec succès"

            } finally {
                if ($pDevMode -ne [IntPtr]::Zero) {
                    [WindowsPrintAPI]::GlobalUnlock($hDevMode)
                }
                if ($hDevMode -ne [IntPtr]::Zero) {
                    [WindowsPrintAPI]::GlobalFree($hDevMode)
                }
            }

        } finally {
            [WindowsPrintAPI]::ClosePrinter($hPrinter)
        }

    } catch {
        Write-Error "Erreur lors de l'impression: $($_.Exception.Message)"
        throw
    }
}

# Paramètres d'impression
$FilePath = "${filePath.replace(/\\/g, '\\\\')}"
$PrinterName = ${printerName ? `"$printerName"` : '$null'}
$PaperSize = "${paperSize}"
$Duplex = $${duplex ? 'true' : 'false'}
$Color = $${color ? 'true' : 'false'}
$Copies = ${copies}

Write-Host "Démarrage de l'impression..."
Write-Host "Fichier: $FilePath"
Write-Host "Imprimante: $PrinterName"
Write-Host "Format: $PaperSize"
Write-Host "Duplex: $Duplex"
Write-Host "Couleur: $Color"
Write-Host "Copies: $Copies"

# Lancer l'impression
Print-Document -FilePath $FilePath -PrinterName $PrinterName -PaperSize $PaperSize -Duplex $Duplex -Color $Color -Copies $Copies

Write-Host "Impression terminée avec succès"
        `;
    }

    /**
     * Exécuter le script PowerShell d'impression
     */
    async executePrintScript(psScript) {
        return new Promise((resolve, reject) => {
            const tempScriptPath = path.join(os.tmpdir(), `print-job-${Date.now()}.ps1`);

            try {
                // Écrire le script dans un fichier temporaire
                fs.writeFileSync(tempScriptPath, psScript, 'utf8');

                // Exécuter le script PowerShell
                const psProcess = spawn('powershell.exe', [
                    '-NoProfile',
                    '-ExecutionPolicy', 'Bypass',
                    '-File', tempScriptPath
                ], {
                    stdio: ['pipe', 'pipe', 'pipe'],
                    shell: false
                });

                let output = '';
                let errorOutput = '';

                psProcess.stdout.on('data', (data) => {
                    output += data.toString();
                });

                psProcess.stderr.on('data', (data) => {
                    errorOutput += data.toString();
                });

                psProcess.on('close', (code) => {
                    // Nettoyer le fichier temporaire
                    try {
                        fs.unlinkSync(tempScriptPath);
                    } catch (e) {
                        // Ignorer
                    }

                    if (code === 0) {
                        console.log('✅ Impression lancée avec succès via winspool.drv');
                        resolve(output);
                    } else {
                        console.error('❌ Erreur lors de l\'impression:', errorOutput);
                        reject(new Error(`Code d'erreur PowerShell: ${code}\n${errorOutput}`));
                    }
                });

                psProcess.on('error', (error) => {
                    try {
                        fs.unlinkSync(tempScriptPath);
                    } catch (e) {
                        // Ignorer
                    }
                    reject(error);
                });

            } catch (error) {
                try {
                    fs.unlinkSync(tempScriptPath);
                } catch (e) {
                    // Ignorer
                }
                reject(error);
            }
        });
    }

    /**
     * Imprimer un document de test avec des paramètres spécifiques
     */
    async printTestDocument(testNumber) {
        // Définir les paramètres selon le numéro de test
        const testConfigs = {
            1: { paperSize: 'A4', duplex: false, color: false, pages: 1 },
            2: { paperSize: 'A4', duplex: true, color: true, pages: 2 },
            3: { paperSize: 'A3', duplex: false, color: false, pages: 1 },
            4: { paperSize: 'A3', duplex: true, color: true, pages: 2 },
            5: { paperSize: 'A4', duplex: false, color: true, pages: 3 }
        };

        const config = testConfigs[testNumber];
        if (!config) {
            throw new Error(`Configuration de test inconnue: ${testNumber}`);
        }

        const fileName = `test-${config.paperSize.toLowerCase()}-${testNumber}.pdf`;
        const filePath = path.join(__dirname, 'test-pdfs', fileName);

        return this.printDocument(filePath, {
            paperSize: config.paperSize,
            duplex: config.duplex,
            color: config.color,
            copies: 1
        });
    }
}

module.exports = PrintEngine;
