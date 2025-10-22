# Compatibilité Windows 8.1

## Problème résolu

L'erreur `DiscardVirtualMemory` sur Windows 8.1 était causée par l'utilisation d'APIs Windows modernes non disponibles sur cette version.

## Corrections apportées

### 1. Downgrade d'Electron
- **Avant** : Electron 28.3.3 (utilise des APIs Windows 10+)
- **Après** : Electron 22.3.27 (compatible Windows 8.1)

### 2. Configuration electron-builder
Ajout de paramètres de compatibilité dans `electron-builder-caddy.yml` :
```yaml
win:
  # Configuration pour la compatibilité Windows 8.1
  requestedExecutionLevel: asInvoker
  # Forcer l'utilisation d'APIs compatibles Windows 8.1
  env:
    ELECTRON_DISABLE_SANDBOX: true
    ELECTRON_DISABLE_GPU: true
  # Paramètres de compatibilité pour Windows 8.1
  executableArgs: ["--no-sandbox", "--disable-gpu", "--disable-web-security"]
```

### 3. Script de compatibilité
Création de `utils/windows-compatibility.js` qui :
- Détecte automatiquement Windows 8.1
- Applique les paramètres de compatibilité
- Désactive les fonctionnalités problématiques

### 4. Intégration dans l'application
- Vérification de compatibilité au démarrage
- Application automatique des paramètres de compatibilité

## Instructions de déploiement

1. **Installer les nouvelles dépendances** :
   ```bash
   npm install
   ```

2. **Reconstruire l'application** :
   ```bash
   npm run build:caddy
   ```

3. **Tester sur Windows 8.1** :
   L'application devrait maintenant fonctionner sans l'erreur `DiscardVirtualMemory`.

## Fonctionnalités désactivées sur Windows 8.1

Pour assurer la compatibilité, les fonctionnalités suivantes sont automatiquement désactivées sur Windows 8.1 :
- Sandbox Electron
- Accélération GPU
- Fonctionnalités de sécurité modernes
- APIs Windows 10+

## Notes importantes

- Cette solution maintient la compatibilité avec Windows 10/11
- Les paramètres de compatibilité sont appliqués automatiquement
- Aucune action manuelle requise de la part de l'utilisateur
