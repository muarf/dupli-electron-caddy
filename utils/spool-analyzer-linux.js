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
        this.watcher = null;
        this.processedFiles = new Set();
    }

    start() {
        if (this.watching) return true;

        try {
            // Vérifier l'accès
            fs.accessSync(this.spoolDir, fs.constants.R_OK);

            console.log(`🚀 Démarrage du moniteur Linux sur ${this.spoolDir}`);

            this.watcher = fs.watch(this.spoolDir, (eventType, filename) => {
                if (filename && filename.startsWith('d') && !this.processedFiles.has(filename)) {
                    // Attendre un peu que le fichier soit écrit
                    setTimeout(() => {
                        this.handleNewJobFile(filename);
                    }, 500);
                }
            });

            this.watching = true;
            return true;
        } catch (e) {
            console.error('❌ Impossible de surveiller le spool CUPS:', e.message);
            return false;
        }
    }

    stop() {
        if (this.watcher) {
            this.watcher.close();
            this.watcher = null;
        }
        this.watching = false;
        this.processedFiles.clear();
    }

    async handleNewJobFile(filename) {
        if (this.processedFiles.has(filename)) return;
        this.processedFiles.add(filename);

        const filePath = path.join(this.spoolDir, filename);
        console.log(`📄 Nouveau fichier spool détecté: ${filename}`);

        try {
            // 1. Extraire le Job ID du nom de fichier (d00123-001 -> 123)
            const jobIdMatch = filename.match(/d(\d+)-/);
            const jobId = jobIdMatch ? parseInt(jobIdMatch[1]) : 0;

            // 2. Récupérer les métadonnées via lpstat
            const metadata = await this.getJobMetadata(jobId);

            // 3. Analyser le contenu (Ghostscript) pour le taux de remplissage
            const analysis = await this.analyzeContent(filePath);

            // 4. Construire l'événement
            const jobInfo = {
                JobId: jobId,
                PrinterName: metadata.printer || 'Unknown',
                Document: metadata.title || `Job ${jobId}`,
                Status: 'Printing', // On suppose qu'il s'imprime
                TotalPages: analysis.totalPages,
                PaperSize: 'A4', // Difficile à deviner sans parser le PS/PDF
                IsDuplex: false, // Difficile à deviner
                ColorMode: analysis.isColor ? 'Color' : 'Monochrome',
                Copies: 1, // CUPS gère les copies, le fichier d contient 1 copie du document
                FillRate: analysis.fillRate,
                ThumbnailUrl: '', // TODO: Générer thumbnail
                TimeSubmitted: new Date().toISOString()
            };

            console.log(`✅ Job Linux analysé: #${jobId} ${jobInfo.Document} (${jobInfo.FillRate.toFixed(2)}%)`);
            this.emit('job', jobInfo);

        } catch (error) {
            console.error(`❌ Erreur analyse job ${filename}:`, error);
        }
    }

    getJobMetadata(jobId) {
        return new Promise((resolve) => {
            // lpstat -W not-completed -o
            exec(`lpstat -l -W not-completed -o`, (err, stdout) => {
                if (err) {
                    resolve({});
                    return;
                }

                // Parser la sortie pour trouver le job ID
                // Format: printer-name-123 user ...
                const lines = stdout.split('\n');
                for (const line of lines) {
                    if (line.includes(`-${jobId} `)) {
                        const parts = line.split(' ');
                        const printer = parts[0].split('-').slice(0, -1).join('-'); // hacky
                        // lpstat output is unstructured, hard to parse accurately without rigorous regex
                        // Fallback: use just printer name from the job identifier
                        const printerName = parts[0].replace(`-${jobId}`, '');
                        resolve({
                            printer: printerName,
                            user: parts[1],
                            title: `Job ${jobId}` // lpstat doesn't always show title cleanly in oneline
                        });
                        return;
                    }
                }
                resolve({});
            });
        });
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
                // 0.00000  0.00000  0.00000  0.00000 CMYK OK
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

                // Calcul basique: si C+M+Y > 0 => Couleur
                const isColor = (totalC + totalM + totalY) > 0.01;

                // Fill rate (moyenne de l'encre totale par page)
                // CMYK coverage is 0.0-1.0 per channel vs page area.
                // Sum of all 4 channels / 4? No, sum is total ink.
                // Usually Fill Rate is max 400%.
                // Let's normalize to percentage 0-100% of surface covered?
                // Or sum of inks? 
                // In Windows version, how was it calculated? 
                // Let's assume average ink coverage per channel.

                // Simple logical approach:
                // Total ink used / Number of pages.
                // 1.0 means full coverage of one channel.
                // We want a percentage. (e.g. 5% text).
                // (C+M+Y+K) / 4 (channels) * 100? No.
                // Just (C+M+Y+K) / Pages * 100 ?
                const avgInkPerPage = (totalC + totalM + totalY + totalK) / pages;
                const fillRate = avgInkPerPage * 100; // 0.05 -> 5%

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
