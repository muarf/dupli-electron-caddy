# Stratégie de Fusion : Unified (Base) <- Cross-Platform (Linux Fixes)

## 1. Objectif
Utiliser `unified-printer-monitoring` comme branche principale de développement (car plus avancée en fonctionnalités v2.0) et y injecter les correctifs Linux essentiels de la branche `feature/cross-platform-unification`.

## 2. Blocs de code à intégrer (Greffons Linux)

### Greffon A : Préfixe CUPS et gestion des fichiers manquants
**Fichier** : `utils/spool-analyzer-linux.js`
**Source (Beta)** : Basé sur les commits `49ddae6` et `20da835`.

```javascript
// Remplacer le calcul du nom de fichier dans analyzeNewJob
const paddedId = jobId.toString().padStart(5, '0');
const filename = `c${paddedId}`; // Utilisation du préfixe 'c' (correct pour CUPS)
const filePath = path.join(this.spoolDir, filename);

// Ajouter le fallback si le fichier spool est absent
if (fileSize === 0 || !fs.existsSync(filePath)) {
    const jobInfo = {
        JobId: jobId,
        PrinterName: printerName || 'Unknown',
        Document: `Job ${jobId} (${user})`,
        Status: 'Completed',
        TotalPages: 1, // Fallback par défaut
        PaperSize: 'A4',
        TimeSubmitted: new Date().toISOString()
        // ... (voir commit 49ddae6 pour l'objet complet)
    };
    this.emit('job', jobInfo);
    return;
}
```

### Greffon B : Fallback du moniteur d'impression
**Fichier** : `main-caddy.js` (ou `main.js` selon l'entrée)
**Source (Beta)** : Commit `69ee116`.

```javascript
// Démarrage forcé du moniteur Linux si Caddy/PHP échoue à le faire
if (process.platform === 'linux') {
    console.log('🐧 Linux detected: Ensuring printer monitor is active...');
    // Logique de démarrage du moniteur linux via spawn
}
```

## 3. Points de vigilance (À conserver d'Alpha)
- **Filtrage temporel** : Garder le `MAX_AGE_MS = 30000` d'Alpha dans `pollJobs()` pour éviter les notifications en cascade au démarrage.
- **Logique v2.0** : Ne pas écraser les nouveaux modèles PHP de la branche Unified.

## 4. Commits critiques à Cherry-pick
- `49ddae6` : fix(linux) spool filename prefix
- `20da835` : fix: use correct CUPS spool path prefix
- `69ee116` : fix: start printer monitor on Linux in fallback case
