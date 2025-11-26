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

# Surveiller les nouveaux jobs
$newJobQuery = "SELECT * FROM __InstanceCreationEvent WITHIN 2 WHERE TargetInstance ISA 'Win32_PrintJob'"
$newJobWatcher = Register-WmiEvent -Query $newJobQuery -Action {
    try {
        $job = $Event.SourceEventArgs.NewEvent.TargetInstance
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }
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
        }
        $json = $jobInfo | ConvertTo-Json -Compress
        Write-Output "NEW_PRINT_JOB:$json"
    } catch {
        # Ignorer les erreurs silencieusement
    }
} -ErrorAction SilentlyContinue

# Surveiller les modifications de jobs (changement de statut)
$modifyJobQuery = "SELECT * FROM __InstanceModificationEvent WITHIN 2 WHERE TargetInstance ISA 'Win32_PrintJob'"
$modifyJobWatcher = Register-WmiEvent -Query $modifyJobQuery -Action {
    try {
        $job = $Event.SourceEventArgs.NewEvent.TargetInstance
        $printerName = $job.Name
        if ($printerName -match ',') {
            $printerName = $printerName.Split(',')[0].Trim()
        }
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
        }
        $json = $jobInfo | ConvertTo-Json -Compress
        Write-Output "MODIFY_PRINT_JOB:$json"
    } catch {
        # Ignorer les erreurs silencieusement
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
                    }
                    $json = $jobInfo | ConvertTo-Json -Compress
                    Write-Output "ACTIVE_JOB:$json"
                    $processedJobs[$jobKey] = $status
                    Write-Output "DEBUG: Job $($job.JobId) traite - $($job.Document) (Status: $status)"
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
            // Log toutes les sorties pour debug (limitées pour ne pas polluer)
            if (output.trim() && (output.includes('ACTIVE_JOB') || output.includes('NEW_PRINT_JOB') || output.includes('DEBUG'))) {
                console.log('📥 Sortie PowerShell:', output.substring(0, 300));
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
                            this.processPrintJob(eventType, jobInfo);
                        } catch (error) {
                            console.error('❌ Erreur parsing JSON:', error.message);
                            console.error('   Données:', jsonData.substring(0, 200));
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

        // Préparer les données pour l'API PHP
        const printData = {
            jobId: String(jobInfo.JobId || jobInfo.JobID || ''),
            document: String(jobInfo.Document || ''),
            owner: String(jobInfo.Owner || ''),
            printerName: printerName,
            status: String(jobInfo.Status || 'Unknown'),
            pagesPrinted: parseInt(jobInfo.PagesPrinted || jobInfo.Pages || 0),
            totalPages: parseInt(jobInfo.TotalPages || jobInfo.Total || 0),
            timeSubmitted: String(jobInfo.TimeSubmitted || ''),
            size: parseInt(jobInfo.Size || 0),
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

