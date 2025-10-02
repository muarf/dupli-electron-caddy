#!/usr/bin/env node

const { execSync } = require('child_process');
const fs = require('fs');

function getVersionFromGit() {
    try {
        // Vérifier d'abord s'il existe des tags
        let hasTags = false;
        try {
            execSync('git tag --list', { encoding: 'utf8' });
            // Si on arrive ici, il y a des tags ou la commande a réussi
            hasTags = true;
        } catch (error) {
            hasTags = false;
        }
        
        let lastTag;
        if (hasTags) {
            try {
                lastTag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
            } catch (error) {
                // Problème avec describe, utiliser le premier tag disponible
                const tags = execSync('git tag --list --sort=-version:refname', { encoding: 'utf8' }).trim().split('\n');
                lastTag = tags[0] || '1.0.0';
            }
        } else {
            // Pas de tags, utiliser 1.0.0 comme base
            lastTag = '1.0.0';
        }
        
        // Récupérer le nombre de commits total si pas de tags, sinon depuis le dernier tag
        let commitCount;
        if (hasTags) {
            try {
                commitCount = execSync(`git rev-list --count ${lastTag}..HEAD`, { encoding: 'utf8' }).trim();
            } catch (error) {
                commitCount = execSync('git rev-list --count HEAD', { encoding: 'utf8' }).trim();
            }
        } else {
            // Pas de tags, utiliser le nombre total de commits
            commitCount = execSync('git rev-list --count HEAD', { encoding: 'utf8' }).trim();
        }
        
        // Extraire la version de base
        const baseVersion = lastTag.replace(/^v/, '');
        const [major, minor, patch] = baseVersion.split('.');
        
        // Calculer la nouvelle version
        const newPatch = parseInt(patch) + parseInt(commitCount);
        const newVersion = `${major}.${minor}.${newPatch}`;
        
        return newVersion;
    } catch (error) {
        console.error('Erreur lors de la récupération de la version:', error.message);
        // Fallback vers une version par défaut
        return '1.0.0';
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
