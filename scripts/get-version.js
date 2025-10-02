#!/usr/bin/env node

const { execSync } = require('child_process');
const fs = require('fs');

function getVersionFromGit() {
    try {
        // Récupérer le nombre de commits depuis le dernier tag
        const commitCount = execSync('git rev-list --count HEAD', { encoding: 'utf8' }).trim();
        
        // Récupérer le dernier tag
        let lastTag;
        try {
            lastTag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
        } catch (error) {
            // Pas de tags, utiliser 1.0.0 comme base
            lastTag = '1.0.0';
        }
        
        // Extraire la version de base (1.0.0)
        const baseVersion = lastTag.replace(/^v/, '');
        const [major, minor, patch] = baseVersion.split('.');
        
        // Calculer la nouvelle version
        const newVersion = `${major}.${minor}.${parseInt(patch) + parseInt(commitCount)}`;
        
        return newVersion;
    } catch (error) {
        console.error('Erreur lors de la récupération de la version:', error.message);
        // Fallback vers une version timestamp
        const timestamp = Math.floor(Date.now() / 1000);
        return `1.0.${timestamp}`;
    }
}

function updatePackageJson() {
    const newVersion = getVersionFromGit();
    
    try {
        const packagePath = 'package.json';
        const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
        
        // Sauvegarder l'ancienne version
        const oldVersion = packageJson.version;
        packageJson.version = newVersion;
        
        // Écrire le nouveau package.json
        fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, 2) + '\n');
        
        console.log(`Version mise à jour: ${oldVersion} → ${newVersion}`);
        return newVersion;
    } catch (error) {
        console.error('Erreur lors de la mise à jour du package.json:', error.message);
        return null;
    }
}

if (require.main === module) {
    const version = updatePackageJson();
    if (version) {
        console.log(`✅ Version finale: ${version}`);
    } else {
        process.exit(1);
    }
}

module.exports = { getVersionFromGit, updatePackageJson };
