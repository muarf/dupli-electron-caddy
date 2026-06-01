const { spawn } = require('child_process');
const path = require('path');

console.log('--- TEST DE LANCEMENT ELECTRON HORS PLAYWRIGHT ---');

const electronPath = path.join(__dirname, 'node_modules/.bin/electron');
const args = [
    '.',
    '--no-sandbox',
    '--disable-gpu',
    '--remote-debugging-port=9222'
];

console.log('Lancement de:', electronPath, args.join(' '));

const child = spawn(electronPath, args, {
    env: {
        ...process.env,
        NODE_ENV: 'test',
        DEBUG: '*'
    },
    stdio: 'pipe'
});

child.stdout.on('data', (data) => {
    console.log(`[STDOUT] ${data}`);
});

child.stderr.on('data', (data) => {
    console.log(`[STDERR] ${data}`);
});

child.on('error', (err) => {
    console.error('Erreur lors du spawn:', err);
});

child.on('close', (code) => {
    console.log('Processus terminé avec le code:', code);
    process.exit(code);
});

// On laisse tourner 15 secondes puis on tue
setTimeout(() => {
    console.log('Fin du test de 15s, fermeture...');
    child.kill();
    process.exit(0);
}, 15000);
