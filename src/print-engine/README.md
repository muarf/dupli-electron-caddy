# Module d'impression Print Engine

Module d'impression complet pour l'application Electron Duplicator, avec support Windows (Win32 API) et Linux (CUPS/IPP).

## Architecture

Le module fournit une API JavaScript uniforme qui s'adapte automatiquement à la plateforme :

- **Windows** : Utilise un addon Node.js natif (N-API) pour accéder aux APIs Win32 (WinSpool, DEVMODE, DeviceCapabilities)
- **Linux/macOS** : Utilise CUPS/IPP via les commandes système (`lpstat`, `lpoptions`, `lp`)

## Installation

### Dépendances

```bash
npm install node-addon-api
npm install --save-dev node-gyp
```

### Compilation de l'addon Windows

Sur Windows, l'addon natif doit être compilé :

```bash
cd src/print-engine/windows
node-gyp rebuild
```

Ou depuis la racine du projet :

```bash
npm run rebuild:print-engine
```

## Utilisation

### API JavaScript

```javascript
const printEngine = require('./src/print-engine');

// Obtenir la liste des imprimantes
const printers = await printEngine.getPrinters();
console.log('Imprimantes disponibles:', printers);

// Obtenir les capacités d'une imprimante
const capabilities = await printEngine.getPrinterCapabilities('HP LaserJet');
console.log('Capacités:', capabilities);

// Imprimer un PDF
const result = await printEngine.printJob('/path/to/file.pdf', {
    printer: 'HP LaserJet',
    copies: 2,
    inputSlot: 'Tray1',
    pageSize: 'A4',
    colorMode: 'Color',
    duplex: 'DuplexNoTumble'
});
```

### Format des capacités

```javascript
{
  inputSlots: [
    { name: "Tray1", value: "Tray1" },
    { name: "Tray2", value: "Tray2" }
  ],
  pageSizes: [
    { name: "A4", value: "A4", width: 210, height: 297 },
    { name: "A3", value: "A3", width: 297, height: 420 }
  ],
  duplex: true,
  color: true,
  colorModes: ["Color", "Monochrome"],
  resolutions: ["300dpi", "600dpi", "1200dpi"]
}
```

### Format des options d'impression

```javascript
{
  printer: "Nom de l'imprimante",      // Requis
  copies: 1,                            // Optionnel, défaut: 1
  inputSlot: "Tray1",                   // Optionnel
  pageSize: "A4",                       // Optionnel
  colorMode: "Color",                   // Optionnel: "Color" ou "Monochrome"
  duplex: "DuplexNoTumble",            // Optionnel: "Simplex", "DuplexNoTumble", "DuplexTumble"
  resolution: "600dpi"                  // Optionnel
}
```

## Implémentations

### Linux (CUPS)

L'implémentation Linux utilise les commandes CUPS standard :

- `lpstat -p` : Liste des imprimantes
- `lpoptions -l` : Options et capacités
- `lp` : Envoi du job d'impression

**Prérequis** : CUPS doit être installé et accessible sur le système.

### Windows (Win32 API)

L'implémentation Windows utilise un addon Node.js natif qui expose :

- `EnumPrinters` : Énumération des imprimantes
- `DeviceCapabilities` : Récupération des capacités (bacs, formats, duplex, couleur)
- `DocumentProperties` : Gestion du DEVMODE pour configurer les options
- `ShellExecute` : Lancement de l'impression

**Prérequis** : 
- Visual Studio Build Tools ou Visual Studio avec les outils C++
- Windows SDK

## Intégration Electron

Le module est intégré dans l'application Electron via IPC :

### Preload.js

```javascript
window.electronAPI.getPrinterCapabilities(printerName)
window.electronAPI.printJob(pdfPath, options)
```

### Main Process (main-caddy.js)

Les handlers IPC sont automatiquement enregistrés lors du chargement du module.

## Gestion des erreurs

Le module gère gracieusement les erreurs :

- Imprimante hors ligne
- Fichier PDF introuvable
- Permissions insuffisantes
- Module non disponible sur la plateforme

Toutes les erreurs sont remontées avec des messages explicites.

## Limitations

- **Windows** : L'addon doit être compilé pour chaque architecture (x64 uniquement actuellement)
- **Linux** : Nécessite CUPS installé et configuré
- **macOS** : Utilise CUPS (comme Linux) mais peut nécessiter des permissions supplémentaires

## Développement

### Structure des fichiers

```
src/print-engine/
  index.js                    # API principale unifiée
  windows/
    win32-printer.js          # Wrapper JavaScript
    win32-printer.cc          # Code C++ de l'addon
    binding.gyp               # Configuration de build
  linux/
    cups-printer.js           # Implémentation CUPS
  README.md                   # Cette documentation
```

### Tests

Les tests unitaires sont dans `tests/unit/print-engine.test.js`.

Pour exécuter les tests :

```bash
npm test -- print-engine
```

## Support

Pour signaler un problème ou proposer une amélioration, veuillez ouvrir une issue sur le dépôt GitHub.

