/**
 * Spool File Analyzer
 * Uses Ghostscript to render spool files to images and analyze pixels for color detection
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Find Ghostscript executable
function getGhostscriptPath() {
    // Try app/ghostscript first (packaged app)
    const appGsPath = path.join(__dirname, '..', 'app', 'ghostscript', 'gswin64c.exe');
    if (fs.existsSync(appGsPath)) {
        return appGsPath;
    }

    // Try ghostscript folder at root
    const rootGsPath = path.join(__dirname, '..', 'ghostscript', 'gswin64c.exe');
    if (fs.existsSync(rootGsPath)) {
        return rootGsPath;
    }

    // Fallback to PATH
    return 'gswin64c.exe';
}

/**
 * Analyze a spool file for color content and fill rate
 * @param {string} spoolFilePath - Path to the SPL file
 * @returns {Promise<{isGrayscale: boolean, fillRate: number, analyzed: boolean}>}
 */
async function analyzeSpoolFile(spoolFilePath) {
    const result = {
        isGrayscale: true,  // Default to grayscale
        fillRate: 0,
        analyzed: false
    };

    try {
        const gsPath = getGhostscriptPath();

        // Create temp directory for rendering
        const tempDir = path.join(os.tmpdir(), 'dupli_spool_analysis_' + Date.now());
        fs.mkdirSync(tempDir, { recursive: true });

        const outputImage = path.join(tempDir, 'page.png');

        // Try to render the spool file to PNG using Ghostscript
        // Ghostscript can handle PostScript, PDF, and many other formats
        try {
            const args = [
                '-dNOPAUSE',
                '-dBATCH',
                '-dSAFER',
                '-sDEVICE=png16m',  // 24-bit color PNG
                '-r72',             // Low resolution for fast analysis
                '-dFirstPage=1',
                '-dLastPage=1',     // Only first page
                '-dTextAlphaBits=1',
                '-dGraphicsAlphaBits=1',
                `-sOutputFile=${outputImage}`,
                spoolFilePath
            ];

            execFileSync(gsPath, args, {
                timeout: 30000,  // 30 second timeout
                windowsHide: true,
                stdio: ['ignore', 'pipe', 'pipe']
            });
        } catch (gsError) {
            // Ghostscript might fail on proprietary formats - that's OK
            console.log(`[SPOOL_ANALYZER] Ghostscript couldn't render file: ${gsError.message}`);

            // Clean up
            try { fs.rmSync(tempDir, { recursive: true }); } catch (e) { }
            return result;
        }

        // Check if image was created
        if (!fs.existsSync(outputImage)) {
            console.log('[SPOOL_ANALYZER] No image generated');
            try { fs.rmSync(tempDir, { recursive: true }); } catch (e) { }
            return result;
        }

        // Analyze the PNG for color content
        const imageBuffer = fs.readFileSync(outputImage);
        const analysis = analyzeImagePixels(imageBuffer);

        result.isGrayscale = analysis.isGrayscale;
        result.fillRate = analysis.fillRate;
        result.analyzed = true;

        console.log(`[SPOOL_ANALYZER] Analysis complete: isGrayscale=${result.isGrayscale}, fillRate=${result.fillRate.toFixed(1)}%`);

        // Clean up
        try { fs.rmSync(tempDir, { recursive: true }); } catch (e) { }

    } catch (error) {
        console.error('[SPOOL_ANALYZER] Error:', error.message);
    }

    return result;
}

/**
 * Analyze PNG image pixels for color content and fill rate
 * Simple PNG parser - reads raw pixel data
 * @param {Buffer} pngBuffer
 * @returns {{isGrayscale: boolean, fillRate: number}}
 */
function analyzeImagePixels(pngBuffer) {
    // For simplicity, we'll use a basic approach:
    // Read the PNG and look for color patterns in the raw data

    let hasColor = false;
    let nonWhitePixels = 0;
    let totalPixels = 0;

    // PNG signature check
    if (pngBuffer[0] !== 0x89 || pngBuffer[1] !== 0x50) {
        return { isGrayscale: true, fillRate: 0 };
    }

    // Simple heuristic: scan for RGB triplets in the data
    // Look at IDAT chunks (after decompression this would have pixel data)
    // For speed, we'll sample the raw buffer for patterns

    // Find IDAT chunk
    let offset = 8; // Skip PNG signature
    let idatData = Buffer.alloc(0);

    while (offset < pngBuffer.length - 8) {
        const chunkLength = pngBuffer.readUInt32BE(offset);
        const chunkType = pngBuffer.toString('ascii', offset + 4, offset + 8);

        if (chunkType === 'IDAT') {
            const chunkData = pngBuffer.slice(offset + 8, offset + 8 + chunkLength);
            idatData = Buffer.concat([idatData, chunkData]);
        }

        if (chunkType === 'IEND') break;

        offset += 12 + chunkLength; // length(4) + type(4) + data + crc(4)
    }

    // Decompress IDAT data
    try {
        const zlib = require('zlib');
        const decompressed = zlib.inflateSync(idatData);

        // Analyze decompressed pixel data
        // Format is: filter_byte R G B R G B ... per scanline
        // We'll sample every Nth pixel for speed

        const sampleRate = 10; // Sample every 10th pixel
        let pixelIndex = 0;

        for (let i = 0; i < decompressed.length - 3; i++) {
            // Skip filter bytes (first byte of each scanline)
            // Assuming RGB (3 bytes per pixel) + filter byte

            const r = decompressed[i];
            const g = decompressed[i + 1];
            const b = decompressed[i + 2];

            pixelIndex++;

            if (pixelIndex % sampleRate !== 0) continue;

            totalPixels++;

            // Check if pixel is not white (255, 255, 255)
            if (r < 250 || g < 250 || b < 250) {
                nonWhitePixels++;
            }

            // Check if pixel is color (R != G or G != B with significant difference)
            if (Math.abs(r - g) > 10 || Math.abs(g - b) > 10 || Math.abs(r - b) > 10) {
                hasColor = true;
            }
        }

    } catch (zlibError) {
        console.log('[SPOOL_ANALYZER] Could not decompress PNG data');
        return { isGrayscale: true, fillRate: 0 };
    }

    const fillRate = totalPixels > 0 ? (nonWhitePixels / totalPixels) * 100 : 0;

    return {
        isGrayscale: !hasColor,
        fillRate: Math.min(fillRate, 100)
    };
}

/**
 * Find and analyze the most recent spool file
 * @returns {Promise<{isGrayscale: boolean, fillRate: number, analyzed: boolean}>}
 */
async function analyzeLatestSpoolFile() {
    const result = {
        isGrayscale: true,
        fillRate: 0,
        analyzed: false
    };

    try {
        // Windows spool directory
        const spoolDir = path.join(process.env.SystemRoot || 'C:\\Windows', 'System32', 'spool', 'PRINTERS');

        if (!fs.existsSync(spoolDir)) {
            return result;
        }

        // Find SPL files
        const files = fs.readdirSync(spoolDir)
            .filter(f => f.endsWith('.SPL'))
            .map(f => ({
                name: f,
                path: path.join(spoolDir, f),
                mtime: fs.statSync(path.join(spoolDir, f)).mtime
            }))
            .sort((a, b) => b.mtime - a.mtime); // Most recent first

        if (files.length === 0) {
            return result;
        }

        // Try to copy and analyze the most recent file
        for (const file of files.slice(0, 3)) { // Try up to 3 most recent files
            try {
                // Copy to temp (file might be locked)
                const tempCopy = path.join(os.tmpdir(), `spool_copy_${Date.now()}.spl`);
                fs.copyFileSync(file.path, tempCopy);

                const analysis = await analyzeSpoolFile(tempCopy);

                // Clean up copy
                try { fs.unlinkSync(tempCopy); } catch (e) { }

                if (analysis.analyzed) {
                    return analysis;
                }
            } catch (copyError) {
                // File locked, try next one
                console.log(`[SPOOL_ANALYZER] Couldn't copy ${file.name}: ${copyError.message}`);
            }
        }

    } catch (error) {
        console.error('[SPOOL_ANALYZER] Error finding spool files:', error.message);
    }

    return result;
}

module.exports = {
    analyzeSpoolFile,
    analyzeLatestSpoolFile,
    getGhostscriptPath
};
