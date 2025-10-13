# Configuration de l'extension GD pour Windows (dupli-electron-caddy)

## Problème
La nouvelle fonctionnalité **"Taux de Remplissage"** nécessite l'extension PHP GD pour analyser les images pixel par pixel.

## Solution pour Windows

### 1. Vérifier la présence de l'extension GD

Dans votre distribution PHP embarquée, vérifiez que le fichier suivant existe :
```
php/ext/php_gd2.dll
```

Si le fichier existe déjà (très probable dans PHP 7.4+), il suffit de l'activer dans `php.ini`.

### 2. Activation dans php.ini

Le fichier `php.ini` a déjà été mis à jour avec :
```ini
extension=gd2.dll
```

### 3. Si l'extension n'existe pas

Si `php_gd2.dll` n'est pas présent dans votre distribution PHP :

1. **Télécharger PHP pour Windows** depuis https://windows.php.net/download/
   - Choisir la version correspondante (PHP 7.4 ou 8.x)
   - Télécharger la version "Thread Safe" en ZIP

2. **Extraire uniquement les fichiers nécessaires** :
   - `ext/php_gd2.dll` (ou `ext/gd.dll` pour PHP 8+)
   - Les DLLs dépendantes (généralement déjà présentes) :
     - `libpng16.dll`
     - `libjpeg-9.dll`
     - `libfreetype-6.dll`
     - `zlib1.dll`

3. **Copier dans votre distribution** :
   - `php_gd2.dll` → `php/ext/`
   - Les autres DLLs → racine du dossier `php/`

### 4. Configuration finale

Le fichier `php.ini` doit contenir :
```ini
; Configuration des extensions
extension_dir = "php/ext"
extension=sqlite3.dll
extension=pdo_sqlite.dll
extension=fileinfo
extension=php_curl.dll
extension=gd2.dll    ; <-- Nouvelle ligne ajoutée
```

### 5. Redémarrage

Après avoir ajouté l'extension :
1. Redémarrer l'application Electron
2. Ou redémarrer le serveur Caddy

### 6. Vérification

Pour vérifier que l'extension fonctionne :
1. Créer un fichier `test_gd.php` :
```php
<?php
if (extension_loaded('gd')) {
    echo "✅ Extension GD chargée !\n";
    echo "Fonctions disponibles :\n";
    echo "- imagecreatefrompng: " . (function_exists('imagecreatefrompng') ? 'OUI' : 'NON') . "\n";
    echo "- imagecreatefromjpeg: " . (function_exists('imagecreatefromjpeg') ? 'OUI' : 'NON') . "\n";
    echo "- getimagesize: " . (function_exists('getimagesize') ? 'OUI' : 'NON') . "\n";
} else {
    echo "❌ Extension GD non chargée\n";
}
?>
```

2. Lancer : `php test_gd.php`

## Alternative : Imagick

Si GD pose problème, une alternative serait d'utiliser **Imagick** (extension ImageMagick), mais cela nécessiterait de modifier le code de `taux_remplissage.php`.

## Note sur PHP 8+

Pour PHP 8.0 et supérieur, l'extension s'appelle simplement `gd` au lieu de `gd2` :
```ini
extension=gd
```

Le fichier DLL reste `php_gd.dll` ou `php_gd2.dll` selon la version.

## Fonctionnalités GD utilisées

La page "Taux de Remplissage" utilise les fonctions suivantes :
- `getimagesize()` - Obtenir les dimensions de l'image
- `imagecreatefromjpeg()` - Charger des images JPEG
- `imagecreatefrompng()` - Charger des images PNG
- `imagecreatefromgif()` - Charger des images GIF
- `imagecolorat()` - Lire la couleur d'un pixel
- `imagecolorsforindex()` - Obtenir les composantes RGB
- `imagedestroy()` - Libérer la mémoire

## Support Ghostscript

La fonctionnalité utilise également **Ghostscript** pour convertir les PDF en images. Assurez-vous que :
- `ghostscript/gswin64c.exe` est présent dans le projet
- Le chemin est correctement configuré dans `taux_remplissage.php`

---

**Date de création** : 13 octobre 2025  
**Concerne** : Nouvelle fonctionnalité "Taux de Remplissage"






