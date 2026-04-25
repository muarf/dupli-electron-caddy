/**
 * printer-monitor.js (Unifié & Cross-platform)
 *
 * Orchestre la surveillance d'impression sur Windows et Linux.
 * - Windows : Module natif Slim + API PHP.
 * - Linux : spool-analyzer-linux (CUPS) + Ghostscript local.
 * - Analyse : Unifiée avec `sharp` sur les deux OS.
 */

'use strict';

const http = require('http');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { exec } = require('child_process');
const sharp = require('sharp');

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const PHP_API_BASE = 'http://127.0.0.1:8002';
const PHP_TIMEOUT = 10000;
const LOG_PATH = path.join(
    os.platform() === 'win32' ? (process.env.LOCALAPPDATA || 'C:\\') : '/tmp',
    'dupli-electron-caddy', 'logs', 'node_monitor.log'
);

// Dossier pour miniatures temporaires sur Linux
const LINUX_THUMB_DIR = '/tmp/dupli-thumbnails';
if (os.platform() === 'linux' && !fs.existsSync(LINUX_THUMB_DIR)) {
    fs.mkdirSync(LINUX_THUMB_DIR, { recursive: true });
}

function log(msg) {
    const line = `[${new Date().toISOString()}] ${msg}\n`;
    fs.appendFile(LOG_PATH, line, () => {});
}

const analysisCache = new Map();
function makeCacheKey(jobId, documentName) { return `${jobId}|${documentName}`; }

// ---------------------------------------------------------------------------
// Analyse de pixels avec Sharp (Unifiée)
// ---------------------------------------------------------------------------
async function analyzePng(pngPath) {
    const { data, info } = await sharp(pngPath)
        .resize(200, 200, { fit: 'inside', withoutEnlargement: true })
        .removeAlpha()
        .raw()
        .toBuffer({ resolveWithObject: true });

    const { width, height, channels } = info;
    const totalPixels = width * height;
    let filledPixels = 0;
    let coloredPixels = 0;

    for (let i = 0; i < data.length; i += channels) {
        const r = data[i], g = data[i + 1], b = data[i + 2];
        const lum = (r + g + b) / 3;
        if (lum < 210) filledPixels++; // Seuil de remplissage
        
        // Seuil de couleur augmenté de 15 à 25 pour éviter les faux positifs (anti-aliasing, compression)
        if (Math.abs(r - g) > 25 || Math.abs(g - b) > 25 || Math.abs(r - b) > 25) {
            coloredPixels++;
        }
    }

    return { 
        fillRate: (filledPixels / totalPixels) * 100, 
        // Seuil de ratio augmenté de 0.001 (0.1%) à 0.005 (0.5%) pour plus de robustesse
        isColor: (coloredPixels / totalPixels) > 0.005 
    };
}

// ---------------------------------------------------------------------------
// Conversion (Spécifique par OS)
// ---------------------------------------------------------------------------

/**
 * Windows : Utilise l'API PHP locale
 */
async function convertWindows(jobId, format) {
    const endpointMap = { EMF: 'convert_emf_to_png', PCL: 'convert_pcl_to_png', XPS: 'convert_xps_to_png', RAW: 'convert_pcl_to_png', PostScript: 'convert_ps_to_png' };
    const endpoint = endpointMap[format];
    if (!endpoint) return null;

    return new Promise((resolve) => {
        const url = `${PHP_API_BASE}/?${endpoint}&job_id=${jobId}`;
        http.get(url, { timeout: PHP_TIMEOUT }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                try {
                    const data = JSON.parse(body);
                    if (!data.success) return resolve(null);
                    const pages = data.pages || [];
                    resolve({
                        thumbnailUrl: data.base_url ? `${data.base_url}page_0.png` : (pages[0]?.path || ""),
                        pngPaths: pages.map(p => p.path).filter(Boolean),
                        totalPages: data.total_pages || data.page_count || pages.length
                    });
                } catch (e) { resolve(null); }
            });
        }).on('error', () => resolve(null));
    });
}

/**
 * Linux : Utilise Ghostscript (gs) en local
 */
async function convertLinux(jobId, splPath) {
    const jobDir = path.join(LINUX_THUMB_DIR, String(jobId));
    if (!fs.existsSync(jobDir)) fs.mkdirSync(jobDir, { recursive: true });

    const outputPattern = path.join(jobDir, 'page_%d.png');
    // gs -o output_%d.png -sDEVICE=png16m -r72 input
    const cmd = `gs -dNOPAUSE -dBATCH -dSAFER -dQUIET -sDEVICE=png16m -r72 -sOutputFile="${outputPattern}" "${splPath}"`;

    return new Promise((resolve) => {
        exec(cmd, (err) => {
            if (err) return resolve(null);
            const pngFiles = fs.readdirSync(jobDir)
                .filter(f => f.endsWith('.png'))
                .map(f => path.join(jobDir, f))
                .sort();
            
            if (!pngFiles.length) return resolve(null);

            resolve({
                thumbnailUrl: `file://${pngFiles[0]}`, // Note: Electron peut lire file:// si configuré
                pngPaths: pngFiles,
                totalPages: pngFiles.length
            });
        });
    });
}

// ---------------------------------------------------------------------------
// Orchestration Analyse
// ---------------------------------------------------------------------------
async function analyzeJob(ev) {
    const { jobId, documentName, format, splPath, color: driverColor } = ev;
    const cacheKey = makeCacheKey(jobId, documentName);

    // Cache check
    if (analysisCache.has(cacheKey)) {
        const cached = analysisCache.get(cacheKey);
        let currentSize = 0;
        try { currentSize = fs.statSync(splPath).size; } catch {}
        if (currentSize <= cached.splSize && cached.splSize > 0) return cached;
    }

    // Conversion
    let conv = null;
    if (os.platform() === 'win32') {
        conv = await convertWindows(jobId, format);
    } else {
        conv = await convertLinux(jobId, splPath);
    }

    if (!conv || !conv.pngPaths.length) return null;

    // Analyse Sharp : Règle hybride
    // 1. Si le driver dit Noir et Blanc (1), on respecte le choix de l'utilisateur.
    // 2. Si le driver dit Couleur (2), on analyse les pixels pour voir si on peut "rétrograder" en N&B.
    let totalFill = 0;
    let foundRealColor = false;
    
    for (const png of conv.pngPaths) {
        try {
            const { fillRate, isColor } = await analyzePng(png);
            totalFill += fillRate;
            if (isColor) foundRealColor = true;
        } catch (e) {}
    }

    // Le job est gris si (Le driver a été forcé en N&B) OU (L'analyse n'a trouvé aucune couleur)
    const isGrayscale = (driverColor === 1) || !foundRealColor;

    const result = {
        isGrayscale,
        fillRate: totalFill / conv.pngPaths.length,
        thumbnailUrl: conv.thumbnailUrl,
        totalPages: conv.totalPages,
        splSize: fs.statSync(splPath).size,
        documentName
    };

    analysisCache.set(cacheKey, result);
    return result;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------
function startMonitoring(nativeAddon, onJob) {
    if (os.platform() === 'win32' && nativeAddon) {
        nativeAddon.startPrinterMonitor(async (type, raw) => {
            if (type !== 'job') return;
            
            // Envoyer immédiatement les infos de base pour éviter l'impression de lenteur
            onJob({
                ...raw,
                isGrayscale: (raw.color === 1),
                fillRate: 0,
                thumbnailUrl: '',
                isAnalyzing: true
            });

            let analysis = null;
            if (raw.splPath && raw.status !== 'Deleting' && raw.format !== 'unknown') {
                analysis = await analyzeJob(raw).catch(() => null);
            }

            // Renvoyer le job enrichi avec la miniature et l'analyse
            onJob({
                ...raw,
                isGrayscale: analysis?.isGrayscale ?? (raw.color === 1),
                fillRate: analysis?.fillRate ?? 0,
                thumbnailUrl: analysis?.thumbnailUrl ?? '',
                totalPages: analysis?.totalPages || raw.totalPages,
                isAnalyzing: false
            });
        });
        return () => nativeAddon.stopPrinterMonitor();
    } else if (os.platform() === 'linux') {
        try {
            const LinuxAnalyzer = require('./spool-analyzer-linux');
            const la = new LinuxAnalyzer();
            la.on('job', async (job) => {
                // job contient déjà jobId, printerName, documentName, status, totalPages
                // Mais on va tenter d'enrichir avec Sharp si on a le chemin du spool
                const paddedId = job.JobId.toString().padStart(5, '0');
                const splPath = `/var/spool/cups/d${paddedId}-001`;
                
                let analysis = null;
                if (fs.existsSync(splPath)) {
                    analysis = await analyzeJob({
                        jobId: job.JobId,
                        documentName: job.Document,
                        format: 'pdf', // CUPS standard
                        splPath: splPath,
                        color: 2 // default to color check
                    }).catch(() => null);
                }

                onJob({
                    ...job,
                    jobId: job.JobId,
                    isGrayscale: analysis?.isGrayscale ?? job.ColorMode === 'Monochrome',
                    fillRate: analysis?.fillRate ?? job.FillRate,
                    thumbnailUrl: analysis?.thumbnailUrl ?? job.ThumbnailUrl,
                    totalPages: analysis?.totalPages || job.TotalPages
                });
            });
            la.start();
            return () => la.stop();
        } catch (e) { log(`Erreur Linux: ${e.message}`); }
    }
    return () => {};
}

async function reanalyzeJob(jobId, documentName, format, splPath, driverColor) {
    analysisCache.delete(makeCacheKey(jobId, documentName));
    return analyzeJob({ jobId, documentName, format, splPath, color: driverColor });
}

module.exports = { startMonitoring, reanalyzeJob };
