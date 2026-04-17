# Déploiement & CI - Duplicator

> **Fichiers couverts :** `package.json`, `deploy-script.sh`, `electron-builder-*.yml`, `.github/` (workflows)

---

## Table des matières

1. [Build & packaging](#1-build--packaging)
2. [Scripts npm disponibles](#2-scripts-npm-disponibles)
3. [Configuration electron-builder](#3-configuration-electron-builder)
4. [Script déploiement automatique](#4-script-déploiement-automatique)
5. [Tests & qualité](#5-tests--qualité)
6. [CI/CD (GitHub Actions)](#6-cicd-github-actions)
7. [Publications releases](#7-publications-releases)
8. [Mises à jour automatiques](#8-mises-à-jour-automatiques)

---

## 1. Build & packaging

### 1.1 electron-builder

L'application utilise **electron-builder** pour créer des paquets multi-plateformes :

```bash
npm run build           # Config par défaut (electron-builder.yml)
npm run build:caddy     # Config avec Caddy embarqué
```

**Outputs :**

| Platform | Format | Chemin de sortie |
|----------|--------|------------------|
| Windows  | NSIS   | `dist/Duplicator-Setup-<version>.exe` |
| Linux    | AppImage | `dist/Duplicator-<version>.AppImage` |
| macOS    | DMG    | `dist/Duplicator-<version>.dmg` |

**Configuration :** `package.json > "build"` + `electron-builder-caddy.yml`

### 1.2 Fichiers inclus dans le build

```
"files" array in package.json:
├── main-caddy.js
├── main.js
├── preload.js
├── Caddyfile
├── php-fpm.conf
├── app/**/*           (toute l'application PHP)
├── php/**/*           (PHP portable Windows)
├── ghostscript/**/*   (binaires GS)
├── caddy/**/*         (binaire Caddy)
├── src/**/*           (modules natifs)
├── utils/**/*
└── docs/              (documentation utilisateur)
```

**Exclusions :**
```
"!app/vendor/phpunit/**/*"
"!app/vendor/pestphp/**/*"
"!app/vendor/sebastian/**/*"
"!app/vendor/**/tests/**/*"
```

### 1.3 asarUnpack

Certains fichiers **ne doivent pas être compacts en asar** (archive Electron) car ils doivent être accessibles en écriture ou exécutables :

```json
"asarUnpack": [
  "php/**/*",          // PHP CLI doit être exécutable
  "app/**/*",          // Accès fichiers temporaires
  "ghostscript/**/*",  // Binaires exécutables
  "caddy/**/*",        // Binaire serveur
  "src/**/*",          // Addons Node.js .node
  "utils/**/*"
]
```

Résultat : ces dossiers sont copiés **tels quels** à côté de l'asar, accessibles via `process.resourcesPath`.

---

## 2. Scripts npm disponibles

### 2.1 Développement

```bash
npm install
  # Installe Electron + dépendances Node + devDependencies

npm start              # Lance Electron (main-caddy.js) avec flags
  # → electron . --no-sandbox --disable-gpu

npm start:php          # Mode léger (sans Caddy)
  # → electron main.js --no-sandbox --disable-gpu

npm start:caddy        # Mode complet (Caddy + PHP) — défaut
  # → electron main-caddy.js --no-sandbox --disable-gpu

npm run dev            # Mode dev avec NODE_ENV=development (DevTools auto-ouvert)
  # → cross-env NODE_ENV=development electron . --no-sandbox --disable-gpu
```

### 2.2 Build

```bash
npm run build
  # → electron-builder (utilise electron-builder.yml ou package.json "build")

npm run build:caddy
  # → electron-builder --config electron-builder-caddy.yml
  # (force inclusion de Caddy embarqué)

npm run rebuild:print-engine
  # → cd src/print-engine/windows && node-gyp rebuild
  # Recompile l'addon natif C++ pour la plateforme courante
```

### 2.3 Téléchargement binaires externes

```bash
npm run download-caddy
  # Télécharge Caddy depuis releases.caddy.com (selon OS/arch)

npm run download-php
  # Télécharge PHP portable Windows (ou Linux selon config)

npm run download-all
  # Télécharge tout (Caddy + PHP + éventuellement GS/IM si pas bundlé)
```

**Scripts :** `scripts/download-caddy.js` — contient logique de download avec fallback miroirs.

### 2.4 Tests

```bash
npm test                    # Tous les tests (Jest + Pest)
npm run test:unit          # Tests unitaires isolés
npm run test:integration   # Tests intégration (Caddy + PHP simple)
npm run test:e2e           # Tests end-to-end (Electron app via Spectron)
npm run test:watch         # Watch mode (redétection changements)
npm run test:coverage      # Génère coverage (lcov, html)
```

**Configuration Jest** (`package.json > jest`):
- `testEnvironment: node`
- `testMatch: ["**/tests/**/*.test.js"]`
- `collectCoverageFrom: ["main*.js", "scripts/**/*.js"]`
- Output : `coverage/`

---

## 3. Configuration electron-builder

### 3.1 electron-builder-caddy.yml

```yaml
appId: com.dupli.beta
productName: Duplicator
directories:
  output: dist
files:
  - main-caddy.js
  - main.js
  - preload.js
  - Caddyfile
  - php-fpm.conf
  - app/**
  - php/**
  - ghostscript/**
  - caddy/**
  - src/**
  - utils/**
asarUnpack:
  - php/**
  - app/**
  - ghostscript/**
  - caddy/**
  - src/**
  - utils/**

linux:
  target: AppImage
  category: Office
  executableName: Duplicator
  executableArgs:
    - --no-sandbox
    - --disable-gpu
    - --disable-setuid-sandbox

mac:
  target: dmg
  category: public.app-category.productivity

win:
  target:
    - target: nsis
      arch: [x64]
  forceCodeSigning: false
  requestedExecutionLevel: requireAdministrator

nsis:
  include: build/installer.nsh
  oneClick: false
  allowToChangeInstallationDirectory: true
  createDesktopShortcut: true
  createStartMenuShortcut: true
  shortcutName: Duplicator
  deleteAppDataOnUninstall: false
  artifactName: Duplicator-Setup-${version}.exe
```

**NSIS options :**
- `oneClick: false` → wizard avec choix dossier
- `allowToChangeInstallationDirectory: true` → utilisateur choisit
- `createDesktopShortcut: true` → raccourci bureau créé
- `deleteAppDataOnUninstall: false` → conserve données (dupli.db)

### 3.2 electron-builder-beta.yml

Variante pour builds beta (peut inclure :
- `publish: [{ provider: github, owner: muarf, repo: dupli-electron-caddy }]` pour auto-release
- `mac: notarize: true` (si Apple Developer)
)

### 3.3 Build variables

```bash
# Version depuis package.json
npm version patch      # bump 2.0.0 → 2.0.1
# electron-builder récupère automatiquement

# Build avec platform spécifique
electron-builder --win --x64
electron-builder --linux
electron-builder --mac

# Build pour toutes les plateformes (long)
electron-builder --win --linux --mac
```

---

## 4. Script déploiement automatique

**Fichier :** `app/deploy-script.sh`

### 4.1 Fonctionnalités

Script bash **idempotent** qui automatise :

1. **Commit & push** des changements dans dépôt `dupli-php-dev`
2. **Pull** dans `dupli-electron-caddy/app`
3. **Bump version** (incrément patch)
4. **Git tag** + push (release GitHub)

### 4.2 Exécution typique

```bash
cd /root/dupli-electron-caddy
./app/deploy-script.sh
```

**Sortie :**
```
🚀 Début du processus de déploiement...
📝 1. Commit et push des changements dans dupli-php-dev...
✅ Changements commités et pushés dans dupli-php-dev
📥 2. Pull des changements dans dupli-electron-caddy/app...
✅ Changements récupérés dans dupli-electron-caddy/app
🏷️ 3. Création d'une nouvelle release...
🔍 Récupération de la dernière release depuis GitHub...
Dernière release trouvée: v2.0.1
Version actuelle: 2.0.1
Nouvelle version: 2.0.2
✅ Release v2.0.2 créée et publiée

🎉 Déploiement terminé avec succès !
📋 Résumé:
   - Commit pushé dans dupli-php-dev
   - Changements récupérés dans dupli-electron-caddy/app
   - Release v2.0.2 créée
```

### 4.3 Détails techniques

```bash
#!/bin/bash
set -e  # Arrêt sur première erreur

cd /root/dupli-php-dev
git add .
if ! git diff --staged --quiet; then
  git commit -m "feat: Ajout fonctionnalité upload PDF pour aide_machines"
fi
git push origin main

cd /root/dupli-electron-caddy/app
git stash push -m "Stash avant pull"   # si modifications locales
git checkout main
git pull origin main

cd /root/dupli-electron-caddy/
LATEST_TAG=$(git ls-remote --tags origin | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1)
CURRENT_VERSION=${LATEST_TAG#v}
NEW_VERSION=$(node -p "const [maj,min,patch] = '$CURRENT_VERSION'.split('.').map(Number); `${maj}.${min}.${patch+1}`;")

npm version $NEW_VERSION --no-git-tag-version
git add package.json
git commit -m "chore: bump version to $NEW_VERSION - Ajout fonctionnalité PDF upload"
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION: Ajout fonctionnalité upload PDF"
git push origin main
git push origin "v$NEW_VERSION"
```

**Prérequis :**
- Dépôts clonés dans `/root/dupli-php-dev` et `/root/dupli-electron-caddy`
- Credentials Git configurés (SSH key ou token)
- `node` disponible pour calcul version

---

## 5. Tests & qualité

### 5.1 Frameworks

| Type | Framework | Fichiers |
|------|-----------|----------|
| **Node/Electron** | Jest | `tests/**/*.test.js`, `tests/**/*.spec.js` |
| **PHP** | Pest | `tests/Unit/`, `tests/Feature/` |
| **E2E** | Spectron | `tests/e2e/*.js` (Electron app automation) |

### 5.2 Coverage

```bash
npm run test:coverage
# → coverage/
#   ├── lcov.info
#   └── html/ (rapport visuel)
```

**Seuil (package.json jest) :**
```json
"collectCoverageFrom": [
  "main*.js",
  "scripts/**/*.js",
  "!node_modules/**",
  "!dist/**"
]
```

### 5.3 Tests intégration Caddy/PHP

Tests simples (`test:integration:simple`) :
- Démarrage Caddy en arrière-plan
- requête HTTP `GET http://127.0.0.1:8000/`
- Vérifie status 200 + contenu HTML

**Fichier exemple :** `tests/integration/caddy-php-simple.test.js`

### 5.4 Tests unitaires PHP (Pest)

```bash
cd app
./vendor/bin/pest
# ou npm test (script cross-root)
```

**Exemple test :** `tests/Unit/PricingTest.php` — vérifie `get_price_devis()` retourne valeurs attendues.

---

## 6. CI/CD (GitHub Actions)

### 6.1 Workflows disponibles (probables)

```
.github/
└── workflows/
    ├── ci.yml              # Tests + lint sur chaque PR
    ├── build.yml           # Build multi-platform sur release/tag
    ├── publish.yml         # Publication GitHub Releases
    └── auto-update.yml     # Vérif mises à jour dépendances
```

**ci.yml typique :**
```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with: { node-version: '22' }
      - run: npm ci
      - run: npm test
      - run: npm run test:coverage
      - uses: codecov/codecov-action@v3

  build:
    needs: test
    if: github.event_name == 'release' || startsWith(github.ref, 'refs/tags/')
    runs-on: ${{ matrix.os }}
    strategy:
      matrix:
        os: [ubuntu-latest, windows-latest, macos-latest]
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
      - run: npm ci
      - run: npm run build
      - uses: actions/upload-artifact@v3
        with:
          name: ${{ runner.os }}-build
          path: dist/
```

### 6.2 Artifacts

Les builds poussés sur `push` vers `refs/tags/v*` produisent des **artifacts GitHub** :
- `Duplicator-Setup-2.0.1.exe` (Windows)
- `Duplicator-2.0.1.AppImage` (Linux)
- `Duplicator-2.0.1.dmg` (macOS)

### 6.3 Signature code (Windows)

Config `electron-builder.yml` :

```yaml
win:
  certificateFile: path/to/cert.pfx
  certificatePassword: ${{ secrets.CERT_PASSWORD }}
```

Non activé ici (`forceCodeSigning: false`).

---

## 7. Publications releases

### 7.1 Création manuelle (GitHub UI)

1. Aller dans **Releases** du repo
2. **Draft new release**
3. Tag : `v2.0.1` (doit exister : `git tag -a v2.0.1 -m "…" && git push origin v2.0.1`)
4. Titre : `Duplicator v2.0.1`
5. Description : notes de version (changelog)
6. Attacher binaires (upload `dist/*.exe`, `*.AppImage`, `*.dmg`)
7. Publish

### 7.2 Création automatique (deploy-script.sh)

Le script fait :
```bash
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION: …"
git push origin main
git push origin "v$NEW_VERSION"
```

Le push du tag **déclenche automatiquement** un workflow GitHub Actions (si configuré) qui :
1. Checkout code
2. `npm ci`
3. `npm run build`
4. `gh release create v$NEW_VERSION --notes "…" dist/*`

**Note :** Le script ne télécharge pas les binaires — il faut que le CI les build.

---

## 8. Mises à jour automatiques

### 8.1 electron-updater

**Lib :** `electron-updater` (^6.6.2)

**Configuration dans `main-caddy.js` :**

```js
const { autoUpdater } = require('electron-updater');

// Au démarrage
autoUpdater.checkForUpdatesAndNotify();

// Événements
autoUpdater.on('update-available', (info) => {
  dialog.showMessageBox(mainWindow, {
    type: 'info',
    title: 'Mise à jour disponible',
    message: `Version ${info.version} disponible`
  });
});

autoUpdater.on('update-downloaded', (info) => {
  dialog.showMessageBox(mainWindow, {
    title: 'Mise à jour prête',
    message: `Version ${info.version} téléchargée`,
    buttons: ['Redémarrer maintenant']
  }).then(() => autoUpdater.quitAndInstall());
});

autoUpdater.on('error', (err) => console.error('Update error:', err));
```

### 8.2 Providers supportés

| Provider | Configuration | Usage |
|----------|---------------|-------|
| **GitHub** | `autoUpdater.setFeedURL({ provider: 'github', owner: 'muarf', repo: 'dupli-electron-caddy' })` | Releases GitHub publiques |
| **Generic** | HTTP server statique (dossier `dist/` sur site web) | Auto-hébergement |
| **S3** | `provider: 's3'` + bucket credentials | Stockage objet |
| **Custom** | `provider: 'custom'` + URL provider | API maison |

**Important :** Les releases doivent contenir :
- `Duplicator Setup <version>.exe` (Windows)
- `Duplicator-<version>.AppImage` (Linux)
- `Duplicator-<version>.dmg` (macOS)
- `latest.yml` (metadonnées update) généré par electron-builder

### 8.3 Signature updates (macOS/Windows)

Pour macOS : `.zip` signé avec Apple Developer ID requis.  
Pour Windows : code signing facultatif (`forceCodeSigning: false`).

### 8.4 Désactivation (dev)

```js
autoUpdater.checkForUpdatesAndNotify(); // appelé seulement si production
if (process.env.NODE_ENV === 'production') {
  autoUpdater.checkForUpdatesAndNotify();
}
```

---

## 9. Variables d'environnement build

| Variable | Usage |
|----------|------|
| `CSC_LINK` | Chemin certificat Windows (.pfx) |
| `CSC_KEY_PASSWORD` | Mot de passe certificat |
| `GH_TOKEN` | Token GitHub (pour publish releases) |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | S3 provider |
| `APPIMAGE_BUILD` | Force build AppImage (Linux) |

**Exemple CI secret :**
```yaml
env:
  GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  CSC_LINK: ${{ secrets.WINDOWS_CERTIFICATE }}
```

---

## 10. Checklist release

Avant de lancer `deploy-script.sh` ou publish GitHub :

- [ ] Tests passent (`npm test` vert)
- [ ] Version bump dans `package.json` (commit séparé)
- [ ] Changelog mis à jour (`CHANGELOG.md`)
- [ ] Commit propre, pas de fichiers temporaires
- [ ] Build réussi sur 3 plateformes (si CI)
- [ ] Binaires fonctionnent ( smoke test )
- [ ] README à jour (stack, install)
- [ ] `deploy-script.sh` paramètres (dépôts distants corrects)

---

*Fin déploiement & CI — Fin de la documentation générée*
