const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

console.log('=== Diagnostic Windows ===');
console.log('Platform:', process.platform);
console.log('process.resourcesPath:', process.resourcesPath);
console.log('__dirname:', __dirname);

// Test des chemins
const isAppImage = process.env.APPIMAGE || process.resourcesPath.includes('.mount');
const isWindows = process.platform === 'win32';

console.log('isAppImage:', isAppImage);
console.log('isWindows:', isWindows);

// Chemins Caddy
const caddyPath = isAppImage 
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy')
    : isWindows
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'caddy', 'caddy.exe')
    : path.join(__dirname, 'caddy', 'caddy');

console.log('Caddy Path:', caddyPath);
console.log('Caddy exists:', fs.existsSync(caddyPath));

// Chemins PHP
const phpPath = isAppImage 
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php')
    : isWindows
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'php', 'php.exe')
    : path.join(__dirname, 'php', 'php');

console.log('PHP Path:', phpPath);
console.log('PHP exists:', fs.existsSync(phpPath));

// Chemins App
const appPath = isAppImage 
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public')
    : isWindows
    ? path.join(process.resourcesPath, 'app.asar.unpacked', 'app', 'public')
    : path.join(__dirname, 'app', 'public');

console.log('App Path:', appPath);
console.log('App exists:', fs.existsSync(appPath));

// Caddyfile
const caddyfilePath = isAppImage 
    ? path.join(process.resourcesPath, 'Caddyfile')
    : isWindows
    ? path.join(process.resourcesPath, 'Caddyfile')
    : path.join(__dirname, 'Caddyfile');

console.log('Caddyfile Path:', caddyfilePath);
console.log('Caddyfile exists:', fs.existsSync(caddyfilePath));

// Test d'exécution PHP
if (fs.existsSync(phpPath)) {
    console.log('\n=== Test PHP ===');
    const phpProcess = spawn(phpPath, ['--version'], { stdio: 'pipe' });
    
    phpProcess.stdout.on('data', (data) => {
        console.log('PHP stdout:', data.toString());
    });
    
    phpProcess.stderr.on('data', (data) => {
        console.log('PHP stderr:', data.toString());
    });
    
    phpProcess.on('close', (code) => {
        console.log('PHP exit code:', code);
    });
} else {
    console.log('❌ PHP non trouvé');
}

// Test d'exécution Caddy
if (fs.existsSync(caddyPath)) {
    console.log('\n=== Test Caddy ===');
    const caddyProcess = spawn(caddyPath, ['version'], { stdio: 'pipe' });
    
    caddyProcess.stdout.on('data', (data) => {
        console.log('Caddy stdout:', data.toString());
    });
    
    caddyProcess.stderr.on('data', (data) => {
        console.log('Caddy stderr:', data.toString());
    });
    
    caddyProcess.on('close', (code) => {
        console.log('Caddy exit code:', code);
    });
} else {
    console.log('❌ Caddy non trouvé');
}
