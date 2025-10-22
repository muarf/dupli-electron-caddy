const os = require('os');
const { app } = require('electron');

/**
 * Vérifie la compatibilité Windows et applique les corrections nécessaires
 */
function checkWindowsCompatibility() {
    if (process.platform !== 'win32') {
        return; // Pas Windows, pas de problème
    }

    const release = os.release();
    const version = parseFloat(release);
    
    console.log(`Version Windows détectée: ${release} (${version})`);
    
    // Windows 8.1 correspond à la version 6.3
    if (version < 10.0) {
        console.log('Windows 8.1 ou antérieur détecté - Application des corrections de compatibilité');
        
        // Désactiver les fonctionnalités qui causent des problèmes sur Windows 8.1
        process.env.ELECTRON_DISABLE_SANDBOX = 'true';
        process.env.ELECTRON_DISABLE_GPU = 'true';
        
        // Ajouter les arguments de compatibilité
        const originalArgs = process.argv;
        if (!originalArgs.includes('--no-sandbox')) {
            process.argv.push('--no-sandbox');
        }
        if (!originalArgs.includes('--disable-gpu')) {
            process.argv.push('--disable-gpu');
        }
        if (!originalArgs.includes('--disable-web-security')) {
            process.argv.push('--disable-web-security');
        }
        
        console.log('Arguments de compatibilité appliqués:', process.argv);
    }
}

/**
 * Applique les paramètres de compatibilité au démarrage de l'application
 */
function applyCompatibilitySettings() {
    if (process.platform === 'win32') {
        const release = os.release();
        const version = parseFloat(release);
        
        if (version < 10.0) {
            // Désactiver l'accélération matérielle qui peut causer des problèmes
            app.commandLine.appendSwitch('disable-gpu');
            app.commandLine.appendSwitch('disable-gpu-compositing');
            app.commandLine.appendSwitch('disable-software-rasterizer');
            
            // Désactiver le sandbox qui n'est pas supporté sur les anciennes versions
            app.commandLine.appendSwitch('no-sandbox');
            
            // Désactiver les fonctionnalités de sécurité modernes
            app.commandLine.appendSwitch('disable-web-security');
            app.commandLine.appendSwitch('disable-features', 'VizDisplayCompositor');
            
            console.log('Paramètres de compatibilité Windows 8.1 appliqués');
        }
    }
}

module.exports = {
    checkWindowsCompatibility,
    applyCompatibilitySettings
};
