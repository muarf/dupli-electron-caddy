const fs = require('fs');
const path = require('path');

function pngToIco(pngPath, icoPath) {
    const pngBuffer = fs.readFileSync(pngPath);
    
    // Parse width and height from IHDR chunk
    // PNG signature is 8 bytes, IHDR chunk length is 4 bytes, IHDR chunk type is 4 bytes
    // Width is at offset 16 (4 bytes), Height at offset 20 (4 bytes)
    const width = pngBuffer.readUInt32BE(16);
    const height = pngBuffer.readUInt32BE(20);
    
    console.log(`PNG Info: ${width}x${height}, size: ${pngBuffer.length} bytes`);
    
    // ICO header (6 bytes)
    // Reserved (2 bytes) = 0
    // Type (2 bytes) = 1 (ICO)
    // Image count (2 bytes) = 1
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(1, 4);
    
    // ICO directory entry (16 bytes)
    // Width (1 byte): 0 if 256
    // Height (1 byte): 0 if 256
    // Color palette (1 byte) = 0
    // Reserved (1 byte) = 0
    // Color planes (2 bytes) = 1
    // Bits per pixel (2 bytes) = 32
    // Image size (4 bytes): size of the PNG buffer
    // Image offset (4 bytes): 6 (header) + 16 (entry) = 22
    const entry = Buffer.alloc(16);
    entry.writeUInt8(width >= 256 ? 0 : width, 0);
    entry.writeUInt8(height >= 256 ? 0 : height, 1);
    entry.writeUInt8(0, 2);
    entry.writeUInt8(0, 3);
    entry.writeUInt16LE(1, 4);
    entry.writeUInt16LE(32, 6);
    entry.writeUInt32LE(pngBuffer.length, 8);
    entry.writeUInt32LE(22, 12);
    
    // Combine everything
    const icoBuffer = Buffer.concat([header, entry, pngBuffer]);
    fs.writeFileSync(icoPath, icoBuffer);
    console.log(`Successfully generated ICO file at ${icoPath} (size: ${icoBuffer.length} bytes)`);
}

// Convert icons/icon.png to icon.ico
const rootIconPng = path.join(__dirname, '..', 'icons', 'icon.png');
const rootIconIco = path.join(__dirname, '..', 'icons', 'icon.ico');
const tauriIconIco = path.join(__dirname, '..', 'src-tauri', 'icons', 'icon.ico');

pngToIco(rootIconPng, rootIconIco);
fs.copyFileSync(rootIconIco, tauriIconIco);
console.log(`Copied to ${tauriIconIco}`);
