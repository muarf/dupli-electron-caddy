const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

async function convert() {
    const rootIconPng = path.join(__dirname, '..', 'icons', 'icon.png');
    const rootIconIco = path.join(__dirname, '..', 'icons', 'icon.ico');
    const tauriIconIco = path.join(__dirname, '..', 'src-tauri', 'icons', 'icon.ico');
    const tempPng = path.join(__dirname, '..', 'icons', 'icon_temp_8bit.png');

    console.log("Loading PNG and forcing 8-bit channel (depth: 8)...");
    
    // sharp automatically converts to 8-bit per channel (standard sRGB/RGBA) when outputting standard png
    await sharp(rootIconPng)
        .png({ palette: false }) // force standard RGBA, no indexed palette
        .toFile(tempPng);

    const pngBuffer = fs.readFileSync(tempPng);
    const width = pngBuffer.readUInt32BE(16);
    const height = pngBuffer.readUInt32BE(20);
    const depth = pngBuffer.readUInt8(24);
    const colorType = pngBuffer.readUInt8(25);
    console.log(`Converted PNG Info: ${width}x${height}, Bit Depth: ${depth}, Color Type: ${colorType}, size: ${pngBuffer.length} bytes`);

    // ICO header (6 bytes)
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(1, 4);
    
    // ICO directory entry (16 bytes)
    const entry = Buffer.alloc(16);
    entry.writeUInt8(width >= 256 ? 0 : width, 0);
    entry.writeUInt8(height >= 256 ? 0 : height, 1);
    entry.writeUInt8(0, 2);
    entry.writeUInt8(0, 3);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(pngBuffer.length, 8);
    entry.writeUInt32LE(22, 12);
    
    const icoBuffer = Buffer.concat([header, entry, pngBuffer]);
    fs.writeFileSync(rootIconIco, icoBuffer);
    fs.writeFileSync(tauriIconIco, icoBuffer);
    
    // Cleanup temp png
    fs.unlinkSync(tempPng);
    
    console.log(`Successfully generated ICO files at ${rootIconIco} and ${tauriIconIco} (size: ${icoBuffer.length} bytes)`);
}

convert().catch(console.error);
