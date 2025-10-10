/**
 * Riso Tools - Bibliothèque JavaScript pour séparation de couleurs Riso
 * Compatible navigateur et Electron
 * Inspiré de p5.riso et risoAtHome
 */

// ===== COULEURS RISO =====
const RISO_COLORS = {
    'black': { hex: '#000000', name: 'Noir' },
    'red': { hex: '#FF5C5C', name: 'Rouge' },
    'blue': { hex: '#0078BF', name: 'Bleu' },
    'yellow': { hex: '#FFD800', name: 'Jaune' },
    'green': { hex: '#00A95C', name: 'Vert' },
    'violet': { hex: '#765BA7', name: 'Violet' },
    'burgundy': { hex: '#914E72', name: 'Bordeaux' },
    'teal': { hex: '#00838A', name: 'Sarcelle' },
    'brightred': { hex: '#F15060', name: 'Rouge vif' },
    'orange': { hex: '#FF6C2F', name: 'Orange' },
    'fluorpink': { hex: '#FF48B0', name: 'Rose fluo' },
    'fluororange': { hex: '#FF7477', name: 'Orange fluo' },
    'fluorgreen': { hex: '#00D47E', name: 'Vert fluo' },
    'fluorblue': { hex: '#009FE6', name: 'Bleu fluo' }
};

// ===== UTILITAIRES =====

/**
 * Convertir hex en RGB
 */
function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : {r: 0, g: 0, b: 0};
}

/**
 * Convertir RGB vers CMYK
 */
function rgbToCmyk(r, g, b) {
    let c = 1 - (r / 255);
    let m = 1 - (g / 255);
    let y = 1 - (b / 255);
    let k = Math.min(c, m, y);
    
    if (k === 1) {
        return {c: 0, m: 0, y: 0, k: 1};
    }
    
    c = (c - k) / (1 - k);
    m = (m - k) / (1 - k);
    y = (y - k) / (1 - k);
    
    return {c, m, y, k};
}

// ===== SÉPARATION DE CANAUX =====

/**
 * Extraire les canaux RGB d'une image
 * Retourne 3 ImageData en niveaux de gris
 */
function extractRGBChannels(img) {
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    // Créer 3 ImageData pour les canaux
    const redData = ctx.createImageData(canvas.width, canvas.height);
    const greenData = ctx.createImageData(canvas.width, canvas.height);
    const blueData = ctx.createImageData(canvas.width, canvas.height);

    // Séparer les canaux en niveaux de gris
    for (let i = 0; i < data.length; i += 4) {
        // Canal rouge
        redData.data[i] = data[i];
        redData.data[i+1] = data[i];
        redData.data[i+2] = data[i];
        redData.data[i+3] = 255;

        // Canal vert
        greenData.data[i] = data[i+1];
        greenData.data[i+1] = data[i+1];
        greenData.data[i+2] = data[i+1];
        greenData.data[i+3] = 255;

        // Canal bleu
        blueData.data[i] = data[i+2];
        blueData.data[i+1] = data[i+2];
        blueData.data[i+2] = data[i+2];
        blueData.data[i+3] = 255;
    }

    return {
        red: redData,
        green: greenData,
        blue: blueData
    };
}

/**
 * Extraire les canaux CMYK d'une image
 * Retourne 4 ImageData en niveaux de gris
 */
function extractCMYKChannels(img) {
    const canvas = document.createElement('canvas');
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    // Créer 4 ImageData pour les canaux
    const cyanData = ctx.createImageData(canvas.width, canvas.height);
    const magentaData = ctx.createImageData(canvas.width, canvas.height);
    const yellowData = ctx.createImageData(canvas.width, canvas.height);
    const blackData = ctx.createImageData(canvas.width, canvas.height);

    // Séparer en CMYK
    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i+1];
        const b = data[i+2];
        
        const cmyk = rgbToCmyk(r, g, b);
        
        // Convertir les valeurs 0-1 en 0-255 (inversé pour affichage)
        const cVal = Math.round((1 - cmyk.c) * 255);
        const mVal = Math.round((1 - cmyk.m) * 255);
        const yVal = Math.round((1 - cmyk.y) * 255);
        const kVal = Math.round((1 - cmyk.k) * 255);

        // Canal Cyan
        cyanData.data[i] = cVal;
        cyanData.data[i+1] = cVal;
        cyanData.data[i+2] = cVal;
        cyanData.data[i+3] = 255;

        // Canal Magenta
        magentaData.data[i] = mVal;
        magentaData.data[i+1] = mVal;
        magentaData.data[i+2] = mVal;
        magentaData.data[i+3] = 255;

        // Canal Yellow
        yellowData.data[i] = yVal;
        yellowData.data[i+1] = yVal;
        yellowData.data[i+2] = yVal;
        yellowData.data[i+3] = 255;

        // Canal Black
        blackData.data[i] = kVal;
        blackData.data[i+1] = kVal;
        blackData.data[i+2] = kVal;
        blackData.data[i+3] = 255;
    }

    return {
        cyan: cyanData,
        magenta: magentaData,
        yellow: yellowData,
        black: blackData
    };
}

// ===== ISOLATION DE COULEUR (PIPETTE) =====

/**
 * Isoler une couleur spécifique avec tolérance (Magic Wand)
 * Sélectionne automatiquement les couleurs dominantes (texte + fond)
 */
function isolateColor(imageData, targetR, targetG, targetB, tolerance = 30) {
    const width = imageData.width;
    const height = imageData.height;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    
    const result = ctx.createImageData(width, height);
    const data = imageData.data;
    
    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i+1];
        const b = data[i+2];
        
        // Calculer la distance avec la couleur cible
        const distance = Math.sqrt(
            Math.pow(r - targetR, 2) + 
            Math.pow(g - targetG, 2) + 
            Math.pow(b - targetB, 2)
        );
        
        if (distance <= tolerance) {
            // Convertir en niveaux de gris
            const gray = Math.round((r + g + b) / 3);
            result.data[i] = gray;
            result.data[i+1] = gray;
            result.data[i+2] = gray;
            result.data[i+3] = 255;
        } else {
            // Pixel transparent (non sélectionné)
            result.data[i] = 255;
            result.data[i+1] = 255;
            result.data[i+2] = 255;
            result.data[i+3] = 0;
        }
    }
    return result;
}

/**
 * Obtenir la couleur d'un pixel aux coordonnées x,y
 */
function getPixelColor(canvas, x, y) {
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(x, y, 1, 1);
    return {
        r: imageData.data[0],
        g: imageData.data[1],
        b: imageData.data[2]
    };
}

// ===== POSTÉRISATION =====

/**
 * Postériser une image (réduire les niveaux de gris)
 * levels: nombre de niveaux (2-10 typiquement)
 */
function posterizeImage(imageData, levels = 4) {
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        imageData.width,
        imageData.height
    );
    
    const step = 255 / (levels - 1);
    
    for (let i = 0; i < result.data.length; i += 4) {
        const gray = (result.data[i] + result.data[i+1] + result.data[i+2]) / 3;
        const posterized = Math.round(gray / step) * step;
        result.data[i] = posterized;
        result.data[i+1] = posterized;
        result.data[i+2] = posterized;
    }
    
    return result;
}

/**
 * Diviser une image N&B en 2 couches (tons clairs + tons foncés)
 * Pour impression 2 couleurs
 */
function splitGrayscaleInTwo(imageData, threshold = 128) {
    const width = imageData.width;
    const height = imageData.height;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    
    const light = ctx.createImageData(width, height);
    const dark = ctx.createImageData(width, height);
    const data = imageData.data;
    
    for (let i = 0; i < data.length; i += 4) {
        const gray = (data[i] + data[i+1] + data[i+2]) / 3;
        
        if (gray > threshold) {
            // Tons clairs
            light.data[i] = gray;
            light.data[i+1] = gray;
            light.data[i+2] = gray;
            light.data[i+3] = 255;
            
            dark.data[i] = 255;
            dark.data[i+1] = 255;
            dark.data[i+2] = 255;
            dark.data[i+3] = 0;
        } else {
            // Tons foncés
            dark.data[i] = gray;
            dark.data[i+1] = gray;
            dark.data[i+2] = gray;
            dark.data[i+3] = 255;
            
            light.data[i] = 255;
            light.data[i+1] = 255;
            light.data[i+2] = 255;
            light.data[i+3] = 0;
        }
    }
    
    return {light, dark};
}

// ===== HALFTONE / TRAMES =====

/**
 * Appliquer un effet halftone (trame de points) à une image
 * Utilise un tramage ordonné avec matrice de seuil pour un rendu professionnel
 */
function applyHalftone(imageData, dotSize = 4, angle = 45) {
    const width = imageData.width;
    const height = imageData.height;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    
    const result = ctx.createImageData(width, height);
    
    // Remplir de blanc
    for (let i = 0; i < result.data.length; i += 4) {
        result.data[i] = 255;
        result.data[i+1] = 255;
        result.data[i+2] = 255;
        result.data[i+3] = 255;
    }
    
    // Matrice de seuil pour tramage ordonné (Bayer matrix 8x8)
    const bayerMatrix = [
        [ 0, 32,  8, 40,  2, 34, 10, 42],
        [48, 16, 56, 24, 50, 18, 58, 26],
        [12, 44,  4, 36, 14, 46,  6, 38],
        [60, 28, 52, 20, 62, 30, 54, 22],
        [ 3, 35, 11, 43,  1, 33,  9, 41],
        [51, 19, 59, 27, 49, 17, 57, 25],
        [15, 47,  7, 39, 13, 45,  5, 37],
        [63, 31, 55, 23, 61, 29, 53, 21]
    ];
    
    const matrixSize = 8;
    const angleRad = angle * Math.PI / 180;
    const cosAngle = Math.cos(angleRad);
    const sinAngle = Math.sin(angleRad);
    
    // dotSize contrôle l'espacement de la grille (1-10)
    // Plus dotSize est petit, plus la trame est fine (plus de points)
    // La taille des cellules de la grille
    const cellSize = dotSize;
    
    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = (y * width + x) * 4;
            
            // Récupérer la valeur de gris du pixel
            const alpha = imageData.data[i+3] / 255;
            let gray;
            
            if (alpha === 0) {
                // Pixel transparent = blanc
                gray = 255;
            } else {
                gray = (imageData.data[i] + imageData.data[i+1] + imageData.data[i+2]) / 3;
            }
            
            // Appliquer la rotation pour éviter le moiré
            const rotX = x * cosAngle - y * sinAngle;
            const rotY = x * sinAngle + y * cosAngle;
            
            // Position dans la grille de tramage
            const cellX = Math.floor(rotX / cellSize);
            const cellY = Math.floor(rotY / cellSize);
            
            // Position relative dans la cellule (0 à cellSize)
            const localX = rotX - cellX * cellSize;
            const localY = rotY - cellY * cellSize;
            
            // Centre de la cellule
            const centerX = cellSize / 2;
            const centerY = cellSize / 2;
            
            // Distance au centre de la cellule
            const dx = localX - centerX;
            const dy = localY - centerY;
            const distanceToCenter = Math.sqrt(dx * dx + dy * dy);
            
            // Rayon du point basé sur l'intensité (inversé: noir = gros point)
            const intensity = 1 - (gray / 255);
            // Le rayon maximum est 100% de la taille de la cellule pour que les points se touchent complètement
            const maxRadius = cellSize * 0.5; // Rayon = moitié de la cellule
            const dotRadius = intensity * maxRadius;
            
            // Décision: point noir ou blanc SANS anti-aliasing pour garder le noir franc
            let pixelValue;
            if (distanceToCenter <= dotRadius) {
                // Noir franc - pas d'anti-aliasing
                pixelValue = 0;
            } else {
                // Blanc franc
                pixelValue = 255;
            }
            
            result.data[i] = pixelValue;
            result.data[i+1] = pixelValue;
            result.data[i+2] = pixelValue;
            result.data[i+3] = 255;
        }
    }
    
    return result;
}

/**
 * Appliquer un dithering (tramage) - Floyd-Steinberg
 */
function applyDithering(imageData, algorithm = 'floydsteinberg') {
    const width = imageData.width;
    const height = imageData.height;
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        width,
        height
    );
    
    if (algorithm === 'floydsteinberg') {
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const i = (y * width + x) * 4;
                const oldPixel = result.data[i];
                const newPixel = oldPixel < 128 ? 0 : 255;
                const error = oldPixel - newPixel;
                
                result.data[i] = newPixel;
                result.data[i+1] = newPixel;
                result.data[i+2] = newPixel;
                
                // Diffuser l'erreur
                if (x + 1 < width) {
                    const idx = i + 4;
                    result.data[idx] += error * 7/16;
                }
                if (x - 1 >= 0 && y + 1 < height) {
                    const idx = ((y + 1) * width + (x - 1)) * 4;
                    result.data[idx] += error * 3/16;
                }
                if (y + 1 < height) {
                    const idx = ((y + 1) * width + x) * 4;
                    result.data[idx] += error * 5/16;
                }
                if (x + 1 < width && y + 1 < height) {
                    const idx = ((y + 1) * width + (x + 1)) * 4;
                    result.data[idx] += error * 1/16;
                }
            }
        }
    } else if (algorithm === 'threshold') {
        // Simple threshold
        for (let i = 0; i < result.data.length; i += 4) {
            const gray = (result.data[i] + result.data[i+1] + result.data[i+2]) / 3;
            const val = gray < 128 ? 0 : 255;
            result.data[i] = val;
            result.data[i+1] = val;
            result.data[i+2] = val;
        }
    }
    
    return result;
}

// ===== COLORISATION =====

/**
 * Coloriser une image en niveaux de gris avec une couleur Riso
 */
function colorizeWithRiso(imageData, risoColorHex, opacity = 1.0) {
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        imageData.width,
        imageData.height
    );
    
    const rgb = hexToRgb(risoColorHex);
    
    for (let i = 0; i < result.data.length; i += 4) {
        const intensity = result.data[i] / 255; // Utiliser le niveau de gris comme intensité
        result.data[i] = rgb.r * intensity;
        result.data[i+1] = rgb.g * intensity;
        result.data[i+2] = rgb.b * intensity;
        result.data[i+3] = 255 * opacity;
    }
    
    return result;
}

// ===== SUPERPOSITION / BLEND =====

/**
 * Superposer plusieurs couches avec blend mode multiply
 */
function blendLayers(layers, width, height) {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    
    // Fond blanc
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, width, height);
    
    // Appliquer chaque couche
    layers.forEach(layer => {
        if (!layer.imageData || layer.opacity === 0) return;
        
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = width;
        tempCanvas.height = height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.putImageData(layer.imageData, 0, 0);
        
        ctx.globalCompositeOperation = 'multiply';
        ctx.globalAlpha = layer.opacity;
        ctx.drawImage(tempCanvas, 0, 0);
    });
    
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = 1;
    
    return ctx.getImageData(0, 0, width, height);
}

// ===== EXPORT =====

/**
 * Exporter un ImageData en PNG
 */
function exportImageData(imageData, filename) {
    const canvas = document.createElement('canvas');
    canvas.width = imageData.width;
    canvas.height = imageData.height;
    const ctx = canvas.getContext('2d');
    ctx.putImageData(imageData, 0, 0);
    
    canvas.toBlob(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}

/**
 * Créer un ZIP avec plusieurs couches
 */
async function exportLayersAsZip(layers, baseFilename = 'riso_layers') {
    if (typeof JSZip === 'undefined') {
        throw new Error('JSZip non disponible');
    }
    
    const zip = new JSZip();
    
    for (const layer of layers) {
        if (!layer.imageData || !layer.name) continue;
        
        const canvas = document.createElement('canvas');
        canvas.width = layer.imageData.width;
        canvas.height = layer.imageData.height;
        const ctx = canvas.getContext('2d');
        ctx.putImageData(layer.imageData, 0, 0);
        
        const blob = await new Promise(resolve => canvas.toBlob(resolve));
        zip.file(layer.name + '.png', blob);
    }
    
    const content = await zip.generateAsync({type: 'blob'});
    const url = URL.createObjectURL(content);
    const a = document.createElement('a');
    a.href = url;
    a.download = baseFilename + '.zip';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ===== CONVERSION GRAYSCALE =====

/**
 * Convertir une image couleur en niveaux de gris
 */
function toGrayscale(imageData) {
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        imageData.width,
        imageData.height
    );
    
    for (let i = 0; i < result.data.length; i += 4) {
        const gray = Math.round(
            result.data[i] * 0.299 + 
            result.data[i+1] * 0.587 + 
            result.data[i+2] * 0.114
        );
        result.data[i] = gray;
        result.data[i+1] = gray;
        result.data[i+2] = gray;
    }
    
    return result;
}

// ===== AJUSTEMENTS =====

/**
 * Ajuster le contraste d'une image
 */
function adjustContrast(imageData, contrast = 1.5) {
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        imageData.width,
        imageData.height
    );
    
    const factor = (259 * (contrast + 255)) / (255 * (259 - contrast));
    
    for (let i = 0; i < result.data.length; i += 4) {
        result.data[i] = Math.min(255, Math.max(0, factor * (result.data[i] - 128) + 128));
        result.data[i+1] = Math.min(255, Math.max(0, factor * (result.data[i+1] - 128) + 128));
        result.data[i+2] = Math.min(255, Math.max(0, factor * (result.data[i+2] - 128) + 128));
    }
    
    return result;
}

/**
 * Ajuster la luminosité
 */
function adjustBrightness(imageData, brightness = 20) {
    const result = new ImageData(
        new Uint8ClampedArray(imageData.data),
        imageData.width,
        imageData.height
    );
    
    for (let i = 0; i < result.data.length; i += 4) {
        result.data[i] = Math.min(255, Math.max(0, result.data[i] + brightness));
        result.data[i+1] = Math.min(255, Math.max(0, result.data[i+1] + brightness));
        result.data[i+2] = Math.min(255, Math.max(0, result.data[i+2] + brightness));
    }
    
    return result;
}

// ===== EXPORT DES FONCTIONS =====
if (typeof module !== 'undefined' && module.exports) {
    // Node.js / Electron
    module.exports = {
        RISO_COLORS,
        extractRGBChannels,
        extractCMYKChannels,
        isolateColor,
        getPixelColor,
        posterizeImage,
        splitGrayscaleInTwo,
        applyHalftone,
        applyDithering,
        colorizeWithRiso,
        blendLayers,
        exportImageData,
        exportLayersAsZip,
        toGrayscale,
        adjustContrast,
        adjustBrightness,
        hexToRgb,
        rgbToCmyk
    };
}
