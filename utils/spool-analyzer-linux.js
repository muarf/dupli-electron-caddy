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
            if (err) {
                console.log('[DEBUG pollJobs] lpstat error:', err.message);
                return;
            }

            const lines = stdout.split('\n');
            console.log('[DEBUG pollJobs] lines count:', lines.length);

            for (const line of lines) {
                // Format: "PrinterName-JobId   user   size   date time"
                const match = line.match(/^(\S+)-(\d+)\s+(\S+)\s+\d+/);
                if (!match) continue;

                const printerName = match[1];
                const jobId = parseInt(match[2], 10);
                const user = match[3];

                console.log('[DEBUG pollJobs] found job:', jobId, 'printer:', printerName);

                if (isNaN(jobId) || this.processedJobIds.has(jobId)) {
                    console.log('[DEBUG pollJobs] skipping job', jobId, 'already processed or NaN');
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
        // Formater le numéro de job (ex: 39 -> d00039-001)
        const paddedId = jobId.toString().padStart(5, '0');
        const filename = `d${paddedId}-001`;
        const filePath = path.join(this.spoolDir, filename);

        console.log(`📄 Nouveau job détecté via Polling: #${jobId} (Spool attendu: ${filename})`);

        try {
            // Vérifier que le fichier spool existe bien physiquement (permission de lecture directe)
            try {
                fs.accessSync(filePath, fs.constants.R_OK);
            } catch (err) {
                console.warn(`⚠️ Spool file ${filename} non lisible ou inexistant. Le job est peut-être déjà parti.`);
                return;
            }

            // Analyser le contenu (Ghostscript) pour le taux de remplissage
            const analysis = await this.analyzeContent(filePath);

            // Construire l'événement
            const jobInfo = {
                JobId: jobId,
                PrinterName: printerName || 'Unknown',
                Document: `Job ${jobId} (${user})`, // Difficile d'avoir le vrai titre sans IPP root
                Status: 'Printing',
                TotalPages: analysis.totalPages,
                PaperSize: 'A4',
                IsDuplex: false,
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

                const isColor = (totalC + totalM + totalY) > 0.5;
                // GS ink_cov donne des pourcentages (0-100), moyenne des 4 canaux
                const fillRate = (totalC + totalM + totalY + totalK) / (pages * 4);

                resolve({
                    isColor,
                    fillRate,
                    totalPages: pages
                });
            });
        });
    }
}

module.exports = LinuxSpoolAnalyzer;
