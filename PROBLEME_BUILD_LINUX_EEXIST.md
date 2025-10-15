# Problème persistant: Build Linux AppImage échoue avec EEXIST

## Date: 15 Octobre 2025

## Résumé du problème

Le build Linux AppImage échoue systématiquement sur GitHub Actions avec l'erreur:
```
Error: EEXIST: file already exists, link '/path/to/file' -> '/tmp/t-xxxxx/asar-app-0/file'
```

## Solutions tentées (toutes ont échoué)

1. ✗ Nettoyage de `app/public/tmp/` (457 MB de fichiers temporaires)
2. ✗ Désactivation d'ASAR (`asar: false`)
3. ✗ Réactivation d'ASAR avec `asarUnpack`
4. ✗ Nettoyage de `/tmp/t-*` au début du workflow
5. ✗ Nettoyage de `/tmp/t-*` juste avant le build
6. ✗ Nettoyage de `dist/` avant le build
7. ✗ Nettoyage des caches electron et electron-builder
8. ✗ Utilisation de `extraResources` au lieu de `files`

## Diagnostic

**Cause racine** : Bug dans electron-builder v26.0.12 sur Linux/GitHub Actions

Electron-builder crée des répertoires temporaires dans `/tmp/t-*` et essaie de créer des hard links. Pour une raison inconnue, il essaie de créer les mêmes liens plusieurs fois pendant le même processus de build, ce qui cause l'erreur EEXIST.

Ce problème ne se produit PAS:
- Sur Windows (build GitHub Actions réussit)
- Sur macOS
- En local (build Linux local réussit)

Le problème se produit UNIQUEMENT:
- Sur GitHub Actions avec Ubuntu
- Avec la configuration actuelle d'electron-builder

## Solutions possibles

### Option 1: Builder avec --dir puis créer l'AppImage manuellement

```yaml
- name: Build unpacked directory
  run: npm run build:caddy -- --dir --publish=never

- name: Create AppImage manually
  run: |
    wget https://github.com/AppImage/AppImageKit/releases/download/continuous/appimagetool-x86_64.AppImage
    chmod +x appimagetool-x86_64.AppImage
    ./appimagetool-x86_64.AppImage dist/linux-unpacked dist/Duplicator.AppImage
```

### Option 2: Utiliser une version différente d'electron-builder

Downgrade vers electron-builder v24.x ou v25.x :
```json
"electron-builder": "^24.13.3"
```

### Option 3: Passer à Snap au lieu d'AppImage

```yaml
linux:
  target: 
    - target: snap
      arch: [x64]
```

### Option 4: Utiliser electron-forge au lieu d'electron-builder

Migrer vers `@electron-forge` qui n'a pas ce problème.

## Recommandation

**Option 1** est la plus simple et rapide à implémenter.

## État actuel

- ✅ Build Windows fonctionne
- ✅ Build local Linux fonctionne  
- ❌ Build Linux sur GitHub Actions échoue systématiquement
- ❌ Tous les correctifs ont échoué

## Commits pertinents

- `25ad5fc` - Nettoyage fichiers temporaires
- `ca70b2b` - Désactivation ASAR
- `f343aff` - Réactivation ASAR avec asarUnpack
- `bcf8292` - Nettoyage `/tmp` au début
- `9fa5dfd` - Nettoyage `dist/` avant build
- `ddb0ba1` - Nettoyage caches electron
- `28dd14f` - Utilisation extraResources

Tous ont échoué avec la même erreur EEXIST.

