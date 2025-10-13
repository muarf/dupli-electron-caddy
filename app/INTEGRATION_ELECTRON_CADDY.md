# Intégration de la nouvelle fonctionnalité "Taux de Remplissage" dans dupli-electron-caddy

## 📦 Fichiers à intégrer

### 1. Nouveaux fichiers PHP
```
models/taux_remplissage.php
view/taux_remplissage.html.php
```

### 2. Fichiers modifiés
```
view/header.html.php          (ajout menu)
index.php                      (ajout dans $page_secure)
public/index.php               (ajout dans $page_secure)
php.ini                        (ajout extension GD)
```

### 3. Fichiers de documentation
```
README_GD_WINDOWS.md           (guide configuration GD)
test_gd.php                    (script de test)
INTEGRATION_ELECTRON_CADDY.md  (ce fichier)
```

## 🔧 Configuration requise

### Extension PHP GD

**CRITIQUE** : La fonctionnalité nécessite l'extension GD pour analyser les images.

#### Pour PHP 7.4 (Windows)
1. Vérifier que `php/ext/php_gd2.dll` existe dans votre distribution PHP
2. Ajouter dans `php.ini` :
   ```ini
   extension=gd2.dll
   ```

#### Pour PHP 8.0+ (Windows)
1. Vérifier que `php/ext/php_gd.dll` existe
2. Ajouter dans `php.ini` :
   ```ini
   extension=gd
   ```

#### DLLs dépendantes (généralement déjà présentes)
Les DLLs suivantes doivent être dans le dossier `php/` :
- `libpng16.dll`
- `libjpeg-9.dll` ou `libjpeg-62.dll`
- `libfreetype-6.dll`
- `zlib1.dll`

### Ghostscript

La conversion PDF utilise Ghostscript. Vérifier que :
```
ghostscript/gswin64c.exe
```
existe dans le projet (déjà présent normalement).

## ✅ Checklist d'intégration

### Étape 1 : Copier les fichiers
- [ ] Copier `models/taux_remplissage.php`
- [ ] Copier `view/taux_remplissage.html.php`
- [ ] Mettre à jour `view/header.html.php`
- [ ] Mettre à jour `index.php` et `public/index.php`
- [ ] Mettre à jour `php.ini`

### Étape 2 : Vérifier les dépendances
- [ ] L'extension GD est présente : `php/ext/php_gd2.dll` ou `php/ext/php_gd.dll`
- [ ] Ghostscript est présent : `ghostscript/gswin64c.exe`
- [ ] Les DLLs dépendantes sont présentes

### Étape 3 : Tester
- [ ] Lancer `php test_gd.php` pour vérifier GD
- [ ] Démarrer l'application Electron
- [ ] Accéder au menu "Outils PDF" → "Taux de Remplissage"
- [ ] Tester avec une image PNG/JPG
- [ ] Tester avec un PDF

### Étape 4 : Vérifier les logs
En cas de problème, consulter :
- Logs de l'application Electron
- `tmp/duplicator_errors.log`
- Console développeur (F12 dans Electron)

## 🐛 Résolution de problèmes

### Page blanche après upload
**Cause** : Extension GD manquante ou non chargée

**Solution** :
1. Vérifier que `php.ini` contient `extension=gd2.dll`
2. Vérifier que `php/ext/php_gd2.dll` existe
3. Redémarrer l'application
4. Lancer `php test_gd.php` pour diagnostiquer

### "Call to undefined function imagecreatefrompng()"
**Cause** : Extension GD non chargée

**Solution** :
1. Vérifier le `extension_dir` dans `php.ini` : doit pointer vers `php/ext`
2. S'assurer que le chemin est relatif ou absolu correct
3. Vérifier les permissions des fichiers DLL

### "Ghostscript non trouvé"
**Cause** : Ghostscript manquant ou mal configuré

**Solution** :
1. Vérifier que `ghostscript/gswin64c.exe` existe
2. Le code vérifie automatiquement ce chemin pour Windows
3. Sur Linux/Mac, Ghostscript doit être installé système

### Timeout lors de l'analyse
**Cause** : Image trop grande ou timeout PHP trop court

**Solution** :
Dans `php.ini`, vérifier :
```ini
max_execution_time = 300
memory_limit = 512M
```

### Fichier uploadé mais erreur "Type MIME non autorisé"
**Cause** : Extension fileinfo manquante

**Solution** :
Dans `php.ini`, vérifier que `extension=fileinfo` est activé.

## 📝 Notes techniques

### Performance
L'analyse pixel par pixel peut être lente sur de grandes images :
- Image 2000x3000 pixels : ~5-10 secondes
- Image 4000x6000 pixels : ~20-40 secondes

### Mémoire
Pour les grandes images, la mémoire nécessaire est d'environ :
```
Mémoire ≈ largeur × hauteur × 4 octets × 2
```
Exemple : Image 4000x6000 → ~192 MB de RAM

### Formats supportés
- ✅ PDF (converti en PNG via Ghostscript)
- ✅ JPEG/JPG
- ✅ PNG
- ✅ GIF
- ❌ TIFF (pas supporté par défaut)
- ❌ BMP (pas supporté par défaut)

### Limites
- Taille max fichier : 50 MB (configurable dans le code)
- Format de sortie : Toujours PNG après conversion PDF
- DPI conversion PDF : 150 DPI (configurable)

## 🔄 Mise à jour future

Si vous devez mettre à jour PHP dans dupli-electron-caddy :
1. Télécharger la version PHP "Thread Safe" pour Windows
2. S'assurer que GD est inclus (généralement oui depuis PHP 7.4)
3. Copier `php/ext/php_gd2.dll` dans la nouvelle version
4. Réactiver dans `php.ini`

## 📞 Support

En cas de problème :
1. Lancer `php test_gd.php` et envoyer le résultat
2. Consulter les logs dans `tmp/duplicator_errors.log`
3. Vérifier la configuration avec `php -i | findstr gd`

---

**Version** : 1.0  
**Date** : 13 octobre 2025  
**Compatibilité** : PHP 7.4+ sur Windows avec Electron + Caddy






