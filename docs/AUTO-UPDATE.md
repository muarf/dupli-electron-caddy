# 🔄 Système de Mise à Jour Automatique

Ce guide explique comment fonctionne le système de mise à jour automatique et comment l'utiliser pour déployer de nouvelles versions de Duplicator.

## 📋 Vue d'ensemble

Le système de mise à jour automatique permet aux utilisateurs de recevoir des mises à jour sans avoir à réinstaller l'application. **La base de données est automatiquement préservée** lors des mises à jour.

### ✨ Fonctionnalités

- ✅ **Vérification automatique** des mises à jour (au démarrage + toutes les 4 heures)
- ✅ **Téléchargement en arrière-plan** sans bloquer l'utilisateur
- ✅ **Installation au redémarrage** pour éviter les interruptions
- ✅ **Préservation de la base de données** - stockée dans le dossier userData
- ✅ **Notifications visuelles** dans l'interface utilisateur
- ✅ **Compatible Windows, Linux et macOS**

## 🗂️ Emplacement de la Base de Données

La base de données `duplinew.sqlite` est maintenant stockée dans le dossier `userData` de l'utilisateur :

- **Windows**: `C:\Users\[Username]\AppData\Roaming\Duplicator\duplinew.sqlite`
- **Linux**: `~/.config/Duplicator/duplinew.sqlite`
- **macOS**: `~/Library/Application Support/Duplicator/duplinew.sqlite`

Au premier lancement, si la base de données n'existe pas dans userData, elle est copiée depuis le template inclus dans l'application (si disponible), sinon l'application PHP la créera automatiquement.

## 🔧 Configuration

### 1. Installer electron-updater

```bash
npm install electron-updater --save
```

### 2. Configuration electron-builder

Le fichier `electron-builder-caddy.yml` est déjà configuré :

```yaml
publish:
  provider: github
  releaseType: release
  publishAutoUpdate: true  # ← Active la publication des mises à jour
```

### 3. Intégration dans l'interface web

Ajoutez le script dans votre page HTML :

```html
<!-- Dans app/view/header.html.php ou votre layout principal -->
<script src="/js/updater-ui.js"></script>
```

Le script `updater-ui.js` gère automatiquement :
- Les notifications de mise à jour disponible
- La barre de progression du téléchargement
- Les boutons pour télécharger et installer

## 📦 Publier une Nouvelle Version

### Étape 1 : Mettre à jour la version

Modifiez `package.json` :

```json
{
  "version": "1.0.13"
}
```

### Étape 2 : Commit et tag

```bash
git add .
git commit -m "Version 1.0.13 - Description des changements"
git tag v1.0.13
git push origin main --tags
```

### Étape 3 : Build et publication

```bash
# Build pour toutes les plateformes
npm run build:caddy

# Ou pour Windows uniquement
npm run build:caddy -- --win
```

### Étape 4 : Créer une Release GitHub

1. Allez sur GitHub → Releases → New Release
2. Sélectionnez le tag `v1.0.13`
3. Ajoutez un titre et une description
4. Uploadez les fichiers depuis `dist/` :
   - `Duplicator-Setup-1.0.13.exe` (Windows installer)
   - `Duplicator-1.0.13.exe` (Windows portable)
   - `latest.yml` (fichier de métadonnées pour l'updater)
   - `Duplicator-1.0.13.AppImage` (Linux, si applicable)
   - `latest-linux.yml` (métadonnées Linux)
5. Publiez la release

**Important** : Le fichier `latest.yml` (ou `latest-linux.yml`, `latest-mac.yml`) est crucial. C'est ce fichier que l'updater vérifie pour détecter les nouvelles versions.

## 🎯 Fonctionnement Technique

### Flux de mise à jour

```
1. L'application démarre
   ↓
2. Après 10 secondes, vérification des mises à jour sur GitHub
   ↓
3. Si une nouvelle version existe :
   → Notification à l'utilisateur
   → L'utilisateur clique sur "Télécharger"
   ↓
4. Téléchargement en arrière-plan avec barre de progression
   ↓
5. Une fois téléchargé :
   → Notification "Prêt à installer"
   → L'utilisateur choisit "Maintenant" ou "Plus tard"
   ↓
6. Installation au prochain redémarrage
   → La base de données reste dans userData
   → Aucune perte de données
```

### Vérifications automatiques

- **Au démarrage** : 10 secondes après le lancement
- **Périodique** : Toutes les 4 heures pendant que l'application tourne
- **Manuel** : Via `window.electronAPI.checkForUpdates()` si implémenté

## 🔌 API Disponibles

### Dans votre code JavaScript (frontend)

```javascript
// Vérifier manuellement les mises à jour
const result = await window.electronAPI.checkForUpdates();

// Télécharger une mise à jour
await window.electronAPI.downloadUpdate();

// Installer la mise à jour (redémarre l'app)
await window.electronAPI.installUpdate();

// Obtenir le chemin de la base de données
const dbPath = await window.electronAPI.getDatabasePath();

// Obtenir la version de l'application
const version = await window.electronAPI.getAppVersion();

// Écouter les événements
window.electronAPI.onUpdateAvailable((info) => {
    console.log('Nouvelle version:', info.version);
});

window.electronAPI.onDownloadProgress((progress) => {
    console.log('Progression:', progress.percent);
});

window.electronAPI.onUpdateDownloaded((info) => {
    console.log('Mise à jour prête à être installée');
});
```

## 🐛 Dépannage

### La mise à jour ne se déclenche pas

1. Vérifiez que `publishAutoUpdate: true` dans `electron-builder-caddy.yml`
2. Assurez-vous que le fichier `latest.yml` est bien publié sur GitHub
3. Vérifiez les logs dans la console Electron : "Vérification des mises à jour..."
4. En développement, l'updater est désactivé (voir `NODE_ENV`)

### Erreur "Update check failed"

- Vérifiez votre connexion internet
- Vérifiez que le repository GitHub est public ou que vous avez un token d'accès
- Vérifiez les permissions de la release GitHub

### La base de données n'est pas préservée

- Vérifiez que `deleteAppDataOnUninstall: false` dans la config NSIS
- La base de données doit être dans `userData`, pas dans le dossier de l'application

## 📝 Notes Importantes

### Mode développement

L'auto-updater est **désactivé en développement** pour éviter les conflits. Pour tester :

```bash
# Build en mode production
npm run build:caddy

# Installer et tester l'exe généré
```

### Structure de version

Utilisez le format **Semantic Versioning** : `MAJOR.MINOR.PATCH`
- `1.0.0` → `1.0.1` : Correctifs
- `1.0.0` → `1.1.0` : Nouvelles fonctionnalités
- `1.0.0` → `2.0.0` : Changements majeurs

### Sécurité

- Les mises à jour sont téléchargées depuis GitHub Releases
- Utilisez HTTPS pour toutes les communications
- Ne téléchargez que des releases signées en production

## 🎨 Personnalisation

### Modifier l'interface des notifications

Éditez `app/public/js/updater-ui.js` pour personnaliser :
- Les couleurs
- Le texte
- La position des notifications
- Le comportement

### Modifier la fréquence de vérification

Dans `main-caddy.js`, fonction `setupAutoUpdater()` :

```javascript
// Vérification toutes les 2 heures au lieu de 4
setInterval(() => {
    autoUpdater.checkForUpdates();
}, 2 * 60 * 60 * 1000);
```

## 🚀 Exemple Complet

### Publication d'une mise à jour (workflow complet)

```bash
# 1. Développer les nouvelles fonctionnalités
# 2. Tester localement

# 3. Mettre à jour la version
# Éditer package.json: "version": "1.1.0"

# 4. Commit et tag
git add .
git commit -m "Version 1.1.0 - Ajout du séparateur RISO"
git tag v1.1.0
git push origin main --tags

# 5. Build
npm run build:caddy

# 6. Upload sur GitHub
# - Créer une release v1.1.0
# - Upload dist/*.exe et dist/*.yml

# 7. Les utilisateurs recevront la notification automatiquement !
```

## 📚 Ressources

- [Documentation electron-updater](https://www.electron.build/auto-update)
- [GitHub Releases](https://docs.github.com/en/repositories/releasing-projects-on-github)
- [Semantic Versioning](https://semver.org/)

---

**Note** : Ce système est déjà entièrement configuré et fonctionnel. Il vous suffit de publier vos releases sur GitHub pour que les utilisateurs reçoivent les mises à jour automatiquement.

