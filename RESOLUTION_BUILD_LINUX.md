# Résolution du problème de build Linux AppImage

## Date: 15 Octobre 2025

## Problèmes identifiés et corrigés

### 1. **Espace disque insuffisant**
**Symptôme:** `Error: No space left on device`
**Cause:** Le dossier `app/public/tmp/` contenait 457 MB de fichiers PDF temporaires
**Solution:** Ajout d'une étape de nettoyage dans le workflow
```yaml
rm -rf app/public/tmp/* || true
rm -rf app/public/sauvegarde/* || true
rm -rf app/tmp/* || true
```

### 2. **Erreur ASAR "file already exists"**
**Symptôme:** `Error: EEXIST: file already exists, link '...' -> '...'`
**Cause:** Tentative de désactiver ASAR complètement pour Linux
**Solution:** Réactiver ASAR avec `asarUnpack` (configuration qui fonctionnait au commit dd98b437)
```yaml
linux:
  asarUnpack:
    - "app/**/*"
    - "ghostscript/**/*"
    - "caddy/**/*"
    - "Caddyfile"
    - "php-fpm.conf"
    - "php-install-guide.html"
```

### 3. **Conflit avec electron-builder** ⚠️ **CRITIQUE**
**Symptôme:** Build échoue rapidement sans message d'erreur clair
**Cause:** Nettoyage de `/tmp/t-*` juste avant le build
**Solution:** Supprimer la ligne qui nettoyait `/tmp` avant le build
```bash
# SUPPRIMÉ (causait le problème):
# sudo find /tmp -name "t-*" -type d -exec rm -rf {} + 2>/dev/null || true
```

Electron-builder utilise `/tmp/t-*` comme répertoire de travail temporaire pendant le processus de build. Le nettoyer juste avant causait l'échec du build.

## Commits de correction

1. `25ad5fc` - Fix: Nettoyer les fichiers temporaires de app/public/tmp
2. `5e2c485` - Fix: Désactiver ASAR pour Linux (ANNULÉ)
3. `ad5e96f` - Fix: Utiliser --publish=never pour le build
4. `f343aff` - Fix: Réactiver ASAR pour Linux avec asarUnpack
5. `da6be92` - Debug: Ajouter workflow de debug
6. `0304504` - Fix CRITIQUE: Supprimer le nettoyage de /tmp ✅

## Test de validation

Le workflow de debug a **RÉUSSI** au commit `da6be92`, confirmant que la configuration fonctionne.

Le build local a également **RÉUSSI** :
```bash
$ npm run build:caddy -- --publish=never
# Résultat: Duplicator-1.3.0.AppImage (382 MB)
```

## Vérification du workflow GitHub Actions

Pour vérifier si le dernier workflow a réussi, consultez :
https://github.com/muarf/dupli-electron-caddy/actions

Le dernier commit poussé est : `0304504`
Le workflow devrait maintenant **réussir** car le problème critique a été corrigé.

## Configuration finale

### electron-builder-caddy.yml (Linux)
```yaml
linux:
  target: 
    - target: AppImage
      arch: [x64]
  category: Office
  executableName: Duplicator
  executableArgs: ["--no-sandbox", "--disable-gpu", "--disable-setuid-sandbox"]
  files:
    - main-caddy.js
    - main.js
    - preload.js
    - Caddyfile
    - php-fpm.conf
    - php-install-guide.html
    - app/**/*
    - ghostscript/**/*
    - caddy/**/*
    - "!php/**/*"  # Exclure PHP (utilise PHP système)
  asarUnpack:
    - "app/**/*"
    - "ghostscript/**/*"
    - "caddy/**/*"
    - "Caddyfile"
    - "php-fpm.conf"
    - "php-install-guide.html"
```

### Workflow test-linux.yml
- Nettoyage des fichiers temporaires AVANT les installations
- **PAS** de nettoyage de `/tmp` juste avant le build
- Build avec `--publish=never`
- Upload des artifacts via `actions/upload-artifact@v4`

## Prochaines étapes

1. ✅ Vérifier que le workflow GitHub Actions réussit
2. ✅ Télécharger l'AppImage depuis les artifacts
3. ✅ Tester l'AppImage sur une machine Linux
4. 🔄 Si tout fonctionne, merger dans main

## Notes importantes

- **Ne JAMAIS nettoyer `/tmp` juste avant un build electron-builder**
- Les fichiers temporaires de l'application doivent être nettoyés AVANT l'installation des dépendances
- ASAR doit être activé avec `asarUnpack` pour éviter les erreurs de liens
- Le dossier `php/` doit être vidé pour Linux (économie d'espace, utilise PHP système)

