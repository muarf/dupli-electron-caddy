/**
 * Analyseur de spool pour Linux (CUPS)
 * Surveille /var/spool/cups et analyse les fichiers avec Ghostscript
 */

const fs = require('fs');
const path = require('path');
const { spawn, exec } = require('child_process');
const EventEmitter = require('events');

class LinuxSpoolAnalyzer extends EventEmitter {
    constructor() {
        super();
        this.spoolDir = '/var/spool/cups';
        this.watching = false;
        this.pollInterval = null;
        this.processedJobIds = new Set();
    }

    start() {
        if (this.watching) return true;

        try {
            // Vérifier l'accès en traversée (X_OK) sur le dossier (ex: usager dans le groupe lp)
            fs.accessSync(this.spoolDir, fs.constants.X_OK);

            console.log(`🚀 Démarrage du moniteur Linux (Polling) sur ${this.spoolDir}`);

            // Intervalle de polling toutes les 5 secondes
            this.pollInterval = setInterval(() => {
                this.pollJobs();
            }, 5000);

            // Premier passage immédiat
            this.pollJobs();

            this.watching = true;
            return true;
        } catch (e) {
            console.error('❌ Impossible d\'accéder à /var/spool/cups (droits insuffisants):', e.message);
            return false;
        }
    }

    stop() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        this.watching = false;
        this.processedJobIds.clear();
    }

    pollJobs() {
        // FIXE RACE CONDITION: Utiliser -W all pour voir aussi les jobs déjà complétés.
        exec(`lpstat -W all -o`, (err, stdout) => {
            if (err) return;

            const lines = stdout.split('\n');

            for (const line of lines) {
                // Format: "PrinterName-JobId   user   size   date time"
                const match = line.match(/^(\S+)-(\d+)\s+(\S+)\s+\d+/);
                if (!match) continue;

                const printerName = match[1];
                const jobId = parseInt(match[2], 10);
                const user = match[3];

                if (isNaN(jobId) || this.processedJobIds.has(jobId)) {
                    continue;
                }

                // Filtrer sur la date du job pour ignorer les anciens
                // Extraire la partie date/heure de la ligne lpstat
                const dateMatch = line.match(/\d{4}\s+(\w+\.\s+\d+\s+\w+\.\s+\d{4}\s+\d{2}:\d{2}:\d{2}|\w+\.\s+\d+\s+\w+\.\s+\d{4}\s+\d{2}:\d{2})/);
                // On ne peut pas parser facilement la date locale, donc on utilise
                // une heuristique : skip si le pool est plein de vieux jobs au démarrage.
                // On marque les jobs existants au 1er poll sans les notifier.
                if (!this.initialScanDone) {
                    // Au 1er passage, juste marquer comme vus sans notifier
                    this.processedJobIds.add(jobId);
                    continue;
                }

                // Nouveau job détecté après le scan initial !
                this.processedJobIds.add(jobId);
                console.log(`📄 Nouveau job détecté via Polling: #${jobId} sur ${printerName}`);

                setTimeout(() => {
                    this.analyzeNewJob(jobId, printerName, user);
                }, 500); // Réduit à 500ms (le fichier spool est souvent déjà écrit)
            }

            // Marquer le scan initial comme terminé après le 1er poll
            if (!this.initialScanDone) {
                this.initialScanDone = true;
                console.log(`✅ Scan initial terminé: ${this.processedJobIds.size} job(s) existants ignorés`);
            }
        });
    }

    async analyzeNewJob(jobId, printerName, user) {
        const paddedId = jobId.toString().padStart(5, '0');
        let filename = `d${paddedId}-001`;
        let filePath = path.join(this.spoolDir, filename);

        // 1. Notification immédiate au statut "Spooling"
        const initialJobInfo = {
            JobId: jobId,
            PrinterName: printerName || 'Unknown',
            Document: `Job ${jobId} (${user})`,
            Status: 'Spooling',
            TotalPages: 0,
            IsDuplex: false,
            ColorMode: 'Unknown',
            FillRate: 0,
            TimeSubmitted: new Date().toISOString()
        };
        this.emit('job', initialJobInfo);

        // 2. Attente de stabilité du fichier spool
        let fileSize = 0;
        let lastSize = -1;
        let attempts = 0;
        const maxAttempts = 15; // 15 secondes max d'attente

        while (attempts < maxAttempts) {
            if (fs.existsSync(filePath)) {
                fileSize = fs.statSync(filePath).size;
            } else {
                // Essayer le fallback sans -001
                const fallback = path.join(this.spoolDir, `d${paddedId}`);
                if (fs.existsSync(fallback)) {
                    filePath = fallback;
                    filename = `d${paddedId}`;
                    fileSize = fs.statSync(filePath).size;
                }
            }

            // Si le fichier existe et que sa taille est stable, on sort
            if (fileSize > 0 && fileSize === lastSize) {
                break;
            }

            lastSize = fileSize;
            attempts++;
            await new Promise(r => setTimeout(r, 1000)); // Attendre 1s entre chaque vérification
        }

        if (fileSize === 0) {
            console.warn(`⚠️ Spool file ${filename} reste vide ou introuvable après ${maxAttempts}s.`);
            return;
        }

        // Tentative de récupération des métadonnées via IPP
        const ippData = await this.fetchIppAttributes(jobId);

        try {
            // Vérifier que le fichier spool existe bien physiquement (permission de lecture directe)
            try {
                fs.accessSync(filePath, fs.constants.R_OK);
            } catch (err) {
                console.warn(`⚠️ Spool file ${filename} non lisible ou inexistant. Le job est peut-être déjà parti.`);
                return;
            }

            // Analyser le contenu (Ghostscript) pour le taux de remplissage
            const tmpCopy = path.join('/tmp', `analyze_job_${jobId}.pdf`);
            fs.copyFileSync(filePath, tmpCopy);

            const analysis = await this.analyzeContent(tmpCopy);

            // Nettoyage
            try { fs.unlinkSync(tmpCopy); } catch (e) {}

            // Construire l'événement
            const jobInfo = {
                JobId: jobId,
                PrinterName: printerName || 'Unknown',
                Document: ippData.documentName || `Job ${jobId} (${user})`,
                Status: 'Printing',
                TotalPages: analysis.totalPages,
                PaperSize: 'A4',
                IsDuplex: ippData.isDuplex,
                ColorMode: analysis.isColor ? 'Color' : 'Monochrome',
                Copies: 1,
                FillRate: analysis.fillRate,
                ThumbnailUrl: '',
                TimeSubmitted: new Date().toISOString()
            };

            console.log(`✅ Job Linux (Polling) analysé: #${jobId} (${jobInfo.FillRate.toFixed(2)}%)`);
            this.emit('job', jobInfo);

        } catch (error) {
            console.error(`❌ Erreur analyse job #${jobId}:`, error);
        }
    }

    analyzeContent(filePath) {
        return new Promise((resolve, reject) => {
            // Utiliser la méthode ink_cov de Ghostscript
            const gs = spawn('gs', [
                '-o', '-',
                '-sDEVICE=ink_cov',
                filePath
            ]);

            let output = '';
            gs.stdout.on('data', data => output += data.toString());

            gs.on('close', code => {
                if (code !== 0) {
                    console.warn('Ghostscript analysis failed, assuming grayscale 0%');
                    resolve({ isColor: false, fillRate: 0, totalPages: 1 });
                    return;
                }

                // Parser la sortie CMYK
                const lines = output.split('\n').filter(l => l.trim().match(/^\s*\d+\.\d+/));
                let totalC = 0, totalM = 0, totalY = 0, totalK = 0;
                let pages = 0;

                for (const line of lines) {
                    const parts = line.trim().split(/\s+/).map(parseFloat);
                    if (parts.length >= 4) {
                        totalC += parts[0];
                        totalM += parts[1];
                        totalY += parts[2];
                        totalK += parts[3];
                        pages++;
                    }
                }

                if (pages === 0) pages = 1;

                // Règle de saturation améliorée (ignorer Rich Black)
                const avgC = totalC / pages;
                const avgM = totalM / pages;
                const avgY = totalY / pages;
                const maxDiff = Math.max(
                    Math.abs(avgC - avgM),
                    Math.abs(avgM - avgY),
                    Math.abs(avgC - avgY)
                );

                const isColor = (avgC + avgM + avgY > 2.0) && (maxDiff > 1.0);
                const fillRate = (totalC + totalM + totalY + totalK) / (pages * 4);

                resolve({
                    isColor,
                    fillRate,
                    totalPages: pages
                });
            });
        });
    }

    /**
     * Interroge CUPS via IPP pour obtenir les attributs détaillés
     */
    fetchIppAttributes(jobId) {
        return new Promise((resolve) => {
            const cmd = `ipptool -tv ipp://localhost:631/jobs/${jobId} /usr/share/cups/ipptool/get-job-attributes.test`;
            exec(cmd, (err, stdout) => {
                const data = {
                    isDuplex: false,
                    documentName: null
                };

                if (!err && stdout) {
                    // Analyse du Recto-Verso
                    if (stdout.includes('sides (keyword) = two-sided')) {
                        data.isDuplex = true;
                    }
                    
                    // Analyse du nom du document
                    const nameMatch = stdout.match(/job-name \(nameWithoutLanguage\) = (.*)/);
                    if (nameMatch && nameMatch[1].trim()) {
                        data.documentName = nameMatch[1].trim();
                    }
                }

                resolve(data);
            });
        });
    }
}

module.exports = LinuxSpoolAnalyzer;
